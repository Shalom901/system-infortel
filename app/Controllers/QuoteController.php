<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\QuoteModel;

class QuoteController
{
    private PDO $db;
    private QuoteModel $model;

    public function __construct(PDO $db)
    {
        $this->db    = $db;
        $this->model = new QuoteModel($db);
    }

    public function index(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $filters = [
            'busqueda'    => $_GET['q']           ?? '',
            'estado'      => $_GET['estado']      ?? '',
            'fecha_desde' => $_GET['desde']       ?? '',
            'fecha_hasta' => $_GET['hasta']       ?? '',
            'limit'       => (int)($_GET['limit'] ?? 25),
            'offset'      => ((int)($_GET['page'] ?? 1) - 1) * (int)($_GET['limit'] ?? 25),
        ];

        try {
            $resultado = $this->model->getAll($filters);
        } catch (\Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            redirect('/cotizaciones');
            $resultado = ['data' => [], 'total' => 0];
        }
        
        $cotizaciones = $resultado['data'] ?? [];
        $totalPaginas = (int)ceil(($resultado['total'] ?? 0) / $filters['limit']);
        $paginaActual = (int)($_GET['page'] ?? 1);
        $title = 'Cotizaciones';

        ob_start();
        require VIEWS_PATH . '/quotes/index.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }

    public function crear(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }
        
        $title = 'Nueva Cotización';
        // Pasar el cliente genérico para que el JS lo tenga disponible
        $clienteGenerico = json_encode([
            'id' => 1,
            'razon_social' => 'CLIENTE GENÉRICO',
            'numero_doc' => '00000000',
            'tipo_doc' => 'DNI',
        ]);
        
        ob_start();
        require VIEWS_PATH . '/quotes/crear.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }

    public function store(): void
    {
        header('Content-Type: application/json');
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'No autorizado']);
            return;
        }

        // Leer JSON desde php input
        $input = file_get_contents('php://input');
        $postData = json_decode($input, true) ?? [];

        $validezDias = (int)($postData['validez'] ?? 15);
        $fechaVencimiento = date('Y-m-d', strtotime("+{$validezDias} days"));

        $clienteId = (int)($postData['cliente_id'] ?? 1); // 1 = Cliente Genérico por defecto

        // Items recibidos desde el JS
        $items = $postData['items'] ?? [];
        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'La cotización no tiene items.']);
            return;
        }

        $data = [
            'cliente_id'        => $clienteId > 0 ? $clienteId : null,
            'usuario_id'        => (int)$_SESSION['user_id'],
            'moneda'            => $postData['moneda'] ?? 'PEN',
            'tipo_cambio'       => 1.0,
            'fecha_vencimiento' => $fechaVencimiento,
            'notas'             => trim($postData['notas'] ?? ''),
            'condiciones'       => trim($postData['condiciones'] ?? ''),
            'subtotal'          => (float)($postData['subtotal'] ?? 0),
            'descuento_total'   => (float)($postData['descuento_total'] ?? 0),
            'igv'               => (float)($postData['igv'] ?? 0),
            'total'             => (float)($postData['total'] ?? 0),
            'items'             => $items,
        ];

        try {
            $quoteId = $this->model->create($data);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Cotización creada exitosamente.'];
            echo json_encode(['success' => true, 'message' => 'Cotización creada', 'id' => $quoteId]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function convertToSale(): void
    {
        if (empty($_SESSION['user_id'])) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'No autorizado.'];
            redirect('/cotizaciones');
            return;
        }

        $id = (int)($_POST['id'] ?? 0);
        try {
            $ventaId = $this->model->convertToSale(
                $id,
                (int)$_SESSION['user_id'],
                $_POST['tipo_comprobante'] ?? '03',
                $_POST['metodo_pago'] ?? 'efectivo'
            );
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Cotización convertida a venta correctamente.'];
            redirect('/cotizaciones');
            echo json_encode(['success' => true, 'message' => 'Cotización convertida a venta.', 'venta_id' => $ventaId]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        redirect('/cotizaciones');
    }

    /** Genera la representación A4 de una cotización; no es un comprobante SUNAT. */
    public function printPDF(int $quoteId): void
    {
        if (empty($_SESSION['user_id'])) {
            redirect('/');
            return;
        }

        $cotizacion = $this->model->getById($quoteId);
        if (!$cotizacion) {
            http_response_code(404);
            echo 'Cotización no encontrada.';
            return;
        }

        $config = $this->getEmpresaConfig();
        ob_start();
        include VIEWS_PATH . '/quotes/cotizacion_pdf.php';
        $html = ob_get_clean();

        if (class_exists('Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream('cotizacion_' . $cotizacion['numero'] . '.pdf', ['Attachment' => false]);
            return;
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
    }

    private function getEmpresaConfig(): array
    {
        $config = [];
        try {
            $stmt = $this->db->query("SELECT clave, valor FROM configuracion WHERE clave LIKE 'empresa_%'");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $config[str_replace('empresa_', '', $row['clave'])] = $row['valor'];
            }
        } catch (\Exception) {
            // Los valores de entorno cubren instalaciones sin tabla de configuración.
        }

        return $config + [
            'razon_social' => $_ENV['EMPRESA_NOMBRE'] ?? 'EMPRESA',
            'ruc'          => $_ENV['EMPRESA_RUC'] ?? '-',
            'direccion'    => $_ENV['EMPRESA_DIR'] ?? '',
            'telefono'     => $_ENV['EMPRESA_TEL'] ?? '',
        ];
    }
}
