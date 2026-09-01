<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\PurchaseModel;
use App\Models\SupplierModel;

class PurchaseController
{
    private PDO $db;
    private PurchaseModel $model;

    public function __construct(PDO $db)
    {
        $this->db    = $db;
        $this->model = new PurchaseModel($db);
    }

    public function index(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $filters = [
            'busqueda'    => $_GET['q']            ?? '',
            'proveedor_id'=> (int)($_GET['prov']   ?? 0),
            'fecha_desde' => $_GET['desde']        ?? '',
            'fecha_hasta' => $_GET['hasta']        ?? '',
            'estado'      => $_GET['estado']       ?? '',
            'limit'       => (int)($_GET['limit']  ?? 25),
            'offset'      => ((int)($_GET['page']  ?? 1) - 1) * (int)($_GET['limit'] ?? 25),
        ];

        $resultado   = $this->model->getAll($filters);
        $proveedores = $this->db->query("SELECT id, razon_social FROM proveedores WHERE activo = 1 ORDER BY razon_social")->fetchAll(PDO::FETCH_ASSOC);

        $totalPaginas = (int)ceil(($resultado['total'] ?? 0) / $filters['limit']);
        $paginaActual = (int)($_GET['page'] ?? 1);
        $title = 'Compras';

        ob_start();
        require VIEWS_PATH . '/purchase/index.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }

    public function store(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $data = [
            'proveedor_id'   => (int)($_POST['proveedor_id']   ?? 0),
            'usuario_id'     => (int)($_SESSION['user_id']),
            'moneda'         => 'PEN',
            'tipo_cambio'    => 1.0,
            'fecha_emision'  => $_POST['fecha']                ?? date('Y-m-d'),
            'notas'          => 'Ref Doc: ' . trim($_POST['numero_factura'] ?? ''),
            'subtotal'       => (float)($_POST['subtotal']     ?? 0),
            'igv'            => (float)($_POST['igv']          ?? 0),
            'total'          => (float)($_POST['total']        ?? 0),
            'items'          => $_POST['items']                ?? [],
            'estado'         => 'emitida'
        ];

        try {
            $this->model->create($data);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Compra registrada exitosamente.'];
        } catch (\Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error: ' . $e->getMessage()];
        }

        redirect('/compras');
    }
}
