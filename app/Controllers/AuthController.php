<?php

declare(strict_types=1);

/**
 * Controlador de Autenticación
 * 
 * Gestiona el proceso de login, logout, validación CSRF,
 * rate limiting por IP y bloqueo de cuentas por intentos fallidos.
 * 
 * @package App\Controllers
 * @author  Sistema de Facturación PΛRADISE
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Models\UserModel;
use PDO;

class AuthController
{
    /** @var UserModel Modelo de usuarios */
    private UserModel $userModel;

    /** @var PDO Conexión a la base de datos */
    private PDO $db;

    /** @var int Máximo de intentos de login por IP antes de bloquear */
    private const MAX_INTENTOS_IP = 5;

    /** @var int Máximo de intentos por usuario antes de bloquear cuenta */
    private const MAX_INTENTOS_USUARIO = 5;

    /** @var int Minutos de bloqueo tras exceder intentos */
    private const MINUTOS_BLOQUEO = 15;

    /** @var int Ventana de tiempo en segundos para rate limiting por IP */
    private const VENTANA_TIEMPO_IP = 300; // 5 minutos

    /**
     * Constructor - Inicializa conexión y modelos
     */
    public function __construct(PDO $db)
    {
        $this->db        = $db;
        $this->userModel = new UserModel($db);

        // Iniciar sesión si no está activa
        if (session_status() === PHP_SESSION_NONE) {
            $this->configurarSesion();
            session_start();
        }
    }

    // =========================================================================
    // CONFIGURACIÓN DE SESIÓN SEGURA
    // =========================================================================

    /**
     * Configura los parámetros de sesión seguros antes de iniciarla
     */
    private function configurarSesion(): void
    {
        $segura   = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $lifetime = (int)(getenv('SESSION_LIFETIME') ?: 120) * 60;

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $segura,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('FACTURACION_SESSION');
    }

    // =========================================================================
    // MOSTRAR LOGIN
    // =========================================================================

    /**
     * Muestra la vista del formulario de login
     * Si el usuario ya tiene sesión, redirige al dashboard
     */
    // Alias for router compatibility
    public function showLoginForm(): void
    {
        $this->showLogin();
    }

    public function showLogin(): void
    {
        // Si ya hay sesión activa, redirigir al dashboard
        if (isset($_SESSION['usuario_id']) || isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
            return;
        }

        // Generar token CSRF si no existe
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // Obtener mensajes flash si existen
        $error   = $_SESSION['flash_error']   ?? null;
        $success = $_SESSION['flash_success'] ?? null;
        $info    = $_SESSION['flash_info']    ?? null;

        // Limpiar mensajes flash
        unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['flash_info']);

        // Variables para la vista
        $csrfToken = $_SESSION['csrf_token'];
        $appName   = getenv('APP_NAME') ?: 'Sistema de Facturación';

        // Renderizar vista
        $this->renderView('auth/login', compact('csrfToken', 'error', 'success', 'info', 'appName'));
    }

    // =========================================================================
    // PROCESAR LOGIN
    // =========================================================================

    /**
     * Procesa el formulario POST de login
     * 
     * Flujo de seguridad:
     * 0. Validar trampa Honeypot (Defensa Activa contra Bots)
     * 1. Validar token CSRF
     * 2. Verificar rate limiting por IP
     * 3. Validar campos
     * 4. Buscar usuario en BD (con mitigación de Timing Attacks)
     * 5. Verificar si la cuenta está bloqueada
     * 6. Verificar contraseña con password_verify()
     * 7. Si OK: regenerar sesión, guardar datos, redirigir
     * 8. Si falla: incrementar intentos, registrar log y alertar
     */
    public function login(): void
    {
        // Solo aceptar POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/login');
            return;
        }

        $ip       = $this->getClientIp();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // -----------------------------------------------------------------
        // 0. TRAMPA HONEYPOT (DEFENSA ACTIVA)
        // -----------------------------------------------------------------
        if (!empty($_POST['website_url'])) {
            // Un humano no puede llenar este campo oculto. Es un bot automatizado.
            $this->registrarLogAcceso(null, $ip, 'bot_honeypot_capturado', $username);
            
            if (class_exists('\App\Helpers\SecurityLogger')) {
                \App\Helpers\SecurityLogger::alertarAtaque('Bot Honeypot (Spam/Fuerza Bruta)', $username, $ip);
            }
            
            // Tarpitting: Congelamos el proceso del atacante por 5 segundos
            sleep(5);
            $this->setFlash('error', 'Petición anómala detectada.');
            $this->redirect('/login');
            return;
        }

        // -----------------------------------------------------------------
        // 1. VALIDACIÓN CSRF
        // -----------------------------------------------------------------
        $tokenEnviado = $_POST['csrf_token'] ?? '';
        if (empty($tokenEnviado) || !hash_equals($_SESSION['csrf_token'] ?? '', $tokenEnviado)) {
            $this->registrarLogAcceso(null, $ip, 'csrf_invalido', $username);
            $this->setFlash('error', '⚠️ Token de seguridad inválido. Por favor, recarga la página e intenta nuevamente.');
            $this->redirect('/login');
            return;
        }

        // Regenerar CSRF token después de validar (token de un solo uso)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // -----------------------------------------------------------------
        // 2. VALIDAR CAMPOS REQUERIDOS
        // -----------------------------------------------------------------
        if (empty($username) || empty($password)) {
            $this->setFlash('error', 'El usuario y la contraseña son obligatorios.');
            $this->redirect('/login');
            return;
        }

        // Sanitización básica del username (no del password)
        $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

        // -----------------------------------------------------------------
        // 3. RATE LIMITING POR IP
        // -----------------------------------------------------------------
        if ($this->ipEstaBloqueada($ip)) {
            $minutosRestantes = $this->getMinutosBloqueoIp($ip);
            
            if (class_exists('\App\Helpers\SecurityLogger')) {
                \App\Helpers\SecurityLogger::alertarAtaque('Bloqueo de IP por Fuerza Bruta', $username, $ip);
            }

            $this->setFlash(
                'error',
                "🚫 Demasiados intentos fallidos desde tu IP. Por favor espera {$minutosRestantes} minuto(s) antes de intentar nuevamente."
            );
            $this->registrarLogAcceso(null, $ip, 'ip_bloqueada', $username);
            $this->redirect('/login');
            return;
        }

        // -----------------------------------------------------------------
        // 4. BUSCAR USUARIO EN LA BASE DE DATOS
        // -----------------------------------------------------------------
        $usuario = $this->userModel->findByUsername($username);

        if (!$usuario) {
            // VULNERABILIDAD CORREGIDA (User Enumeration via Timing Attack):
            // Si saltamos directamente al sleep, un atacante puede medir el tiempo de respuesta
            // y saber qué usuarios existen y cuáles no, ya que password_verify() consume ~200ms.
            // Ejecutamos un hash falso para igualar el tiempo de cómputo.
            password_verify('dummy_password', '$2y$10$dummyhashdummyhashdummyhashdummyhashdummyhashdummyhashd');

            // Usuario no encontrado - incrementar contador IP de todas formas
            $this->incrementarIntentosIp($ip);
            // Esperar tiempo aleatorio para prevenir timing attacks
            $this->sleepAleatorio();
            $this->setFlash('error', 'Usuario o contraseña incorrectos.');
            $this->registrarLogAcceso(null, $ip, 'usuario_no_encontrado', $username);
            $this->redirect('/login');
            return;
        }

        // -----------------------------------------------------------------
        // 5. VERIFICAR SI LA CUENTA ESTÁ BLOQUEADA
        // -----------------------------------------------------------------
        if ($this->userModel->isLocked((int)$usuario['id'])) {
            $bloqueadoHasta = $this->userModel->getLockExpiry((int)$usuario['id']);
            $segundosRestantes = max(0, strtotime($bloqueadoHasta) - time());
            $minutosRestantes  = (int)ceil($segundosRestantes / 60);

            $this->setFlash(
                'error',
                "🔒 Tu cuenta está temporalmente bloqueada por exceso de intentos fallidos. " .
                "Podrás intentar nuevamente en {$minutosRestantes} minuto(s)."
            );
            $this->registrarLogAcceso((int)$usuario['id'], $ip, 'cuenta_bloqueada', $username);
            $this->redirect('/login');
            return;
        }

        // -----------------------------------------------------------------
        // 6. VERIFICAR QUE EL USUARIO ESTÉ ACTIVO
        // -----------------------------------------------------------------
        if (!(bool)$usuario['activo']) {
            $this->setFlash('error', '🚫 Tu cuenta ha sido desactivada. Contacta al administrador del sistema.');
            $this->registrarLogAcceso((int)$usuario['id'], $ip, 'cuenta_inactiva', $username);
            $this->redirect('/login');
            return;
        }

        // -----------------------------------------------------------------
        // 7. VERIFICAR CONTRASEÑA
        // -----------------------------------------------------------------
        if (!password_verify($password, $usuario['password'])) {
            // Incrementar intentos fallidos
            $this->userModel->incrementLoginAttempts((int)$usuario['id']);
            $this->incrementarIntentosIp($ip);

            // Contar intentos actuales
            $intentosActuales = (int)$usuario['intentos_login'] + 1;

            // Bloquear si se superó el máximo
            if ($intentosActuales >= self::MAX_INTENTOS_USUARIO) {
                $this->userModel->lockUser((int)$usuario['id'], self::MINUTOS_BLOQUEO);
                
                if (class_exists('\App\Helpers\SecurityLogger')) {
                    \App\Helpers\SecurityLogger::alertarAtaque('Cuenta de Usuario Bloqueada', $username, $ip);
                }

                $this->setFlash(
                    'error',
                    "🔒 Has excedido el máximo de intentos. Tu cuenta se ha bloqueado por " .
                    self::MINUTOS_BLOQUEO . " minutos."
                );
            } else {
                $intentosRestantes = self::MAX_INTENTOS_USUARIO - $intentosActuales;
                $this->setFlash(
                    'error',
                    "Usuario o contraseña incorrectos. Te quedan {$intentosRestantes} intento(s) antes de que tu cuenta sea bloqueada."
                );
            }

            $this->sleepAleatorio(); // Prevenir timing attacks
            $this->registrarLogAcceso((int)$usuario['id'], $ip, 'password_incorrecto', $username);
            $this->redirect('/login');
            return;
        }

        // -----------------------------------------------------------------
        // 8. LOGIN EXITOSO
        // -----------------------------------------------------------------

        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);

        // Guardar datos del usuario en la sesión
        // Claves canónicas con prefijo 'usuario_'
        $_SESSION['usuario_id']       = (int)$usuario['id'];
        $_SESSION['usuario_nombre']   = $usuario['nombre'] . ' ' . $usuario['apellidos'];
        $_SESSION['usuario_username'] = $usuario['username'];
        $_SESSION['usuario_email']    = $usuario['email'];
        $_SESSION['usuario_rol']      = $usuario['rol_nombre'] ?? 'vendedor';
        $_SESSION['usuario_rol_id']   = (int)($usuario['rol_id'] ?? 2);
        $_SESSION['usuario_tema']     = $usuario['tema'] ?? 'light';
        $_SESSION['usuario_permisos'] = json_decode($usuario['rol_permisos'] ?? '[]', true);
        $_SESSION['login_time']       = time();
        $_SESSION['_last_activity']   = time();
        $_SESSION['login_ip']         = $ip;

        // Aliases para compatibilidad con los controladores que usan 'user_id'
        $_SESSION['user_id']          = $_SESSION['usuario_id'];
        $_SESSION['user_nombre']      = $_SESSION['usuario_nombre'];
        $_SESSION['user_rol']         = $_SESSION['usuario_rol'];
        $_SESSION['user_rol_id']      = $_SESSION['usuario_rol_id'];
        $_SESSION['theme']            = $_SESSION['usuario_tema'];

        // Array 'user' para la función currentUser() del helper
        $_SESSION['user'] = [
            'id'       => $_SESSION['usuario_id'],
            'nombre'   => $usuario['nombre'],
            'apellidos'=> $usuario['apellidos'],
            'username' => $usuario['username'],
            'email'    => $usuario['email'],
            'rol_id'   => $_SESSION['usuario_rol_id'],
            'rol'      => $_SESSION['usuario_rol'],
        ];

        // Actualizar último acceso y resetear intentos
        $this->userModel->updateLastAccess((int)$usuario['id']);
        $this->userModel->resetLoginAttempts((int)$usuario['id']);

        // Resetear intentos de IP
        $this->resetearIntentosIp($ip);

        // Regenerar token CSRF tras login exitoso
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Registrar log de acceso exitoso
        $this->registrarLogAcceso((int)$usuario['id'], $ip, 'login_exitoso', $username);

        // Redirigir según rol
        $redirectUrl = $this->getRedirectPorRol($usuario['rol_nombre']);
        $this->redirect($redirectUrl);
    }

    // =========================================================================
    // LOGOUT
    // =========================================================================

    /**
     * Cierra la sesión del usuario de forma segura
     * Destruye todos los datos de sesión y la cookie
     */
    public function logout(): void
    {
        // Registrar el logout si hay sesión activa
        if (isset($_SESSION['usuario_id'])) {
            $ip = $this->getClientIp();
            $this->registrarLogAcceso(
                (int)$_SESSION['usuario_id'],
                $ip,
                'logout',
                $_SESSION['usuario_username'] ?? ''
            );
        }

        // Limpiar todas las variables de sesión
        $_SESSION = [];

        // Destruir la cookie de sesión
        if (ini_get('session.use_cookies')) {
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

        // Destruir la sesión del servidor
        session_destroy();

        // Redirigir al login con mensaje
        session_start();
        $this->setFlash('success', '✅ Has cerrado sesión correctamente. ¡Hasta pronto!');
        $this->redirect('/login');
    }

    // =========================================================================
    // MÉTODOS AUXILIARES - RATE LIMITING POR IP
    // =========================================================================

    /**
     * Verifica si una IP está bloqueada por exceso de intentos
     * Usa la tabla log_accesos para contar intentos recientes
     * 
     * @param string $ip Dirección IP del cliente
     * @return bool true si la IP está bloqueada
     */
    private function ipEstaBloqueada(string $ip): bool
    {
        $sql = "SELECT COUNT(*) as intentos
                FROM logs_acceso
                WHERE ip = :ip
                  AND accion IN ('password_incorrecto', 'usuario_no_encontrado', 'csrf_invalido')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :segundos SECOND)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':ip',       $ip, PDO::PARAM_STR);
            $stmt->bindValue(':segundos', self::VENTANA_TIEMPO_IP, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($resultado['intentos'] ?? 0) >= self::MAX_INTENTOS_IP;
        } catch (\Exception) {
            // Si falla la consulta, no bloquear para no afectar usuarios legítimos
            return false;
        }
    }

    /**
     * Obtiene los minutos restantes de bloqueo para una IP
     * 
     * @param string $ip Dirección IP
     * @return int Minutos restantes de bloqueo
     */
    private function getMinutosBloqueoIp(string $ip): int
    {
        $sql = "SELECT MAX(created_at) as ultimo_intento
                FROM logs_acceso
                WHERE ip = :ip
                  AND accion IN ('password_incorrecto', 'usuario_no_encontrado')";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
            $stmt->execute();

            $resultado  = $stmt->fetch(PDO::FETCH_ASSOC);
            $ultimoFin  = strtotime($resultado['ultimo_intento'] ?? 'now') + self::VENTANA_TIEMPO_IP;
            $restantes  = max(0, $ultimoFin - time());

            return (int)ceil($restantes / 60);
        } catch (\Exception) {
            return (int)ceil(self::VENTANA_TIEMPO_IP / 60);
        }
    }

    /**
     * Incrementa el contador de intentos para una IP en sesión
     * 
     * @param string $ip Dirección IP
     */
    private function incrementarIntentosIp(string $ip): void
    {
        if (!isset($_SESSION['ip_intentos'])) {
            $_SESSION['ip_intentos'] = [];
        }
        $_SESSION['ip_intentos'][$ip] = ($_SESSION['ip_intentos'][$ip] ?? 0) + 1;
    }

    /**
     * Reinicia el contador de intentos para una IP
     * 
     * @param string $ip Dirección IP
     */
    private function resetearIntentosIp(string $ip): void
    {
        unset($_SESSION['ip_intentos'][$ip]);
    }

    // =========================================================================
    // MÉTODOS AUXILIARES - LOG DE ACCESOS
    // =========================================================================

    /**
     * Registra un intento de acceso (exitoso o fallido) en la base de datos
     * 
     * @param int|null $usuarioId ID del usuario (null si no se encontró)
     * @param string   $ip        Dirección IP del cliente
     * @param string   $accion    Tipo de acción registrada
     * @param string   $username  Username intentado
     */
    private function registrarLogAcceso(
        ?int   $usuarioId,
        string $ip,
        string $accion,
        string $username
    ): void {
        try {
            $sql = "INSERT INTO logs_acceso 
                        (usuario_id, ip, accion, username_intentado, user_agent, created_at)
                    VALUES 
                        (:usuario_id, :ip, :accion, :username, :user_agent, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':usuario_id', $usuarioId, $usuarioId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':ip',         $ip, PDO::PARAM_STR);
            $stmt->bindValue(':accion',     $accion, PDO::PARAM_STR);
            $stmt->bindValue(':username',   substr($username, 0, 100), PDO::PARAM_STR);
            $stmt->bindValue(':user_agent', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), PDO::PARAM_STR);
            $stmt->execute();
        } catch (\Exception) {
            // Silenciar errores de log para no afectar el flujo principal
        }
    }

    // =========================================================================
    // MÉTODOS AUXILIARES - UTILIDADES
    // =========================================================================

    /**
     * Obtiene la dirección IP real del cliente
     * Considera proxies y load balancers
     * 
     * @return string Dirección IP del cliente
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Duerme un tiempo aleatorio para prevenir timing attacks
     * Rango: 200ms - 600ms
     */
    private function sleepAleatorio(): void
    {
        usleep(random_int(200000, 600000));
    }

    /**
     * Determina la URL de redirección según el rol del usuario
     * 
     * @param string $rol Nombre del rol
     * @return string URL de redirección
     */
    private function getRedirectPorRol(string $rol): string
    {
        return match (strtolower($rol)) {
            'vendedor', 'cajero' => '/pos',
            default              => '/dashboard',
        };
    }

    /**
     * Establece un mensaje flash en la sesión
     * 
     * @param string $tipo    Tipo: 'error', 'success', 'info', 'warning'
     * @param string $mensaje Mensaje a mostrar
     */
    private function setFlash(string $tipo, string $mensaje): void
    {
        $_SESSION["flash_{$tipo}"] = $mensaje;
    }

    /**
     * Redirige a una URL y termina la ejecución
     * 
     * @param string $url URL de destino (relativa o absoluta)
     */
    private function redirect(string $url): void
    {
        $baseUrl = rtrim(getenv('APP_URL') ?: '', '/');
        $fullUrl = str_starts_with($url, 'http') ? $url : $baseUrl . $url;

        header("Location: {$fullUrl}");
        exit;
    }

    /**
     * Renderiza una vista PHP pasando variables
     * 
     * @param string $view      Ruta relativa a app/Views/ (sin .php)
     * @param array  $variables Variables a extraer en la vista
     */
    private function renderView(string $view, array $variables = []): void
    {
        extract($variables, EXTR_SKIP);

        $viewPath = __DIR__ . "/../Views/{$view}.php";

        if (!file_exists($viewPath)) {
            http_response_code(404);
            echo "Vista no encontrada: {$view}";
            exit;
        }

        require $viewPath; 
    }
}