<?php

declare(strict_types=1);

/**
 * =============================================================
 * MIDDLEWARE DE PROTECCIÓN CSRF
 * Sistema de Facturación - Empresa de Informática Pucallpa
 * =============================================================
 *
 * Genera y valida tokens CSRF para proteger formularios
 * contra ataques Cross-Site Request Forgery.
 *
 * Uso:
 *   - En el HTML del formulario: echo csrf_field();
 *   - En el controlador POST:    (new CsrfMiddleware())->handle();
 */

namespace App\Middleware;

use RuntimeException;

class CsrfMiddleware
{
    /**
     * Nombre del campo CSRF en los formularios.
     */
    private const FIELD_NAME = '_csrf_token';

    /**
     * Nombre de la clave del token en la sesión.
     */
    private const SESSION_KEY = '_csrf_token';

    /**
     * Longitud del token en bytes (resultará en 64 caracteres hex).
     */
    private const TOKEN_BYTES = 32;

    /**
     * Tiempo de vida del token CSRF en segundos (1 hora).
     */
    private const TOKEN_TTL = 3600;

    /**
     * Rutas excluidas de la validación CSRF (webhooks, APIs externas, etc.).
     *
     * @var string[]
     */
    private array $excluidas = [
        '/api/sunat/webhook',
    ];

    /**
     * Ejecutar el middleware CSRF para peticiones POST/PUT/DELETE.
     *
     * Valida el token CSRF en peticiones de modificación de datos.
     * Las peticiones GET, HEAD y OPTIONS son ignoradas.
     */
    public function handle(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Solo validar peticiones que modifican datos
        if (!in_array(strtoupper($metodo), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        // Verificar si la ruta actual está excluida
        $rutaActual = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        if (in_array($rutaActual, $this->excluidas, true)) {
            return;
        }

        // Obtener el token enviado (del formulario o cabecera AJAX)
        $tokenEnviado = $_POST[self::FIELD_NAME]
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? null;

        if ($tokenEnviado === null) {
            $this->fallarValidacion('Token CSRF no encontrado en la solicitud.');
        }

        if (!$this->validateToken($tokenEnviado)) {
            $this->fallarValidacion('Token CSRF inválido o expirado.');
        }

        // Regenerar el token después de validación exitosa (double-submit cookie pattern)
        $this->generateToken();
    }

    /**
     * Generar un nuevo token CSRF y almacenarlo en la sesión.
     *
     * @return string Token CSRF generado
     */
    public function generateToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        $_SESSION[self::SESSION_KEY] = [
            'token'      => $token,
            'expires_at' => time() + self::TOKEN_TTL,
        ];

        return $token;
    }

    /**
     * Validar que el token CSRF proporcionado sea correcto.
     *
     * @param  string $token Token a validar
     * @return bool          true si el token es válido y no ha expirado
     */
    public function validateToken(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sesionData = $_SESSION[self::SESSION_KEY] ?? null;

        // Verificar que existe un token en sesión
        if (!is_array($sesionData) || empty($sesionData['token'])) {
            return false;
        }

        // Verificar que no haya expirado
        if (time() > ($sesionData['expires_at'] ?? 0)) {
            unset($_SESSION[self::SESSION_KEY]);
            return false;
        }

        // Comparación en tiempo constante para prevenir timing attacks
        return hash_equals($sesionData['token'], $token);
    }

    /**
     * Obtener el token CSRF actual de la sesión (o generar uno nuevo si no existe).
     *
     * @return string Token CSRF
     */
    public function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sesionData = $_SESSION[self::SESSION_KEY] ?? null;

        // Generar nuevo token si no existe o ha expirado
        if (!is_array($sesionData)
            || empty($sesionData['token'])
            || time() > ($sesionData['expires_at'] ?? 0)
        ) {
            return $this->generateToken();
        }

        return $sesionData['token'];
    }

    /**
     * Generar el campo HTML oculto con el token CSRF.
     *
     * @return string HTML del input hidden
     */
    public function field(): string
    {
        $token = htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . $token . '">';
    }

    /**
     * Invalidar el token CSRF actual (útil al cerrar sesión).
     */
    public function invalidate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Manejar el fallo de validación CSRF.
     * Responde con JSON para peticiones AJAX o redirige al formulario.
     *
     * @param  string $mensaje Mensaje de error
     * @throws never           Siempre termina la ejecución
     */
    private function fallarValidacion(string $mensaje): never
    {
        // Detectar si es una petición AJAX
        $esAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($esAjax) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'CSRF_TOKEN_MISMATCH',
                'message' => $mensaje,
            ]);
            exit;
        }

        // Para peticiones normales: guardar mensaje y redirigir atrás
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['_flash_messages'][] = [
            'type'    => 'error',
            'message' => 'Solicitud no válida: ' . $mensaje . ' Por favor, recarga la página e intenta de nuevo.',
        ];

        $referer = $_SERVER['HTTP_REFERER'] ?? (defined('APP_URL') ? APP_URL : '/');
        header("Location: {$referer}", true, 302);
        exit;
    }
}
