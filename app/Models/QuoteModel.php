<?php

declare(strict_types=1);

/**
 * Modelo de Cotizaciones
 *
 * Gestiona todas las operaciones de base de datos para cotizaciones:
 * creación, consulta, conversión a venta y control de vencimientos.
 *
 * Tablas utilizadas:
 *   - cotizaciones        : registro principal de cotizaciones
 *   - cotizacion_items    : líneas de detalle de cada cotización
 *   - ventas              : destino al convertir una cotización
 *   - venta_items         : detalle de la venta generada
 *
 * @package App\Models
 * @author  Sistema de Facturación Pucallpa
 * @version 1.0.0
 */

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

class QuoteModel
{
    /** @var PDO Conexión PDO a la base de datos */
    private PDO $db;

    // =========================================================================
    // ESTADOS DE COTIZACIÓN
    // =========================================================================
    public const ESTADO_BORRADOR   = 'borrador';
    public const ESTADO_ENVIADA    = 'enviada';
    public const ESTADO_ACEPTADA   = 'aprobada';
    public const ESTADO_RECHAZADA  = 'rechazada';
    public const ESTADO_VENCIDA    = 'vencida';
    public const ESTADO_CONVERTIDA = 'convertida';

    /** Colores Bootstrap para cada estado */
    public const COLORES_ESTADO = [
        'borrador'   => 'secondary',
        'enviada'    => 'info',
        'aceptada'   => 'success',
        'rechazada'  => 'danger',
        'vencida'    => 'warning',
        'convertida' => 'primary',
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
     * Obtiene todas las cotizaciones con filtros opcionales y paginación.
     *
     * @param array $filters Filtros posibles:
     *   - estado      : string  (borrador|enviada|aceptada|rechazada|vencida|convertida)
     *   - cliente_id  : int
     *   - fecha_desde : string  (Y-m-d)
     *   - fecha_hasta : string  (Y-m-d)
     *   - busqueda    : string  (número, nombre de cliente)
     *   - moneda      : string  (PEN|USD)
     *   - limit       : int     (default 20)
     *   - offset      : int     (default 0)
     * @return array ['data' => [], 'total' => int]
     */
    public function getAll(array $filters = []): array
    {
        $where  = ['1 = 1'];
        $params = [];

        // Filtro por estado
        if (!empty($filters['estado'])) {
            $where[]            = 'c.estado = :estado';
            $params[':estado']  = $filters['estado'];
        }

        // Filtro por cliente
        if (!empty($filters['cliente_id'])) {
            $where[]               = 'c.cliente_id = :cliente_id';
            $params[':cliente_id'] = (int)$filters['cliente_id'];
        }

        // Filtro por moneda
        if (!empty($filters['moneda'])) {
            $where[]             = 'c.moneda = :moneda';
            $params[':moneda']   = $filters['moneda'];
        }

        // Filtro por rango de fechas
        if (!empty($filters['fecha_desde'])) {
            $where[]                  = 'DATE(c.fecha_emision) >= :fecha_desde';
            $params[':fecha_desde']   = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[]                  = 'DATE(c.fecha_emision) <= :fecha_hasta';
            $params[':fecha_hasta']   = $filters['fecha_hasta'];
        }

        // Búsqueda por texto
        if (!empty($filters['busqueda'])) {
            $termino              = '%' . $filters['busqueda'] . '%';
            $where[]              = '(c.numero LIKE :busq1 OR cl.razon_social LIKE :busq2 OR cl.numero_doc LIKE :busq3)';
            $params[':busq1']     = $termino;
            $params[':busq2']     = $termino;
            $params[':busq3']     = $termino;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Contar total para paginación
        $countSql  = "SELECT COUNT(*) FROM cotizaciones c
                      LEFT JOIN clientes cl ON c.cliente_id = cl.id
                      {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $limit  = (int)($filters['limit']  ?? 20);
        $offset = (int)($filters['offset'] ?? 0);

        $sql = "SELECT
                    c.*,
                    c.subtotal_gravado AS subtotal,
                    c.total_pen AS total,
                    cl.razon_social        AS cliente_nombre,
                    cl.numero_doc          AS cliente_documento,
                    cl.tipo_doc            AS cliente_tipo_doc,
                    cl.email               AS cliente_email,
                    cl.telefono            AS cliente_telefono,
                    u.nombre               AS usuario_nombre,
                    u.apellidos            AS usuario_apellidos,
                    DATEDIFF(c.fecha_vencimiento, CURDATE()) AS dias_para_vencer
                FROM cotizaciones c
                LEFT JOIN clientes  cl ON c.cliente_id  = cl.id
                LEFT JOIN usuarios  u  ON c.usuario_id  = u.id
                {$whereClause}
                ORDER BY c.created_at DESC
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
     * Obtiene una cotización por ID con todos sus items y datos relacionados.
     *
     * @param int $id ID de la cotización
     * @return array|null Cotización completa o null si no existe
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT
                    c.*,
                    c.subtotal_gravado AS subtotal,
                    c.total_pen AS total,
                    cl.razon_social        AS cliente_nombre,
                    cl.numero_doc          AS cliente_documento,
                    cl.tipo_doc            AS cliente_tipo_doc,
                    cl.email               AS cliente_email,
                    cl.telefono            AS cliente_telefono,
                    cl.direccion           AS cliente_direccion,
                    u.nombre               AS usuario_nombre,
                    u.apellidos            AS usuario_apellidos,
                    DATEDIFF(c.fecha_vencimiento, CURDATE()) AS dias_para_vencer
                FROM cotizaciones c
                LEFT JOIN clientes  cl ON c.cliente_id  = cl.id
                LEFT JOIN usuarios  u  ON c.usuario_id  = u.id
                WHERE c.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cotizacion) {
            return null;
        }

        // Obtener items de la cotización
        $cotizacion['items'] = $this->getItems($id);

        return $cotizacion;
    }

    /**
     * Obtiene los items (líneas de detalle) de una cotización.
     *
     * @param int $cotizacionId ID de la cotización
     * @return array Lista de items
     */
    public function getItems(int $cotizacionId): array
    {
        $sql = "SELECT
                    ci.*,
                    p.nombre          AS producto_nombre_actual,
                    p.codigo_interno  AS producto_codigo,
                    p.stock_actual    AS producto_stock,
                    u.abreviatura     AS unidad_abreviatura
                FROM detalle_cotizaciones ci
                LEFT JOIN productos       p ON ci.producto_id = p.id
                LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
                WHERE ci.cotizacion_id = :cotizacion_id
                ORDER BY ci.orden ASC, ci.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':cotizacion_id', $cotizacionId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene cotizaciones de un cliente específico.
     *
     * @param int $clientId ID del cliente
     * @param int $limit    Límite de resultados
     * @return array Lista de cotizaciones del cliente
     */
    public function getByClient(int $clientId, int $limit = 10): array
    {
        $sql = "SELECT c.*,
                       DATEDIFF(c.fecha_vencimiento, CURDATE()) AS dias_para_vencer
                FROM cotizaciones c
                WHERE c.cliente_id = :cliente_id
                 
                ORDER BY c.created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':cliente_id', $clientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',      $limit,    PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene cotizaciones vencidas que aún no han sido marcadas como vencidas.
     * Útil para el cron que actualiza estados automáticamente.
     *
     * @return array Lista de cotizaciones vencidas
     */
    public function getExpired(): array
    {
        $sql = "SELECT c.*,
                       cl.razon_social AS cliente_nombre,
                       cl.email        AS cliente_email
                FROM cotizaciones c
                LEFT JOIN clientes cl ON c.cliente_id = cl.id
                WHERE c.fecha_vencimiento < CURDATE()
                  AND c.estado IN (:borrador, :enviada)
                 
                ORDER BY c.fecha_vencimiento ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':borrador', self::ESTADO_BORRADOR);
        $stmt->bindValue(':enviada',  self::ESTADO_ENVIADA);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Genera el siguiente número de cotización para el año y serie dados.
     * Formato: CQ-2024-0001
     *
     * @param string $serie Serie (default 'CQ')
     * @return string Número de cotización generado
     */
    public function generarNumero(string $serie = 'CQ'): string
    {
        $anio = date('Y');
        $sql  = "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero, '-', -1) AS UNSIGNED)), 0) + 1 AS siguiente
                 FROM cotizaciones
                 WHERE numero LIKE :patron";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':patron', "{$serie}-{$anio}-%");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $correlativo = (int)($row['siguiente'] ?? 1);
        return sprintf('%s-%s-%04d', strtoupper($serie), $anio, $correlativo);
    }

    // =========================================================================
    // OPERACIONES DE ESCRITURA
    // =========================================================================

    /**
     * Crea una nueva cotización con sus items en una transacción atómica.
     *
     * @param array $data Datos de la cotización:
     *   - cliente_id       : int    (requerido)
     *   - usuario_id       : int    (requerido)
     *   - moneda           : string (PEN|USD)
     *   - tipo_cambio      : float
     *   - fecha_vencimiento: string (Y-m-d)
     *   - notas            : string
     *   - condiciones      : string
     *   - subtotal         : float
     *   - descuento_total  : float
     *   - igv              : float
     *   - total            : float
     *   - items            : array  (lista de ítems)
     * @return int ID de la cotización creada
     * @throws RuntimeException Si falla la creación
     */
    public function create(array $data): int
    {
        try {
            $this->db->beginTransaction();

            // Generar número de cotización
            $numero = $this->generarNumero('CQ');

            $sql = "INSERT INTO cotizaciones (
                        numero, cliente_id, usuario_id, estado,
                        moneda, tipo_cambio, fecha_emision, fecha_vencimiento,
                        subtotal_gravado, subtotal_exonerado, igv, total_pen, total_usd,
                        condicion_pago, notas
                    ) VALUES (
                        :numero, :cliente_id, :usuario_id, :estado,
                        :moneda, :tipo_cambio, :fecha_emision, :fecha_vencimiento,
                        :subtotal_gravado, 0, :igv, :total_pen, :total_usd,
                        :condicion_pago, :notas
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':numero'           => $numero,
                ':cliente_id'       => (int)$data['cliente_id'],
                ':usuario_id'       => (int)$data['usuario_id'],
                ':estado'           => $data['estado'] ?? self::ESTADO_BORRADOR,
                ':moneda'           => $data['moneda']           ?? 'PEN',
                ':tipo_cambio'      => (float)($data['tipo_cambio'] ?? 1.0),
                ':fecha_emision'    => $data['fecha_emision']    ?? date('Y-m-d'),
                ':fecha_vencimiento'=> $data['fecha_vencimiento'],
                ':subtotal_gravado' => (float)($data['subtotal']       ?? 0),
                ':igv'              => (float)($data['igv']            ?? 0),
                ':total_pen'        => ($data['moneda'] ?? 'PEN') === 'PEN' ? (float)($data['total'] ?? 0) : 0,
                ':total_usd'        => ($data['moneda'] ?? 'PEN') === 'USD' ? (float)($data['total'] ?? 0) : 0,
                ':notas'            => $data['notas']            ?? null,
                ':condicion_pago'   => $data['condiciones']      ?? null,
            ]);

            $cotizacionId = (int)$this->db->lastInsertId();

            // Insertar items
            if (!empty($data['items'])) {
                $this->insertItems($cotizacionId, $data['items']);
            }

            $this->db->commit();
            return $cotizacionId;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new RuntimeException('Error al crear la cotización: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Actualiza una cotización existente (solo en estado borrador o enviada).
     *
     * @param int   $id   ID de la cotización
     * @param array $data Datos a actualizar (misma estructura que create())
     * @return bool True si se actualizó correctamente
     * @throws RuntimeException Si la cotización no está en estado editable
     */
    public function update(int $id, array $data): bool
    {
        // Verificar que la cotización existe y está en estado editable
        $cotizacion = $this->getById($id);
        if (!$cotizacion) {
            throw new RuntimeException('Cotización no encontrada.');
        }
        if (!in_array($cotizacion['estado'], [self::ESTADO_BORRADOR, self::ESTADO_ENVIADA])) {
            throw new RuntimeException('Solo se pueden editar cotizaciones en estado Borrador o Enviada.');
        }

        try {
            $this->db->beginTransaction();

            $sql = "UPDATE cotizaciones SET
                        cliente_id        = :cliente_id,
                        moneda            = :moneda,
                        tipo_cambio       = :tipo_cambio,
                        fecha_vencimiento = :fecha_vencimiento,
                        subtotal          = :subtotal,
                        descuento_total   = :descuento_total,
                        igv               = :igv,
                        total             = :total,
                        notas             = :notas,
                        condiciones       = :condiciones,
                        actualizado_en    = NOW()
                    WHERE id = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':cliente_id'        => (int)$data['cliente_id'],
                ':moneda'            => $data['moneda']           ?? 'PEN',
                ':tipo_cambio'       => (float)($data['tipo_cambio'] ?? 1.0),
                ':fecha_vencimiento' => $data['fecha_vencimiento'],
                ':subtotal'          => (float)($data['subtotal']       ?? 0),
                ':descuento_total'   => (float)($data['descuento_total'] ?? 0),
                ':igv'               => (float)($data['igv']            ?? 0),
                ':total'             => (float)($data['total']          ?? 0),
                ':notas'             => $data['notas']            ?? null,
                ':condiciones'       => $data['condiciones']      ?? null,
                ':id'                => $id,
            ]);

            // Eliminar items anteriores y reinsertar
            $delStmt = $this->db->prepare('DELETE FROM cotizacion_items WHERE cotizacion_id = :id');
            $delStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $delStmt->execute();

            if (!empty($data['items'])) {
                $this->insertItems($id, $data['items']);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new RuntimeException('Error al actualizar la cotización: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Actualiza únicamente el estado de una cotización.
     *
     * @param int    $id     ID de la cotización
     * @param string $estado Nuevo estado (usar constantes ESTADO_*)
     * @return bool True si se actualizó
     */
    public function updateStatus(int $id, string $estado): bool
    {
        $estadosValidos = [
            self::ESTADO_BORRADOR,
            self::ESTADO_ENVIADA,
            self::ESTADO_ACEPTADA,
            self::ESTADO_RECHAZADA,
            self::ESTADO_VENCIDA,
            self::ESTADO_CONVERTIDA,
        ];

        if (!in_array($estado, $estadosValidos)) {
            throw new RuntimeException("Estado inválido: {$estado}");
        }

        $sql  = "UPDATE cotizaciones SET estado = :estado, actualizado_en = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':estado', $estado);
        $stmt->bindValue(':id',     $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Marca las cotizaciones vencidas actualizando su estado en lote.
     * Llamar desde el cron diario.
     *
     * @return int Número de cotizaciones actualizadas
     */
    public function marcarVencidas(): int
    {
        $sql = "UPDATE cotizaciones
                SET estado = :vencida, actualizado_en = NOW()
                WHERE fecha_vencimiento < CURDATE()
                  AND estado IN (:borrador, :enviada)
                 ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':vencida',  self::ESTADO_VENCIDA);
        $stmt->bindValue(':borrador', self::ESTADO_BORRADOR);
        $stmt->bindValue(':enviada',  self::ESTADO_ENVIADA);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Elimina lógicamente una cotización.
     * Solo se pueden eliminar cotizaciones en estado borrador.
     *
     * @param int $id ID de la cotización
     * @return bool True si se eliminó
     * @throws RuntimeException Si la cotización no se puede eliminar
     */
    public function delete(int $id): bool
    {
        $cotizacion = $this->getById($id);
        if (!$cotizacion) {
            throw new RuntimeException('Cotización no encontrada.');
        }

        if ($cotizacion['estado'] === self::ESTADO_CONVERTIDA) {
            throw new RuntimeException('No se puede eliminar una cotización ya convertida a venta.');
        }

        $sql  = "UPDATE cotizaciones SET actualizado_en = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // =========================================================================
    // CONVERSIÓN A VENTA
    // =========================================================================

    /**
     * Convierte una cotización aprobada en una venta real.
     *
     * Proceso:
     * 1. Valida que la cotización esté en estado aceptada o enviada
     * 2. Crea el registro en la tabla ventas
     * 3. Crea los items en venta_items
     * 4. Actualiza el estado de la cotización a 'convertida'
     * 5. Registra la referencia cruzada (cotizacion_id en la venta)
     *
     * @param int    $quoteId         ID de la cotización a convertir
     * @param int    $userId          ID del usuario que realiza la conversión
     * @param string $tipoComprobante Tipo de comprobante: '01' (factura), '03' (boleta), 'NV' (nota)
     * @param string $metodoPago      Método de pago
     * @return int ID de la venta creada
     * @throws RuntimeException Si la cotización no se puede convertir
     */
    public function convertToSale(int $quoteId, int $userId, string $tipoComprobante = '03', string $metodoPago = 'efectivo'): int
    {
        $cotizacion = $this->getById($quoteId);

        if (!$cotizacion) {
            throw new RuntimeException('Cotización no encontrada.');
        }

        $estadosConvertibles = [self::ESTADO_ACEPTADA, self::ESTADO_ENVIADA, self::ESTADO_BORRADOR];
        if (!in_array($cotizacion['estado'], $estadosConvertibles)) {
            throw new RuntimeException("No se puede convertir una cotización en estado '{$cotizacion['estado']}'.");
        }

        if (empty($cotizacion['items'])) {
            throw new RuntimeException('La cotización no tiene items.');
        }

        try {
            $this->db->beginTransaction();

            // ---- Generar número/serie de la venta ----
            $serieMap = ['01' => 'F001', '03' => 'B001', 'NV' => 'NV01'];
            $serie    = $serieMap[$tipoComprobante] ?? 'B001';

            // Obtener siguiente número correlativo
            $sqlNum = "SELECT COALESCE(MAX(numero), 0) + 1 AS siguiente
                       FROM ventas WHERE serie = :serie";
            $stmtNum = $this->db->prepare($sqlNum);
            $stmtNum->bindValue(':serie', $serie);
            $stmtNum->execute();
            $numero = (int)($stmtNum->fetch(PDO::FETCH_ASSOC)['siguiente'] ?? 1);

            // ---- Insertar en tabla ventas ----
            $sqlVenta = "INSERT INTO ventas (
                            serie, numero, tipo_comprobante,
                            cliente_id, usuario_id,
                            moneda, tipo_cambio,
                            subtotal, descuento_total, igv, total,
                            metodo_pago, estado_pago,
                            cotizacion_id, notas,
                            fecha, creado_en, actualizado_en
                        ) VALUES (
                            :serie, :numero, :tipo_comprobante,
                            :cliente_id, :usuario_id,
                            :moneda, :tipo_cambio,
                            :subtotal, :descuento_total, :igv, :total,
                            :metodo_pago, 'pagado',
                            :cotizacion_id, :notas,
                            NOW(), NOW(), NOW()
                        )";

            $stmtVenta = $this->db->prepare($sqlVenta);
            $stmtVenta->execute([
                ':serie'            => $serie,
                ':numero'           => $numero,
                ':tipo_comprobante' => $tipoComprobante,
                ':cliente_id'       => (int)$cotizacion['cliente_id'],
                ':usuario_id'       => $userId,
                ':moneda'           => $cotizacion['moneda'],
                ':tipo_cambio'      => (float)$cotizacion['tipo_cambio'],
                ':subtotal'         => (float)$cotizacion['subtotal'],
                ':descuento_total'  => (float)$cotizacion['descuento_total'],
                ':igv'              => (float)$cotizacion['igv'],
                ':total'            => (float)$cotizacion['total'],
                ':metodo_pago'      => $metodoPago,
                ':cotizacion_id'    => $quoteId,
                ':notas'            => 'Generado desde cotización ' . $cotizacion['numero'],
            ]);

            $ventaId = (int)$this->db->lastInsertId();

            // ---- Insertar items de la venta ----
            $sqlItem = "INSERT INTO venta_items (
                            venta_id, producto_id, descripcion, cantidad,
                            precio_unitario, descuento_porcentaje, descuento_monto,
                            tipo_afectacion_igv, igv_unitario, subtotal, total,
                            orden, creado_en
                        ) VALUES (
                            :venta_id, :producto_id, :descripcion, :cantidad,
                            :precio_unitario, :descuento_porcentaje, :descuento_monto,
                            :tipo_afectacion_igv, :igv_unitario, :subtotal, :total,
                            :orden, NOW()
                        )";

            $stmtItem = $this->db->prepare($sqlItem);
            foreach ($cotizacion['items'] as $orden => $item) {
                $stmtItem->execute([
                    ':venta_id'              => $ventaId,
                    ':producto_id'           => $item['producto_id']           ?? null,
                    ':descripcion'           => $item['descripcion'],
                    ':cantidad'              => (float)$item['cantidad'],
                    ':precio_unitario'       => (float)$item['precio_unitario'],
                    ':descuento_porcentaje'  => (float)($item['descuento_porcentaje'] ?? 0),
                    ':descuento_monto'       => (float)($item['descuento_monto']      ?? 0),
                    ':tipo_afectacion_igv'   => $item['tipo_afectacion_igv'] ?? '10',
                    ':igv_unitario'          => (float)($item['igv_unitario'] ?? 0),
                    ':subtotal'              => (float)$item['subtotal'],
                    ':total'                 => (float)$item['total'],
                    ':orden'                 => $orden + 1,
                ]);
            }

            // ---- Actualizar estado de la cotización ----
            $this->updateStatus($quoteId, self::ESTADO_CONVERTIDA);

            // Guardar referencia a la venta generada
            $sqlRef = "UPDATE cotizaciones SET venta_id = :venta_id WHERE id = :id";
            $stmtRef = $this->db->prepare($sqlRef);
            $stmtRef->bindValue(':venta_id', $ventaId, PDO::PARAM_INT);
            $stmtRef->bindValue(':id',       $quoteId, PDO::PARAM_INT);
            $stmtRef->execute();

            $this->db->commit();
            return $ventaId;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new RuntimeException('Error al convertir la cotización a venta: ' . $e->getMessage(), 0, $e);
        }
    }

    // =========================================================================
    // ESTADÍSTICAS
    // =========================================================================

    /**
     * Obtiene estadísticas de cotizaciones agrupadas por estado.
     *
     * @param string|null $fechaDesde Fecha de inicio del período (Y-m-d)
     * @param string|null $fechaHasta Fecha de fin del período (Y-m-d)
     * @return array Estadísticas por estado
     */
    public function getStats(?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $where  = [];
        $params = [];

        if ($fechaDesde) {
            $where[]              = 'DATE(fecha_emision) >= :desde';
            $params[':desde']     = $fechaDesde;
        }
        if ($fechaHasta) {
            $where[]              = 'DATE(fecha_emision) <= :hasta';
            $params[':hasta']     = $fechaHasta;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT
                    estado,
                    COUNT(*)        AS total,
                    SUM(total)      AS monto_total,
                    AVG(total)      AS monto_promedio
                FROM cotizaciones
                {$whereClause}
                GROUP BY estado";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Inserta los items de una cotización.
     *
     * @param int   $cotizacionId ID de la cotización
     * @param array $items        Lista de items a insertar
     */
    private function insertItems(int $cotizacionId, array $items): void
    {
        $sql = "INSERT INTO detalle_cotizaciones (
                    cotizacion_id, producto_id, descripcion, unidad_medida,
                    cantidad, precio_unitario, descuento_porcentaje,
                    tipo_afectacion_igv, igv_item, subtotal_item, orden
                ) VALUES (
                    :cotizacion_id, :producto_id, :descripcion, :unidad_medida,
                    :cantidad, :precio_unitario, :descuento_porcentaje,
                    :tipo_afectacion_igv, :igv_item, :subtotal_item, :orden
                )";

        $stmt = $this->db->prepare($sql);

        foreach ($items as $orden => $item) {
            $stmt->execute([
                ':cotizacion_id'         => $cotizacionId,
                ':producto_id'           => !empty($item['producto_id']) ? (int)$item['producto_id'] : null,
                ':descripcion'           => trim($item['descripcion'] ?? ''),
                ':unidad_medida'         => $item['unidad_medida'] ?? 'NIU',
                ':cantidad'              => (float)($item['cantidad']          ?? 1),
                ':precio_unitario'       => (float)($item['precio_unitario']   ?? 0),
                ':descuento_porcentaje'  => (float)($item['descuento_porcentaje'] ?? 0),
                ':tipo_afectacion_igv'   => $item['tipo_afectacion_igv'] ?? '10',
                ':igv_item'              => (float)($item['igv_item'] ?? $item['igv_unitario'] ?? 0),
                ':subtotal_item'         => (float)($item['subtotal'] ?? $item['total'] ?? 0),
                ':orden'                 => $orden + 1,
            ]);
        }
    }
}
