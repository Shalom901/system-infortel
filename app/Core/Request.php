<?php

declare(strict_types=1);

/**
 * =============================================================
 * CLASE REQUEST - MANEJO DE PETICIONES HTTP
 * Sistema de Facturación - Empresa de Informática Pucallpa
 * =============================================================
 *
 * Encapsula y sanitiza los datos de la petición HTTP actual:
 * GET, POST, archivos, cabeceras y validación de formularios.
 */

namespace App\Core;

class Request
{
    /**
     * Datos GET de la petición (sin sanitizar para consultas).
     */
    private array $queryData;

    /**
     * Datos POST de la petición (sin sanitizar para procesamiento manual).
     */
    private array $postData;

    /**
     * Archivos subidos ($_FILES).
     */
    private array $filesData;

    /**
     * Cabeceras HTTP de la petición.
     */
    private array $headers;

    /**
     * Errores de validación (campo → mensajes).
     *
     * @var array<string, string[]>
     */
    private array $errors = [];

    public function __construct()
    {
        $this->queryData = $_GET    ?? [];
        $this->postData  = $_POST   ?? [];
        $this->filesData = $_FILES  ?? [];
        $this->headers   = $this->parseHeaders();
    }

    // -----------------------------------------------
    // ACCESO A DATOS
    // -----------------------------------------------

    /**
     * Obtener un valor de la query string ($_GET).
     *
     * @param  string $key     Clave del parámetro
     * @param  mixed  $default Valor por defecto
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->queryData[$key] ?? $default;
    }

    /**
     * Obtener un valor del cuerpo POST ($_POST).
     *
     * @param  string $key     Clave del campo
     * @param  mixed  $default Valor por defecto
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->postData[$key] ?? $default;
    }

    /**
     * Obtener un valor de GET o POST (en ese orden de prioridad).
     * Es el método principal de acceso a datos de entrada.
     *
     * @param  string $key     Clave del campo
     * @param  mixed  $default Valor por defecto
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        // Soportar notación de punto para arrays anidados: 'usuario.nombre'
        if (str_contains($key, '.')) {
            return $this->getNestedValue(
                array_merge($this->postData, $this->queryData),
                $key,
                $default
            );
        }

        return $this->postData[$key] ?? $this->queryData[$key] ?? $default;
    }

    /**
     * Obtener un valor entero limpio.
     *
     * @param  string $key     Clave del campo
     * @param  int    $default Valor por defecto
     * @return int
     */
    public function inputInt(string $key, int $default = 0): int
    {
        return (int) filter_var(
            $this->input($key, $default),
            FILTER_VALIDATE_INT,
            ['options' => ['default' => $default]]
        );
    }

    /**
     * Obtener un valor float limpio.
     *
     * @param  string $key     Clave del campo
     * @param  float  $default Valor por defecto
     * @return float
     */
    public function inputFloat(string $key, float $default = 0.0): float
    {
        $valor = str_replace(',', '.', (string) $this->input($key, $default));
        return (float) filter_var($valor, FILTER_VALIDATE_FLOAT, ['options' => ['default' => $default]]);
    }

    /**
     * Obtener un valor booleano (checkbox, toggle).
     *
     * @param  string $key Clave del campo
     * @return bool
     */
    public function inputBool(string $key): bool
    {
        $valor = $this->input($key, false);
        if (is_string($valor)) {
            return in_array(strtolower($valor), ['1', 'true', 'on', 'yes', 'si', 'sí'], true);
        }
        return (bool) $valor;
    }

    /**
     * Obtener todos los datos de entrada (POST + GET combinados, POST tiene prioridad).
     *
     * @param  string[] $only   Si se especifica, retornar solo estas claves
     * @param  string[] $except Claves a excluir del resultado
     * @return array
     */
    public function all(array $only = [], array $except = []): array
    {
        $datos = array_merge($this->queryData, $this->postData);

        if (!empty($only)) {
            $datos = array_intersect_key($datos, array_flip($only));
        }

        if (!empty($except)) {
            $datos = array_diff_key($datos, array_flip($except));
        }

        // Excluir siempre el token CSRF de los datos de entrada
        unset($datos['_csrf_token']);

        return $datos;
    }

    /**
     * Verificar si existe una clave en los datos de entrada.
     *
     * @param  string $key Clave a verificar
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->postData[$key]) || isset($this->queryData[$key]);
    }

    /**
     * Verificar si una clave existe y no está vacía.
     *
     * @param  string $key Clave a verificar
     * @return bool
     */
    public function filled(string $key): bool
    {
        $valor = $this->input($key);
        return $valor !== null && $valor !== '';
    }

    // -----------------------------------------------
    // ACCESO A ARCHIVOS
    // -----------------------------------------------

    /**
     * Obtener información de un archivo subido.
     *
     * @param  string      $key Nombre del campo de archivo
     * @return array|null       Información del archivo o null si no existe
     */
    public function file(string $key): ?array
    {
        if (!isset($this->filesData[$key])) {
            return null;
        }

        $archivo = $this->filesData[$key];

        // Verificar que no hubo error en la subida
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        return $archivo;
    }

    /**
     * Verificar si se subió un archivo para el campo especificado.
     *
     * @param  string $key Nombre del campo
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        return isset($this->filesData[$key])
            && $this->filesData[$key]['error'] === UPLOAD_ERR_OK
            && $this->filesData[$key]['size']  > 0;
    }

    // -----------------------------------------------
    // INFORMACIÓN DE LA PETICIÓN
    // -----------------------------------------------

    /**
     * Obtener el método HTTP de la petición.
     *
     * @return string GET|POST|PUT|DELETE|PATCH|etc.
     */
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Verificar si la petición es de tipo POST.
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * Verificar si la petición es de tipo GET.
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    /**
     * Verificar si la petición es AJAX (XMLHttpRequest).
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Verificar si la petición acepta respuesta JSON.
     *
     * @return bool
     */
    public function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json') || $this->isAjax();
    }

    /**
     * Obtener la dirección IP del cliente.
     *
     * @return string
     */
    public function ip(): string
    {
        return function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * Obtener la URI completa de la petición (con query string).
     *
     * @return string
     */
    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Obtener solo la ruta (sin query string).
     *
     * @return string
     */
    public function path(): string
    {
        $uri = $this->uri();
        return str_contains($uri, '?') ? strstr($uri, '?', true) : $uri;
    }

    /**
     * Obtener el valor de una cabecera HTTP.
     *
     * @param  string      $key     Nombre de la cabecera (case-insensitive)
     * @param  string|null $default Valor por defecto
     * @return string|null
     */
    public function header(string $key, ?string $default = null): ?string
    {
        $key = strtolower(str_replace('_', '-', $key));
        return $this->headers[$key] ?? $default;
    }

    // -----------------------------------------------
    // VALIDACIÓN
    // -----------------------------------------------

    /**
     * Validar los datos de entrada según reglas definidas.
     *
     * Reglas soportadas:
     *   required         - El campo no puede estar vacío
     *   string           - Debe ser una cadena de texto
     *   int|integer      - Debe ser un número entero
     *   float|numeric    - Debe ser un número decimal
     *   email            - Debe ser un email válido
     *   min:N            - Longitud mínima (strings) o valor mínimo (números)
     *   max:N            - Longitud máxima (strings) o valor máximo (números)
     *   ruc              - RUC peruano válido (11 dígitos)
     *   dni              - DNI peruano válido (8 dígitos)
     *   date             - Fecha válida formato Y-m-d
     *   in:a,b,c         - El valor debe estar en la lista
     *   regex:/patrón/   - Debe coincidir con la expresión regular
     *
     * @param  array<string, string> $rules Reglas de validación: ['campo' => 'regla1|regla2']
     * @return bool                          true si todos los campos son válidos
     */
    public function validate(array $rules): bool
    {
        $this->errors = [];
        $datos = $this->all();

        foreach ($rules as $campo => $reglasString) {
            $reglasLista = explode('|', $reglasString);
            $valor       = $datos[$campo] ?? null;
            $nombreCampo = ucfirst(str_replace('_', ' ', $campo));

            foreach ($reglasLista as $regla) {
                // Separar el nombre de la regla de sus parámetros
                [$nombreRegla, $parametro] = $this->parseRule($regla);

                $error = $this->applyRule($nombreRegla, $parametro, $valor, $nombreCampo, $campo);
                if ($error !== null) {
                    $this->errors[$campo][] = $error;
                    // Si el campo falló 'required', no seguir validando ese campo
                    if ($nombreRegla === 'required') {
                        break;
                    }
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Obtener todos los errores de validación.
     *
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Obtener el primer error de un campo específico.
     *
     * @param  string      $campo Nombre del campo
     * @return string|null        Primer mensaje de error o null
     */
    public function getError(string $campo): ?string
    {
        return $this->errors[$campo][0] ?? null;
    }

    /**
     * Verificar si hay errores de validación.
     *
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    // -----------------------------------------------
    // MÉTODOS PRIVADOS
    // -----------------------------------------------

    /**
     * Parsear una cabecera HTTP del servidor.
     *
     * @return array<string, string>
     */
    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $nombre = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$nombre] = $value;
            }
        }
        return $headers;
    }

    /**
     * Obtener un valor anidado con notación de punto.
     *
     * @param  array  $datos   Datos donde buscar
     * @param  string $key     Clave con puntos (ej: 'usuario.nombre')
     * @param  mixed  $default Valor por defecto
     * @return mixed
     */
    private function getNestedValue(array $datos, string $key, mixed $default): mixed
    {
        $partes = explode('.', $key);
        $actual = $datos;

        foreach ($partes as $parte) {
            if (!is_array($actual) || !array_key_exists($parte, $actual)) {
                return $default;
            }
            $actual = $actual[$parte];
        }

        return $actual;
    }

    /**
     * Parsear el nombre y parámetro de una regla de validación.
     *
     * @param  string $regla  Regla en formato 'nombre:parametro'
     * @return array{0: string, 1: string|null}
     */
    private function parseRule(string $regla): array
    {
        if (str_contains($regla, ':')) {
            [$nombre, $param] = explode(':', $regla, 2);
            return [trim($nombre), trim($param)];
        }
        return [trim($regla), null];
    }

    /**
     * Aplicar una regla de validación a un valor.
     *
     * @param  string      $regla     Nombre de la regla
     * @param  string|null $param     Parámetro de la regla (ej: '8' para min:8)
     * @param  mixed       $valor     Valor a validar
     * @param  string      $nombre    Nombre legible del campo para el mensaje de error
     * @param  string      $campo     Nombre técnico del campo
     * @return string|null            Mensaje de error o null si es válido
     */
    private function applyRule(string $regla, ?string $param, mixed $valor, string $nombre, string $campo): ?string
    {
        return match ($regla) {
            'required' => (empty($valor) && $valor !== '0' && $valor !== 0)
                ? "{$nombre} es obligatorio."
                : null,

            'string' => (isset($valor) && !is_string($valor))
                ? "{$nombre} debe ser texto."
                : null,

            'int', 'integer' => (isset($valor) && !filter_var($valor, FILTER_VALIDATE_INT))
                ? "{$nombre} debe ser un número entero."
                : null,

            'float', 'numeric' => (isset($valor) && !is_numeric($valor))
                ? "{$nombre} debe ser un número."
                : null,

            'email' => (isset($valor) && !filter_var($valor, FILTER_VALIDATE_EMAIL))
                ? "{$nombre} debe ser un email válido."
                : null,

            'min' => $this->validateMin($valor, (int) $param, $nombre),

            'max' => $this->validateMax($valor, (int) $param, $nombre),

            'ruc' => (isset($valor) && !function_exists('validarRuc') || !validarRuc((string) $valor))
                ? "{$nombre} no es un RUC válido."
                : null,

            'dni' => (isset($valor) && !function_exists('validarDni') || !validarDni((string) $valor))
                ? "{$nombre} no es un DNI válido (debe tener 8 dígitos)."
                : null,

            'date' => (isset($valor) && !$this->isValidDate((string) $valor))
                ? "{$nombre} debe ser una fecha válida (formato DD/MM/YYYY o YYYY-MM-DD)."
                : null,

            'in' => (isset($valor) && $param !== null && !in_array($valor, explode(',', $param), true))
                ? "{$nombre} debe ser uno de los valores: {$param}."
                : null,

            'regex' => (isset($valor) && $param !== null && !preg_match($param, (string) $valor))
                ? "{$nombre} tiene un formato inválido."
                : null,

            'confirmed' => $this->validateConfirmed($valor, $campo),

            default => null,
        };
    }

    /** Validar mínimo para strings (longitud) y números (valor) */
    private function validateMin(mixed $valor, int $min, string $nombre): ?string
    {
        if (!isset($valor)) return null;
        if (is_string($valor) && mb_strlen($valor, 'UTF-8') < $min) {
            return "{$nombre} debe tener al menos {$min} caracteres.";
        }
        if (is_numeric($valor) && (float) $valor < $min) {
            return "{$nombre} debe ser mayor o igual a {$min}.";
        }
        return null;
    }

    /** Validar máximo para strings (longitud) y números (valor) */
    private function validateMax(mixed $valor, int $max, string $nombre): ?string
    {
        if (!isset($valor)) return null;
        if (is_string($valor) && mb_strlen($valor, 'UTF-8') > $max) {
            return "{$nombre} no puede tener más de {$max} caracteres.";
        }
        if (is_numeric($valor) && (float) $valor > $max) {
            return "{$nombre} debe ser menor o igual a {$max}.";
        }
        return null;
    }

    /** Validar confirmación de campo (ej: password_confirmation) */
    private function validateConfirmed(mixed $valor, string $campo): ?string
    {
        $confirmacion = $this->input("{$campo}_confirmation");
        if ($valor !== $confirmacion) {
            return "La confirmación no coincide.";
        }
        return null;
    }

    /** Validar que una cadena sea una fecha válida */
    private function isValidDate(string $fecha): bool
    {
        // Intentar varios formatos
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            $dt = \DateTime::createFromFormat($formato, $fecha);
            if ($dt && $dt->format($formato) === $fecha) {
                return true;
            }
        }
        return false;
    }
}
