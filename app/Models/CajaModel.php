<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Modelo de Caja
 *
 * Gestiona las operaciones de apertura, cierre y consulta de caja.
 * Cada apertura de caja representa una sesión de trabajo del cajero/vendedor.
 * Se registra el saldo inicial y al cierre se compara con las ventas realizadas.
 *
 * @package App\Models
 */
class CajaModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // ESTADO DE CAJA
    // =========================================================================

    /**
     * Obtiene la sesión de caja abierta para una caja específica
     *
     * @param int $cajaId ID de la caja física
     * @return array|null Datos de la apertura activa o null si está cerrada
     */
    public function getCajaAbierta(int $cajaId): ?array
    {
        $sql = "
            SELECT
                ca.*,
                'Caja Principal' AS caja_nombre,
                'Caja Principal' AS caja_descripcion,
                u.nombre        AS usuario_nombre,
                u.apellidos     AS usuario_apellidos
            FROM caja_aperturas ca
            INNER JOIN usuarios u  ON ca.usuario_id = u.id
            WHERE ca.caja_id = :caja_id
              AND ca.estado  = 'abierta'
            ORDER BY ca.fecha_apertura DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':caja_id', $cajaId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtiene la caja abierta del usuario actual (cualquier caja)
     *
     * @param int $userId ID del usuario
     * @return array|null Apertura activa del usuario o null si no tiene caja abierta
     */
    public function getCajaAbiertaByUsuario(int $userId): ?array
    {
        $sql = "
            SELECT
                ca.*,
                'Caja Principal' AS caja_nombre,
                'Caja Principal' AS caja_descripcion,
                u.nombre        AS usuario_nombre,
                u.apellidos     AS usuario_apellidos
            FROM caja_aperturas ca
            INNER JOIN usuarios u  ON ca.usuario_id = u.id
            WHERE ca.usuario_id = :usuario_id
              AND ca.estado     = 'abierta'
            ORDER BY ca.fecha_apertura DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':usuario_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getCajaPrincipalAbierta(int $cajaId = 1): ?array
    {
        return $this->getCajaAbierta($cajaId);
    }

    // =========================================================================
    // APERTURA Y CIERRE
    // =========================================================================

    /**
     * Abre una nueva sesión de caja
     *
     * @param int   $cajaId          ID de la caja física
     * @param int   $userId          ID del usuario que abre la caja
     * @param float $saldoInicial    Saldo inicial en soles (PEN)
     * @param float $saldoInicialUSD Saldo inicial en dólares (USD)
     * @return int ID de la apertura de caja creada
     * @throws RuntimeException Si la caja ya está abierta o falla la apertura
     */
    public function abrir(int $cajaId, int $userId, float $saldoInicial, float $saldoInicialUSD = 0.00): int
    {
        // Una caja física compartida solo puede tener una apertura activa.
        $cajaActual = $this->getCajaAbierta($cajaId);
        if ($cajaActual) {
            return (int)$cajaActual['id'];
        }

        $sql = "
            INSERT INTO caja_aperturas (
                caja_id, usuario_id,
                saldo_inicial_pen, saldo_inicial_usd,
                estado, fecha_apertura
            ) VALUES (
                :caja_id, :usuario_id,
                :saldo_inicial, :saldo_inicial_usd,
                'abierta', NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':caja_id'          => $cajaId,
            ':usuario_id'       => $userId,
            ':saldo_inicial'    => $saldoInicial,
            ':saldo_inicial_usd'=> $saldoInicialUSD,
        ]);

        $aperturaId = (int)$this->db->lastInsertId();

        // (La actualización de la tabla 'cajas' se eliminó porque no existe)

        return $aperturaId;
    }

    /**
     * Cierra una sesión de caja con el arqueo correspondiente
     *
     * @param int   $aperturaId ID de la apertura de caja a cerrar
     * @param array $datos      Datos del cierre:
     *   - saldo_final_efectivo: monto contado en efectivo
     *   - saldo_final_efectivo_usd: monto contado en dólares
     *   - notas: observaciones del cajero
     *   - [metodo]_monto_declarado: montos declarados por método de pago
     * @return bool True si se cerró correctamente
     * @throws RuntimeException Si la apertura no existe o ya está cerrada
     */
    public function cerrar(int $aperturaId, array $datos): bool
    {
        try {
            $this->db->beginTransaction();

            // Bloquear la apertura evita que dos usuarios cierren la misma sesión.
            $sqlApertura = "SELECT * FROM caja_aperturas WHERE id = :id AND estado = 'abierta' LIMIT 1 FOR UPDATE";
            $stmtAp = $this->db->prepare($sqlApertura);
            $stmtAp->bindValue(':id', $aperturaId, PDO::PARAM_INT);
            $stmtAp->execute();
            $apertura = $stmtAp->fetch(PDO::FETCH_ASSOC);

            if (!$apertura) {
                throw new RuntimeException("La apertura de caja #{$aperturaId} no existe o ya está cerrada.");
            }

            $cierre = $this->getCierreCalculado($aperturaId, $apertura);

            // Actualizar apertura como cerrada
            $sqlCerrar = "
                UPDATE caja_aperturas SET
                    estado               = 'cerrada',
                    fecha_cierre         = NOW(),
                    usuario_cierre_id    = :usuario_cierre_id,
                    saldo_final_efectivo_pen = :saldo_final_efectivo,
                    saldo_final_usd = :saldo_final_efectivo_usd,
                    total_ventas = :total_ventas_sistema,
                    diferencia      = :diferencia,
                    notas_cierre         = :notas
                WHERE id = :id
            ";
            $stmtCerrar = $this->db->prepare($sqlCerrar);
            $stmtCerrar->execute([
                ':saldo_final_efectivo'    => $cierre['efectivo_sistema_pen'],
                ':saldo_final_efectivo_usd'=> $cierre['efectivo_sistema_usd'],
                ':usuario_cierre_id'       => $datos['usuario_cierre_id'] ?? null,
                ':total_ventas_sistema'    => $cierre['total_ventas_pen'],
                ':diferencia'              => 0,
                ':notas'                   => $datos['notas'] ?? null,
                ':id'                      => $aperturaId,
            ]);

            // (La actualización de la tabla 'cajas' se eliminó porque no existe)

            $this->db->commit();
            return true;

        } catch (PDOException | RuntimeException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException("Error al cerrar caja: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Calcula el saldo que debe quedar según la apertura y los pagos registrados.
     */
    public function getCierreCalculado(int $aperturaId, ?array $apertura = null): array
    {
        if ($apertura === null) {
            $stmt = $this->db->prepare("SELECT * FROM caja_aperturas WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $aperturaId]);
            $apertura = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN pv.metodo_pago = 'efectivo' THEN pv.monto_pen ELSE 0 END), 0) AS efectivo_pen,
                COALESCE(SUM(CASE WHEN pv.metodo_pago = 'usd' OR pv.moneda = 'USD' THEN pv.monto ELSE 0 END), 0) AS efectivo_usd,
                COALESCE((
                    SELECT SUM(v2.total_pen)
                    FROM ventas v2
                    WHERE v2.apertura_caja_id = :id_ventas AND v2.estado != 'anulada'
                ), 0) AS total_ventas_pen,
                COUNT(DISTINCT v.id) AS cantidad_ventas
            FROM ventas v
            LEFT JOIN pagos_venta pv ON pv.venta_id = v.id
            WHERE v.apertura_caja_id = :id AND v.estado != 'anulada'
        ");
        $stmt->execute([':id' => $aperturaId, ':id_ventas' => $aperturaId]);
        $totales = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'efectivo_sistema_pen' => round((float)($apertura['saldo_inicial_pen'] ?? 0) + (float)($totales['efectivo_pen'] ?? 0), 2),
            'efectivo_sistema_usd' => round((float)($apertura['saldo_inicial_usd'] ?? 0) + (float)($totales['efectivo_usd'] ?? 0), 2),
            'total_ventas_pen'     => round((float)($totales['total_ventas_pen'] ?? 0), 2),
            'cantidad_ventas'      => (int)($totales['cantidad_ventas'] ?? 0),
        ];
    }

    // =========================================================================
    // CONSULTAS Y RESÚMENES
    // =========================================================================

    /**
     * Obtiene el resumen completo de una apertura de caja
     * Incluye totales de ventas, métodos de pago y comparativa con saldo inicial
     *
     * @param int $aperturaId ID de la apertura de caja
     * @return array Resumen completo de la sesión
     */
    public function getResumen(int $aperturaId): array
    {
        // Datos de la apertura
        $sqlApertura = "
            SELECT
                ca.*,
                'Caja Principal' AS caja_nombre,
                u.nombre       AS usuario_nombre,
                u.apellidos    AS usuario_apellidos
            FROM caja_aperturas ca
            INNER JOIN usuarios u  ON ca.usuario_id = u.id
            WHERE ca.id = :id
            LIMIT 1
        ";
        $stmtAp = $this->db->prepare($sqlApertura);
        $stmtAp->bindValue(':id', $aperturaId, PDO::PARAM_INT);
        $stmtAp->execute();
        $apertura = $stmtAp->fetch(PDO::FETCH_ASSOC);

        if (!$apertura) {
            return [];
        }

        // Totales de ventas por método de pago
        $totalesPorMetodo = $this->getTotalesByMetodo($aperturaId);

        // Estadísticas generales de ventas
        $sqlStats = "
            SELECT
                COUNT(v.id)    AS cantidad_ventas,
                SUM(v.total)   AS total_ventas,
                SUM(v.igv)     AS total_igv,
                SUM(v.subtotal)AS total_subtotal,
                COUNT(CASE WHEN v.tipo_comprobante = '01' THEN 1 END) AS cant_facturas,
                COUNT(CASE WHEN v.tipo_comprobante = '03' THEN 1 END) AS cant_boletas,
                COUNT(CASE WHEN v.tipo_comprobante = 'NV' THEN 1 END) AS cant_notas_venta
            FROM ventas v
            WHERE v.caja_apertura_id = :apertura_id
              AND v.estado != 'anulada'
        ";
        $stmtStats = $this->db->prepare($sqlStats);
        $stmtStats->bindValue(':apertura_id', $aperturaId, PDO::PARAM_INT);
        $stmtStats->execute();
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];

        // Calcular diferencia de efectivo
        $efectivoSistema = ((float)($totalesPorMetodo['efectivo'] ?? 0)) + (float)$apertura['saldo_inicial_pen'];
        $efectivoContado = (float)($apertura['saldo_final_efectivo'] ?? 0);

        return [
            'apertura'          => $apertura,
            'totales_por_metodo'=> $totalesPorMetodo,
            'stats'             => $stats,
            'efectivo_sistema'  => $efectivoSistema,
            'efectivo_contado'  => $efectivoContado,
            'diferencia'        => $efectivoContado - $efectivoSistema,
        ];
    }

    /**
     * Obtiene el historial de aperturas/cierres de una caja
     *
     * @param int $cajaId ID de la caja
     * @param int $limit  Cantidad máxima de registros (por defecto 30)
     * @return array Lista de aperturas/cierres ordenadas por fecha
     */
    public function getHistorial(int $cajaId, int $limit = 30): array
    {
        $sql = "
            SELECT
                ca.*,
                u.nombre     AS usuario_nombre,
                u.apellidos  AS usuario_apellidos,
                (
                    SELECT COUNT(*) FROM ventas v
                    WHERE v.caja_apertura_id = ca.id AND v.estado != 'anulada'
                ) AS cantidad_ventas,
                (
                    SELECT SUM(v.total) FROM ventas v
                    WHERE v.caja_apertura_id = ca.id AND v.estado != 'anulada'
                ) AS total_ventas
            FROM caja_aperturas ca
            INNER JOIN usuarios u ON ca.usuario_id = u.id
            WHERE ca.caja_id = :caja_id
            ORDER BY ca.fecha_apertura DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':caja_id', $cajaId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',   $limit,   PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula los totales de ventas agrupados por método de pago
     * para una apertura de caja específica
     *
     * @param int $aperturaId ID de la apertura de caja
     * @return array Asociativo [metodo_pago => monto_total]
     */
    public function getTotalesByMetodo(int $aperturaId): array
    {
        $sql = "
            SELECT
                pv.metodo_pago,
                SUM(CASE WHEN pv.moneda = 'USD' OR pv.metodo_pago = 'usd' THEN pv.monto ELSE pv.monto_pen END) AS total
            FROM pagos_venta pv
            INNER JOIN ventas v ON pv.venta_id = v.id
            WHERE v.caja_apertura_id = :apertura_id
              AND v.estado != 'anulada'
            GROUP BY pv.metodo_pago
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':apertura_id', $aperturaId, PDO::PARAM_INT);
        $stmt->execute();

        $result  = [];
        $rows    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $result[$row['metodo_pago']] = (float)$row['total'];
        }
        return $result;
    }

    /**
     * Obtiene todas las cajas disponibles en el sistema
     *
     * @return array Lista de cajas
     */
    public function getAllCajas(): array
    {
        $sql = "SELECT * FROM cajas WHERE activo = 1 ORDER BY nombre ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
