<?php

// $ipAddress = $request->ip();

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
php artisan make:command copy:u
*/

class CopyUserPages extends Command
{
    use Constants;

    const MSJ_EXITO = ' fue realizada con exito ';

    const MSJ_FALLO = ' Fallo';

    public $generando;

    protected $signature = 'copy:u';

    protected $description = 'Copia de la entidad generica';

    protected int $contadorMetodos;

    // notacion de notas:
    // //todo:
    // very usefull
    // heyRemember:
    // nexttochange:
    // todo: sync: añadir a los demas repos
    // justtesting: cuando hay que qutiar cosas que solo deberian aparecer en la version de pruebas
    // thisisnew!!!
    protected $thename = 'Presupuesto';

    protected function aagenerateAttributes(): array
    {
        // string text number dinero date datetime boolean foreign json
        return [
            // Core
            'nombre' => 'string',
            'descripcion' => 'text',
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',

            // Montos
            'monto_total' => 'dinero',
            'monto_ejecutado' => 'dinero',
            'monto_disponible' => 'dinero',

            // Relaciones futuras (Reporte, Viaticos, Materiales)
            'total_salarios' => 'dinero',
            'total_viaticos' => 'dinero',
            'total_materiales' => 'dinero',

            // Estado
            'estado' => 'string',   // borrador | aprobado | cerrado | suspendido
            'user_id_aprobado' => 'foreign',   // FK a users implícita, sin foreign key duro
            'aprobado_por' => 'string',
            'fecha_aprobacion' => 'datetime',

            // Opcionales
            'codigo_interno' => 'string',
            'observaciones' => 'text',
            'porcentaje_ejecutado' => 'float',   // calculable pero útil persistirlo para reportes

            // 💡 Novedad 1: alerta de sobreejecution
            'umbral_alerta' => 'float',   // ej: 0.85 → alerta cuando ejecutado >= 85% del total

            // 💡 Novedad 2: vigencia de reajuste
            'fecha_ultimo_reajuste' => 'datetime', // cuándo se modificó el monto_total por última vez
            'motivo_reajuste' => 'text',     // justificación del cambio de presupuesto
        ];
    }

    public function handle(): int
    {
        try {
            $this->generando = self::getMessage('generando');

            $this->contadorMetodos = 0;
            $submetodo['Lenguaje'] = 0;

            // $modelName = $this->ask('¿Cuál es el nombre del modelo? Recuerde revisar los atributos.');
            // if (! $modelName || $modelName == '') {
            //     $this->info('Sin modelo');

            //     return 0;
            // }
            $modelName = $this->thename;

            if (! $this->ValidacionesPrevias($modelName, 'generic')) {
                $this->error('Las validaciones previas fallaron. Ejecución cancelada.');

                return 0;
            }

            $progressBar = $this->output->createProgressBar(2);
            $progressBar->start();

            $this->MetodologiaInicial($modelName, 'generic', '');
            $this->AddAttributesVue($modelName);3
            $this->Paso2($modelName, $submetodo);

            // $this->info('generando permisos');
            // $this->info(Artisan::call('make:permission-seeder '.$modelName));
            // $progressBar->advance();

            $this->info(Artisan::call('optimize'));
            $this->info(Artisan::call('optimize:clear'));
            $progressBar->advance();
            $progressBar->finish();

            return 1;
        } catch (Exception $e) {
            $this->error('FALLO CONTADOR: '.$this->contadorMetodos.'FALLO Lenguaje: '.$submetodo['Lenguaje'].' excepcion: '.$e->getMessage());

            return 0;
        }

    }

    public function MetodologiaInicial(mixed $modelName, string $plantillaActual, mixed $depende): int
    {
        $this->warn('Empezando make:model');
        Artisan::call('make:model', ['name' => $modelName, '--all' => true]);

        // comandos de dependencias
        $this->warn('Empezando copies');
        Artisan::call('copy:f'); // Commands/WriteFillable.php
        $this->warn('Ahora Lang');
        Artisan::call('lang:u '.$modelName); // LanguageCopyU.php

        $RealizoVueConExito = $this->MakeVuePages($plantillaActual, $modelName);
        $mensaje = $RealizoVueConExito ? self::getMessage('generando').' Vuejs'.self::MSJ_EXITO : self::getMessage('generando').' Vuejs'.self::getMessage('fallo');
        $this->info($mensaje);

        $RealizoControllerConExito = $this->MakeControllerPages($plantillaActual, $modelName);
        $mensaje = $RealizoControllerConExito ? self::getMessage('generando').'el controlador'.self::MSJ_EXITO : self::getMessage('generando').' controlador '.self::getMessage('fallo');
        $this->info($mensaje);

        if ($RealizoControllerConExito || $RealizoVueConExito) {
            $this->replaceWordInFiles($plantillaActual, [
                'vue' => $RealizoVueConExito,
                'controller' => $RealizoControllerConExito,
            ], $modelName, $depende);
        }

        return 1;
    }

    private function ValidacionesPrevias(string $modelName, string $plantillaActual): bool
    {
        $valido = true;

        // 1. Plantilla del controlador origen
        $controllerOrigen = base_path('app/Console/Commands/'.$plantillaActual.'Controller.stub');
        if (! File::exists($controllerOrigen)) {
            $this->error("[1] No existe el controlador plantilla: $controllerOrigen");
            $valido = false;
        }

        // 2. Carpeta Vue origen
        $vueOrigen = base_path('resources/js/Pages/'.$plantillaActual);
        if (! File::exists($vueOrigen)) {
            $this->error("[2] No existe la carpeta Vue origen: $vueOrigen");
            $valido = false;
        }

        // 3. Index.vue de la plantilla (requerido por AddAttributesVue)
        $indexVue = resource_path("js/Pages/$plantillaActual/Index.vue");
        if (! File::exists($indexVue)) {
            $this->error("[3] No existe el Index.vue de la plantilla: $indexVue");
            $valido = false;
        }

        // 4. Destino Vue no debe existir
        $vueDestino = base_path("resources/js/Pages/$modelName");
        if (File::exists($vueDestino)) {
            $this->error("[4] La carpeta Vue destino ya existe: $vueDestino");
            $valido = false;
        }

        // 5. Controlador destino no debe existir
        $controllerDestino = base_path('app/Http/Controllers/'.ucfirst($modelName).'sController.php');
        if (File::exists($controllerDestino)) {
            $this->error("[5] El controlador destino ya existe: $controllerDestino");
            $valido = false;
        }

        // 6. El modelo no debe existir previamente
        $modeloPath = app_path("Models/$modelName.php");
        if (File::exists($modeloPath)) {
            $this->error("[6] El modelo ya existe: $modeloPath");
            $valido = false;
        }

        // 7. routes/web.php con marcador //aquipues
        $webPhp = base_path('routes/web.php');
        if (! File::exists($webPhp) || ! str_contains(file_get_contents($webPhp), '//aquipues')) {
            $this->error('[7] routes/web.php no existe o no contiene el marcador //aquipues');
            $valido = false;
        }

        // 8. lang/es/app.php con marcador //aquipues
        $langFile = base_path('lang/es/app.php');
        if (! File::exists($langFile) || ! str_contains(file_get_contents($langFile), '//aquipues')) {
            $this->error('[8] lang/es/app.php no existe o no contiene el marcador //aquipues');
            $valido = false;
        }

        // 9. AppSidebar.vue con marcador //aquipuesSide
        $sidebar = base_path('resources/js/components/AppSidebar.vue');
        if (! File::exists($sidebar) || ! str_contains(file_get_contents($sidebar), '//aquipuesSide')) {
            $this->error('[9] AppSidebar.vue no existe o no contiene el marcador //aquipuesSide');
            $valido = false;
        }

        if ($valido) {
            $this->info('Todas las validaciones previas pasaron correctamente.');
        }

        return $valido;
    }

    private function MakeVuePages($plantillaActual, $modelName): bool
    {
        $sourcePath = base_path('resources/js/Pages/'.$plantillaActual);
        $destinationPath = base_path("resources/js/Pages/$modelName");

        // Add this validation
        if (! File::exists($sourcePath)) {
            $this->error("La carpeta de origen '$plantillaActual' no existe.");

            return false;
        }
        if (File::exists($destinationPath)) {
            $this->warn("La carpeta de destino '$modelName' ya existe.");

            return false;
        }
        File::copyDirectory($sourcePath, $destinationPath);

        return true;
    }

    private function MakeControllerPages($plantillaActual, $modelName): bool
    {
        $folderMayus = ucfirst($modelName);
        $sourcePath = base_path('app/Console/Commands/'.$plantillaActual.'Controller.stub');
        if (! File::exists($sourcePath)) {
            $this->error("El controlador de origen '$sourcePath' no existe.");

            return false;
        }

        $destinationPath = base_path('app/Http/Controllers/'.$folderMayus.'sController.php');

        if (File::exists($destinationPath)) {
            $this->warn("La carpeta de destino '$destinationPath' ya existe.");

            return false;
        }
        File::copyDirectory($sourcePath, $destinationPath);
        $this->info('- '.$sourcePath);
        $this->info('- '.$destinationPath);

        return true;
    }

    private function replaceWordInFiles($oldWord, $permiteRemplazo, $modelName, $depende): int
    {
        $folderMayus = ucfirst($modelName);
        $files = File::allFiles(base_path("resources/js/Pages/$modelName"));
        $controller = base_path("app/Http/Controllers/$folderMayus".'Controller.php');

        $depende = $depende == '' || $depende == null ? 'no_nada' : $depende;

        if ($permiteRemplazo['vue']) {
            foreach ($files as $file) {

                $content = file_get_contents($file);
                $content = str_replace([$oldWord, ucfirst($oldWord), 'geeneric'],// ojo aqui, es estatico
                    [$modelName, $folderMayus, $folderMayus], $content);
                file_put_contents($file, $content);
            }
        }

        // reemplazo de controlador
        if ($permiteRemplazo['controller']) {
            $sourcePath = base_path('app/Http/Controllers/'.ucfirst($oldWord).'Controller.php');
            $content = file_get_contents($sourcePath);
            $content = str_replace([$oldWord, 'dependex', 'deependex', 'geeneric'],// TODO:ojo aqui, es estatico
                [$modelName, $depende, ucfirst($depende), ucfirst($modelName)], $content);
            file_put_contents($controller, $content);
        }

        return 1;
    }

    private function AddAttributesVue($modelName): int
    {
        $vueFilePath = resource_path("js/Pages/$modelName/Index.vue");

        if (! File::exists($vueFilePath)) {
            $this->error('El archivo Index.vue no existe.');

            return 0;
        }

        $content = File::get($vueFilePath);

        // Convertir los atributos en formato Vue
        $titulosArray = collect($this->aagenerateAttributes())->map(function ($type, $key) {
            return "    { order: '$key', label: '$key', type: '$type' },";
        })->implode("\n");

        // Expresión regular para encontrar la sección `const titulos = [`
        $pattern = '/const titulos = \[\s*([\s\S]*?)\s*\];/';

        // Reemplazar con los nuevos valores
        $replacement = "const titulos = [\n$titulosArray\n];";

        $newContent = preg_replace($pattern, $replacement, $content);

        if ($newContent) {
            File::put($vueFilePath, $newContent);
            $this->info('Archivo Index.vue actualizado correctamente.');
        } else {
            $this->error('No se pudo actualizar Index.vue.');

            return 0;
        }

        return 1;
    }

    private function Paso2($modelName, &$submetodo): int
    {

        $thereturn = 1;
        // estos metodos para abajo tienen validacion
        if ($this->DoWebphp($modelName)) {

            $this->info('DoWebphp'.__FUNCTION__.' '.self::MSJ_EXITO);
            $this->contadorMetodos++;
        } else {
            $this->error('DoWebphp '.__FUNCTION__.' '.self::MSJ_FALLO);

            return 0;

        }

        if ($this->L2_LenguajeInsert($modelName, $submetodo) === 0) {
            return 0;
        }

        if ($this->DoSideBar($modelName)) {

            $this->info('DoSideBar'.__FUNCTION__.' '.self::MSJ_EXITO);
            $this->contadorMetodos++;
        } else {
            $this->error('DoSideBar '.__FUNCTION__.' '.self::MSJ_FALLO);

            return 0;
        }
        $this->DoFillable($modelName);
        $this->contadorMetodos++;
        $this->updateMigration($modelName);
        $this->contadorMetodos++;
        $this->DoPermissions($modelName);
        $this->contadorMetodos++;

        return 1;
    }

    protected function DoPermissions($modelName): int
    {
        $cruds = ['create', 'read', 'update', 'delete'];
        $permissionsCreated = [];

        foreach ($cruds as $crud) {
            $permName = "$crud $modelName";
            \App\Models\Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
            $permissionsCreated[] = $permName;
        }

        try {
            $roles = \Spatie\Permission\Models\Role::whereIn('name', ['superadmin', 'admin'])->get();
            foreach ($roles as $role) {
                foreach ($permissionsCreated as $permName) {
                    $role->givePermissionTo($permName);
                }
            }
        } catch (\Throwable $e) {
            // Ignorar silenciosamente si no existen los roles aún
        }

        $this->info("Permisos generados exitosamente para $modelName (" . implode(', ', $permissionsCreated) . ")");
        return 1;
    }

    private function DoWebphp($resource): int
    {
        $directory = 'routes';
        $files = glob($directory.'/*.php');

        $insertable = "Route::resource(\"/$resource\", \\App\\Http\\Controllers\\".ucfirst($resource)."Controller::class);\n\t//aquipues";

        $pattern = '/\/\/aquipues/';

        $contadorVerificador = 0;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $contadorVerificador++;

            if (! str_contains($content, $pattern)) {
                $content2 = preg_replace($pattern, $insertable, $content);
                //                $content2 = preg_replace($pattern, "$0$insertable", $content);
                file_put_contents($file, $content2);
                if ($content == $content2) {
                    $this->info("Routes Actualizado: $file\n");
                } else {
                    $this->info("Routes sin cambios: $file\n");
                }
            } else {
                $this->error("No existe aquipues en: $file\n");
                $contadorVerificador = 0;
                break;
            }
        }

        return $contadorVerificador;
    }

    public function L2_LenguajeInsert($modelName, &$submetodo): int
    {
        if ($this->DoAppLenguaje($modelName)) {
            $submetodo['Lenguaje'] = 0;
            $this->info('DoAppLenguaje'.self::MSJ_EXITO);
            $this->contadorMetodos++;

            foreach ($this->aagenerateAttributes() as $key => $generateAttribute) {
                $this->DoAppLenguaje($key);
                $submetodo['Lenguaje']++;
            }
            foreach ($this->generateForeign() as $generateAttribute) {
                $this->DoAppLenguaje($generateAttribute, 'mochar_id');
                $submetodo['Lenguaje']++;
            }

            return 1;
        } else {
            $this->error('DoAppLenguaje '.self::MSJ_FALLO);
            $this->error('$this->contadorMetodos = '.$this->contadorMetodos);
            $this->error('$submetodo = '.$submetodo['Lenguaje']);

            return 0;
        }
    }

    private function DoAppLenguaje($resource, $mochar = 'no'): int
    {
        $directory = 'lang/es/app.php';
        $files = glob($directory);

        if ($mochar == 'mochar_id') {
            $resource_Sin_Id = substr($resource, 0, -3);
            $insertable = "'$resource' => '$resource_Sin_Id',\n\t\t//aquipues";
        } else {
            $insertable = "'$resource' => '$resource',\n\t\t//aquipues";
        }
        $pattern = '/\/\/aquipues/';
        $contadorVerificador = 0;
        foreach ($files as $file) {
            $contadorVerificador++;
            $content = file_get_contents($file);
            if (! str_contains($content, $pattern)) {
                $content2 = preg_replace($pattern, $insertable, $content);
                // $content2 = preg_replace($pattern, "$0$insertable", $content);
                file_put_contents($file, $content2);
                if ($content == $content2) {
                    $this->info("Language Actualizado: $file\n");
                } else {
                    $this->info("Language sin cambios: $file\n");
                }
            } else {
                $this->error("No existe aquipues en: $file\n");
                $contadorVerificador = 0;
                break;
            }
        }

        return $contadorVerificador;

    }

    protected function generateForeign(): array
    {
        return [//			'oferta_id' => 'oferta_id',
        ];
        /*
    //            'valor_consig' => 'biginteger',
    //            'texto' => 'text',
    //            'fecha_legalizacion' => 'datetime',
    //            'descripcion' => 'text',
    //            'precio' => 'decimal',
        */

    }

    private function DoSideBar($resource): int
    {
        $directory = 'resources/js/components/AppSidebar.vue';
        $files = glob($directory);

        $insertable = "{\n\t\ttitle: '$resource',\n\t\thref: '/$resource',\n\t\ticon: LayoutGrid,\n\t},\n\t//aquipuesSide";
        $pattern = '/\/\/aquipuesSide/';

        $contadorVerificador = 0;
        foreach ($files as $file) {
            $content = file_get_contents($file);

            if (! str_contains($content, $pattern)) {
                $contadorVerificador++;
                $content2 = preg_replace($pattern, $insertable, $content);
                file_put_contents($file, $content2);
                if ($content != $content2) {
                    $this->info("SideBarMenu.vue Actualizado: $file\n");
                } else {
                    $this->info("SideBarMenu.vue sin cambios: $file\n");
                } // todo: revisar si ya existe
            } else {
                $this->error("No existe aquipues en: $file\n");
                $contadorVerificador = 0;
                break;
            }
        }

        return $contadorVerificador;
    }

    protected function DoFillable($modelName): int
    {
        $attributes = array_merge($this->aagenerateAttributes(), $this->generateForeign());

        // Generar el fillable
        $fillable = array_keys($attributes);
        $fillableString = implode("', '", $fillable);

        // Ruta del modelo
        $modelPath = app_path("Models/$modelName.php");

        // Verificar si el modelo existe
        if (! File::exists($modelPath)) {
            $this->error("El modelo $modelName no existe.");

            return 0;
        }

        // Leer el contenido del modelo
        $modelContent = File::get($modelPath);

        // Añadir el fillable y SoftDeletes
        $modelContent = preg_replace('/protected \$fillable = \[.*?\];/s', "protected \$fillable = ['$fillableString'];", $modelContent);
        if (! str_contains($modelContent, 'use SoftDeletes;')) {
            $modelContent = preg_replace('/class '.$modelName.' extends/', "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n\n    class $modelName extends", $modelContent);
        }

        // Generar relaciones belongsTo
        $foreignKeys = $this->generateForeign();
        foreach ($foreignKeys as $key => $value) {
            $functionName = lcfirst(str_replace('_id', '', $key)); // Nombre de la función
            $relatedModel = ucfirst(str_replace('_id', '', $key)); // Nombre del modelo relacionado

            // Verificar si la relación ya existe
            if (! str_contains($modelContent, "function $functionName(")) {
                $relationMethod = "\n    public function $functionName(): BelongsTo\n    {\n        return \$this->belongsTo($relatedModel::class);\n    }\n";

                // Insertar la relación antes del final de la clase
                $modelContent = preg_replace('/}\s*$/', "$relationMethod\n}", $modelContent);
            }
        }

        // Guardar el contenido modificado
        File::put($modelPath, $modelContent);

        $this->info("El fillable, SoftDeletes y relaciones han sido añadidos al modelo $modelName.");

        return 1;
    }

    protected function updateMigration($modelName): int
    {
        $atributos = $this->aagenerateAttributes();
        $migrationFile = collect(glob(database_path('migrations/*.php')))->first(fn ($file) => str_contains($file, 'create_'.Str::snake(Str::plural($modelName)).'_table'));

        if (! $migrationFile) {
            $this->error("No se encontró la migración para $modelName");

            return 0;
        }

        $columns = collect($atributos)->map(function ($type, $name) {
            if ($type === 'dinero') {
                return "\$table->decimal('$name', 62, 2)->default(0);";
            }
            if ($type === 'dateTime') {
                return "\$table->dateTime('$name')->default(now());";
            }
            if ($type === 'boolean') {
                return "\$table->boolean('$name')->default(false);";
            }

            if ($type === 'number') {
                $type = 'integer';
            }

            return "\$table->$type('$name')->nullable();";
        })->implode("\n            ");

        $content = file_get_contents($migrationFile);
        $content = preg_replace('/Schema::create\(.*?\{/', "$0\n            $columns", $content);
        file_put_contents($migrationFile, $content);

        $this->info("Migración actualizada para $modelName");

        return 1;
    }
}
