<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UpdateModelAttributes extends Command
{
    protected $signature = 'copy:attr {model? : Nombre del modelo a actualizar}';

    protected $description = 'Actualiza atributos de un modelo en migración, modelo, Vue y lang';

    protected array $modelos = [

        // Código_interno	ID_producto_Shopify	Categoría_original	Familia_normalizada	Título_Shopify	Control_de_variante	Rango_/_opciones	Estado	Observaciones
        // Codigo_interno
        // ID_producto_Shopify
        // Categoria_original
        // Familia_normalizada
        // Titulo_Shopify
        // Control_de_variante
        // Opciones
        // Estado
        // Observaciones
        
        'Producto' => [
            'required' => [
                'Codigo_interno' => 'string',
                'ID_producto_Shopify' => 'string',
                'Categoria_original' => 'string',
                'Familia_normalizada' => 'string',
                'Titulo_Shopify' => 'string',
                'Control_de_variante' => 'string',
                'Opciones' => 'string',
                'Estado' => 'string',
            ],
            'optional' => [
                'Observaciones' => 'string',
                'descripcion'   => 'text',
                'precio_predicho' => 'number',
                'precio_preventa'  => 'number',
            ],
        ],
    ];

    public function handle(): int
    {
        $disponibles = array_keys($this->modelos);
        $model = $this->argument('model') ?? $this->choice('¿Cuál modelo deseas actualizar?', $disponibles, 0);

        if (! isset($this->modelos[$model])) {
            $this->error("El modelo '$model' no está configurado en el array.");
            return 1;
        }

        $config = $this->modelos[$model];
        $allAttrs = array_merge($config['required'] ?? [], $config['optional'] ?? []);

        $this->info("Actualizando atributos para el modelo: $model");

        $this->actualizarMigracion($model, $config);
        $this->actualizarModelo($model, $allAttrs);
        $this->actualizarVue($model, $allAttrs);
        $this->actualizarLenguaje($allAttrs);

        Artisan::call('optimize:clear');
        $this->info("Proceso completado exitosamente para $model.");

        return 0;
    }

    protected function actualizarMigracion(string $model, array $config): void
    {
        $tabla = Str::snake(Str::plural($model));
        $migrationFile = collect(glob(database_path('migrations/*.php')))->first(
            fn ($file) => str_contains($file, "create_{$tabla}_table")
        );

        if (! $migrationFile) {
            $this->warn("No se encontró migración para $model, creándola...");
            Artisan::call('make:migration', [
                'name' => "create_{$tabla}_table",
                '--create' => $tabla,
            ]);
            $migrationFile = collect(glob(database_path('migrations/*.php')))->first(
                fn ($file) => str_contains($file, "create_{$tabla}_table")
            );
        }

        if (! $migrationFile) {
            $this->error("No se pudo obtener el archivo de migración para $model.");
            return;
        }

        $buildCol = function (string $name, string $type, bool $nullable): string {
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

        $colsReq = collect($config['required'] ?? [])
            ->map(fn ($type, $name) => $buildCol($name, $type, false))
            ->implode("\n            ");

        $colsOpt = collect($config['optional'] ?? [])
            ->map(fn ($type, $name) => $buildCol($name, $type, true))
            ->implode("\n            ");

        $columns = $colsReq . ($colsOpt !== '' ? "\n            $colsOpt" : '');

        $content = File::get($migrationFile);
        $tieneSoftDeletes = str_contains($content, 'softDeletes');

        $body = "            \$table->id();\n"
            . "            $columns\n"
            . "            \$table->timestamps();\n"
            . ($tieneSoftDeletes ? "            \$table->softDeletes();\n" : '');

        $pattern = '/Schema::create\([^{]+\{\s*[\s\S]*?\}\);/';
        $replacement = "Schema::create('$tabla', function (Blueprint \$table) {\n$body        });";

        $newContent = preg_replace($pattern, $replacement, $content);
        if ($newContent) {
            File::put($migrationFile, $newContent);
            $this->info("Migración actualizada: $migrationFile");
        }
    }

    protected function actualizarModelo(string $model, array $allAttrs): void
    {
        $path = app_path("Models/$model.php");
        if (! File::exists($path)) {
            $this->warn("El modelo $path no existe.");
            return;
        }

        $content = File::get($path);
        $fillableString = "'" . implode("', '", array_keys($allAttrs)) . "'";

        if (preg_match('/protected\s+\$fillable\s*=\s*\[[\s\S]*?\];/', $content)) {
            $content = preg_replace('/protected\s+\$fillable\s*=\s*\[[\s\S]*?\];/', "protected \$fillable = [$fillableString];", $content);
        } else {
            $content = preg_replace('/class\s+'.$model.'\s+extends\s+[^{]+\{/', "$0\n    protected \$fillable = [$fillableString];\n", $content);
        }

        File::put($path, $content);
        $this->info("Modelo actualizado: $path");
    }

    protected function actualizarVue(string $model, array $allAttrs): void
    {
        $vueFile = resource_path("js/Pages/$model/Index.vue");
        if (! File::exists($vueFile)) {
            $this->warn("Vista Vue no encontrada: $vueFile");
            return;
        }

        $content = File::get($vueFile);
        $titulosArray = collect($allAttrs)->map(function ($type, $key) {
            return "    { order: '$key', label: '$key', type: '$type' },";
        })->implode("\n");

        $pattern = '/const titulos = \[\s*([\s\S]*?)\s*\];/';
        $replacement = "const titulos = [\n$titulosArray\n];";

        $newContent = preg_replace($pattern, $replacement, $content);
        if ($newContent) {
            File::put($vueFile, $newContent);
            $this->info("Index.vue actualizado: $vueFile");
        }
    }

    protected function actualizarLenguaje(array $allAttrs): void
    {
        $langFile = base_path('lang/es/app.php');
        if (! File::exists($langFile)) {
            return;
        }

        $content = File::get($langFile);
        if (! str_contains($content, '//aquipues')) {
            return;
        }

        $modificado = false;
        foreach (array_keys($allAttrs) as $key) {
            if (! str_contains($content, "'$key' =>")) {
                $content = str_replace('//aquipues', "'$key' => '$key',\n\t\t//aquipues", $content);
                $modificado = true;
            }
        }

        if ($modificado) {
            File::put($langFile, $content);
            $this->info("Traducciones actualizadas en lang/es/app.php");
        }
    }
}
