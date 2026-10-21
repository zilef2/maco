<?php

// $ipAddress = $request->ip();

namespace App\Console\Commands;

use App\Models\Permission;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/*
php artisan make:command copy:u
*/

class CopyUserPages extends Command
{
    use Constants;

    protected $signature = 'copy';
    // similar: model:find-usages
    // similar: copy:attr

    const MSJ_EXITO = ' fue realizada con exito ';

    const MSJ_FALLO = ' Fallo';

    public $generando;

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
    protected array $modelConfig = [];

    protected function aagenerateAttributes(): array
    {
        // Tipos válidos: string text number dinero date datetime boolean foreign json
        // Cada entrada: [ 'NombreModelo', 'required' => [...], 'optional' => [...] ]
        return [
    [
        'FichaEnsamble',
        'required' => [
            // Cabecera Ficha de Ensamble
            'orden_produccion_id'    => 'foreign', // OP vinculada
            'producto_referencia_id' => 'foreign', // Producto / referencia (ej. Colibrí Esmeralda)
            'taller_id'              => 'foreign', // Taller que realizará el ensamble
            'cantidad_total'         => 'number',  // Cantidad total planificada
            'fecha_entrega'          => 'date',    // Fecha de entrega al taller
        ],
        'optional' => [
            'coleccion'              => 'string',  // Colección a la que pertenece la referencia
            'imagen_referencia_url'  => 'string',  // Fotografía de referencia del producto terminado
            'instrucciones_especiales' => 'text',  // Instrucciones o notas de ensamble para el taller
        ],
    ],
    [
        'FichaEnsambleMaterial',
        'required' => [
            // Listado de materiales entregados
            'ficha_ensamble_id'      => 'foreign', // Ficha de ensamble padre
            'material_id'            => 'foreign', // Material / componente (Cuero, Capelladas, Sesgo, etc.)
            'cantidad'               => 'number',  // Cantidad requerida/entregada
            'unidad_medida'          => 'string',  // Unidad (pie, unidad, metro, par)
            'aporta'                 => 'string',  // Quién lo aporta (ej: 'Macondas', 'Taller')
        ],
        'optional' => [
            'variante_especificacion'=> 'string',  // Variante o especificación técnica
            'proceso_previo'         => 'string',  // Proceso previo (ej: 'Embarretado + sublimación', 'Corte + pedrería')
            'verificado'             => 'boolean', // Checkbox de comprobación/verificación
            'observaciones'          => 'text',    // Observaciones particulares del ítem
        ],
    ],
];
    }

    private function allAttributes(): array
    {
        return array_merge(
            $this->modelConfig['required'] ?? [],
            $this->modelConfig['optional'] ?? []
        );
    }

    protected function addToMyModels(): void
    {
        $modelName = $this->modelConfig['name'];
        $path = app_path('helpers/MyModels.php');
        if (! file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);

        if (strpos($content, '//aquipues') !== false) {
            if (strpos($content, "'$modelName'") === false) {
                $replacement = "'$modelName',\n\t\t\t//aquipues";
                $content = str_replace('//aquipues', $replacement, $content);
                file_put_contents($path, $content);
                $this->info("Modelo $modelName agregado a MyModels.php exitosamente.");
            } else {
                $this->info("El modelo $modelName ya existe en MyModels.php.");
            }
        }
    }

    public function handle(): int
    {
        try {
            $this->generando = self::getMessage('generando');
            $this->contadorMetodos = 0;
            $submetodo['Lenguaje'] = 0;

            $pasos = [
                'modelo' => '1. make:model + copy:f + lang:u',
                'vue' => '2. Páginas Vue (MakeVuePages)',
                'controller' => '3. Controlador (MakeControllerPages)',
                'replace' => '4. Reemplazar palabras en archivos',
                'atributos' => '5. AddAttributesVue',
                'migracion' => '6. Actualizar migración',
                'paso2' => '7. Paso2 (routes, seeder permisos…)',
                'optimize' => '8. optimize + optimize:clear',
            ];

            $opciones = array_merge(['0. Todos los pasos'], array_values($pasos));

            $seleccionados = $this->choice(
                '¿Qué pasos deseas ejecutar? (separados por coma, ej: 0,2,4)',
                $opciones,
                '0',
                null,
                true
            );

            if (in_array('0. Todos los pasos', $seleccionados)) {
                $activos = array_keys($pasos);
            } else {
                $activos = array_keys(array_filter(
                    $pasos,
                    fn ($label) => in_array($label, $seleccionados)
                ));
            }

            $this->info('Pasos seleccionados: '.implode(', ', $activos));

            foreach ($this->aagenerateAttributes() as $entry) {
                $this->modelConfig = [
                    'name' => $entry[0],
                    'required' => $entry['required'] ?? [],
                    'optional' => $entry['optional'] ?? [],
                ];
                $modelName = $this->modelConfig['name'];

                $this->info("\n=== Procesando modelo: $modelName ===");

                if (! $this->ValidacionesPrevias($modelName, 'generic', $activos)) {
                    $this->error("Las validaciones previas fallaron para $modelName. Saltando.");

                    continue;
                }

                $progressBar = $this->output->createProgressBar(count($activos));
                $progressBar->start();

                // --- Paso 1: modelo base ---
                if (in_array('modelo', $activos)) {
                    $this->warn('Empezando make:model');
                    Artisan::call('make:model', ['name' => $modelName, '--all' => true]);
                    Artisan::call('copy:f');
                    Artisan::call('lang:u '.$modelName);
                    $this->addToMyModels();
                    $progressBar->advance();
                }

                // --- Paso 2: Vue pages ---
                $vueOk = false;
                if (in_array('vue', $activos)) {
                    $vueOk = $this->MakeVuePages('generic', $modelName);
                    $this->info($vueOk ? 'Vue OK' : 'Vue FALLO');
                    $progressBar->advance();
                }

                // --- Paso 3: Controller ---
                $ctrlOk = false;
                if (in_array('controller', $activos)) {
                    $ctrlOk = $this->MakeControllerPages('generic', $modelName);
                    $this->info($ctrlOk ? 'Controller OK' : 'Controller FALLO');
                    $progressBar->advance();
                }

                // --- Paso 4: Reemplazar palabras ---
                if (in_array('replace', $activos) && ($vueOk || $ctrlOk)) {
                    $this->replaceWordInFiles('generic', [
                        'vue' => $vueOk,
                        'controller' => $ctrlOk,
                    ], $modelName, '');
                    $progressBar->advance();
                }

                // --- Paso 5: Atributos Vue ---
                if (in_array('atributos', $activos)) {
                    $this->AddAttributesVue($modelName);
                    $progressBar->advance();
                }

                // --- Paso 6: Migración ---
                if (in_array('migracion', $activos)) {
                    $this->updateMigration($modelName);
                    $progressBar->advance();
                }

                // --- Paso 7: Paso2 (routes, seeder permisos…) ---
                if (in_array('paso2', $activos)) {
                    $this->Paso2($modelName, $submetodo);
                    $progressBar->advance();
                }

                // --- Paso 8: Optimize ---
                if (in_array('optimize', $activos)) {
                    $this->info(Artisan::call('optimize'));
                    $this->info(Artisan::call('optimize:clear'));
                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->newLine();
                $this->info("$modelName finalizado.");
            }

            return 1;
        } catch (Exception $e) {
            $this->error('FALLO en contador: '.$this->contadorMetodos.' | '.$e->getMessage());

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

    private function ValidacionesPrevias(string $modelName, string $plantillaActual, array $activos = []): bool
    {
        $valido = true;
        $todos = empty($activos);

        if ($todos || in_array('controller', $activos)) {
            $controllerOrigen = base_path('app/Console/Commands/'.$plantillaActual.'Controller.stub');
            if (! File::exists($controllerOrigen)) {
                $this->error("[1] No existe el controlador plantilla: $controllerOrigen");
                $valido = false;
            }
        }

        if ($todos || in_array('vue', $activos)) {
            $vueOrigen = base_path('resources/js/Pages/'.$plantillaActual);
            if (! File::exists($vueOrigen)) {
                $this->error("[2] No existe la carpeta Vue origen: $vueOrigen");
                $valido = false;
            }
        }

        if ($todos || in_array('atributos', $activos)) {
            $indexVue = resource_path("js/Pages/$plantillaActual/Index.vue");
            if (! File::exists($indexVue)) {
                $this->error("[3] No existe el Index.vue de la plantilla: $indexVue");
                $valido = false;
            }
        }

        if ($todos || in_array('vue', $activos)) {
            $vueDestino = base_path("resources/js/Pages/$modelName");
            if (File::exists($vueDestino)) {
                $this->error("[4] La carpeta Vue destino ya existe: $vueDestino");
                $valido = false;
            }
        }

        if ($todos || in_array('controller', $activos)) {
            $controllerDestino = base_path('app/Http/Controllers/'.ucfirst($modelName).'sController.php');
            if (File::exists($controllerDestino)) {
                $this->error("[5] El controlador destino ya existe: $controllerDestino");
                $valido = false;
            }
        }

        if ($todos || in_array('modelo', $activos)) {
            $modeloPath = app_path("Models/$modelName.php");
            if (File::exists($modeloPath)) {
                $this->error("[6] El modelo ya existe: $modeloPath");
                $valido = false;
            }
        }

        if (in_array('migracion', $activos)) {
            $migrationFile = collect(glob(database_path('migrations/*.php')))->first(
                fn ($file) => str_contains($file, 'create_'.Str::snake(Str::plural($modelName)).'_table')
            );
            if (! $migrationFile && ! in_array('modelo', $activos)) {
                $this->warn("No existe la migración para $modelName. Creándola automáticamente...");
                Artisan::call('make:migration', [
                    'name' => 'create_'.Str::snake(Str::plural($modelName)).'_table',
                    '--create' => Str::snake(Str::plural($modelName)),
                ]);
            }
        }

        if ($todos || in_array('paso2', $activos)) {
            $webPhp = base_path('routes/web.php');
            if (! File::exists($webPhp) || ! str_contains(file_get_contents($webPhp), '//aquipues')) {
                $this->error('[7] routes/web.php no existe o no contiene el marcador //aquipues');
                $valido = false;
            }

            $langFile = base_path('lang/es/app.php');
            if (! File::exists($langFile) || ! str_contains(file_get_contents($langFile), '//aquipues')) {
                $this->error('[8] lang/es/app.php no existe o no contiene el marcador //aquipues');
                $valido = false;
            }

            $sidebar = base_path('resources/js/components/AppSidebar.vue');
            if (! File::exists($sidebar) || ! str_contains(file_get_contents($sidebar), '//aquipuesSide')) {
                $this->error('[9] AppSidebar.vue no existe o no contiene el marcador //aquipuesSide');
                $valido = false;
            }
        }

        if ($valido) {
            $this->info('Todas las validaciones previas pasaron correctamente.');
        }

        return $valido;
    }

    private function MakeVuePages($plantillaActual, $modelName): bool
    {
        try {
            $sourcePath = base_path('resources/js/Pages/'.$plantillaActual);
            $destinationPath = base_path("resources/js/Pages/$modelName");

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
        } catch (\Throwable $e) {
            $this->error('[MakeVuePages] '.$e->getMessage());

            return false;
        }
    }

    private function MakeControllerPages($plantillaActual, $modelName): bool
    {
        try {
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
        } catch (\Throwable $e) {
            $this->error('[MakeControllerPages] '.$e->getMessage());

            return false;
        }
    }

    private function replaceWordInFiles($oldWord, $permiteRemplazo, $modelName, $depende): int
    {
        try {
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
        } catch (\Throwable $e) {
            $this->error('[replaceWordInFiles] '.$e->getMessage());

            return 0;
        }
    }

    private function AddAttributesVue($modelName): int
    {
        try {
            $vueFilePath = resource_path("js/Pages/$modelName/Index.vue");

            if (! File::exists($vueFilePath)) {
                $this->error('El archivo Index.vue no existe.');

                return 0;
            }

            $content = File::get($vueFilePath);

            // Convertir los atributos en formato Vue
            $titulosArray = collect($this->allAttributes())->map(function ($type, $key) {
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
        } catch (\Throwable $e) {
            $this->error('[AddAttributesVue] '.$e->getMessage());

            return 0;
        }
    }

    private function Paso2($modelName, &$submetodo): int
    {
        try {
            $thereturn = 1;
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
            $this->DoPermissions($modelName);
            $this->contadorMetodos++;

            return $thereturn;
        } catch (\Throwable $e) {
            $this->error('[Paso2] '.$e->getMessage());

            return 0;
        }
    }

    protected function DoPermissions($modelName): int
    {
        $cruds = ['create', 'read', 'update', 'delete'];
        $permissionsCreated = [];

        foreach ($cruds as $crud) {
            $permName = "$crud $modelName";
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
            $permissionsCreated[] = $permName;
        }

        try {
            $roles = Role::whereIn('name', ['superadmin', 'admin'])->get();
            foreach ($roles as $role) {
                foreach ($permissionsCreated as $permName) {
                    $role->givePermissionTo($permName);
                }
            }
        } catch (\Throwable $e) {
            // Ignorar silenciosamente si no existen los roles aún
        }

        $this->info("Permisos generados exitosamente para $modelName (".implode(', ', $permissionsCreated).')');

        return 1;
    }

    private function DoWebphp($resource): int
    {
        try {
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
        } catch (\Throwable $e) {
            $this->error('[DoWebphp] '.$e->getMessage());

            return 0;
        }
    }

    public function L2_LenguajeInsert($modelName, &$submetodo): int
    {
        try {
            if ($this->DoAppLenguaje($modelName)) {
                $submetodo['Lenguaje'] = 0;
                $this->info('DoAppLenguaje'.self::MSJ_EXITO);
                $this->contadorMetodos++;

                foreach ($this->allAttributes() as $key => $generateAttribute) {
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
        } catch (\Throwable $e) {
            $this->error('[L2_LenguajeInsert] '.$e->getMessage());

            return 0;
        }
    }

    private function DoAppLenguaje($resource, $mochar = 'no'): int
    {
        try {
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
        } catch (\Throwable $e) {
            $this->error('[DoAppLenguaje] '.$e->getMessage());

            return 0;
        }
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
        try {
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
        } catch (\Throwable $e) {
            $this->error('[DoSideBar] '.$e->getMessage());

            return 0;
        }
    }

    protected function DoFillable($modelName): int
    {
        try {
            $attributes = array_merge($this->allAttributes(), $this->generateForeign());

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

            // El tipo de los fillable se resuelve con el trait compartido.
            // Si el modelo todavía tiene el método antiguo, se elimina.
            $modelContent = preg_replace(
                '/\n\s*public static function getFillableWithTypes\(\): array\s*\{.*?return \$result;\s*\}\s*/s',
                '',
                $modelContent
            );

            // Si el método era el último, el cierre de la clase queda pegado.
            $modelContent = preg_replace('/([^\n])\}\s*$/', "$1\n}\n", $modelContent);

            if (! str_contains($modelContent, 'use App\\Traits\\HasFillableWithTypes;')) {
                $modelContent = str_replace(
                    'namespace App\\Models;',
                    "namespace App\\Models;\n\nuse App\\Traits\\HasFillableWithTypes;",
                    $modelContent
                );
            }

            if (! str_contains($modelContent, 'use HasFillableWithTypes;')) {
                $modelContent = preg_replace(
                    '/(\n\s*use HasFactory;)/',
                    "$1\n    use HasFillableWithTypes;",
                    $modelContent,
                    1
                );
            }

            if (! str_contains($modelContent, 'DB::')) {
                $modelContent = preg_replace('/^use Illuminate\\\\Support\\\\Facades\\\\DB;\R/m', '', $modelContent);
            }

            // Guardar el contenido modificado
            File::put($modelPath, $modelContent);

            $this->info("El fillable, SoftDeletes y relaciones han sido añadidos al modelo $modelName.");

            return 1;
        } catch (\Throwable $e) {
            $this->error('[DoFillable] '.$e->getMessage());

            return 0;
        }
    }

    protected function updateMigration($modelName): int
    {
        try {
            $required = $this->modelConfig['required'] ?? [];
            $optional = $this->modelConfig['optional'] ?? [];

            $migrationFile = collect(glob(database_path('migrations/*.php')))->first(
                fn ($file) => str_contains($file, 'create_'.Str::snake(Str::plural($modelName)).'_table')
            );

            if (! $migrationFile) {
                $this->warn("No se encontró la migración para $modelName. Creando archivo...");
                Artisan::call('make:migration', [
                    'name' => 'create_'.Str::snake(Str::plural($modelName)).'_table',
                    '--create' => Str::snake(Str::plural($modelName)),
                ]);

                $migrationFile = collect(glob(database_path('migrations/*.php')))->first(
                    fn ($file) => str_contains($file, 'create_'.Str::snake(Str::plural($modelName)).'_table')
                );
            }

            $buildColumn = function (string $name, string $type, bool $nullable): string {
                if ($type === 'dinero') {
                    $col = "\$table->decimal('$name', 62, 2)->default(0)";

                    return $nullable ? $col.'->nullable();' : $col.';';
                }
                if ($type === 'dateTime') {
                    $col = "\$table->dateTime('$name')->default(now())";

                    return $nullable ? $col.'->nullable();' : $col.';';
                }
                if ($type === 'boolean') {
                    $col = "\$table->boolean('$name')->default(false)";

                    return $nullable ? $col.'->nullable();' : $col.';';
                }
                if ($type === 'number') {
                    $type = 'integer';
                }
                $col = "\$table->$type('$name')";

                return $nullable ? $col.'->nullable();' : $col.';';
            };

            $requiredColumns = collect($required)
                ->map(fn ($type, $name) => $buildColumn($name, $type, false))
                ->implode("\n            ");

            $optionalColumns = collect($optional)
                ->map(fn ($type, $name) => $buildColumn($name, $type, true))
                ->implode("\n            ");

            $columns = $requiredColumns
                .(($optionalColumns !== '') ? "\n            // opcionales\n            $optionalColumns" : '');

            $content = file_get_contents($migrationFile);
            $content = preg_replace('/Schema::create\(.*?\{/', "$0\n            $columns", $content);
            file_put_contents($migrationFile, $content);

            $this->info("Migración actualizada para $modelName");

            return 1;
        } catch (\Throwable $e) {
            $this->error('[updateMigration] '.$e->getMessage().' en '.$e->getFile().':'.$e->getLine());

            return 0;
        }
    }
}
