<?php

namespace App\Services;

use App\Models\EmpresaExterna;
use App\Models\Proveedor;
use App\Models\Taller;
use App\Services\Concerns\LeeExcelRapido;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Validación, escaneo de seguridad e importación de la ficha de terceros.
 *
 * El archivo de talleres es también el archivo de empresas externas y de
 * proveedores: la columna "proveedor o externa o macondas" decide el destino
 * de cada fila (macondas → Taller, taller de proceso externo → EmpresaExterna,
 * proveedor → Proveedor). Las tres entidades comparten la misma ficha, así que
 * el resto de columnas se interpretan igual en los tres casos.
 *
 * Un registro se actualiza cuando ya existe con el mismo código de la entidad,
 * el mismo NIT o el mismo nombre comercial; si no, se crea. Las celdas vacías
 * no borran el dato que ya tiene el registro.
 */
class ExcelTalleresService
{
    use LeeExcelRapido;

    public const MAX_BYTES = ExcelSecurityScanner::MAX_BYTES;

    /** Clave del archivo para el código: cada destino lo guarda en su columna. */
    public const COLUMNA_CODIGO = 'codigo';

    /** Columnas obligatorias del archivo. */
    public const COLUMNAS_REQUERIDAS = ['nombre_comercial', 'tipo_proveedor', 'clasificacion'];

    /** Columnas opcionales del archivo. */
    public const COLUMNAS_OPCIONALES = [
        'codigo',
        'nit',
        'razon_social',
        'direccion',
        'observaciones_direccion',
        'correo_electronico',
        'ciudad',
        'telefono_principal',
        'que_suministra',
        'persona_contacto',
        'celular_contacto',
        'forma_pago',
        'tiempo_entrega',
        'estado',
        'observaciones_generales',
        'clasificacion',
    ];

    /**
     * Destino de cada valor de "proveedor o externa o macondas", en la forma
     * en que lo escribe claveComparacion() (sin mayúsculas, tildes ni signos).
     * Fuera de estos tres valores no se importa nada.
     */
    private const DESTINOS = [
        'tallerdeprocesoexterno' => [
            'modelo' => EmpresaExterna::class,
            'codigo' => 'codigo_empresa_externa',
            'prefijo' => 'EMP',
        ],
        'macondas' => [
            'modelo' => Taller::class,
            'codigo' => 'codigo_taller',
            'prefijo' => 'TAL',
        ],
        'proveedor' => [
            'modelo' => Proveedor::class,
            'codigo' => 'codigo_proveedor',
            'prefijo' => 'PRV',
        ],
    ];

    /**
     * Encabezados aceptados (ya normalizados) y la columna a la que apuntan.
     * Acepta el nombre legible del archivo y el nombre interno del campo.
     */
    private const ALIAS_COLUMNAS = [
        'codigoproveedor'      => 'codigo',
        'codigotaller'         => 'codigo',
        'codigoempresaexterna' => 'codigo',
        'codigoempresa'        => 'codigo',
        'codigo'               => 'codigo',
        'nit'                  => 'nit',
        'nombrecomercial'      => 'nombre_comercial',
        'nombre'               => 'nombre_comercial',
        'razonsocial'          => 'razon_social',
        'direccion'            => 'direccion',
        'observaciones'        => 'observaciones_direccion',
        'observacionesdireccion' => 'observaciones_direccion',
        'observacionesdedireccion' => 'observaciones_direccion',
        'correo'               => 'correo_electronico',
        'correoelectronico'    => 'correo_electronico',
        'email'                => 'correo_electronico',
        'ciudad'               => 'ciudad',
        'telefono'             => 'telefono_principal',
        'telefonoprincipal'    => 'telefono_principal',
        'tipoproveedor'        => 'tipo_proveedor',
        'quesuministra'        => 'que_suministra',
        'personacontacto'      => 'persona_contacto',
        'personadecontacto'    => 'persona_contacto',
        'celular'              => 'celular_contacto',
        'celularcontacto'      => 'celular_contacto',
        'celulardecontacto'    => 'celular_contacto',
        'celularwhatsapp'      => 'celular_contacto',
        'celulardecontactowhatsapp' => 'celular_contacto',
        'formapago'            => 'forma_pago',
        'formadepago'          => 'forma_pago',
        'plazopago'            => 'forma_pago',
        'plazodepago'          => 'forma_pago',
        'tiempoentrega'        => 'tiempo_entrega',
        'tiempodeentrega'      => 'tiempo_entrega',
        'tiempohabitualentrega' => 'tiempo_entrega',
        'tiempohabitualdeentrega' => 'tiempo_entrega',
        'estado'               => 'estado',
        'observacionesgenerales' => 'observaciones_generales',
        'clasificacion'        => 'clasificacion',
        'proveedoroexternaomacondas' => 'clasificacion',
    ];

    /**
     * Reglas por columna, en línea con StoreTallerRequest.
     *
     * @var array<string, array{max?: int, email?: bool}>
     */
    private const REGLAS = [
        'codigo'                 => ['max' => 50],
        'nit'                    => ['max' => 255],
        'nombre_comercial'       => ['max' => 255],
        'razon_social'           => ['max' => 255],
        'direccion'              => ['max' => 255],
        'observaciones_direccion' => ['max' => 1000],
        'correo_electronico'     => ['email' => true, 'max' => 255],
        'ciudad'                 => ['max' => 255],
        'telefono_principal'     => ['max' => 50],
        'que_suministra'         => ['max' => 1000],
        'persona_contacto'       => ['max' => 255],
        'celular_contacto'       => ['max' => 50],
        'tiempo_entrega'         => ['max' => 255],
        'observaciones_generales' => ['max' => 5000],
    ];

    /** Catálogos: la celda debe coincidir con uno de sus valores. */
    private const CATALOGOS = [
        'tipo_proveedor' => Proveedor::TIPOS_PROVEEDOR,
        'forma_pago' => Proveedor::FORMAS_PAGO,
        'estado' => Proveedor::ESTADOS,
        'clasificacion' => Proveedor::CLASIFICACIONES,
    ];

    public function __construct(private ExcelSecurityScanner $scanner)
    {
    }

    /**
     * @return string[]
     */
    public function columnasPermitidas(): array
    {
        return array_merge(self::COLUMNAS_REQUERIDAS, self::COLUMNAS_OPCIONALES);
    }

    /**
     * @return string[]
     */
    public function columnasOpcionales(): array
    {
        return array_values(array_diff($this->columnasPermitidas(), self::COLUMNAS_REQUERIDAS));
    }

    /**
     * Escaneo de seguridad del archivo .xlsx.
     *
     * @return string[]
     */
    public function escanearSeguridad(string $ruta): array
    {
        return $this->scanner->escanear($ruta);
    }

    /**
     * Valida solo la fila 1: el contenido completo lo valida el job antes de
     * tocar la base de datos.
     *
     * @throws RuntimeException cuando el encabezado no cumple el formato
     */
    public function validarEncabezados(string $ruta): void
    {
        $this->columnasDesdeTitulos($this->leerFilaTitulos($ruta));
    }

    /**
     * Verifica en la petición de subida el destino de cada fila: solo se
     * aceptan los tres valores de "proveedor o externa o macondas". Con una
     * celda vacía o distinta se cancela la subida antes de encolar nada,
     * porque la fila terminaría en la entidad equivocada.
     *
     * @throws RuntimeException cuando alguna fila no tiene destino válido
     */
    public function validarDestinos(string $ruta): void
    {
        $filas = $this->leerTodasLasFilas($ruta);
        if (empty($filas)) {
            throw new RuntimeException('El archivo está vacío.');
        }

        $columnas = $this->columnasDesdeTitulos($filas[0] ?? []);
        $indiceDestino = array_search('clasificacion', $columnas, true);

        $problemas = [];
        foreach (array_slice($filas, 1) as $posicion => $fila) {
            if ($this->esFilaVacia($fila)) {
                continue;
            }

            $valor = $this->limpiarValor($fila[$indiceDestino] ?? null);
            if (!isset(self::DESTINOS[$this->claveComparacion($valor)])) {
                $problemas[] = 'fila ' . ($posicion + 2) . ': ' . ($valor === '' ? 'vacía' : '"' . $valor . '"');
            }
        }

        if (empty($problemas)) {
            return;
        }

        $recortados = array_slice($problemas, 0, 10);
        if (count($problemas) > count($recortados)) {
            $recortados[] = 'y ' . (count($problemas) - count($recortados)) . ' filas más';
        }

        throw new RuntimeException(
            'La columna "proveedor o externa o macondas" solo acepta '
            . implode(', ', Proveedor::CLASIFICACIONES) . '. Corríjelas en el archivo: '
            . implode(', ', $recortados) . '.'
        );
    }

    /**
     * Lee la hoja activa, valida los títulos (fila 1) y todas las filas de
     * datos. Devuelve los registros listos para importar.
     *
     * @return array<int, array<string, string|null>>
     *
     * @throws RuntimeException cuando el archivo no cumple el formato
     */
    public function leerRegistros(string $ruta): array
    {
        $filas = $this->leerTodasLasFilas($ruta);
        if (empty($filas)) {
            throw new RuntimeException('El archivo está vacío.');
        }

        $columnas = $this->columnasDesdeTitulos($filas[0] ?? []);
        $registros = [];
        $errores = [];

        foreach (array_slice($filas, 1) as $posicion => $fila) {
            $numeroFila = $posicion + 2; // La fila 1 son los títulos.

            if ($this->esFilaVacia($fila)) {
                continue;
            }

            $registro = [];
            foreach ($columnas as $indice => $canonica) {
                $valor = $this->limpiarValor($fila[$indice] ?? null);
                $registro[$canonica] = $valor === '' ? null : $valor;
            }

            $this->canonizarCatalogos($registro, $numeroFila, $errores);
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
     * Reparte las filas entre Taller, EmpresaExterna y Proveedor según la
     * columna de clasificación, y escribe cada grupo en bloque.
     *
     * @param  array<int, array<string, string|null>>  $registros
     * @return array{creados: int, actualizados: int}
     */
    public function importar(array $registros): array
    {
        $creados = 0;
        $actualizados = 0;
        $escribeEmpresaExterna = false;

        DB::transaction(function () use ($registros, &$creados, &$actualizados, &$escribeEmpresaExterna) {
            $existentes = [];
            $pendientes = [];
            $nuevos = [];

            foreach (array_keys(self::DESTINOS) as $clave) {
                $existentes[$clave] = $this->indicesDeDestino(self::DESTINOS[$clave]);
                $pendientes[$clave] = ['codigo' => [], 'nit' => [], 'nombre' => []];
                $nuevos[$clave] = [];
            }

            foreach ($registros as $registro) {
                $clave = $this->claveDestino($registro['clasificacion'] ?? null);
                $destino = self::DESTINOS[$clave];
                $datos = $this->datosAGuardar($registro, $destino);

                $idExistente = $this->buscarEnIndices($existentes[$clave], $datos, $destino);
                if ($idExistente !== null) {
                    // Las celdas vacías no borran lo que ya tenía el registro y
                    // el código existente nunca se reescribe desde el archivo.
                    $cambios = $this->soloConValor($datos);
                    unset($cambios[$destino['codigo']]);
                    if (!empty($cambios)) {
                        $destino['modelo']::whereKey($idExistente)->update($cambios);
                    }
                    $actualizados++;
                    continue;
                }

                // Fila repetida dentro del mismo archivo: se completa la que
                // está pendiente de insertar en vez de crear un duplicado.
                $indicePendiente = $this->buscarEnIndices($pendientes[$clave], $datos, $destino);
                if ($indicePendiente !== null) {
                    $nuevos[$clave][$indicePendiente] = array_merge(
                        $nuevos[$clave][$indicePendiente],
                        $this->soloConValor($datos)
                    );
                    $this->registrarEnIndices($pendientes[$clave], $nuevos[$clave][$indicePendiente], $destino, $indicePendiente);
                    $actualizados++;
                    continue;
                }

                $datos['estado'] ??= Proveedor::ESTADOS[0];
                $indice = count($nuevos[$clave]);
                $nuevos[$clave][] = $datos;
                $this->registrarEnIndices($pendientes[$clave], $datos, $destino, $indice);
                $creados++;
            }

            foreach (self::DESTINOS as $clave => $destino) {
                if (!empty($nuevos[$clave])) {
                    $this->insertarNuevos($destino, $nuevos[$clave]);
                    $escribeEmpresaExterna = $escribeEmpresaExterna || $clave === 'tallerdeprocesoexterno';
                }
            }
        });

        // Los desplegables de las OP y el listado de empresas externas se
        // sirven en cache: hay que invalidarlos tras escribir.
        Cache::forget('op_selects');
        if ($escribeEmpresaExterna) {
            Cache::increment('empresa_externa_index_version');
        }

        return [
            'creados' => $creados,
            'actualizados' => $actualizados,
        ];
    }

    /**
     * Inserta en bloque los registros nuevos de un destino, con el código
     * autogenerado cuando el archivo no lo trae.
     *
     * @param  array{modelo: class-string, codigo: string, prefijo: string}  $destino
     * @param  array<int, array<string, string|null>>  $nuevos
     */
    private function insertarNuevos(array $destino, array $nuevos): void
    {
        if (empty($nuevos)) {
            return;
        }

        $siguienteCodigo = $this->siguienteNumeroCodigo($destino['modelo'], $destino['codigo']);
        $ahora = now();

        foreach ($nuevos as &$datos) {
            if (empty($datos[$destino['codigo']])) {
                $datos[$destino['codigo']] = $destino['prefijo'] . '-' . str_pad(
                    (string) $siguienteCodigo++,
                    3,
                    '0',
                    STR_PAD_LEFT
                );
            }
            $datos['created_at'] = $ahora;
            $datos['updated_at'] = $ahora;
        }
        unset($datos);

        // El insert por bloques arma una sola sentencia: todas las filas deben
        // traer las mismas columnas y en el mismo orden. El archivo llena
        // campos distintos según la fila, así que las columnas que una fila no
        // trae se completan con null (los únicos NOT NULL sin default, nombre y
        // estado, siempre vienen del campo obligatorio nombre_comercial).
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
            $destino['modelo']::insert($bloque);
        }
    }

    /**
     * Datos a persistir: las columnas del archivo ya traducidas a la columna
     * de código del destino. La clasificación solo es un campo de proveedor.
     *
     * @param  array<string, string|null>  $registro
     * @param  array{modelo: class-string, codigo: string, prefijo: string}  $destino
     * @return array<string, string>
     */
    private function datosAGuardar(array $registro, array $destino): array
    {
        $datos = [];

        foreach ($this->columnasPermitidas() as $campo) {
            $valor = $registro[$campo] ?? null;
            if ($valor === null || $campo === self::COLUMNA_CODIGO || $campo === 'clasificacion') {
                continue;
            }
            $datos[$campo] = $valor;
        }

        $codigo = $registro[self::COLUMNA_CODIGO] ?? null;
        if ($codigo !== null) {
            $datos[$destino['codigo']] = $codigo;
        }

        // Las tres entidades siguen usando "nombre" para mostrarse y para
        // sus desplegables: es el espejo del nombre comercial.
        if (isset($datos['nombre_comercial'])) {
            $datos['nombre'] = $datos['nombre_comercial'];
        }

        if ($destino['modelo'] === Proveedor::class) {
            $datos['clasificacion'] = Proveedor::CLASIFICACIONES[0];
        }

        return $datos;
    }

    /**
     * @return array<string, string>
     */
    private function soloConValor(array $datos): array
    {
        return array_filter($datos, fn (?string $valor): bool => $valor !== null && $valor !== '');
    }

    /**
     * Destino de la fila según "proveedor o externa o macondas". El archivo
     * ya fue filtrado por validarDestinos(); este error es solo la última
     * barrera por si el job recibe un valor que no es de la lista.
     */
    private function claveDestino(?string $clasificacion): string
    {
        $clave = $this->claveComparacion((string) $clasificacion);

        if (!isset(self::DESTINOS[$clave])) {
            throw new RuntimeException(
                'Una fila indica "' . $clasificacion . '" en "proveedor o externa o macondas", '
                . 'que no es ' . implode(', ', Proveedor::CLASIFICACIONES) . '.'
            );
        }

        return $clave;
    }

    /**
     * Índices de emparejamiento de un destino: código, NIT y nombre comercial
     * ya normalizados, apuntando al id guardado.
     *
     * @param  array{modelo: class-string, codigo: string, prefijo: string}  $destino
     * @return array{codigo: array<string,int>, nit: array<string,int>, nombre: array<string,int>}
     */
    private function indicesDeDestino(array $destino): array
    {
        $indices = ['codigo' => [], 'nit' => [], 'nombre' => []];
        $modelo = $destino['modelo'];

        $columnas = ['id', 'nit', 'nombre_comercial', $destino['codigo']];
        foreach ($modelo::query()->toBase()->get($columnas) as $fila) {
            $this->registrarEnIndices($indices, (array) $fila, $destino, (int) $fila->id);
        }

        return $indices;
    }

    /**
     * Claves normalizadas con las que se reconoce un registro.
     *
     * @param  array<string, string|null>  $datos
     * @param  array{modelo: class-string, codigo: string, prefijo: string}  $destino
     * @return array{codigo: string, nit: string, nombre: string}
     */
    private function clavesDe(array $datos, array $destino): array
    {
        return [
            'codigo' => $this->claveComparacion((string) ($datos[$destino['codigo']] ?? '')),
            'nit' => $this->claveComparacion((string) ($datos['nit'] ?? '')),
            'nombre' => $this->claveComparacion((string) ($datos['nombre_comercial'] ?? '')),
        ];
    }

    /**
     * @param  array{codigo: array<string,int>, nit: array<string,int>, nombre: array<string,int>}  $indices
     * @param  array<string, string|null>  $datos
     * @param  array{modelo: class-string, codigo: string, prefijo: string}  $destino
     */
    private function buscarEnIndices(array $indices, array $datos, array $destino): ?int
    {
        foreach ($this->clavesDe($datos, $destino) as $campo => $llave) {
            if ($llave !== '' && isset($indices[$campo][$llave])) {
                return $indices[$campo][$llave];
            }
        }

        return null;
    }

    /**
     * @param  array{codigo: array<string,int>, nit: array<string,int>, nombre: array<string,int>}  $indices
     * @param  array<string, string|null>  $datos
     * @param  array{modelo: class-string, codigo: string, prefijo: string}  $destino
     */
    private function registrarEnIndices(array &$indices, array $datos, array $destino, int $indice): void
    {
        foreach ($this->clavesDe($datos, $destino) as $campo => $llave) {
            if ($llave !== '') {
                $indices[$campo][$llave] = $indice;
            }
        }
    }

    /**
     * Siguiente número de código de un destino. Se toma el mayor número de
     * todos los códigos existentes: ordenar por texto hacía que TAL-999
     * quedara antes que TAL-1000 y se repitieran códigos.
     *
     * @param  class-string  $modelo
     */
    private function siguienteNumeroCodigo(string $modelo, string $columna): int
    {
        $maximo = 0;

        foreach ($modelo::query()->whereNotNull($columna)->pluck($columna) as $codigo) {
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
            $titulo = $this->normalizarTitulo($tituloOriginal);
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
                . ' no está en el formato de talleres, empresas externas y proveedores.'
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
     * Resuelve el encabezado normalizado a la columna canónica. Acepta el
     * nombre exacto o el que lo tiene como prefijo
     * (ej. "Tiempo habitual de entrega" → tiempo_entrega).
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
     * Ajusta los valores de catálogo a la forma exacta del aplicativo (sin
     * importar mayúsculas ni tildes del archivo) y anota los que no sirven.
     *
     * @param  array<string, string|null>  $registro
     * @param  array<int, string>  $errores
     */
    private function canonizarCatalogos(array &$registro, int $numeroFila, array &$errores): void
    {
        foreach (self::CATALOGOS as $campo => $opciones) {
            $valor = $registro[$campo] ?? null;
            if ($valor === null) {
                continue;
            }

            $buscada = $this->claveComparacion($valor);
            foreach ($opciones as $opcion) {
                if ($this->claveComparacion($opcion) === $buscada) {
                    $registro[$campo] = $opcion;
                    continue 2;
                }
            }

            $errores[] = "Fila {$numeroFila}: {$campo} debe ser uno de: " . implode(', ', $opciones) . '.';
        }
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
            }
        }
    }

    /**
     * Clave de comparación: sin mayúsculas, tildes ni puntuación.
     */
    private function claveComparacion(?string $valor): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', $this->normalizarTitulo((string) $valor));
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

    /**
     * Filas en blanco sobrantes del archivo (formato, filas borradas a medias):
     * no son datos y no deben detener la importación.
     *
     * @param  array<int, mixed>  $fila
     */
    private function esFilaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if ($this->limpiarValor($valor) !== '') {
                return false;
            }
        }

        return true;
    }
}
