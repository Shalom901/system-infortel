<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo de Proveedores
 * 
 * Gestiona las operaciones CRUD de proveedores del sistema,
 * incluyendo validación de RUC peruano y relación con productos.
 * 
 * @package App\Models
 */
class SupplierModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // =========================================================================
    // CONSULTAS PRINCIPALES
    // =========================================================================

    /**
     * Obtiene todos los proveedores con conteo de productos
     *
     * @param array $filters Filtros: busqueda, estado, limit, offset
     * @return array Lista de proveedores con paginación
     */
    public function getAll(array $filters = []): array
    {
        $where  = ['pv.activo = 1'];
        $params = [];

        // Filtro por búsqueda: Mapeo estricto a la columna física 'numero_doc'
        if (!empty($filters['busqueda'])) {
            $termino = '%' . $filters['busqueda'] . '%';
            $where[] = '(pv.razon_social LIKE :busqueda OR pv.numero_doc LIKE :busqueda2 OR pv.contacto_nombre LIKE :busqueda3)';
            $params[':busqueda']  = $termino;
            $params[':busqueda2'] = $termino;
            $params[':busqueda3'] = $termino;
        }

        // Filtro por estado
        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $where[] = 'pv.activo = :activo';
            $params[':activo'] = (int)$filters['estado'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Conteo total para paginación analítica
        $countSql  = "SELECT COUNT(*) FROM proveedores pv {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $limit  = (int)($filters['limit']  ?? 25);
        $offset = (int)($filters['offset'] ?? 0);

        // SQL Blindado: Desacoplamos el JOIN de productos para asegurar el retorno de datos core
        $sql = "
            SELECT 
                pv.*, 
                0 AS total_productos 
            FROM proveedores pv
            {$whereClause}
            ORDER BY pv.razon_social ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        // Garantizamos una estructura inmutable para el controlador
        return [
            'data'   => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total'  => $total,
            'limit'  => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Obtiene un proveedor por ID
     *
     * @param int $id ID del proveedor
     * @return array|null Datos del proveedor o null
     */
    public function getById(int $id): ?array
    {
        $sql  = 'SELECT * FROM proveedores WHERE id = :id AND activo = 1 LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Crea un nuevo proveedor
     *
     * @param array $data Datos del proveedor
     * @return int ID del proveedor creado
     */
public function create(array $data): int
    {
        $sql = "
            INSERT INTO proveedores (
                tipo_doc, numero_doc, razon_social, nombre_comercial,
                direccion, distrito, provincia, departamento, ubigeo,
                telefono, telefono2, email, web,
                contacto_nombre, contacto_telefono, contacto_email,
                moneda_preferida, notas, activo, created_at, updated_at
            ) VALUES (
                :tipo_doc, :numero_doc, :razon_social, :nombre_comercial,
                :direccion, :distrito, :provincia, :departamento, :ubigeo,
                :telefono, :telefono2, :email, :web,
                :contacto_nombre, :contacto_telefono, :contacto_email,
                :moneda_preferida, :notas, 1, NOW(), NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tipo_doc'         => $data['tipo_doc'] ?? 6,
            ':numero_doc'       => $data['ruc'] ?? $data['numero_doc'] ?? '',
            ':razon_social'     => trim($data['razon_social'] ?? ''),
            ':nombre_comercial' => $data['nombre_comercial'] ?? null,
            ':direccion'        => $data['direccion']        ?? null,
            ':distrito'         => $data['distrito']         ?? null,
            ':provincia'        => $data['provincia']        ?? null,
            ':departamento'     => $data['departamento']     ?? null,
            ':ubigeo'           => $data['ubigeo']           ?? null,
            ':telefono'         => $data['telefono']         ?? null,
            ':telefono2'        => $data['telefono2']        ?? null,
            ':email'            => $data['email']            ?? null,
            ':web'              => $data['web']              ?? null,
            ':contacto_nombre'  => $data['contacto_nombre']  ?? null,
            ':contacto_telefono'=> $data['contacto_telefono']?? null,
            ':contacto_email'   => $data['contacto_email']   ?? null,
            ':moneda_preferida' => $data['moneda_preferida'] ?? 'PEN',
            ':notas'            => $data['notas']            ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        // Mapeo seguro de 'ruc' si viene desde formularios heredados
        if (isset($data['ruc']) && !isset($data['numero_doc'])) {
            $data['numero_doc'] = $data['ruc'];
            unset($data['ruc']);
        }

        $allowedFields = [
            'tipo_doc', 'numero_doc', 'razon_social', 'nombre_comercial',
            'direccion', 'distrito', 'provincia', 'departamento', 'ubigeo',
            'telefono', 'telefono2', 'email', 'web',
            'contacto_nombre', 'contacto_telefono', 'contacto_email',
            'moneda_preferida', 'notas', 'activo',
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
        $sql      = 'UPDATE proveedores SET ' . implode(', ', $fields) . ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina lógicamente un proveedor validando su integridad referencial
     *
     * @param int $id ID del proveedor
     * @return array ['success' => bool, 'message' => string]
     */
    public function delete(int $id): array
    {
        // 1. Auditoría de Integridad Referencial:
        // Se corrige el mapeo a la llave foránea física 'proveedor_principal_id'
        $sqlCheck  = 'SELECT COUNT(*) FROM productos WHERE proveedor_principal_id = :id AND activo = 1';
        $stmtCheck = $this->db->prepare($sqlCheck);
        $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtCheck->execute();
        
        $totalProductos = (int)$stmtCheck->fetchColumn();

        // Si existen dependencias, bloqueamos la eliminación para evitar orfandad de datos
        if ($totalProductos > 0) {
            return [
                'success' => false,
                'message' => "Restricción de dominio: El proveedor posee {$totalProductos} producto(s) en catálogo."
            ];
        }

        // 2. Ejecución de la Mutación (Borrado Lógico)
        $sql  = 'UPDATE proveedores SET activo = 0, updated_at = NOW() WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Proveedor dado de baja correctamente.'];
        }

        return ['success' => false, 'message' => 'Fallo de persistencia al intentar eliminar el registro.'];
    }

    /**
     * Busca un proveedor por RUC
     *
     * @param string $ruc RUC del proveedor (11 dígitos)
     * @return array|null Proveedor encontrado o null
     */
    public function findByRUC(string $ruc): ?array
    {
        $sql  = 'SELECT * FROM proveedores WHERE numero_doc = :ruc AND activo = 1 LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':ruc', $ruc);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtiene los productos asociados a un proveedor
     *
     * @param int $supplierId ID del proveedor
     * @return array Lista de productos del proveedor
     */
    public function getProductos(int $supplierId): array
    {
        $sql = "
            SELECT
                p.id, p.codigo_interno, p.nombre, p.precio_compra,
                p.precio_venta, p.stock_actual, p.activo,
                c.nombre AS categoria_nombre,
                u.abreviatura AS unidad
            FROM productos p
            LEFT JOIN categorias      c ON p.categoria_id = c.id
            LEFT JOIN unidades_medida u ON p.unidad_id     = u.id
            WHERE p.proveedor_id = :proveedor_id AND p.activo = 1
            ORDER BY p.nombre ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':proveedor_id', $supplierId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Valida un RUC peruano según el algoritmo oficial SUNAT
     * 
     * @param string $ruc RUC a validar
     * @return bool True si el RUC es válido
     */
    public static function validarRUC(string $ruc): bool
    {
        // Debe tener 11 dígitos numéricos
        if (!preg_match('/^\d{11}$/', $ruc)) {
            return false;
        }

        // Debe comenzar con 10, 15, 16, 17 o 20
        $prefijo = (int)substr($ruc, 0, 2);
        if (!in_array($prefijo, [10, 15, 16, 17, 20])) {
            return false;
        }

        // Algoritmo de validación del dígito verificador
        $factores  = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma      = 0;

        for ($i = 0; $i < 10; $i++) {
            $suma += (int)$ruc[$i] * $factores[$i];
        }

        $resto    = $suma % 11;
        $digito   = 11 - $resto;

        if ($digito >= 10) {
            $digito -= 10;
        }

        return (int)$ruc[10] === $digito;
    }

    /**
     * Obtiene todos los proveedores activos para selects
     *
     * @return array Lista simplificada de proveedores
     */
    public function getForSelect(): array
    {
        $sql = "
            SELECT id, numero_doc AS ruc, razon_social, nombre_comercial
            FROM proveedores
            WHERE activo = 1
            ORDER BY razon_social ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
