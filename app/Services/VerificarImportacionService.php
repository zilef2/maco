<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Pasa los requisitos de una subida sin escribir nada en la base de datos.
 *
 * Corre el mismo recorrido de lectura que usa la importación —el contenedor, las
 * hojas, sus títulos y sus filas— pero en vez de abortar en el primer problema
 * los junta todos, que es lo que sirve para corregir el archivo de una pasada.
 * El resultado vuelve a la tarjeta del Centro de Importación: la verificación
 * nunca manda correo.
 *
 * Cada entidad verifica por el método de lectura de su propio servicio, así no
 * existe una segunda lista de requisitos que se desactualice de la importación.
 */
class VerificarImportacionService
{
    /** Entidades cuya subida ya tiene un camino de lectura reutilizable. */
    public const ENTIDADES = [
        'mpp',
        'producto',
        'material',
        'ficha_tecnica_materiales',
        'taller',
        'usuario',
    ];

    public function __construct(
        private ExcelSecurityScanner $scanner,
        private ExcelMPPService $mpp,
        private ExcelMaterialesService $materiales,
        private ExcelProductosService $productos,
        private ExcelTalleresService $talleres,
        private ExcelFichasProductoService $fichas,
        private ExcelUsuariosService $usuarios,
    ) {
    }

    /**
     * @return array{ok: bool, problemas: string[], avisos: string[], resumen: string}
     */
    public function verificar(string $entidad, UploadedFile $archivo): array
    {
        $problemas = $this->problemasDelContenedor($archivo);

        if ($problemas !== []) {
            return ['ok' => false, 'problemas' => $problemas, 'avisos' => [], 'resumen' => ''];
        }

        $ruta = (string) $archivo->getRealPath();

        try {
            $revisado = $this->contenido($entidad, $ruta);
        } catch (Throwable $e) {
            $revisado = [
                'problemas' => [$e->getMessage()],
                'avisos' => [],
                'resumen' => '',
            ];
        }

        return [
            'ok' => $revisado['problemas'] === [],
            'problemas' => self::listaProblemas($revisado['problemas']),
            'avisos' => $revisado['avisos'],
            'resumen' => $revisado['resumen'],
        ];
    }

    /**
     * Lo que se comprueba antes de tocar el libro: extensión, tamaño, firma del
     * contenedor y el escaneo de seguridad que también corre la subida.
     *
     * @return string[]
     */
    private function problemasDelContenedor(UploadedFile $archivo): array
    {
        $problemas = [];

        if (strtolower($archivo->getClientOriginalExtension()) !== 'xlsx') {
            $problemas[] = 'El archivo no tiene extensión .xlsx.';
        }

        if ($archivo->getSize() !== false && $archivo->getSize() > ExcelDesdeDriveService::TAMANO_MAXIMO) {
            $problemas[] = 'El archivo supera el tamaño máximo de 10 MB.';
        }

        $ruta = (string) $archivo->getRealPath();

        if (!is_readable($ruta)) {
            $problemas[] = 'El archivo no se pudo abrir en el servidor.';

            return $problemas;
        }

        if (!str_starts_with((string) file_get_contents($ruta, false, null, 0, 4), "PK\x03\x04")) {
            $problemas[] = 'El archivo no tiene una firma XLSX válida.';
        }

        if ($problemas === []) {
            try {
                $problemas = $this->scanner->escanear($ruta);
            } catch (Throwable $e) {
                $problemas = ['No se pudo escanear el archivo: ' . $e->getMessage()];
            }
        }

        return $problemas;
    }

    /**
     * @return array{problemas: string[], avisos: string[], resumen: string}
     */
    private function contenido(string $entidad, string $ruta): array
    {
        if ($entidad === 'mpp') {
            return $this->mpp->verificar($ruta);
        }

        if ($entidad === 'ficha_tecnica_materiales') {
            $validacion = $this->fichas->validar($ruta);

            return [
                'problemas' => [],
                'avisos' => $validacion['avisos'],
                'resumen' => $validacion['fichas'] . ' ficha(s), ' . $validacion['materiales']
                    . ' material(es) y ' . $validacion['filas'] . ' fila(s) de receta.',
            ];
        }

        if ($entidad === 'taller') {
            $this->talleres->validarEncabezados($ruta);
            $this->talleres->validarDestinos($ruta);
            $registros = $this->talleres->leerRegistros($ruta);

            return $this->conFilas($registros, 'tercero(s) listo(s) para importar.');
        }

        if ($entidad === 'usuario') {
            $this->usuarios->validarEncabezados($ruta);
            $registros = $this->usuarios->leerRegistros($ruta);

            return $this->conFilas($registros, 'usuario(s) listo(s) para importar.');
        }

        $filas = $this->filasDeHojaActiva($ruta);
        $servicio = $entidad === 'material' ? $this->materiales : $this->productos;
        $lectura = $servicio->registrosDesdeFilas($filas);

        $avisos = empty($lectura['ignoradas'])
            ? []
            : ['Columnas ignoradas (encabezado no reconocido): ' . implode(', ', $lectura['ignoradas']) . '.'];

        return [
            'problemas' => [],
            'avisos' => $avisos,
            'resumen' => count($lectura['registros']) . ' registro(s) listo(s) para importar en '
                . max(0, count($filas) - 1) . ' fila(s).',
        ];
    }

    /**
     * @param  array<int, mixed>  $registros
     * @return array{problemas: string[], avisos: string[], resumen: string}
     */
    private function conFilas(array $registros, string $resto): array
    {
        return [
            'problemas' => [],
            'avisos' => [],
            'resumen' => count($registros) . ' ' . $resto,
        ];
    }

    /**
     * La hoja activa, como la lee la subida individual de la entidad.
     *
     * @return array<int, array<int, mixed>>
     */
    private function filasDeHojaActiva(string $ruta): array
    {
        try {
            $lector = IOFactory::createReaderForFile($ruta);
            $lector->setReadDataOnly(true);
            $spreadsheet = $lector->load($ruta);
            $filas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (Throwable $e) {
            throw new \RuntimeException('No se pudo leer el archivo: ' . $e->getMessage(), 0, $e);
        }

        if ($filas === []) {
            throw new \RuntimeException('El archivo está vacío.');
        }

        return $filas;
    }

    /**
     * Los servicios juntan sus problemas en un solo mensaje separados por "|";
     * aquí cada uno vuelve a su propia línea, sin el preámbulo que el servicio
     * usa para anunciar la lista, para que la tarjeta los muestre de a uno.
     *
     * @param  string[]  $mensajes
     * @return string[]
     */
    private static function listaProblemas(array $mensajes): array
    {
        $preambulos = [
            'No se importó nada por los siguientes problemas: ',
            'No se pudo importar por los siguientes problemas: ',
            'El archivo tiene errores en sus datos: ',
        ];

        $lista = [];

        foreach ($mensajes as $mensaje) {
            foreach (preg_split('/\s\|\s/', $mensaje) ?: [$mensaje] as $parte) {
                $parte = trim($parte);
                foreach ($preambulos as $preambulo) {
                    $parte = str_replace($preambulo, '', $parte);
                }

                if ($parte !== '') {
                    $lista[] = trim($parte);
                }
            }
        }

        return $lista;
    }
}
