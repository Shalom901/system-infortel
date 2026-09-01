<?php

declare(strict_types=1);

/**
 * =============================================================
 * CONFIGURACIÓN GENERAL DE LA APLICACIÓN
 * Sistema de Facturación - Empresa de Informática Pucallpa
 * =============================================================
 *
 * Define constantes globales, configuración de zona horaria,
 * roles de usuario, estados de comprobantes y tipos de documentos.
 */

// -----------------------------------------------
// ZONA HORARIA Y CONFIGURACIÓN REGIONAL
// -----------------------------------------------
date_default_timezone_set('America/Lima');
setlocale(LC_MONETARY, 'es_PE.UTF-8');
setlocale(LC_TIME, 'es_PE.UTF-8');

// -----------------------------------------------
// INFORMACIÓN DEL SISTEMA
// -----------------------------------------------
define('APP_VERSION',     '1.0.0');
define('APP_YEAR',        (int) date('Y'));
define('APP_NAME',        $_ENV['APP_NAME'] ?? 'Sistema de Facturación');
define('APP_URL',         rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'));
define('APP_ENV',         $_ENV['APP_ENV']   ?? 'production');
define('APP_DEBUG',       filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('APP_KEY',         $_ENV['APP_KEY']   ?? '');

// -----------------------------------------------
// RUTAS DE ALMACENAMIENTO
// -----------------------------------------------
define('ROOT_PATH',       dirname(__DIR__));
define('APP_PATH',        ROOT_PATH . '/app');
define('CONFIG_PATH',     ROOT_PATH . '/config');
define('PUBLIC_PATH',     ROOT_PATH . '/public');
define('STORAGE_PATH',    ROOT_PATH . '/storage');
define('VIEWS_PATH',      APP_PATH  . '/Views');
define('LOGS_PATH',       STORAGE_PATH . '/logs');
define('UPLOADS_PATH',    STORAGE_PATH . '/uploads');

// Crear directorios de almacenamiento si no existen
foreach ([LOGS_PATH, UPLOADS_PATH, STORAGE_PATH . '/certificados', STORAGE_PATH . '/comprobantes'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Cargar configuración SUNAT centralizada
require_once CONFIG_PATH . '/sunat.php';

// -----------------------------------------------
// ROLES DE USUARIO
// -----------------------------------------------
define('ROLE_ADMIN',      1);  // Administrador: acceso total
define('ROLE_VENDEDOR',   2);  // Vendedor: acceso a POS y ventas
define('ROLE_CONTADOR',   3);  // Contador: acceso a reportes y contabilidad
define('ROLE_ALMACEN',    4);  // Almacén: acceso a inventario y compras

/** Nombres descriptivos de los roles */
define('ROLES', [
    ROLE_ADMIN    => 'Administrador',
    ROLE_VENDEDOR => 'Vendedor',
    ROLE_CONTADOR => 'Contador',
    ROLE_ALMACEN  => 'Almacén',
]);

// -----------------------------------------------
// ESTADOS DE COMPROBANTES
// -----------------------------------------------
define('ESTADO_EMITIDO',    1);  // Comprobante emitido localmente
define('ESTADO_ENVIADO',    2);  // Enviado a SUNAT
define('ESTADO_ACEPTADO',   3);  // Aceptado por SUNAT
define('ESTADO_RECHAZADO',  4);  // Rechazado por SUNAT
define('ESTADO_ANULADO',    5);  // Anulado / Comunicación de baja enviada
define('ESTADO_BORRADOR',   6);  // Borrador (no emitido)

/** Nombres y colores de estado para la UI */
define('ESTADOS_COMPROBANTE', [
    ESTADO_EMITIDO   => ['nombre' => 'Emitido',   'color' => 'secondary', 'icono' => 'file-text'],
    ESTADO_ENVIADO   => ['nombre' => 'Enviado',   'color' => 'info',      'icono' => 'send'],
    ESTADO_ACEPTADO  => ['nombre' => 'Aceptado',  'color' => 'success',   'icono' => 'check-circle'],
    ESTADO_RECHAZADO => ['nombre' => 'Rechazado', 'color' => 'danger',    'icono' => 'x-circle'],
    ESTADO_ANULADO   => ['nombre' => 'Anulado',   'color' => 'warning',   'icono' => 'slash'],
    ESTADO_BORRADOR  => ['nombre' => 'Borrador',  'color' => 'light',     'icono' => 'edit-2'],
]);

// -----------------------------------------------
// TIPOS DE COMPROBANTE SUNAT
// -----------------------------------------------
define('TIPOS_COMPROBANTE', [
    '01' => [
        'nombre'      => 'Factura',
        'prefijo'     => 'F',
        'serie_min'   => 'F001',
        'requiere_ruc'=> true,
        'electronico' => true,
    ],
    '03' => [
        'nombre'      => 'Boleta de Venta',
        'prefijo'     => 'B',
        'serie_min'   => 'B001',
        'requiere_ruc'=> false,
        'electronico' => true,
    ],
    '07' => [
        'nombre'      => 'Nota de Crédito',
        'prefijo'     => 'FC',
        'serie_min'   => 'FC01',
        'requiere_ruc'=> false,
        'electronico' => true,
    ],
    '08' => [
        'nombre'      => 'Nota de Débito',
        'prefijo'     => 'FD',
        'serie_min'   => 'FD01',
        'requiere_ruc'=> false,
        'electronico' => true,
    ],
    'NV' => [
        'nombre'      => 'Nota de Venta',
        'prefijo'     => 'NV',
        'serie_min'   => 'NV01',
        'requiere_ruc'=> false,
        'electronico' => false,  // No es comprobante electrónico SUNAT
    ],
    'CQ' => [
        'nombre'      => 'Cotización',
        'prefijo'     => 'CQ',
        'serie_min'   => 'CQ01',
        'requiere_ruc'=> false,
        'electronico' => false,
    ],
]);

// -----------------------------------------------
// TIPOS DE MONEDA
// -----------------------------------------------
define('MONEDAS', [
    'PEN' => [
        'nombre'   => 'Sol Peruano',
        'simbolo'  => 'S/',
        'decimales'=> 2,
        'codigo_sunat' => 'PEN',
    ],
    'USD' => [
        'nombre'   => 'Dólar Americano',
        'simbolo'  => '$',
        'decimales'=> 2,
        'codigo_sunat' => 'USD',
    ],
]);

define('TIPO_CAMBIO_USD', (float) ($_ENV['TIPO_CAMBIO_USD'] ?? 3.85));

// -----------------------------------------------
// MÉTODOS DE PAGO
// -----------------------------------------------
define('METODOS_PAGO', [
    'efectivo'       => ['nombre' => 'Efectivo',             'icono' => 'dollar-sign'],
    'tarjeta_debito' => ['nombre' => 'Tarjeta de Débito',    'icono' => 'credit-card'],
    'tarjeta_credito'=> ['nombre' => 'Tarjeta de Crédito',   'icono' => 'credit-card'],
    'transferencia'  => ['nombre' => 'Transferencia Bancaria','icono' => 'repeat'],
    'yape'           => ['nombre' => 'Yape',                  'icono' => 'smartphone'],
    'plin'           => ['nombre' => 'Plin',                  'icono' => 'smartphone'],
    'deposito'       => ['nombre' => 'Depósito Bancario',     'icono' => 'home'],
    'credito'        => ['nombre' => 'Al Crédito',            'icono' => 'clock'],
]);

// -----------------------------------------------
// TIPOS DE DOCUMENTO DE IDENTIDAD
// -----------------------------------------------
define('TIPOS_DOCUMENTO', [
    '1'  => ['nombre' => 'DNI',              'longitud' => 8,  'solo_numeros' => true],
    '6'  => ['nombre' => 'RUC',              'longitud' => 11, 'solo_numeros' => true],
    '4'  => ['nombre' => 'Carnet Extranjería','longitud' => 12, 'solo_numeros' => false],
    '7'  => ['nombre' => 'Pasaporte',        'longitud' => 12, 'solo_numeros' => false],
    '-'  => ['nombre' => 'Sin Documento',    'longitud' => 0,  'solo_numeros' => false],
]);

// -----------------------------------------------
// CONFIGURACIÓN DE IMPUESTOS
// -----------------------------------------------
define('IGV_PORCENTAJE',    18.00);   // IGV 18%
define('IGV_FACTOR',        0.18);    // Factor IGV
define('ICBPER_MONTO',      0.20);    // Impuesto al consumo de bolsas plásticas S/ 0.20
define('ISC_PORCENTAJE',    0.00);    // ISC (varía por producto)

// -----------------------------------------------
// CONFIGURACIÓN DE PAGINACIÓN
// -----------------------------------------------
define('ITEMS_POR_PAGINA',  20);      // Registros por página en listados
define('MAX_ITEMS_EXPORT',  5000);    // Máximo de registros para exportar

// -----------------------------------------------
// CONFIGURACIÓN DE SESIÓN SEGURA
// -----------------------------------------------
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');

// Activar cookie segura sólo en producción con HTTPS
if (APP_ENV === 'production') {
    ini_set('session.cookie_secure', '1');
}

// Duración de sesión (en segundos): 2 horas por defecto
$sessionLifetime = (int) ($_ENV['SESSION_LIFETIME'] ?? 120) * 60;
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
ini_set('session.cookie_lifetime', '0'); // Expirar al cerrar el navegador

// -----------------------------------------------
// CONFIGURACIÓN DE DISPLAY DE ERRORES
// -----------------------------------------------
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOGS_PATH . '/php_errors.log');
}
