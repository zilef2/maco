<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Services\Concerns\LeeExcelRapido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use RuntimeException;

/**
 * Validación, escaneo de seguridad e importación de usuarios desde Excel.
 *
 * La fila 1 contiene los títulos de las columnas y las siguientes filas los
 * datos. Nombre, email, identificación y rol son obligatorios; los demás
 * campos del formulario de usuarios son opcionales.
 *
 * Un usuario se actualiza cuando ya existe con el mismo email y, si no
 * coincide, con la misma identificación. Las celdas vacías no borran el dato
 * que ya tiene el registro. La contraseña de los usuarios nuevos se crea con
 * la misma regla del formulario: identificación + "*".
 */
class ExcelUsuariosService
{
    use LeeExcelRapido;

    public const MAX_BYTES = ExcelSecurityScanner::MAX_BYTES;

    /** Columnas obligatorias del archivo. */
    public const COLUMNAS_REQUERIDAS = ['nombre', 'email', 'identificacion', 'rol'];

    /** Columnas opcionales del archivo. */
    public const COLUMNAS_OPCIONALES = [

        'cargo',
        'celular',
        'sexo',
        'fecha_nacimiento',
        'salario',
        'fecha_de_ingreso',
    ];

    /**
     * Encabezados aceptados (ya normalizados) y la columna a la que apuntan.
     * Permite tanto el nombre legible como el nombre interno del campo.
     */
    private const ALIAS_COLUMNAS = [
        'nombre'             => 'nombre',
        'nombres'            => 'nombre',
        'name'               => 'nombre',
        'email'              => 'email',
        'correo'             => 'email',
        'correoelectronico'  => 'email',
        'identificacion'     => 'identificacion',
        'cedula'             => 'identificacion',
        'documento'          => 'identificacion',
        'rol'                => 'rol',
        'role'               => 'rol',
        'cargo'              => 'cargo',
        'celular'            => 'celular',
        'telefono'           => 'celular',
        'sexo'               => 'sexo',
        'genero'             => 'sexo',
        'fechanacimiento'    => 'fecha_nacimiento',
        'fechadenacimiento'  => 'fecha_nacimiento',
        'salario'            => 'salario',
        'fechadeingreso'     => 'fecha_de_ingreso',
        'fechaingreso'       => 'fecha_de_ingreso',
    ];

    /**
     * Reglas por columna, en línea con UserStoreRequest.
     *
     * @var array<string, array{max?: int, email?: bool}>
     */
    private const REGLAS = [
        'nombre'           => ['max' => 255],
        'email'            => ['email' => true, 'max' => 255],
        'identificacion'   => ['max' => 255],

        'cargo'            => ['max' => 255],
        'celular'          => ['max' => 50],
        'salario'          => ['max' => 50],
    ];

    public function __construct(private ExcelSecurityScanner $scanner)
    {
    }

    /**
     * Columnas permitidas del formato de usuarios.
     *
     * @return string[]
     */
    public function columnasPermitidas(): array
    {
        return array_merge(self::COLUMNAS_REQUERIDAS, self::COLUMNAS_OPCIONALES);
    }

    /**
     * @return string[]
     */
    public function escanearSeguridad(string $ruta): array
    {
        return $this->scanner->escanear($ruta);
    }

    /**
     * Valida únicamente la fila de títulos en la petición de subida.
     *
     * @throws RuntimeException cuando el archivo no cumple el formato
     */
    public function validarEncabezados(string $ruta): void
    {
        $this->columnasDesdeTitulos($this->leerFilaTitulos($ruta));
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
        $filas = $this->leerTodasLasFilas($ruta);
        if (empty($filas)) {
            throw new RuntimeException('El archivo está vacío.');
        }

        $columnas = $this->columnasDesdeTitulos($filas[0] ?? []);
        $roles = $this->rolesDisponibles();
        $registros = [];
        $errores = [];
        $emailsVistos = [];
        $identificacionesVistas = [];

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

            $this->normalizarRegistro($registro, $numeroFila, $roles, $errores);
            $this->validarRegistro($registro, $numeroFila, $errores);

            $emailClave = mb_strtolower((string) ($registro['email'] ?? ''));
            $identificacionClave = $this->claveComparacion($registro['identificacion'] ?? null);

            if ($emailClave !== '' && isset($emailsVistos[$emailClave])) {
                $errores[] = "Fila {$numeroFila}: el email {$registro['email']} está repetido en el archivo.";
            }
            if ($identificacionClave !== '' && isset($identificacionesVistas[$identificacionClave])) {
                $errores[] = "Fila {$numeroFila}: la identificación {$registro['identificacion']} está repetida en el archivo.";
            }

            if ($emailClave !== '') {
                $emailsVistos[$emailClave] = true;
            }
            if ($identificacionClave !== '') {
                $identificacionesVistas[$identificacionClave] = true;
            }

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
     * Inserta los usuarios nuevos y actualiza los existentes.
     *
     * El emparejamiento se hace por email y, si no coincide, por
     * identificación. La comparación de identificación ignora mayúsculas,
     * tildes y puntuación.
     *
     * @param  array<int, array<string, string|null>>  $registros
     * @return array{creados: int, actualizados: int}
     */
    public function importar(array $registros): array
    {
        $creados = 0;
        $actualizados = 0;

        DB::transaction(function () use ($registros, &$creados, &$actualizados) {
            $porEmail = [];
            $porIdentificacion = [];

            foreach (User::query()->withTrashed()->get(['id', 'email', 'identificacion', 'deleted_at']) as $usuario) {
                if ($usuario->email) {
                    $porEmail[mb_strtolower($usuario->email)] = $usuario->id;
                }
                $claveIdentificacion = $this->claveComparacion($usuario->identificacion);
                if ($claveIdentificacion !== '') {
                    $porIdentificacion[$claveIdentificacion] = $usuario->id;
                }
            }

            foreach ($registros as $registro) {
                $email = mb_strtolower((string) ($registro['email'] ?? ''));
                $identificacion = $this->claveComparacion($registro['identificacion'] ?? null);

                $idExistente = null;
                $idPorEmail = $email !== '' ? ($porEmail[$email] ?? null) : null;
                $idPorIdentificacion = $identificacion !== '' ? ($porIdentificacion[$identificacion] ?? null) : null;

                if ($idPorEmail !== null && $idPorIdentificacion !== null && $idPorEmail !== $idPorIdentificacion) {
                    throw new RuntimeException(
                        "El email {$registro['email']} y la identificación {$registro['identificacion']} "
                        . 'pertenecen a usuarios distintos.'
                    );
                }

                $idExistente = $idPorEmail ?? $idPorIdentificacion;

                $datos = $this->datosAGuardar($registro);
                $usuario = $idExistente !== null
                    ? User::withTrashed()->find($idExistente)
                    : null;

                if ($usuario !== null) {
                    // Las celdas vacías no borran lo que ya tenía el registro.
                    $datos = array_filter($datos, fn ($valor): bool => $valor !== null && $valor !== '');

                    if ($usuario->trashed()) {
                        $usuario->restore();
                    }

                    $usuario->update($datos);
                    $usuario->syncRoles([$registro['rol']]);
                    $actualizados++;
                } else {
                    $datos['password'] = Hash::make((string) $registro['identificacion'] . '*');

                    $usuario = User::create($datos);
                    $usuario->assignRole($registro['rol']);
                    $creados++;
                }

                if ($email !== '') {
                    $porEmail[$email] = $usuario->id;
                }
                if ($identificacion !== '') {
                    $porIdentificacion[$identificacion] = $usuario->id;
                }
            }
        });

        return [
            'creados' => $creados,
            'actualizados' => $actualizados,
        ];
    }

    /**
     * Normaliza los valores especiales (rol, sexo y fechas) antes de validar.
     *
     * @param  array<string, string|null>  $registro
     * @param  array<string, string>  $roles
     * @param  array<int, string>  $errores
     */
    private function normalizarRegistro(array &$registro, int $numeroFila, array $roles, array &$errores): void
    {
        $rol = $this->normalizarTitulo((string) ($registro['rol'] ?? ''));
        if ($rol !== '') {
            if (!isset($roles[$rol])) {
                $errores[] = "Fila {$numeroFila}: el rol \"{$registro['rol']}\" no existe. Roles disponibles: "
                    . implode(', ', array_values($roles)) . '.';
            } else {
                $registro['rol'] = $roles[$rol];
            }
        }

        $sexo = $this->normalizarTitulo((string) ($registro['sexo'] ?? ''));
        if ($sexo !== '') {
            $registro['sexo'] = match ($sexo) {
                'm', 'masculino', 'hombre' => 'Masculino',
                'f', 'femenino', 'mujer' => 'Femenino',
                'otro', 'otros', 'otra' => 'Otro',
                default => null,
            };

            if ($registro['sexo'] === null) {
                $errores[] = "Fila {$numeroFila}: el sexo debe ser Masculino, Femenino u Otro.";
            }
        }

        foreach (['fecha_nacimiento', 'fecha_de_ingreso'] as $campo) {
            $fecha = $this->parseFecha($registro[$campo] ?? null);
            if ($fecha === false) {
                $errores[] = "Fila {$numeroFila}: {$campo} no es una fecha válida.";
                continue;
            }

            $registro[$campo] = $fecha;
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
     * Datos a persistir, con los nombres de columna del modelo User.
     *
     * @param  array<string, string|null>  $registro
     * @return array<string, string|null>
     */
    private function datosAGuardar(array $registro): array
    {
        return [
            'name' => $registro['nombre'] ?? null,
            'email' => $registro['email'] ?? null,
            'identificacion' => $registro['identificacion'] ?? null,
            'cargo' => $registro['cargo'] ?? null,
            'celular' => $registro['celular'] ?? null,
            'sexo' => $registro['sexo'] ?? null,
            'fecha_nacimiento' => $registro['fecha_nacimiento'] ?? null,
            'salario' => $registro['salario'] ?? null,
            'fecha_de_ingreso' => $registro['fecha_de_ingreso'] ?? null,
        ];
    }

    /**
     * Roles existentes indexados por su nombre normalizado.
     *
     * @return array<string, string>
     */
    private function rolesDisponibles(): array
    {
        $roles = [];
        foreach (Role::query()->orderBy('name')->pluck('name') as $nombre) {
            $roles[$this->normalizarTitulo((string) $nombre)] = (string) $nombre;
        }

        return $roles;
    }

    /**
     * Convierte una celda de fecha a Y-m-d. Acepta fechas de Excel (serial),
     * Y-m-d, d/m/Y y d-m-Y. Devuelve null si la celda está vacía y false si
     * el valor no es una fecha válida.
     */
    private function parseFecha(mixed $valor): string|false|null
    {
        $texto = $this->limpiarValor($valor);
        if ($texto === '') {
            return null;
        }

        if (is_numeric($texto)) {
            try {
                return Date::excelToDateTimeObject((float) $texto)->format('Y-m-d');
            } catch (\Throwable) {
                return false;
            }
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $formato) {
            $fecha = \DateTimeImmutable::createFromFormat('!' . $formato, $texto);
            if ($fecha instanceof \DateTimeImmutable && $fecha->format($formato) === $texto) {
                return $fecha->format('Y-m-d');
            }
        }

        $timestamp = strtotime($texto);

        return $timestamp === false ? false : date('Y-m-d', $timestamp);
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
                . ' no está en el formato de usuarios.'
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
     * (ej. "Correo electrónico" → email).
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
}
