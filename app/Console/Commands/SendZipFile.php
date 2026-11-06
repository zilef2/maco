<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use ZipArchive;

class SendZipFile extends Command
{
    protected $signature = 'send:zip
        {--backup : Generar un nuevo backup antes de enviar}
        {--no-backup : Omitir la generación de backup y usar los existentes}
        {--to= : Correo destino (por defecto ajelof2@gmail.com)}';

    protected $description = 'Comprimir los últimos respaldos y enviarlos por correo';

    public function handle(): int
    {
        $zipPath = public_path('backup_send_' . now()->format('Ymd_His') . '.zip');
        $appName = (string) config('app.name', env('APP_NAME', 'Modnom'));
        $backupDirectory = storage_path('app/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $appName) . '_zilef2025');
        $recipient = (string) ($this->option('to') ?: 'ajelof2@gmail.com');

        try {
            $shouldBackup = $this->shouldRunBackup();

            if ($shouldBackup) {
                $this->info('Ejecutando backup:run...');

                try {
                    $exitCode = $this->call('backup:run');
                } catch (Throwable $exception) {
                    $this->error('No se pudo ejecutar backup:run: ' . $exception->getMessage());
                    Log::warning('send:zip: backup:run lanzó excepción', ['error' => $exception->getMessage()]);

                    return self::FAILURE;
                }

                if ($exitCode !== self::SUCCESS) {
                    $this->error("backup:run terminó con código {$exitCode}. No se enviará correo.");

                    return self::FAILURE;
                }

                $this->info('Backup generado correctamente.');
            } else {
                $this->info('Se omitió la generación de backup; se usarán respaldos existentes.');
            }

            $selectedBackups = $this->collectBackups($backupDirectory);

            if ($selectedBackups === []) {
                $this->warn("No hay archivos ZIP de respaldo válidos para enviar en: {$backupDirectory}");
                $this->line('Verifique que existan backups con nombre que inicie con YYYY-MM-DD.');

                return self::FAILURE;
            }

            $this->createZip($zipPath, $selectedBackups);
            $this->sendMail($zipPath, $recipient, $appName);

            $this->info('Backup comprimido y enviado correctamente.');

            Log::info('Backup enviado por correo', [
                'zip' => $zipPath,
                'directorio' => $backupDirectory,
                'destinatario' => $recipient,
                'backups_incluidos' => array_column($selectedBackups, 'path'),
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Error: ' . $exception->getMessage());

            Log::error('Error enviando backup por correo', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return self::FAILURE;
        } finally {
            if (is_file($zipPath) && !@unlink($zipPath)) {
                Log::warning('No se pudo eliminar el ZIP temporal: ' . $zipPath);
            }
        }
    }

    private function shouldRunBackup(): bool
    {
        if ($this->option('backup')) {
            return true;
        }

        if ($this->option('no-backup')) {
            return false;
        }

        if (!$this->input->isInteractive()) {
            return true;
        }

        return $this->confirm('¿Desea generar un nuevo backup antes de enviar el correo?', true);
    }

    /**
     * @return list<array{path: string, date: Carbon}>
     */
    private function collectBackups(string $backupDirectory): array
    {
        if (!is_dir($backupDirectory)) {
            $this->warn("No existe el directorio de backups: {$backupDirectory}");

            return [];
        }

        $files = glob($backupDirectory . DIRECTORY_SEPARATOR . '*.zip') ?: [];

        if ($files === []) {
            return [];
        }

        $backups = [];

        foreach ($files as $file) {
            $filename = basename($file);

            if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                $this->line("Ignorado (fecha no reconocida): {$filename}");
                continue;
            }

            try {
                $date = Carbon::createFromFormat('!Y-m-d', $matches[1]);
            } catch (Throwable) {
                continue;
            }

            if (!$date || $date->format('Y-m-d') !== $matches[1]) {
                continue;
            }

            clearstatcache(true, $file);
            if (!is_file($file) || (int) filesize($file) <= 0) {
                $this->line("Ignorado (vacío o inaccesible): {$filename}");
                continue;
            }

            $backups[] = [
                'path' => $file,
                'date' => $date->startOfDay(),
            ];
        }

        if ($backups === []) {
            return [];
        }

        usort($backups, static fn (array $a, array $b): int => $b['date']->timestamp <=> $a['date']->timestamp);

        $latestDate = $backups[0]['date']->copy();
        $previousDate = $latestDate->copy()->subDay();

        $selected = array_values(array_filter(
            $backups,
            static fn (array $backup): bool => $backup['date']->isSameDay($latestDate)
                || $backup['date']->isSameDay($previousDate)
        ));

        $this->info('Última fecha encontrada: ' . $latestDate->format('Y-m-d'));
        $this->info('Backups seleccionados: ' . count($selected));

        return $selected;
    }

    /**
     * @param list<array{path: string, date: Carbon}> $backups
     */
    private function createZip(string $zipPath, array $backups): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new \RuntimeException("No se pudo crear el ZIP temporal. Código ZipArchive: {$result}");
        }

        foreach ($backups as $backup) {
            $filename = basename($backup['path']);

            if (!$zip->addFile($backup['path'], $filename)) {
                $zip->close();
                @unlink($zipPath);
                throw new \RuntimeException("No se pudo agregar al ZIP: {$filename}");
            }

            $this->line("Agregado: {$filename}");
        }

        if (!$zip->close()) {
            @unlink($zipPath);
            throw new \RuntimeException('No se pudo finalizar el archivo ZIP.');
        }

        clearstatcache(true, $zipPath);
        if (!is_file($zipPath) || (int) filesize($zipPath) <= 0) {
            throw new \RuntimeException('El ZIP generado está vacío o no existe.');
        }

        $validation = new ZipArchive();
        if ($validation->open($zipPath) !== true) {
            throw new \RuntimeException('El ZIP generado no pasó la validación.');
        }

        $validation->close();
    }

    private function sendMail(string $zipPath, string $recipient, string $appName): void
    {
        $this->info("Enviando correo a {$recipient}...");

        Mail::raw('Adjunto el respaldo más reciente de ' . $appName . '.', function ($message) use ($zipPath, $recipient, $appName) {
            $message->to($recipient)
                ->subject('Backup ' . $appName . ' - ' . now()->format('Y-m-d'))
                ->attach($zipPath, [
                    'as' => basename($zipPath),
                    'mime' => 'application/zip',
                ]);
        });
    }
}