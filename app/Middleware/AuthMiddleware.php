<?php

declare(strict_types=1);

/**
 * =============================================================
 * MIDDLEWARE DE AUTENTICACIÓN
 * Sistema de Facturación - Empresa de Informática Pucallpa
 * =============================================================
 *
 * Verifica que el usuario tenga una sesión activa y válida.
 * Si no está autenticado, redirige al login con mensaje informativo.
 */

namespace App\Middleware;

class AuthMiddleware
{
    /**
     * Tiempo máximo de inactividad en segundos (2 horas).
     */
    private const SESSION_TIMEOUT = 7200;

    /**
     * Ejecutar el middleware de autenticación.
     *
     * Verifica la sesión activa, el tiempo de inactividad
     * y la validez del fingerprint del usuario.
     */
    public function handle(): void
    {
        // Iniciar sesión si no está activa
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Compatibilidad con las claves de sesión usadas por AuthController.
        $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0);

        // Verificar si el usuario está autenticado
        if ($userId <= 0) {
            $this->redirectToLogin('Debes iniciar sesión para acceder.');
        }

        // Verificar tiempo de inactividad de la sesión
        $ultimaActividad = (int)($_SESSION['_last_activity'] ?? $_SESSION['login_time'] ?? time());
        if ((time() - $ultimaActividad) > self::SESSION_TIMEOUT) {
            $this->destroySession();
            $this->redirectToLogin('Tu sesión ha expirado. Inicia sesión nuevamente.');
        }

        // Verificar integridad del fingerprint de sesión (prevenir session hijacking)
        $fingerprintActual = $this->generateFingerprint();
        $fingerprintGuardado = $_SESSION['_fingerprint'] ?? null;

        if ($fingerprintGuardado !== null && !hash_equals($fingerprintGuardado, $fingerprintActual)) {
            // Posible robo de sesión: destruir y redirigir
            $this->destroySession();
            $this->redirectToLogin('Sesión inválida por razones de seguridad. Inicia sesión nuevamente.');
        }

        // Verificar que el usuario siga activo en la base de datos (cada 5 minutos)
        $ultimaVerificacion = $_SESSION['_db_check'] ?? 0;
        if ((time() - $ultimaVerificacion) > 300) {
            $this->verificarUsuarioEnBd();
        }

        // Actualizar timestamp de última actividad
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Verificar que el usuario siga activo y habilitado en la base de datos.
     */
    private function verificarUsuarioEnBd(): void
    {
        try {
            $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0);
            $db = \Config\Database::getInstance();
            $usuario = $db->fetchOne(
                "SELECT id, activo FROM usuarios WHERE id = ? LIMIT 1",
                [$userId]
            );

            if (!$usuario || !(bool) $usuario['activo']) {
                // Usuario deshabilitado o eliminado
                $this->destroySession();
                $this->redirectToLogin('Tu cuenta ha sido deshabilitada. Contacta al administrador.');
            }

            // Registrar que la verificación fue exitosa
            $_SESSION['_db_check'] = time();
        } catch (\Throwable) {
            // Si la BD no responde, continuar (no bloquear al usuario por error de BD)
            $_SESSION['_db_check'] = time();
        }
    }

    /**
     * Generar un fingerprint basado en datos del navegador del usuario.
     * Permite detectar intentos de session hijacking.
     *
     * @return string Hash del fingerprint
     */
    private function generateFingerprint(): string
    {
        // Combinar User-Agent y Accept-Language para crear un fingerprint estable
        $datos = implode('|', [
            $_SERVER['HTTP_USER_AGENT']      ?? 'unknown',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown',
        ]);

        // Usar HMAC con APP_KEY para mayor seguridad
        $secreto = defined('APP_KEY') ? APP_KEY : ($_ENV['APP_KEY'] ?? 'default_secret');

        return hash_hmac('sha256', $datos, $secreto);
    }

    /**
     * Destruir completamente la sesión del usuario.
     */
    private function destroySession(): void
    {
        $_SESSION = [];

        // Eliminar cookie de sesión si existe
        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Redirigir al formulario de login con un mensaje informativo.
     *
     * @param string $mensaje Mensaje a mostrar en la página de login
     */
    private function redirectToLogin(string $mensaje): never
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Guardar la URL original para redirigir después del login
        $urlActual = $_SERVER['REQUEST_URI'] ?? '/';
        if ($urlActual !== '/login') {
            $_SESSION['_intended_url'] = $urlActual;
        }

        // Guardar mensaje flash de error
        $_SESSION['_flash_messages'][] = [
            'type'    => 'warning',
            'message' => $mensaje,
        ];

        $loginUrl = (defined('APP_URL') ? APP_URL : '') . '/login';
        header("Location: {$loginUrl}", true, 302);
        exit;
    }
}
