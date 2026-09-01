<?php

declare(strict_types=1);

/**
 * Modelo de Usuarios
 * 
 * Gestiona todas las operaciones de base de datos relacionadas con usuarios,
 * autenticación, bloqueo de cuentas y configuración de tema.
 * 
 * @package App\Models
 * @author  Sistema de Facturación Pucallpa
 * @version 1.0.0
 */

namespace App\Models;

use PDO;
use PDOException;

class UserModel
{
    /** @var PDO Conexión PDO a la base de datos */
    private PDO $db;

    /**
     * Constructor - inyecta la conexión PDO
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // CONSULTAS DE BÚSQUEDA
    // =========================================================================

    /**
     * Busca un usuario por su nombre de usuario (username)
     * Incluye información del rol asociado
     * 
     * @param string $username Nombre de usuario
     * @return array|false Datos del usuario con su rol, o false si no existe
     */
    public function findByUsername(string $username): array|false
    {
        $sql = "SELECT u.*, r.nombre AS rol_nombre
                FROM usuarios u
                INNER JOIN roles r ON u.rol_id = r.id
                WHERE u.username = :username
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca un usuario por su ID
     * Incluye información del rol asociado
     * 
     * @param int $id ID del usuario
     * @return array|false Datos del usuario con su rol, o false si no existe
     */
    public function findById(int $id): array|false
    {
        $sql = "SELECT u.*, r.nombre AS rol_nombre
                FROM usuarios u
                INNER JOIN roles r ON u.rol_id = r.id
                WHERE u.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los usuarios del sistema con su rol
     * Ordenados por nombre
     * 
     * @return array Lista de todos los usuarios
     */
    public function getAll(): array
    {
        $sql = "SELECT u.id, u.nombre, u.apellidos, u.email, u.username,
                       u.activo, u.ultimo_acceso, u.created_at, u.tema,
                       u.intentos_login, u.bloqueado_hasta,
                       r.nombre AS rol_nombre, r.id AS rol_id
                FROM usuarios u
                INNER JOIN roles r ON u.rol_id = r.id
                ORDER BY u.nombre ASC, u.apellidos ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // OPERACIONES CRUD
    // =========================================================================

    /**
     * Crea un nuevo usuario en la base de datos
     * La contraseña se hashea con bcrypt automáticamente
     * 
     * @param array $data Datos del usuario:
     *                    - nombre: string (requerido)
     *                    - apellidos: string (requerido)
     *                    - email: string (requerido)
     *                    - username: string (requerido)
     *                    - password: string (requerido, en texto plano)
     *                    - rol_id: int (requerido)
     *                    - activo: int (opcional, default 1)
     *                    - tema: string (opcional, default 'light')
     * @return int|false ID del usuario creado, o false en caso de error
     */
    public function create(array $data): int|false
    {
        $sql = "INSERT INTO usuarios 
                    (nombre, apellidos, email, username, password, rol_id, activo, tema, created_at)
                VALUES 
                    (:nombre, :apellidos, :email, :username, :password, :rol_id, :activo, :tema, NOW())";

        // Hash de contraseña con bcrypt (costo 12 para mayor seguridad)
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre',    htmlspecialchars($data['nombre'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
        $stmt->bindValue(':apellidos', htmlspecialchars($data['apellidos'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
        $stmt->bindValue(':email',     filter_var($data['email'], FILTER_SANITIZE_EMAIL), PDO::PARAM_STR);
        $stmt->bindValue(':username',  htmlspecialchars($data['username'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
        $stmt->bindValue(':password',  $passwordHash, PDO::PARAM_STR);
        $stmt->bindValue(':rol_id',    (int)$data['rol_id'], PDO::PARAM_INT);
        $stmt->bindValue(':activo',    (int)($data['activo'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':tema',      $data['tema'] ?? 'light', PDO::PARAM_STR);

        if ($stmt->execute()) {
            return (int)$this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Actualiza los datos de un usuario existente
     * No actualiza la contraseña (usar updatePassword() para eso)
     * 
     * @param int   $id   ID del usuario a actualizar
     * @param array $data Datos a actualizar
     * @return bool true si se actualizó correctamente
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE usuarios SET
                    nombre    = :nombre,
                    apellidos = :apellidos,
                    email     = :email,
                    username  = :username,
                    rol_id    = :rol_id,
                    activo    = :activo,
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':nombre',    htmlspecialchars($data['nombre'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
        $stmt->bindValue(':apellidos', htmlspecialchars($data['apellidos'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
        $stmt->bindValue(':email',     filter_var($data['email'], FILTER_SANITIZE_EMAIL), PDO::PARAM_STR);
        $stmt->bindValue(':username',  htmlspecialchars($data['username'], ENT_QUOTES, 'UTF-8'), PDO::PARAM_STR);
        $stmt->bindValue(':rol_id',    (int)$data['rol_id'], PDO::PARAM_INT);
        $stmt->bindValue(':activo',    (int)($data['activo'] ?? 1), PDO::PARAM_INT);
        $stmt->bindValue(':id',        $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Elimina un usuario por su ID (eliminación lógica = desactivar)
     * Nunca se elimina físicamente para mantener la integridad referencial
     * 
     * @param int $id ID del usuario
     * @return bool true si se eliminó/desactivó correctamente
     */
    public function delete(int $id): bool
    {
        // Eliminación lógica: marcar como inactivo en lugar de eliminar físicamente
        $sql = "UPDATE usuarios SET activo = 0, updated_at = NOW() WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Actualiza la contraseña de un usuario
     * Hashea la nueva contraseña con bcrypt
     * 
     * @param int    $userId      ID del usuario
     * @param string $newPassword Nueva contraseña en texto plano
     * @return bool true si se actualizó correctamente
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        $sql = "UPDATE usuarios 
                SET password = :password, updated_at = NOW() 
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':password', $passwordHash, PDO::PARAM_STR);
        $stmt->bindValue(':id',       $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // =========================================================================
    // CONFIGURACIÓN DE TEMA
    // =========================================================================

    /**
     * Actualiza la preferencia de tema del usuario (oscuro/claro)
     * 
     * @param int    $userId ID del usuario
     * @param string $theme  Tema a guardar: 'dark' o 'light'
     * @return bool true si se actualizó correctamente
     */
    public function updateTheme(int $userId, string $theme): bool
    {
        // Validar que sea un valor permitido
        $temaValido = in_array($theme, ['dark', 'light'], true) ? $theme : 'light';

        $sql = "UPDATE usuarios SET tema = :tema WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tema', $temaValido, PDO::PARAM_STR);
        $stmt->bindValue(':id',   $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // =========================================================================
    // GESTIÓN DE SESIÓN Y ACCESOS
    // =========================================================================

    /**
     * Actualiza la fecha y hora del último acceso del usuario
     * Se llama al iniciar sesión exitosamente
     * 
     * @param int $userId ID del usuario
     * @return bool true si se actualizó correctamente
     */
    public function updateLastAccess(int $userId): bool
    {
        $sql = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // =========================================================================
    // SEGURIDAD: BLOQUEO DE CUENTAS Y RATE LIMITING
    // =========================================================================

    /**
     * Incrementa el contador de intentos de login fallidos
     * 
     * @param int $userId ID del usuario
     * @return bool true si se actualizó correctamente
     */
    public function incrementLoginAttempts(int $userId): bool
    {
        $sql = "UPDATE usuarios 
                SET intentos_login = intentos_login + 1, updated_at = NOW() 
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Reinicia el contador de intentos de login a cero
     * Se llama al iniciar sesión exitosamente
     * 
     * @param int $userId ID del usuario
     * @return bool true si se actualizó correctamente
     */
    public function resetLoginAttempts(int $userId): bool
    {
        $sql = "UPDATE usuarios 
                SET intentos_login = 0, bloqueado_hasta = NULL, updated_at = NOW() 
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Bloquea temporalmente la cuenta de un usuario
     * 
     * @param int $userId  ID del usuario a bloquear
     * @param int $minutes Minutos de bloqueo (default: 15)
     * @return bool true si se aplicó el bloqueo correctamente
     */
    public function lockUser(int $userId, int $minutes = 15): bool
    {
        $sql = "UPDATE usuarios 
                SET bloqueado_hasta = DATE_ADD(NOW(), INTERVAL :minutos MINUTE),
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':minutos', $minutes, PDO::PARAM_INT);
        $stmt->bindValue(':id',      $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Verifica si un usuario está actualmente bloqueado
     * 
     * @param int $userId ID del usuario
     * @return bool true si el usuario está bloqueado, false si puede iniciar sesión
     */
    public function isLocked(int $userId): bool
    {
        $sql = "SELECT bloqueado_hasta FROM usuarios WHERE id = :id LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || $result['bloqueado_hasta'] === null) {
            return false;
        }

        // Comparar la fecha de bloqueo con la fecha/hora actual
        return strtotime($result['bloqueado_hasta']) > time();
    }

    /**
     * Obtiene la fecha/hora hasta la cual está bloqueado el usuario
     * 
     * @param int $userId ID del usuario
     * @return string|null Fecha de desbloqueo o null si no está bloqueado
     */
    public function getLockExpiry(int $userId): string|null
    {
        $sql = "SELECT bloqueado_hasta FROM usuarios WHERE id = :id LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        return $result['bloqueado_hasta'];
    }

    // =========================================================================
    // ROLES
    // =========================================================================

    /**
     * Obtiene todos los roles disponibles en el sistema
     * 
     * @return array Lista de roles
     */
    public function getRoles(): array
    {
        $sql = "SELECT id, nombre, descripcion FROM roles ORDER BY nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function roleExists(int $roleId): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM roles WHERE id = :id');
        $stmt->bindValue(':id', $roleId, PDO::PARAM_INT);
        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si un username ya existe en la base de datos
     * Útil para validación al crear/editar usuarios
     * 
     * @param string   $username  Username a verificar
     * @param int|null $excludeId ID a excluir (para edición del mismo usuario)
     * @return bool true si el username ya existe
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $sql = "SELECT COUNT(*) FROM usuarios WHERE username = :username AND id != :excludeId";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':username',  $username, PDO::PARAM_STR);
            $stmt->bindValue(':excludeId', $excludeId, PDO::PARAM_INT);
        } else {
            $sql = "SELECT COUNT(*) FROM usuarios WHERE username = :username";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        }

        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si un email ya existe en la base de datos
     * 
     * @param string   $email     Email a verificar
     * @param int|null $excludeId ID a excluir (para edición del mismo usuario)
     * @return bool true si el email ya existe
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :excludeId";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email',     $email, PDO::PARAM_STR);
            $stmt->bindValue(':excludeId', $excludeId, PDO::PARAM_INT);
        } else {
            $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        }

        $stmt->execute();

        return (int)$stmt->fetchColumn() > 0;
    }
}
