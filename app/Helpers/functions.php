<?php

declare(strict_types=1);

/**
 * =============================================================
 * FUNCIONES AUXILIARES GLOBALES (HELPERS)
 * Sistema de Facturación - Empresa de Informática Pucallpa
 * =============================================================
 *
 * Funciones de uso general disponibles en toda la aplicación.
 * Cargadas automáticamente por el autoloader de Composer.
 */

use Config\Database;

// -----------------------------------------------
// FUNCIONES DE DEBUGGING
// -----------------------------------------------

/**
 * Dump y Die - Imprime la variable formateada y termina la ejecución.
 * Solo disponible en modo desarrollo.
 *
 * @param  mixed ...$vars Variables a inspeccionar
 */
function dd(mixed ...$vars): never
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<style>
        .dd-dump { background:#1a1a2e; color:#e94560; padding:20px; font-family:monospace;
                   font-size:14px; border-radius:8px; margin:10px; white-space:pre-wrap; }
        .dd-title { color:#0f3460; background:#e94560; padding:4px 8px; border-radius:4px;
                    font-weight:bold; margin-bottom:8px; display:inline-block; }
    </style>';

    foreach ($vars as $index => $var) {
        echo '<div class="dd-dump">';
        echo '<span class="dd-title">dd() #' . ($index + 1) . ' — ' . gettype($var) . '</span><br>';
        echo htmlspecialchars(print_r($var, true), ENT_QUOTES, 'UTF-8');
        echo '</div>';
    }
    exit(1);
}

// -----------------------------------------------
// FUNCIONES DE NAVEGACIÓN Y RUTAS
// -----------------------------------------------

/**
 * Redirigir a una URL.
 *
 * @param string $url URL de destino (puede ser relativa al APP_URL)
 */
function redirect(string $url): void
{
    // Si no comienza con http, construir URL completa
    if (!str_starts_with($url, 'http')) {
        $url = baseUrl(ltrim($url, '/'));
    }
    header("Location: {$url}", true, 302);
    exit;
}

/**
 * Obtener la URL base de la aplicación.
 *
 * @param  string $path Ruta adicional (sin barra inicial)
 * @return string       URL completa
 */
function baseUrl(string $path = ''): string
{
    $host = $_SERVER['HTTP_HOST'] ?? null;
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $envUrl = $_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? getenv('APP_URL');
    
    if ($host) {
        $base = "{$scheme}://{$host}";
        // Include subdirectory if running in one (e.g. XAMPP htdocs/app/public)
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptDir && $scriptDir !== '/' && $scriptDir !== '\\') {
            $base .= $scriptDir;
        }
    } else {
        $base = defined('APP_URL') ? APP_URL : (rtrim($envUrl ?: 'http://localhost:8000', '/'));
    }
    
    return $path ? rtrim($base, '/') . '/' . ltrim($path, '/') : $base;
}

/**
 * Obtener la URL de un asset del directorio public/.
 *
 * @param  string $path Ruta del asset relativa a /public (ej: 'css/app.css')
 * @return string       URL completa del asset con cache busting
 */
function asset(string $path): string
{
    $path     = ltrim($path, '/');
    $fullPath = (defined('PUBLIC_PATH') ? PUBLIC_PATH : __DIR__ . '/../../public') . '/' . $path;
    $version  = file_exists($fullPath) ? '?v=' . substr(md5((string) filemtime($fullPath)), 0, 8) : '';

    return baseUrl($path) . $version;
}

// -----------------------------------------------
// FUNCIONES DE VISTAS
// -----------------------------------------------

/**
 * Renderizar una vista PHP y retornar el HTML.
 *
 * @param  string $template Nombre de la vista (relativa a Views/, con punto como separador)
 * @param  array  $data     Variables disponibles en la vista
 * @return string           HTML renderizado
 */
function view(string $template, array $data = []): string
{
    $viewsPath = defined('VIEWS_PATH') ? VIEWS_PATH : __DIR__ . '/../../app/Views';
    $filePath  = $viewsPath . '/' . str_replace('.', '/', $template) . '.php';

    if (!file_exists($filePath)) {
        throw new RuntimeException("Vista no encontrada: {$filePath}");
    }

    // Extraer variables para la vista
    extract($data, EXTR_SKIP);

    ob_start();
    include $filePath;
    return ob_get_clean() ?: '';
}

// -----------------------------------------------
// FUNCIONES DE SEGURIDAD - CSRF
// -----------------------------------------------

/**
 * Obtener o generar el token CSRF de la sesión actual.
 *
 * @return string Token CSRF de 64 caracteres hexadecimales
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

/**
 * Generar el campo HTML oculto con el token CSRF.
 *
 * @return string HTML del campo hidden con el token CSRF
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"_csrf_token\" value=\"{$token}\">";
}

// -----------------------------------------------
// FUNCIONES DE SESIÓN Y MENSAJES FLASH
// -----------------------------------------------

/**
 * Obtener o establecer un valor de sesión.
 *
 * @param  string $key   Clave de la sesión
 * @param  mixed  $value Valor a guardar (null para solo leer)
 * @return mixed         Valor actual de la sesión
 */
function session(string $key, mixed $value = null): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if ($value !== null) {
        $_SESSION[$key] = $value;
    }

    return $_SESSION[$key] ?? null;
}

/**
 * Agregar un mensaje flash que se muestra una sola vez.
 *
 * @param string $type    Tipo: 'success', 'error', 'warning', 'info'
 * @param string $message Mensaje a mostrar
 */
function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['_flash_messages'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Obtener y limpiar todos los mensajes flash de la sesión.
 *
 * @return array Lista de mensajes flash pendientes
 */
function getFlashMessages(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $messages = $_SESSION['_flash_messages'] ?? [];
    unset($_SESSION['_flash_messages']);
    return $messages;
}

/**
 * Recuperar el valor anterior de un campo de formulario (para repoblar).
 *
 * @param  string $key     Nombre del campo
 * @param  mixed  $default Valor por defecto si no existe
 * @return mixed           Valor anterior sanitizado
 */
function old(string $key, mixed $default = ''): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $old = $_SESSION['_old_input'][$key] ?? $default;

    // Si es string, escapar para XSS
    if (is_string($old)) {
        return htmlspecialchars($old, ENT_QUOTES, 'UTF-8');
    }

    return $old;
}

/**
 * Guardar los datos del formulario actual en sesión (para repoblar en error).
 *
 * @param array $data Datos del formulario (normalmente $_POST)
 */
function withOldInput(array $data): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Remover campos sensibles antes de guardar
    $excluir = ['password', 'password_confirmation', 'clave', '_csrf_token'];
    foreach ($excluir as $campo) {
        unset($data[$campo]);
    }

    $_SESSION['_old_input'] = $data;
}

// -----------------------------------------------
// FUNCIONES DE FORMATEO
// -----------------------------------------------

/**
 * Formatear un monto monetario con símbolo de moneda.
 *
 * @param  float  $amount   Monto a formatear
 * @param  string $currency Código de moneda: 'PEN' o 'USD'
 * @return string           Monto formateado (ej: "S/ 1,250.00")
 */
function formatMoney(float $amount, string $currency = 'PEN'): string
{
    $monedas = defined('MONEDAS') ? MONEDAS : [
        'PEN' => ['simbolo' => 'S/', 'decimales' => 2],
        'USD' => ['simbolo' => '$',  'decimales' => 2],
    ];

    $config  = $monedas[$currency] ?? $monedas['PEN'];
    $formateado = number_format($amount, $config['decimales'], '.', ',');

    return $config['simbolo'] . ' ' . $formateado;
}

/**
 * Formatear una fecha en el formato indicado.
 *
 * @param  string|null $date   Fecha en formato Y-m-d o Y-m-d H:i:s
 * @param  string      $format Formato de salida (php date format)
 * @return string              Fecha formateada o cadena vacía si es nula
 */
function formatDate(?string $date, string $format = 'd/m/Y'): string
{
    if (empty($date)) {
        return '';
    }

    try {
        $dt = new \DateTime($date, new \DateTimeZone('America/Lima'));
        return $dt->format($format);
    } catch (\Exception) {
        return $date;
    }
}

/**
 * Formatear fecha y hora.
 *
 * @param  string|null $datetime Fecha y hora en formato Y-m-d H:i:s
 * @return string                Fecha y hora formateada
 */
function formatDateTime(?string $datetime): string
{
    return formatDate($datetime, 'd/m/Y H:i');
}

/**
 * Convertir número a letras en español (para montos en comprobantes).
 *
 * @param  float  $numero  Número a convertir
 * @param  string $moneda  Nombre de la moneda (ej: 'SOLES', 'DÓLARES')
 * @return string          Número en letras
 */
function numeroALetras(float $numero, string $moneda = 'SOLES'): string
{
    $entero     = (int) floor($numero);
    $centavos   = (int) round(($numero - $entero) * 100);
    $letras     = convertirEnteroALetras($entero);

    return strtoupper(trim($letras)) . " CON {$centavos}/100 {$moneda}";
}

/**
 * Función interna: convertir un entero a letras en español.
 *
 * @param  int    $numero Número entero a convertir
 * @return string         Representación en letras
 */
function convertirEnteroALetras(int $numero): string
{
    if ($numero === 0) return 'CERO';
    if ($numero < 0)  return 'MENOS ' . convertirEnteroALetras(abs($numero));

    $unidades  = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
                  'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS',
                  'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
    $decenas   = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA',
                  'OCHENTA', 'NOVENTA'];
    $centenas  = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
                  'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    if ($numero < 20) return $unidades[$numero];
    if ($numero === 100) return 'CIEN';

    if ($numero < 100) {
        $d = intdiv($numero, 10);
        $u = $numero % 10;
        if ($numero >= 21 && $numero <= 29) {
            return 'VEINTI' . strtolower($unidades[$u]);
        }
        return $decenas[$d] . ($u > 0 ? ' Y ' . $unidades[$u] : '');
    }

    if ($numero < 1000) {
        $c    = intdiv($numero, 100);
        $rest = $numero % 100;
        return $centenas[$c] . ($rest > 0 ? ' ' . convertirEnteroALetras($rest) : '');
    }

    if ($numero < 2000) {
        $rest = $numero % 1000;
        return 'MIL' . ($rest > 0 ? ' ' . convertirEnteroALetras($rest) : '');
    }

    if ($numero < 1_000_000) {
        $miles = intdiv($numero, 1000);
        $rest  = $numero % 1000;
        return convertirEnteroALetras($miles) . ' MIL' . ($rest > 0 ? ' ' . convertirEnteroALetras($rest) : '');
    }

    if ($numero < 2_000_000) {
        $rest = $numero % 1_000_000;
        return 'UN MILLÓN' . ($rest > 0 ? ' ' . convertirEnteroALetras($rest) : '');
    }

    $millones = intdiv($numero, 1_000_000);
    $rest     = $numero % 1_000_000;
    return convertirEnteroALetras($millones) . ' MILLONES' . ($rest > 0 ? ' ' . convertirEnteroALetras($rest) : '');
}

// -----------------------------------------------
// FUNCIONES DE SEGURIDAD Y SANITIZACIÓN
// -----------------------------------------------

/**
 * Sanitizar un valor de entrada para prevenir XSS.
 *
 * @param  mixed $input Valor a sanitizar
 * @return mixed        Valor sanitizado
 */
function sanitize(mixed $input): mixed
{
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }

    if (is_string($input)) {
        // Eliminar caracteres de control y espacios en blanco extremos
        $input = trim($input);
        // Convertir caracteres especiales HTML a entidades
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $input;
}

/**
 * Limpiar un string sin convertir a entidades HTML (para datos que van a BD).
 *
 * @param  string $input Cadena a limpiar
 * @return string        Cadena sin espacios extremos y sin null bytes
 */
function cleanInput(string $input): string
{
    // Eliminar null bytes y caracteres de control peligrosos
    $input = str_replace(["\0", "\x00"], '', $input);
    return trim($input);
}

// -----------------------------------------------
// FUNCIONES DE GENERACIÓN DE CÓDIGOS
// -----------------------------------------------

/**
 * Generar el número de serie de un comprobante.
 * Formato: {PREFIJO}-{SECUENCIA CON CEROS} Ej: F001-00000123
 *
 * @param  string $prefijo   Prefijo del comprobante (ej: 'F001', 'B001')
 * @param  int    $secuencia Número secuencial actual
 * @param  int    $digitos   Cantidad de dígitos para la secuencia (por defecto 8)
 * @return string            Número de comprobante formateado
 */
function generateCode(string $prefijo, int $secuencia, int $digitos = 8): string
{
    return strtoupper($prefijo) . '-' . str_pad((string) $secuencia, $digitos, '0', STR_PAD_LEFT);
}

/**
 * Generar un UUID v4 único.
 *
 * @return string UUID en formato estándar (xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx)
 */
function generateUuid(): string
{
    $data    = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // Versión 4
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // Variante RFC 4122

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Generar un código de producto único.
 *
 * @param  string $prefix  Prefijo (ej: 'PROD', 'SRV')
 * @return string          Código único
 */
function generateProductCode(string $prefix = 'PROD'): string
{
    return strtoupper($prefix) . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

// -----------------------------------------------
// FUNCIONES DE AUTENTICACIÓN Y AUTORIZACIÓN
// -----------------------------------------------

/**
 * Obtener el usuario actualmente autenticado.
 *
 * @return array|null Datos del usuario o null si no está autenticado
 */
function currentUser(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return $_SESSION['user'] ?? null;
}

/**
 * Verificar si el usuario actual tiene rol de Administrador.
 *
 * @return bool
 */
function isAdmin(): bool
{
    $user = currentUser();
    return $user !== null && (int) $user['rol_id'] === (defined('ROLE_ADMIN') ? ROLE_ADMIN : 1);
}

/**
 * Verificar si el usuario actual tiene rol de Vendedor.
 *
 * @return bool
 */
function isVendedor(): bool
{
    $user = currentUser();
    return $user !== null && (int) $user['rol_id'] === (defined('ROLE_VENDEDOR') ? ROLE_VENDEDOR : 2);
}

/**
 * Verificar si el usuario actual tiene un permiso específico.
 *
 * @param  string $permission Nombre del permiso a verificar
 * @return bool
 */
function hasPermission(string $permission): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Los administradores tienen todos los permisos
    if (isAdmin()) {
        return true;
    }

    $permisos = $_SESSION['user']['permisos'] ?? [];
    return in_array($permission, $permisos, true);
}

/**
 * Verificar si hay un usuario autenticado activo.
 *
 * @return bool
 */
function isAuthenticated(): bool
{
    return currentUser() !== null;
}

// -----------------------------------------------
// FUNCIONES DE LOGGING DE ACTIVIDAD
// -----------------------------------------------

/**
 * Registrar una actividad del sistema en el log de auditoría.
 *
 * @param string   $action      Acción realizada (ej: 'crear_factura', 'eliminar_producto')
 * @param string   $description Descripción detallada de la acción
 * @param int|null $userId      ID del usuario (null = usuario actual de sesión)
 */
function logActivity(string $action, string $description, ?int $userId = null): void
{
    try {
        // Determinar el usuario
        $user   = currentUser();
        $userId = $userId ?? ($user['id'] ?? null);

        // Registrar en base de datos si está disponible
        $db = Database::getInstance();
        $db->execute(
            "INSERT INTO actividad_log (usuario_id, accion, descripcion, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $userId,
                $action,
                $description,
                getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]
        );
    } catch (\Throwable) {
        // Si la BD no está disponible, registrar en archivo
        $logPath = defined('LOGS_PATH') ? LOGS_PATH . '/activity.log' : __DIR__ . '/../../storage/logs/activity.log';
        $logDir  = dirname($logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $line = sprintf(
            "[%s] [USER:%s] [IP:%s] %s: %s\n",
            date('Y-m-d H:i:s'),
            $userId ?? 'guest',
            getClientIp(),
            strtoupper($action),
            $description
        );

        file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Obtener la IP real del cliente, considerando proxies.
 *
 * @return string Dirección IP del cliente
 */
function getClientIp(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',   // Cloudflare
        'HTTP_X_FORWARDED_FOR',    // Proxy estándar
        'HTTP_X_REAL_IP',          // Nginx proxy
        'REMOTE_ADDR',             // Directo
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            // Tomar solo la primera IP en caso de lista separada por comas
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// -----------------------------------------------
// FUNCIONES UTILITARIAS ADICIONALES
// -----------------------------------------------

/**
 * Truncar un texto a cierta longitud añadiendo '...' al final.
 *
 * @param  string $texto   Texto a truncar
 * @param  int    $largo   Longitud máxima
 * @param  string $sufijo  Sufijo a añadir (default '...')
 * @return string          Texto truncado
 */
function truncar(string $texto, int $largo = 50, string $sufijo = '...'): string
{
    if (mb_strlen($texto, 'UTF-8') <= $largo) {
        return $texto;
    }
    return mb_substr($texto, 0, $largo - mb_strlen($sufijo, 'UTF-8'), 'UTF-8') . $sufijo;
}

/**
 * Validar que un RUC peruano tenga formato correcto (11 dígitos).
 *
 * @param  string $ruc RUC a validar
 * @return bool        true si el formato es válido
 */
function validarRuc(string $ruc): bool
{
    if (!preg_match('/^(10|15|16|17|20)\d{9}$/', $ruc)) {
        return false;
    }

    // Algoritmo de dígito verificador RUC SUNAT
    $factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    for ($i = 0; $i < 10; $i++) {
        $suma += (int) $ruc[$i] * $factores[$i];
    }
    $resto     = $suma % 11;
    $digitoRed = 11 - $resto;
    $digitoVerif = match(true) {
        $digitoRed >= 10 => $digitoRed - 10,
        default          => $digitoRed,
    };

    return $digitoVerif === (int) $ruc[10];
}

/**
 * Validar DNI peruano (8 dígitos numéricos).
 *
 * @param  string $dni DNI a validar
 * @return bool
 */
function validarDni(string $dni): bool
{
    return (bool) preg_match('/^\d{8}$/', $dni);
}

/**
 * Convertir bytes a formato legible (KB, MB, GB).
 *
 * @param  int    $bytes  Tamaño en bytes
 * @param  int    $dec    Decimales a mostrar
 * @return string         Tamaño formateado
 */
function formatBytes(int $bytes, int $dec = 2): string
{
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor   = (int) floor(log($bytes, 1024));
    return round($bytes / (1024 ** $factor), $dec) . ' ' . $unidades[$factor];
}

/**
 * Generar slug a partir de un texto (para URLs amigables).
 *
 * @param  string $texto Texto a convertir
 * @return string        Slug generado
 */
function slug(string $texto): string
{
    // Reemplazar caracteres especiales del español
    $texto = str_replace(
        ['á','é','í','ó','ú','ä','ë','ï','ö','ü','ñ','Á','É','Í','Ó','Ú','Ñ'],
        ['a','e','i','o','u','a','e','i','o','u','n','a','e','i','o','u','n'],
        $texto
    );

    $texto = strtolower(trim($texto));
    $texto = preg_replace('/[^a-z0-9\-]/', '-', $texto);
    $texto = preg_replace('/-+/', '-', $texto);

    return trim($texto, '-');
}
