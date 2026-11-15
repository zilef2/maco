<?php

namespace Database\Seeders;

use App\Models\CompromisoReparacion;
use App\Models\Distribucion_por_tallas;
use App\Models\EstadoOperacion;
use App\Models\FichaTecnica;
use App\Models\FichaTecnicaMateriales;
use App\Models\KitProceso;
use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\Producto;
use App\Models\RecepcionCalidad;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder determinista para pruebas E2E del m�dulo Orden de Producci�n.
 * Crea su propia data de referencia (usuario, estados, taller, producto con
 * ficha, materiales) y tres OP preconfiguradas en los estados necesarios para
 * cubrir los cuatro escenarios de prueba.
 */
class E2EOrdenProduccionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->crearUsuario();
            $estados = $this->obtenerEstados();
            $taller = $this->crearTaller();
            $material = $this->crearMaterial();
            $producto = $this->crearProductoConFicha($material);

            $this->crearOpPreparacion($estados, $taller, $producto, $material);
            $this->crearOpEsperando($estados, $taller, $producto, $material);
            $this->crearOpEnTallerConReparacion($estados, $taller, $producto);
        });
    }

    private function crearUsuario(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'e2e@maco.test'],
            [
                'name' => 'Usuario E2E',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        $rol = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->syncRoles([$rol->id]);

        $permisos = [
            'create OrdenProduccion',
            'update OrdenProduccion',
            'read OrdenProduccion',
            'delete OrdenProduccion',
        ];
        foreach ($permisos as $permiso) {
            \App\Models\Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
            $user->givePermissionTo($permiso);
        }
    }

    /**
     * Estados ya sembrados por EstadoOperacionSeeder; se recuperan por nombre.
     *
     * @return array<string, \App\Models\EstadoOperacion>
     */
    private function obtenerEstados(): array
    {
        $nombres = [
            'Borrador', 'Preparación', 'Esperando materiales', 'Lista para taller',
            'En taller', 'Recibiendo', 'Cerrada',
        ];
        $estados = [];
        foreach ($nombres as $nombre) {
            $estados[$nombre] = EstadoOperacion::where('nombre', $nombre)->firstOrFail();
        }
        return $estados;
    }

    private function crearTaller(): \App\Models\Tercero
    {
        return \App\Models\Tercero::updateOrCreate(
            ['tipo' => 'Taller', 'nombre' => 'Taller E2E'],
            [
                'nombre_comercial' => 'Taller E2E',
                'direccion' => 'Cra. E2E # 1-2',
                'que_suministra' => 'Confecci�n',
            ]
        );
    }

    private function crearMaterial(): Material
    {
        return Material::updateOrCreate(
            ['codigo_material' => 'MAT-E2E-001'],
            [
                'nombre_base' => 'Tela E2E',
                'categoria' => 'Tela',
                'unidad_inventario' => 'Metros',
                'estado' => 'Activo',
                'existencia_actual' => 100,
            ]
        );
    }

    private function crearProductoConFicha(Material $material): Producto
    {
        $producto = Producto::updateOrCreate(
            ['Codigo_interno' => 'E2E-PROD-001'],
            [
                'ID_producto_Shopify' => 'gid://shopify/Product/E2E-PROD-001',
                'Categoria_original' => 'Calzado > Deportivo',
                'Familia_normalizada' => 'Zapatillas',
                'Titulo_Shopify' => 'Producto E2E',
                'Control_de_variante' => 'Talla',
                'Opciones' => 'Única',
                'Estado' => 'Activo',
            ]
        );

        $ficha = FichaTecnica::updateOrCreate(
            ['nombre' => 'Ficha E2E Producto'],
        );
        $ficha->productos()->syncWithoutDetaching([$producto->id]);

        FichaTecnicaMateriales::updateOrCreate(
            ['ficha_tecnica_id' => $ficha->id, 'material_componente_id' => $material->id],
            [
                'consumo_estandar' => 1,
                'unidad_medida' => 'Metros',
                'costo_asociado_teorico' => 1000,
            ]
        );

        return $producto;
    }

    private function crearOpPreparacion(array $estados, \App\Models\Tercero $taller, Producto $producto, Material $material): void
    {
        $op = OrdenProduccion::updateOrCreate(
            ['numero_op' => 'E2E-OP-PREP'],
            [
                'producto_referencia_id' => $producto->id,
                'cantidad_planificada' => 2,
                'tipo' => 'taller',
                'taller_id' => $taller->id,
                'estado_op_id' => $estados['Preparación']->id,
                'user_id' => $this->usuario()->id,
                'estado_op_material_id' => null,
            ]
        );
        $op->productos()->sync([$producto->id]);

        Distribucion_por_tallas::updateOrCreate(
            ['producto_id' => $producto->id, 'talla' => 'Única'],
            ['cantidad_planificada' => 2]
        );
        $dist = Distribucion_por_tallas::where('producto_id', $producto->id)
            ->where('talla', 'Única')->first();
        $op->distribucionesPorTallas()->sync([$dist->id]);

        // Kit incompleto: se requiere m�s de lo disponible.
        KitProceso::updateOrCreate(
            ['orden_produccion_id' => $op->id, 'material_id' => $material->id],
            [
                'unidad' => 'Metros',
                'cantidad_requerida' => 10,
                'cantidad_disponible' => 3,
                'cantidad_preparada' => null,
            ]
        );
    }

    private function crearOpEsperando(array $estados, \App\Models\Tercero $taller, Producto $producto, Material $material): void
    {
        $op = OrdenProduccion::updateOrCreate(
            ['numero_op' => 'E2E-OP-ESP'],
            [
                'producto_referencia_id' => $producto->id,
                'cantidad_planificada' => 2,
                'tipo' => 'taller',
                'taller_id' => $taller->id,
                'estado_op_id' => $estados['Esperando materiales']->id,
                'user_id' => $this->usuario()->id,
                'esperando_materiales_desde' => now(),
                'estado_op_material_id' => null,
            ]
        );
        $op->productos()->sync([$producto->id]);

        Distribucion_por_tallas::updateOrCreate(
            ['producto_id' => $producto->id, 'talla' => 'Única'],
            ['cantidad_planificada' => 2]
        );
        $dist = Distribucion_por_tallas::where('producto_id', $producto->id)
            ->where('talla', 'Única')->first();
        $op->distribucionesPorTallas()->sync([$dist->id]);

        // Kit incomplete: faltan materiales -> no puede pasar a "Lista para taller".
        KitProceso::updateOrCreate(
            ['orden_produccion_id' => $op->id, 'material_id' => $material->id],
            [
                'unidad' => 'Metros',
                'cantidad_requerida' => 10,
                'cantidad_disponible' => 3,
                'cantidad_preparada' => null,
            ]
        );
    }

    private function crearOpEnTallerConReparacion(array $estados, \App\Models\Tercero $taller, Producto $producto): void
    {
        $op = OrdenProduccion::updateOrCreate(
            ['numero_op' => 'E2E-OP-REP'],
            [
                'producto_referencia_id' => $producto->id,
                'cantidad_planificada' => 2,
                'tipo' => 'taller',
                'taller_id' => $taller->id,
                'estado_op_id' => $estados['En taller']->id,
                'user_id' => $this->usuario()->id,
                'estado_op_material_id' => null,
            ]
        );
        $op->productos()->sync([$producto->id]);

        Distribucion_por_tallas::updateOrCreate(
            ['producto_id' => $producto->id, 'talla' => 'Única'],
            ['cantidad_planificada' => 2]
        );
        $dist = Distribucion_por_tallas::where('producto_id', $producto->id)
            ->where('talla', 'Única')->first();
        $op->distribucionesPorTallas()->sync([$dist->id]);

        // Recepcion con pares en reparaci�n -> no puede cerrarse.
        RecepcionCalidad::updateOrCreate(
            ['orden_produccion_id' => $op->id, 'talla' => 'Única', 'producto_referencia_id' => $producto->id],
            [
                'material_referencia_id' => null,
                'cantidad_esperada' => 2,
                'cantidad_recibida' => 2,
                'cantidad_aprobada' => 1,
                'cantidad_en_reparacion' => 1,
                'cantidad_no_recuperable' => 0,
                'fecha_recepcion' => now(),
            ]
        );

        CompromisoReparacion::updateOrCreate(
            ['orden_produccion_id' => $op->id, 'talla' => 'Única', 'producto_referencia_id' => $producto->id],
            [
                'material_referencia_id' => null,
                'cantidad_en_reparacion' => 1,
                'fecha_compromiso' => now()->addDays(5)->toDateString(),
                'atendido' => false,
            ]
        );
    }

    private function usuario(): User
    {
        return User::where('email', 'e2e@maco.test')->firstOrFail();
    }
}
