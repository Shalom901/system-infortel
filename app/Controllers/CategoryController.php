<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\CategoryModel;

class CategoryController
{
    private PDO $db;
    private CategoryModel $model;

    public function __construct(PDO $db)
    {
        $this->db    = $db;
        $this->model = new CategoryModel($db);
    }

    public function index(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $categorias = $this->model->getAll();
        $title = 'Categorías';

        ob_start();
        require VIEWS_PATH . '/categories/index.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }

    public function store(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $nombre      = trim($_POST['nombre'] ?? '');
        $codigo      = trim($_POST['codigo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $parentId    = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if ($nombre === '') {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'El nombre es requerido.'];
            redirect('/categorias');
            return;
        }

        try {
            $data = [
                'nombre'      => $nombre,
                'descripcion' => $descripcion,
            ];

            if ($codigo !== '') {
                $data['codigo'] = $codigo; // 👈 Guarda el código escrito (ej: CAT06)
            }
            if ($parentId) {
                $data['parent_id'] = $parentId;
            }

            $this->model->create($data);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Categoría creada exitosamente.'];
        } catch (\Throwable $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error al crear: ' . $e->getMessage()];
        }

        redirect('/categorias');
    }

    public function update(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $id          = (int)($_POST['id'] ?? 0);
        $nombre      = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        if ($id === 0 || $nombre === '') {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Datos inválidos.'];
            redirect('/categorias');
            return;
        }

        $this->model->update($id, ['nombre' => $nombre, 'descripcion' => $descripcion]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Categoría actualizada.'];
        redirect('/categorias');
    }

    public function delete(): void
    {
        // Asegurar que la respuesta siempre sea tratada como JSON estricto
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autorizado.']);
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de categoría inválido.']);
            return;
        }

        try {
            // Capturamos el array de resultado que devuelve el CategoryModel->delete($id)
            $resultado = $this->model->delete($id);

            if (isset($resultado['success']) && $resultado['success'] === false) {
                http_response_code(422); // Unprocessable Entity (regla de negocio bloqueada)
            } else {
                http_response_code(200); // Éxito
            }

            echo json_encode($resultado);
        } catch (\Throwable $e) {
            // Captura cualquier error crítico de base de datos y lo devuelve en JSON seguro
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'message' => 'Error en el servidor: ' . $e->getMessage()
            ]);
        }
    }
}
