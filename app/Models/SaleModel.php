<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Modelo de Ventas
 *
 * Gestiona todas las operaciones de base de datos relacionadas con ventas,
 * incluyendo creación de comprobantes, detalle de ítems, pagos, movimientos
 * de stock y actualización de correlativos.
 *
 * @package App\Models
 */
class SaleModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // CREAR VENTA (TRANSACCIÓN ACID COMPLETA)
    // =========================================================================

    /**
     * Crea una venta completa con transacción ACID
     *
     * Pasos:
     * 1. INSERT en tabla ventas
     * 2. INSERT en detalle_ventas por cada ítem
     * 3. INSERT en pagos_venta por cada método de pago
     * 4. UPDATE stock de cada producto (resta)
     * 5. INSERT en movimientos_inventario por cada ítem
     * 6. UPDATE correlativo en configuracion_series
     *
     * @param array $data Datos de la venta:
     *   - tipo_comprobante: '01', '03', 'NV'
     *   - serie: serie del comprobante (ej: 'B001')
     *   - numero: correlativo (si vacío, se genera automáticamente)
     *   - cliente_id: ID del cliente
     *   - usuario_id: ID del vendedor
     *   - caja_apertura_id: ID de apertura de caja
     *   - moneda: 'PEN' o 'USD'
     *   - tipo_cambio: tipo de cambio si moneda=USD
     *   - subtotal, igv, total, descuento_total
     *   - notas: observaciones
     *   - items: array de ítems [{producto_id, nombre, cantidad, precio_unit, descuento, subtotal, igv, total, aplica_igv}]
     *   - pagos: array de pagos [{metodo, monto, referencia}]
     * @return array Datos de la venta creada [id, numero_comprobante, ...]
     * @throws RuntimeException Si falla la creación
     */
    public function create(array $data): array
    {
        try {
            $this->db->beginTransaction();

            // -----------------------------------------------------------------
            // PASO 1: Obtener correlativo si no fue proporcionado
            // -----------------------------------------------------------------
            $serie  = $data['serie']  ?? 'B001';
            $numero = $data['numero'] ?? null;

            if (empty($numero)) {
                $numero = $this->getNextCorrelativoInterno($data['tipo_comprobante'], $serie);
            }

            $numeroComprobante = $serie . '-' . str_pad((string)$numero, 8, '0', STR_PAD_LEFT);

            // -----------------------------------------------------------------
            // PASO 2: Insertar cabecera de venta
            // -----------------------------------------------------------------
            $sqlVenta = "
                INSERT INTO ventas (
                    tipo_comprobante, serie, numero,
                    numero_correlativo,
                    cliente_id, usuario_id, apertura_caja_id,
                    moneda, tipo_cambio,
                    subtotal_gravado, igv, descuento_global, total_pen, total_usd,
                    estado, notas,
                    fecha_emision, created_at, updated_at
                ) VALUES (
                    :tipo_comprobante, :serie, :numero,
                    :numero_correlativo,
                    :cliente_id, :usuario_id, :apertura_caja_id,
                    :moneda, :tipo_cambio,
                    :subtotal, :igv, :descuento_total, :total, :total_usd,
                    :estado, :notas,
                    CURDATE(), NOW(), NOW()
                )
            ";

            $stmtVenta = $this->db->prepare($sqlVenta);
            $stmtVenta->execute([
                ':tipo_comprobante'    => $data['tipo_comprobante'],
                ':serie'               => $serie,
                ':numero'              => $numero,
                ':numero_correlativo'  => $numero,
                ':cliente_id'          => $data['cliente_id']         ?? null,
                ':usuario_id'          => $data['usuario_id'],
                ':apertura_caja_id'    => $data['caja_apertura_id']   ?? null,
                ':moneda'              => $data['moneda']              ?? 'PEN',
                ':tipo_cambio'         => $data['tipo_cambio']         ?? 1.00,
                ':subtotal'            => $data['subtotal']            ?? 0.00,
                ':igv'                 => $data['igv']                 ?? 0.00,
                ':descuento_total'     => $data['descuento_total']     ?? 0.00,
                ':total'               => $data['total'],
                ':total_usd'           => ($data['moneda'] === 'USD') ? $data['total'] : 0.00,
                ':estado'              => 'pendiente',
                ':notas'               => $data['notas']               ?? null,
            ]);

            $ventaId = (int)$this->db->lastInsertId();

            // -----------------------------------------------------------------
            // PASO 3: Insertar detalle de ítems
            // -----------------------------------------------------------------
            $sqlDetalle = "
                INSERT INTO detalle_ventas (
                    venta_id, producto_id, descripcion_item,
                    cantidad, precio_unitario_pen, descuento_pen, subtotal_pen,
                    igv_item, subtotal_item, tipo_afectacion_igv, valor_unitario
                ) VALUES (
                    :venta_id, :producto_id, :descripcion,
                    :cantidad, :precio_unitario, :descuento, :subtotal,
                    :igv_monto, :total, :tipo_afectacion_igv, :valor_unitario
                )
            ";
            $stmtDetalle = $this->db->prepare($sqlDetalle);

            foreach ($data['items'] as $item) {
                $precioUnitario = $item['precio_unit'] ?? $item['precio_unitario'];
                $cantidad = $item['cantidad'];
                $subtotal = $item['subtotal'];
                $valorUnitario = $cantidad > 0 ? ($subtotal / $cantidad) : 0;

                $stmtDetalle->execute([
                    ':venta_id'            => $ventaId,
                    ':producto_id'         => $item['producto_id']         ?? null,
                    ':descripcion'         => $item['nombre']              ?? $item['descripcion'] ?? '',
                    ':cantidad'            => $cantidad,
                    ':precio_unitario'     => $precioUnitario,
                    ':descuento'           => $item['descuento']           ?? 0.00,
                    ':subtotal'            => $subtotal,
                    ':igv_monto'           => $item['igv']                 ?? $item['igv_monto'] ?? 0.00,
                    ':total'               => $item['total'],
                    ':tipo_afectacion_igv' => $item['tipo_afectacion_igv'] ?? '10',
                    ':valor_unitario'      => $valorUnitario
                ]);
            }

            // -----------------------------------------------------------------
            // PASO 4: Insertar pagos de la venta
            // -----------------------------------------------------------------
            $sqlPago = "
                INSERT INTO pagos_venta (
                    venta_id, metodo_pago, monto, monto_pen, referencia, moneda, tipo_cambio
                ) VALUES (
                    :venta_id, :metodo_pago, :monto, :monto_pen, :referencia, :moneda, :tipo_cambio
                )
            ";
            $stmtPago = $this->db->prepare($sqlPago);

            foreach ($data['pagos'] as $pago) {
                $metodo = $pago['metodo'] ?? $pago['metodo_pago'];
                $stmtPago->execute([
                    ':venta_id'    => $ventaId,
                    ':metodo_pago' => $metodo,
                    ':monto'       => $pago['monto'],
                    ':monto_pen'   => $pago['monto'],
                    ':referencia'  => $pago['referencia'] ?? null,
                    ':moneda'      => 'PEN',
                    ':tipo_cambio' => 1.00
                ]);
            }

            // -----------------------------------------------------------------
            // PASO 5: Omitido en código (gestionado por TRIGGER en BD)
            // -----------------------------------------------------------------

            // -----------------------------------------------------------------
            // PASO 6: Actualizar correlativo en configuracion_series
            // -----------------------------------------------------------------
            $sqlCorrelativo = "
                UPDATE configuracion_series
                SET correlativo = :numero
                WHERE tipo_comprobante = :tipo
                  AND serie = :serie
            ";
            $stmtCorrelativo = $this->db->prepare($sqlCorrelativo);
            $stmtCorrelativo->execute([
                ':numero' => $numero,
                ':tipo'   => $data['tipo_comprobante'],
                ':serie'  => $serie,
            ]);

            $this->db->commit();

            // -----------------------------------------------------------------
            // PASO 7: Crear registro en comprobantes_electronicos (post-commit)
            // Solo para comprobantes electrónicos (01=Factura, 03=Boleta)
            // -----------------------------------------------------------------
            if (in_array($data['tipo_comprobante'], ['01', '03'])) {
                try {
                    $stmtCE = $this->db->prepare("
                        INSERT INTO comprobantes_electronicos
                            (venta_id, tipo_comprobante, serie, numero, fecha_emision,
                             estado_sunat, created_at, updated_at)
                        VALUES
                            (:venta_id, :tipo, :serie, :numero, :fecha,
                             'pendiente', NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            updated_at = NOW()
                    ");
                    $stmtCE->execute([
                        ':venta_id' => $ventaId,
                        ':tipo'     => $data['tipo_comprobante'],
                        ':serie'    => $serie,
                        ':numero'   => str_pad((string)$numero, 8, '0', STR_PAD_LEFT),
                        ':fecha'    => date('Y-m-d'),
                    ]);
                } catch (\Exception $e) {
                    // Error no crítico: no detiene la venta, solo se loguea
                    error_log("No se pudo crear registro en comprobantes_electronicos: " . $e->getMessage());
                }
            }

            // Retornar datos de la venta creada
            return [
                'id'                 => $ventaId,
                'numero_comprobante' => $numeroComprobante,
                'serie'              => $serie,
                'numero'             => $numero,
                'total'              => $data['total'],
                'tipo_comprobante'   => $data['tipo_comprobante'],
            ];

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new RuntimeException(
                "Error al crear la venta: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    // =========================================================================
    // CONSULTAR VENTAS
    // =========================================================================

    /**
     * Obtiene lista de ventas con filtros y paginación
     *
     * @param array $filters Filtros disponibles:
     *   - fecha_desde: fecha inicio (Y-m-d)
     *   - fecha_hasta: fecha fin (Y-m-d)
     *   - tipo_comprobante: '01', '03', 'NV'
     *   - estado: 'pendiente', 'anulado'
     *   - usuario_id: filtrar por vendedor
     *   - metodo_pago: filtrar por método de pago
     *   - cliente_id: filtrar por cliente
     *   - busqueda: búsqueda por número de comprobante
     *   - limit, offset: paginación
     * @return array ['data' => [], 'total' => int, 'limit' => int, 'offset' => int]
     */
    public function getAll(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        // Filtro por fecha desde
        if (!empty($filters['fecha_desde'])) {
            $where[]                   = 'DATE(v.fecha_emision) >= :fecha_desde';
            $params[':fecha_desde']    = $filters['fecha_desde'];
        }

        // Filtro por fecha hasta
        if (!empty($filters['fecha_hasta'])) {
            $where[]                   = 'DATE(v.fecha_emision) <= :fecha_hasta';
            $params[':fecha_hasta']    = $filters['fecha_hasta'];
        }

        // Filtro por tipo de comprobante
        if (!empty($filters['tipo_comprobante'])) {
            $where[]                          = 'v.tipo_comprobante = :tipo_comprobante';
            $params[':tipo_comprobante']       = $filters['tipo_comprobante'];
        }

        // Filtro por estado
        if (!empty($filters['estado'])) {
            $where[]           = 'v.estado = :estado';
            $params[':estado'] = $filters['estado'];
        }

        // Filtro por vendedor
        if (!empty($filters['usuario_id'])) {
            $where[]               = 'v.usuario_id = :usuario_id';
            $params[':usuario_id'] = (int)$filters['usuario_id'];
        }

        // Filtro por cliente
        if (!empty($filters['cliente_id'])) {
            $where[]               = 'v.cliente_id = :cliente_id';
            $params[':cliente_id'] = (int)$filters['cliente_id'];
        }

        // Búsqueda por número de comprobante
        if (!empty($filters['busqueda'])) {
            $where[]              = '(CONCAT(v.serie, "-", v.numero) LIKE :busqueda OR c.razon_social LIKE :busqueda2 OR c.numero_doc LIKE :busqueda3)';
            $termino              = '%' . $filters['busqueda'] . '%';
            $params[':busqueda']  = $termino;
            $params[':busqueda2'] = $termino;
            $params[':busqueda3'] = $termino;
        }

        // Filtro por método de pago (subquery en pagos_venta)
        if (!empty($filters['metodo_pago'])) {
            $where[]                = 'EXISTS (SELECT 1 FROM pagos_venta pv WHERE pv.venta_id = v.id AND pv.metodo_pago = :metodo_pago)';
            $params[':metodo_pago'] = $filters['metodo_pago'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Contar total para paginación
        $countSql  = "SELECT COUNT(*) FROM ventas v LEFT JOIN clientes c ON v.cliente_id = c.id {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Paginación
        $limit  = (int)($filters['limit']  ?? 25);
        $offset = (int)($filters['offset'] ?? 0);

        $sql = "
            SELECT
                v.id,
                v.tipo_comprobante,
                v.serie,
                v.numero,
                CONCAT(v.serie, '-', v.numero) AS numero_comprobante,
                v.fecha_emision,
                v.moneda,
                v.subtotal_gravado AS subtotal,
                v.igv,
                v.descuento_global AS descuento_total,
                v.total_pen AS total,
                v.estado,
                COALESCE(ce.estado_sunat, 'pendiente') AS estado_sunat,
                v.notas,
                -- Datos del cliente
                c.id           AS cliente_id,
                c.razon_social AS cliente_nombre,
                c.tipo_doc AS cliente_tipo_doc,
                c.numero_doc AS cliente_numero_doc,
                -- Datos del usuario/vendedor
                u.nombre       AS usuario_nombre,
                u.apellidos    AS usuario_apellidos,
                -- Método de pago principal (primer pago registrado)
                (SELECT metodo_pago FROM pagos_venta pv WHERE pv.venta_id = v.id ORDER BY id ASC LIMIT 1) AS metodo_pago_principal
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            LEFT JOIN comprobantes_electronicos ce ON ce.venta_id = v.id
            {$whereClause}
            ORDER BY v.fecha_emision DESC, v.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'   => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Obtiene una venta por ID con detalle completo (ítems + pagos)
     *
     * @param int $id ID de la venta
     * @return array|null Datos completos de la venta o null si no existe
     */
    public function getById(int $id): ?array
    {
        // Obtener cabecera de la venta
        $sqlVenta = "
            SELECT
                v.*,
                COALESCE(NULLIF(c.razon_social, ''), NULLIF(CONCAT_WS(' ', c.nombres, c.apellidos), ''), 'CLIENTE GENERICO') AS cliente_nombre,
                c.tipo_doc   AS cliente_tipo_doc,
                c.numero_doc AS cliente_numero_doc,
                c.direccion        AS cliente_direccion,
                c.telefono         AS cliente_telefono,
                c.email            AS cliente_email,
                u.nombre           AS usuario_nombre,
                u.apellidos        AS usuario_apellidos
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            WHERE v.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sqlVenta);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venta) {
            return null;
        }

        // Obtener ítems del detalle
        $sqlDetalle = "
            SELECT
                dv.*,
                p.codigo_interno,
                p.codigo_barras,
                p.imagen_path AS imagen
            FROM detalle_ventas dv
            LEFT JOIN productos p ON dv.producto_id = p.id
            WHERE dv.venta_id = :venta_id
            ORDER BY dv.id ASC
        ";
        $stmtDet = $this->db->prepare($sqlDetalle);
        $stmtDet->bindValue(':venta_id', $id, PDO::PARAM_INT);
        $stmtDet->execute();
        $venta['items'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

        // Obtener pagos de la venta
        $sqlPagos = "
            SELECT *
            FROM pagos_venta
            WHERE venta_id = :venta_id
            ORDER BY id ASC
        ";
        $stmtPag = $this->db->prepare($sqlPagos);
        $stmtPag->bindValue(':venta_id', $id, PDO::PARAM_INT);
        $stmtPag->execute();
        $venta['pagos'] = $stmtPag->fetchAll(PDO::FETCH_ASSOC);

        return $venta;
    }

    /**
     * Anula una venta y restaura el stock de los productos
     *
     * @param int    $id     ID de la venta a anular
     * @param string $motivo Motivo de la anulación
     * @param int    $userId ID del usuario que anula
     * @return bool True si se anuló correctamente
     * @throws RuntimeException Si falla la anulación
     */
    public function cancel(int $id, string $motivo, int $userId): bool
    {
        try {
            $this->db->beginTransaction();

            // Verificar que la venta existe y está activa
            $sqlCheck = "SELECT id, estado, CONCAT(serie, '-', numero) AS numero_comprobante FROM ventas WHERE id = :id LIMIT 1";
            $stmtCheck = $this->db->prepare($sqlCheck);
            $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtCheck->execute();
            $venta = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$venta || $venta['estado'] === 'anulado') {
                throw new RuntimeException("La venta no existe o ya está anulada.");
            }

            // Actualizar estado de la venta a anulada
            $sqlAnular = "
                UPDATE ventas
                SET estado = 'anulado',
                    motivo_anulacion = :motivo,
                    anulado_por = :usuario_id,
                    fecha_anulacion = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ";
            $stmtAnular = $this->db->prepare($sqlAnular);
            $stmtAnular->execute([
                ':motivo'     => $motivo,
                ':usuario_id' => $userId,
                ':id'         => $id,
            ]);

            // Restaurar stock de cada producto
            $sqlItems = "SELECT producto_id, cantidad, precio_unitario_pen FROM detalle_ventas WHERE venta_id = :venta_id AND producto_id IS NOT NULL";
            $stmtItems = $this->db->prepare($sqlItems);
            $stmtItems->bindValue(':venta_id', $id, PDO::PARAM_INT);
            $stmtItems->execute();
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            $sqlGetStock = "SELECT stock_actual FROM productos WHERE id = :id FOR UPDATE";
            $stmtGetStock = $this->db->prepare($sqlGetStock);

            $sqlRestStock = "
                UPDATE productos
                SET stock_actual = stock_actual + :cantidad,
                    updated_at = NOW()
                WHERE id = :id
            ";
            $stmtRestStock = $this->db->prepare($sqlRestStock);

            $sqlMovAnulacion = "
                INSERT INTO movimientos_stock (
                    producto_id, tipo_movimiento, cantidad, stock_antes, stock_despues,
                    costo_unitario, referencia_tipo, referencia_id, notas, usuario_id, created_at
                ) VALUES (
                    :producto_id, 'ajuste_positivo', :cantidad, :stock_antes, :stock_despues,
                    :costo_unitario, 'anulacion_venta', :ref_id, :notas, :usuario_id, NOW()
                )
            ";
            $stmtMov = $this->db->prepare($sqlMovAnulacion);

            foreach ($items as $item) {
                $productoId = (int)$item['producto_id'];
                $cantidad   = (float)$item['cantidad'];

                $stmtGetStock->execute([':id' => $productoId]);
                $stockAnterior = (float)($stmtGetStock->fetchColumn() ?? 0);

                $stmtRestStock->execute([
                    ':cantidad' => $cantidad,
                    ':id'       => $productoId,
                ]);

                $stmtMov->execute([
                    ':producto_id'   => $productoId,
                    ':cantidad'      => $cantidad,
                    ':stock_antes'   => $stockAnterior,
                    ':stock_despues' => $stockAnterior + $cantidad,
                    ':costo_unitario'=> $item['precio_unitario_pen'],
                    ':ref_id'        => $id,
                    ':notas'         => "Anulación venta #{$venta['numero_comprobante']}: {$motivo}",
                    ':usuario_id'    => $userId,
                ]);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new RuntimeException("Error al anular la venta: " . $e->getMessage(), 0, $e);
        }
    }

    // =========================================================================
    // REPORTES Y CONSULTAS ESPECIALES
    // =========================================================================

    /**
     * Obtiene ventas para reportes en un rango de fechas
     *
     * @param string      $dateFrom Fecha inicio (Y-m-d)
     * @param string      $dateTo   Fecha fin (Y-m-d)
     * @param string|null $type     Tipo de comprobante o null para todos
     * @return array Lista de ventas para reporte
     */
    public function getForReport(string $dateFrom, string $dateTo, ?string $type = null): array
    {
        $where  = ["DATE(v.fecha_emision) BETWEEN :fecha_desde AND :fecha_hasta", "v.estado = 'pendiente'"];
        $params = [
            ':fecha_desde' => $dateFrom,
            ':fecha_hasta' => $dateTo,
        ];

        if ($type !== null) {
            $where[]             = 'v.tipo_comprobante = :tipo';
            $params[':tipo']     = $type;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT
                v.id,
                CONCAT(v.serie, '-', v.numero) AS numero_comprobante,
                v.fecha_emision,
                v.tipo_comprobante,
                v.moneda,
                v.subtotal_gravado AS subtotal,
                v.igv,
                v.descuento_global AS descuento_total,
                v.total_pen AS total,
                v.estado,
                c.razon_social     AS cliente_nombre,
                c.numero_doc AS cliente_ruc_dni,
                u.nombre           AS vendedor,
                GROUP_CONCAT(DISTINCT pv.metodo_pago ORDER BY pv.id SEPARATOR ', ') AS metodos_pago
            FROM ventas v
            LEFT JOIN clientes c    ON v.cliente_id = c.id
            LEFT JOIN usuarios u    ON v.usuario_id = u.id
            LEFT JOIN pagos_venta pv ON v.id = pv.venta_id
            {$whereClause}
            GROUP BY v.id
            ORDER BY v.fecha_emision ASC, v.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las ventas del día de hoy
     *
     * @param int|null $userId Si se especifica, filtra por usuario/vendedor
     * @return array Lista de ventas de hoy
     */
    public function getToday(?int $userId = null): array
    {
        $where  = ["DATE(v.fecha_emision) = CURDATE()", "v.estado = 'pendiente'"];
        $params = [];

        if ($userId !== null) {
            $where[]               = 'v.usuario_id = :usuario_id';
            $params[':usuario_id'] = $userId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "
            SELECT
                v.*,
                c.razon_social AS cliente_nombre,
                u.nombre       AS usuario_nombre
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            {$whereClause}
            ORDER BY v.fecha_emision DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el resumen de ventas de un día agrupado por método de pago
     *
     * @param string   $date   Fecha en formato Y-m-d
     * @param int|null $cajaId ID de apertura de caja (opcional)
     * @return array Resumen con totales por método de pago
     */
    public function getDailySummary(string $date, ?int $cajaId = null): array
    {
        $where  = ["DATE(v.fecha_emision) = :fecha", "v.estado = 'pendiente'"];
        $params = [':fecha' => $date];

        if ($cajaId !== null) {
            $where[]               = 'v.caja_apertura_id = :caja_id';
            $params[':caja_id']    = $cajaId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Totales generales
        $sqlTotales = "
            SELECT
                COUNT(v.id)   AS cantidad_ventas,
                SUM(v.total_pen)  AS total_ventas,
                SUM(v.igv)    AS total_igv,
                SUM(v.subtotal_gravado) AS total_subtotal
            FROM ventas v
            {$whereClause}
        ";
        $stmtTot = $this->db->prepare($sqlTotales);
        $stmtTot->execute($params);
        $totales = $stmtTot->fetch(PDO::FETCH_ASSOC) ?: [];

        // Totales por método de pago
        $sqlMetodos = "
            SELECT
                pv.metodo_pago,
                COUNT(DISTINCT pv.venta_id) AS cantidad,
                SUM(pv.monto)               AS monto_total
            FROM pagos_venta pv
            INNER JOIN ventas v ON pv.venta_id = v.id
            {$whereClause}
            GROUP BY pv.metodo_pago
            ORDER BY monto_total DESC
        ";
        $stmtMet = $this->db->prepare($sqlMetodos);
        $stmtMet->execute($params);
        $metodos = $stmtMet->fetchAll(PDO::FETCH_ASSOC);

        // Totales por tipo de comprobante
        $sqlTipos = "
            SELECT
                v.tipo_comprobante,
                COUNT(v.id)  AS cantidad,
                SUM(v.total_pen) AS total
            FROM ventas v
            {$whereClause}
            GROUP BY v.tipo_comprobante
        ";
        $stmtTip = $this->db->prepare($sqlTipos);
        $stmtTip->execute($params);
        $tipos = $stmtTip->fetchAll(PDO::FETCH_ASSOC);

        return [
            'totales' => $totales,
            'metodos' => $metodos,
            'tipos'   => $tipos,
            'fecha'   => $date,
        ];
    }

    /**
     * Obtiene el siguiente número de correlativo para una serie
     *
     * @param string $tipoComprobante Tipo ('01', '03', 'NV')
     * @param string $serie           Serie (ej: 'B001')
     * @return int Siguiente número correlativo
     */
    public function getNextCorrelativo(string $tipoComprobante, string $serie): int
    {
        return $this->getNextCorrelativoInterno($tipoComprobante, $serie);
    }

    /**
     * Obtiene ventas de un mes específico
     *
     * @param int $year  Año (ej: 2024)
     * @param int $month Mes (1-12)
     * @return array Lista de ventas del mes
     */
    public function getByMonth(int $year, int $month): array
    {
        $sql = "
            SELECT
                v.*,
                c.razon_social AS cliente_nombre,
                u.nombre       AS usuario_nombre
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            WHERE YEAR(v.fecha_emision)  = :anio
              AND MONTH(v.fecha_emision) = :mes
              AND v.estado = 'pendiente'
            ORDER BY v.fecha_emision ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':anio' => $year,
            ':mes'  => $month,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Obtiene el siguiente correlativo desde la tabla de series
     *
     * @param string $tipo  Tipo de comprobante
     * @param string $serie Serie del comprobante
     * @return int Siguiente correlativo disponible
     */
    private function getNextCorrelativoInterno(string $tipo, string $serie): int
    {
        // Buscar en configuracion_series
        $sqlSerie = "
            SELECT correlativo
            FROM configuracion_series
            WHERE tipo_comprobante = :tipo AND serie = :serie
            LIMIT 1
        ";
        $stmtSerie = $this->db->prepare($sqlSerie);
        $stmtSerie->execute([':tipo' => $tipo, ':serie' => $serie]);
        $row = $stmtSerie->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return (int)$row['correlativo'] + 1;
        }

        // Si no existe la serie, calcular desde las ventas
        $sqlMax = "
            SELECT COALESCE(MAX(numero), 0) + 1 AS siguiente
            FROM ventas
            WHERE tipo_comprobante = :tipo AND serie = :serie
        ";
        $stmtMax = $this->db->prepare($sqlMax);
        $stmtMax->execute([':tipo' => $tipo, ':serie' => $serie]);
        $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);

        return (int)($rowMax['siguiente'] ?? 1);
    }

    /**
     * Actualiza el estado de SUNAT de una venta
     *
     * @param int    $ventaId ID de la venta
     * @param string $estado  Estado (Ej: 'Aceptado', 'Rechazado_o_Pendiente')
     * @return bool
     */
    public function actualizarEstadoSunat(int $ventaId, string $estado): bool
    {
        // Verificar si ya existe registro en comprobantes_electronicos
        $sqlCheck = "SELECT id FROM comprobantes_electronicos WHERE venta_id = :venta_id LIMIT 1";
        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->execute([':venta_id' => $ventaId]);
        $exists = $stmtCheck->fetchColumn();

        if ($exists) {
            $sql = "UPDATE comprobantes_electronicos SET estado_sunat = :estado_sunat WHERE venta_id = :venta_id";
        } else {
            // Insertar el registro con datos de la venta
            $sqlVenta = "SELECT tipo_comprobante, serie, numero, fecha_emision FROM ventas WHERE id = :id LIMIT 1";
            $stmtVenta = $this->db->prepare($sqlVenta);
            $stmtVenta->execute([':id' => $ventaId]);
            $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);
            if (!$venta) return false;

            $sql = "INSERT INTO comprobantes_electronicos (venta_id, tipo_comprobante, serie, numero, fecha_emision, estado_sunat, created_at) 
                    VALUES (:venta_id, :tipo, :serie, :numero, :fecha, :estado_sunat, NOW())";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':venta_id' => $ventaId,
                ':tipo'     => $venta['tipo_comprobante'],
                ':serie'    => $venta['serie'],
                ':numero'   => $venta['numero'],
                ':fecha'    => $venta['fecha_emision'],
                ':estado_sunat' => $estado,
            ]);
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':estado_sunat' => $estado, ':venta_id' => $ventaId]);
    }
}
