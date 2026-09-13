<?php

declare(strict_types=1);

/**
 * Modelo de Comprobantes Electrónicos SUNAT
 *
 * Gestiona todas las operaciones de base de datos relacionadas con comprobantes
 * electrónicos: facturas, boletas, notas de crédito, notas de débito y la cola
 * de procesamiento hacia SUNAT.
 *
 * Tablas utilizadas:
 *   - comprobantes_electronicos : registro principal de comprobantes
 *   - sunat_cola_envios         : cola de operaciones pendientes hacia SUNAT
 *
 * @package App\Models
 * @author  Sistema de Facturación Pucallpa
 * @version 1.0.0
 */

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

class ComprobanteModel
{
    /** @var PDO Conexión PDO a la base de datos */
    private PDO $db;

    // =========================================================================
    // ESTADOS DE COMPROBANTE (deben coincidir con las constantes en app.php)
    // =========================================================================
    public const ESTADO_EMITIDO   = 1;
    public const ESTADO_ENVIADO   = 2;
    public const ESTADO_ACEPTADO  = 3;
    public const ESTADO_RECHAZADO = 4;
    public const ESTADO_ANULADO   = 5;
    public const ESTADO_BORRADOR  = 6;

    // =========================================================================
    // ESTADOS DE COLA
    // =========================================================================
    public const COLA_PENDIENTE  = 'pendiente';
    public const COLA_PROCESANDO = 'procesando';
    public const COLA_COMPLETADO = 'completado';
    public const COLA_ERROR      = 'error';
    public const COLA_REINTENTO  = 'reintento';

    // =========================================================================
    // TIPOS DE OPERACIÓN EN COLA
    // =========================================================================
    public const OP_ENVIO_FACTURA  = 'envio_factura';
    public const OP_ENVIO_BOLETA   = 'envio_boleta';
    public const OP_RESUMEN_RC     = 'resumen_rc';
    public const OP_BAJA_RA        = 'baja_ra';
    public const OP_NOTA_CREDITO   = 'nota_credito';
    public const OP_NOTA_DEBITO    = 'nota_debito';

    /**
     * Constructor: inyecta la conexión PDO
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // CRUD PRINCIPAL
    // =========================================================================

    /**
     * Crea un nuevo registro de comprobante electrónico.
     *
     * @param array $data Datos del comprobante:
     *   - venta_id        : int    (requerido) ID de la venta asociada
     *   - tipo_comprobante: string (requerido) '01'=Factura, '03'=Boleta, '07'=NC, '08'=ND
     *   - serie           : string (requerido) Ej: 'F001'
     *   - numero          : int    (requerido) Correlativo
     *   - xml_path        : string (opcional) Ruta al XML generado
     *   - hash            : string (opcional) Hash del XML firmado
     *   - estado          : int    (opcional) Por defecto: ESTADO_EMITIDO
     * @return int ID del comprobante creado
     * @throws RuntimeException Si falla la inserción
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO comprobantes_electronicos
                    (venta_id, tipo_comprobante, serie, numero, xml_path, hash, estado, creado_en)
                VALUES
                    (:venta_id, :tipo_comprobante, :serie, :numero, :xml_path, :hash, :estado, NOW())";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':venta_id',         (int)$data['venta_id'],                PDO::PARAM_INT);
            $stmt->bindValue(':tipo_comprobante',  $data['tipo_comprobante'],             PDO::PARAM_STR);
            $stmt->bindValue(':serie',             strtoupper($data['serie']),            PDO::PARAM_STR);
            $stmt->bindValue(':numero',            (int)$data['numero'],                  PDO::PARAM_INT);
            $stmt->bindValue(':xml_path',          $data['xml_path']    ?? null,          PDO::PARAM_STR);
            $stmt->bindValue(':hash',              $data['hash']        ?? null,          PDO::PARAM_STR);
            $stmt->bindValue(':estado',            (int)($data['estado'] ?? self::ESTADO_EMITIDO), PDO::PARAM_INT);
            $stmt->execute();

            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            $this->log("Error al crear comprobante: " . $e->getMessage());
            throw new RuntimeException('No se pudo crear el registro del comprobante.', 0, $e);
        }
    }

    /**
     * Actualiza el estado y respuesta SUNAT de un comprobante.
     *
     * @param int         $id       ID del comprobante
     * @param int         $estado   Nuevo estado (usar constantes de esta clase)
     * @param string|null $cdr      Ruta al archivo CDR recibido de SUNAT
     * @param string|null $mensaje  Mensaje de respuesta SUNAT
     * @param string|null $codigo   Código de respuesta SUNAT (Ej: '0', '2100')
     * @param string|null $ticket   Número de ticket SUNAT para consulta asíncrona
     * @return bool true si la actualización fue exitosa
     */
    public function updateEstado(
        int $id,
        int $estado,
        ?string $cdr     = null,
        ?string $mensaje = null,
        ?string $codigo  = null,
        ?string $ticket  = null
    ): bool {
        $sql = "UPDATE comprobantes_electronicos
                SET estado           = :estado,
                    cdr_path         = COALESCE(:cdr, cdr_path),
                    sunat_mensaje    = COALESCE(:mensaje, sunat_mensaje),
                    sunat_codigo     = COALESCE(:codigo, sunat_codigo),
                    ticket_sunat     = COALESCE(:ticket, ticket_sunat),
                    enviado_en       = CASE WHEN :estado_check >= :estado_enviado THEN NOW() ELSE enviado_en END,
                    actualizado_en   = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':estado',        $estado,     PDO::PARAM_INT);
        $stmt->bindValue(':cdr',           $cdr,        PDO::PARAM_STR);
        $stmt->bindValue(':mensaje',       $mensaje,    PDO::PARAM_STR);
        $stmt->bindValue(':codigo',        $codigo,     PDO::PARAM_STR);
        $stmt->bindValue(':ticket',        $ticket,     PDO::PARAM_STR);
        $stmt->bindValue(':estado_check',  $estado,     PDO::PARAM_INT);
        $stmt->bindValue(':estado_enviado', self::ESTADO_ENVIADO, PDO::PARAM_INT);
        $stmt->bindValue(':id',            $id,         PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Actualiza la ruta del XML y el hash del comprobante.
     *
     * @param int    $id      ID del comprobante
     * @param string $xmlPath Ruta al XML firmado
     * @param string $hash    Hash del XML
     * @return bool
     */
    public function updateXml(int $id, string $xmlPath, string $hash): bool
    {
        $sql = "UPDATE comprobantes_electronicos
                SET xml_path = :xml_path, hash = :hash, actualizado_en = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':xml_path', $xmlPath, PDO::PARAM_STR);
        $stmt->bindValue(':hash',     $hash,    PDO::PARAM_STR);
        $stmt->bindValue(':id',       $id,      PDO::PARAM_INT);

        return $stmt->execute();
    }

/**
     * Obtiene el listado de comprobantes con filtros avanzados para la gestión SUNAT.
     * 
     * @param array $filters Filtros: fecha_desde, fecha_hasta, tipo_comprobante, estado, busqueda, export, limit, offset.
     * @return array
     */
    public function getListado(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['fecha_desde'])) {
            $where[] = "v.fecha_emision >= :desde";
            $params[':desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[] = "v.fecha_emision <= :hasta";
            $params[':hasta'] = $filters['fecha_hasta'];
        }
        if (!empty($filters['tipo_comprobante'])) {
            $where[] = "ce.tipo_comprobante = :tipo";
            $params[':tipo'] = $filters['tipo_comprobante'];
        }

        // --- FILTRO DE ESTADO SUNAT CORREGIDO ---
        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $mapEstados = [
                '1' => 'pendiente',
                '2' => 'enviado',
                '3' => 'aceptado',
                '4' => 'rechazado'
            ];
            $estadoValor = $mapEstados[$filters['estado']] ?? $filters['estado'];

            $where[] = "ce.estado_sunat = :estado";
            $params[':estado'] = $estadoValor;
        }

        if (!empty($filters['busqueda'])) {
            $where[] = "(CONCAT(ce.serie, '-', ce.numero) LIKE :search OR c.razon_social LIKE :search2 OR c.numero_doc LIKE :search3)";
            $search = "%" . $filters['busqueda'] . "%";
            $params[':search']  = $search;
            $params[':search2'] = $search;
            $params[':search3'] = $search;
        }

        $sql = "SELECT ce.*, 
                       ce.estado_sunat AS estado,
                       ce.codigo_respuesta AS sunat_codigo,
                       v.fecha_emision AS fecha_emision_real, 
                       v.total_pen AS total, 
                       v.igv, 
                       v.subtotal_gravado AS subtotal, 
                       v.moneda,
                       COALESCE(c.razon_social, CONCAT(c.nombres, ' ', c.apellidos), 'Cliente General') AS cliente_nombre, 
                       c.numero_doc AS cliente_doc,
                       c.tipo_doc AS cliente_tipo_doc
                FROM comprobantes_electronicos ce
                INNER JOIN ventas v ON ce.venta_id = v.id
                LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY v.fecha_emision DESC, ce.id DESC";
        
        if (empty($filters['export'])) {
            $limit  = (int)($filters['limit'] ?? 50);
            $offset = (int)($filters['offset'] ?? 0);
            $sql .= " LIMIT $limit OFFSET $offset";
        }

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("ComprobanteModel::getListado - Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta el total de comprobantes bajo los filtros actuales (para paginación).
     */
    public function countListado(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['fecha_desde'])) {
            $where[] = "v.fecha_emision >= :desde";
            $params[':desde'] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[] = "v.fecha_emision <= :hasta";
            $params[':hasta'] = $filters['fecha_hasta'];
        }
        if (!empty($filters['tipo_comprobante'])) {
            $where[] = "ce.tipo_comprobante = :tipo";
            $params[':tipo'] = $filters['tipo_comprobante'];
        }

        // --- FILTRO DE ESTADO SUNAT CORREGIDO ---
        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $mapEstados = [
                '1' => 'pendiente',
                '2' => 'enviado',
                '3' => 'aceptado',
                '4' => 'rechazado'
            ];
            $estadoValor = $mapEstados[$filters['estado']] ?? $filters['estado'];

            $where[] = "ce.estado_sunat = :estado";
            $params[':estado'] = $estadoValor;
        }

        if (!empty($filters['busqueda'])) {
            $where[] = "(CONCAT(ce.serie, '-', ce.numero) LIKE :search OR c.razon_social LIKE :search2 OR c.numero_doc LIKE :search3)";
            $search = "%" . $filters['busqueda'] . "%";
            $params[':search']  = $search;
            $params[':search2'] = $search;
            $params[':search3'] = $search;
        }

        $sql = "SELECT COUNT(*) 
                FROM comprobantes_electronicos ce
                INNER JOIN ventas v ON ce.venta_id = v.id
                LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE " . implode(' AND ', $where);

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("ComprobanteModel::countListado - Error: " . $e->getMessage());
            return 0;
        }
    }

    // =========================================================================
    // CONSULTAS POR VENTA
    // =========================================================================

    /**
     * Obtiene el comprobante electrónico asociado a una venta.
     *
     * @param int $ventaId ID de la venta
     * @return array|false Datos del comprobante o false si no existe
     */
    public function getByVenta(int $ventaId): array|false
    {
        $sql = "SELECT ce.*,
                       v.serie         AS venta_serie,
                       v.numero        AS venta_numero,
                       v.total         AS venta_total,
                       v.tipo_comprobante AS tipo_venta
                FROM comprobantes_electronicos ce
                INNER JOIN ventas v ON ce.venta_id = v.id
                WHERE ce.venta_id = :venta_id
                ORDER BY ce.id DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':venta_id', $ventaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un comprobante por su ID.
     *
     * @param int $id ID del comprobante
     * @return array|false
     */
    public function findById(int $id): array|false
    {
        $sql = "SELECT ce.*,
                       v.cliente_id, v.total, v.igv, v.subtotal,
                       v.moneda, v.tipo_cambio, v.fecha AS fecha_emision
                FROM comprobantes_electronicos ce
                LEFT JOIN ventas v ON ce.venta_id = v.id
                WHERE ce.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un comprobante por su serie y número.
     *
     * @param string $serie  Serie (Ej: 'F001')
     * @param int    $numero Número correlativo
     * @return array|false
     */
    public function findBySeriNumero(string $serie, int $numero): array|false
    {
        $sql = "SELECT * FROM comprobantes_electronicos
                WHERE serie = :serie AND numero = :numero
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':serie',  strtoupper($serie), PDO::PARAM_STR);
        $stmt->bindValue(':numero', $numero,            PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // CONSULTAS DE ESTADO SUNAT
    // =========================================================================

    /**
     * Obtiene todos los comprobantes pendientes de envío a SUNAT.
     * Solo facturas, notas de crédito y notas de débito (boletas van por RC).
     *
     * @return array Lista de comprobantes pendientes
     */
    public function getPendientes(): array
    {
        $sql = "SELECT ce.*, v.total, v.igv, v.subtotal, v.moneda,
                       v.fecha AS fecha_emision, v.cliente_id,
                       c.razon_social AS cliente_nombre,
                       c.num_documento AS cliente_doc
                FROM comprobantes_electronicos ce
                INNER JOIN ventas v ON ce.venta_id = v.id
                LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE ce.estado = :estado_emitido
                  AND ce.tipo_comprobante IN ('01', '07', '08')
                ORDER BY ce.creado_en ASC
                LIMIT 50";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':estado_emitido', self::ESTADO_EMITIDO, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene boletas del día pendientes de incluir en resumen diario (RC).
     *
     * @param string $fecha Fecha en formato 'Y-m-d'
     * @return array Lista de boletas del día
     */
    public function getBoletasPendientesRC(string $fecha): array
    {
        $sql = "SELECT ce.*, v.total, v.igv, v.subtotal, v.moneda,
                       v.fecha_emision
                FROM comprobantes_electronicos ce
                INNER JOIN ventas v ON ce.venta_id = v.id
                WHERE ce.tipo_comprobante = '03'
                  AND ce.estado IN (:emitido, :enviado)
                  AND DATE(ce.creado_en) = :fecha
                  AND ce.en_resumen = 0
                ORDER BY ce.numero ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':emitido', self::ESTADO_EMITIDO, PDO::PARAM_INT);
        $stmt->bindValue(':enviado', self::ESTADO_ENVIADO, PDO::PARAM_INT);
        $stmt->bindValue(':fecha',   $fecha,               PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene comprobantes con ticket SUNAT pendiente de consulta.
     *
     * @return array Lista de comprobantes con ticket pendiente
     */
    public function getPendientesTicket(): array
    {
        $sql = "SELECT * FROM comprobantes_electronicos
                WHERE estado = :estado_enviado
                  AND ticket_sunat IS NOT NULL
                  AND ticket_sunat != ''
                ORDER BY enviado_en ASC
                LIMIT 20";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':estado_enviado', self::ESTADO_ENVIADO, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los comprobantes rechazados susceptibles de reintento.
     *
     * @param int $maxIntentos Máximo número de intentos antes de descartar
     * @return array Lista de comprobantes rechazados
     */
    public function getRechazadosParaReintento(int $maxIntentos = 3): array
    {
        $sql = "SELECT ce.*, q.intentos, q.id AS queue_id
                FROM comprobantes_electronicos ce
                INNER JOIN sunat_cola_envios q ON q.comprobante_id = ce.id
                WHERE ce.estado = :estado_rechazado
                  AND q.estado = :cola_error
                  AND q.intentos < :max_intentos
                ORDER BY q.ultimo_intento ASC
                LIMIT 20";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':estado_rechazado', self::ESTADO_RECHAZADO, PDO::PARAM_INT);
        $stmt->bindValue(':cola_error',       self::COLA_ERROR,       PDO::PARAM_STR);
        $stmt->bindValue(':max_intentos',     $maxIntentos,           PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca una boleta como incluida en un resumen diario (RC).
     *
     * @param int $comprobanteId ID del comprobante
     * @param int $resumenId     ID del resumen RC
     * @return bool
     */
    public function marcarEnResumen(int $comprobanteId, int $resumenId): bool
    {
        $sql = "UPDATE comprobantes_electronicos
                SET en_resumen = 1, resumen_id = :resumen_id, actualizado_en = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':resumen_id', $resumenId,     PDO::PARAM_INT);
        $stmt->bindValue(':id',         $comprobanteId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // =========================================================================
    // COLA DE PROCESAMIENTO
    // =========================================================================

    /**
     * Obtiene los ítems de la cola pendientes de procesamiento.
     *
     * @param int $limite Máximo de ítems a devolver
     * @return array Ítems de la cola
     */
    public function getForColaProcessing(int $limite = 20): array
    {
        $sql = "SELECT q.*,
                       ce.serie, ce.numero, ce.tipo_comprobante, ce.estado AS comp_estado,
                       v.total AS venta_total
                FROM sunat_cola_envios q
                INNER JOIN comprobantes_electronicos ce ON q.comprobante_id = ce.id
                LEFT JOIN ventas v ON ce.venta_id = v.id
                WHERE q.estado IN (:pendiente, :reintento)
                  AND (q.proximo_intento IS NULL OR q.proximo_intento <= NOW())
                ORDER BY q.prioridad DESC, q.creado_en ASC
                LIMIT :limite";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':pendiente',  self::COLA_PENDIENTE,  PDO::PARAM_STR);
        $stmt->bindValue(':reintento',  self::COLA_REINTENTO,  PDO::PARAM_STR);
        $stmt->bindValue(':limite',     $limite,               PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los ítems de la cola con información completa.
     *
     * @param string|null $filtroEstado Filtrar por estado ('pendiente', 'error', etc.) o null para todos
     * @param int         $limite       Límite de resultados
     * @return array
     */
    public function getColaCompleta(?string $filtroEstado = null, int $limite = 50): array
    {
        $condicion = $filtroEstado ? "AND q.estado = :estado" : "";

        $sql = "SELECT q.*,
                       ce.serie, ce.numero, ce.tipo_comprobante,
                       v.total AS venta_total,
                       cl.razon_social AS cliente_nombre
                FROM sunat_cola_envios q
                INNER JOIN comprobantes_electronicos ce ON q.comprobante_id = ce.id
                LEFT JOIN ventas v ON ce.venta_id = v.id
                LEFT JOIN clientes cl ON v.cliente_id = cl.id
                WHERE 1=1 {$condicion}
                ORDER BY q.creado_en DESC
                LIMIT :limite";

        $stmt = $this->db->prepare($sql);
        if ($filtroEstado) {
            $stmt->bindValue(':estado', $filtroEstado, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Agrega un comprobante a la cola de envío SUNAT.
     *
     * @param int    $comprobanteId ID del comprobante
     * @param string $tipoOp        Tipo de operación (usar constantes OP_*)
     * @param int    $prioridad     Prioridad: 1=normal, 2=alta, 3=urgente
     * @return int ID del registro en cola creado
     */
    public function addToQueue(int $comprobanteId, string $tipoOp, int $prioridad = 1): int
    {
        // Evitar duplicados: verificar si ya existe en cola para este comprobante
        $check = $this->db->prepare(
            "SELECT id FROM sunat_cola_envios
             WHERE comprobante_id = :id AND estado IN ('pendiente', 'procesando', 'reintento')
             LIMIT 1"
        );
        $check->bindValue(':id', $comprobanteId, PDO::PARAM_INT);
        $check->execute();
        $existe = $check->fetch(PDO::FETCH_ASSOC);

        if ($existe) {
            return (int)$existe['id']; // Ya está en cola, no duplicar
        }

        $sql = "INSERT INTO sunat_cola_envios
                    (comprobante_id, tipo_operacion, estado, intentos, prioridad, creado_en)
                VALUES
                    (:comprobante_id, :tipo_op, :estado, 0, :prioridad, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':comprobante_id', $comprobanteId,      PDO::PARAM_INT);
        $stmt->bindValue(':tipo_op',        $tipoOp,             PDO::PARAM_STR);
        $stmt->bindValue(':estado',         self::COLA_PENDIENTE, PDO::PARAM_STR);
        $stmt->bindValue(':prioridad',      $prioridad,          PDO::PARAM_INT);
        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * Actualiza el estado de un ítem en la cola.
     *
     * @param int         $queueId        ID del registro en cola
     * @param string      $estado         Nuevo estado (usar constantes COLA_*)
     * @param string|null $error          Mensaje de error si aplica
     * @param int|null    $proximoIntento Segundos hasta el próximo intento
     * @return bool
     */
    public function updateQueueStatus(
        int $queueId,
        string $estado,
        ?string $error          = null,
        ?int $proximoIntento    = null
    ): bool {
        // Calcular la fecha del próximo intento con backoff exponencial
        $nextRetry = null;
        if ($proximoIntento !== null) {
            $nextRetry = date('Y-m-d H:i:s', time() + $proximoIntento);
        }

        $sql = "UPDATE sunat_cola_envios
                SET estado          = :estado,
                    intentos        = intentos + 1,
                    ultimo_intento  = NOW(),
                    error_mensaje   = :error,
                    proximo_intento = :proximo_intento,
                    actualizado_en  = NOW()
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':estado',          $estado,     PDO::PARAM_STR);
        $stmt->bindValue(':error',           $error,      PDO::PARAM_STR);
        $stmt->bindValue(':proximo_intento', $nextRetry,  PDO::PARAM_STR);
        $stmt->bindValue(':id',              $queueId,    PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Marca un ítem de cola como 'procesando' para evitar doble procesamiento.
     *
     * @param int $queueId ID del registro en cola
     * @return bool
     */
    public function marcarProcesando(int $queueId): bool
    {
        $sql = "UPDATE sunat_cola_envios
                SET estado = :procesando, actualizado_en = NOW()
                WHERE id = :id AND estado != :procesando_check";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':procesando',       self::COLA_PROCESANDO, PDO::PARAM_STR);
        $stmt->bindValue(':procesando_check', self::COLA_PROCESANDO, PDO::PARAM_STR);
        $stmt->bindValue(':id',               $queueId,              PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    // =========================================================================
    // ESTADÍSTICAS
    // =========================================================================

    /**
     * Retorna estadísticas generales de comprobantes electrónicos.
     *
     * @return array Mapa con conteos y totales
     */
    public function getStats(): array
    {
        // Estadísticas de comprobantes por estado
        $sqlEstados = "SELECT estado, COUNT(*) AS total
                       FROM comprobantes_electronicos
                       GROUP BY estado";

        $stmtE = $this->db->prepare($sqlEstados);
        $stmtE->execute();
        $porEstado = $stmtE->fetchAll(PDO::FETCH_ASSOC);

        // Estadísticas de cola
        $sqlCola = "SELECT estado, COUNT(*) AS total,
                           MAX(intentos) AS max_intentos
                    FROM sunat_cola_envios
                    GROUP BY estado";

        $stmtC = $this->db->prepare($sqlCola);
        $stmtC->execute();
        $cola = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        // Comprobantes de hoy
        $sqlHoy = "SELECT
                       COUNT(*) AS total_hoy,
                       SUM(CASE WHEN estado = 3 THEN 1 ELSE 0 END) AS aceptados_hoy,
                       SUM(CASE WHEN estado = 4 THEN 1 ELSE 0 END) AS rechazados_hoy
                   FROM comprobantes_electronicos
                   WHERE DATE(creado_en) = CURDATE()";

        $stmtH = $this->db->prepare($sqlHoy);
        $stmtH->execute();
        $hoy = $stmtH->fetch(PDO::FETCH_ASSOC);

        // Último comprobante enviado
        $sqlUltimo = "SELECT ce.serie, ce.numero, ce.tipo_comprobante,
                             ce.estado, ce.sunat_mensaje, ce.actualizado_en
                      FROM comprobantes_electronicos ce
                      WHERE ce.estado IN (2, 3, 4)
                      ORDER BY ce.actualizado_en DESC
                      LIMIT 5";

        $stmtU = $this->db->prepare($sqlUltimo);
        $stmtU->execute();
        $ultimos = $stmtU->fetchAll(PDO::FETCH_ASSOC);

        return [
            'por_estado' => $porEstado,
            'cola'       => $cola,
            'hoy'        => $hoy,
            'ultimos'    => $ultimos,
        ];
    }

    /**
     * Obtiene el siguiente número correlativo para una serie dada.
     *
     * @param string $serie Serie del comprobante (Ej: 'F001')
     * @return int Siguiente número correlativo
     */
    public function getNextNumero(string $serie): int
    {
        $sql = "SELECT COALESCE(MAX(numero), 0) + 1 AS siguiente
                FROM comprobantes_electronicos
                WHERE serie = :serie";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':serie', strtoupper($serie), PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($row['siguiente'] ?? 1);
    }

    // =========================================================================
    // LOGS DE OPERACIONES SUNAT
    // =========================================================================

    /**
     * Registra una operación SUNAT en el log de auditoría.
     *
     * @param int    $comprobanteId ID del comprobante
     * @param string $operacion     Tipo de operación
     * @param string $resultado     'exito' | 'error'
     * @param string $detalle       Detalle de la respuesta
     * @return bool
     */
    public function registrarLog(
        int $comprobanteId,
        string $operacion,
        string $resultado,
        string $detalle
    ): bool {
        $sql = "INSERT INTO sunat_logs
                    (comprobante_id, operacion, resultado, detalle, creado_en)
                VALUES
                    (:comprobante_id, :operacion, :resultado, :detalle, NOW())";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':comprobante_id', $comprobanteId, PDO::PARAM_INT);
            $stmt->bindValue(':operacion',      $operacion,     PDO::PARAM_STR);
            $stmt->bindValue(':resultado',      $resultado,     PDO::PARAM_STR);
            $stmt->bindValue(':detalle',        $detalle,       PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            // Si no existe la tabla de logs, no fallar silenciosamente
            $this->log("Error al registrar log SUNAT: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene los últimos logs de operaciones SUNAT.
     *
     * @param int $limite Cantidad de registros a retornar
     * @return array
     */
    public function getUltimosLogs(int $limite = 20): array
    {
        try {
            $sql = "SELECT sl.*, ce.serie, ce.numero, ce.tipo_comprobante
                    FROM sunat_logs sl
                    LEFT JOIN comprobantes_electronicos ce ON sl.comprobante_id = ce.id
                    ORDER BY sl.creado_en DESC
                    LIMIT :limite";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Escribe un mensaje en el log del sistema.
     *
     * @param string $mensaje Mensaje a registrar
     */
    private function log(string $mensaje): void
    {
        $logPath = defined('LOGS_PATH') ? LOGS_PATH . '/comprobantes.log' : __DIR__ . '/../../storage/logs/comprobantes.log';
        $logDir  = dirname($logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $linea = '[' . date('Y-m-d H:i:s') . '] [ComprobanteModel] ' . $mensaje . PHP_EOL;
        file_put_contents($logPath, $linea, FILE_APPEND | LOCK_EX);
    }
}
