<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Modelo de Productos
 * 
 * Gestiona todas las operaciones de base de datos relacionadas con
 * productos, inventario y movimientos de stock del sistema de facturación.
 * 
 * @package App\Models
 */
class ProductModel
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
     * Obtiene todos los productos con filtros opcionales
     *
     * @param array $filters Filtros: categoria_id, proveedor_id, bajo_stock, estado, busqueda, limit, offset
     * @return array Lista de productos con sus relaciones
     */
    public function getAll(array $filters = []): array
    {
        $where  = ['p.activo = 1'];
        $params = [];

        // Filtro por categoría
        if (!empty($filters['categoria_id'])) {
            $where[]                   = 'p.categoria_id = :categoria_id';
            $params[':categoria_id']   = (int)$filters['categoria_id'];
        }

        // Filtro por proveedor
        if (!empty($filters['proveedor_id'])) {
            $where[]                   = 'p.proveedor_id = :proveedor_id';
            $params[':proveedor_id']   = (int)$filters['proveedor_id'];
        }

        // Filtro por stock bajo (stock actual <= stock mínimo)
        if (!empty($filters['bajo_stock'])) {
            $where[] = 'p.stock_actual <= p.stock_minimo';
        }

        // Filtro por estado (activo/inactivo)
        if (isset($filters['estado']) && $filters['estado'] !== '') {
            $where[]           = 'p.activo = :activo';
            $params[':activo'] = (int)$filters['estado'];
        }

        // Búsqueda por texto (nombre, código interno, código de barras)
        if (!empty($filters['busqueda'])) {
            $where[]             = '(p.nombre LIKE :busqueda OR p.codigo_interno LIKE :busqueda2 OR p.codigo_barras LIKE :busqueda3 OR p.descripcion LIKE :busqueda4)';
            $termino             = '%' . $filters['busqueda'] . '%';
            $params[':busqueda']  = $termino;
            $params[':busqueda2'] = $termino;
            $params[':busqueda3'] = $termino;
            $params[':busqueda4'] = $termino;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Contar total para paginación
        $countSql  = "SELECT COUNT(*) FROM productos p {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Ordenamiento
        $orderBy = 'p.nombre ASC';
        $validOrders = ['nombre', 'codigo_interno', 'stock_actual', 'precio_venta_pen', 'created_at'];
        if (!empty($filters['order']) && in_array($filters['order'], $validOrders)) {
            $dir     = (!empty($filters['dir']) && strtoupper($filters['dir']) === 'DESC') ? 'DESC' : 'ASC';
            $orderBy = "p.{$filters['order']} {$dir}";
        }

        // Paginación
        $limit  = (int)($filters['limit']  ?? 25);
        $offset = (int)($filters['offset'] ?? 0);

        $sql = "
            SELECT
                p.*,
                c.nombre      AS categoria_nombre,
                m.nombre      AS marca_nombre,
                u.nombre      AS unidad_nombre,
                u.abreviatura AS unidad_abreviatura,
                pv.razon_social AS proveedor_nombre,
                (p.stock_actual <= p.stock_minimo AND p.stock_minimo > 0) AS bajo_stock
            FROM productos p
            LEFT JOIN categorias    c  ON p.categoria_id  = c.id
            LEFT JOIN marcas        m  ON p.marca_id       = m.id
            LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
            LEFT JOIN proveedores   pv ON p.proveedor_principal_id = pv.id
            {$whereClause}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        // Bind de parámetros de filtros
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
     * Obtiene un producto por ID con todas sus relaciones
     *
     * @param int $id ID del producto
     * @return array|null Datos del producto o null si no existe
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT
                p.*,
                c.nombre       AS categoria_nombre,
                c.parent_id    AS categoria_parent_id,
                m.nombre       AS marca_nombre,
                u.nombre       AS unidad_nombre,
                u.abreviatura  AS unidad_abreviatura,
                pv.razon_social AS proveedor_nombre,
                pv.numero_doc  AS proveedor_ruc
            FROM productos p
            LEFT JOIN categorias    c  ON p.categoria_id  = c.id
            LEFT JOIN marcas        m  ON p.marca_id       = m.id
            LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
            LEFT JOIN proveedores   pv ON p.proveedor_principal_id = pv.id
            WHERE p.id = :id AND p.activo = 1
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Busca un producto por código interno o código de barras
     *
     * @param string $code Código a buscar
     * @return array|null Producto encontrado o null
     */
    public function findByCode(string $code): ?array
    {
        $sql = "
            SELECT
                p.*,
                c.nombre      AS categoria_nombre,
                u.nombre      AS unidad_nombre,
                u.abreviatura AS unidad_abreviatura
            FROM productos p
            LEFT JOIN categorias     c  ON p.categoria_id = c.id
            LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
            WHERE (p.codigo_interno = :code OR p.codigo_barras = :code2)
              AND p.activo = 1
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':code',  $code);
        $stmt->bindValue(':code2', $code);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Crea un nuevo producto con código interno autogenerado
     * Formato del código: CAT-YYYY-NNNN (ej: INF-2024-0001)
     *
     * @param array $data Datos del producto
     * @return int ID del producto creado
     * @throws RuntimeException Si falla la creación
     */
    public function create(array $data): int
    {
        // Generar código interno si no se proporcionó
        if (empty($data['codigo_interno'])) {
            $data['codigo_interno'] = $this->generateCodigoInterno((int)($data['categoria_id'] ?? 0));
        }

        $sql = "
            INSERT INTO productos (
                codigo_interno, codigo_barras, nombre, descripcion,
                categoria_id, marca_id, unidad_medida_id, proveedor_principal_id,
                precio_compra_pen, precio_compra_usd, precio_venta_pen, precio_venta_usd,
                precio_mayorista_pen,
                stock_actual, stock_minimo, stock_maximo,
                aplica_igv, tipo_afectacion_igv,
                imagen_path, activo, created_at, updated_at
            ) VALUES (
                :codigo_interno, :codigo_barras, :nombre, :descripcion,
                :categoria_id, :marca_id, :unidad_medida_id, :proveedor_principal_id,
                :precio_compra_pen, :precio_compra_usd, :precio_venta_pen, :precio_venta_usd,
                :precio_mayorista_pen,
                :stock_actual, :stock_minimo, :stock_maximo,
                :aplica_igv, :tipo_afectacion_igv,
                :imagen_path, 1, NOW(), NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':codigo_interno'          => $data['codigo_interno'],
            ':codigo_barras'           => $data['codigo_barras']       ?? null,
            ':nombre'                  => trim($data['nombre']),
            ':descripcion'             => $data['descripcion']         ?? null,
            ':categoria_id'            => $data['categoria_id']        ?? null,
            ':marca_id'                => $data['marca_id']            ?? null,
            ':unidad_medida_id'        => $data['unidad_medida_id']    ?? null,
            ':proveedor_principal_id'  => $data['proveedor_principal_id'] ?? null,
            ':precio_compra_pen'       => $data['precio_compra_pen']   ?? 0,
            ':precio_compra_usd'       => $data['precio_compra_usd']   ?? 0,
            ':precio_venta_pen'        => $data['precio_venta_pen']    ?? 0,
            ':precio_venta_usd'        => $data['precio_venta_usd']    ?? 0,
            ':precio_mayorista_pen'    => $data['precio_mayorista_pen'] ?? 0,
            ':stock_actual'            => $data['stock_actual']        ?? 0,
            ':stock_minimo'            => $data['stock_minimo']        ?? 0,
            ':stock_maximo'            => $data['stock_maximo']        ?? 0,
            ':aplica_igv'              => isset($data['aplica_igv']) ? (int)$data['aplica_igv'] : 1,
            ':tipo_afectacion_igv'     => $data['tipo_afectacion_igv'] ?? '10',
            ':imagen_path'             => $data['imagen_path']         ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Actualiza los datos de un producto
     *
     * @param int   $id   ID del producto
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó correctamente
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        // Mapear los campos del controlador a los nombres de la base de datos
        $fieldMap = [
            'codigo_barras'          => 'codigo_barras',
            'nombre'                 => 'nombre',
            'descripcion'            => 'descripcion',
            'categoria_id'           => 'categoria_id',
            'marca_id'               => 'marca_id',
            'unidad_medida_id'       => 'unidad_medida_id',
            'proveedor_principal_id' => 'proveedor_principal_id',
            'precio_compra_pen'      => 'precio_compra_pen',
            'precio_compra_usd'      => 'precio_compra_usd',
            'precio_venta_pen'       => 'precio_venta_pen',
            'precio_venta_usd'       => 'precio_venta_usd',
            'precio_mayorista_pen'   => 'precio_mayorista_pen',
            'stock_minimo'           => 'stock_minimo',
            'stock_maximo'           => 'stock_maximo',
            'aplica_igv'             => 'aplica_igv',
            'tipo_afectacion_igv'    => 'tipo_afectacion_igv',
            'imagen_path'            => 'imagen_path',
            'activo'                 => 'activo'
        ];

        foreach ($fieldMap as $dataKey => $dbColumn) {
            if (array_key_exists($dataKey, $data)) {
                $fields[]          = "{$dbColumn} = :{$dbColumn}";
                $params[":{$dbColumn}"] = $data[$dataKey];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';
        $sql      = 'UPDATE productos SET ' . implode(', ', $fields) . ' WHERE id = :id AND activo = 1';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina lógicamente un producto (soft delete)
     *
     * @param int $id ID del producto
     * @return bool True si se eliminó
     */
    public function delete(int $id): bool
    {
        $sql  = 'UPDATE productos SET activo = 0, updated_at = NOW() WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // =========================================================================
    // GESTIÓN DE STOCK
    // =========================================================================

    /**
     * Obtiene productos con stock bajo (stock_actual <= stock_minimo)
     *
     * @return array Lista de productos con stock bajo
     */
    public function getLowStock(): array
    {
        $sql = "
            SELECT
                p.*,
                c.nombre AS categoria_nombre,
                u.abreviatura AS unidad_abreviatura,
                pv.razon_social AS proveedor_nombre
            FROM productos p
            LEFT JOIN categorias     c  ON p.categoria_id = c.id
            LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
            LEFT JOIN proveedores    pv ON p.proveedor_principal_id = pv.id
            WHERE p.activo = 1
              AND p.activo = 1
              AND p.stock_minimo > 0
              AND p.stock_actual <= p.stock_minimo
            ORDER BY (p.stock_minimo - p.stock_actual) DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza el stock de un producto y registra el movimiento
     *
     * @param int    $productId ID del producto
     * @param float  $cantidad  Cantidad a mover (positiva = entrada, negativa = salida)
     * @param string $tipo      Tipo: 'entrada', 'salida', 'ajuste', 'devolucion'
     * @param string $refTipo   Tipo de referencia: 'compra', 'venta', 'ajuste_manual', etc.
     * @param int|null $refId   ID del documento de referencia
     * @param float  $costo     Costo unitario del movimiento
     * @param int    $userId    ID del usuario que realizó el movimiento
     * @param string $notas     Notas adicionales
     * @return bool True si se actualizó correctamente
     */
    public function updateStock(
        int $productId,
        float $cantidad,
        string $tipo,
        string $refTipo,
        ?int $refId,
        float $costo,
        int $userId,
        string $notas = ''
    ): bool {
        try {
            $this->db->beginTransaction();

            // Obtener stock actual para validar
            $sqlGet  = 'SELECT stock_actual, precio_compra_pen AS costo_promedio FROM productos WHERE id = :id AND activo = 1 FOR UPDATE';
            $stmtGet = $this->db->prepare($sqlGet);
            $stmtGet->bindValue(':id', $productId, PDO::PARAM_INT);
            $stmtGet->execute();
            $producto = $stmtGet->fetch(PDO::FETCH_ASSOC);

            if (!$producto) {
                $this->db->rollBack();
                return false;
            }

            $stockAnterior = (float)$producto['stock_actual'];
            $stockNuevo    = $stockAnterior + $cantidad;

            // Actualizar stock en tabla de productos
            $sqlUpdate = 'UPDATE productos SET stock_actual = :stock, updated_at = NOW() WHERE id = :id';
            $stmtUpd   = $this->db->prepare($sqlUpdate);
            $stmtUpd->bindValue(':stock', $stockNuevo);
            $stmtUpd->bindValue(':id',    $productId, PDO::PARAM_INT);
            $stmtUpd->execute();

            // Si es entrada, actualizar costo promedio ponderado
            if ($cantidad > 0 && $costo > 0) {
                $this->updateCostoPonderado($productId, $costo, $cantidad);
            }

            // Registrar movimiento de inventario
            $tipoMovimiento = match ($tipo) {
                'entrada'    => $refTipo === 'stock_inicial' ? 'inicial' : 'entrada_compra',
                'salida'     => 'salida_venta',
                'ajuste'     => $cantidad >= 0 ? 'ajuste_positivo' : 'ajuste_negativo',
                'devolucion' => $cantidad >= 0 ? 'devolucion_cliente' : 'devolucion_proveedor',
                default       => $tipo,
            };

            $sqlMov = "
                INSERT INTO movimientos_stock (
                    producto_id, tipo_movimiento, cantidad, stock_antes, stock_despues,
                    costo_unitario, referencia_tipo, referencia_id, notas, usuario_id, created_at
                ) VALUES (
                    :producto_id, :tipo_movimiento, :cantidad, :stock_antes, :stock_despues,
                    :costo_unitario, :referencia_tipo, :referencia_id, :notas, :usuario_id, NOW()
                )
            ";

            $stmtMov = $this->db->prepare($sqlMov);
            $stmtMov->execute([
                ':producto_id'    => $productId,
                ':tipo_movimiento'=> $tipoMovimiento,
                ':cantidad'       => $cantidad,
                ':stock_antes'    => $stockAnterior,
                ':stock_despues'  => $stockNuevo,
                ':costo_unitario' => $costo,
                ':referencia_tipo'=> $refTipo,
                ':referencia_id'  => $refId,
                ':notas'          => $notas,
                ':usuario_id'     => $userId,
            ]);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw new RuntimeException("Error al actualizar stock: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obtiene el historial de movimientos de stock de un producto
     *
     * @param int $productId ID del producto
     * @param int $limit     Cantidad máxima de registros
     * @return array Lista de movimientos
     */
    public function getMovimientos(int $productId, int $limit = 50): array
    {
        $sql = "
            SELECT
                mi.*,
                u.nombre    AS usuario_nombre,
                u.apellidos AS usuario_apellidos
            FROM movimientos_stock mi
            LEFT JOIN usuarios u ON mi.usuario_id = u.id
            WHERE mi.producto_id = :producto_id
            ORDER BY mi.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':producto_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',       $limit,      PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // CÓDIGOS DE BARRAS
    // =========================================================================

    /**
     * Genera los datos necesarios para el código de barras de un producto
     *
     * @param int    $productId ID del producto
     * @param string $tipo      Tipo de código: 'CODE128', 'EAN13', 'CODE39'
     * @return array|null Datos del producto para generar el código
     */
    public function generateBarcode(int $productId, string $tipo = 'CODE128'): ?array
    {
        $producto = $this->getById($productId);
        if (!$producto) {
            return null;
        }

        // Determinar el código a usar según el tipo
        $codigo = match ($tipo) {
            'EAN13'  => $producto['codigo_barras'] ?? $producto['codigo_interno'],
            default  => $producto['codigo_interno'],
        };

        return [
            'producto_id'    => $productId,
            'nombre'         => $producto['nombre'],
            'codigo'         => $codigo,
            'tipo'           => $tipo,
            'precio_venta'   => $producto['precio_venta'],
            'unidad'         => $producto['unidad_abreviatura'] ?? 'UND',
        ];
    }

    // =========================================================================
    // PUNTO DE VENTA (POS)
    // =========================================================================

    /**
     * Búsqueda rápida de productos para el punto de venta
     * Busca por nombre, código interno o código de barras
     *
     * @param string $search   Término de búsqueda
     * @param int    $limit    Máximo de resultados
     * @return array Lista simplificada de productos para POS
     */
    public function getForPOS(string $search, int $limit = 10): array
    {
        $sql = "
            SELECT
                p.id,
                p.codigo_interno,
                p.codigo_barras,
                p.nombre,
                p.precio_venta_pen AS precio_venta,
                p.precio_mayorista_pen AS precio_mayorista,
                p.stock_actual,
                p.aplica_igv,
                p.tipo_afectacion_igv,
                p.categoria_id,
                u.abreviatura AS unidad,
                p.imagen_path AS imagen
            FROM productos p
            LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
            WHERE p.activo = 1
              AND p.activo = 1
              AND (
                  p.nombre         LIKE :busqueda
                  OR p.codigo_interno LIKE :busqueda2
                  OR p.codigo_barras  LIKE :busqueda3
              )
            ORDER BY
                CASE
                    WHEN p.codigo_interno = :exact1 OR p.codigo_barras = :exact2 THEN 0
                    ELSE 1
                END,
                p.nombre ASC
            LIMIT :limit
        ";

        $termino = '%' . $search . '%';
        $stmt    = $this->db->prepare($sql);
        $stmt->bindValue(':busqueda',  $termino);
        $stmt->bindValue(':busqueda2', $termino);
        $stmt->bindValue(':busqueda3', $termino);
        $stmt->bindValue(':exact1',    $search);
        $stmt->bindValue(':exact2',    $search);
        $stmt->bindValue(':limit',     $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // COSTOS
    // =========================================================================

    /**
     * Actualiza el costo promedio ponderado del producto
     * Fórmula: CPP = (Stock actual * Costo actual + Cantidad nueva * Costo nuevo) / (Stock actual + Cantidad nueva)
     *
     * @param int   $productId    ID del producto
     * @param float $nuevoCosto   Costo unitario de la nueva entrada
     * @param float $nuevaCantidad Cantidad de la nueva entrada
     * @return bool True si se actualizó
     */
    public function updateCostoPonderado(int $productId, float $nuevoCosto, float $nuevaCantidad): bool
    {
        $sql = "
            UPDATE productos
            SET precio_compra_pen = (
                (GREATEST(stock_actual, 0) * COALESCE(precio_compra_pen, 0) + :nueva_cantidad * :nuevo_costo)
                / GREATEST(GREATEST(stock_actual, 0) + :nueva_cantidad2, 1)
            ),
            updated_at = NOW()
            WHERE id = :id AND activo = 1
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nueva_cantidad'  => $nuevaCantidad,
            ':nuevo_costo'     => $nuevoCosto,
            ':nueva_cantidad2' => $nuevaCantidad,
            ':id'              => $productId,
        ]);
    }

    // =========================================================================
    // CATEGORÍAS Y FILTROS
    // =========================================================================

    /**
     * Obtiene los productos de una categoría específica
     *
     * @param int $categoryId ID de la categoría
     * @return array Lista de productos de la categoría
     */
    public function getByCategory(int $categoryId): array
    {
        $sql = "
            SELECT p.*, u.abreviatura AS unidad_abreviatura
            FROM productos p
            LEFT JOIN unidades_medida u ON p.unidad_medida_id = u.id
            WHERE p.categoria_id = :categoria_id
              AND p.activo = 1
              AND p.activo = 1
            ORDER BY p.nombre ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':categoria_id', $categoryId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // ESTADÍSTICAS
    // =========================================================================

    /**
     * Obtiene estadísticas generales del inventario
     *
     * @return array Estadísticas del inventario
     */
    public function getStats(): array
    {
        $sql = "
            SELECT
                COUNT(*)                                              AS total_productos,
                COUNT(CASE WHEN activo = 1 THEN 1 END)               AS productos_activos,
                COUNT(CASE WHEN activo = 0 THEN 1 END)               AS productos_inactivos,
                COUNT(CASE WHEN stock_actual <= stock_minimo
                           AND stock_minimo > 0 THEN 1 END)          AS productos_bajo_stock,
                COUNT(CASE WHEN stock_actual = 0 THEN 1 END)         AS productos_sin_stock,
                SUM(stock_actual * precio_compra_pen)                AS valor_inventario,
                SUM(stock_actual * precio_venta_pen)                 AS valor_venta_inventario,
                AVG(precio_venta_pen)                                AS precio_promedio
            FROM productos
            WHERE activo = 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // =========================================================================
    // MÉTODOS AUXILIARES PRIVADOS
    // =========================================================================

    /**
     * Genera un código interno único para el producto
     * Formato: CAT-YYYY-NNNN (ej: INF-2024-0001)
     *
     * @param int $categoriaId ID de la categoría para obtener el prefijo
     * @return string Código interno único
     */
    private function generateCodigoInterno(int $categoriaId): string
    {
        // Obtener prefijo de la categoría
        $prefijo = 'PRD';
        if ($categoriaId > 0) {
            $sqlCat  = 'SELECT nombre FROM categorias WHERE id = :id LIMIT 1';
            $stmtCat = $this->db->prepare($sqlCat);
            $stmtCat->bindValue(':id', $categoriaId, PDO::PARAM_INT);
            $stmtCat->execute();
            $cat = $stmtCat->fetch(PDO::FETCH_ASSOC);
            if ($cat && !empty($cat['nombre'])) {
                // Get alphanumeric characters only, take first 3, make uppercase
                $prefijo = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $cat['nombre']), 0, 3));
            }
        }

        $anio = date('Y');

        // Obtener el siguiente número secuencial para este prefijo y año
        $sqlSeq  = "
            SELECT COUNT(*) + 1 AS siguiente
            FROM productos
            WHERE codigo_interno LIKE :patron
        ";
        $patron  = "{$prefijo}-{$anio}-%";
        $stmtSeq = $this->db->prepare($sqlSeq);
        $stmtSeq->bindValue(':patron', $patron);
        $stmtSeq->execute();
        $row      = $stmtSeq->fetch(PDO::FETCH_ASSOC);
        $numero   = (int)($row['siguiente'] ?? 1);

        // Generar código y verificar unicidad
        $codigo   = sprintf('%s-%s-%04d', $prefijo, $anio, $numero);

        // Verificar que el código no exista ya (en caso de borrados)
        $sqlCheck  = 'SELECT COUNT(*) FROM productos WHERE codigo_interno = :codigo';
        $stmtCheck = $this->db->prepare($sqlCheck);

        while (true) {
            $stmtCheck->bindValue(':codigo', $codigo);
            $stmtCheck->execute();
            if ((int)$stmtCheck->fetchColumn() === 0) {
                break;
            }
            $numero++;
            $codigo = sprintf('%s-%s-%04d', $prefijo, $anio, $numero);
        }

        return $codigo;
    }
}
