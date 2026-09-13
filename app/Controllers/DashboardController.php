<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;

class DashboardController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function index(): void
    {
        if (!$this->hasSession()) {
            redirect('/');
            return;
        }

        $dashboardData = $this->getDashboardData();
        $title = 'Dashboard';

        // Extraer datos estructurados para la vista
        $summary     = $dashboardData['summary'];
        $salesTrend  = $dashboardData['sales_trend'];
        $payments    = $dashboardData['payments'];
        $hourlySales = $dashboardData['hourly_sales'];
        $recentSales = $dashboardData['recent_sales'];

        // Mantener compatibilidad con variables legacy
        $ventasHoy       = $summary;
        $comprasMes      = $summary['compras_mes'];
        $bajoStock       = $summary['bajo_stock'];
        $ventasRecientes = $recentSales;
        $datosGrafico    = $salesTrend;

        ob_start();
        require VIEWS_PATH . '/dashboard/index.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';

        // Obtener los productos con mayor rotación y ventas
        $topProducts = [];
        try {
            $sqlTop = "
                SELECT 
                    p.id,
                    p.nombre,
                    p.codigo_interno,
                    p.imagen_path,
                    COALESCE(SUM(dv.cantidad), 0) AS total_vendido,
                    COALESCE(SUM(dv.subtotal), 0) AS total_monto
                FROM detalle_ventas dv
                JOIN productos p ON p.id = dv.producto_id
                JOIN ventas v ON v.id = dv.venta_id
                WHERE v.estado != 'anulada'
                GROUP BY p.id, p.nombre, p.codigo_interno, p.imagen_path
                ORDER BY total_vendido DESC
                LIMIT 4
            ";
            $stmt = $this->db->query($sqlTop);
            $topProducts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $topProducts = [];
        }
    }

    /** Datos usados por la actualización automática del dashboard. */
    public function liveData(): void
    {
        if (!$this->hasSession()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'data' => $this->getDashboardData(),
            'updated_at' => date('H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
    }

    private function hasSession(): bool
    {
        return !empty($_SESSION['user_id']) || !empty($_SESSION['usuario_id']);
    }

    private function getDashboardData(): array
    {
        $today = date('Y-m-d');
        $start30Days = date('Y-m-d 00:00:00', strtotime('-29 days'));

        $ventasHoy = $this->fetchOne(
            'SELECT COUNT(*) AS cantidad, COALESCE(SUM(total_pen), 0) AS monto
             FROM ventas WHERE DATE(fecha_emision) = :today AND estado != \'anulada\'',
            [':today' => $today],
            ['cantidad' => 0, 'monto' => 0]
        );

        $bajoStock = (int)$this->fetchValue(
            'SELECT COUNT(*) FROM productos WHERE stock_actual <= stock_minimo AND activo = 1'
        );

        $comprasMes = (float)$this->fetchValue(
            'SELECT COALESCE(SUM(total_pen), 0) FROM ordenes_compra
             WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
             AND estado != \'cancelada\''
        );

        $ventasRecientes = $this->fetchAll(
            'SELECT CONCAT(v.tipo_comprobante, \' \', v.serie, \'-\', LPAD(v.numero, 8, \'0\')) AS comprobante,
                  v.total_pen AS total, DATE_FORMAT(v.created_at, \'%d/%m/%Y %H:%i\') AS fecha,
                    COALESCE(NULLIF(c.razon_social, \'\'), NULLIF(CONCAT_WS(\' \', c.nombres, c.apellidos), \'\'), \'Cliente general\') AS cliente
             FROM ventas v
             LEFT JOIN clientes c ON c.id = v.cliente_id
             WHERE v.estado != \'anulada\'
              ORDER BY v.created_at DESC, v.id DESC LIMIT 8'
        );

        $dailyRows = $this->fetchAll(
            'SELECT DATE(fecha_emision) AS fecha, COALESCE(SUM(total_pen), 0) AS total,
                    COUNT(*) AS cantidad
             FROM ventas
             WHERE fecha_emision >= :start AND estado != \'anulada\'
             GROUP BY DATE(fecha_emision) ORDER BY fecha ASC',
            [':start' => $start30Days]
        );
        $salesTrend = $this->makeDailySeries($dailyRows);

        $paymentRows = $this->fetchAll(
            'SELECT LOWER(TRIM(pv.metodo_pago)) AS metodo,
                    COALESCE(SUM(NULLIF(pv.monto_pen, 0)), SUM(pv.monto), 0) AS total
             FROM pagos_venta pv
             INNER JOIN ventas v ON v.id = pv.venta_id
             WHERE v.created_at >= :start AND v.estado != \'anulada\'
             GROUP BY LOWER(TRIM(pv.metodo_pago))',
            [':start' => $start30Days]
        );
        $payments = $this->makePaymentBreakdown($paymentRows);

        $hourRows = $this->fetchAll(
            'SELECT HOUR(fecha_emision) AS hora, COUNT(*) AS cantidad, COALESCE(SUM(total_pen), 0) AS total
             FROM ventas WHERE fecha_emision >= :start AND estado != \'anulada\'
             GROUP BY HOUR(fecha_emision)',
            [':start' => $start30Days]
        );
        $hours = $this->makeHourlySeries($hourRows);

        $total30Days = array_sum($salesTrend['data']);
        $salesCount30Days = array_sum($salesTrend['counts']);

        return [
            'summary' => [
                'ventas_hoy' => (int)($ventasHoy['cantidad'] ?? 0),
                'monto_hoy' => (float)($ventasHoy['monto'] ?? 0),
                'compras_mes' => $comprasMes,
                'bajo_stock' => $bajoStock,
                'ticket_promedio' => $salesCount30Days > 0 ? $total30Days / $salesCount30Days : 0,
                'mejor_hora' => $hours['best'],
                'hora_baja' => $hours['lowest'],
            ],
            'sales_trend' => $salesTrend,
            'payments' => $payments,
            'hourly_sales' => $hours,
            'recent_sales' => $ventasRecientes,
        ];
    }

    private function makeDailySeries(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['fecha']] = $row;
        }

        $labels = $data = $counts = [];
        for ($offset = 29; $offset >= 0; $offset--) {
            $date = date('Y-m-d', strtotime("-{$offset} days"));
            $labels[] = date('d M', strtotime($date));
            $data[] = (float)($indexed[$date]['total'] ?? 0);
            $counts[] = (int)($indexed[$date]['cantidad'] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data, 'counts' => $counts];
    }

    private function makePaymentBreakdown(array $rows): array
    {
        $totals = [
            'Efectivo' => 0.0,
            'Yape' => 0.0,
            'Plin' => 0.0,
            'Transferencias' => 0.0,
            'Tarjetas' => 0.0,
            'Otros' => 0.0,
        ];
        foreach ($rows as $row) {
            $method = (string)($row['metodo'] ?? '');
            $label = str_contains($method, 'yape') ? 'Yape'
                : (str_contains($method, 'plin') ? 'Plin'
                : (str_contains($method, 'efectivo') || $method === 'cash' ? 'Efectivo'
                : (str_contains($method, 'tarjeta') ? 'Tarjetas'
                : (in_array($method, ['bcp', 'interbank', 'bbva', 'scotiabank', 'transferencia'], true) ? 'Transferencias' : 'Otros'))));
            $totals[$label] += (float)($row['total'] ?? 0);
        }

        return ['labels' => array_keys($totals), 'data' => array_values($totals)];
    }

    private function makeHourlySeries(array $rows): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int)$row['hora']] = $row;
        }

        $labels = $data = $counts = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = sprintf('%02d:00', $hour);
            $data[] = (float)($indexed[$hour]['total'] ?? 0);
            $counts[] = (int)($indexed[$hour]['cantidad'] ?? 0);
        }

        $bestHour = array_keys($data, max($data), true)[0] ?? 0;
        $activeHours = array_filter($data, static fn(float $amount): bool => $amount > 0);
        $lowestHour = $activeHours === [] ? 0 : array_keys($data, min($activeHours), true)[0];

        return [
            'labels' => $labels,
            'data' => $data,
            'counts' => $counts,
            'best' => ['label' => $labels[$bestHour], 'amount' => $data[$bestHour]],
            'lowest' => ['label' => $labels[$lowestHour], 'amount' => $data[$lowestHour]],
        ];
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchOne(string $sql, array $params, array $fallback): array
    {
        $rows = $this->fetchAll($sql, $params);
        return $rows[0] ?? $fallback;
    }

    private function fetchValue(string $sql): mixed
    {
        try {
            return $this->db->query($sql)->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
