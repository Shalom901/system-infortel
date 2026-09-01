<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\SupplierModel;

class SupplierController
{
    private PDO $db;
    private SupplierModel $model;

    public function __construct(PDO $db)
    {
        $this->db    = $db;
        $this->model = new SupplierModel($db);
    }

    public function index(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        // Sanitización estricta: El estado por defecto debe ser 1 (Activo), no la palabra 'activo'
        $estadoFiltro = isset($_GET['estado']) && $_GET['estado'] !== '' ? (int)$_GET['estado'] : 1;

        $filters = [
            'busqueda' => trim($_GET['q'] ?? ''),
            'estado'   => $estadoFiltro,
            'limit'    => (int)($_GET['limit'] ?? 25),
            'offset'   => ((int)($_GET['page'] ?? 1) - 1) * (int)($_GET['limit'] ?? 25),
        ];

        $resultado = $this->model->getAll($filters);

        // Extracción de datos para la vista
        $proveedores      = $resultado['data'] ?? [];
        $totalProveedores = $resultado['total'] ?? 0;
        
        $title = 'Proveedores - Módulo Core';

        ob_start();
        require VIEWS_PATH . '/suppliers/index.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }

    public function store(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        // Mapeo estricto ajustado a las columnas físicas reales de la tabla proveedores
        $data = [
            'razon_social'      => trim($_POST['razon_social']      ?? ''),
            'numero_doc'        => trim($_POST['ruc'] ?? $_POST['numero_doc'] ?? ''),
            'tipo_doc'          => 6,
            'direccion'         => trim($_POST['direccion']         ?? ''),
            'telefono'          => trim($_POST['telefono']          ?? ''),
            'email'             => trim($_POST['email']             ?? ''),
            'contacto_nombre'   => trim($_POST['contacto'] ?? $_POST['contacto_nombre'] ?? ''),
        ];

        if (empty($data['razon_social'])) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'La razón social es requerida.'];
            redirect('/proveedores');
            return;
        }

        if (!preg_match('/^\d{11}$/', $data['numero_doc'])) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'El RUC debe tener exactamente 11 dígitos.'];
            redirect('/proveedores/crear');
            return;
        }

        try {
            $this->model->create($data);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Proveedor creado exitosamente.'];
        } catch (\Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error al registrar: ' . $e->getMessage()];
        }

        redirect('/proveedores');
    }

    public function create(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }
        $modo = 'crear';
        $proveedor = null;
        $title = 'Nuevo Proveedor';
        
        ob_start();
        require VIEWS_PATH . '/suppliers/form.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }

    public function edit(int $id): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }
        
        $proveedor = $this->model->getById($id);
        if (!$proveedor) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Proveedor no encontrado.'];
            redirect('/proveedores');
            return;
        }

        $modo = 'editar';
        $title = 'Editar Proveedor';
        
        ob_start();
        require VIEWS_PATH . '/suppliers/form.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }

    public function update(int $id): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $data = [
            'tipo_doc'          => 6,
            'razon_social'      => trim($_POST['razon_social']      ?? ''),
            'numero_doc'        => trim($_POST['ruc'] ?? $_POST['numero_doc'] ?? ''),
            'direccion'         => trim($_POST['direccion']         ?? ''),
            'telefono'          => trim($_POST['telefono']          ?? ''),
            'email'             => trim($_POST['email']             ?? ''),
            'contacto_nombre'   => trim($_POST['contacto'] ?? $_POST['contacto_nombre'] ?? ''),
        ];

        if (!preg_match('/^\d{11}$/', $data['numero_doc'])) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'El RUC debe tener exactamente 11 dígitos.'];
            redirect('/proveedores/' . $id . '/editar');
            return;
        }

        try {
            $this->model->update($id, $data);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Proveedor actualizado correctamente.'];
        } catch (\Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error al actualizar: ' . $e->getMessage()];
        }

        redirect('/proveedores');
    }

    public function delete(int $id): void
    {
        if (empty($_SESSION['user_id'])) {
            redirect('/');
            return;
        }
        
        $resultado = $this->model->delete($id);
        
        if (isset($resultado['success']) && !$resultado['success']) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $resultado['message']];
        } else {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Proveedor eliminado correctamente.'];
        }
        
        redirect('/proveedores');
    }

    public function getByRuc(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $ruc = trim($_GET['ruc'] ?? '');
        
        if (strlen($ruc) !== 11) {
            echo json_encode(['success' => false, 'message' => 'RUC inválido']);
            return;
        }

        // Corrección de la consulta SQL directa usando la columna real 'numero_doc'
        $stmt = $this->db->prepare("SELECT * FROM proveedores WHERE numero_doc = :ruc LIMIT 1");
        $stmt->execute([':ruc' => $ruc]);
        $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => (bool)$proveedor, 'data' => $proveedor ?: null]);
    }
}