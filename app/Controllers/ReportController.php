<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\SaleModel;

class ReportController
{
    private PDO $db;
    private SaleModel $saleModel;

    public function __construct(PDO $db)
    {
        $this->db        = $db;
        $this->saleModel = new SaleModel($db);
    }

    public function index(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $fechaDesde = $_GET['desde'] ?? date('Y-m-01');
        $fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
        $tipo       = $_GET['tipo']  ?? null;

        // Resumen de ventas del período
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*)          AS cantidad_ventas,
                COALESCE(SUM(total_pen), 0) AS total_ventas,
                COALESCE(SUM(igv), 0)       AS total_igv,
                COALESCE(SUM(subtotal_gravado + subtotal_exonerado + subtotal_inafecto), 0) AS total_neto,
                COALESCE(SUM(descuento_global), 0) AS total_descuentos
            FROM ventas
            WHERE DATE(fecha_emision) BETWEEN :desde AND :hasta
              AND estado != 'anulada'
              " . ($tipo ? "AND tipo_comprobante = :tipo" : "") . "
        ");
        $params = [':desde' => $fechaDesde, ':hasta' => $fechaHasta];
        if ($tipo) $params[':tipo'] = $tipo;
        $stmt->execute($params);
        $resumen = $stmt->fetch(PDO::FETCH_ASSOC);

        // Ventas por tipo de comprobante
        $stmtTipos = $this->db->prepare("
            SELECT tipo_comprobante, COUNT(*) as cantidad, SUM(total_pen) as total
            FROM ventas
            WHERE DATE(fecha_emision) BETWEEN :desde AND :hasta AND estado != 'anulada'
            GROUP BY tipo_comprobante
        ");
        $stmtTipos->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
        $porTipo = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

        // Top 10 productos más vendidos
        $stmtTop = $this->db->prepare("
            SELECT p.nombre, SUM(dv.cantidad) as total_vendido, SUM(dv.subtotal_pen) as ingreso
            FROM detalle_ventas dv
            JOIN ventas v ON dv.venta_id = v.id
            JOIN productos p ON dv.producto_id = p.id
            WHERE DATE(v.fecha_emision) BETWEEN :desde AND :hasta AND v.estado != 'anulada'
            GROUP BY dv.producto_id, p.nombre
            ORDER BY total_vendido DESC
            LIMIT 10
        ");
        $stmtTop->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
        $topProductos = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

        // Ventas por método de pago
        $stmtMetodos = $this->db->prepare("
            SELECT pv.metodo_pago, COUNT(DISTINCT pv.venta_id) as cantidad, SUM(pv.monto) as total
            FROM pagos_venta pv
            JOIN ventas v ON pv.venta_id = v.id
            WHERE DATE(v.fecha_emision) BETWEEN :desde AND :hasta AND v.estado != 'anulada'
            GROUP BY pv.metodo_pago
            ORDER BY total DESC
        ");
        $stmtMetodos->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
        $porMetodo = $stmtMetodos->fetchAll(PDO::FETCH_ASSOC);

        // Ventas diarias (para gráfico de línea)
        $stmtDiario = $this->db->prepare("
            SELECT DATE(fecha_emision) as fecha, SUM(total_pen) as total, COUNT(*) as cantidad
            FROM ventas
            WHERE DATE(fecha_emision) BETWEEN :desde AND :hasta AND estado != 'anulada'
            GROUP BY DATE(fecha_emision)
            ORDER BY fecha ASC
        ");
        $stmtDiario->execute([':desde' => $fechaDesde, ':hasta' => $fechaHasta]);
        $ventasDiarias = $stmtDiario->fetchAll(PDO::FETCH_ASSOC);

        $title = 'Reportes';

        ob_start();
        require VIEWS_PATH . '/reports/index.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }
}
