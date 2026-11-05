<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use ZipArchive;
use Throwable;


class SendZipFile extends Command
{
    protected $signature = 'send:zip';

    protected $description = 'Generar backup, comprimir los últimos respaldos y enviarlos por correo';

    public function handle(): int
    {
        $zipPath = public_path('backup_' . now()->format('Ymd_His') . '.zip');
        $backupDirectory = storage_path('app/' . env('APP_NAME', 'Modnom') . '_zilef2025');

        try {
            // 1. Ejecutar backup de Laravel
            $this->info('Ejecutando backup:run...');

            $exitCode = $this->call('backup:run');

            if ($exitCode !== self::SUCCESS) {
                throw new \RuntimeException(
                    "El comando backup:run terminó con código {$exitCode}"
                );
            }

            $this->info('Backup generado correctamente.');

            // 2. Validar directorio
            if (!is_dir($backupDirectory)) {
                throw new \RuntimeException(
                    "No existe el directorio de backups: {$backupDirectory}"
                );
            }

            // 3. Obtener archivos ZIP
            $files = glob($backupDirectory . DIRECTORY_SEPARATOR . '*.zip');

            if (empty($files)) {
                throw new \RuntimeException(
                    "No se encontraron archivos ZIP en {$backupDirectory}"
                );
            }

            // 4. Identificar archivos con fecha válida
            $backups = [];

            foreach ($files as $file) {
                $filename = basename($file);

                if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
                    $this->warn("Archivo ignorado por formato de fecha inválido: {$filename}");
                    continue;
                }

                try {
                    $date = Carbon::createFromFormat('!Y-m-d', $matches[1]);

                    if (!$date || $date->format('Y-m-d') !== $matches[1]) {
                        continue;
                    }

                    if (!is_file($file) || filesize($file) === 0) {
                        $this->warn("Backup vacío o inexistente: {$filename}");
                        continue;
                    }

                    $backups[] = [
                        'path' => $file,
                        'date' => $date,
                    ];
                } catch (Throwable $exception) {
                    $this->warn("No se pudo interpretar {$filename}: {$exception->getMessage()}");
                }
            }

            if (empty($backups)) {
                throw new \RuntimeException(
                    'No se encontraron backups con fechas válidas.'
                );
            }

            // 5. Ordenar por fecha descendente
            usort($backups, fn ($a, $b) => $b['date']->timestamp <=> $a['date']->timestamp);

            $latestDate = $backups[0]['date']->copy();

            // Incluir backups del día más reciente y del día anterior
            $selectedBackups = array_filter(
                $backups,
                fn ($backup) => $backup['date']->isSameDay($latestDate)
                    || $backup['date']->isSameDay($latestDate->copy()->subDay())
            );

            if (empty($selectedBackups)) {
                throw new \RuntimeException('No hay backups para comprimir.');
            }

            $this->info('Última fecha encontrada: ' . $latestDate->format('Y-m-d'));
            $this->info('Backups seleccionados: ' . count($selectedBackups));

            // 6. Crear ZIP nuevo
            $zip = new ZipArchive();

            $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            if ($result !== true) {
                throw new \RuntimeException(
                    "No se pudo crear el ZIP. Código ZipArchive: {$result}"
                );
            }

            foreach ($selectedBackups as $backup) {
                $filename = basename($backup['path']);

                if (!$zip->addFile($backup['path'], $filename)) {
                    throw new \RuntimeException(
                        "No se pudo agregar el archivo: {$filename}"
                    );
                }

                $this->line("Agregado: {$filename}");
            }

            if (!$zip->close()) {
                throw new \RuntimeException('No se pudo finalizar el archivo ZIP.');
            }

            // 7. Validar ZIP generado
            if (!is_file($zipPath) || filesize($zipPath) === 0) {
                throw new \RuntimeException('El ZIP generado está vacío o no existe.');
            }

            $validationZip = new ZipArchive();

            if ($validationZip->open($zipPath) !== true) {
                throw new \RuntimeException('El ZIP generado no es válido.');
            }

            $validationZip->close();

            // 8. Enviar correo
            $this->info('Enviando correo...');

            Mail::raw('Adjunto el respaldo más reciente de ' . config('app.name') . '.', function ($message) use ($zipPath) {
                $message->to('ajelof2@gmail.com')
                    ->subject('Backup ' . config('app.name') . ' - ' . now()->format('Y-m-d'))
                    ->attach($zipPath);
            });

            $this->info('Backup comprimido y enviado correctamente.');

            Log::info('Backup enviado por correo', [
                'zip' => $zipPath,
                'directorio' => $backupDirectory,
                'backups_incluidos' => array_column($selectedBackups, 'path'),
            ]);

            return self::SUCCESS;

        } catch (Throwable $exception) {
            $this->error('ERROR: ' . $exception->getMessage());
            $this->error('Archivo: ' . $exception->getFile());
            $this->error('Línea: ' . $exception->getLine());

            Log::error('Error enviando backup por correo', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return self::FAILURE;

        } finally {
            // 9. Eliminar ZIP temporal
            if (isset($zipPath) && is_file($zipPath)) {
                if (!@unlink($zipPath)) {
                    Log::warning('No se pudo eliminar el ZIP temporal: ' . $zipPath);
                }
            }
        }
    }
}