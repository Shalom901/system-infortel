<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Modelo de Clientes
 *
 * Gestiona todas las operaciones de base de datos relacionadas con clientes,
 * incluyendo búsqueda por documento de identidad, creación y actualización.
 *
 * @package App\Models
 */
class ClientModel
{
    private PDO $db;

    /**
     * ID del cliente genérico (para boletas sin documento)
     * Debe existir en la tabla clientes como cliente por defecto
     */
    private const CLIENTE_GENERICO_DOC = '00000000';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // CONSULTAS PRINCIPALES
    // =========================================================================

    /**
     * Obtiene todos los clientes con filtros y paginación
     *
     * @param array $filters Filtros:
     *   - busqueda: búsqueda por nombre, RUC o DNI
     *   - tipo_doc: '1' DNI, '6' RUC, etc.
     *   - activo: 1 o 0
     *   - limit, offset: paginación
     * @return array ['data' => [], 'total' => int]
     */
    public function getAll(array $filters = []): array
    {
        $where  = ['c.activo = 1'];
        $params = [];

        // Búsqueda general por nombre o documento
        if (!empty($filters['busqueda'])) {
            $termino              = '%' . $filters['busqueda'] . '%';
            $where[]              = '(c.razon_social LIKE :busqueda OR c.numero_doc LIKE :busqueda2 OR c.email LIKE :busqueda3)';
            $params[':busqueda']  = $termino;
            $params[':busqueda2'] = $termino;
            $params[':busqueda3'] = $termino;
        }

        // Filtro por tipo de documento
        if (!empty($filters['tipo_doc'])) {
            $where[]                    = 'c.tipo_doc = :tipo_doc';
            $params[':tipo_doc']  = $filters['tipo_doc'];
        }

        // Filtro por estado activo/inactivo
        if (isset($filters['activo']) && $filters['activo'] !== '') {
            $where[]           = 'c.activo = :activo';
            $params[':activo'] = (int)$filters['activo'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Contar total para paginación
        $countSql  = "SELECT COUNT(*) FROM clientes c {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Paginación
        $limit  = (int)($filters['limit']  ?? 25);
        $offset = (int)($filters['offset'] ?? 0);

        $sql = "
            SELECT
                c.*,
                COUNT(v.id)   AS total_ventas,
                SUM(v.total)  AS monto_total_compras
            FROM clientes c
            LEFT JOIN ventas v ON c.id = v.cliente_id AND v.estado != 'anulada'
            {$whereClause}
            GROUP BY c.id
            ORDER BY c.razon_social ASC
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
     * Obtiene un cliente por su ID
     *
     * @param int $id ID del cliente
     * @return array|null Datos del cliente o null si no existe
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT c.*
            FROM clientes c
            WHERE c.id = :id AND c.activo = 1
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Busca un cliente por tipo y número de documento
     *
     * @param string $tipo   Tipo de documento: '1'=DNI, '6'=RUC
     * @param string $numero Número de documento
     * @return array|null Cliente encontrado o null
     */
    public function findByDoc(string $tipo, string $numero): ?array
    {
        $sql = "
            SELECT c.*
            FROM clientes c
            WHERE c.tipo_doc   = :tipo
              AND c.numero_doc = :numero
              AND c.activo = 1
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tipo'   => $tipo,
            ':numero' => $numero,
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Busca clientes por término de búsqueda (para autocomplete AJAX)
     * Busca en razón social, número de documento y email
     *
     * @param string $query Término a buscar
     * @param int    $limit Máximo de resultados (por defecto 10)
     * @return array Lista de clientes que coinciden
     */
    public function search(string $query, int $limit = 10): array
    {
        $termino = '%' . $query . '%';

        $sql = "
            SELECT
                c.id,
                c.tipo_doc,
                c.numero_doc,
                c.razon_social,
                c.email,
                c.telefono,
                c.direccion
            FROM clientes c
            WHERE c.activo = 1
              AND c.activo = 1
              AND (
                  c.razon_social     LIKE :busqueda
                  OR c.numero_doc LIKE :busqueda2
                  OR c.email           LIKE :busqueda3
              )
            ORDER BY
                CASE
                    WHEN c.numero_doc = :exact THEN 0
                    WHEN c.razon_social LIKE :empieza THEN 1
                    ELSE 2
                END,
                c.razon_social ASC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':busqueda',  $termino);
        $stmt->bindValue(':busqueda2', $termino);
        $stmt->bindValue(':busqueda3', $termino);
        $stmt->bindValue(':exact',     $query);
        $stmt->bindValue(':empieza',   $query . '%');
        $stmt->bindValue(':limit',     $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crea un nuevo cliente
     *
     * @param array $data Datos del cliente:
     *   - tipo_doc: '1'=DNI, '6'=RUC
     *   - numero_doc: número del documento
     *   - razon_social: nombre o razón social
     *   - email, telefono, direccion (opcionales)
     * @return int ID del cliente creado
     * @throws RuntimeException Si el documento ya existe o falla la creación
     */
    public function create(array $data): int
    {
        // Verificar si el documento ya existe
        if (!empty($data['numero_doc']) && $data['numero_doc'] !== self::CLIENTE_GENERICO_DOC) {
            $existente = $this->findByDoc($data['tipo_doc'] ?? '1', $data['numero_doc']);
            if ($existente) {
                throw new RuntimeException("Ya existe un cliente con el documento {$data['numero_doc']}.");
            }
        }

        $sql = "
            INSERT INTO clientes (
                tipo_doc, numero_doc, razon_social,
                email, telefono, telefono2,
                direccion, ubigeo, departamento, provincia, distrito,
                activo, es_empresa, created_at, updated_at
            ) VALUES (
                :tipo_doc, :numero_doc, :razon_social,
                :email, :telefono, :telefono2,
                :direccion, :ubigeo, :departamento, :provincia, :distrito,
                1, 0, NOW(), NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tipo_doc'  => $data['tipo_doc']  ?? '1',
            ':numero_doc'=> $data['numero_doc']?? '',
            ':razon_social'    => trim($data['razon_social']),
            ':email'           => $data['email']            ?? null,
            ':telefono'        => $data['telefono']         ?? null,
            ':telefono2'       => $data['celular']          ?? null,
            ':direccion'       => $data['direccion']        ?? null,
            ':ubigeo'          => $data['ubigeo']           ?? null,
            ':departamento'    => $data['departamento']     ?? null,
            ':provincia'       => $data['provincia']        ?? null,
            ':distrito'        => $data['distrito']         ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Actualiza los datos de un cliente
     *
     * @param int   $id   ID del cliente
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        // Campos permitidos para actualización
        $allowedFields = [
            'tipo_doc', 'numero_doc', 'razon_social',
            'email', 'telefono', 'telefono2',
            'direccion', 'ubigeo', 'departamento', 'provincia', 'distrito',
            'activo', 'es_empresa', 'nombres', 'apellidos', 'notas'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[]          = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';
        $sql      = 'UPDATE clientes SET ' . implode(', ', $fields) . ' WHERE id = :id AND activo = 1';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina lógicamente un cliente (soft delete)
     *
     * @param int $id ID del cliente
     * @return bool True si se eliminó
     * @throws RuntimeException Si el cliente tiene ventas asociadas
     */
    public function delete(int $id): bool
    {
        // Verificar si el cliente tiene ventas activas
        $sqlVentas = "SELECT COUNT(*) FROM ventas WHERE cliente_id = :id AND estado = 'activo'";
        $stmtVentas = $this->db->prepare($sqlVentas);
        $stmtVentas->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtVentas->execute();
        $countVentas = (int)$stmtVentas->fetchColumn();

        if ($countVentas > 0) {
            throw new RuntimeException("No se puede eliminar el cliente porque tiene {$countVentas} venta(s) asociada(s).");
        }

        $sql  = 'UPDATE clientes SET activo = 0, updated_at = NOW() WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Obtiene o crea el cliente genérico para boletas sin documento
     *
     * El cliente genérico es usado en el POS cuando no se ingresa
     * datos del cliente (ventas en efectivo sin comprobante de identidad).
     *
     * @return array Datos del cliente genérico
     */
    public function getClienteGenerico(): array
    {
        // Buscar cliente genérico existente
        $sql = "
            SELECT * FROM clientes
            WHERE numero_doc = :doc
              AND activo = 1
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':doc' => self::CLIENTE_GENERICO_DOC]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si no existe, crearlo
        if (!$cliente) {
            $id = $this->create([
                'tipo_doc'  => '-',
                'numero_doc'=> self::CLIENTE_GENERICO_DOC,
                'razon_social'    => 'CLIENTE GENÉRICO',
                'email'           => null,
                'telefono'        => null,
                'direccion'       => 'PUCALLPA - PERU',
            ]);
            $cliente = $this->getById($id);
        }

        return $cliente ?? [];
    }

    // =========================================================================
    // ESTADÍSTICAS DE CLIENTE
    // =========================================================================

    /**
     * Obtiene el historial de compras de un cliente
     *
     * @param int $clienteId ID del cliente
     * @param int $limit     Máximo de registros
     * @return array Historial de ventas del cliente
     */
    public function getHistorialCompras(int $clienteId, int $limit = 20): array
    {
        $sql = "
            SELECT
                v.id,
                v.numero_comprobante,
                v.fecha_emision,
                v.tipo_comprobante,
                v.total,
                v.moneda,
                v.estado,
                GROUP_CONCAT(pv.metodo_pago ORDER BY pv.id SEPARATOR ', ') AS metodos_pago
            FROM ventas v
            LEFT JOIN pagos_venta pv ON v.id = pv.venta_id
            WHERE v.cliente_id = :cliente_id
            GROUP BY v.id
            ORDER BY v.fecha_emision DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':cliente_id', $clienteId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',      $limit,     PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
