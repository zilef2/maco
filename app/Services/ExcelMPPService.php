<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;
use Throwable;

/**
 * Archivo MPP: Materiales, Productos, Proveedores y Fichas en un solo Excel.
 *
 * El libro debe traer las hojas "material", "producto" y "proveedor"; puede
 * traer además "ficha" con las recetas de los productos. Cualquier otra hoja se
 * descarta. Cada hoja se lee con el mismo formato de su tarjeta en el Centro de
 * Importación (ExcelMaterialesService, ExcelProductosService,
 * ExcelTalleresService y ExcelFichasProductoService), así que lo que sirve para
 * el MPP sirve también para la carga por entidad. La hoja "proveedor" es la
 * ficha de terceros: su columna de clasificación decide si cada fila termina
 * en Taller, Empresa externa o Proveedor.
 *
 * En la petición de subida solo se valida la fila de títulos; las filas de
 * datos se validan e insertan en el job. La importación es una sola
 * transacción: si una hoja falla, no queda nada importado de las demás. Por eso
 * el orden importa: los materiales señalan al proveedor habitual del mismo
 * archivo y la receta señala a los productos y materiales que el mismo archivo
 * acaba de escribir, así que "proveedor" va primero y "ficha" al final. Antes
 * de las fichas se sincronizan los materiales genéricos, que son los únicos que
 * admite la ficha técnica.
 */
class ExcelMPPService
{
    /**
     * Hojas que se leen, en el orden en que se importan.
     */
    public const HOJAS = ['proveedor', 'material', 'producto', 'ficha'];

    /** Sin alguna de estas tres no hay archivo MPP; la de fichas es opcional. */
    private const REQUERIDAS = ['proveedor', 'material', 'producto'];

    /** Títulos con los que además se reconoce la hoja de fichas: la plantilla
     * individual la llama "Codigos". */
    private const OTROS_TITULOS = ['codigos' => 'ficha', 'fichaproducto' => 'ficha'];

    public function __construct(
        private ExcelSecurityScanner $scanner,
        private ExcelMaterialesService $materiales,
        private ExcelProductosService $productos,
        private ExcelTalleresService $talleres,
        private ExcelFichasProductoService $fichas,
        private MaterialesGenericosService $genericos,
    ) {
    }

    /**
     * @return string[] Problemas del contenedor (vacío si el archivo es seguro)
     */
    public function escanearSeguridad(string $ruta): array
    {
        return $this->scanner->escanear($ruta);
    }

    /**
     * Lo que se comprueba antes de encolar el archivo: que estén las hojas
     * obligatorias y que los títulos de cada hoja que sí venga cumplan el
     * formato de su entidad.
     *
     * @throws RuntimeException con el aviso que debe ver el usuario
     */
    public function validarLibro(string $ruta): void
    {
        $titulos = $this->titulosPorHoja($ruta);

        $faltantes = array_values(array_diff(self::REQUERIDAS, array_keys($titulos)));
        if (!empty($faltantes)) {
            throw new RuntimeException($this->avisoHojasFaltantes($faltantes));
        }

        foreach (self::HOJAS as $hoja) {
            // La hoja de fichas es opcional; si el archivo la trae, sus títulos
            // se exigen aquí mismo para que el usuario los corrija sin esperar.
            if (!isset($titulos[$hoja])) {
                continue;
            }

            $this->conAvisoDeHoja($hoja, function () use ($hoja, $titulos): void {
                if ($hoja === 'material') {
                    $this->materiales->validarTitulos($titulos[$hoja]);
                } elseif ($hoja === 'producto') {
                    $this->productos->validarTitulos($titulos[$hoja]);
                } elseif ($hoja === 'ficha') {
                    $this->fichas->validarTitulos($titulos[$hoja]);
                } else {
                    $this->talleres->validarTitulos($titulos[$hoja]);
                }
            });
        }
    }

    /**
     * Importa las hojas del archivo, en el orden de `HOJAS`.
     *
     * @param  int|null  $usuarioId  El que subió el archivo; en la cola no hay
     *                               sesión y los movimientos de inventario
     *                               necesitan saber quién los causó.
     *
     * @return array{creados: int, actualizados: int, filas: int, desglose: array<string, array{creados: int, actualizados: int}>, avisos: string[]}
     *
     * @throws RuntimeException cuando alguna hoja no se puede importar
     */
    public function importar(string $ruta, ?int $usuarioId = null): array
    {
        $filasPorHoja = $this->filasPorHoja($ruta);

        $faltantes = array_values(array_diff(self::REQUERIDAS, array_keys($filasPorHoja)));
        if (!empty($faltantes)) {
            throw new RuntimeException($this->avisoHojasFaltantes($faltantes));
        }

        $desglose = [];
        $avisos = [];

        DB::transaction(function () use ($filasPorHoja, $usuarioId, &$desglose, &$avisos): void {
            foreach (self::HOJAS as $hoja) {
                if (!isset($filasPorHoja[$hoja])) {
                    continue;
                }

                if ($hoja === 'ficha') {
                    // Las recetas se escriben sobre el genérico del par y el
                    // archivo acaba de crear los materiales específicos: sin
                    // ese paso, la hoja de fichas rechazaría el archivo entero.
                    $aviso = $this->asegurarGenericos();
                    if ($aviso !== null) {
                        $avisos[] = $aviso;
                    }
                }

                $resultado = $this->conAvisoDeHoja(
                    $hoja,
                    fn (): array => $this->importarHoja($hoja, $filasPorHoja[$hoja], $usuarioId)
                );

                $desglose[$hoja] = $resultado;

                // Avisos que no cancelan la carga: los aporta la hoja de
                // fichas (genéricos nuevos y filas que llegaron sin consumo).
                foreach ((array) ($resultado['avisos'] ?? []) as $aviso) {
                    $avisos[] = $aviso;
                }
            }
        });

        return [
            'creados' => array_sum(array_column($desglose, 'creados')),
            'actualizados' => array_sum(array_column($desglose, 'actualizados')),
            'filas' => array_sum(array_column($desglose, 'filas')),
            'desglose' => array_map(
                fn (array $resultado): array => [
                    'creados' => $resultado['creados'],
                    'actualizados' => $resultado['actualizados'],
                ],
                $desglose
            ),
            'avisos' => array_values(array_unique($avisos)),
        ];
    }

    /**
     * Pasa los requisitos del archivo sin escribir: las hojas obligatorias, los
     * títulos de cada hoja y las filas de material, producto y proveedor, por el
     * mismo camino de lectura que usa `importar()`.
     *
     * La hoja "ficha" se revisa solo en formato. Sus recetas se validan contra
     * los materiales y productos que el propio archivo crea y contra el genérico
     * de cada familia, así que en la verificación todavía no existen en la app.
     *
     * @return array{problemas: string[], avisos: string[], resumen: string}
     */
    public function verificar(string $ruta): array
    {
        try {
            $this->validarLibro($ruta);
        } catch (Throwable $e) {
            return ['problemas' => [$e->getMessage()], 'avisos' => [], 'resumen' => ''];
        }

        $problemas = [];
        $avisos = [];
        $conteo = [];

        foreach ($this->filasPorHoja($ruta) as $hoja => $filas) {
            try {
                $conteo[$hoja] = $this->conteoDeVerificacion($hoja, $filas, $avisos);
            } catch (Throwable $e) {
                // Se acumula hoja por hoja y fila por fila: corregir un archivo no
                // debería costar una subida cada vez que aparece un error. Como el
                // servicio de la hoja lanza todos sus problemas juntos, cada uno
                // vuelve a su línea con el nombre de su hoja.
                foreach (preg_split('/\s\|\s/', $e->getMessage()) ?: [$e->getMessage()] as $parte) {
                    $problemas[] = 'La hoja "' . $hoja . '": ' . trim($parte);
                }
            }
        }

        $resumen = $conteo === [] ? '' : implode(', ', array_map(
            fn (string $hoja): string => count($conteo[$hoja]) . ' fila(s) en "' . $hoja . '"',
            array_keys($conteo)
        ));

        return ['problemas' => $problemas, 'avisos' => $avisos, 'resumen' => $resumen];
    }

    /**
     * Filas de la hoja que pasan su validación de lectura.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @param  string[]  $avisos
     */
    private function conteoDeVerificacion(string $hoja, array $filas, array &$avisos): array
    {
        if ($hoja === 'proveedor') {
            return $this->talleres->registrosDesdeFilas($filas);
        }

        if ($hoja === 'material') {
            $lectura = $this->materiales->registrosDesdeFilas($filas);
            $this->avisoDeColumnasIgnoradas($hoja, $lectura['ignoradas'], $avisos);

            return $lectura['registros'];
        }

        if ($hoja === 'producto') {
            $lectura = $this->productos->registrosDesdeFilas($filas);
            $this->avisoDeColumnasIgnoradas($hoja, $lectura['ignoradas'], $avisos);

            return $lectura['registros'];
        }

        $avisos[] = 'La hoja "ficha" se revisó solo en formato: sus recetas se validan al importar,'
            . ' cuando los materiales y productos del archivo ya están en la app.';

        return array_filter(array_slice($filas, 1), function (array $fila): bool {
            foreach ($fila as $celda) {
                if (is_scalar($celda) && trim((string) $celda) !== '') {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @param  string[]  $ignoradas
     * @param  string[]  $avisos
     */
    private function avisoDeColumnasIgnoradas(string $hoja, array $ignoradas, array &$avisos): void
    {
        if (!empty($ignoradas)) {
            $avisos[] = 'La hoja "' . $hoja . '" tiene columnas que no se reconocen y se ignoran: '
                . implode(', ', $ignoradas) . '.';
        }
    }

    /**
     * Título de hoja escrito como sea ("Material", "PRODUCTO ", " proveedor ",
     * "Codigos") y su nombre canónico, o null si la hoja se descarta.
     */
    public static function claveDeHoja(string $titulo): ?string
    {
        $clave = preg_replace(
            '/[^a-z0-9]/',
            '',
            mb_strtolower(trim($titulo), 'UTF-8')
        );
        $clave = strtr((string) $clave, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        $clave = self::OTROS_TITULOS[$clave] ?? $clave;

        return in_array($clave, self::HOJAS, true) ? $clave : null;
    }

    /**
     * Lee solo la fila 1 de cada hoja que interesa.
     *
     * @return array<string, array<int, mixed>> nombre canónico => títulos
     */
    private function titulosPorHoja(string $ruta): array
    {
        $spreadsheet = $this->cargar($ruta, true);

        try {
            $porHoja = [];
            foreach ($spreadsheet->getAllSheets() as $hoja) {
                $clave = self::claveDeHoja($hoja->getTitle());
                if ($clave === null || isset($porHoja[$clave])) {
                    continue;
                }
                $porHoja[$clave] = $hoja->toArray(null, true, true, false)[0] ?? [];
            }

            return $porHoja;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Lee todas las filas de las hojas que interesa.
     *
     * @return array<string, array<int, array<int, mixed>>> nombre canónico => filas
     */
    private function filasPorHoja(string $ruta): array
    {
        $spreadsheet = $this->cargar($ruta, false);

        try {
            $porHoja = [];
            foreach ($spreadsheet->getAllSheets() as $hoja) {
                $clave = self::claveDeHoja($hoja->getTitle());
                if ($clave === null || isset($porHoja[$clave])) {
                    continue;
                }
                $porHoja[$clave] = $hoja->toArray(null, true, true, false);
            }

            return $porHoja;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Carga el libro sin estilos y solo con las hojas del MPP: en la validación
     * de títulos además se lee únicamente la fila 1, que es lo que hace rápida
     * la petición de subida.
     */
    private function cargar(string $ruta, bool $soloTitulos): Spreadsheet
    {
        try {
            $reader = IOFactory::createReaderForFile($ruta);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new class($soloTitulos) implements IReadFilter
            {
                public function __construct(private bool $soloTitulos)
                {
                }

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    // Las hojas que no son material, producto, proveedor ni
                    // ficha se descartan sin leerlas.
                    if (ExcelMPPService::claveDeHoja($worksheetName) === null) {
                        return false;
                    }

                    return ! $this->soloTitulos || $row === 1;
                }
            });

            return $reader->load($ruta);
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                throw $e;
            }

            throw new RuntimeException('No se pudo leer el archivo Excel: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Valida las filas de una hoja e inserta. Devuelve el conteo de esa hoja.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @return array{creados: int, actualizados: int, filas: int, avisos?: string[]}
     */
    private function importarHoja(string $hoja, array $filas, ?int $usuarioId = null): array
    {
        if ($hoja === 'proveedor') {
            $registros = $this->talleres->registrosDesdeFilas($filas);

            return $this->conFilas($this->talleres->importar($registros), count($registros));
        }

        if ($hoja === 'material') {
            $lectura = $this->materiales->registrosDesdeFilas($filas);

            return $this->conFilas(
                $this->materiales->importar($lectura['registros'], $usuarioId),
                count($lectura['registros'])
            );
        }

        if ($hoja === 'ficha') {
            $lectura = $this->fichas->registrosDesdeFilas($filas);

            return $this->fichas->importarRegistros($lectura);
        }

        $lectura = $this->productos->registrosDesdeFilas($filas);

        return $this->conFilas($this->productos->importar($lectura['registros']), count($lectura['registros']));
    }

    /**
     * Pone al día los materiales genéricos antes de escribir las recetas: la
     * ficha técnica solo admite el CAT-BAS-0 del par y el archivo acaba de
     * crear los materiales específicos. Es el mismo sincronizador del botón
     * «Materiales genéricos» de Actividad, así que correrlo no duplica nada.
     */
    private function asegurarGenericos(): ?string
    {
        $resultado = $this->genericos->sincronizar();

        return $resultado['creados'] > 0
            ? 'La hoja "ficha" creó ' . $resultado['creados'] . ' material(es) genérico(s) que les faltaban a los pares del archivo.'
            : null;
    }

    /**
     * @param  array{creados: int, actualizados: int}  $resultado
     * @return array{creados: int, actualizados: int, filas: int}
     */
    private function conFilas(array $resultado, int $filas): array
    {
        return $resultado + ['filas' => $filas];
    }

    /**
     * En un libro de varias hojas, un aviso sin hoja no se puede corregir: todo
     * error se precede con el nombre de la hoja que lo produjo.
     */
    private function conAvisoDeHoja(string $hoja, callable $accion)
    {
        try {
            return $accion();
        } catch (Throwable $e) {
            throw new RuntimeException('La hoja "' . $hoja . '": ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param string[] $faltantes
     */
    private function avisoHojasFaltantes(array $faltantes): string
    {
        return 'Faltan las hojas: ' . implode(', ', $faltantes)
            . '. El archivo debe traer las hojas "material", "producto" y "proveedor";'
            . ' la hoja "ficha" es opcional y trae las recetas de los productos.';
    }
}
