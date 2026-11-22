<?php

namespace Tests\Feature\OrdenProduccion\Concerns;

use App\Models\Distribucion_por_tallas;
use App\Models\EstadoOpMaterial;
use App\Models\EstadoOperacion;
use App\Models\FichaTecnica;
use App\Models\Material;
use App\Models\OrdenProduccion;
use App\Models\Producto;
use App\Models\Role;
use App\Models\Tercero;
use App\Models\User;

/**
 * Fixtures deterministas para la suite de extremo a extremo de Órdenes de
 * Producción.
 *
 * Todo se crea con datos propios (estados, taller, materiales, fichas,
 * productos y usuarios) sin depender de seeders ni de las demás suites. Los
 * catálogos de estados reproducen el flujo real de la aplicación:
 *
 *   Borrador → Preparación → Esperando materiales → Lista para taller
 *            → En taller → Recibiendo → Cerrada
 *
 * Las transiciones se declaran igual que en `EstadoOperacionSeeder`, de modo
 * que `cambiarEstado` valide contra `puede_pasar_a` las mismas rutas que en
 * producción.
 */
trait SeedsOrdenesProduccion
{
    /** Flujo de estados de la OP, con las transiciones permitidas reales. */
    protected const FLUJO_ESTADOS = [
        'Borrador' => 'Preparación',
        'Preparación' => 'Esperando materiales / Lista para taller',
        'Esperando materiales' => 'Preparación / Lista para taller',
        'Lista para taller' => 'En taller',
        'En taller' => 'Recibiendo',
        'Recibiendo' => null,
        'Cerrada' => null,
    ];

    /** Roles que usa la suite. Se crean una sola vez por test. */
    protected function rol(string $nombre = 'admin'): Role
    {
        return Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
    }

    /**
     * Usuario autenticable con el rol indicado. Se crea con `User::factory`
     * para no depender de seeders.
     */
    protected function usuario(?string $rol = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole($this->rol($rol ?? 'admin'));

        return $user;
    }

    /**
     * Catálogo de estados de la OP, indexado por nombre.
     *
     * @return array<string, EstadoOperacion>
     */
    protected function estados(): array
    {
        $estados = [];

        foreach (self::FLUJO_ESTADOS as $nombre => $puedePasarA) {
            $estados[$nombre] = EstadoOperacion::firstOrCreate(
                ['nombre' => $nombre],
                [
                    'cuando_aplica' => 'Estado de la OP: ' . $nombre,
                    'puede_pasar_a' => $puedePasarA,
                    'regla_clave' => 'Definido por la suite E2E de OP.',
                ]
            );
        }

        return $estados;
    }

    /**
     * Estado de materiales de la OP (independiente del estado principal).
     */
    protected function estadoMaterial(string $nombre = 'Pendiente', string $valor = 'pendiente'): EstadoOpMaterial
    {
        return EstadoOpMaterial::firstOrCreate(
            ['nombre' => $nombre],
            ['valor' => $valor]
        );
    }

    /**
     * Taller (Tercero con tipo "Taller").
     */
    protected function taller(string $nombre = 'Taller E2E'): Tercero
    {
        return Tercero::firstOrCreate(
            ['tipo' => 'Taller', 'nombre' => $nombre],
            ['direccion' => 'Calle 1 # 2-3', 'que_suministra' => 'Confección']
        );
    }

    /**
     * Empresa externa (Tercero con tipo "Empresa externa").
     */
    protected function empresaExterna(string $nombre = 'Empresa E2E'): Tercero
    {
        return Tercero::firstOrCreate(
            ['tipo' => 'Empresa externa', 'nombre' => $nombre],
            ['nombre_comercial' => $nombre, 'que_suministra' => 'Confección']
        );
    }

    /**
     * Material genérico/concreto con precio opcional.
     *
     * Los materiales se crean con `Material::create` (no factory) porque la
     * regla del código del modelo se aplica en el evento `saving` y debe poder
     * derivarse del par categoría-base.
     *
     * @param  array<string, mixed>  $atributos
     */
    protected function material(array $atributos = []): Material
    {
        $material = Material::create(array_merge([
            'codigo_material' => 'MAT-E2E-' . fake()->unique()->numberBetween(1000, 99999),
            'categoria' => 'Tela',
            'nombre_base' => 'Material E2E',
            'unidad_inventario' => 'm',
            'estado' => 'Activo',
            'existencia_actual' => 100,
        ], $atributos));

        if (array_key_exists('precio', $atributos)) {
            $material->prices()->create([
                'precio' => (float) $atributos['precio'],
                'fecha' => now()->toDateString(),
            ]);
        }

        return $material;
    }

    /**
     * Material procesado con una ficha técnica de `consumo x componente`.
     *
     * Se crea con el flag `material_procesado` y una `FichaTecnica` cuya línea
     * apunta al componente indicado, igual que en el flujo real de la app.
     *
     * @param  array<string, mixed>  $atributosMaterial
     * @return array{material: Material, componente: Material, ficha: FichaTecnica}
     */
    protected function materialProcesado(
        float $consumo = 2,
        float $precioComponente = 10,
        array $atributosMaterial = []
    ): array {
        $componente = $this->material([
            'nombre_base' => 'Componente E2E',
            'existencia_actual' => 100,
            'precio' => $precioComponente,
        ]);

        $material = $this->material(array_merge([
            'nombre_base' => 'Material procesado E2E',
            'material_procesado' => 1,
            'existencia_actual' => 0,
        ], $atributosMaterial));

        $ficha = FichaTecnica::create(['nombre' => 'Ficha ' . $material->codigo_material]);
        $ficha->materiales()->create([
            'material_componente_id' => $componente->id,
            'consumo_estandar' => $consumo,
            'unidad_medida' => 'm',
            'costo_asociado_teorico' => $precioComponente,
        ]);
        $ficha->materialesProcesados()->attach($material->id);

        return compact('material', 'componente', 'ficha');
    }

    /**
     * Producto con una ficha técnica de `consumo x componente`.
     *
     * @return array{producto: Producto, componente: Material, ficha: FichaTecnica}
     */
    protected function productoConFicha(
        float $consumo = 2,
        float $precioComponente = 10,
        string $codigo = 'E2E-PROD-001'
    ): array {
        $componente = $this->material([
            'nombre_base' => 'Componente producto E2E',
            'existencia_actual' => 100,
            'precio' => $precioComponente,
        ]);

        $producto = Producto::create([
            'Codigo_interno' => $codigo,
            'ID_producto_Shopify' => 'shopify-' . strtolower($codigo),
            'Categoria_original' => 'Prueba',
            'Familia_normalizada' => 'Prueba',
            'Titulo_Shopify' => 'Producto ' . $codigo,
            'Control_de_variante' => 'Única',
            'Opciones' => 'Única',
            'Estado' => 'Activo',
        ]);

        $ficha = FichaTecnica::create(['nombre' => 'Ficha ' . $codigo]);
        $ficha->productos()->attach($producto->id);
        $ficha->materiales()->create([
            'material_componente_id' => $componente->id,
            'consumo_estandar' => $consumo,
            'unidad_medida' => 'm',
            'costo_asociado_teorico' => $precioComponente,
        ]);

        return compact('producto', 'componente', 'ficha');
    }

    /**
     * Crea una fila de distribución (talla para producto, o material) y la
     * vincula a la OP.
     *
     * @param  array<string, mixed>  $atributos
     */
    protected function distribucion(
        int $opId,
        array $atributos = []
    ): Distribucion_por_tallas {
        $distribucion = Distribucion_por_tallas::create($atributos);
        OrdenProduccion::findOrFail($opId)
            ->distribucionesPorTallas()
            ->syncWithoutDetaching([$distribucion->id]);

        return $distribucion;
    }
}