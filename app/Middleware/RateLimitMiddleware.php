<?php

declare(strict_types=1);

/**
 * =============================================================
 * MIDDLEWARE DE RATE LIMITING (LIMITACIÓN DE INTENTOS)
 * Sistema de Facturación - Empresa de Informática Pucallpa
 * =============================================================
 *
 * Limita los intentos de login y otras operaciones sensibles
 * para prevenir ataques de fuerza bruta.
 *
 * Configuración:
 *   - Máximo 5 intentos fallidos
 *   - Bloqueo por 15 minutos tras superar el límite
 *   - Rastreo por dirección IP usando sesiones PHP
 */

namespace App\Middleware;

class RateLimitMiddleware
{
    /**
     * Número máximo de intentos permitidos antes de bloquear.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Tiempo de bloqueo en segundos (15 minutos).
     */
    private const LOCKOUT_TIME = 900;

    /**
     * Ventana de tiempo para contar intentos en segundos (15 minutos).
     */
    private const WINDOW_TIME = 900;

    /**
     * Prefijo para las claves en sesión.
     */
    private const SESSION_PREFIX = '_rate_limit_';

    /**
     * Ejecutar el middleware de rate limiting.
     *
     * @param  string $key Clave identificadora del límite (ej: 'login', 'api')
     * @throws never       Si el límite está excedido, redirige o responde con error
     */
    public function handle(string $key = 'login'): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $ipKey = $this->buildKey($key);

        if ($this->isLocked($ipKey)) {
            $tiempoRestante = $this->getRemainingLockTime($ipKey);
            $this->respondLimitExceeded($tiempoRestante);
        }
    }

    /**
     * Verificar si una clave específica está bloqueada.
     *
     * @param  string $key Clave del límite (con IP incluida)
     * @return bool        true si está bloqueada
     */
    public function isLocked(string $key): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $datos = $this->getDatos($key);

        if (empty($datos)) {
            return false;
        }

        // Verificar si está en período de bloqueo explícito
        if (!empty($datos['bloqueado_hasta']) && time() < $datos['bloqueado_hasta']) {
            return true;
        }

        // Si el período de bloqueo ya pasó, limpiar los datos
        if (!empty($datos['bloqueado_hasta']) && time() >= $datos['bloqueado_hasta']) {
            $this->clearLimit($key);
            return false;
        }

        return false;
    }

    /**
     * Verificar el límite de intentos sin bloquear (solo verificar).
     *
     * @param  string $key Clave base del límite (sin IP)
     * @return bool        true si el límite está excedido
     */
    public function checkLimit(string $key): bool
    {
        $ipKey = $this->buildKey($key);
        return $this->isLocked($ipKey);
    }

    /**
     * Incrementar el contador de intentos para una clave.
     * Si supera el máximo, activa el bloqueo automáticamente.
     *
     * @param  string $key Clave base del límite (sin IP)
     * @return int         Número de intentos actuales
     */
    public function increment(string $key): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $ipKey    = $this->buildKey($key);
        $datos    = $this->getDatos($ipKey);
        $ahora    = time();

        // Inicializar si no existe o si la ventana de tiempo ya pasó
        if (empty($datos) || ($ahora - ($datos['primera_vez'] ?? $ahora)) > self::WINDOW_TIME) {
            $datos = [
                'intentos'      => 0,
                'primera_vez'   => $ahora,
                'ultima_vez'    => $ahora,
                'bloqueado_hasta' => null,
            ];
        }

        $datos['intentos']++;
        $datos['ultima_vez'] = $ahora;

        // Si supera el máximo de intentos, activar bloqueo
        if ($datos['intentos'] >= self::MAX_ATTEMPTS) {
            $datos['bloqueado_hasta'] = $ahora + self::LOCKOUT_TIME;

            // Registrar el bloqueo en log
            $this->registrarBloqueo($key, $datos['intentos']);
        }

        $this->setDatos($ipKey, $datos);

        return $datos['intentos'];
    }

    /**
     * Decrementar el contador (útil si el intento fue exitoso).
     * Generalmente llamado después de un login exitoso para limpiar el límite.
     *
     * @param string $key Clave base del límite
     */
    public function clearLimit(string $key): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $ipKey = $this->buildKey($key);
        $sessionKey = self::SESSION_PREFIX . $ipKey;
        unset($_SESSION[$sessionKey]);
    }

    /**
     * Obtener los intentos restantes antes del bloqueo.
     *
     * @param  string $key Clave base del límite
     * @return int         Intentos restantes (0 si ya está bloqueado)
     */
    public function getRemainingAttempts(string $key): int
    {
        $ipKey  = $this->buildKey($key);
        $datos  = $this->getDatos($ipKey);
        $usados = $datos['intentos'] ?? 0;

        return max(0, self::MAX_ATTEMPTS - $usados);
    }

    /**
     * Obtener el tiempo restante de bloqueo en segundos.
     *
     * @param  string $key Clave del límite (con IP incluida)
     * @return int         Segundos restantes de bloqueo
     */
    public function getRemainingLockTime(string $key): int
    {
        $datos = $this->getDatos($key);

        if (empty($datos['bloqueado_hasta'])) {
            return 0;
        }

        return max(0, $datos['bloqueado_hasta'] - time());
    }

    /**
     * Verificar si hay advertencia (3 o más intentos).
     *
     * @param  string $key Clave base del límite
     * @return bool
     */
    public function shouldWarn(string $key): bool
    {
        $ipKey  = $this->buildKey($key);
        $datos  = $this->getDatos($ipKey);
        $intentos = $datos['intentos'] ?? 0;

        return $intentos >= (self::MAX_ATTEMPTS - 2);
    }

    /**
     * Construir la clave única combinando la clave base con la IP del cliente.
     *
     * @param  string $key Clave base
     * @return string      Clave con IP hasheada
     */
    private function buildKey(string $key): string
    {
        $ip = $this->getClientIp();
        // Hashear la IP para no almacenarla en texto claro en la sesión
        return $key . '_' . substr(md5($ip), 0, 16);
    }

    /**
     * Obtener la IP real del cliente.
     *
     * @return string Dirección IP
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Obtener los datos del rate limit de la sesión.
     *
     * @param  string $key Clave del límite
     * @return array       Datos del límite o array vacío
     */
    private function getDatos(string $key): array
    {
        $sessionKey = self::SESSION_PREFIX . $key;
        return $_SESSION[$sessionKey] ?? [];
    }

    /**
     * Guardar los datos del rate limit en sesión.
     *
     * @param string $key   Clave del límite
     * @param array  $datos Datos a guardar
     */
    private function setDatos(string $key, array $datos): void
    {
        $sessionKey = self::SESSION_PREFIX . $key;
        $_SESSION[$sessionKey] = $datos;
    }

    /**
     * Registrar el bloqueo de una IP en el log de seguridad.
     *
     * @param string $key      Nombre del servicio limitado
     * @param int    $intentos Número de intentos realizados
     */
    private function registrarBloqueo(string $key, int $intentos): void
    {
        $ip      = $this->getClientIp();
        $minutos = intdiv(self::LOCKOUT_TIME, 60);
        $logPath = defined('LOGS_PATH') ? LOGS_PATH . '/security.log' : __DIR__ . '/../../../storage/logs/security.log';
        $logDir  = dirname($logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $linea = sprintf(
            "[%s] [BLOQUEO_RATE_LIMIT] IP: %s | Servicio: %s | Intentos: %d | Bloqueado por: %d minutos\n",
            date('Y-m-d H:i:s'),
            $ip,
            $key,
            $intentos,
            $minutos
        );

        file_put_contents($logPath, $linea, FILE_APPEND | LOCK_EX);
    }

    /**
     * Responder al cliente indicando que el límite está excedido.
     *
     * @param  int    $segundosRestantes Tiempo de bloqueo restante
     * @throws never                     Siempre termina la ejecución
     */
    private function respondLimitExceeded(int $segundosRestantes): never
    {
        $minutos  = (int) ceil($segundosRestantes / 60);
        $mensaje  = "Demasiados intentos fallidos. Por razones de seguridad, debes esperar "
            . "{$minutos} minuto(s) antes de intentar de nuevo.";

        // Detectar si es AJAX
        $esAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($esAjax) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . $segundosRestantes);
            echo json_encode([
                'success'            => false,
                'error'              => 'RATE_LIMIT_EXCEEDED',
                'message'            => $mensaje,
                'retry_after_seconds'=> $segundosRestantes,
                'retry_after_minutes'=> $minutos,
            ]);
            exit;
        }

        // Para peticiones normales: guardar mensaje y redirigir
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['_flash_messages'][] = [
            'type'    => 'error',
            'message' => $mensaje,
        ];

        $referer = $_SERVER['HTTP_REFERER'] ?? (defined('APP_URL') ? APP_URL . '/login' : '/login');
        header('Retry-After: ' . $segundosRestantes);
        header("Location: {$referer}", true, 429);
        exit;
    }
}
