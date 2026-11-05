<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgarMaterialesRepository
{
    /**
     * Tablas que mencionan un material y no pueden quedar apuntando a la nada.
     *
     * `kit_procesos`, `proceso_externos` y `ficha_ensamble_materials` tienen clave
     * foránea RESTRICT/NO ACTION sobre una columna NOT NULL: bloquean el borrado
     * del material. `material_movimientos` y `procesos_externos` no tienen clave
     * foránea, así que sin borrarlas quedarían huérfanas en silencio. Las demás
     * tablas se resuelven solas con ON DELETE CASCADE o SET NULL.
     *
     * @var array<string, string> tabla => columna que apunta al material
     */
    private const REFERENCIAS = [
        'material_movimientos' => 'material_id',
        'procesos_externos' => 'material_origen_id',
        'kit_procesos' => 'material_id',
        'proceso_externos' => 'material_origen_id',
        'ficha_ensamble_materials' => 'material_id',
    ];

    /**
     * @return array{materiales: int, variantes: int, precios: int, vinculos: int}
     */
    public function purgar(): array
    {
        return DB::transaction(function (): array {
            $conteo = [
                'materiales' => DB::table('materials')->count(),
                'variantes' => DB::table('material_variants')->count(),
                'precios' => DB::table('material_prices')->count(),
                'vinculos' => 0,
            ];

            foreach (self::REFERENCIAS as $tabla => $columna) {
                if (Schema::hasTable($tabla)) {
                    $conteo['vinculos'] += DB::table($tabla)->whereNotNull($columna)->delete();
                }
            }

            // La cascada se lleva variantes, precios, fichas técnicas y los costos
            // de material de las OPs; recepciones, mermas y distribuciones quedan
            // en NULL, que es lo que son: historia de un material que ya no existe.
            DB::table('materials')->delete();

            return $conteo;
        });
    }
}
