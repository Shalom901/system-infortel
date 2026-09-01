<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\SaleModel;

class SaleController
{
    private SaleModel $saleModel;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->saleModel = new SaleModel($db);
    }

    /**
     * Listado de ventas con filtros
     */
    public function index(): void
    {
        if (empty($_SESSION['user_id']) && empty($_SESSION['usuario_id'])) {
            redirect('/');
            return;
        }

        $filters = [
            'busqueda'         => $_GET['q']           ?? '',
            'tipo_comprobante' => $_GET['tipo']         ?? '',
            'fecha_desde'      => $_GET['desde']        ?? date('Y-m-01'),
            'fecha_hasta'      => $_GET['hasta']        ?? date('Y-m-d'),
            'limit'            => (int)($_GET['limit']  ?? 25),
            'offset'           => ((int)($_GET['page']  ?? 1) - 1) * (int)($_GET['limit'] ?? 25),
        ];

        try {
            $resultado = $this->saleModel->getAll($filters);
        } catch (\Exception) {
            $resultado = ['data' => [], 'total' => 0];
        }

        $ventas       = $resultado['data'] ?? [];
        $totalPaginas = (int)ceil(($resultado['total'] ?? 0) / $filters['limit']);
        $paginaActual = (int)($_GET['page'] ?? 1);
        $title        = 'Ventas';

        ob_start();
        require VIEWS_PATH . '/sales/index.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }

    /**
     * Crear venta desde el POS (JSON API)
     */
    public function store(): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['user_id']) && empty($_SESSION['usuario_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $userId = $_SESSION['user_id'] ?? $_SESSION['usuario_id'];
        $input  = file_get_contents('php://input');
        $datosVenta = json_decode($input, true);

        if (!$datosVenta || empty($datosVenta['items'])) {
            echo json_encode(['status' => 'error', 'message' => 'Datos de venta inválidos o vacíos']);
            return;
        }

        $datosVenta['usuario_id'] = (int)$userId;

        try {
            $ventaData = $this->saleModel->create($datosVenta);
            $ventaId   = $ventaData['id'] ?? null;

            if (!$ventaId) {
                echo json_encode(['status' => 'error', 'message' => 'No se pudo registrar la venta']);
                return;
            }

            echo json_encode([
                'status'             => 'success',
                'message'            => 'Venta registrada exitosamente.',
                'venta_id'           => $ventaId,
                'numero_comprobante' => $ventaData['numero_comprobante'] ?? '',
                'total'              => $ventaData['total'] ?? 0,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar: ' . $e->getMessage()]);
        }
    }

    /**
     * Anular una venta
     */
    public function cancel(): void
    {
        header('Content-Type: application/json');

        if (empty($_SESSION['user_id']) && empty($_SESSION['usuario_id'])) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            return;
        }

        $userId = $_SESSION['user_id'] ?? $_SESSION['usuario_id'];
        $id     = (int)($_POST['id']    ?? 0);
        $motivo = trim($_POST['motivo'] ?? 'Anulación solicitada');

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID de venta inválido']);
            return;
        }

        try {
            $this->saleModel->cancel($id, $motivo, (int)$userId);
            echo json_encode(['success' => true, 'message' => 'Venta anulada correctamente.']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}