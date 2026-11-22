<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Baja el .xlsx que está publicado en un enlace de Google Drive o de Sheets.
 *
 * La subida del Centro de Importación acepta el archivo o el enlace: con el
 * enlace el catálogo se vuelve a cargar sin descargarlo y volverlo a adjuntar,
 * que es lo que se hace cuando el archivo vive en la nube y se edita ahí.
 *
 * Google solo deja bajar sin sesión lo que está compartido como "Cualquiera con
 * el enlace"; si no, responde con una página de acceso en lugar del libro. Un
 * .xlsx subido a Drive se baja directo, pero una hoja nativa de Sheets hay que
 * exportarla, así que se prueban las dos direcciones con el mismo id.
 */
class ExcelDesdeDriveService
{
    public const TAMANO_MAXIMO = 10 * 1024 * 1024;

    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const HOSTS = ['drive.google.com', 'docs.google.com'];

    /**
     * @return UploadedFile lista para el mismo código que valida el archivo adjunto.
     */
    public function descargar(string $enlace): UploadedFile
    {
        $id = self::idDelEnlace($enlace);

        if ($id === null) {
            throw new RuntimeException('El enlace no apunta a un archivo de Google Drive ni de Google Sheets.');
        }

        $carpeta = $this->carpetaTemporal();
        $motivos = [];

        foreach ($this->direcciones($id) as $direccion) {
            try {
                $respuesta = $this->pedir($direccion);
            } catch (\Throwable $e) {
                $motivos[] = $e->getMessage();
                continue;
            }

            $ruta = $carpeta . '/' . Str::uuid() . '.xlsx';
            file_put_contents($ruta, $respuesta->body());

            return new UploadedFile(
                $ruta,
                self::nombreDesdeRespuesta($respuesta, $id),
                self::MIME_XLSX,
                null,
                true,
            );
        }

        throw new RuntimeException(
            'Google no dejó bajar el Excel (' . implode(' | ', $motivos) . ').'
            . ' Compartí el archivo como "Cualquiera con el enlace" con permiso de descarga, o subí el .xlsx.'
        );
    }

    /**
     * Nombre del id que trae el enlace, para la bitácora de importaciones.
     */
    public static function nombreDesdeEnlace(?string $enlace): ?string
    {
        $id = ($enlace === null || trim($enlace) === '') ? null : self::idDelEnlace($enlace);

        return $id === null ? null : 'Google Drive: ' . $id;
    }

    /**
     * El id del archivo: /file/d/ID/view, /spreadsheets/d/ID/edit, ?id=ID.
     */
    public static function idDelEnlace(string $enlace): ?string
    {
        $enlace = trim($enlace);

        if (!preg_match('#^https?://#i', $enlace)) {
            return null;
        }

        $host = strtolower((string) parse_url($enlace, PHP_URL_HOST));
        if (!in_array($host, self::HOSTS, true)) {
            return null;
        }

        // Una carpeta no es un archivo: no hay libro que bajar.
        if (str_contains((string) parse_url($enlace, PHP_URL_PATH), '/folders/')) {
            return null;
        }

        if (preg_match('#/(?:file/d|d|spreadsheets/d|documents/d)/([a-zA-Z0-9_-]{10,})#', $enlace, $m)) {
            return $m[1];
        }

        if (preg_match('#[?&]id=([a-zA-Z0-9_-]{10,})#', $enlace, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return string[] la descarga de Drive y, por si es una hoja de Sheets, su exportación
     */
    private function direcciones(string $id): array
    {
        return [
            'https://drive.google.com/uc?export=download&confirm=t&id=' . $id,
            'https://docs.google.com/spreadsheets/d/' . $id . '/export?format=xlsx',
        ];
    }

    private function pedir(string $direccion): \Illuminate\Http\Client\Response
    {
        $respuesta = Http::timeout(120)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
            ->get($direccion);

        if (!$respuesta->successful()) {
            throw new RuntimeException('Google respondió ' . $respuesta->status());
        }

        $cuerpo = (string) $respuesta->body();

        if (!str_starts_with($cuerpo, "PK\x03\x04")) {
            throw new RuntimeException(str_contains(strtolower($cuerpo), '<html')
                ? 'el enlace no tiene permiso de descarga'
                : 'Google no devolvió un .xlsx');
        }

        if (strlen($cuerpo) > self::TAMANO_MAXIMO) {
            throw new RuntimeException('el archivo de Drive supera los 10 MB permitidos');
        }

        return $respuesta;
    }

    /**
     * El nombre real del libro viene en Content-Disposition; si Google no lo
     * manda, se identifica por su id para que el resultado sea reconocible.
     */
    private static function nombreDesdeRespuesta(\Illuminate\Http\Client\Response $respuesta, string $id): string
    {
        $cabecera = (string) $respuesta->header('Content-Disposition');

        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $cabecera, $m)) {
            $nombre = trim(rawurldecode($m[1]));
            if ($nombre !== '') {
                return Str::finish($nombre, '.xlsx');
            }
        }

        return 'drive-' . $id . '.xlsx';
    }

    /**
     * Carpeta temporal de la descarga. Los archivos quedan porque los controladores
     * los leen y los copian durante la petición; se barren los de intentos anteriores.
     */
    private function carpetaTemporal(): string
    {
        $carpeta = storage_path('app/imports/drive');

        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0775, true);
        }

        foreach ((array) glob($carpeta . '/*.xlsx') ?: [] as $viejo) {
            if (filemtime($viejo) < time() - 86400) {
                @unlink($viejo);
            }
        }

        return $carpeta;
    }
}
