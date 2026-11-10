<?php

namespace App\Services;

use App\Models\Proveedor;
use App\Services\Concerns\LeeExcelRapido;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Validación, escaneo de seguridad e importación de proveedores desde Excel.
 *
 * El formato es el mismo del formulario de proveedores: la fila 1 trae los
 * títulos de las columnas y las siguientes filas los datos. Las columnas
 * obligatorias son nit y nombre_comercial; el resto admite los campos
 * opcionales del proveedor (razón social, contacto, condiciones comerciales y
 * estado).
 *
 * Un proveedor se actualiza cuando ya existe con el mismo NIT y, si el NIT
 * no coincide, con el mismo nombre comercial. Las celdas vacías no borran
 * el dato que ya tiene el registro.
 */
class ExcelProveedoresService
{
    use LeeExcelRapido;

    public const MAX_BYTES = ExcelSecurityScanner::MAX_BYTES;

    /** Columnas obligatorias del archivo. */
    public const COLUMNAS_REQUERIDAS = ['nit', 'nombre_comercial'];

    /** Columnas opcionales del archivo. */
    public const COLUMNAS_OPCIONALES = [
        'razon_social',
        'direccion',
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

    /**
     * Encabezados aceptados (ya normalizados) y la columna a la que apuntan.
     * Permite tanto el nombre legible como el nombre interno del campo.
     */
    private const ALIAS_COLUMNAS = [
        'nit'                  => 'nit',
        'nombrecomercial'      => 'nombre_comercial',
        'nombre'               => 'nombre_comercial',
        'razonsocial'          => 'razon_social',
        'direccion'            => 'direccion',
        'observacionesdireccion' => 'observaciones_direccion',
        'observacionesdedireccion' => 'observaciones_direccion',
        'correoelectronico'    => 'correo_electronico',
        'email'                => 'correo_electronico',
        'ciudad'               => 'ciudad',
        'telefonoprincipal'    => 'telefono_principal',
        'tipoproveedor'        => 'tipo_proveedor',
        'quesuministra'        => 'que_suministra',
        'personacontacto'      => 'persona_contacto',
        'personadecontacto'    => 'persona_contacto',
        'celularcontacto'      => 'celular_contacto',
        'celulardecontacto'    => 'celular_contacto',
        'formapago'            => 'forma_pago',
        'formadepago'          => 'forma_pago',
        'tiempoentrega'        => 'tiempo_entrega',
        'tiempodeentrega'      => 'tiempo_entrega',
        'estado'               => 'estado',
        'observacionesgenerales' => 'observaciones_generales',
    ];

    /**
     * Reglas por columna, en línea con StoreProveedorRequest.
     *
     * @var array<string, array{max?: int, email?: bool, in?: array<int, string>}>
     */
    private const REGLAS = [
        'nit'                    => ['max' => 255],
        'nombre_comercial'       => ['max' => 255],
        'razon_social'           => ['max' => 255],
        'direccion'              => ['max' => 255],
        'observaciones_direccion' => ['max' => 1000],
        'correo_electronico'     => ['email' => true, 'max' => 255],
        'ciudad'                 => ['max' => 255],
        'telefono_principal'     => ['max' => 50],
        'tipo_proveedor'         => ['in' => Proveedor::TIPOS_PROVEEDOR],
        'que_suministra'         => ['max' => 1000],
        'persona_contacto'       => ['max' => 255],
        'celular_contacto'       => ['max' => 50],
        'forma_pago'             => ['in' => Proveedor::FORMAS_PAGO],
        'tiempo_entrega'         => ['max' => 255],
        'estado'                 => ['in' => Proveedor::ESTADOS],
        'observaciones_generales' => ['max' => 5000],
    ];

    public function __construct(private ExcelSecurityScanner $scanner)
    {
    }

    /**
     * Columnas permitidas, derivadas del formato actual del formulario.
     *
     * @return string[]
     */
    public function columnasPermitidas(): array
    {
        return array_merge(self::COLUMNAS_REQUERIDAS, self::COLUMNAS_OPCIONALES);
    }

    /**
     * Columnas opcionales (todas las permitidas menos las obligatorias).
     *
     * @return string[]
     */
    public function columnasOpcionales(): array
    {
        return array_values(array_diff($this->columnasPermitidas(), self::COLUMNAS_REQUERIDAS));
    }

    /**
     * @return string[]
     */
    public function escanearSeguridad(string $ruta): array
    {
        return $this->scanner->escanear($ruta);
    }

    /**
     * Valida solo la fila 1. Es lo que se ejecuta durante la subida: leer
     * todas las filas aquí duplicaba el trabajo del job y hacía lenta la
     * petición sin aportar nada, porque el archivo se vuelve a validar entero
     * antes de tocar la base de datos.
     *
     * @throws RuntimeException cuando el encabezado no cumple el formato
     */
    public function validarEncabezados(string $ruta): void
    {
        $this->validarTitulos($this->leerFilaTitulos($ruta));
    }

    /**
     * Validez de una fila de títulos ya leída: la usa la tarjeta MPP, que lee
     * las hojas del libro por su nombre y las pasa todas al mismo formato.
     *
     * @param  array<int, mixed>  $filaTitulos
     *
     * @throws RuntimeException cuando el encabezado no cumple el formato
     */
    public function validarTitulos(array $filaTitulos): void
    {
        $this->columnasDesdeTitulos($filaTitulos);
    }

    /**
     * Lee la primera hoja, valida los títulos (fila 1) y todas las filas de
     * datos. Devuelve los registros listos para importar.
     *
     * @return array<int, array<string, string|null>>
     *
     * @throws RuntimeException cuando el archivo no cumple el formato
     */
    public function leerRegistros(string $ruta): array
    {
        return $this->registrosDesdeFilas($this->leerTodasLasFilas($ruta));
    }

    /**
     * Valida los títulos (posición 0) y el resto de las filas de una hoja.
     *
     * @param  array<int, array<int, mixed>>  $filas
     * @return array<int, array<string, string|null>>
     *
     * @throws RuntimeException cuando el archivo no cumple el formato
     */
    public function registrosDesdeFilas(array $filas): array
    {
        if (empty($filas)) {
            throw new RuntimeException('El archivo está vacío.');
        }

        $columnas = $this->columnasDesdeTitulos($filas[0] ?? []);
        $registros = [];
        $errores = [];

        foreach (array_slice($filas, 1) as $posicion => $fila) {
            $numeroFila = $posicion + 2; // La fila 1 son los títulos.

            $filaVacia = true;
            foreach ($fila as $valor) {
                if ($valor !== null && trim((string) $valor) !== '') {
                    $filaVacia = false;
                    break;
                }
            }
            if ($filaVacia) {
                continue;
            }

            $registro = [];
            foreach ($columnas as $indice => $canonica) {
                $valor = $this->limpiarValor($fila[$indice] ?? null);
                $registro[$canonica] = $valor === '' ? null : $valor;
            }

            $this->validarRegistro($registro, $numeroFila, $errores);

            $registros[] = $registro;
        }

        if (!empty($errores)) {
            throw new RuntimeException('El archivo tiene errores en sus datos: ' . implode(' | ', $errores));
        }
        if (empty($registros)) {
            throw new RuntimeException('El archivo no contiene filas de datos debajo de los títulos.');
        }

        return $registros;
    }

    /**
     * Inserta los proveedores nuevos y actualiza los existentes.
     *
     * El emparejamiento se hace por NIT y, si no coincide, por nombre
     * comercial (los proveedores que vienen de la carga anterior solo tienen
     * el nombre). La comparación ignora mayúsculas, tildes y puntuación.
     *
     * Los proveedores nuevos se insertan en bloque: antes se hacía un
     * INSERT por fila y, además, el modelo consultaba el último código en
     * cada creación, lo que volvía la carga cuadrática con el tamaño del
     * archivo.
     *
     * @param  array<int, array<string, string|null>>  $registros
     * @return array{creados: int, actualizados: int}
     */
    public function importar(array $registros): array
    {
        $creados = 0;
        $actualizados = 0;

        DB::transaction(function () use ($registros, &$creados, &$actualizados) {
            $existentesPorNit = [];
            $existentesPorNombre = [];

            foreach (Proveedor::query()->get(['id', 'nit', 'nombre_comercial']) as $proveedor) {
                $porNit = $this->claveComparacion($proveedor->nit);
                $porNombre = $this->claveComparacion($proveedor->nombre_comercial);

                if ($porNit !== '') {
                    $existentesPorNit[$porNit] = $proveedor->id;
                }
                if ($porNombre !== '') {
                    $existentesPorNombre[$porNombre] = $proveedor->id;
                }
            }

            $nuevos = [];
            $nuevosPorNit = [];
            $nuevosPorNombre = [];

            foreach ($registros as $registro) {
                $nit = $this->claveComparacion($registro['nit'] ?? null);
                $nombre = $this->claveComparacion($registro['nombre_comercial'] ?? null);
                $datos = $this->datosAGuardar($registro);

                $idExistente = null;
                if ($nit !== '' && isset($existentesPorNit[$nit])) {
                    $idExistente = $existentesPorNit[$nit];
                } elseif ($nombre !== '' && isset($existentesPorNombre[$nombre])) {
                    $idExistente = $existentesPorNombre[$nombre];
                }

                if ($idExistente !== null) {
                    // Las celdas vacías no borran lo que ya tenía el registro.
                    $datos = array_filter($datos, fn ($valor): bool => $valor !== null && $valor !== '');
                    if (!empty($datos)) {
                        Proveedor::whereKey($idExistente)->update($datos);
                    }
                    $actualizados++;
                    continue;
                }

                // Fila repetida dentro del mismo archivo: se completa la que
                // ya está pendiente de insertar en vez de crear un duplicado.
                $indiceNuevo = null;
                if ($nit !== '' && isset($nuevosPorNit[$nit])) {
                    $indiceNuevo = $nuevosPorNit[$nit];
                } elseif ($nombre !== '' && isset($nuevosPorNombre[$nombre])) {
                    $indiceNuevo = $nuevosPorNombre[$nombre];
                }

                if ($indiceNuevo !== null) {
                    $nuevos[$indiceNuevo] = array_merge(
                        $nuevos[$indiceNuevo],
                        array_filter($datos, fn ($valor): bool => $valor !== null && $valor !== '')
                    );
                    if ($nit !== '') {
                        $nuevosPorNit[$nit] = $indiceNuevo;
                    }
                    if ($nombre !== '') {
                        $nuevosPorNombre[$nombre] = $indiceNuevo;
                    }
                    $actualizados++;
                    continue;
                }

                $datos['estado'] = $datos['estado'] ?? Proveedor::ESTADOS[0];

                $indice = count($nuevos);
                $nuevos[] = $datos;

                if ($nit !== '') {
                    $nuevosPorNit[$nit] = $indice;
                }
                if ($nombre !== '') {
                    $nuevosPorNombre[$nombre] = $indice;
                }
                $creados++;
            }

            if (!empty($nuevos)) {
                $siguienteCodigo = $this->siguienteNumeroCodigo();
                $ahora = now();

                foreach ($nuevos as &$datos) {
                    $datos['codigo'] = 'PRV-' . str_pad(
                        (string) $siguienteCodigo++,
                        3,
                        '0',
                        STR_PAD_LEFT
                    );
                    $datos['tipo'] = 'Proveedor';
                    $datos['nombre'] = $datos['nombre']
                        ?? ($datos['nombre_comercial'] ?? ($datos['razon_social'] ?? 'Sin nombre'));
                    $datos['created_at'] = $ahora;
                    $datos['updated_at'] = $ahora;
                }
                unset($datos);

                // El insert por bloques arma una sola sentencia: todas las filas
                // deben traer las mismas columnas y en el mismo orden, y aquí
                // cada fila guarda solo las celdas que el archivo llenó.
                $columnas = [];
                foreach ($nuevos as $datos) {
                    foreach (array_keys($datos) as $campo) {
                        $columnas[$campo] = true;
                    }
                }
                $columnas = array_keys($columnas);

                $filas = array_map(
                    fn (array $datos): array => array_replace(array_fill_keys($columnas, null), $datos),
                    $nuevos
                );

                foreach (array_chunk($filas, 500) as $bloque) {
                    Proveedor::insert($bloque);
                }
            }
        });

        return [
            'creados' => $creados,
            'actualizados' => $actualizados,
        ];
    }

    /**
     * Siguiente número de código disponible. Se toma el mayor número de todos
     * los códigos existentes: ordenar por el texto hacía que PRV-999 quedara
     * antes que PRV-1000 y se repitieran códigos.
     */
    private function siguienteNumeroCodigo(): int
    {
        $maximo = 0;

        foreach (Proveedor::query()->whereNotNull('codigo')->pluck('codigo') as $codigo) {
            $numero = (int) preg_replace('/\D+/', '', (string) $codigo);
            if ($numero > $maximo) {
                $maximo = $numero;
            }
        }

        return $maximo + 1;
    }

    /**
     * Resuelve y valida la fila de títulos.
     *
     * @param  array<int, mixed>  $filaTitulos
     * @return array<int, string> índice de columna => nombre canónico
     */
    private function columnasDesdeTitulos(array $filaTitulos): array
    {
        $columnas = [];
        $desconocidas = [];

        foreach ($filaTitulos as $indice => $valor) {
            $tituloOriginal = trim((string) $valor);
            $titulo = $this->normalizarTitulo((string) $valor);
            if ($titulo === '') {
                continue;
            }

            $canonica = $this->resolverColumna($titulo);
            if ($canonica === null) {
                $desconocidas[] = $tituloOriginal;
                continue;
            }

            if (in_array($canonica, $columnas, true)) {
                throw new RuntimeException("La columna \"{$tituloOriginal}\" está repetida en la fila 1.");
            }

            $columnas[$indice] = $canonica;
        }

        if (!empty($desconocidas)) {
            throw new RuntimeException(
                'La columna ' . implode(', ', array_map(fn (string $t): string => "\"{$t}\"", array_unique($desconocidas)))
                . ' no está en el formato de proveedores.'
            );
        }

        $faltantes = array_values(array_diff(self::COLUMNAS_REQUERIDAS, $columnas));
        if (!empty($faltantes)) {
            throw new RuntimeException(
                'La fila 1 debe contener los títulos de las columnas y faltan las obligatorias: '
                . implode(', ', $faltantes) . '.'
            );
        }

        return $columnas;
    }

    /**
     * Resuelve el encabezado normalizado a la columna canónica.
     * Acepta el nombre exacto o el que lo tiene como prefijo
     * (ej. "NIT del proveedor" → nit).
     */
    private function resolverColumna(string $titulo): ?string
    {
        if (isset(self::ALIAS_COLUMNAS[$titulo])) {
            return self::ALIAS_COLUMNAS[$titulo];
        }

        $alias = array_keys(self::ALIAS_COLUMNAS);
        usort($alias, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($alias as $clave) {
            if (str_starts_with($titulo, $clave)) {
                return self::ALIAS_COLUMNAS[$clave];
            }
        }

        return null;
    }

    /**
     * Valida los valores del registro con las reglas del formulario.
     *
     * @param  array<string, string|null>  $registro
     * @param  array<int, string>  $errores
     */
    private function validarRegistro(array $registro, int $numeroFila, array &$errores): void
    {
        foreach (self::COLUMNAS_REQUERIDAS as $campo) {
            if (($registro[$campo] ?? null) === null) {
                $errores[] = "Fila {$numeroFila}: el campo {$campo} es obligatorio.";
            }
        }

        foreach (self::REGLAS as $campo => $reglas) {
            $valor = $registro[$campo] ?? null;
            if ($valor === null) {
                continue;
            }

            if (isset($reglas['max']) && mb_strlen($valor) > $reglas['max']) {
                $errores[] = "Fila {$numeroFila}: {$campo} supera los {$reglas['max']} caracteres.";
                continue;
            }

            if (!empty($reglas['email']) && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                $errores[] = "Fila {$numeroFila}: {$campo} no es un correo válido.";
                continue;
            }

            if (isset($reglas['in']) && !in_array($valor, $reglas['in'], true)) {
                $errores[] = "Fila {$numeroFila}: {$campo} debe ser uno de: "
                    . implode(', ', $reglas['in']) . '.';
            }
        }
    }

    /**
     * Datos a persistir: solo las columnas del formato, sin valores nulos.
     *
     * @param  array<string, string|null>  $registro
     * @return array<string, string>
     */
    private function datosAGuardar(array $registro): array
    {
        $datos = [];
        foreach ($this->columnasPermitidas() as $campo) {
            $valor = $registro[$campo] ?? null;
            if ($valor !== null) {
                $datos[$campo] = $valor;
            }
        }

        return $datos;
    }

    /**
     * Clave de comparación: sin mayúsculas, tildes ni puntuación.
     */
    private function claveComparacion(?string $valor): string
    {
        return (string) preg_replace(
            '/[^a-z0-9]/',
            '',
            $this->normalizarTitulo((string) $valor)
        );
    }

    /** Normaliza un título: minúsculas, sin acentos ni signos. */
    private function normalizarTitulo(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]/', '', $texto) ?? '';
    }

    /** Limpia el valor de una celda (sin HTML y sin espacios sobrantes). */
    private function limpiarValor(mixed $valor): string
    {
        return trim(strip_tags((string) ($valor ?? '')));
    }

    public function procesarSubida($archivo, $usuario, ?int $importacionId = null): string
    {
        if (!$archivo) {
            throw new RuntimeException('Selecciona un archivo .xlsx.');
        }

        $nombreArchivo = $archivo->getClientOriginalName();
        $problemas = $this->escanearSeguridad($archivo->getRealPath());
        if (!empty($problemas)) {
            throw new RuntimeException(implode(' | ', $problemas));
        }

        // Solo se valida el encabezado en la petición. El contenido completo
        // se valida en el job, que es quien hace la inserción.
        $this->validarEncabezados($archivo->getRealPath());

        $rutaRelativa = $archivo->store('imports/proveedores', 'local');
        if (!$rutaRelativa) {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }

        \App\Jobs\ImportarProveedoresJob::dispatch(
            $rutaRelativa,
            $nombreArchivo,
            $usuario?->email,
            $usuario?->name,
            $usuario?->id,
            $importacionId,
        );

        return $rutaRelativa;
    }
}
