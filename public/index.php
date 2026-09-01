<?php
declare(strict_types=1);

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ============================================================
// MANEJO DE EXCEPCIONES Y CORS (PREFLIGHT)
// ============================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function custom_exception_handler($exception) {
    // Detect AJAX requests (X-Requested-With header or Accept: application/json)
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
              && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $isJsonRequest = !empty($_SERVER['HTTP_ACCEPT']) 
                     && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    $isApiRoute = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
    
    if ($isAjax || $isJsonRequest || $isApiRoute) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'message' => 'Error interno: ' . $exception->getMessage(),
            'error'   => $exception->getMessage(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
        ]);
    } else {
        echo "EXCEPTION: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine() . "<br>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
    }
    exit;
}
set_exception_handler('custom_exception_handler');

function custom_error_handler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    echo "\n<!-- PHP ERROR [$errno]: " . htmlspecialchars($errstr) . " in $errfile on line $errline -->\n";
    if ($errno === E_USER_ERROR) {
        exit(1);
    }
    return true;
}
set_error_handler("custom_error_handler");

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\Router;
use App\Core\Request;
use App\Core\Response;

// ============================================================
// CARGAR VARIABLES DE ENTORNO
// ============================================================
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// ============================================================
// INICIAR SESIÓN SEGURA
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ============================================================
// INICIALIZAR ROUTER
// ============================================================
$request  = new Request();
$response = new Response();
$router   = new Router($request, $response);

// ============================================================
// REGISTRO DE RUTAS
// ============================================================

// --- Autenticación ---
$router->get('/',                         'AuthController@showLoginForm');
$router->get('/login',                    'AuthController@showLoginForm');
$router->post('/login',                   'AuthController@login');
$router->get('/logout',                   'AuthController@logout');

// --- Dashboard ---
$router->get('/dashboard',               'DashboardController@index');
$router->get('/dashboard/live-data',     'DashboardController@liveData');

// --- Catálogo: Productos ---
$router->get('/productos',               'ProductController@index');
$router->get('/productos/crear',         'ProductController@create');
$router->post('/productos/guardar',      'ProductController@store');
$router->get('/productos/{id}',          'ProductController@show');
$router->get('/productos/{id}/editar',   'ProductController@edit');
$router->post('/productos/{id}/actualizar', 'ProductController@update');
$router->post('/productos/{id}/eliminar',   'ProductController@delete');
$router->post('/productos/exportar-pdf',    'ProductController@exportPDF');
$router->post('/productos/exportar-excel',  'ProductController@exportExcel');

// --- Catálogo: Categorías ---
$router->get('/categorias',              'CategoryController@index');
$router->post('/categorias/guardar',     'CategoryController@store');
$router->post('/categorias/actualizar',  'CategoryController@update');
$router->post('/categorias/eliminar',    'CategoryController@delete');

// --- Ventas ---
$router->get('/ventas',                  'SaleController@index');
$router->post('/ventas/guardar',         'SaleController@store');
$router->post('/ventas/anular',          'SaleController@cancel');

// --- Compras ---
$router->get('/compras',                 'PurchaseController@index');
$router->post('/compras/guardar',        'PurchaseController@store');

// --- Cotizaciones ---
$router->get('/cotizaciones',            'QuoteController@index');
$router->get('/cotizaciones/crear',      'QuoteController@crear');
$router->post('/cotizaciones/guardar',   'QuoteController@store');
$router->post('/cotizaciones/convertir', 'QuoteController@convertToSale');
$router->get('/cotizaciones/pdf/{id}',   'QuoteController@printPDF');

// --- Caja ---
$router->get('/caja',                    'CajaController@index');
$router->post('/caja/abrir',             'CajaController@abrir');
$router->post('/caja/cerrar',            'CajaController@cerrar');

// --- Proveedores ---
$router->get('/proveedores',             'SupplierController@index');
$router->get('/proveedores/crear',       'SupplierController@create');
$router->post('/proveedores/guardar',    'SupplierController@store');
$router->get('/proveedores/{id}/editar', 'SupplierController@edit');
$router->post('/proveedores/{id}/actualizar', 'SupplierController@update');
$router->post('/proveedores/{id}/eliminar',   'SupplierController@delete');

// --- Usuarios ---
$router->get('/usuarios',                'UserController@index');
$router->get('/usuarios/crear',          'UserController@create');
$router->post('/usuarios/guardar',       'UserController@store');
$router->get('/usuarios/{id}/editar',    'UserController@edit');
$router->post('/usuarios/{id}/actualizar',    'UserController@update');
$router->post('/usuarios/{id}/eliminar',      'UserController@delete');
$router->post('/usuario/tema',           'UserController@updateTheme');

// --- Reportes ---
$router->get('/reportes',                'ReportController@index');

// --- SUNAT ---
$router->get('/sunat/config',                    'SunatController@config', ['auth']);
$router->post('/sunat/actualizar',               'SunatController@updateConfig', ['auth']);
$router->post('/sunat/enviar',                   'SunatController@enviar', ['auth']);
$router->get('/sunat/enviar',                    'SunatController@enviar', ['auth']);
$router->post('/sunat/enviar-pendientes',        'SunatController@enviarPendientes', ['auth']);
$router->get('/sunat/consultar-ticket',          'SunatController@consultarTicket', ['auth']);
$router->post('/sunat/consultar-tickets',        'SunatController@consultarTicketsPendientes', ['auth']);
$router->post('/sunat/reintentar',               'SunatController@reintentar', ['auth']);
$router->get('/sunat/cert-info',                 'SunatController@certInfo', ['auth']);
$router->get('/sunat/test-connection',           'SunatController@testConnection', ['auth']);

// --- POS ---
$router->get('/pos',                     'POSController@index');
$router->post('/pos/procesar-venta',     'POSController@procesarVenta');
$router->get('/pos/search-product',      'POSController@searchProduct');
$router->get('/pos/get-product/{id}',    'POSController@getProduct');
$router->get('/pos/search-client',       'POSController@searchClient');
$router->get('/pos/tipo-cambio',         'POSController@getExchangeRate');
$router->get('/pos/ticket/{id}',         'POSController@printTicket');
$router->get('/pos/pdf/{id}',            'POSController@printPDF');

// --- API Auxiliares ---
$router->get('/api/clientes/buscar',     'POSController@searchClient');
$router->get('/api/clientes/buscar-documento', 'POSController@lookupClientDocument');
$router->post('/api/clientes/guardar',    'POSController@createClient');
$router->get('/api/productos/buscar',    'POSController@searchProduct');
$router->get('/api/proveedores/buscar-ruc', 'SupplierController@getByRuc');

// ============================================================
// NUEVAS RUTAS AÑADIDAS: PΛRADISE OS
// ============================================================
// Motor de Telemetría y Alertas
$router->get('/api/notificaciones/todas', 'NotificationController@fetchAll');

// Motor de Inteligencia Artificial (Inferencia Local)
$router->post('/api/v1/ai/parse-intent',  'AiController@parseFacturacionIntent');
$router->get('/api/v1/ai/health',         'AiController@checkOllamaStatus');

// ============================================================
// DESPACHAR RUTA
// ============================================================
$router->dispatch();