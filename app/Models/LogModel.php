<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;

/**
 * Modelo de Registros de Auditoría y Logs del Sistema
 * 
 * Gestiona el registro de actividades, accesos y eventos del sistema
 * para fines de auditoría, seguridad y depuración.
 * 
 * @package App\Models
 * @author  Sistema de Facturación Pucallpa
 * @version 1.0.0
 */
class LogModel
{
    /** @var PDO Instancia de conexión PDO */
    private PDO $db;

    /**
     * Constructor del modelo
     * 
     * @param PDO $db Instancia PDO inyectada
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // =========================================================
    // SECCIÓN: LOGS DE ACTIVIDAD GENERAL
    // =========================================================

    /**
     * Registra una acción de usuario en el log de actividad.
     * 
     * Este método se usa para auditar cualquier operación relevante del sistema:
     * creación de documentos, cambios de configuración, eliminaciones, etc.
     * 
     * @param int|null $userId      ID del usuario que realiza la acción (null = sistema)
     * @param string   $accion      Acción realizada: 'crear', 'editar', 'eliminar', 'ver', etc.
     * @param string   $modulo      Módulo del sistema: 'ventas', 'compras', 'config', etc.
     * @param string   $descripcion Descripción detallada de la acción realizada
     * @param mixed    $datosExtra  Datos adicionales en formato array (se guardan como JSON)
     * @return bool true si se registró correctamente
     */
    public function log(
        ?int $userId,
        string $accion,
        string $modulo,
        string $descripcion,
        mixed $datosExtra = null
    ): bool {
        try {
            $ip          = $this->getClientIP();
            $userAgent   = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
            $datosJson   = $datosExtra !== null ? json_encode($datosExtra, JSON_UNESCAPED_UNICODE) : null;

            $stmt = $this->db->prepare("
                INSERT INTO logs_actividad 
                    (usuario_id, accion, modulo, descripcion, datos_extra, ip_address, user_agent, creado_en)
                VALUES 
                    (:usuario_id, :accion, :modulo, :descripcion, :datos_extra, :ip_address, :user_agent, NOW())
            ");

            $stmt->execute([
                ':usuario_id'  => $userId,
                ':accion'      => substr($accion, 0, 50),
                ':modulo'      => substr($modulo, 0, 50),
                ':descripcion' => substr($descripcion, 0, 500),
                ':datos_extra' => $datosJson,
                ':ip_address'  => $ip,
                ':user_agent'  => substr($userAgent, 0, 255),
            ]);

            return true;

        } catch (PDOException $e) {
            // No lanzar excepción para no interrumpir el flujo principal
            error_log("LogModel::log - Error PDO: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    // SECCIÓN: LOGS DE ACCESO Y AUTENTICACIÓN
    // =========================================================

    /**
     * Registra un intento de acceso al sistema (login/logout).
     * 
     * Guarda tanto los accesos exitosos como los fallidos, incluyendo
     * el motivo del fallo para análisis de seguridad.
     * 
     * @param string $username  Nombre de usuario que intentó acceder
     * @param string $ip        Dirección IP del cliente
     * @param bool   $exitoso   true si el acceso fue exitoso, false si falló
     * @param string $motivo    Motivo del fallo (vacío si fue exitoso): 'credenciales_invalidas', 'cuenta_bloqueada', etc.
     * @param int|null $userId  ID del usuario si el login fue exitoso
     * @return bool true si se registró correctamente
     */
    public function logAcceso(
        string $username,
        string $ip,
        bool $exitoso,
        string $motivo = '',
        ?int $userId = null
    ): bool {
        try {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';

            $stmt = $this->db->prepare("
                INSERT INTO logs_acceso 
                    (usuario_id, username, ip_address, user_agent, exitoso, motivo_fallo, creado_en)
                VALUES 
                    (:usuario_id, :username, :ip_address, :user_agent, :exitoso, :motivo_fallo, NOW())
            ");

            $stmt->execute([
                ':usuario_id'   => $userId,
                ':username'     => substr($username, 0, 100),
                ':ip_address'   => $ip,
                ':user_agent'   => substr($userAgent, 0, 255),
                ':exitoso'      => $exitoso ? 1 : 0,
                ':motivo_fallo' => $exitoso ? null : substr($motivo, 0, 200),
            ]);

            return true;

        } catch (PDOException $e) {
            error_log("LogModel::logAcceso - Error PDO: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registra el cierre de sesión de un usuario.
     * 
     * @param int    $userId   ID del usuario que cierra sesión
     * @param string $username Nombre de usuario
     * @return bool true si se registró correctamente
     */
    public function logLogout(int $userId, string $username): bool
    {
        return $this->log(
            $userId,
            'logout',
            'autenticacion',
            "Usuario '{$username}' cerró sesión"
        );
    }

    // =========================================================
    // SECCIÓN: CONSULTA DE LOGS
    // =========================================================

    /**
     * Obtiene los logs de actividad con filtros opcionales.
     * 
     * Soporta filtrado por usuario, módulo, acción, rango de fechas
     * y paginación para una consulta eficiente.
     * 
     * @param array<string, mixed> $filters Filtros:
     *   - usuario_id: int (opcional)
     *   - modulo: string (opcional)
     *   - accion: string (opcional)
     *   - fecha_desde: string Y-m-d (opcional)
     *   - fecha_hasta: string Y-m-d (opcional)
     *   - pagina: int (por defecto 1)
     *   - por_pagina: int (por defecto 50)
     * @return array{datos: array, total: int, paginas: int} Resultados paginados
     */
    public function getLogs(array $filters = []): array
    {
        try {
            // Construir condiciones dinámicas de forma segura
            $condiciones = ['1 = 1'];
            $params      = [];

            if (!empty($filters['usuario_id'])) {
                $condiciones[] = 'la.usuario_id = :usuario_id';
                $params[':usuario_id'] = (int) $filters['usuario_id'];
            }

            if (!empty($filters['modulo'])) {
                $condiciones[] = 'la.modulo = :modulo';
                $params[':modulo'] = $filters['modulo'];
            }

            if (!empty($filters['accion'])) {
                $condiciones[] = 'la.accion = :accion';
                $params[':accion'] = $filters['accion'];
            }

            if (!empty($filters['fecha_desde'])) {
                $condiciones[] = 'DATE(la.creado_en) >= :fecha_desde';
                $params[':fecha_desde'] = $filters['fecha_desde'];
            }

            if (!empty($filters['fecha_hasta'])) {
                $condiciones[] = 'DATE(la.creado_en) <= :fecha_hasta';
                $params[':fecha_hasta'] = $filters['fecha_hasta'];
            }

            $where   = implode(' AND ', $condiciones);
            $pagina  = max(1, (int) ($filters['pagina'] ?? 1));
            $porPagina = min(200, max(10, (int) ($filters['por_pagina'] ?? 50)));
            $offset  = ($pagina - 1) * $porPagina;

            // Contar total de registros
            $stmtCount = $this->db->prepare("
                SELECT COUNT(*) FROM logs_actividad la WHERE {$where}
            ");
            $stmtCount->execute($params);
            $total = (int) $stmtCount->fetchColumn();

            // Obtener registros paginados
            $stmtData = $this->db->prepare("
                SELECT 
                    la.id,
                    la.accion,
                    la.modulo,
                    la.descripcion,
                    la.ip_address,
                    DATE_FORMAT(la.creado_en, '%d/%m/%Y %H:%i:%s') AS fecha_hora,
                    u.nombre AS usuario_nombre,
                    u.username AS usuario_username
                FROM logs_actividad la
                LEFT JOIN usuarios u ON la.usuario_id = u.id
                WHERE {$where}
                ORDER BY la.id DESC
                LIMIT :limit OFFSET :offset
            ");

            foreach ($params as $key => $val) {
                $stmtData->bindValue($key, $val);
            }
            $stmtData->bindValue(':limit', $porPagina, PDO::PARAM_INT);
            $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmtData->execute();

            return [
                'datos'   => $stmtData->fetchAll(PDO::FETCH_ASSOC),
                'total'   => $total,
                'paginas' => (int) ceil($total / $porPagina),
                'pagina_actual' => $pagina,
                'por_pagina'    => $porPagina,
            ];

        } catch (PDOException $e) {
            error_log("LogModel::getLogs - Error PDO: " . $e->getMessage());
            return ['datos' => [], 'total' => 0, 'paginas' => 0, 'pagina_actual' => 1, 'por_pagina' => 50];
        }
    }

    /**
     * Obtiene los logs de acceso con filtros opcionales.
     * 
     * Permite analizar intentos de login, accesos fallidos y la
     * actividad de autenticación en general.
     * 
     * @param array<string, mixed> $filters Filtros:
     *   - username: string (opcional)
     *   - ip_address: string (opcional)
     *   - exitoso: bool (opcional, null = todos)
     *   - fecha_desde: string Y-m-d (opcional)
     *   - fecha_hasta: string Y-m-d (opcional)
     *   - limit: int (por defecto 20)
     * @return array<int, array<string, mixed>> Lista de registros de acceso
     */
    public function getAccesos(array $filters = []): array
    {
        try {
            $condiciones = ['1 = 1'];
            $params      = [];

            if (!empty($filters['username'])) {
                $condiciones[] = 'la.username LIKE :username';
                $params[':username'] = '%' . $filters['username'] . '%';
            }

            if (!empty($filters['ip_address'])) {
                $condiciones[] = 'la.ip_address = :ip_address';
                $params[':ip_address'] = $filters['ip_address'];
            }

            if (isset($filters['exitoso']) && $filters['exitoso'] !== null) {
                $condiciones[] = 'la.exitoso = :exitoso';
                $params[':exitoso'] = $filters['exitoso'] ? 1 : 0;
            }

            if (!empty($filters['fecha_desde'])) {
                $condiciones[] = 'DATE(la.creado_en) >= :fecha_desde';
                $params[':fecha_desde'] = $filters['fecha_desde'];
            }

            if (!empty($filters['fecha_hasta'])) {
                $condiciones[] = 'DATE(la.creado_en) <= :fecha_hasta';
                $params[':fecha_hasta'] = $filters['fecha_hasta'];
            }

            $where = implode(' AND ', $condiciones);
            $limit = min(500, max(10, (int) ($filters['limit'] ?? 20)));

            $stmt = $this->db->prepare("
                SELECT 
                    la.id,
                    la.username,
                    la.ip_address,
                    la.exitoso,
                    la.motivo_fallo,
                    DATE_FORMAT(la.creado_en, '%d/%m/%Y %H:%i:%s') AS fecha_hora,
                    SUBSTRING(la.user_agent, 1, 100) AS user_agent_corto
                FROM logs_acceso la
                WHERE {$where}
                ORDER BY la.id DESC
                LIMIT :limit
            ");

            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("LogModel::getAccesos - Error PDO: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene los últimos N accesos para mostrar en el panel de seguridad.
     * 
     * @param int $limit Número de registros a retornar (por defecto 20)
     * @return array<int, array<string, mixed>> Últimos accesos
     */
    public function getUltimosAccesos(int $limit = 20): array
    {
        return $this->getAccesos(['limit' => $limit]);
    }

    /**
     * Obtiene estadísticas de accesos fallidos por IP.
     * 
     * Útil para detectar ataques de fuerza bruta y bloquear IPs sospechosas.
     * 
     * @param int $horas  Período de tiempo en horas a analizar (por defecto 24)
     * @param int $minFallos Mínimo de fallos para incluir en el resultado (por defecto 3)
     * @return array<int, array<string, mixed>> IPs con múltiples fallos
     */
    public function getIPsConFallos(int $horas = 24, int $minFallos = 3): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ip_address,
                    COUNT(*) AS total_fallos,
                    MAX(creado_en) AS ultimo_intento,
                    GROUP_CONCAT(DISTINCT username ORDER BY creado_en DESC SEPARATOR ', ') AS usuarios_intentados
                FROM logs_acceso
                WHERE exitoso = 0
                  AND creado_en >= DATE_SUB(NOW(), INTERVAL :horas HOUR)
                GROUP BY ip_address
                HAVING COUNT(*) >= :min_fallos
                ORDER BY total_fallos DESC
                LIMIT 50
            ");
            $stmt->bindValue(':horas', $horas, PDO::PARAM_INT);
            $stmt->bindValue(':min_fallos', $minFallos, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("LogModel::getIPsConFallos - Error PDO: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================
    // SECCIÓN: MANTENIMIENTO DE LOGS
    // =========================================================

    /**
     * Elimina logs de actividad antiguos para mantener el rendimiento de la BD.
     * 
     * Se recomienda ejecutar este método periódicamente via cron job.
     * Por defecto conserva los últimos 90 días de logs.
     * 
     * @param int $daysToKeep Días de historial a conservar (por defecto 90)
     * @return int Número de registros activos (-1 en caso de error)
     */
    public function cleanOldLogs(int $daysToKeep = 90): int
    {
        try {
            $this->db->beginTransaction();

            // Eliminar logs de actividad antiguos
            $stmtActividad = $this->db->prepare("
                DELETE FROM logs_actividad
                WHERE creado_en < DATE_SUB(NOW(), INTERVAL :dias DAY)
            ");
            $stmtActividad->bindValue(':dias', $daysToKeep, PDO::PARAM_INT);
            $stmtActividad->execute();
            $activosActividad = $stmtActividad->rowCount();

            // Eliminar logs de acceso más antiguos que el doble del período
            // (conservar historial de seguridad por más tiempo)
            $diasAcceso = $daysToKeep * 2;
            $stmtAcceso = $this->db->prepare("
                DELETE FROM logs_acceso
                WHERE creado_en < DATE_SUB(NOW(), INTERVAL :dias DAY)
            ");
            $stmtAcceso->bindValue(':dias', $diasAcceso, PDO::PARAM_INT);
            $stmtAcceso->execute();
            $activosAcceso = $stmtAcceso->rowCount();

            $this->db->commit();

            $totalEliminados = $activosActividad + $activosAcceso;
            error_log("LogModel::cleanOldLogs - Eliminados: {$activosActividad} actividad, {$activosAcceso} acceso");

            return $totalEliminados;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("LogModel::cleanOldLogs - Error PDO: " . $e->getMessage());
            return -1;
        }
    }

    /**
     * Exporta los logs a formato CSV para descarga.
     * 
     * Genera un CSV con todos los logs de actividad en el rango
     * de fechas especificado para auditoría externa.
     * 
     * @param string $fechaDesde Fecha inicio en formato Y-m-d
     * @param string $fechaHasta Fecha fin en formato Y-m-d
     * @return string Contenido del CSV como string
     */
    public function exportarCSV(string $fechaDesde, string $fechaHasta): string
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    la.id,
                    IFNULL(u.username, 'sistema') AS usuario,
                    la.accion,
                    la.modulo,
                    la.descripcion,
                    la.ip_address,
                    la.creado_en
                FROM logs_actividad la
                LEFT JOIN usuarios u ON la.usuario_id = u.id
                WHERE DATE(la.creado_en) BETWEEN :fecha_desde AND :fecha_hasta
                ORDER BY la.id DESC
            ");
            $stmt->execute([
                ':fecha_desde' => $fechaDesde,
                ':fecha_hasta' => $fechaHasta,
            ]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Generar CSV en memoria
            $output = fopen('php://temp', 'r+');

            // Encabezados del CSV
            fputcsv($output, ['ID', 'Usuario', 'Acción', 'Módulo', 'Descripción', 'IP', 'Fecha/Hora']);

            // Datos
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['id'],
                    $log['usuario'],
                    $log['accion'],
                    $log['modulo'],
                    $log['descripcion'],
                    $log['ip_address'],
                    $log['creado_en'],
                ]);
            }

            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);

            return $csv ?: '';

        } catch (PDOException $e) {
            error_log("LogModel::exportarCSV - Error PDO: " . $e->getMessage());
            return '';
        }
    }

    // =========================================================
    // SECCIÓN: MÉTODOS PRIVADOS DE UTILIDAD
    // =========================================================

    /**
     * Obtiene la IP real del cliente, considerando proxies y balanceadores.
     * 
     * Revisa múltiples headers HTTP en orden de confiabilidad para
     * determinar la IP real del cliente final.
     * 
     * @return string Dirección IP del cliente
     */
    private function getClientIP(): string
    {
        $headersConfiables = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($headersConfiables as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For puede contener múltiples IPs separadas por coma
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback a REMOTE_ADDR (puede ser IP privada en entornos locales)
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
