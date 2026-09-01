<?php

declare(strict_types=1);

/**
 * =============================================================
 * MIDDLEWARE DE ROLES Y PERMISOS
 * Sistema de Facturación - Empresa de Informática Pucallpa
 * =============================================================
 *
 * Verifica que el usuario autenticado tenga el rol necesario
 * para acceder a una ruta o recurso protegido.
 */

namespace App\Middleware;

class RoleMiddleware
{
    /**
     * Mapeo de nombres de rol a sus IDs numéricos.
     */
    private const ROLES_MAP = [
        'admin'    => 1,
        'vendedor' => 2,
        'contador' => 3,
        'almacen'  => 4,
    ];

    /**
     * Ejecutar el middleware de verificación de rol.
     *
     * @param int|string|array $requiredRole Rol requerido. Puede ser:
     *   - int:    ID numérico del rol (ej: 1)
     *   - string: Nombre del rol (ej: 'admin')
     *   - array:  Lista de roles permitidos (ej: [1, 2] o ['admin', 'vendedor'])
     */
    public function handle(int|string|array $requiredRole): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Verificar autenticación primero
        if (empty($_SESSION['user'])) {
            $this->redirectUnauthorized('Debes iniciar sesión para acceder.');
        }

        $rolUsuario = (int) ($_SESSION['user']['rol_id'] ?? 0);

        // Normalizar el rol requerido a un array de IDs numéricos
        $rolesPermitidos = $this->normalizeRoles($requiredRole);

        // Verificar si el usuario tiene alguno de los roles permitidos
        if (!in_array($rolUsuario, $rolesPermitidos, true)) {
            $this->redirectForbidden($rolesPermitidos);
        }
    }

    /**
     * Verificar si el usuario tiene acceso a un módulo específico.
     * Retorna bool en lugar de redirigir (útil para vistas condicionales).
     *
     * @param  int|string|array $requiredRole Rol requerido
     * @return bool                           true si tiene acceso
     */
    public function check(int|string|array $requiredRole): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['user'])) {
            return false;
        }

        $rolUsuario      = (int) ($_SESSION['user']['rol_id'] ?? 0);
        $rolesPermitidos = $this->normalizeRoles($requiredRole);

        return in_array($rolUsuario, $rolesPermitidos, true);
    }

    /**
     * Verificar acceso a una acción específica (CRUD).
     * Los administradores siempre tienen acceso.
     * Los vendedores tienen acceso a lectura y creación de ventas.
     *
     * Tabla de permisos por rol:
     * | Acción   | Admin | Vendedor | Contador | Almacén |
     * |----------|-------|----------|----------|---------|
     * | ver      |  ✓    |    ✓     |    ✓     |    ✓    |
     * | crear    |  ✓    |    ✓     |    ✗     |    ✓    |
     * | editar   |  ✓    |    ✗     |    ✗     |    ✓    |
     * | eliminar |  ✓    |    ✗     |    ✗     |    ✗    |
     *
     * @param  string $modulo Nombre del módulo (ej: 'productos', 'ventas')
     * @param  string $accion Acción a verificar: 'ver', 'crear', 'editar', 'eliminar'
     * @return bool
     */
    public function canDo(string $modulo, string $accion): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $rolId = (int) ($_SESSION['user']['rol_id'] ?? 0);

        // Los administradores tienen acceso total
        if ($rolId === self::ROLES_MAP['admin']) {
            return true;
        }

        // Permisos por módulo y rol
        $permisosModulos = [
            'productos' => [
                'ver'      => [self::ROLES_MAP['vendedor'], self::ROLES_MAP['contador'], self::ROLES_MAP['almacen']],
                'crear'    => [self::ROLES_MAP['almacen']],
                'editar'   => [self::ROLES_MAP['almacen']],
                'eliminar' => [],
            ],
            'ventas' => [
                'ver'      => [self::ROLES_MAP['vendedor'], self::ROLES_MAP['contador']],
                'crear'    => [self::ROLES_MAP['vendedor']],
                'editar'   => [],
                'eliminar' => [],
            ],
            'compras' => [
                'ver'      => [self::ROLES_MAP['contador'], self::ROLES_MAP['almacen']],
                'crear'    => [self::ROLES_MAP['almacen']],
                'editar'   => [self::ROLES_MAP['almacen']],
                'eliminar' => [],
            ],
            'reportes' => [
                'ver'      => [self::ROLES_MAP['contador'], self::ROLES_MAP['vendedor']],
                'crear'    => [],
                'editar'   => [],
                'eliminar' => [],
            ],
            'clientes' => [
                'ver'      => [self::ROLES_MAP['vendedor'], self::ROLES_MAP['contador']],
                'crear'    => [self::ROLES_MAP['vendedor']],
                'editar'   => [self::ROLES_MAP['vendedor']],
                'eliminar' => [],
            ],
            'usuarios' => [
                'ver'      => [],
                'crear'    => [],
                'editar'   => [],
                'eliminar' => [],
            ],
            'configuracion' => [
                'ver'      => [],
                'crear'    => [],
                'editar'   => [],
                'eliminar' => [],
            ],
        ];

        $rolesPermitidos = $permisosModulos[$modulo][$accion] ?? [];
        return in_array($rolId, $rolesPermitidos, true);
    }

    /**
     * Normalizar roles a un array de IDs numéricos.
     *
     * @param  int|string|array $roles Roles a normalizar
     * @return int[]                   Array de IDs numéricos
     */
    private function normalizeRoles(int|string|array $roles): array
    {
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        return array_map(function ($rol) {
            if (is_int($rol)) {
                return $rol;
            }
            return self::ROLES_MAP[strtolower(trim($rol))] ?? 0;
        }, $roles);
    }

    /**
     * Redirigir al login indicando que debe autenticarse.
     *
     * @param  string $mensaje Mensaje de error
     * @throws never           Siempre termina la ejecución
     */
    private function redirectUnauthorized(string $mensaje): never
    {
        $_SESSION['_flash_messages'][] = [
            'type'    => 'warning',
            'message' => $mensaje,
        ];

        $loginUrl = (defined('APP_URL') ? APP_URL : '') . '/login';
        header("Location: {$loginUrl}", true, 302);
        exit;
    }

    /**
     * Redirigir a una página de acceso denegado con mensaje descriptivo.
     *
     * @param  int[]  $rolesPermitidos IDs de roles con acceso permitido
     * @throws never                   Siempre termina la ejecución
     */
    private function redirectForbidden(array $rolesPermitidos): never
    {
        // Construir nombre descriptivo de los roles permitidos
        $nombresRoles = [];
        $rolesInverso = array_flip(self::ROLES_MAP);
        foreach ($rolesPermitidos as $rolId) {
            $nombre        = $rolesInverso[$rolId] ?? "Rol #{$rolId}";
            $nombresRoles[] = ucfirst($nombre);
        }

        $listaRoles = implode(', ', $nombresRoles);
        $mensaje    = "No tienes permisos para acceder a esta sección. Se requiere rol: {$listaRoles}.";

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['_flash_messages'][] = [
            'type'    => 'error',
            'message' => $mensaje,
        ];

        // Registrar intento de acceso no autorizado
        $userId  = $_SESSION['user']['id']  ?? null;
        $rolId   = $_SESSION['user']['rol_id'] ?? null;
        $ruta    = $_SERVER['REQUEST_URI'] ?? 'desconocida';

        try {
            logActivity(
                'acceso_denegado',
                "Intento de acceso a [{$ruta}] con rol [{$rolId}]. Roles requeridos: [{$listaRoles}]",
                $userId !== null ? (int) $userId : null
            );
        } catch (\Throwable) {
            // No interrumpir el flujo si el log falla
        }

        // Detectar si es AJAX
        $esAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($esAjax) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'FORBIDDEN',
                'message' => $mensaje,
            ]);
            exit;
        }

        // Redirigir al dashboard con mensaje de error
        $dashboardUrl = (defined('APP_URL') ? APP_URL : '') . '/dashboard';
        header("Location: {$dashboardUrl}", true, 302);
        exit;
    }
}
