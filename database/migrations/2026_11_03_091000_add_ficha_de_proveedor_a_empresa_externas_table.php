<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La empresa externa pasa a tener la misma ficha que el proveedor y el taller.
 * `actividad` y `numero_contacto` no se pierden: se mudan a `que_suministra`
 * y `telefono_principal`, que son los nombres que usa el archivo de Excel.
 */
return new class extends Migration
{
    private const CAMPOS_NUEVOS = [
        'codigo_empresa_externa',
        'nit',
        'nombre_comercial',
        'razon_social',
        'observaciones_direccion',
        'correo_electronico',
        'ciudad',
        'telefono_principal',
        'tipo_proveedor',
        'que_suministra',
        'persona_contacto',
        'celular_contacto',
        'forma_pago',
        'tiempo_entrega',
        'estado',
        'observaciones_generales',
    ];

    public function up(): void
    {
        Schema::table('empresa_externas', function (Blueprint $table) {
            $table->string('codigo_empresa_externa')->nullable()->after('id');
            $table->string('nit')->nullable()->after('codigo_empresa_externa');
            $table->string('nombre_comercial')->nullable()->after('nit');
            $table->string('razon_social')->nullable()->after('direccion');
            $table->text('observaciones_direccion')->nullable()->after('razon_social');
            $table->string('correo_electronico')->nullable()->after('observaciones_direccion');
            $table->string('ciudad')->nullable()->after('correo_electronico');
            $table->string('telefono_principal')->nullable()->after('ciudad');
            $table->string('tipo_proveedor')->nullable()->after('telefono_principal');
            $table->text('que_suministra')->nullable()->after('tipo_proveedor');
            $table->string('persona_contacto')->nullable()->after('que_suministra');
            $table->string('celular_contacto')->nullable()->after('persona_contacto');
            $table->string('forma_pago')->nullable()->after('celular_contacto');
            $table->string('tiempo_entrega')->nullable()->after('forma_pago');
            $table->string('estado')->default('Activo')->after('tiempo_entrega');
            $table->text('observaciones_generales')->nullable()->after('estado');
        });

        DB::statement('UPDATE empresa_externas
            SET que_suministra = actividad,
                telefono_principal = numero_contacto,
                codigo_empresa_externa = CONCAT(\'EMP-\', LPAD(id, 3, \'0\'))');

        DB::statement('UPDATE empresa_externas SET nombre_comercial = nombre WHERE nombre_comercial IS NULL AND nombre IS NOT NULL');

        Schema::table('empresa_externas', function (Blueprint $table) {
            $table->dropColumn(['actividad', 'numero_contacto']);
        });
    }

    public function down(): void
    {
        Schema::table('empresa_externas', function (Blueprint $table) {
            $table->string('actividad')->nullable()->after('nombre');
            $table->string('numero_contacto')->nullable()->after('direccion');
        });

        DB::statement('UPDATE empresa_externas
            SET actividad = que_suministra,
                numero_contacto = telefono_principal');

        Schema::table('empresa_externas', function (Blueprint $table) {
            $table->dropColumn(self::CAMPOS_NUEVOS);
        });
    }
};
