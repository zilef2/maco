<?php

namespace App\Services;

use App\Models\CierreOrdenProduccion;
use App\Models\FichaTecnica;
use App\Models\KitProceso;
use App\Models\Material;
use App\Models\Merma;
use App\Models\OrdenProduccion;
use App\Models\PendientesProduccion;
use App\Models\ProcesoExterno;
use App\Models\RecepcionCalidad;
use Illuminate\Support\Facades\DB;

/**
 * Borrado en masa de materiales. Un material puede estar referenciado por OPs
 * (como producido, en el kit, como origen de un proceso externo o en la ficha
 * de ensamble) y por fichas técnicas (como procesado o como componente). Esas
 * referencias se borran en cascada, así que el servicio las detecta antes para
 * que el usuario decida si también se eliminan las OPs y fichas involucradas.
 */
class MaterialBulkDeleteService
{
    /**
     * @param  list<int>  $ids
     * @return array{materiales: list<array<string, mixed>>, ordenes: list<array<string, mixed>>, fichas: list<array<string, mixed>>}
     */
    public function dependencias(array $ids): array
    {
        $ids = $this->normalizarIds($ids);

        $ordenesPorMaterial = $this->ordenesPorMaterial($ids);
        $fichasPorMaterial = $this->fichasPorMaterial($ids);

        $materiales = Material::whereIn('id', $ids)
            ->orderBy('nombre_base')
            ->get()
            ->map(fn (Material $material) => [
                'id' => $material->id,
                'codigo' => $material->codigo_material,
                'nombre' => $material->nombre_base,
                'ordenes' => array_values($ordenesPorMaterial[$material->id] ?? []),
                'fichas' => array_values($fichasPorMaterial[$material->id] ?? []),
            ])
            ->values()
            ->all();

        return [
            'materiales' => $materiales,
            'ordenes' => $this->ordenarPorNumero($this->collectUnique($ordenesPorMaterial)),
            'fichas' => $this->collectUnique($fichasPorMaterial),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @param  bool  $conDependientes  elimina también las OPs y fichas referenciadas
     * @return array{eliminados: int, omitidos: list<array<string, mixed>>, ordenes_eliminadas: int, fichas_eliminadas: int}
     */
    public function eliminarMasivo(array $ids, bool $conDependientes): array
    {
        $ids = $this->normalizarIds($ids);
        $dependencias = $this->dependencias($ids);

        $aEliminar = [];
        $omitidos = [];

        foreach ($dependencias['materiales'] as $material) {
            $tieneDependencias = !empty($material['ordenes']) || !empty($material['fichas']);
            if ($tieneDependencias && !$conDependientes) {
                $omitidos[] = [
                    'id' => $material['id'],
                    'codigo' => $material['codigo'],
                    'nombre' => $material['nombre'],
                    'ordenes' => $material['ordenes'],
                    'fichas' => $material['fichas'],
                ];
                continue;
            }

            $aEliminar[] = (int) $material['id'];
        }

        $ordenesABorrar = $conDependientes ? array_column($dependencias['ordenes'], 'id') : [];
        $fichasABorrar = $conDependientes ? array_column($dependencias['fichas'], 'id') : [];

        DB::transaction(function () use ($aEliminar, $ordenesABorrar, $fichasABorrar) {
            $this->borrarOrdenes($ordenesABorrar);
            FichaTecnica::whereIn('id', $fichasABorrar)->delete();

            // Los movimientos del material no tienen llave foránea: se limpian a mano.
            DB::table('material_movimientos')->whereIn('material_id', $aEliminar)->delete();
            Material::whereIn('id', $aEliminar)->delete();
        });

        return [
            'eliminados' => count($aEliminar),
            'omitidos' => $omitidos,
            'ordenes_eliminadas' => count($ordenesABorrar),
            'fichas_eliminadas' => count($fichasABorrar),
        ];
    }

    /**
     * @param  list<int>  $opIds
     */
    private function borrarOrdenes(array $opIds): void
    {
        if (empty($opIds)) {
            return;
        }

        // Las mermas apuntan a la OP, al proceso externo y a la recepción, así
        // que se eliminan primero. Recepciones y cierres tienen FK RESTRICT.
        Merma::whereIn('orden_produccion_id', $opIds)->delete();
        RecepcionCalidad::whereIn('orden_produccion_id', $opIds)->delete();
        ProcesoExterno::whereIn('orden_produccion_id', $opIds)->delete();
        PendientesProduccion::whereIn('orden_produccion_id', $opIds)->delete();
        KitProceso::whereIn('orden_produccion_id', $opIds)->delete();
        CierreOrdenProduccion::whereIn('orden_produccion_id', $opIds)->delete();

        // El pivote de materiales, los costos, los ensambles y los compromisos
        // se van en cascada con la OP.
        OrdenProduccion::whereIn('id', $opIds)->delete();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<array<string, mixed>>>
     */
    private function ordenesPorMaterial(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $pares = DB::table('orden_produccion_material')
            ->whereIn('material_id', $ids)
            ->get(['material_id', 'orden_produccion_id'])
            ->merge(DB::table('kit_procesos')->whereIn('material_id', $ids)->get(['material_id', 'orden_produccion_id']))
            ->merge(DB::table('proceso_externos')->whereIn('material_origen_id', $ids)->get(['material_origen_id as material_id', 'orden_produccion_id']))
            ->merge(
                DB::table('ficha_ensamble_materials as fem')
                    ->join('ficha_ensambles as fe', 'fe.id', '=', 'fem.ficha_ensamble_id')
                    ->whereIn('fem.material_id', $ids)
                    ->get(['fem.material_id', 'fe.orden_produccion_id'])
            )
            ->filter(fn ($par) => $par->orden_produccion_id !== null)
            ->unique(fn ($par) => $par->material_id . '-' . $par->orden_produccion_id);

        $ordenes = OrdenProduccion::with('estadoOp:id,nombre')
            ->whereIn('id', $pares->pluck('orden_produccion_id')->unique())
            ->get()
            ->mapWithKeys(fn (OrdenProduccion $op) => [$op->id => [
                'id' => $op->id,
                'numero_op' => $op->numero_op ?? ('OP #' . $op->id),
                'estado' => $op->estadoOp?->nombre ?? 'sin estado',
            ]])
            ->all();

        $porMaterial = [];
        foreach ($pares as $par) {
            $op = $ordenes[$par->orden_produccion_id] ?? null;
            if ($op) {
                $porMaterial[$par->material_id][$op['id']] = $op;
            }
        }

        return $porMaterial;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<array<string, mixed>>>
     */
    private function fichasPorMaterial(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $pares = DB::table('ficha_tecnica_material')
            ->whereIn('material_id', $ids)
            ->get(['material_id', 'ficha_tecnica_id'])
            ->merge(
                DB::table('ficha_tecnica_materiales')
                    ->whereIn('material_componente_id', $ids)
                    ->get(['material_componente_id as material_id', 'ficha_tecnica_id'])
            )
            ->unique(fn ($par) => $par->material_id . '-' . $par->ficha_tecnica_id);

        $fichas = FichaTecnica::whereIn('id', $pares->pluck('ficha_tecnica_id')->unique())
            ->get()
            ->mapWithKeys(fn (FichaTecnica $ficha) => [$ficha->id => [
                'id' => $ficha->id,
                'nombre' => $ficha->nombre ?? ('Ficha #' . $ficha->id),
            ]])
            ->all();

        $porMaterial = [];
        foreach ($pares as $par) {
            $ficha = $fichas[$par->ficha_tecnica_id] ?? null;
            if ($ficha) {
                $porMaterial[$par->material_id][$ficha['id']] = $ficha;
            }
        }

        return $porMaterial;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $porMaterial
     * @return list<array<string, mixed>>
     */
    private function collectUnique(array $porMaterial): array
    {
        $unicos = [];
        foreach ($porMaterial as $lista) {
            foreach ($lista as $item) {
                $unicos[$item['id']] = $item;
            }
        }

        return array_values($unicos);
    }

    /**
     * @param  list<array<string, mixed>>  $ordenes
     * @return list<array<string, mixed>>
     */
    private function ordenarPorNumero(array $ordenes): array
    {
        usort($ordenes, fn ($a, $b) => strcmp((string) $a['numero_op'], (string) $b['numero_op']));

        return $ordenes;
    }

    /**
     * @param  array<int|string>  $ids
     * @return list<int>
     */
    private function normalizarIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
