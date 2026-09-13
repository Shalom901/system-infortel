<?php

declare(strict_types=1);

/**
 * =============================================================
 * ROUTER - ENRUTADOR HTTP
 * Sistema de Facturación - Empresa de Informática Pucallpa
 * =============================================================
 *
 * Router completo con soporte para:
 *   - Métodos GET y POST
 *   - Parámetros dinámicos: /productos/{id}
 *   - Middleware por ruta o grupo
 *   - Grupos de rutas con prefijo
 *   - Página 404 personalizada
 */

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\RateLimitMiddleware;

class Router
{
    /**
     * Registro de todas las rutas definidas.
     * Formato: [['method' => 'GET', 'path' => '/login', 'callback' => ..., 'middleware' => [...]], ...]
     *
     * @var array<int, array{method: string, path: string, callback: mixed, middleware: string[]}>
     */
    private array $routes = [];

    /**
     * Prefijo actual para grupos de rutas.
     */
    private string $groupPrefix = '';

    /**
     * Middleware global aplicado a las rutas del grupo actual.
     *
     * @var string[]
     */
    private array $groupMiddleware = [];

    /**
     * Registrar una ruta GET.
     *
     * @param  string          $path       Patrón de la ruta (ej: '/productos/{id}')
     * @param  callable|string $callback   Función o 'ControladorClase@metodo'
     * @param  string[]        $middleware Lista de nombres de middleware
     * @return self                        Para encadenamiento fluido
     */
    public function get(string $path, callable|string $callback, array $middleware = []): self
    {
        $this->addRoute('GET', $path, $callback, $middleware);
        return $this;
    }

    /**
     * Registrar una ruta POST.
     *
     * @param  string          $path       Patrón de la ruta
     * @param  callable|string $callback   Función o 'ControladorClase@metodo'
     * @param  string[]        $middleware Lista de nombres de middleware
     * @return self
     */
    public function post(string $path, callable|string $callback, array $middleware = []): self
    {
        $this->addRoute('POST', $path, $callback, $middleware);
        return $this;
    }

    /**
     * Registrar rutas GET y POST para el mismo path (formularios).
     *
     * @param  string          $path       Patrón de la ruta
     * @param  callable|string $getCallback  Callback para GET
     * @param  callable|string $postCallback Callback para POST
     * @param  string[]        $middleware   Lista de middleware
     */
    public function form(string $path, callable|string $getCallback, callable|string $postCallback, array $middleware = []): void
    {
        $this->get($path, $getCallback, $middleware);
        $this->post($path, $postCallback, $middleware);
    }

    /**
     * Definir un grupo de rutas con prefijo y middleware compartido.
     *
     * @param  string   $prefix     Prefijo de URL para todas las rutas del grupo
     * @param  callable $callback   Función donde se definen las rutas del grupo
     * @param  string[] $middleware Middleware aplicado a todas las rutas del grupo
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        // Guardar estado anterior
        $prefijoPrevio    = $this->groupPrefix;
        $middlewarePrevio = $this->groupMiddleware;

        // Establecer nuevo contexto de grupo
        $this->groupPrefix    = $prefijoPrevio . $prefix;
        $this->groupMiddleware = array_merge($middlewarePrevio, $middleware);

        // Ejecutar el callback con el contexto del grupo
        $callback($this);

        // Restaurar estado anterior (permite grupos anidados)
        $this->groupPrefix    = $prefijoPrevio;
        $this->groupMiddleware = $middlewarePrevio;
    }

    /**
     * Procesar la solicitud HTTP actual y despachar a la ruta correspondiente.
     */
    public function dispatch(): void
    {
        $metodo  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri     = $this->parseUri();

        // Iterar las rutas registradas buscando coincidencia
        foreach ($this->routes as $ruta) {
            if ($ruta['method'] !== $metodo) {
                continue;
            }

            $params = $this->match($ruta['path'], $uri);

            if ($params !== false) {
                // Ejecutar middleware de la ruta
                $this->executeMiddleware($ruta['middleware']);

                // Despachar el callback con los parámetros extraídos
                $this->executeCallback($ruta['callback'], $params);
                return;
            }
        }

        // Ninguna ruta coincidió: mostrar 404
        $this->notFound($uri);
    }

    /**
     * Agregar una ruta al registro.
     *
     * @param string          $method     Método HTTP
     * @param string          $path       Patrón de la ruta
     * @param callable|string $callback   Callback de la ruta
     * @param string[]        $middleware Middleware específico de la ruta
     */
    private function addRoute(string $method, string $path, callable|string $callback, array $middleware): void
    {
        // Combinar prefijo del grupo con la ruta individual
        $rutaCompleta = $this->groupPrefix . '/' . ltrim($path, '/');
        $rutaCompleta = '/' . ltrim($rutaCompleta, '/');
        // Normalizar barras dobles
        $rutaCompleta = preg_replace('#/+#', '/', $rutaCompleta);

        $this->routes[] = [
            'method'     => strtoupper($method),
            'path'       => $rutaCompleta,
            'callback'   => $callback,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    /**
     * Intentar hacer match entre el patrón de ruta y la URI actual.
     * Extrae los parámetros dinámicos si los hay.
     *
     * @param  string      $pattern Patrón de la ruta (ej: '/productos/{id}/editar')
     * @param  string      $uri     URI actual (ej: '/productos/42/editar')
     * @return array|false          Array con parámetros extraídos o false si no coincide
     */
    private function match(string $pattern, string $uri): array|false
    {
        // Escapar el patrón para regex y reemplazar {param} por grupos de captura
        $regexPattern = preg_replace(
            '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}/',
            '([^/]+)',
            preg_quote($pattern, '#')
        );

        if (!preg_match("#^{$regexPattern}$#", $uri, $matches)) {
            return false;
        }

        // Extraer nombres de los parámetros dinámicos del patrón original
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $pattern, $paramNames);
        $nombres = $paramNames[1];

        // Quitar el primer match completo y mapear nombres → valores
        array_shift($matches);

        return array_combine($nombres, $matches) ?: [];
    }

    /**
     * Obtener la URI actual limpia (sin query string, con barra inicial).
     *
     * @return string URI normalizada
     */
    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remover query string
        if (str_contains($uri, '?')) {
            $uri = strstr($uri, '?', true);
        }
        $uri = urldecode($uri);

        // Remover prefijo de subcarpeta (XAMPP htdocs u otros)
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        
        // Si el scriptName es /facturacion-pucallpa/public, pero REQUEST_URI es /facturacion-pucallpa/ventas, 
        // necesitamos un basepath más inteligente.
        $baseUri = parse_url($_ENV['APP_URL'] ?? '', PHP_URL_PATH) ?? '';
        $baseUri = rtrim($baseUri, '/');

        // Intentar limpiar con el APP_URL base
        if ($baseUri !== '' && str_starts_with($uri, $baseUri)) {
            $uri = substr($uri, strlen($baseUri));
        } elseif ($scriptName !== '/' && $scriptName !== '\\' && str_starts_with($uri, $scriptName)) {
            // Fallback al dirname original
            $uri = substr($uri, strlen($scriptName));
        }

        // Si después de todo eso, el usuario usó un htaccess en la raíz que omite /public,
        // pero el APP_URL no estaba bien configurado, intentamos quitar el directorio padre del script.
        $parentDir = dirname($scriptName);
        if ($parentDir !== '/' && $parentDir !== '\\' && str_starts_with($uri, $parentDir)) {
             // Solo lo hacemos si el uri no empieza por $scriptName, para no sobre-recortar
             if (!str_starts_with($_SERVER['REQUEST_URI'] ?? '', $scriptName)) {
                 $uri = substr($uri, strlen($parentDir));
             }
        }

        // Asegurar barra inicial
        $uri = '/' . ltrim($uri, '/');

        // Normalizar barras dobles
        return preg_replace('#/+#', '/', $uri) ?: '/';
    }

    

    /**
     * Ejecutar los middlewares asociados a la ruta.
     *
     * @param  string[] $middlewareList Lista de nombres de middleware
     */
    private function executeMiddleware(array $middlewareList): void
    {
        foreach ($middlewareList as $mw) {
            match (true) {
                // Middleware de autenticación
                $mw === 'auth'
                    => (new AuthMiddleware())->handle(),

                // Middleware CSRF
                $mw === 'csrf'
                    => (new CsrfMiddleware())->handle(),

                // Middleware de rol admin
                $mw === 'admin'
                    => (new RoleMiddleware())->handle('admin'),

                // Middleware de rol vendedor o admin
                $mw === 'vendedor'
                    => (new RoleMiddleware())->handle(['admin', 'vendedor']),

                // Middleware de rol contador
                $mw === 'contador'
                    => (new RoleMiddleware())->handle(['admin', 'contador']),

                // Middleware de rol almacén
                $mw === 'almacen'
                    => (new RoleMiddleware())->handle(['admin', 'almacen']),

                // Middleware de rate limit para login
                $mw === 'throttle:login'
                    => (new RateLimitMiddleware())->handle('login'),

                // Rutas de Gestión SUNAT
                $mw === 'sunat.listado'
                    => (new RoleMiddleware())->handle(['admin', 'vendedor']),

                // Middleware con parámetro de rol personalizado: 'role:admin,vendedor'
                str_starts_with($mw, 'role:')
                    => $this->handleRoleMiddleware(substr($mw, 5)),

                // Middleware desconocido: ignorar en producción, advertir en desarrollo
                default => $this->handleUnknownMiddleware($mw),
            };
        }
    }

    /**
     * Procesar middleware de rol con roles personalizados separados por coma.
     *
     * @param string $rolesString Roles separados por coma (ej: 'admin,vendedor')
     */
    private function handleRoleMiddleware(string $rolesString): void
    {
        $roles = array_map('trim', explode(',', $rolesString));
        (new RoleMiddleware())->handle($roles);
    }

    /**
     * Manejar un middleware desconocido.
     *
     * @param string $name Nombre del middleware no reconocido
     */
    private function handleUnknownMiddleware(string $name): void
    {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            trigger_error("Middleware '{$name}' no reconocido en el Router.", E_USER_WARNING);
        }
    }

    /**
     * Ejecutar el callback de la ruta con los parámetros extraídos.
     *
     * @param  callable|string $callback Función callable o 'Clase@metodo'
     * @param  array           $params   Parámetros dinámicos extraídos de la URI
     */
    private function executeCallback(callable|string $callback, array $params): void
    {
        if (is_callable($callback)) {
            // Llamar directamente como función
            call_user_func_array($callback, array_values($params));
            return;
        }

        if (is_string($callback) && str_contains($callback, '@')) {
            [$clase, $metodo] = explode('@', $callback, 2);

            // Agregar namespace completo si no lo tiene
            if (!str_contains($clase, '\\')) {
                $clase = "App\\Controllers\\{$clase}";
            }

            if (!class_exists($clase)) {
                throw new \RuntimeException("Controlador no encontrado: {$clase}");
            }

            $db = \Config\Database::getInstance()->getConnection();
            $controlador = new $clase($db);

            if (!method_exists($controlador, $metodo)) {
                throw new \RuntimeException("Método no encontrado: {$clase}::{$metodo}");
            }

            call_user_func_array([$controlador, $metodo], array_values($params));
            return;
        }

        throw new \RuntimeException("Callback de ruta inválido: " . (is_string($callback) ? $callback : gettype($callback)));
    }

    /**
     * Mostrar la página de error 404 personalizada.
     *
     * @param string $uri URI que no fue encontrada
     */
    private function notFound(string $uri): void
    {
        http_response_code(404);

        $viewPath = defined('VIEWS_PATH') ? VIEWS_PATH . '/errors/404.php' : __DIR__ . '/../Views/errors/404.php';

        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            // Vista 404 de respaldo si el archivo no existe
            $appName = defined('APP_NAME') ? APP_NAME : 'Sistema de Facturación';
            echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>404 - Página no encontrada | {$appName}</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0;
               display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .container { text-align: center; padding: 2rem; }
        h1 { font-size: 8rem; font-weight: 900; color: #3b82f6; margin: 0; line-height: 1; }
        h2 { font-size: 2rem; color: #94a3b8; margin: 1rem 0; }
        p  { color: #64748b; font-size: 1.1rem; }
        a  { color: #3b82f6; text-decoration: none; font-weight: 600; padding: 0.75rem 1.5rem;
             border: 2px solid #3b82f6; border-radius: 8px; display: inline-block; margin-top: 1.5rem;
             transition: all 0.2s; }
        a:hover { background: #3b82f6; color: #fff; }
        .uri { background: #1e293b; padding: 0.5rem 1rem; border-radius: 6px; font-family: monospace;
               color: #f87171; display: inline-block; margin: 1rem 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>404</h1>
        <h2>Página no encontrada</h2>
        <p>La ruta solicitada no existe:</p>
        <span class='uri'>" . htmlspecialchars($uri, ENT_QUOTES, 'UTF-8') . "</span>
        <br>
        <a href='" . (defined('APP_URL') ? APP_URL : '') . "/dashboard'>← Volver al Dashboard</a>
    </div>
</body>
</html>";
        }

        exit;
    }

    /**
     * Obtener todas las rutas registradas (útil para debugging y listado de rutas).
     *
     * @return array<int, array{method: string, path: string, middleware: string[]}>
     */
    public function getRoutes(): array
    {
        return array_map(fn($r) => [
            'method'     => $r['method'],
            'path'       => $r['path'],
            'middleware' => $r['middleware'],
        ], $this->routes);
    }
}
