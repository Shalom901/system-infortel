<?php

declare(strict_types=1);

/**
 * Modelo de Compras / Órdenes de Compra
 *
 * Gestiona toda la lógica de compras:
 * - Órdenes de compra (OC)
 * - Recepción de mercadería (actualiza stock con CPP)
 * - Registro de comprobantes de proveedor
 * - Libro de Compras SUNAT
 *
 * Tablas utilizadas:
 *   - ordenes_compra         : registro principal de OCs
 *   - orden_compra_items     : líneas de detalle de cada OC
 *   - recepciones_compra     : recepciones de mercadería
 *   - detalle_recepciones    : items recibidos por recepción
 *   - comprobantes_compra    : comprobantes del proveedor (para libro de compras)
 *   - movimientos_inventario : movimientos de stock generados
 *   - productos              : actualización de stock y costo promedio
 *
 * @package App\Models
 * @author  Sistema de Facturación Pucallpa
 * @version 1.0.0
 */

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

class PurchaseModel
{
    /** @var PDO Conexión PDO a la base de datos */
    private PDO $db;

    // =========================================================================
    // ESTADOS DE ORDEN DE COMPRA
    // =========================================================================
    public const ESTADO_BORRADOR   = 'borrador';
    public const ESTADO_EMITIDA    = 'emitida';
    public const ESTADO_ENVIADA    = 'enviada';
    public const ESTADO_RECIBIDA   = 'recibida';
    public const ESTADO_PARCIAL    = 'parcial';
    public const ESTADO_CANCELADA  = 'cancelada';
    public const ESTADO_CERRADA    = 'cerrada';

    /** Colores Bootstrap para cada estado */
    public const COLORES_ESTADO = [
        'borrador'  => 'secondary',
        'emitida'   => 'primary',
        'enviada'   => 'info',
        'recibida'  => 'success',
        'parcial'   => 'warning',
        'cancelada' => 'danger',
        'cerrada'   => 'dark',
    ];

    /**
     * Constructor: inyecta la conexión PDO
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // CONSULTAS PRINCIPALES
    // =========================================================================

    /**
     * Obtiene todas las órdenes de compra con filtros y paginación.
     *
     * @param array $filters Filtros posibles:
     *   - proveedor_id : int
     *   - estado       : string
     *   - moneda       : string  (PEN|USD)
     *   - fecha_desde  : string  (Y-m-d)
     *   - fecha_hasta  : string  (Y-m-d)
     *   - busqueda     : string  (número OC, razón social proveedor)
     *   - limit        : int     (default 20)
     *   - offset       : int     (default 0)
     * @return array ['data' => [], 'total' => int]
     */
    public function getAll(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['proveedor_id'])) {
            $where[]                = 'oc.proveedor_id = :proveedor_id';
            $params[':proveedor_id']= (int)$filters['proveedor_id'];
        }

        if (!empty($filters['estado'])) {
            $where[]           = 'oc.estado = :estado';
            $params[':estado'] = $filters['estado'];
        }

        if (!empty($filters['moneda'])) {
            $where[]           = 'oc.moneda = :moneda';
            $params[':moneda'] = $filters['moneda'];
        }

        if (!empty($filters['fecha_desde'])) {
            $where[]                = 'DATE(oc.fecha) >= :fecha_desde';
            $params[':fecha_desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[]                = 'DATE(oc.fecha) <= :fecha_hasta';
            $params[':fecha_hasta'] = $filters['fecha_hasta'];
        }

        if (!empty($filters['busqueda'])) {
            $termino          = '%' . $filters['busqueda'] . '%';
            $where[]          = '(oc.numero LIKE :busq1 OR pv.razon_social LIKE :busq2)';
            $params[':busq1'] = $termino;
            $params[':busq2'] = $termino;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql  = "SELECT COUNT(*) FROM ordenes_compra oc
                      LEFT JOIN proveedores pv ON oc.proveedor_id = pv.id
                      {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $limit  = (int)($filters['limit']  ?? 20);
        $offset = (int)($filters['offset'] ?? 0);

        $sql = "SELECT
                    oc.*,
                    pv.razon_social        AS proveedor_nombre,
                    pv.numero_doc          AS proveedor_ruc,
                    pv.email               AS proveedor_email,
                    u.nombre               AS usuario_nombre,
                    u.apellidos            AS usuario_apellidos,
                    (SELECT COUNT(*) FROM recepciones_compra rc WHERE rc.orden_compra_id = oc.id) AS total_recepciones
                FROM ordenes_compra oc
                LEFT JOIN proveedores pv ON oc.proveedor_id = pv.id
                LEFT JOIN usuarios   u  ON oc.usuario_id   = u.id
                {$whereClause}
                ORDER BY oc.created_at DESC
                LIMIT :limit OFFSET :offset";

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
     * Obtiene una orden de compra por ID con proveedor, items y recepciones.
     *
     * @param int $id ID de la orden de compra
     * @return array|null OC completa o null si no existe
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT
                    oc.*,
                    pv.razon_social        AS proveedor_nombre,
                    pv.ruc                 AS proveedor_ruc,
                    pv.email               AS proveedor_email,
                    pv.telefono            AS proveedor_telefono,
                    pv.direccion           AS proveedor_direccion,
                    pv.contacto            AS proveedor_contacto,
                    u.nombre               AS usuario_nombre,
                    u.apellidos            AS usuario_apellidos
                FROM ordenes_compra oc
                LEFT JOIN proveedores pv ON oc.proveedor_id = pv.id
                LEFT JOIN usuarios   u  ON oc.usuario_id   = u.id
                WHERE oc.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $oc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oc) {
            return null;
        }

        // Items de la OC
        $oc['items']       = $this->getItems($id);
        // Recepciones registradas
        $oc['recepciones'] = $this->getRecepciones($id);
        // Comprobante del proveedor si existe
        $oc['comprobante'] = $this->getComprobanteByOC($id);

        return $oc;
    }

    /**
     * Obtiene las OCs de un proveedor específico.
     *
     * @param int $proveedorId ID del proveedor
     * @param int $limit       Límite de resultados
     * @return array Lista de OCs del proveedor
     */
    public function getByProveedor(int $proveedorId, int $limit = 10): array
    {
        $sql = "SELECT oc.*, pv.razon_social AS proveedor_nombre
                FROM ordenes_compra oc
                LEFT JOIN proveedores pv ON oc.proveedor_id = pv.id
                WHERE oc.proveedor_id = :proveedor_id
                ORDER BY oc.creado_en DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':proveedor_id', $proveedorId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',        $limit,       PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene órdenes de compra pendientes de recepción total.
     *
     * @return array Lista de OCs pendientes
     */
    public function getPendientesRecepcion(): array
    {
        $sql = "SELECT oc.*, pv.razon_social AS proveedor_nombre
                FROM ordenes_compra oc
                LEFT JOIN proveedores pv ON oc.proveedor_id = pv.id
                WHERE oc.estado IN (:emitida, :enviada, :parcial)
                 
                ORDER BY oc.fecha_entrega_esperada ASC, oc.creado_en ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':emitida',  self::ESTADO_EMITIDA);
        $stmt->bindValue(':enviada',  self::ESTADO_ENVIADA);
        $stmt->bindValue(':parcial',  self::ESTADO_PARCIAL);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los items de una orden de compra.
     *
     * @param int $ocId ID de la orden de compra
     * @return array Lista de items
     */
    public function getItems(int $ocId): array
    {
        $sql = "SELECT
                    oi.*,
                    p.nombre         AS producto_nombre_actual,
                    p.codigo_interno AS producto_codigo,
                    p.stock_actual   AS producto_stock,
                    u.abreviatura    AS unidad_abreviatura,
                    -- Calcular cantidad ya recibida
                    COALESCE(
                        (SELECT SUM(dr.cantidad_recibida)
                         FROM detalle_recepciones dr
                         INNER JOIN recepciones_compra rc ON dr.recepcion_id = rc.id
                         WHERE dr.orden_item_id = oi.id
                           AND rc.estado = 'confirmada'),
                        0
                    ) AS cantidad_recibida
                FROM orden_compra_items oi
                LEFT JOIN productos       p ON oi.producto_id = p.id
                LEFT JOIN unidades_medida u ON p.unidad_id    = u.id
                WHERE oi.orden_compra_id = :oc_id
                ORDER BY oi.orden ASC, oi.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':oc_id', $ocId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las recepciones de una orden de compra.
     *
     * @param int $ocId ID de la orden de compra
     * @return array Lista de recepciones con sus detalles
     */
    public function getRecepciones(int $ocId): array
    {
        $sql = "SELECT rc.*,
                       u.nombre     AS usuario_nombre,
                       u.apellidos  AS usuario_apellidos
                FROM recepciones_compra rc
                LEFT JOIN usuarios u ON rc.usuario_id = u.id
                WHERE rc.orden_compra_id = :oc_id
                ORDER BY rc.fecha_recepcion ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':oc_id', $ocId, PDO::PARAM_INT);
        $stmt->execute();
        $recepciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cargar items de cada recepción
        foreach ($recepciones as &$rec) {
            $sqlDet = "SELECT dr.*,
                              p.nombre         AS producto_nombre,
                              p.codigo_interno AS producto_codigo
                       FROM detalle_recepciones dr
                       LEFT JOIN orden_compra_items oi ON dr.orden_item_id = oi.id
                       LEFT JOIN productos p ON oi.producto_id = p.id
                       WHERE dr.recepcion_id = :recepcion_id";

            $stmtDet = $this->db->prepare($sqlDet);
            $stmtDet->bindValue(':recepcion_id', (int)$rec['id'], PDO::PARAM_INT);
            $stmtDet->execute();
            $rec['items'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($rec);

        return $recepciones;
    }

    /**
     * Obtiene el comprobante de proveedor asociado a una OC.
     *
     * @param int $ocId ID de la orden de compra
     * @return array|null Comprobante o null si no existe
     */
    public function getComprobanteByOC(int $ocId): ?array
    {
        $sql = "SELECT * FROM comprobantes_compra WHERE orden_compra_id = :oc_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':oc_id', $ocId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    // =========================================================================
    // LIBRO DE COMPRAS SUNAT
    // =========================================================================

    /**
     * Obtiene los datos del libro de compras para un mes/año específico.
     * Formato según estructura del Registro de Compras SUNAT.
     *
     * @param int $mes  Mes (1-12)
     * @param int $anio Año (YYYY)
     * @return array Registros del libro de compras
     */
    public function getLibroCompras(int $mes, int $anio): array
    {
        $sql = "SELECT
                    cc.id,
                    cc.fecha_emision,
                    cc.fecha_vencimiento,
                    cc.tipo_comprobante,
                    cc.serie,
                    cc.numero,
                    cc.tipo_doc_proveedor,
                    cc.num_doc_proveedor,
                    cc.razon_social_proveedor,
                    cc.base_imponible_gravada,
                    cc.igv,
                    cc.base_imponible_exonerada,
                    cc.base_imponible_inafecta,
                    cc.importe_total,
                    cc.moneda,
                    cc.tipo_cambio,
                    cc.orden_compra_id,
                    oc.numero AS oc_numero
                FROM comprobantes_compra cc
                LEFT JOIN ordenes_compra oc ON cc.orden_compra_id = oc.id
                WHERE MONTH(cc.fecha_emision) = :mes
                  AND YEAR(cc.fecha_emision)  = :anio
                  AND cc.anulado              = 0
                ORDER BY cc.fecha_emision ASC, cc.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':mes',  $mes,  PDO::PARAM_INT);
        $stmt->bindValue(':anio', $anio, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // OPERACIONES DE ESCRITURA
    // =========================================================================

    /**
     * Crea una nueva orden de compra con sus items.
     *
     * @param array $data Datos de la OC:
     *   - proveedor_id         : int    (requerido)
     *   - usuario_id           : int    (requerido)
     *   - moneda               : string (PEN|USD)
     *   - tipo_cambio          : float
     *   - fecha_entrega_esperada: string (Y-m-d, opcional)
     *   - notas                : string
     *   - subtotal             : float
     *   - igv                  : float
     *   - total                : float
     *   - items                : array  (lista de ítems)
     * @return int ID de la OC creada
     * @throws RuntimeException Si falla la creación
     */
    public function create(array $data): int
    {
        try {
            $this->db->beginTransaction();

            $numero = $this->generarNumeroOC();

            $sql = "INSERT INTO ordenes_compra (
                        numero, proveedor_id, usuario_id, estado,
                        moneda, tipo_cambio,
                        fecha_emision, fecha_entrega_esperada,
                        subtotal, igv, total,
                        notas,
                        creado_en, actualizado_en
                    ) VALUES (
                        :numero, :proveedor_id, :usuario_id, :estado,
                        :moneda, :tipo_cambio,
                        :fecha_emision, :fecha_entrega_esperada,
                        :subtotal, :igv, :total,
                        :notas,
                        NOW(), NOW()
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':numero'                  => $numero,
                ':proveedor_id'            => (int)$data['proveedor_id'],
                ':usuario_id'              => (int)$data['usuario_id'],
                ':estado'                  => $data['estado'] ?? self::ESTADO_BORRADOR,
                ':moneda'                  => $data['moneda']           ?? 'PEN',
                ':tipo_cambio'             => (float)($data['tipo_cambio'] ?? 1.0),
                ':fecha_emision'           => $data['fecha_emision']    ?? date('Y-m-d'),
                ':fecha_entrega_esperada'  => $data['fecha_entrega_esperada'] ?? null,
                ':subtotal'                => (float)($data['subtotal'] ?? 0),
                ':igv'                     => (float)($data['igv']      ?? 0),
                ':total'                   => (float)($data['total']    ?? 0),
                ':notas'                   => $data['notas']            ?? null,
            ]);

            $ocId = (int)$this->db->lastInsertId();

            if (!empty($data['items'])) {
                $this->insertItems($ocId, $data['items']);
            }

            $this->db->commit();
            return $ocId;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new RuntimeException('Error al crear la orden de compra: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Actualiza una orden de compra (solo en estado borrador).
     *
     * @param int   $id   ID de la OC
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     * @throws RuntimeException Si la OC no está en estado editable
     */
    public function update(int $id, array $data): bool
    {
        $oc = $this->getById($id);
        if (!$oc) {
            throw new RuntimeException('Orden de compra no encontrada.');
        }
        if ($oc['estado'] !== self::ESTADO_BORRADOR) {
            throw new RuntimeException('Solo se pueden editar órdenes en estado Borrador.');
        }

        try {
            $this->db->beginTransaction();

            $sql = "UPDATE ordenes_compra SET
                        proveedor_id           = :proveedor_id,
                        moneda                 = :moneda,
                        tipo_cambio            = :tipo_cambio,
                        fecha_entrega_esperada = :fecha_entrega_esperada,
                        subtotal               = :subtotal,
                        igv                    = :igv,
                        total                  = :total,
                        notas                  = :notas,
                        actualizado_en         = NOW()
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':proveedor_id'           => (int)$data['proveedor_id'],
                ':moneda'                 => $data['moneda']           ?? 'PEN',
                ':tipo_cambio'            => (float)($data['tipo_cambio'] ?? 1.0),
                ':fecha_entrega_esperada' => $data['fecha_entrega_esperada'] ?? null,
                ':subtotal'               => (float)($data['subtotal'] ?? 0),
                ':igv'                    => (float)($data['igv']      ?? 0),
                ':total'                  => (float)($data['total']    ?? 0),
                ':notas'                  => $data['notas']            ?? null,
                ':id'                     => $id,
            ]);

            // Reemplazar items
            $delStmt = $this->db->prepare('DELETE FROM orden_compra_items WHERE orden_compra_id = :id');
            $delStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $delStmt->execute();

            if (!empty($data['items'])) {
                $this->insertItems($id, $data['items']);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new RuntimeException('Error al actualizar la orden de compra: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Procesa la recepción de mercadería de una orden de compra.
     *
     * Proceso:
     * 1. Valida que la OC esté en estado recepcionable
     * 2. Crea el registro de recepción
     * 3. Crea los detalles de ítems recibidos
     * 4. Actualiza el stock usando costo promedio ponderado (CPP)
     * 5. Registra movimientos en movimientos_inventario
     * 6. Actualiza el estado de la OC (parcial o recibida)
     *
     * @param int   $ordenId ID de la orden de compra
     * @param array $items   Items recibidos:
     *   - orden_item_id    : int   (ID del item en la OC)
     *   - cantidad_recibida: float
     *   - costo_unitario   : float (costo real recibido)
     *   - notas_item       : string
     * @param int   $userId  ID del usuario que recepciona
     * @param string $notas  Notas generales de la recepción
     * @return int ID de la recepción creada
     * @throws RuntimeException Si falla la recepción
     */
    public function recepcionar(int $ordenId, array $items, int $userId, string $notas = ''): int
    {
        $oc = $this->getById($ordenId);
        if (!$oc) {
            throw new RuntimeException('Orden de compra no encontrada.');
        }

        $estadosRecepcionables = [self::ESTADO_EMITIDA, self::ESTADO_ENVIADA, self::ESTADO_PARCIAL];
        if (!in_array($oc['estado'], $estadosRecepcionables)) {
            throw new RuntimeException("No se puede recepcionar una OC en estado '{$oc['estado']}'.");
        }

        // Filtrar solo items con cantidad > 0
        $itemsValidos = array_filter($items, fn($i) => (float)($i['cantidad_recibida'] ?? 0) > 0);

        if (empty($itemsValidos)) {
            throw new RuntimeException('Debe ingresar al menos un ítem con cantidad recibida mayor a 0.');
        }

        try {
            $this->db->beginTransaction();

            // 1. Crear cabecera de recepción
            $sqlRec = "INSERT INTO recepciones_compra (
                           orden_compra_id, usuario_id, fecha_recepcion, notas, estado, creado_en
                       ) VALUES (
                           :orden_id, :usuario_id, NOW(), :notas, 'confirmada', NOW()
                       )";

            $stmtRec = $this->db->prepare($sqlRec);
            $stmtRec->execute([
                ':orden_id'   => $ordenId,
                ':usuario_id' => $userId,
                ':notas'      => $notas ?: null,
            ]);
            $recepcionId = (int)$this->db->lastInsertId();

            // 2. Procesar cada ítem recibido
            $sqlDet = "INSERT INTO detalle_recepciones (
                           recepcion_id, orden_item_id, cantidad_recibida,
                           costo_unitario, notas, creado_en
                       ) VALUES (
                           :recepcion_id, :orden_item_id, :cantidad_recibida,
                           :costo_unitario, :notas, NOW()
                       )";
            $stmtDet = $this->db->prepare($sqlDet);

            // Obtener datos de los items de la OC para saber qué producto actualizar
            $ocItemsIndexed = array_column($oc['items'], null, 'id');

            foreach ($itemsValidos as $item) {
                $cantidadRecibida = (float)$item['cantidad_recibida'];
                $costoUnitario    = (float)($item['costo_unitario'] ?? 0);
                $ordenItemId      = (int)$item['orden_item_id'];

                // Insertar detalle de recepción
                $stmtDet->execute([
                    ':recepcion_id'     => $recepcionId,
                    ':orden_item_id'    => $ordenItemId,
                    ':cantidad_recibida'=> $cantidadRecibida,
                    ':costo_unitario'   => $costoUnitario,
                    ':notas'            => $item['notas_item'] ?? null,
                ]);

                // Obtener el producto_id del item de la OC
                $ocItem = $ocItemsIndexed[$ordenItemId] ?? null;
                if (!$ocItem || empty($ocItem['producto_id'])) {
                    continue;
                }

                $productoId = (int)$ocItem['producto_id'];

                // 3. Actualizar stock con CPP
                $this->actualizarStockEntrada(
                    $productoId,
                    $cantidadRecibida,
                    $costoUnitario,
                    $ordenId,
                    $userId
                );
            }

            // 4. Determinar nuevo estado de la OC
            $nuevoEstado = $this->calcularEstadoOC($ordenId);

            $sqlUpdateOC = "UPDATE ordenes_compra SET estado = :estado, actualizado_en = NOW() WHERE id = :id";
            $stmtUpd = $this->db->prepare($sqlUpdateOC);
            $stmtUpd->bindValue(':estado', $nuevoEstado);
            $stmtUpd->bindValue(':id',     $ordenId, PDO::PARAM_INT);
            $stmtUpd->execute();

            $this->db->commit();
            return $recepcionId;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new RuntimeException('Error al procesar la recepción: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Registra un comprobante del proveedor para el libro de compras.
     *
     * @param array $data Datos del comprobante:
     *   - orden_compra_id          : int    (puede ser null si no viene de una OC)
     *   - proveedor_id             : int    (requerido)
     *   - fecha_emision            : string (Y-m-d, requerido)
     *   - fecha_vencimiento        : string (Y-m-d, opcional)
     *   - tipo_comprobante         : string ('01'=Factura, '03'=Boleta, '12'=Ticket, etc.)
     *   - serie                    : string
     *   - numero                   : string
     *   - tipo_doc_proveedor       : string ('6'=RUC, '1'=DNI)
     *   - num_doc_proveedor        : string
     *   - razon_social_proveedor   : string
     *   - base_imponible_gravada   : float
     *   - igv                      : float
     *   - base_imponible_exonerada : float
     *   - base_imponible_inafecta  : float
     *   - importe_total            : float
     *   - moneda                   : string (PEN|USD)
     *   - tipo_cambio              : float
     * @return int ID del comprobante registrado
     * @throws RuntimeException Si falla el registro
     */
    public function registrarComprobante(array $data): int
    {
        $sql = "INSERT INTO comprobantes_compra (
                    orden_compra_id, proveedor_id,
                    fecha_emision, fecha_vencimiento,
                    tipo_comprobante, serie, numero,
                    tipo_doc_proveedor, num_doc_proveedor, razon_social_proveedor,
                    base_imponible_gravada, igv,
                    base_imponible_exonerada, base_imponible_inafecta,
                    importe_total, moneda, tipo_cambio,
                    anulado, creado_en
                ) VALUES (
                    :orden_compra_id, :proveedor_id,
                    :fecha_emision, :fecha_vencimiento,
                    :tipo_comprobante, :serie, :numero,
                    :tipo_doc_proveedor, :num_doc_proveedor, :razon_social_proveedor,
                    :base_imponible_gravada, :igv,
                    :base_imponible_exonerada, :base_imponible_inafecta,
                    :importe_total, :moneda, :tipo_cambio,
                    0, NOW()
                )";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':orden_compra_id'          => !empty($data['orden_compra_id']) ? (int)$data['orden_compra_id'] : null,
                ':proveedor_id'             => (int)$data['proveedor_id'],
                ':fecha_emision'            => $data['fecha_emision'],
                ':fecha_vencimiento'        => $data['fecha_vencimiento'] ?? null,
                ':tipo_comprobante'         => $data['tipo_comprobante'],
                ':serie'                    => strtoupper($data['serie'] ?? ''),
                ':numero'                   => $data['numero'],
                ':tipo_doc_proveedor'       => $data['tipo_doc_proveedor'] ?? '6',
                ':num_doc_proveedor'        => $data['num_doc_proveedor'],
                ':razon_social_proveedor'   => $data['razon_social_proveedor'],
                ':base_imponible_gravada'   => (float)($data['base_imponible_gravada']   ?? 0),
                ':igv'                      => (float)($data['igv']                      ?? 0),
                ':base_imponible_exonerada' => (float)($data['base_imponible_exonerada'] ?? 0),
                ':base_imponible_inafecta'  => (float)($data['base_imponible_inafecta']  ?? 0),
                ':importe_total'            => (float)($data['importe_total']            ?? 0),
                ':moneda'                   => $data['moneda']     ?? 'PEN',
                ':tipo_cambio'              => (float)($data['tipo_cambio'] ?? 1.0),
            ]);

            return (int)$this->db->lastInsertId();

        } catch (PDOException $e) {
            throw new RuntimeException('Error al registrar el comprobante: ' . $e->getMessage(), 0, $e);
        }
    }

    // =========================================================================
    // MÉTODOS PRIVADOS AUXILIARES
    // =========================================================================

    /**
     * Genera el siguiente número correlativo para una OC.
     * Formato: OC-2024-0001
     *
     * @return string Número de OC
     */
    private function generarNumeroOC(): string
    {
        $anio = date('Y');
        $sql  = "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)), 0) + 1 AS siguiente
                 FROM ordenes_compra WHERE numero LIKE :patron";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':patron', "OC-{$anio}-%");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $correlativo = (int)($row['siguiente'] ?? 1);
        return sprintf('OC-%s-%04d', $anio, $correlativo);
    }

    /**
     * Inserta los items de una orden de compra.
     *
     * @param int   $ocId  ID de la OC
     * @param array $items Lista de items
     */
    private function insertItems(int $ocId, array $items): void
    {
        $sql = "INSERT INTO orden_compra_items (
                    orden_compra_id, producto_id, descripcion, cantidad,
                    costo_unitario, subtotal, total, orden, creado_en
                ) VALUES (
                    :oc_id, :producto_id, :descripcion, :cantidad,
                    :costo_unitario, :subtotal, :total, :orden, NOW()
                )";

        $stmt = $this->db->prepare($sql);

        foreach ($items as $orden => $item) {
            $cantidad      = (float)($item['cantidad']       ?? 1);
            $costoUnitario = (float)($item['costo_unitario'] ?? 0);
            $subtotal      = $cantidad * $costoUnitario;
            $igvMonto      = $subtotal * 0.18;
            $total         = (float)($item['total'] ?? ($subtotal + $igvMonto));

            $stmt->execute([
                ':oc_id'          => $ocId,
                ':producto_id'    => !empty($item['producto_id']) ? (int)$item['producto_id'] : null,
                ':descripcion'    => trim($item['descripcion'] ?? ''),
                ':cantidad'       => $cantidad,
                ':costo_unitario' => $costoUnitario,
                ':subtotal'       => (float)($item['subtotal'] ?? $subtotal),
                ':total'          => $total,
                ':orden'          => $orden + 1,
            ]);
        }
    }

    /**
     * Actualiza el stock de un producto con costo promedio ponderado.
     * También registra el movimiento en movimientos_inventario.
     *
     * @param int   $productoId      ID del producto
     * @param float $cantidad        Cantidad recibida
     * @param float $costoUnitario   Costo unitario real
     * @param int   $ordenCompraId   ID de la OC de referencia
     * @param int   $userId          ID del usuario
     */
    private function actualizarStockEntrada(
        int $productoId,
        float $cantidad,
        float $costoUnitario,
        int $ordenCompraId,
        int $userId
    ): void {
        // Obtener stock y costo actual con lock para evitar race conditions
        $sqlGet = "SELECT stock_actual, costo_promedio FROM productos WHERE id = :id FOR UPDATE";
        $stmtGet = $this->db->prepare($sqlGet);
        $stmtGet->bindValue(':id', $productoId, PDO::PARAM_INT);
        $stmtGet->execute();
        $producto = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            return; // Producto no encontrado, saltar silenciosamente
        }

        $stockAnterior  = (float)$producto['stock_actual'];
        $costoAnterior  = (float)($producto['costo_promedio'] ?? 0);
        $stockNuevo     = $stockAnterior + $cantidad;

        // Calcular nuevo costo promedio ponderado (CPP)
        // CPP = (Stock actual * Costo actual + Cantidad nueva * Costo nuevo) / Stock nuevo
        $costoNuevoCPP = $stockNuevo > 0
            ? (($stockAnterior * $costoAnterior) + ($cantidad * $costoUnitario)) / $stockNuevo
            : $costoUnitario;

        // Actualizar stock y costo promedio en productos
        $sqlUpdate = "UPDATE productos
                      SET stock_actual    = :stock_nuevo,
                          costo_promedio  = :costo_promedio,
                          updated_at      = NOW()
                      WHERE id = :id";

        $stmtUpd = $this->db->prepare($sqlUpdate);
        $stmtUpd->execute([
            ':stock_nuevo'    => $stockNuevo,
            ':costo_promedio' => round($costoNuevoCPP, 4),
            ':id'             => $productoId,
        ]);

        // Registrar movimiento de inventario
        $sqlMov = "INSERT INTO movimientos_inventario (
                       producto_id, tipo, cantidad,
                       stock_anterior, stock_nuevo, costo_unitario,
                       ref_tipo, ref_id, notas, usuario_id, created_at
                   ) VALUES (
                       :producto_id, 'entrada', :cantidad,
                       :stock_anterior, :stock_nuevo, :costo_unitario,
                       'compra', :ref_id, :notas, :usuario_id, NOW()
                   )";

        $stmtMov = $this->db->prepare($sqlMov);
        $stmtMov->execute([
            ':producto_id'   => $productoId,
            ':cantidad'      => $cantidad,
            ':stock_anterior'=> $stockAnterior,
            ':stock_nuevo'   => $stockNuevo,
            ':costo_unitario'=> $costoUnitario,
            ':ref_id'        => $ordenCompraId,
            ':notas'         => "Entrada por OC #{$ordenCompraId}",
            ':usuario_id'    => $userId,
        ]);
    }

    /**
     * Determina el nuevo estado de una OC según las cantidades recibidas vs pedidas.
     *
     * @param int $ocId ID de la OC
     * @return string Estado calculado
     */
    private function calcularEstadoOC(int $ocId): string
    {
        $sql = "SELECT
                    SUM(oi.cantidad) AS total_pedido,
                    COALESCE(SUM(
                        (SELECT SUM(dr.cantidad_recibida)
                         FROM detalle_recepciones dr
                         INNER JOIN recepciones_compra rc ON dr.recepcion_id = rc.id
                         WHERE dr.orden_item_id = oi.id AND rc.estado = 'confirmada')
                    ), 0) AS total_recibido
                FROM orden_compra_items oi
                WHERE oi.orden_compra_id = :oc_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':oc_id', $ocId, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalPedido  = (float)($resultado['total_pedido']  ?? 0);
        $totalRecibido = (float)($resultado['total_recibido'] ?? 0);

        if ($totalRecibido <= 0) {
            return self::ESTADO_EMITIDA;
        } elseif ($totalRecibido >= $totalPedido) {
            return self::ESTADO_RECIBIDA;
        } else {
            return self::ESTADO_PARCIAL;
        }
    }
}
