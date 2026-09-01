<?php
/**
 * ============================================================
 * INSTALADOR DEL SISTEMA DE FACTURACIÓN ELECTRÓNICA
 * Facturación Pucallpa - Sistema ERP/POS
 * ============================================================
 * Archivo: install/index.php
 * Descripción: Script de instalación web paso a paso
 * PHP: 8.1+
 * ============================================================
 */

declare(strict_types=1);

// ─── Seguridad: bloquear si ya está instalado ───────────────
$lockFile = __DIR__ . '/installed.lock';
if (file_exists($lockFile)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>403 - Instalación bloqueada</title>
    <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#0f172a;}
    .box{background:#1e293b;color:#f1f5f9;padding:3rem;border-radius:1rem;text-align:center;border:1px solid #ef4444;}
    h1{color:#ef4444;font-size:2rem;margin:0 0 1rem;}p{color:#94a3b8;margin:0 0 1.5rem;}
    a{display:inline-block;background:#3b82f6;color:#fff;padding:.75rem 2rem;border-radius:.5rem;text-decoration:none;}</style></head>
    <body><div class="box"><h1>🔒 Instalación Bloqueada</h1>
    <p>El sistema ya fue instalado previamente.<br>Por seguridad, el instalador está deshabilitado.</p>
    <a href="../public/index.php">Ir al Sistema →</a></div></body></html>';
    exit;
}

// ─── Configuración del instalador ───────────────────────────
define('INSTALL_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('SCHEMA_FILE', BASE_PATH . '/database/schema.sql');
define('SEEDER_FILE', BASE_PATH . '/database/seeders/initial_data.sql');

// ─── Iniciar sesión para persistir datos entre pasos ────────
session_start();

// ─── Helpers ────────────────────────────────────────────────

/**
 * Verificar requisitos del sistema PHP
 */
function checkRequirements(): array
{
    $requirements = [];

    // PHP Version
    $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
    $requirements[] = [
        'nombre'    => 'PHP >= 8.1',
        'estado'    => $phpOk,
        'actual'    => PHP_VERSION,
        'requerido' => '>= 8.1.0',
    ];

    // Extensiones requeridas
    $extensions = [
        'pdo'       => 'PDO (acceso a base de datos)',
        'pdo_mysql' => 'PDO MySQL (driver MySQL)',
        'mbstring'  => 'mbstring (cadenas multibyte)',
        'openssl'   => 'OpenSSL (cifrado y firmas)',
        'gd'        => 'GD Library (imágenes/QR)',
        'zip'       => 'ZIP (archivos comprimidos)',
        'curl'      => 'cURL (conexión SUNAT)',
        'json'      => 'JSON (serialización datos)',
        'fileinfo'  => 'FileInfo (validación archivos)',
    ];

    foreach ($extensions as $ext => $nombre) {
        $ok = extension_loaded($ext);
        $requirements[] = [
            'nombre'    => $nombre,
            'estado'    => $ok,
            'actual'    => $ok ? 'Instalado' : 'No encontrado',
            'requerido' => 'Requerido',
        ];
    }

    // Carpetas con permisos de escritura
    $folders = [
        BASE_PATH . '/storage'          => 'storage/',
        BASE_PATH . '/storage/logs'     => 'storage/logs/',
        BASE_PATH . '/storage/xml'      => 'storage/xml/',
        BASE_PATH . '/storage/pdf'      => 'storage/pdf/',
        BASE_PATH . '/storage/cdr'      => 'storage/cdr/',
        BASE_PATH . '/storage/uploads'  => 'storage/uploads/',
        BASE_PATH . '/storage/backups'  => 'storage/backups/',
        BASE_PATH . '/storage/temp'     => 'storage/temp/',
    ];

    foreach ($folders as $path => $display) {
        // Crear la carpeta si no existe
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        $writable = is_writable($path);
        $requirements[] = [
            'nombre'    => "Carpeta $display",
            'estado'    => $writable,
            'actual'    => $writable ? 'Escribible' : 'Sin permisos',
            'requerido' => 'Escribible',
        ];
    }

    // Archivos SQL disponibles
    $requirements[] = [
        'nombre'    => 'Archivo schema.sql',
        'estado'    => file_exists(SCHEMA_FILE),
        'actual'    => file_exists(SCHEMA_FILE) ? 'Encontrado' : 'No encontrado',
        'requerido' => 'Requerido',
    ];

    $requirements[] = [
        'nombre'    => 'Archivo initial_data.sql',
        'estado'    => file_exists(SEEDER_FILE),
        'actual'    => file_exists(SEEDER_FILE) ? 'Encontrado' : 'No encontrado',
        'requerido' => 'Requerido',
    ];

    return $requirements;
}

/**
 * Verificar si todos los requisitos críticos están OK
 */
function allRequirementsMet(array $requirements): bool
{
    foreach ($requirements as $req) {
        if (!$req['estado']) {
            return false;
        }
    }
    return true;
}

/**
 * Probar conexión a la base de datos
 */
function testDatabaseConnection(array $db): array
{
    try {
        $dsn = "mysql:host={$db['host']};port={$db['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT            => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]);
        // Verificar versión MySQL
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        return ['ok' => true, 'version' => $version, 'pdo' => $pdo];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Crear base de datos si no existe
 */
function createDatabase(PDO $pdo, string $dbName): bool
{
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Ejecutar archivo SQL dividiendo por delimitadores correctamente
 */
function executeSqlFile(PDO $pdo, string $filePath): array
{
    if (!file_exists($filePath)) {
        return ['ok' => false, 'error' => "Archivo no encontrado: $filePath"];
    }

    $sql = file_get_contents($filePath);
    if ($sql === false) {
        return ['ok' => false, 'error' => 'No se pudo leer el archivo SQL'];
    }

    try {
        // Deshabilitar verificación FK durante instalación
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

        // Procesar el SQL manejando DELIMITER correctamente
        $statements = parseSqlStatements($sql);

        $executed = 0;
        $errors   = [];

        foreach ($statements as $statement) {
            $stmt = trim($statement);
            if (empty($stmt) || str_starts_with($stmt, '--') || str_starts_with($stmt, '#')) {
                continue;
            }
            try {
                $pdo->exec($stmt);
                $executed++;
            } catch (PDOException $e) {
                // Ignorar errores de DROP IF EXISTS (tabla no existe)
                $code = (string)$e->getCode();
                if (in_array($code, ['42S02', '42000']) && (
                    str_contains(strtolower($e->getMessage()), 'unknown table') ||
                    str_contains(strtolower($e->getMessage()), 'unknown trigger') ||
                    str_contains(strtolower($e->getMessage()), 'unknown view')
                )) {
                    continue;
                }
                $errors[] = substr($stmt, 0, 100) . '... => ' . $e->getMessage();
            }
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

        if (!empty($errors) && $executed === 0) {
            return ['ok' => false, 'error' => implode("\n", array_slice($errors, 0, 5))];
        }

        return ['ok' => true, 'executed' => $executed, 'warnings' => $errors];

    } catch (PDOException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Parsear sentencias SQL manejando DELIMITER personalizado y bloques BEGIN...END
 */
function parseSqlStatements(string $sql): array
{
    $statements = [];
    $delimiter  = ';';
    $current    = '';
    $lines      = explode("\n", $sql);

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Cambio de DELIMITER
        if (preg_match('/^DELIMITER\s+(\S+)/i', $trimmed, $matches)) {
            $delimiter = $matches[1];
            continue;
        }

        $current .= $line . "\n";

        // Verificar si la línea termina con el delimitador actual
        if ($delimiter === ';') {
            if (str_ends_with(rtrim($trimmed), ';')) {
                $stmt = rtrim($current, " \t\n\r");
                // Eliminar el delimitador final
                if (str_ends_with($stmt, ';')) {
                    $stmt = substr($stmt, 0, -1);
                }
                if (!empty(trim($stmt))) {
                    $statements[] = $stmt;
                }
                $current = '';
            }
        } else {
            // Delimitador personalizado (ej: $$)
            if (str_ends_with(rtrim($trimmed), $delimiter)) {
                $stmt = rtrim($current, " \t\n\r");
                // Eliminar el delimitador personalizado del final
                $stmt = substr($stmt, 0, -strlen($delimiter));
                if (!empty(trim($stmt))) {
                    $statements[] = trim($stmt);
                }
                $current = '';
            }
        }
    }

    // Última sentencia si existe
    if (!empty(trim($current))) {
        $statements[] = trim($current);
    }

    return $statements;
}

/**
 * Generar hash bcrypt seguro
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Cifrar con AES-256 para credenciales SUNAT
 */
function encryptValue(string $value, string $key): string
{
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($value, 'AES-256-CBC', hash('sha256', $key, true), 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Generar archivo .env con la configuración
 */
function generateEnvFile(array $config): bool
{
    $appKey = 'base64:' . base64_encode(openssl_random_pseudo_bytes(32));

    $env = "# ============================================================\n";
    $env .= "# SISTEMA DE FACTURACIÓN - CONFIGURACIÓN DE ENTORNO\n";
    $env .= "# Generado automáticamente por el instalador\n";
    $env .= "# Fecha: " . date('Y-m-d H:i:s') . "\n";
    $env .= "# ¡NUNCA subir este archivo al repositorio!\n";
    $env .= "# ============================================================\n\n";

    // App
    $env .= "# ── Aplicación ──────────────────────────────────────────────\n";
    $env .= "APP_NAME=\"Sistema de Facturación Pucallpa\"\n";
    $env .= "APP_ENV=production\n";
    $env .= "APP_DEBUG=false\n";
    $env .= "APP_KEY={$appKey}\n";
    $env .= "APP_URL=" . ($config['app_url'] ?? 'http://localhost/facturacion-pucallpa/public') . "\n\n";

    // Base de datos
    $env .= "# ── Base de Datos ───────────────────────────────────────────\n";
    $env .= "DB_HOST=" . ($config['db']['host'] ?? 'localhost') . "\n";
    $env .= "DB_PORT=" . ($config['db']['port'] ?? '3306') . "\n";
    $env .= "DB_DATABASE=" . ($config['db']['name'] ?? 'facturacion_pucallpa') . "\n";
    $env .= "DB_USERNAME=" . ($config['db']['user'] ?? '') . "\n";
    $env .= "DB_PASSWORD=" . ($config['db']['pass'] ?? '') . "\n";
    $env .= "DB_CHARSET=utf8mb4\n\n";

    // Empresa
    $env .= "# ── Empresa ─────────────────────────────────────────────────\n";
    $env .= "EMPRESA_RUC=" . ($config['empresa']['ruc'] ?? '') . "\n";
    $env .= "EMPRESA_RAZON_SOCIAL=\"" . ($config['empresa']['razon_social'] ?? '') . "\"\n";
    $env .= "EMPRESA_NOMBRE_COMERCIAL=\"" . ($config['empresa']['nombre_comercial'] ?? '') . "\"\n";
    $env .= "EMPRESA_DIRECCION=\"" . ($config['empresa']['direccion'] ?? '') . "\"\n";
    $env .= "EMPRESA_TELEFONO=" . ($config['empresa']['telefono'] ?? '') . "\n";
    $env .= "EMPRESA_EMAIL=" . ($config['empresa']['email'] ?? '') . "\n\n";

    // SUNAT
    $env .= "# ── SUNAT ───────────────────────────────────────────────────\n";
    $env .= "SUNAT_RUC=" . ($config['empresa']['ruc'] ?? '') . "\n";
    $env .= "SUNAT_USUARIO_SOL=" . ($config['sunat']['usuario_sol'] ?? '') . "\n";
    $env .= "SUNAT_CLAVE_SOL_ENCRYPTED=" . ($config['sunat']['clave_sol_encrypted'] ?? '') . "\n";
    $env .= "SUNAT_MODO=" . ($config['sunat']['modo'] ?? 'beta') . "\n";
    $env .= "SUNAT_CERT_PATH=" . ($config['sunat']['cert_path'] ?? '') . "\n";
    $env .= "SUNAT_CERT_PASS_ENCRYPTED=" . ($config['sunat']['cert_pass_encrypted'] ?? '') . "\n\n";

    // Seguridad
    $env .= "# ── Seguridad ───────────────────────────────────────────────\n";
    $env .= "ENCRYPT_KEY=" . base64_encode(openssl_random_pseudo_bytes(32)) . "\n";
    $env .= "SESSION_LIFETIME=480\n";
    $env .= "CSRF_TOKEN_LIFETIME=3600\n";
    $env .= "MAX_LOGIN_ATTEMPTS=5\n";
    $env .= "LOCKOUT_DURATION=900\n\n";

    // Storage
    $env .= "# ── Rutas de Almacenamiento ─────────────────────────────────\n";
    $env .= "STORAGE_PATH=" . BASE_PATH . "/storage\n";
    $env .= "LOG_PATH=" . BASE_PATH . "/storage/logs\n";
    $env .= "XML_PATH=" . BASE_PATH . "/storage/xml\n";
    $env .= "PDF_PATH=" . BASE_PATH . "/storage/pdf\n";
    $env .= "CDR_PATH=" . BASE_PATH . "/storage/cdr\n\n";

    // Timezone
    $env .= "# ── Zona Horaria ────────────────────────────────────────────\n";
    $env .= "TZ=America/Lima\n";
    $env .= "DB_TIMEZONE=-05:00\n";

    $envPath = BASE_PATH . '/.env';
    return file_put_contents($envPath, $env) !== false;
}

/**
 * Sanitizar entrada de usuario (XSS prevention)
 */
function sanitize(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Validar RUC peruano (11 dígitos con algoritmo de verificación)
 */
function validateRuc(string $ruc): bool
{
    if (!preg_match('/^(10|15|16|17|20)\d{9}$/', $ruc)) {
        return false;
    }
    $factors = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $sum = 0;
    for ($i = 0; $i < 10; $i++) {
        $sum += (int)$ruc[$i] * $factors[$i];
    }
    $remainder = $sum % 11;
    $digit     = 11 - $remainder;
    if ($digit === 10) $digit = 0;
    if ($digit === 11) $digit = 1;
    return $digit === (int)$ruc[10];
}

// ─── Procesamiento de peticiones POST ───────────────────────

$step    = (int)($_GET['step'] ?? $_SESSION['install_step'] ?? 1);
$errors  = [];
$success = [];

// Paso 1: Verificar requisitos → Paso 2
if ($step === 1 && isset($_POST['continuar'])) {
    $requirements = checkRequirements();
    if (allRequirementsMet($requirements)) {
        $_SESSION['install_step'] = 2;
        header('Location: ?step=2');
        exit;
    } else {
        $errors[] = 'Por favor corrija todos los requisitos antes de continuar.';
    }
}

// Paso 2: Datos de empresa → guardar y pasar a paso 3
if ($step === 2 && isset($_POST['continuar'])) {
    $empresa = [
        'ruc'              => trim($_POST['ruc']              ?? ''),
        'razon_social'     => trim($_POST['razon_social']     ?? ''),
        'nombre_comercial' => trim($_POST['nombre_comercial'] ?? ''),
        'direccion'        => trim($_POST['direccion']        ?? ''),
        'distrito'         => trim($_POST['distrito']         ?? ''),
        'provincia'        => trim($_POST['provincia']        ?? ''),
        'departamento'     => trim($_POST['departamento']     ?? ''),
        'telefono'         => trim($_POST['telefono']         ?? ''),
        'email'            => trim($_POST['email']            ?? ''),
        'web'              => trim($_POST['web']              ?? ''),
        'igv'              => trim($_POST['igv']              ?? '18'),
        'tipo_cambio'      => trim($_POST['tipo_cambio']      ?? '3.70'),
        'app_url'          => trim($_POST['app_url']          ?? ''),
    ];

    // Validaciones
    if (empty($empresa['ruc'])) {
        $errors[] = 'El RUC es obligatorio.';
    } elseif (!validateRuc($empresa['ruc'])) {
        $errors[] = 'El RUC ingresado no es válido. Verifique los 11 dígitos.';
    }
    if (empty($empresa['razon_social'])) {
        $errors[] = 'La razón social es obligatoria.';
    }
    if (empty($empresa['direccion'])) {
        $errors[] = 'La dirección fiscal es obligatoria.';
    }
    if (!empty($empresa['email']) && !filter_var($empresa['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El email de la empresa no es válido.';
    }

    if (empty($errors)) {
        $_SESSION['install_empresa']  = $empresa;
        $_SESSION['install_step']     = 3;
        header('Location: ?step=3');
        exit;
    }
}

// Paso 3: Configuración de base de datos
if ($step === 3 && isset($_POST['continuar'])) {
    $db = [
        'host' => trim($_POST['db_host'] ?? 'localhost'),
        'port' => trim($_POST['db_port'] ?? '3306'),
        'name' => trim($_POST['db_name'] ?? 'facturacion_pucallpa'),
        'user' => trim($_POST['db_user'] ?? ''),
        'pass' => $_POST['db_pass']        ?? '',
    ];

    if (empty($db['user'])) {
        $errors[] = 'El usuario de base de datos es obligatorio.';
    }
    if (empty($db['name'])) {
        $errors[] = 'El nombre de la base de datos es obligatorio.';
    }

    if (empty($errors)) {
        $test = testDatabaseConnection($db);
        if (!$test['ok']) {
            $errors[] = 'No se pudo conectar a la base de datos: ' . $test['error'];
        } else {
            $_SESSION['install_db']   = $db;
            $_SESSION['install_step'] = 4;
            header('Location: ?step=4');
            exit;
        }
    }
}

// Paso 4: Credenciales SUNAT
if ($step === 4 && isset($_POST['continuar'])) {
    $sunat = [
        'usuario_sol'  => trim($_POST['sunat_usuario'] ?? ''),
        'clave_sol'    => $_POST['sunat_clave']          ?? '',
        'modo'         => $_POST['sunat_modo']            ?? 'beta',
        'serie_f'      => strtoupper(trim($_POST['serie_factura'] ?? 'F001')),
        'serie_b'      => strtoupper(trim($_POST['serie_boleta']  ?? 'B001')),
        'cert_path'    => trim($_POST['cert_path']        ?? ''),
        'cert_pass'    => $_POST['cert_pass']              ?? '',
    ];

    // Validaciones SUNAT opcionales (se puede configurar después)
    if (!empty($sunat['serie_f']) && !preg_match('/^F\d{3}$/', $sunat['serie_f'])) {
        $errors[] = 'La serie de factura debe tener formato F001, F002, etc.';
    }
    if (!empty($sunat['serie_b']) && !preg_match('/^B\d{3}$/', $sunat['serie_b'])) {
        $errors[] = 'La serie de boleta debe tener formato B001, B002, etc.';
    }

    if (empty($errors)) {
        $_SESSION['install_sunat']  = $sunat;
        $_SESSION['install_step']   = 5;
        header('Location: ?step=5');
        exit;
    }
}

// Paso 5: Usuario administrador
if ($step === 5 && isset($_POST['continuar'])) {
    $admin = [
        'nombre'    => trim($_POST['admin_nombre']    ?? ''),
        'apellidos' => trim($_POST['admin_apellidos'] ?? ''),
        'email'     => trim($_POST['admin_email']     ?? ''),
        'username'  => trim($_POST['admin_username']  ?? ''),
        'password'  => $_POST['admin_password']       ?? '',
        'password2' => $_POST['admin_password2']      ?? '',
    ];

    if (empty($admin['nombre'])) {
        $errors[] = 'El nombre del administrador es obligatorio.';
    }
    if (empty($admin['apellidos'])) {
        $errors[] = 'Los apellidos del administrador son obligatorios.';
    }
    if (empty($admin['email']) || !filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El email del administrador no es válido.';
    }
    if (empty($admin['username']) || strlen($admin['username']) < 4) {
        $errors[] = 'El usuario debe tener al menos 4 caracteres.';
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $admin['username'])) {
        $errors[] = 'El usuario solo puede contener letras, números y guión bajo.';
    }
    if (strlen($admin['password']) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Z]/', $admin['password'])) {
        $errors[] = 'La contraseña debe contener al menos una letra mayúscula.';
    }
    if (!preg_match('/[0-9]/', $admin['password'])) {
        $errors[] = 'La contraseña debe contener al menos un número.';
    }
    if ($admin['password'] !== $admin['password2']) {
        $errors[] = 'Las contraseñas no coinciden.';
    }

    if (empty($errors)) {
        $_SESSION['install_admin'] = $admin;
        $_SESSION['install_step']  = 6;
        header('Location: ?step=6');
        exit;
    }
}

// Paso 6: Ejecutar instalación
if ($step === 6 && isset($_POST['instalar'])) {
    $installLog = [];
    $installOk  = true;

    // Recuperar datos de sesión
    $empresa = $_SESSION['install_empresa'] ?? [];
    $db      = $_SESSION['install_db']      ?? [];
    $sunat   = $_SESSION['install_sunat']   ?? [];
    $admin   = $_SESSION['install_admin']   ?? [];

    if (empty($empresa) || empty($db) || empty($admin)) {
        header('Location: ?step=1');
        exit;
    }

    try {
        // ── Conexión a MySQL ──────────────────────────────────
        $dsn = "mysql:host={$db['host']};port={$db['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_TIMEOUT            => 30,
        ]);
        $installLog[] = ['ok' => true, 'msg' => 'Conexión a MySQL establecida (' . testDatabaseConnection($db)['version'] . ')'];

        // ── Crear base de datos ───────────────────────────────
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$db['name']}`");
        $installLog[] = ['ok' => true, 'msg' => "Base de datos '{$db['name']}' creada/verificada"];

        // ── Ejecutar schema.sql ───────────────────────────────
        $result = executeSqlFile($pdo, SCHEMA_FILE);
        if ($result['ok']) {
            $installLog[] = ['ok' => true, 'msg' => "Schema SQL ejecutado ({$result['executed']} sentencias)"];
            if (!empty($result['warnings'])) {
                $installLog[] = ['ok' => true, 'msg' => 'Advertencias (no críticas): ' . count($result['warnings'])];
            }
        } else {
            $installLog[] = ['ok' => false, 'msg' => 'Error en schema.sql: ' . $result['error']];
            $installOk = false;
        }

        // ── Ejecutar initial_data.sql ─────────────────────────
        if ($installOk) {
            $result = executeSqlFile($pdo, SEEDER_FILE);
            if ($result['ok']) {
                $installLog[] = ['ok' => true, 'msg' => "Datos iniciales insertados ({$result['executed']} sentencias)"];
            } else {
                $installLog[] = ['ok' => false, 'msg' => 'Error en initial_data.sql: ' . $result['error']];
                $installOk = false;
            }
        }

        // ── Actualizar datos de empresa ───────────────────────
        if ($installOk) {
            $stmt = $pdo->prepare("
                UPDATE configuracion_empresa SET
                    ruc              = :ruc,
                    razon_social     = :razon_social,
                    nombre_comercial = :nombre_comercial,
                    direccion        = :direccion,
                    distrito         = :distrito,
                    provincia        = :provincia,
                    departamento     = :departamento,
                    telefono         = :telefono,
                    email            = :email,
                    web              = :web,
                    igv_porcentaje   = :igv,
                    tipo_cambio_usd  = :tipo_cambio
                WHERE id = 1
            ");
            $stmt->execute([
                ':ruc'              => $empresa['ruc'],
                ':razon_social'     => $empresa['razon_social'],
                ':nombre_comercial' => $empresa['nombre_comercial'],
                ':direccion'        => $empresa['direccion'],
                ':distrito'         => $empresa['distrito'],
                ':provincia'        => $empresa['provincia'],
                ':departamento'     => $empresa['departamento'],
                ':telefono'         => $empresa['telefono'],
                ':email'            => $empresa['email'],
                ':web'              => $empresa['web'],
                ':igv'              => (float)($empresa['igv'] ?? 18),
                ':tipo_cambio'      => (float)($empresa['tipo_cambio'] ?? 3.70),
            ]);
            $installLog[] = ['ok' => true, 'msg' => 'Datos de empresa actualizados'];
        }

        // ── Actualizar credenciales SUNAT ─────────────────────
        if ($installOk) {
            // Generar clave de encriptación
            $encryptKey = $empresa['ruc'] . ($db['name'] ?? 'facturacion');
            $claveSolEncrypted = '';
            $certPassEncrypted = '';

            if (!empty($sunat['clave_sol'])) {
                $claveSolEncrypted = encryptValue($sunat['clave_sol'], $encryptKey);
            }
            if (!empty($sunat['cert_pass'])) {
                $certPassEncrypted = encryptValue($sunat['cert_pass'], $encryptKey);
            }

            $stmt = $pdo->prepare("
                UPDATE configuracion_sunat SET
                    ruc                    = :ruc,
                    usuario_sol            = :usuario_sol,
                    clave_sol_encrypted    = :clave_sol,
                    modo                   = :modo,
                    serie_factura          = :serie_f,
                    serie_boleta           = :serie_b,
                    certificado_path       = :cert_path,
                    certificado_pass_encrypted = :cert_pass
                WHERE id = 1
            ");
            $stmt->execute([
                ':ruc'        => $empresa['ruc'],
                ':usuario_sol'=> $sunat['usuario_sol'] ?? '',
                ':clave_sol'  => $claveSolEncrypted,
                ':modo'       => $sunat['modo'] ?? 'beta',
                ':serie_f'    => $sunat['serie_f'] ?? 'F001',
                ':serie_b'    => $sunat['serie_b'] ?? 'B001',
                ':cert_path'  => $sunat['cert_path'] ?? '',
                ':cert_pass'  => $certPassEncrypted,
            ]);

            // Guardar versión cifrada para el .env
            $sunat['clave_sol_encrypted'] = $claveSolEncrypted;
            $sunat['cert_pass_encrypted'] = $certPassEncrypted;

            $installLog[] = ['ok' => true, 'msg' => 'Configuración SUNAT guardada (credenciales cifradas)'];
        }

        // ── Crear usuario administrador ───────────────────────
        if ($installOk) {
            $passwordHash = hashPassword($admin['password']);
            $stmt = $pdo->prepare("
                UPDATE usuarios SET
                    nombre    = :nombre,
                    apellidos = :apellidos,
                    email     = :email,
                    username  = :username,
                    password  = :password
                WHERE username = 'admin' AND rol_id = 1
            ");
            $stmt->execute([
                ':nombre'    => $admin['nombre'],
                ':apellidos' => $admin['apellidos'],
                ':email'     => $admin['email'],
                ':username'  => $admin['username'],
                ':password'  => $passwordHash,
            ]);

            if ($stmt->rowCount() === 0) {
                // Insertar si no se actualizó
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (nombre, apellidos, email, username, password, rol_id, activo)
                    VALUES (:nombre, :apellidos, :email, :username, :password, 1, 1)
                    ON DUPLICATE KEY UPDATE
                        nombre    = VALUES(nombre),
                        apellidos = VALUES(apellidos),
                        email     = VALUES(email),
                        password  = VALUES(password)
                ");
                $stmt->execute([
                    ':nombre'    => $admin['nombre'],
                    ':apellidos' => $admin['apellidos'],
                    ':email'     => $admin['email'],
                    ':username'  => $admin['username'],
                    ':password'  => $passwordHash,
                ]);
            }
            $installLog[] = ['ok' => true, 'msg' => "Usuario administrador '{$admin['username']}' configurado"];
        }

        // ── Actualizar series en configuracion_series ─────────
        if ($installOk && !empty($sunat['serie_f'])) {
            $pdo->prepare("UPDATE configuracion_series SET serie=:s WHERE tipo_comprobante='01' AND es_principal=1")
                ->execute([':s' => $sunat['serie_f']]);
            $pdo->prepare("UPDATE configuracion_series SET serie=:s WHERE tipo_comprobante='03' AND es_principal=1")
                ->execute([':s' => $sunat['serie_b']]);
            $installLog[] = ['ok' => true, 'msg' => 'Series de comprobantes configuradas'];
        }

        // ── Generar archivo .env ──────────────────────────────
        if ($installOk) {
            $envConfig = [
                'db'      => $db,
                'empresa' => $empresa,
                'sunat'   => $sunat,
                'app_url' => $empresa['app_url'] ?? 'http://localhost/facturacion-pucallpa/public',
            ];
            if (generateEnvFile($envConfig)) {
                $installLog[] = ['ok' => true, 'msg' => 'Archivo .env generado correctamente'];
            } else {
                $installLog[] = ['ok' => false, 'msg' => 'No se pudo generar el archivo .env (verificar permisos)'];
            }
        }

        // ── Crear carpetas de storage ─────────────────────────
        $storageDirs = [
            BASE_PATH . '/storage',
            BASE_PATH . '/storage/logs',
            BASE_PATH . '/storage/xml',
            BASE_PATH . '/storage/xml/facturas',
            BASE_PATH . '/storage/xml/boletas',
            BASE_PATH . '/storage/pdf',
            BASE_PATH . '/storage/cdr',
            BASE_PATH . '/storage/uploads',
            BASE_PATH . '/storage/uploads/productos',
            BASE_PATH . '/storage/uploads/empresa',
            BASE_PATH . '/storage/backups',
            BASE_PATH . '/storage/temp',
            BASE_PATH . '/storage/certificados',
        ];
        $dirErrors = 0;
        foreach ($storageDirs as $dir) {
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0755, true)) {
                    $dirErrors++;
                }
            }
            // Crear .htaccess protector en carpetas sensibles
            if (in_array(basename($dir), ['logs', 'certificados', 'backups'])) {
                $htaccess = $dir . '/.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, "Options -Indexes\nDeny from all\n");
                }
            }
        }
        $installLog[] = [
            'ok'  => ($dirErrors === 0),
            'msg' => $dirErrors === 0
                ? 'Estructura de carpetas storage/ creada correctamente'
                : "Advertencia: {$dirErrors} carpetas no pudieron crearse (verificar permisos)"
        ];

        // ── Crear archivo installed.lock ──────────────────────
        if ($installOk) {
            $lockContent = json_encode([
                'installed_at'   => date('Y-m-d H:i:s'),
                'version'        => INSTALL_VERSION,
                'php_version'    => PHP_VERSION,
                'db_name'        => $db['name'],
                'empresa_ruc'    => $empresa['ruc'],
                'admin_username' => $admin['username'],
            ], JSON_PRETTY_PRINT);

            if (file_put_contents($lockFile, $lockContent)) {
                $installLog[] = ['ok' => true, 'msg' => 'Instalador bloqueado (installed.lock creado)'];
            } else {
                $installLog[] = ['ok' => false, 'msg' => 'No se pudo crear installed.lock - ¡eliminar manualmente la carpeta install/!'];
            }
        }

        // ── Registro en log del sistema ───────────────────────
        if ($installOk) {
            $pdo->prepare("
                INSERT INTO logs_sistema (usuario_id, accion, modulo, descripcion, ip)
                VALUES (1, 'instalacion_completada', 'sistema', :desc, :ip)
            ")->execute([
                ':desc' => "Sistema instalado correctamente. Empresa: {$empresa['razon_social']} (RUC: {$empresa['ruc']})",
                ':ip'   => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
        }

        // Limpiar sesión
        if ($installOk) {
            session_destroy();
        }

    } catch (PDOException $e) {
        $installLog[] = ['ok' => false, 'msg' => 'Error de base de datos: ' . $e->getMessage()];
        $installOk    = false;
    } catch (Throwable $e) {
        $installLog[] = ['ok' => false, 'msg' => 'Error inesperado: ' . $e->getMessage()];
        $installOk    = false;
    }

    // Almacenar resultado
    $_SESSION['install_log']    = $installLog;
    $_SESSION['install_result'] = $installOk;
    $_SESSION['install_step']   = 7;
    header('Location: ?step=7');
    exit;
}

// ─── Cargar requisitos para mostrar ─────────────────────────
$requirements = ($step === 1) ? checkRequirements() : [];

?>
<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador — Sistema de Facturación</title>

    <!-- Tailwind CSS CDN (solo para instalador) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' }
                    }
                }
            }
        }
    </script>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        /* Animación de progress */
        @keyframes pulse-soft {
            0%, 100% { opacity: 1; }
            50%       { opacity: .6; }
        }
        .pulse-soft { animation: pulse-soft 2s ease-in-out infinite; }

        /* Spinner */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .spinner { animation: spin .8s linear infinite; }

        /* Fade in */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn .4s ease-out; }

        /* Strength bar */
        .strength-bar div { transition: width .3s ease; }

        /* Step connector */
        .step-connector {
            position: absolute;
            top: 1.25rem;
            left: calc(50% + 1.25rem);
            right: calc(-50% + 1.25rem);
            height: 2px;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-950 text-gray-100">

<!-- ─── Header ───────────────────────────────────────── -->
<header class="bg-gray-900 border-b border-gray-800 shadow-lg">
    <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg">
                <i class="bi bi-receipt text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-white leading-tight">Sistema de Facturación</h1>
                <p class="text-xs text-gray-400">Instalador v<?= INSTALL_VERSION ?> — Pucallpa, Perú</p>
            </div>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-400">
            <i class="bi bi-shield-lock text-green-400"></i>
            <span>Instalación Segura</span>
        </div>
    </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-8">

<!-- ─── Indicador de pasos ────────────────────────────── -->
<?php if ($step <= 7): ?>
<div class="mb-8 fade-in">
    <div class="flex items-center justify-center">
        <?php
        $steps = [
            1 => ['icon' => 'check2-circle',    'label' => 'Requisitos'],
            2 => ['icon' => 'building',          'label' => 'Empresa'],
            3 => ['icon' => 'database',          'label' => 'Base de Datos'],
            4 => ['icon' => 'file-earmark-text', 'label' => 'SUNAT'],
            5 => ['icon' => 'person-lock',       'label' => 'Administrador'],
            6 => ['icon' => 'gear-wide-connected','label' => 'Instalar'],
            7 => ['icon' => 'check-circle',      'label' => 'Completado'],
        ];
        foreach ($steps as $n => $info):
            $isDone    = $n < $step;
            $isCurrent = $n === $step;
            $isNext    = $n > $step;
        ?>
        <div class="relative flex flex-col items-center <?= $n < 7 ? 'flex-1' : '' ?>">
            <!-- Círculo del paso -->
            <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold shadow-lg z-10
                <?= $isDone    ? 'bg-green-500 text-white'  : '' ?>
                <?= $isCurrent ? 'bg-blue-600 text-white ring-4 ring-blue-600/30' : '' ?>
                <?= $isNext    ? 'bg-gray-700 text-gray-400' : '' ?>">
                <?php if ($isDone): ?>
                    <i class="bi bi-check2 text-lg"></i>
                <?php else: ?>
                    <i class="bi bi-<?= $info['icon'] ?> text-base"></i>
                <?php endif; ?>
            </div>
            <!-- Etiqueta -->
            <span class="mt-1 text-xs hidden sm:block
                <?= $isDone    ? 'text-green-400' : '' ?>
                <?= $isCurrent ? 'text-blue-400 font-semibold' : '' ?>
                <?= $isNext    ? 'text-gray-500' : '' ?>">
                <?= $info['label'] ?>
            </span>
            <!-- Línea conectora -->
            <?php if ($n < 7): ?>
            <div class="step-connector <?= $isDone ? 'bg-green-500' : 'bg-gray-700' ?>"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ─── PASO 1: Verificación de Requisitos ───────────── -->
<?php if ($step === 1): ?>
<div class="fade-in">
    <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden shadow-xl">
        <div class="bg-gradient-to-r from-blue-900/50 to-purple-900/50 px-6 py-5 border-b border-gray-800">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="bi bi-check2-circle text-blue-400"></i>
                Verificación de Requisitos del Sistema
            </h2>
            <p class="text-gray-400 text-sm mt-1">Se verificarán todas las dependencias necesarias antes de continuar.</p>
        </div>

        <div class="p-6">
            <?php if (!empty($errors)): ?>
            <div class="mb-4 p-4 bg-red-900/30 border border-red-700 rounded-xl flex items-start gap-3">
                <i class="bi bi-exclamation-triangle-fill text-red-400 text-xl flex-shrink-0 mt-0.5"></i>
                <div>
                    <?php foreach ($errors as $e): ?>
                    <p class="text-red-300 text-sm"><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Lista de requisitos -->
            <div class="space-y-2">
                <?php
                $sections = [
                    'php'     => ['title' => 'PHP y Versión', 'items' => []],
                    'ext'     => ['title' => 'Extensiones PHP', 'items' => []],
                    'folders' => ['title' => 'Permisos de Carpetas', 'items' => []],
                    'files'   => ['title' => 'Archivos SQL', 'items' => []],
                ];
                // Clasificar requisitos
                foreach ($requirements as $i => $req) {
                    if ($i === 0) $sections['php']['items'][] = $req;
                    elseif (str_contains($req['nombre'], 'Carpeta')) $sections['folders']['items'][] = $req;
                    elseif (str_contains($req['nombre'], 'Archivo')) $sections['files']['items'][] = $req;
                    else $sections['ext']['items'][] = $req;
                }
                foreach ($sections as $sec):
                ?>
                <div class="bg-gray-800/50 rounded-xl overflow-hidden">
                    <div class="px-4 py-2 bg-gray-800 flex items-center gap-2">
                        <i class="bi bi-list-check text-gray-400"></i>
                        <span class="text-sm font-semibold text-gray-300"><?= $sec['title'] ?></span>
                    </div>
                    <div class="divide-y divide-gray-700/50">
                        <?php foreach ($sec['items'] as $req): ?>
                        <div class="px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <?php if ($req['estado']): ?>
                                    <i class="bi bi-check-circle-fill text-green-400"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-red-400"></i>
                                <?php endif; ?>
                                <span class="text-sm text-gray-200"><?= htmlspecialchars($req['nombre']) ?></span>
                            </div>
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-gray-500"><?= htmlspecialchars($req['requerido']) ?></span>
                                <span class="<?= $req['estado'] ? 'text-green-400' : 'text-red-400' ?> font-medium">
                                    <?= htmlspecialchars($req['actual']) ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Resumen -->
            <?php $allOk = allRequirementsMet($requirements); ?>
            <div class="mt-4 p-4 rounded-xl <?= $allOk ? 'bg-green-900/30 border border-green-700' : 'bg-yellow-900/30 border border-yellow-700' ?>">
                <div class="flex items-center gap-2">
                    <?php if ($allOk): ?>
                    <i class="bi bi-check-circle-fill text-green-400 text-xl"></i>
                    <p class="text-green-300 font-semibold">¡Todos los requisitos están cumplidos! Puede continuar con la instalación.</p>
                    <?php else: ?>
                    <i class="bi bi-exclamation-triangle-fill text-yellow-400 text-xl"></i>
                    <p class="text-yellow-300 font-semibold">Algunos requisitos no están cumplidos. Corríjalos antes de continuar.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-gray-800/50 border-t border-gray-700 flex justify-between items-center">
            <p class="text-sm text-gray-500">PHP <?= PHP_VERSION ?> — <?= PHP_OS ?></p>
            <form method="POST">
                <button type="submit" name="continuar"
                    <?= !$allOk ? 'disabled' : '' ?>
                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed
                           text-white rounded-xl font-semibold transition-all flex items-center gap-2 shadow-lg">
                    Continuar
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ─── PASO 2: Datos de la Empresa ─────────────────── -->
<?php elseif ($step === 2): ?>
<div class="fade-in">
    <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden shadow-xl">
        <div class="bg-gradient-to-r from-blue-900/50 to-indigo-900/50 px-6 py-5 border-b border-gray-800">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="bi bi-building text-blue-400"></i>
                Datos de la Empresa
            </h2>
            <p class="text-gray-400 text-sm mt-1">Ingrese los datos fiscales de su empresa registrada en SUNAT.</p>
        </div>

        <form method="POST" class="p-6" id="formEmpresa" novalidate>
            <?php if (!empty($errors)): ?>
            <div class="mb-4 p-4 bg-red-900/30 border border-red-700 rounded-xl">
                <?php foreach ($errors as $e): ?>
                <p class="text-red-300 text-sm flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($e) ?>
                </p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- RUC -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        RUC de la Empresa <span class="text-red-400">*</span>
                    </label>
                    <div class="flex gap-2">
                        <input type="text" name="ruc" id="ruc" maxlength="11"
                            value="<?= htmlspecialchars($_SESSION['install_empresa']['ruc'] ?? '') ?>"
                            placeholder="20XXXXXXXXX"
                            class="flex-1 bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                                   placeholder-gray-500 font-mono text-lg"
                            required>
                        <button type="button" id="btnConsultarRUC"
                            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm
                                   font-medium transition-all flex items-center gap-1.5">
                            <i class="bi bi-search"></i>
                            <span class="hidden sm:inline">Consultar SUNAT</span>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Ingrese el RUC de 11 dígitos de su empresa</p>
                </div>

                <!-- Razón Social -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Razón Social <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="razon_social" id="razon_social" maxlength="150"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['razon_social'] ?? '') ?>"
                        placeholder="EMPRESA EJEMPLO S.A.C."
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500"
                        required>
                </div>

                <!-- Nombre Comercial -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Nombre Comercial</label>
                    <input type="text" name="nombre_comercial" maxlength="150"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['nombre_comercial'] ?? '') ?>"
                        placeholder="Mi Empresa"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500">
                </div>

                <!-- Teléfono -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Teléfono</label>
                    <input type="tel" name="telefono" maxlength="20"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['telefono'] ?? '') ?>"
                        placeholder="(061) 123456"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Email de la Empresa</label>
                    <input type="email" name="email" maxlength="150"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['email'] ?? '') ?>"
                        placeholder="info@empresa.com"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500">
                </div>

                <!-- Sitio Web -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Sitio Web</label>
                    <input type="url" name="web" maxlength="150"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['web'] ?? '') ?>"
                        placeholder="https://www.empresa.com"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500">
                </div>

                <!-- Dirección -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Dirección Fiscal <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="direccion" maxlength="300"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['direccion'] ?? '') ?>"
                        placeholder="Jr. Comercio 123"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500"
                        required>
                </div>

                <!-- Distrito -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Distrito</label>
                    <input type="text" name="distrito" maxlength="100"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['distrito'] ?? 'Callería') ?>"
                        placeholder="Callería"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500">
                </div>

                <!-- Provincia -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Provincia</label>
                    <input type="text" name="provincia" maxlength="100"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['provincia'] ?? 'Coronel Portillo') ?>"
                        placeholder="Coronel Portillo"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500">
                </div>

                <!-- Departamento -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Departamento</label>
                    <input type="text" name="departamento" maxlength="100"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['departamento'] ?? 'Ucayali') ?>"
                        placeholder="Ucayali"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500">
                </div>

                <!-- Separador -->
                <div class="md:col-span-2 border-t border-gray-700 pt-4 mt-2">
                    <h3 class="text-sm font-semibold text-gray-300 mb-3 flex items-center gap-2">
                        <i class="bi bi-calculator text-blue-400"></i>
                        Configuración Tributaria
                    </h3>
                </div>

                <!-- IGV -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Porcentaje IGV (%)
                    </label>
                    <input type="number" name="igv" min="0" max="30" step="0.01"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['igv'] ?? '18') ?>"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">IGV actual en Perú: 18%</p>
                </div>

                <!-- Tipo de cambio -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Tipo de Cambio USD/PEN
                    </label>
                    <input type="number" name="tipo_cambio" min="1" max="10" step="0.0001"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['tipo_cambio'] ?? '3.70') ?>"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Actualizable diariamente en el sistema</p>
                </div>

                <!-- URL del sistema -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-1">URL del Sistema</label>
                    <input type="url" name="app_url"
                        value="<?= htmlspecialchars($_SESSION['install_empresa']['app_url'] ?? 'http://localhost/facturacion-pucallpa/public') ?>"
                        placeholder="http://localhost/facturacion-pucallpa/public"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-blue-500 placeholder-gray-500 font-mono text-sm">
                    <p class="text-xs text-gray-500 mt-1">URL base de acceso al sistema (sin barra final)</p>
                </div>

            </div><!-- /grid -->

            <div class="flex justify-between mt-6 pt-4 border-t border-gray-700">
                <a href="?step=1" class="px-4 py-2.5 text-gray-400 hover:text-white transition-colors flex items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Atrás
                </a>
                <button type="submit" name="continuar"
                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold
                           transition-all flex items-center gap-2 shadow-lg">
                    Siguiente <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ─── PASO 3: Configuración de Base de Datos ───────── -->
<?php elseif ($step === 3): ?>
<div class="fade-in">
    <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden shadow-xl">
        <div class="bg-gradient-to-r from-emerald-900/50 to-teal-900/50 px-6 py-5 border-b border-gray-800">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="bi bi-database text-emerald-400"></i>
                Configuración de Base de Datos
            </h2>
            <p class="text-gray-400 text-sm mt-1">Ingrese los datos de conexión a su servidor MySQL.</p>
        </div>

        <form method="POST" class="p-6">
            <?php if (!empty($errors)): ?>
            <div class="mb-4 p-4 bg-red-900/30 border border-red-700 rounded-xl">
                <?php foreach ($errors as $e): ?>
                <p class="text-red-300 text-sm flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($e) ?>
                </p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- Host -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Host del Servidor <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="db_host" maxlength="100"
                        value="<?= htmlspecialchars($_SESSION['install_db']['host'] ?? 'localhost') ?>"
                        placeholder="localhost"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-gray-500 font-mono"
                        required>
                </div>

                <!-- Puerto -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Puerto</label>
                    <input type="number" name="db_port" min="1" max="65535"
                        value="<?= htmlspecialchars($_SESSION['install_db']['port'] ?? '3306') ?>"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono">
                    <p class="text-xs text-gray-500 mt-1">Puerto por defecto MySQL: 3306</p>
                </div>

                <!-- Nombre de BD -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Nombre de Base de Datos <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="db_name" maxlength="64"
                        value="<?= htmlspecialchars($_SESSION['install_db']['name'] ?? 'facturacion_pucallpa') ?>"
                        placeholder="facturacion_pucallpa"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-gray-500 font-mono"
                        required>
                    <p class="text-xs text-gray-500 mt-1">Se creará si no existe</p>
                </div>

                <!-- Usuario -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Usuario MySQL <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="db_user" maxlength="64"
                        value="<?= htmlspecialchars($_SESSION['install_db']['user'] ?? 'root') ?>"
                        placeholder="root"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-gray-500 font-mono"
                        required>
                </div>

                <!-- Contraseña -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-1">Contraseña MySQL</label>
                    <div class="relative">
                        <input type="password" name="db_pass" id="db_pass"
                            value=""
                            placeholder="Contraseña (dejar vacío si no tiene)"
                            class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5 pr-12
                                   focus:outline-none focus:ring-2 focus:ring-emerald-500 placeholder-gray-500 font-mono">
                        <button type="button" onclick="togglePassword('db_pass', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Info box -->
                <div class="md:col-span-2 p-4 bg-blue-900/20 border border-blue-800/50 rounded-xl">
                    <div class="flex gap-3">
                        <i class="bi bi-info-circle-fill text-blue-400 text-xl flex-shrink-0"></i>
                        <div class="text-sm text-blue-200">
                            <p class="font-semibold mb-1">El instalador realizará:</p>
                            <ul class="list-disc list-inside space-y-1 text-blue-300">
                                <li>Crear la base de datos si no existe</li>
                                <li>Ejecutar el schema (32 tablas, 3 triggers, 4 vistas)</li>
                                <li>Insertar datos iniciales (roles, categorías, unidades, marcas)</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex justify-between mt-6 pt-4 border-t border-gray-700">
                <a href="?step=2" class="px-4 py-2.5 text-gray-400 hover:text-white transition-colors flex items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Atrás
                </a>
                <button type="submit" name="continuar"
                    class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold
                           transition-all flex items-center gap-2 shadow-lg">
                    Probar y Continuar <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ─── PASO 4: Credenciales SUNAT ───────────────────── -->
<?php elseif ($step === 4): ?>
<div class="fade-in">
    <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden shadow-xl">
        <div class="bg-gradient-to-r from-orange-900/50 to-red-900/50 px-6 py-5 border-b border-gray-800">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="bi bi-file-earmark-text text-orange-400"></i>
                Configuración SUNAT
            </h2>
            <p class="text-gray-400 text-sm mt-1">Credenciales SOL para emisión de comprobantes electrónicos.</p>
        </div>

        <form method="POST" class="p-6">
            <?php if (!empty($errors)): ?>
            <div class="mb-4 p-4 bg-red-900/30 border border-red-700 rounded-xl">
                <?php foreach ($errors as $e): ?>
                <p class="text-red-300 text-sm"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Aviso de opcionalidad -->
            <div class="mb-4 p-4 bg-yellow-900/20 border border-yellow-700/50 rounded-xl">
                <div class="flex gap-3">
                    <i class="bi bi-lightbulb-fill text-yellow-400 text-xl flex-shrink-0"></i>
                    <p class="text-yellow-200 text-sm">
                        <strong>Esta configuración es opcional.</strong> Puede completarla ahora o después desde
                        <em>Configuración → SUNAT</em> en el sistema. Inicie siempre en modo <strong>Beta</strong> para pruebas.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- Modo SUNAT -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Ambiente SUNAT</label>
                    <div class="flex gap-3">
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="sunat_modo" value="beta"
                                <?= ($_SESSION['install_sunat']['modo'] ?? 'beta') === 'beta' ? 'checked' : '' ?>
                                class="sr-only peer">
                            <div class="p-4 rounded-xl border-2 border-gray-700 peer-checked:border-blue-500
                                        peer-checked:bg-blue-900/20 transition-all text-center">
                                <i class="bi bi-bug text-2xl block mb-1 text-blue-400"></i>
                                <span class="text-sm font-semibold text-gray-200">Beta (Pruebas)</span>
                                <p class="text-xs text-gray-500 mt-1">Recomendado para iniciar</p>
                            </div>
                        </label>
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" name="sunat_modo" value="produccion"
                                <?= ($_SESSION['install_sunat']['modo'] ?? 'beta') === 'produccion' ? 'checked' : '' ?>
                                class="sr-only peer">
                            <div class="p-4 rounded-xl border-2 border-gray-700 peer-checked:border-green-500
                                        peer-checked:bg-green-900/20 transition-all text-center">
                                <i class="bi bi-broadcast text-2xl block mb-1 text-green-400"></i>
                                <span class="text-sm font-semibold text-gray-200">Producción (Real)</span>
                                <p class="text-xs text-gray-500 mt-1">Solo cuando esté listo</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Usuario SOL -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Usuario SOL SUNAT</label>
                    <input type="text" name="sunat_usuario" maxlength="20"
                        value="<?= htmlspecialchars($_SESSION['install_sunat']['usuario_sol'] ?? '') ?>"
                        placeholder="MODDATOS o similar"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-orange-500 placeholder-gray-500 font-mono uppercase">
                </div>

                <!-- Clave SOL -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Clave SOL</label>
                    <div class="relative">
                        <input type="password" name="sunat_clave" id="sunat_clave"
                            placeholder="Clave SOL SUNAT"
                            class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5 pr-12
                                   focus:outline-none focus:ring-2 focus:ring-orange-500 placeholder-gray-500">
                        <button type="button" onclick="togglePassword('sunat_clave', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Se almacenará cifrada con AES-256</p>
                </div>

                <!-- Series -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Serie de Facturas</label>
                    <input type="text" name="serie_factura" maxlength="4"
                        value="<?= htmlspecialchars($_SESSION['install_sunat']['serie_f'] ?? 'F001') ?>"
                        placeholder="F001"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-orange-500 placeholder-gray-500 font-mono uppercase">
                    <p class="text-xs text-gray-500 mt-1">Formato: F001, F002, etc.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Serie de Boletas</label>
                    <input type="text" name="serie_boleta" maxlength="4"
                        value="<?= htmlspecialchars($_SESSION['install_sunat']['serie_b'] ?? 'B001') ?>"
                        placeholder="B001"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-orange-500 placeholder-gray-500 font-mono uppercase">
                    <p class="text-xs text-gray-500 mt-1">Formato: B001, B002, etc.</p>
                </div>

                <!-- Certificado Digital -->
                <div class="md:col-span-2 border-t border-gray-700 pt-4">
                    <h3 class="text-sm font-semibold text-gray-300 mb-3 flex items-center gap-2">
                        <i class="bi bi-patch-check text-orange-400"></i>
                        Certificado Digital (opcional - configurar después)
                    </h3>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Ruta del Certificado (.p12/.pfx)</label>
                    <input type="text" name="cert_path"
                        value="<?= htmlspecialchars($_SESSION['install_sunat']['cert_path'] ?? '') ?>"
                        placeholder="/storage/certificados/certificado.p12"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-orange-500 placeholder-gray-500 font-mono text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Contraseña del Certificado</label>
                    <div class="relative">
                        <input type="password" name="cert_pass" id="cert_pass"
                            placeholder="Contraseña del .p12"
                            class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5 pr-12
                                   focus:outline-none focus:ring-2 focus:ring-orange-500 placeholder-gray-500">
                        <button type="button" onclick="togglePassword('cert_pass', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

            </div>

            <div class="flex justify-between mt-6 pt-4 border-t border-gray-700">
                <a href="?step=3" class="px-4 py-2.5 text-gray-400 hover:text-white transition-colors flex items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Atrás
                </a>
                <button type="submit" name="continuar"
                    class="px-6 py-2.5 bg-orange-600 hover:bg-orange-700 text-white rounded-xl font-semibold
                           transition-all flex items-center gap-2 shadow-lg">
                    Siguiente <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ─── PASO 5: Usuario Administrador ────────────────── -->
<?php elseif ($step === 5): ?>
<div class="fade-in">
    <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden shadow-xl">
        <div class="bg-gradient-to-r from-purple-900/50 to-pink-900/50 px-6 py-5 border-b border-gray-800">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="bi bi-person-lock text-purple-400"></i>
                Cuenta de Administrador
            </h2>
            <p class="text-gray-400 text-sm mt-1">Cree la cuenta principal de acceso al sistema.</p>
        </div>

        <form method="POST" class="p-6" id="formAdmin">
            <?php if (!empty($errors)): ?>
            <div class="mb-4 p-4 bg-red-900/30 border border-red-700 rounded-xl">
                <?php foreach ($errors as $e): ?>
                <p class="text-red-300 text-sm"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <!-- Nombre -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Nombres <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="admin_nombre" maxlength="100"
                        value="<?= htmlspecialchars($_SESSION['install_admin']['nombre'] ?? '') ?>"
                        placeholder="Juan Carlos"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-purple-500 placeholder-gray-500"
                        required>
                </div>

                <!-- Apellidos -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Apellidos <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="admin_apellidos" maxlength="100"
                        value="<?= htmlspecialchars($_SESSION['install_admin']['apellidos'] ?? '') ?>"
                        placeholder="García Pérez"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-purple-500 placeholder-gray-500"
                        required>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Email <span class="text-red-400">*</span>
                    </label>
                    <input type="email" name="admin_email" maxlength="150"
                        value="<?= htmlspecialchars($_SESSION['install_admin']['email'] ?? '') ?>"
                        placeholder="admin@empresa.com"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-purple-500 placeholder-gray-500"
                        required>
                </div>

                <!-- Username -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Usuario de Acceso <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="admin_username" id="admin_username" maxlength="50"
                        value="<?= htmlspecialchars($_SESSION['install_admin']['username'] ?? 'admin') ?>"
                        placeholder="admin"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5
                               focus:outline-none focus:ring-2 focus:ring-purple-500 placeholder-gray-500 font-mono"
                        required>
                    <p class="text-xs text-gray-500 mt-1">Solo letras, números y guión bajo (mínimo 4 caracteres)</p>
                </div>

                <!-- Contraseña -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Contraseña <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="admin_password" id="admin_password"
                            placeholder="Mínimo 8 caracteres"
                            class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5 pr-12
                                   focus:outline-none focus:ring-2 focus:ring-purple-500 placeholder-gray-500"
                            required oninput="updateStrength(this.value)">
                        <button type="button" onclick="togglePassword('admin_password', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <!-- Barra de fortaleza -->
                    <div class="mt-2 h-1.5 bg-gray-700 rounded-full overflow-hidden strength-bar">
                        <div id="strengthBar" class="h-full rounded-full transition-all duration-300 w-0 bg-red-500"></div>
                    </div>
                    <p id="strengthText" class="text-xs mt-1 text-gray-500">Ingrese una contraseña</p>
                </div>

                <!-- Confirmar contraseña -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Confirmar Contraseña <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="admin_password2" id="admin_password2"
                            placeholder="Repita la contraseña"
                            class="w-full bg-gray-800 border border-gray-700 text-white rounded-xl px-4 py-2.5 pr-12
                                   focus:outline-none focus:ring-2 focus:ring-purple-500 placeholder-gray-500"
                            required oninput="checkMatch()">
                        <button type="button" onclick="togglePassword('admin_password2', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <p id="matchText" class="text-xs mt-1 text-gray-500"></p>
                </div>

                <!-- Requisitos de contraseña -->
                <div class="md:col-span-2 p-4 bg-gray-800/50 rounded-xl">
                    <p class="text-xs font-semibold text-gray-400 mb-2">Requisitos de la contraseña:</p>
                    <div class="grid grid-cols-2 gap-1 text-xs">
                        <div id="req-length" class="flex items-center gap-1.5 text-gray-500">
                            <i class="bi bi-circle"></i> Mínimo 8 caracteres
                        </div>
                        <div id="req-upper" class="flex items-center gap-1.5 text-gray-500">
                            <i class="bi bi-circle"></i> Una letra mayúscula
                        </div>
                        <div id="req-number" class="flex items-center gap-1.5 text-gray-500">
                            <i class="bi bi-circle"></i> Un número
                        </div>
                        <div id="req-special" class="flex items-center gap-1.5 text-gray-500">
                            <i class="bi bi-circle"></i> Un carácter especial (recomendado)
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex justify-between mt-6 pt-4 border-t border-gray-700">
                <a href="?step=4" class="px-4 py-2.5 text-gray-400 hover:text-white transition-colors flex items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Atrás
                </a>
                <button type="submit" name="continuar"
                    class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-semibold
                           transition-all flex items-center gap-2 shadow-lg">
                    Siguiente <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ─── PASO 6: Revisión y Confirmación ──────────────── -->
<?php elseif ($step === 6): ?>
<div class="fade-in">
    <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden shadow-xl">
        <div class="bg-gradient-to-r from-gray-800 to-gray-900 px-6 py-5 border-b border-gray-700">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="bi bi-gear-wide-connected text-gray-300"></i>
                Resumen de Instalación
            </h2>
            <p class="text-gray-400 text-sm mt-1">Revise la configuración antes de iniciar la instalación.</p>
        </div>

        <div class="p-6 space-y-4">

            <?php
            $empresa = $_SESSION['install_empresa'] ?? [];
            $db      = $_SESSION['install_db']      ?? [];
            $sunat   = $_SESSION['install_sunat']   ?? [];
            $admin   = $_SESSION['install_admin']   ?? [];

            if (empty($empresa) || empty($db) || empty($admin)):
            ?>
            <div class="p-4 bg-red-900/30 border border-red-700 rounded-xl">
                <p class="text-red-300">Faltan datos de configuración. <a href="?step=1" class="underline">Volver al inicio</a></p>
            </div>
            <?php else: ?>

            <!-- Resumen Empresa -->
            <div class="bg-gray-800/50 rounded-xl overflow-hidden">
                <div class="px-4 py-3 bg-blue-900/30 border-b border-gray-700 flex items-center gap-2">
                    <i class="bi bi-building text-blue-400"></i>
                    <span class="font-semibold text-gray-200">Datos de Empresa</span>
                </div>
                <div class="divide-y divide-gray-700/50">
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">RUC</span>
                        <span class="text-white font-mono"><?= htmlspecialchars($empresa['ruc'] ?? '') ?></span>
                    </div>
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">Razón Social</span>
                        <span class="text-white"><?= htmlspecialchars($empresa['razon_social'] ?? '') ?></span>
                    </div>
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">Dirección</span>
                        <span class="text-white"><?= htmlspecialchars($empresa['direccion'] ?? '') ?></span>
                    </div>
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">IGV</span>
                        <span class="text-white"><?= htmlspecialchars($empresa['igv'] ?? '18') ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Resumen BD -->
            <div class="bg-gray-800/50 rounded-xl overflow-hidden">
                <div class="px-4 py-3 bg-emerald-900/30 border-b border-gray-700 flex items-center gap-2">
                    <i class="bi bi-database text-emerald-400"></i>
                    <span class="font-semibold text-gray-200">Base de Datos</span>
                </div>
                <div class="divide-y divide-gray-700/50">
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">Servidor</span>
                        <span class="text-white font-mono"><?= htmlspecialchars($db['host'] ?? '') ?>:<?= htmlspecialchars($db['port'] ?? '3306') ?></span>
                    </div>
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">Base de Datos</span>
                        <span class="text-white font-mono"><?= htmlspecialchars($db['name'] ?? '') ?></span>
                    </div>
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">Usuario</span>
                        <span class="text-white font-mono"><?= htmlspecialchars($db['user'] ?? '') ?></span>
                    </div>
                </div>
            </div>

            <!-- Resumen SUNAT -->
            <div class="bg-gray-800/50 rounded-xl overflow-hidden">
                <div class="px-4 py-3 bg-orange-900/30 border-b border-gray-700 flex items-center gap-2">
                    <i class="bi bi-file-earmark-text text-orange-400"></i>
                    <span class="font-semibold text-gray-200">SUNAT</span>
                </div>
                <div class="divide-y divide-gray-700/50">
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">Ambiente</span>
                        <span class="<?= ($sunat['modo'] ?? 'beta') === 'beta' ? 'text-blue-400' : 'text-green-400' ?> font-semibold uppercase">
                            <?= htmlspecialchars($sunat['modo'] ?? 'beta') ?>
                        </span>
                    </div>
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">Usuario SOL</span>
                        <span class="text-white font-mono"><?= htmlspecialchars($sunat['usuario_sol'] ?? 'No configurado') ?></span>
                    </div>
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">Series</span>
                        <span class="text-white font-mono"><?= htmlspecialchars(($sunat['serie_f'] ?? 'F001') . ' / ' . ($sunat['serie_b'] ?? 'B001')) ?></span>
                    </div>
                </div>
            </div>

            <!-- Resumen Admin -->
            <div class="bg-gray-800/50 rounded-xl overflow-hidden">
                <div class="px-4 py-3 bg-purple-900/30 border-b border-gray-700 flex items-center gap-2">
                    <i class="bi bi-person-lock text-purple-400"></i>
                    <span class="font-semibold text-gray-200">Administrador</span>
                </div>
                <div class="divide-y divide-gray-700/50">
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">Nombre</span>
                        <span class="text-white"><?= htmlspecialchars(($admin['nombre'] ?? '') . ' ' . ($admin['apellidos'] ?? '')) ?></span>
                    </div>
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">Usuario</span>
                        <span class="text-white font-mono"><?= htmlspecialchars($admin['username'] ?? '') ?></span>
                    </div>
                    <div class="px-4 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-400">Email</span>
                        <span class="text-white"><?= htmlspecialchars($admin['email'] ?? '') ?></span>
                    </div>
                </div>
            </div>

            <!-- Advertencia -->
            <div class="p-4 bg-red-900/20 border border-red-700/50 rounded-xl">
                <div class="flex gap-3">
                    <i class="bi bi-exclamation-triangle-fill text-red-400 text-xl flex-shrink-0"></i>
                    <div class="text-sm text-red-200">
                        <p class="font-semibold mb-1">¡Atención!</p>
                        <p>Al hacer clic en "Instalar ahora" se ejecutará el schema SQL y se configurará la base de datos.
                           Esta acción <strong>no se puede deshacer fácilmente</strong>.
                           Asegúrese de que todos los datos sean correctos.</p>
                    </div>
                </div>
            </div>

            <?php endif; ?>

        </div>

        <div class="px-6 py-4 bg-gray-800/50 border-t border-gray-700 flex justify-between items-center">
            <a href="?step=5" class="px-4 py-2.5 text-gray-400 hover:text-white transition-colors flex items-center gap-2">
                <i class="bi bi-arrow-left"></i> Atrás
            </a>
            <form method="POST" id="formInstalar">
                <button type="submit" name="instalar" id="btnInstalar"
                    onclick="return confirmInstall()"
                    class="px-8 py-3 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700
                           text-white rounded-xl font-bold transition-all flex items-center gap-2 shadow-xl text-lg">
                    <i class="bi bi-rocket-takeoff"></i>
                    Instalar Ahora
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ─── PASO 7: Resultado de Instalación ─────────────── -->
<?php elseif ($step === 7): ?>
<?php
$installLog    = $_SESSION['install_log']    ?? [];
$installResult = $_SESSION['install_result'] ?? false;
?>
<div class="fade-in">
    <div class="bg-gray-900 rounded-2xl border border-gray-800 overflow-hidden shadow-xl">
        <div class="px-6 py-6 border-b border-gray-700 text-center
            <?= $installResult ? 'bg-gradient-to-r from-green-900/50 to-emerald-900/50' : 'bg-gradient-to-r from-red-900/50 to-rose-900/50' ?>">

            <?php if ($installResult): ?>
            <div class="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-2xl">
                <i class="bi bi-check2 text-white text-4xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-white">¡Instalación Exitosa!</h2>
            <p class="text-green-300 mt-2">El sistema de facturación ha sido instalado correctamente.</p>
            <?php else: ?>
            <div class="w-20 h-20 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-2xl">
                <i class="bi bi-x text-white text-4xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-white">Error en la Instalación</h2>
            <p class="text-red-300 mt-2">Ocurrieron errores durante la instalación. Revise el registro.</p>
            <?php endif; ?>
        </div>

        <div class="p-6">

            <!-- Log de instalación -->
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-gray-400 mb-3 flex items-center gap-2">
                    <i class="bi bi-terminal"></i> Registro de Instalación
                </h3>
                <div class="bg-gray-950 rounded-xl p-4 space-y-1.5 max-h-64 overflow-y-auto font-mono text-xs">
                    <?php foreach ($installLog as $log): ?>
                    <div class="flex items-start gap-2">
                        <?php if ($log['ok']): ?>
                        <i class="bi bi-check-circle-fill text-green-400 flex-shrink-0 mt-0.5"></i>
                        <span class="text-green-300"><?= htmlspecialchars($log['msg']) ?></span>
                        <?php else: ?>
                        <i class="bi bi-x-circle-fill text-red-400 flex-shrink-0 mt-0.5"></i>
                        <span class="text-red-300"><?= htmlspecialchars($log['msg']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($installResult): ?>
            <!-- Próximos pasos -->
            <div class="mb-6 p-4 bg-blue-900/20 border border-blue-800/50 rounded-xl">
                <h3 class="text-sm font-semibold text-blue-300 mb-3 flex items-center gap-2">
                    <i class="bi bi-list-task"></i> Próximos Pasos Recomendados
                </h3>
                <ol class="space-y-2 text-sm text-blue-200">
                    <li class="flex items-start gap-2">
                        <span class="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">1</span>
                        Acceder al sistema con las credenciales del administrador configuradas
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">2</span>
                        Ir a <strong>Configuración → Empresa</strong> y subir el logo
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">3</span>
                        Ir a <strong>Configuración → SUNAT</strong> y subir el certificado digital (.p12)
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">4</span>
                        Probar emisión de comprobantes en modo Beta antes de activar Producción
                    </li>
                    <li class="flex items-start gap-2">
                        <span class="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0">5</span>
                        Registrar productos, precios y stock inicial en el catálogo
                    </li>
                </ol>
            </div>

            <!-- Credenciales -->
            <div class="p-4 bg-gray-800/50 rounded-xl border border-gray-700">
                <h3 class="text-sm font-semibold text-gray-300 mb-3 flex items-center gap-2">
                    <i class="bi bi-key text-yellow-400"></i>
                    Datos de Acceso al Sistema
                </h3>
                <div class="text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-400">URL del Sistema:</span>
                        <a href="<?= htmlspecialchars($_SESSION['install_empresa']['app_url'] ?? '../public/index.php') ?>"
                            class="text-blue-400 hover:underline font-mono text-xs">
                            <?= htmlspecialchars($_SESSION['install_empresa']['app_url'] ?? '../public/') ?>
                        </a>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Usuario Admin:</span>
                        <span class="text-white font-mono"><?= htmlspecialchars($_SESSION['install_admin']['username'] ?? 'admin') ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Contraseña:</span>
                        <span class="text-gray-400 italic">La que ingresó en el paso 5</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="<?= htmlspecialchars($_SESSION['install_empresa']['app_url'] ?? '../public/index.php') ?>"
                    class="px-8 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700
                           text-white rounded-xl font-bold text-center transition-all shadow-xl flex items-center justify-center gap-2">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Ir al Sistema
                </a>
            </div>

            <?php else: ?>
            <div class="mt-4 flex gap-3">
                <a href="?step=1" class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl
                                          font-semibold text-center transition-all">
                    Intentar de Nuevo
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php endif; ?>

</main>

<!-- Footer -->
<footer class="mt-12 py-6 border-t border-gray-800">
    <p class="text-center text-xs text-gray-600">
        Sistema de Facturación Electrónica — Pucallpa, Perú |
        PHP <?= PHP_VERSION ?> |
        Versión <?= INSTALL_VERSION ?>
    </p>
</footer>

<script>
/**
 * ─── JavaScript del Instalador ──────────────────────────
 */

// Mostrar/ocultar contraseña
function togglePassword(fieldId, btn) {
    const field = document.getElementById(fieldId);
    const icon  = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        field.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// Calcular fortaleza de contraseña
function updateStrength(password) {
    const bar  = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    if (!bar || !text) return;

    let score = 0;
    const checks = {
        length:  password.length >= 8,
        upper:   /[A-Z]/.test(password),
        lower:   /[a-z]/.test(password),
        number:  /[0-9]/.test(password),
        special: /[^a-zA-Z0-9]/.test(password),
        long:    password.length >= 12,
    };

    score += checks.length  ? 20 : 0;
    score += checks.upper   ? 15 : 0;
    score += checks.lower   ? 10 : 0;
    score += checks.number  ? 20 : 0;
    score += checks.special ? 20 : 0;
    score += checks.long    ? 15 : 0;

    bar.style.width = Math.min(score, 100) + '%';
    if (score < 30) {
        bar.className = 'h-full rounded-full transition-all duration-300 bg-red-500';
        text.textContent = 'Contraseña muy débil';
        text.className = 'text-xs mt-1 text-red-400';
    } else if (score < 60) {
        bar.className = 'h-full rounded-full transition-all duration-300 bg-yellow-500';
        text.textContent = 'Contraseña débil';
        text.className = 'text-xs mt-1 text-yellow-400';
    } else if (score < 80) {
        bar.className = 'h-full rounded-full transition-all duration-300 bg-blue-500';
        text.textContent = 'Contraseña aceptable';
        text.className = 'text-xs mt-1 text-blue-400';
    } else {
        bar.className = 'h-full rounded-full transition-all duration-300 bg-green-500';
        text.textContent = 'Contraseña fuerte ✓';
        text.className = 'text-xs mt-1 text-green-400';
    }

    // Actualizar indicadores de requisitos
    updateReq('req-length',  checks.length);
    updateReq('req-upper',   checks.upper);
    updateReq('req-number',  checks.number);
    updateReq('req-special', checks.special);
}

function updateReq(id, ok) {
    const el = document.getElementById(id);
    if (!el) return;
    const icon = el.querySelector('i');
    if (ok) {
        icon.className = 'bi bi-check-circle-fill';
        el.className = el.className.replace('text-gray-500', '') + ' text-green-400';
    } else {
        icon.className = 'bi bi-circle';
        el.className = 'flex items-center gap-1.5 text-gray-500';
    }
}

// Verificar coincidencia de contraseñas
function checkMatch() {
    const p1   = document.getElementById('admin_password');
    const p2   = document.getElementById('admin_password2');
    const text = document.getElementById('matchText');
    if (!p1 || !p2 || !text) return;
    if (p2.value === '') {
        text.textContent = '';
        return;
    }
    if (p1.value === p2.value) {
        text.textContent = '✓ Las contraseñas coinciden';
        text.className   = 'text-xs mt-1 text-green-400';
    } else {
        text.textContent = '✗ Las contraseñas no coinciden';
        text.className   = 'text-xs mt-1 text-red-400';
    }
}

// Confirmar instalación
function confirmInstall() {
    return confirm(
        '¿Está seguro de que desea iniciar la instalación?\n\n' +
        'Esta acción creará las tablas y configurará la base de datos.\n\n' +
        'Haga clic en "Aceptar" para continuar.'
    );
}

// Mostrar spinner al instalar
document.addEventListener('DOMContentLoaded', function() {
    const formInstalar = document.getElementById('formInstalar');
    if (formInstalar) {
        formInstalar.addEventListener('submit', function() {
            const btn = document.getElementById('btnInstalar');
            if (btn) {
                setTimeout(function() {
                    btn.disabled = true;
                    btn.innerHTML = '<svg class="spinner w-5 h-5" viewBox="0 0 24 24" fill="none">' +
                        '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                        '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                        '</svg> Instalando...';
                }, 100);
            }
        });
    }

    // Validación de username en tiempo real
    const usernameInput = document.getElementById('admin_username');
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '').toLowerCase();
        });
    }

    // Consultar RUC (simulado - se conectaría a API real)
    const btnRuc = document.getElementById('btnConsultarRUC');
    if (btnRuc) {
        btnRuc.addEventListener('click', function() {
            const ruc = document.getElementById('ruc').value.trim();
            if (ruc.length !== 11) {
                alert('Ingrese un RUC de 11 dígitos primero.');
                return;
            }
            // Aquí se llamaría a una API de consulta RUC (SUNAT o terceros)
            alert('Consulta SUNAT: Por favor ingrese manualmente la razón social del RUC ' + ruc + '.\n\n' +
                  'Puede usar el portal SUNAT: https://e-consultaruc.sunat.gob.pe/');
        });
    }

    // Auto-uppercase para series
    ['serie_factura', 'serie_boleta', 'sunat_usuario'].forEach(function(name) {
        const el = document.querySelector('[name="' + name + '"]');
        if (el) el.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
    });
});
</script>
</body>
</html>
