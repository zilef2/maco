<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FindModelUsages extends Command
{
    protected $signature = 'model:find-usages
                            {model? : Nombre del modelo a analizar}
                            {--delete : Eliminar el modelo si no tiene dependencias criticas}';

    protected $description = 'Busca todos los usos de un modelo en el proyecto antes de eliminarlo';

    private string $model = '';

    private array $usages = [];

    private array $ownedFiles = [];

    private array $sharedFiles = [];

    private int $totalHits = 0;

    public function handle(): int
    {
        $this->renderBanner();

        $this->model = $this->argument('model') ?? $this->askForModel();

        if (! $this->model) {
            $this->renderError('No se proporciono ningun modelo.');
            return 1;
        }

        $modelFile = app_path("Models/{$this->model}.php");

        if (! File::exists($modelFile)) {
            $this->renderError("El modelo <fg=yellow;options=bold>{$this->model}</> no existe en app/Models/.");
            return 1;
        }

        $this->renderSection("Analizando usos de <fg=cyan;options=bold>{$this->model}</>");

        $this->scanMigrations();
        $this->scanControllers();
        $this->scanServices();
        $this->scanRepositories();
        $this->scanRequests();
        $this->scanPolicies();
        $this->scanRoutes();
        $this->scanViews();
        $this->scanJobs();
        $this->scanTests();
        $this->scanOtherModels();

        $this->renderResults();
        $this->renderSummaryTable();
        $this->renderDeletionPlan($modelFile);

        if ($this->totalHits === 0) {
            return $this->offerDeletion($modelFile);
        }

        $this->renderWarning('El modelo tiene referencias en archivos compartidos. Revisalas manualmente.');

        if ($this->confirm("\n  <fg=yellow;options=bold>Deseas eliminar el modelo y sus archivos propios de todas formas? (peligroso) ", false)) {
            return $this->performDeletion($modelFile);
        }

        $this->line("\n  <fg=green>Operacion cancelada. El modelo <fg=cyan>{$this->model}</> no fue eliminado.\n");

        return 0;
    }

    private function askForModel(): string
    {
        $models = collect(File::files(app_path('Models')))
            ->map(fn ($f) => $f->getFilenameWithoutExtension())
            ->sort()
            ->values()
            ->toArray();

        if (empty($models)) {
            return '';
        }

        return $this->choice('  Que modelo deseas analizar?', $models, 0);
    }

    private function isOwnedFile(string $filename): bool
    {
        $m = $this->model;
        return str_starts_with($filename, $m)
            || $filename === "Store{$m}Request"
            || $filename === "Update{$m}Request"
            || $filename === "{$m}Policy";
    }

    private function scanMigrations(): void
    {
        $snake = Str::snake(Str::plural($this->model));
        $singular = Str::snake($this->model);
        $files = glob(database_path('migrations/*.php')) ?: [];
        $hits = [];

        foreach ($files as $file) {
            $content = File::get($file);
            $lines = $this->findLines($content, [$snake, $singular, $this->model]);
            if (! $lines) { continue; }
            $basename = basename($file);
            $hits[] = ['file' => $this->relativePath($file), 'lines' => $lines];
            if (str_contains($basename, "create_{$snake}_table")) {
                $this->ownedFiles[] = $file;
            } else {
                $this->sharedFiles[] = $file;
                $this->totalHits++;
            }
        }

        $this->usages['Migraciones'] = $hits;
    }

    private function scanControllers(): void
    {
        $this->usages['Controladores'] = $this->searchInDirectory(
            app_path('Http/Controllers'),
            [$this->model, "use App\\Models\\{$this->model}"],
        );
    }

    private function scanServices(): void
    {
        $this->usages['Servicios'] = $this->searchInDirectory(
            app_path('Services'),
            [$this->model, "use App\\Models\\{$this->model}"],
        );
    }

    private function scanRepositories(): void
    {
        $this->usages['Repositorios'] = $this->searchInDirectory(
            app_path('Repositories'),
            [$this->model, "use App\\Models\\{$this->model}"],
        );
    }

    private function scanRequests(): void
    {
        $this->usages['Form Requests'] = $this->searchInDirectory(
            app_path('Http/Requests'),
            [$this->model],
        );
    }

    private function scanPolicies(): void
    {
        $this->usages['Policies'] = $this->searchInDirectory(
            app_path('Policies'),
            [$this->model],
        );
    }

    private function scanRoutes(): void
    {
        $snake = Str::kebab(Str::plural($this->model));
        $camel = Str::camel($this->model);
        $hits = [];
        $routeFiles = glob(base_path('routes/*.php')) ?: [];

        foreach ($routeFiles as $file) {
            $content = File::get($file);
            $lines = $this->findLines($content, [$this->model, $snake, $camel]);
            if ($lines) {
                $hits[] = ['file' => $this->relativePath($file), 'lines' => $lines];
                $this->sharedFiles[] = $file;
                $this->totalHits++;
            }
        }

        $this->usages['Rutas'] = $hits;
    }

    private function scanViews(): void
    {
        $pascal = $this->model;
        $kebab = Str::kebab($this->model);
        $hits = [];
        $seen = [];

        $scanDirs = [
            resource_path('js/Pages'),
            resource_path('js/pages'),
            resource_path('js/Components'),
            resource_path('js/components'),
            resource_path('views'),
        ];

        foreach ($scanDirs as $dir) {
            if (! File::isDirectory($dir)) { continue; }
            $rp = realpath($dir);
            if (in_array($rp, $seen)) { continue; }
            $seen[] = $rp;

            foreach (File::allFiles($dir) as $file) {
                $real = $file->getRealPath();
                $content = File::get($real);
                $lines = $this->findLines($content, [$pascal, $kebab]);
                if (! $lines) { continue; }
                $hits[] = ['file' => $this->relativePath($real), 'lines' => $lines];
                $parts = explode(DIRECTORY_SEPARATOR, $this->relativePath($real));
                $pageFolder = $parts[2] ?? '';
                $isModelPage = strtolower($pageFolder) === strtolower($this->model);
                // Archivos dentro de pages/{Model}/ son propios (se borran con la carpeta)
                if (! $isModelPage) {
                    $this->sharedFiles[] = $real;
                    $this->totalHits++;
                }
            }
        }

        foreach (['js/Pages', 'js/pages'] as $base) {
            $pageDir = resource_path("{$base}/{$this->model}");
            if (File::isDirectory($pageDir)) {
                $this->ownedFiles[] = $pageDir;
            }
        }

        $this->usages['Vistas / Vue'] = $hits;
    }

    private function scanJobs(): void
    {
        $this->usages['Jobs'] = $this->searchInDirectory(
            app_path('Jobs'),
            [$this->model, "use App\\Models\\{$this->model}"],
        );
    }

    private function scanTests(): void
    {
        $this->usages['Tests'] = $this->searchInDirectory(
            base_path('tests'),
            [$this->model, "use App\\Models\\{$this->model}"],
        );
    }

    private function scanOtherModels(): void
    {
        $hits = [];
        foreach (File::files(app_path('Models')) as $file) {
            if ($file->getFilenameWithoutExtension() === $this->model) { continue; }
            $content = File::get($file->getRealPath());
            $lines = $this->findLines($content, [$this->model, "use App\\Models\\{$this->model}"]);
            if ($lines) {
                $hits[] = ['file' => $this->relativePath($file->getRealPath()), 'lines' => $lines];
                $this->sharedFiles[] = $file->getRealPath();
                $this->totalHits++;
            }
        }
        $this->usages['Otros Modelos'] = $hits;
    }

    private function searchInDirectory(string $directory, array $terms): array
    {
        if (! File::isDirectory($directory)) { return []; }
        $hits = [];

        foreach (File::allFiles($directory) as $file) {
            $real = $file->getRealPath();
            $content = File::get($real);
            $lines = $this->findLines($content, $terms);
            if (! $lines) { continue; }
            $hits[] = ['file' => $this->relativePath($real), 'lines' => $lines];
            if ($this->isOwnedFile($file->getFilenameWithoutExtension())) {
                $this->ownedFiles[] = $real;
            } else {
                $this->sharedFiles[] = $real;
                $this->totalHits++;
            }
        }

        return $hits;
    }

    private function findLines(string $content, array $terms): array
    {
        $matches = [];
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            foreach ($terms as $term) {
                if (str_contains($line, $term)) {
                    $matches[] = ['number' => $i + 1, 'content' => trim($line)];
                    break;
                }
            }
        }
        return $matches;
    }

    private function renderResults(): void
    {
        $hasAny = false;

        foreach ($this->usages as $section => $hits) {
            if (empty($hits)) { continue; }
            $hasAny = true;
            $count = count($hits);
            $label = $count === 1 ? 'archivo' : 'archivos';

            $this->line('');
            $this->line("  <fg=magenta;options=bold>┌─ {$section}</> <fg=gray>({$count} {$label})</>");

            foreach ($hits as $idx => $hit) {
                $isLast = ($idx === count($hits) - 1);
                $connector = $isLast ? '└' : '├';
                $vertical  = $isLast ? ' ' : '│';
                $this->line("  <fg=magenta>{$connector}──</> <fg=cyan;options=bold>{$hit['file']}</>");
                $shown = array_slice($hit['lines'], 0, 4);
                foreach ($shown as $li) {
                    $num = str_pad((string) $li['number'], 4, ' ', STR_PAD_LEFT);
                    $snippet = Str::limit($li['content'], 80);
                    $this->line("  <fg=magenta>{$vertical}</> <fg=gray>   {$num} |</> <fg=white>{$snippet}</>");
                }
                $remaining = count($hit['lines']) - count($shown);
                if ($remaining > 0) {
                    $this->line("  <fg=magenta>{$vertical}</> <fg=gray>        y {$remaining} linea(s) mas</>");
                }
            }
        }

        if (! $hasAny) {
            $this->line('');
            $this->line('  <fg=green>No se encontraron referencias en ninguna categoria.</>');
        }
    }

    private function renderDeletionPlan(string $modelFile): void
    {
        $toDelete = $this->buildDeletionList($modelFile);

        $this->line('');
        $this->line('  <fg=blue;options=bold>></> <fg=white;options=bold>Archivos que se eliminarian</>');
        $this->line('  <fg=gray>'.str_repeat('-', 56).'</>');
        $this->line('');

        foreach ($toDelete as $f) {
            $isDir = File::isDirectory($f);
            $tag   = $isDir ? ' <fg=gray>[carpeta]</>' : '';
            $this->line("  <fg=red>  X</> <fg=white>{$this->relativePath($f)}{$tag}</>");
        }

        $shared = array_unique($this->sharedFiles);
        if (! empty($shared)) {
            $this->line('');
            $this->line('  <fg=yellow;options=bold>  Archivos con referencias (NO se eliminan automaticamente):</>');
            foreach ($shared as $f) {
                $this->line("  <fg=yellow>  !</> <fg=gray>{$this->relativePath($f)}</>");
            }
        }
    }

    private function buildDeletionList(string $modelFile): array
    {
        return array_unique(array_merge([$modelFile], $this->ownedFiles));
    }

    private function renderBanner(): void
    {
        $this->line('');
        $this->line('  <bg=blue;fg=white;options=bold>                                              </>');
        $this->line('  <bg=blue;fg=white;options=bold>   MODEL USAGE FINDER - MACO v1.0            </>');
        $this->line('  <bg=blue;fg=white;options=bold>                                              </>');
        $this->line('');
    }

    private function renderSection(string $message): void
    {
        $this->line('');
        $this->line("  <fg=blue;options=bold>></> {$message}");
        $this->line('  <fg=gray>'.str_repeat('-', 56).'</>');
    }

    private function renderError(string $message): void
    {
        $this->line('');
        $this->line("  <bg=red;fg=white;options=bold> ERROR </> <fg=red>{$message}</>");
        $this->line('');
    }

    private function renderWarning(string $message): void
    {
        $this->line('');
        $this->line("  <bg=yellow;fg=black;options=bold> ALERTA </> <fg=yellow>{$message}</>");
    }

    private function renderSummaryTable(): void
    {
        $rows = [];
        $totalFiles = 0;
        $totalLines = 0;

        foreach ($this->usages as $section => $hits) {
            $files = count($hits);
            $lines = array_sum(array_map(fn ($h) => count($h['lines']), $hits));
            $totalFiles += $files;
            $totalLines += $lines;
            $status = ($files > 0) ? 'Tiene referencias' : 'Limpio';
            $rows[] = [$section, (string) $files, (string) $lines, $status];
        }

        if (empty($rows)) { return; }

        $this->line('');
        $this->line('  <fg=blue;options=bold>></> <fg=white;options=bold>Resumen de referencias</>');
        $this->line('  <fg=gray>'.str_repeat('-', 56).'</>');
        $this->line('');
        $this->table(['Categoria', 'Archivos', 'Lineas', 'Estado'], $rows);
        $this->line("  <fg=gray>Total:</> <fg=yellow;options=bold>{$totalFiles}</> archivos con <fg=yellow;options=bold>{$totalLines}</> referencias");
    }

    private function offerDeletion(string $modelFile): int
    {
        $this->line('');
        $this->line('  <fg=green;options=bold>Sin referencias externas!</> El modelo <fg=cyan;options=bold>'.$this->model.'</> se puede eliminar de forma segura.');
        $this->line('');

        if (! $this->confirm("  Confirmas que deseas eliminar el modelo {$this->model} y todos sus archivos?", false)) {
            $this->line("\n  <fg=green>Operacion cancelada. Ningun archivo fue eliminado.\n");
            return 0;
        }

        return $this->performDeletion($modelFile);
    }

    private function performDeletion(string $modelFile): int
    {
        $deleted = [];
        $errors  = [];

        foreach ($this->buildDeletionList($modelFile) as $path) {
            if (! File::exists($path) && ! File::isDirectory($path)) { continue; }
            try {
                File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
                $deleted[] = $this->relativePath($path);
            } catch (\Throwable $e) {
                $errors[] = $this->relativePath($path).': '.$e->getMessage();
            }
        }

        $this->line('');
        foreach ($deleted as $f) { $this->line("  <fg=red>  X Eliminado:</> <fg=white>{$f}</>"); }
        foreach ($errors  as $e) { $this->line("  <fg=red>  ! Error:</>    <fg=yellow>{$e}</>"); }

        $this->line('');
        $this->line('  <bg=green;fg=black;options=bold> LISTO </> Se eliminaron <fg=yellow;options=bold>'.count($deleted).'</> archivo(s).');
        $this->line('');

        return empty($errors) ? 0 : 1;
    }

    private function relativePath(string $absolute): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $absolute);
    }
}
