<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

/**
 * Modelo de Categorías de Productos
 * 
 * Gestiona la estructura jerárquica de categorías de productos
 * con soporte para múltiples niveles (parent_id).
 * 
 * @package App\Models
 */
class CategoryModel
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
     * Obtiene todas las categorías con jerarquía y conteo de productos
     *
     * @return array Lista plana de categorías con información de padre y conteo
     */
    public function getAll(): array
    {
        $sql = "
            SELECT
                c.*,
                p.nombre  AS padre_nombre,
                COUNT(pr.id) AS total_productos
            FROM categorias c
            LEFT JOIN categorias p  ON c.parent_id  = p.id
            LEFT JOIN productos  pr ON pr.categoria_id = c.id AND pr.activo = 1
            WHERE c.activo = 1
            GROUP BY c.id
            ORDER BY c.parent_id IS NOT NULL, p.nombre, c.nombre
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Construye una estructura de árbol recursiva de categorías
     *
     * @param int|null $parentId ID del padre (null para raíces)
     * @return array Árbol de categorías con hijos anidados
     */
    public function getTree(?int $parentId = null): array
    {
        $sql = "
            SELECT
                c.*,
                COUNT(pr.id) AS total_productos
            FROM categorias c
            LEFT JOIN productos pr ON pr.categoria_id = c.id AND pr.activo = 1
            WHERE c.activo = 1
        ";

        $params = [];
        if ($parentId === null) {
            $sql .= ' AND c.parent_id IS NULL';
        } else {
            $sql           .= ' AND c.parent_id = :parent_id';
            $params[':parent_id'] = $parentId;
        }

        $sql .= ' GROUP BY c.id ORDER BY c.orden ASC, c.nombre ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agregar hijos recursivamente
        foreach ($categorias as &$categoria) {
            $categoria['hijos'] = $this->getTree((int)$categoria['id']);
        }

        return $categorias;
    }

    /**
     * Obtiene una categoría por ID
     *
     * @param int $id ID de la categoría
     * @return array|null Datos de la categoría o null
     */
    public function getById(int $id): ?array
    {
        $sql = "
            SELECT
                c.*,
                p.nombre AS padre_nombre
            FROM categorias c
            LEFT JOIN categorias p ON c.parent_id = p.id
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
     * Crea una nueva categoría
     *
     * @param array $data Datos de la categoría
     * @return int ID de la categoría creada
     */
    public function create(array $data): int
    {
        // Generar código automático si no se proporcionó
        if (empty($data['codigo'])) {
            $data['codigo'] = $this->generateCodigo($data['nombre']);
        }

        $sql = "
            INSERT INTO categorias (
                codigo, nombre, descripcion, parent_id, imagen, orden, activo, activo, created_at, updated_at
            ) VALUES (
                :codigo, :nombre, :descripcion, :parent_id, :imagen, :orden, 1, 0, NOW(), NOW()
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':codigo'      => strtoupper($data['codigo']),
            ':nombre'      => trim($data['nombre']),
            ':descripcion' => $data['descripcion'] ?? null,
            ':parent_id'   => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            ':imagen'      => $data['imagen']      ?? null,
            ':orden'       => $data['orden']       ?? 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Actualiza una categoría existente
     *
     * @param int   $id   ID de la categoría
     * @param array $data Datos a actualizar
     * @return bool True si se actualizó
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = ['codigo', 'nombre', 'descripcion', 'parent_id', 'imagen', 'orden', 'activo'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[]            = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = NOW()';
        $sql      = 'UPDATE categorias SET ' . implode(', ', $fields) . ' WHERE id = :id AND activo = 1';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina una categoría verificando que no tenga productos activos ni subcategorías
     *
     * @param int $id ID de la categoría
     * @return array ['success' => bool, 'message' => string]
     */
    public function delete(int $id): array
    {
        // Verificar que no tenga productos activos
        $sqlProd  = 'SELECT COUNT(*) FROM productos WHERE categoria_id = :id AND activo = 1';
        $stmtProd = $this->db->prepare($sqlProd);
        $stmtProd->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtProd->execute();
        $totalProductos = (int)$stmtProd->fetchColumn();

        if ($totalProductos > 0) {
            return [
                'success' => false,
                'message' => "No se puede eliminar la categoría porque tiene {$totalProductos} producto(s) activo(s).",
            ];
        }

        // Verificar que no tenga subcategorías activas
        $sqlSub = "SELECT COUNT(*) FROM categorias WHERE parent_id = :id AND activo = 1";
        $stmtSub = $this->db->prepare($sqlSub);
        $stmtSub->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtSub->execute();
        $totalSubcats = (int)$stmtSub->fetchColumn();

        if ($totalSubcats > 0) {
            return [
                'success' => false,
                'message' => "No se puede eliminar la categoría porque tiene {$totalSubcats} subcategoría(s).",
            ];
        }

        // Soft delete
        $sql = "UPDATE categorias SET activo = 0, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return ['success' => true, 'message' => 'Categoría eliminada correctamente.'];
    }

    /**
     * Obtiene solo las categorías raíz (sin padre)
     *
     * @return array Categorías de nivel principal
     */
    public function getParents(): array
    {
        $sql = "
            SELECT * FROM categorias
            WHERE parent_id IS NULL AND activo = 1 AND activo = 1
            ORDER BY orden ASC, nombre ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las subcategorías de una categoría padre
     *
     * @param int $parentId ID de la categoría padre
     * @return array Lista de subcategorías
     */
    public function getChildren(int $parentId): array
    {
        $sql = "
            SELECT c.*, COUNT(pr.id) AS total_productos
            FROM categorias c
            LEFT JOIN productos pr ON pr.categoria_id = c.id AND pr.activo = 1
            WHERE c.parent_id = :parent_id AND c.activo = 1
            GROUP BY c.id
            ORDER BY c.orden ASC, c.nombre ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Genera la ruta completa (breadcrumb) de una categoría
     * Ej: Electrónica > Computadoras > Laptops
     *
     * @param int $categoryId ID de la categoría
     * @return array Lista de categorías desde la raíz hasta la actual
     */
    public function getBreadcrumb(int $categoryId): array
    {
        $breadcrumb = [];
        $currentId  = $categoryId;
        $maxLevels  = 5; // Máximo de niveles para evitar loops infinitos

        for ($i = 0; $i < $maxLevels; $i++) {
            $categoria = $this->getById($currentId);

            if (!$categoria) {
                break;
            }

            // Agregar al inicio del array para obtener ruta de raíz a hijo
            array_unshift($breadcrumb, $categoria);

            if (empty($categoria['parent_id'])) {
                break;
            }

            $currentId = (int)$categoria['parent_id'];
        }

        return $breadcrumb;
    }

    /**
     * Obtiene las categorías en formato de select anidado con indentación
     *
     * @return array Categorías con nivel de profundidad para mostrar en select
     */
    public function getForSelect(): array
    {
        $tree   = $this->getTree();
        $result = [];
        $this->flattenTree($tree, $result, 0);
        return $result;
    }

    // =========================================================================
    // MÉTODOS PRIVADOS AUXILIARES
    // =========================================================================

    /**
     * Aplana el árbol de categorías en una lista ordenada con nivel de profundidad
     */
    private function flattenTree(array $tree, array &$result, int $level): void
    {
        foreach ($tree as $categoria) {
            $categoria['nivel']  = $level;
            $categoria['indent'] = str_repeat('— ', $level);
            $hijos               = $categoria['hijos'] ?? [];
            unset($categoria['hijos']);
            $result[] = $categoria;

            if (!empty($hijos)) {
                $this->flattenTree($hijos, $result, $level + 1);
            }
        }
    }

    /**
     * Genera un código único para la categoría basado en su nombre
     *
     * @param string $nombre Nombre de la categoría
     * @return string Código único (máximo 10 caracteres)
     */
    private function generateCodigo(string $nombre): string
    {
        // Tomar las primeras 3 letras, remover acentos
        $clean  = iconv('UTF-8', 'ASCII//TRANSLIT', $nombre) ?: $nombre;
        $base   = strtoupper(preg_replace('/[^A-Z0-9]/', '', $clean));
        $codigo = substr($base, 0, 3);

        if (strlen($codigo) < 3) {
            $codigo = str_pad($codigo, 3, 'X');
        }

        // Verificar unicidad
        $num     = 1;
        $original = $codigo;
        $sqlCheck = 'SELECT COUNT(*) FROM categorias WHERE codigo = :codigo';
        $stmt     = $this->db->prepare($sqlCheck);

        while (true) {
            $stmt->bindValue(':codigo', $codigo);
            $stmt->execute();
            if ((int)$stmt->fetchColumn() === 0) {
                break;
            }
            $codigo = $original . $num;
            $num++;
        }

        return $codigo;
    }
}
