<?php

declare(strict_types=1);

/**
 * Controlador de Usuarios
 * 
 * Gestiona el CRUD de usuarios del sistema y la actualización de tema (dark/light).
 * Solo los administradores pueden gestionar usuarios.
 * 
 * @package App\Controllers
 * @author  Sistema de Facturación Pucallpa
 * @version 1.0.0
 */

namespace App\Controllers;

use App\Models\UserModel;
use PDO;

class UserController
{
    /** @var UserModel Modelo de usuarios */
    private UserModel $userModel;

    /** @var PDO Conexión a la base de datos */
    private PDO $db;

    /**
     * Constructor - Inicializa modelos y verifica autenticación
     */
    public function __construct(PDO $db)
    {
        $this->db        = $db;
        $this->userModel = new UserModel($db);

        // Verificar que existe sesión activa
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Verificar autenticación (excepto para updateTheme que es diferente)
        $this->verificarAutenticacion();
    }

    // =========================================================================
    // LISTADO DE USUARIOS
    // =========================================================================

    /**
     * Lista todos los usuarios del sistema
     * Solo accesible para administradores
     */
    public function index(): void
    {
        $this->verificarRolAdmin();

        $usuarios = $this->userModel->getAll();
        $roles    = $this->userModel->getRoles();

        // Filtros desde GET
        $filtroRol    = $_GET['rol']    ?? '';
        $filtroEstado = $_GET['estado'] ?? '';

        // Aplicar filtros si se especificaron
        if ($filtroRol !== '') {
            $usuarios = array_filter($usuarios, fn($u) => $u['rol_nombre'] === $filtroRol);
        }
        if ($filtroEstado !== '') {
            $activo   = $filtroEstado === 'activo' ? 1 : 0;
            $usuarios = array_filter($usuarios, fn($u) => (int)$u['activo'] === $activo);
        }

        $pageTitle = 'Gestión de Usuarios';
        $this->render('users/index', compact('usuarios', 'roles', 'filtroRol', 'filtroEstado', 'pageTitle'));
    }

    // =========================================================================
    // CREAR USUARIO
    // =========================================================================

    /**
     * Muestra el formulario para crear un nuevo usuario
     * Solo accesible para administradores
     */
    public function create(): void
    {
        $this->verificarRolAdmin();

        $roles     = $this->userModel->getRoles();
        $usuario   = null; // Null indica que es un formulario de creación
        $pageTitle = 'Crear Usuario';
        $accion    = 'crear';

        $this->render('users/form', compact('roles', 'usuario', 'pageTitle', 'accion'));
    }

    /**
     * Procesa el formulario de creación de nuevo usuario
     * Solo accesible para administradores
     */
    public function store(): void
    {
        $this->verificarRolAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/usuarios');
            return;
        }

        // Validar CSRF
        if (!$this->validarCSRF()) {
            $this->setFlash('error', 'Token de seguridad inválido. Por favor recarga la página.');
            $this->redirect('/usuarios/crear');
            return;
        }

        // Recoger y validar datos del formulario
        $datos = $this->recogerDatosFormulario();
        $errores = $this->validarDatosUsuario($datos);

        if (!empty($errores)) {
            $this->setFlash('error', implode('<br>', $errores));
            $this->redirect('/usuarios/crear');
            return;
        }

        // Verificar unicidad de username y email
        if ($this->userModel->usernameExists($datos['username'])) {
            $this->setFlash('error', "El nombre de usuario '{$datos['username']}' ya está registrado.");
            $this->redirect('/usuarios/crear');
            return;
        }

        if ($this->userModel->emailExists($datos['email'])) {
            $this->setFlash('error', "El correo electrónico '{$datos['email']}' ya está registrado.");
            $this->redirect('/usuarios/crear');
            return;
        }

        // Crear usuario
        $nuevoId = $this->userModel->create($datos);

        if ($nuevoId) {
            $this->setFlash('success', "✅ Usuario '{$datos['nombre']} {$datos['apellidos']}' creado correctamente.");
            $this->redirect('/usuarios');
        } else {
            $this->setFlash('error', 'Ocurrió un error al crear el usuario. Por favor intenta nuevamente.');
            $this->redirect('/usuarios/crear');
        }
    }

    // =========================================================================
    // EDITAR USUARIO
    // =========================================================================

    /**
     * Muestra el formulario para editar un usuario existente
     * 
     * @param int $id ID del usuario a editar
     */
    public function edit(int $id): void
    {
        $this->verificarRolAdmin();

        $usuario = $this->userModel->findById($id);

        if (!$usuario) {
            $this->setFlash('error', 'Usuario no encontrado.');
            $this->redirect('/usuarios');
            return;
        }

        $roles     = $this->userModel->getRoles();
        $pageTitle = "Editar Usuario: {$usuario['nombre']} {$usuario['apellidos']}";
        $accion    = 'editar';

        $this->render('users/form', compact('roles', 'usuario', 'pageTitle', 'accion'));
    }

    /**
     * Procesa el formulario de actualización de usuario
     * 
     * @param int $id ID del usuario a actualizar
     */
    public function update(int $id): void
    {
        $this->verificarRolAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/usuarios');
            return;
        }

        // Validar CSRF
        if (!$this->validarCSRF()) {
            $this->setFlash('error', 'Token de seguridad inválido. Por favor recarga la página.');
            $this->redirect("/usuarios/{$id}/editar");
            return;
        }

        // Verificar que el usuario existe
        $usuarioExistente = $this->userModel->findById($id);
        if (!$usuarioExistente) {
            $this->setFlash('error', 'Usuario no encontrado.');
            $this->redirect('/usuarios');
            return;
        }

        // Recoger datos del formulario
        $datos   = $this->recogerDatosFormulario();
        $errores = $this->validarDatosUsuario($datos, false, $id);

        if (!empty($errores)) {
            $this->setFlash('error', implode('<br>', $errores));
            $this->redirect("/usuarios/{$id}/editar");
            return;
        }

        // Verificar unicidad excluyendo el usuario actual
        if ($this->userModel->usernameExists($datos['username'], $id)) {
            $this->setFlash('error', "El nombre de usuario '{$datos['username']}' ya está registrado por otro usuario.");
            $this->redirect("/usuarios/{$id}/editar");
            return;
        }

        if ($this->userModel->emailExists($datos['email'], $id)) {
            $this->setFlash('error', "El correo electrónico '{$datos['email']}' ya está registrado por otro usuario.");
            $this->redirect("/usuarios/{$id}/editar");
            return;
        }

        // Actualizar datos principales
        $actualizado = $this->userModel->update($id, $datos);

        // Actualizar contraseña si se proporcionó una nueva
        if (!empty($_POST['password']) && strlen($_POST['password']) >= 8) {
            $this->userModel->updatePassword($id, $_POST['password']);
        }

        if ($actualizado) {
            $this->setFlash('success', "✅ Usuario '{$datos['nombre']} {$datos['apellidos']}' actualizado correctamente.");
            $this->redirect('/usuarios');
        } else {
            $this->setFlash('error', 'Ocurrió un error al actualizar el usuario.');
            $this->redirect("/usuarios/{$id}/editar");
        }
    }

    // =========================================================================
    // ELIMINAR USUARIO
    // =========================================================================

    /**
     * Elimina (desactiva) un usuario del sistema
     * 
     * @param int $id ID del usuario a eliminar
     */
    public function delete(int $id): void
    {
        $this->verificarRolAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/usuarios');
            return;
        }

        // Validar CSRF
        if (!$this->validarCSRF()) {
            $this->setFlash('error', 'Token de seguridad inválido.');
            $this->redirect('/usuarios');
            return;
        }

        // No permitir que el admin se elimine a sí mismo
        if ($id === (int)($_SESSION['usuario_id'] ?? 0)) {
            $this->setFlash('error', '⚠️ No puedes desactivar tu propia cuenta mientras tienes sesión activa.');
            $this->redirect('/usuarios');
            return;
        }

        $usuario = $this->userModel->findById($id);
        if (!$usuario) {
            $this->setFlash('error', 'Usuario no encontrado.');
            $this->redirect('/usuarios');
            return;
        }

        $eliminado = $this->userModel->delete($id);

        if ($eliminado) {
            $this->setFlash('success', "✅ Usuario '{$usuario['nombre']} {$usuario['apellidos']}' desactivado correctamente.");
        } else {
            $this->setFlash('error', 'Ocurrió un error al desactivar el usuario.');
        }

        $this->redirect('/usuarios');
    }

    // =========================================================================
    // ACTUALIZAR TEMA (DARK/LIGHT MODE)
    // =========================================================================

    /**
     * Actualiza la preferencia de tema del usuario autenticado
     * Recibe POST con JSON: {"tema": "dark"} o {"tema": "light"}
     * Cualquier usuario autenticado puede usar este endpoint
     */
    public function updateTheme(): void
    {
        // Verificar autenticación
        if (empty($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'mensaje' => 'No autenticado']);
            return;
        }

        // Validar CSRF
        if (!$this->validarCSRF()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'mensaje' => 'Token CSRF inválido']);
            return;
        }

        // Leer tema del body (puede ser POST normal o JSON)
        $tema = $_POST['tema'] ?? null;

        if ($tema === null) {
            $body = file_get_contents('php://input');
            $json = json_decode($body, true);
            $tema = $json['tema'] ?? null;
        }

        // Validar valor
        if (!in_array($tema, ['dark', 'light'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'mensaje' => 'Tema inválido. Use "dark" o "light"']);
            return;
        }

        $userId     = (int)$_SESSION['usuario_id'];
        $actualizado = $this->userModel->updateTheme($userId, $tema);

        if ($actualizado) {
            // Actualizar en sesión también
            $_SESSION['usuario_tema'] = $tema;

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'tema' => $tema]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'mensaje' => 'Error al actualizar el tema']);
        }
    }

    // =========================================================================
    // MÉTODOS AUXILIARES PRIVADOS
    // =========================================================================

    /**
     * Recopila y sanitiza los datos del formulario de usuario
     * 
     * @return array Datos sanitizados
     */
    private function recogerDatosFormulario(): array
    {
        return [
            'nombre'    => trim(htmlspecialchars($_POST['nombre']    ?? '', ENT_QUOTES, 'UTF-8')),
            'apellidos' => trim(htmlspecialchars($_POST['apellidos'] ?? '', ENT_QUOTES, 'UTF-8')),
            'email'     => trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)),
            'username'  => trim(htmlspecialchars($_POST['username']  ?? '', ENT_QUOTES, 'UTF-8')),
            'rol_id'    => (int)($_POST['rol_id'] ?? 0),
            
            // CORRECCIÓN: Leer el valor real (0 o 1) del select
            'activo'    => (int)($_POST['activo'] ?? 1), 
            
            'tema'      => 'light',
            'password'  => $_POST['password'] ?? '',
        ];
    }

    /**
     * Valida los datos del formulario de usuario
     * 
     * @param array    $datos        Datos a validar
     * @param bool     $esNuevo      true si es creación, false si es edición
     * @param int|null $excludeId    ID a excluir en verificación de unicidad
     * @return array Lista de errores (vacía si no hay)
     */
    private function validarDatosUsuario(array $datos, bool $esNuevo = true, ?int $excludeId = null): array
    {
        $errores = [];

        if (empty($datos['nombre'])) {
            $errores[] = 'El nombre es obligatorio.';
        } elseif (strlen($datos['nombre']) > 100) {
            $errores[] = 'El nombre no puede exceder 100 caracteres.';
        }

        if (empty($datos['apellidos'])) {
            $errores[] = 'Los apellidos son obligatorios.';
        }

        if (empty($datos['email'])) {
            $errores[] = 'El correo electrónico es obligatorio.';
        } elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no tiene un formato válido.';
        }

        if (empty($datos['username'])) {
            $errores[] = 'El nombre de usuario es obligatorio.';
        } elseif (strlen($datos['username']) < 3) {
            $errores[] = 'El nombre de usuario debe tener al menos 3 caracteres.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $datos['username'])) {
            $errores[] = 'El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos.';
        }

        if ($datos['rol_id'] <= 0) {
            $errores[] = 'Debes seleccionar un rol válido.';
        } elseif (!in_array($datos['rol_id'], array_map('intval', array_column($this->userModel->getRoles(), 'id')), true)) {
            $errores[] = 'El rol seleccionado no existe en la base de datos.';
        }

        // Validar contraseña solo si es creación o si se proporcionó una nueva
        if ($esNuevo) {
            if (empty($datos['password'])) {
                $errores[] = 'La contraseña es obligatoria para nuevos usuarios.';
            } elseif (strlen($datos['password']) < 8) {
                $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
            }

            // Confirmar contraseña
            $confirmacion = $_POST['password_confirm'] ?? '';
            if ($datos['password'] !== $confirmacion) {
                $errores[] = 'Las contraseñas no coinciden.';
            }
        } elseif (!empty($datos['password'])) {
            // Si se proporcionó contraseña en edición, validarla también
            if (strlen($datos['password']) < 8) {
                $errores[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
            }
            $confirmacion = $_POST['password_confirm'] ?? '';
            if ($datos['password'] !== $confirmacion) {
                $errores[] = 'Las contraseñas nuevas no coinciden.';
            }
        }

        return $errores;
    }



    /**
     * Verifica que el usuario tenga sesión activa
     * Redirige al login si no la tiene
     */
    private function verificarAutenticacion(): void
    {
        if (empty($_SESSION['usuario_id'])) {
            $this->setFlash('error', 'Debes iniciar sesión para acceder a esta sección.');
            $this->redirect('/login');
        }
    }

    /**
     * Verifica que el usuario tenga rol de administrador
     * Retorna error 403 si no tiene permiso
     */
    private function verificarRolAdmin(): void
    {
        $rolActual = strtolower($_SESSION['usuario_rol'] ?? '');
        if ($rolActual !== 'administrador' && $rolActual !== 'admin') {
            http_response_code(403);
            $this->setFlash('error', '🚫 No tienes permisos para acceder a la gestión de usuarios.');
            $this->redirect('/dashboard');
        }
    }

    /**
     * Valida el token CSRF del formulario enviado
     * 
     * @return bool true si el token es válido
     */
    private function validarCSRF(): bool
    {
        $tokenEnviado = $_POST['csrf_token']
            ?? $_POST['_csrf_token']
            ?? $_POST['_token']
            ?? '';

        if (empty($tokenEnviado)) {
            return false;
        }

        foreach ([$_SESSION['csrf_token'] ?? '', $_SESSION['_csrf_token'] ?? ''] as $tokenSesion) {
            if ($tokenSesion !== '' && hash_equals($tokenSesion, $tokenEnviado)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Establece un mensaje flash en la sesión
     * 
     * @param string $tipo    Tipo: 'error', 'success', 'info', 'warning'
     * @param string $mensaje Mensaje a mostrar
     */
    /**
     * Establece un mensaje flash en la sesión (Adaptado para SweetAlert en app.php)
     */
    private function setFlash(string $tipo, string $mensaje): void
    {
        // Reemplazamos la lógica antigua por el formato de array requerido
        $_SESSION['flash'] = [
            'type'    => $tipo,
            'message' => $mensaje
        ];
    }

    /**
     * Redirige a una URL relativa
     * 
     * @param string $url URL de destino
     */
    private function redirect(string $url): void
    {
        $baseUrl = rtrim(getenv('APP_URL') ?: '', '/');
        $fullUrl = str_starts_with($url, 'http') ? $url : $baseUrl . $url;

        header("Location: {$fullUrl}");
        exit;
    }

    /**
     * Renderiza una vista usando el layout principal
     * 
     * @param string $view      Vista a renderizar (relativa a app/Views/)
     * @param array  $variables Variables a pasar a la vista
     */
    private function render(string $view, array $variables = []): void
    {
        extract($variables, EXTR_SKIP);

        // Asegurar que existan variables de layout
        $csrfToken  = $_SESSION['csrf_token']     ?? '';
        $theme      = $_SESSION['usuario_tema']   ?? 'light';
        $userName   = $_SESSION['usuario_nombre'] ?? '';
        $userRol    = $_SESSION['usuario_rol']    ?? '';
        $userId     = $_SESSION['usuario_id']     ?? 0;

        // Generar token CSRF si no existe
        if (empty($csrfToken)) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $csrfToken = $_SESSION['csrf_token'];
        }

        // Leer mensajes flash
        $flashError   = $_SESSION['flash_error']   ?? null;
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashInfo    = $_SESSION['flash_info']    ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['flash_info']);

        // Capturar el contenido de la vista
        ob_start();
        $viewPath = __DIR__ . "/../Views/{$view}.php";
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            echo "<p class='text-red-500'>Vista no encontrada: {$view}</p>";
        }
        $content = ob_get_clean();

        // Renderizar layout principal
        require __DIR__ . '/../Views/layouts/app.php';
    }
}
