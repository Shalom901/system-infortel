<?php
declare(strict_types=1);

namespace App\Controllers;

use Config\Database;

class NotificationController
{
    public function __construct(\PDO $db = null) {}

    public function fetchAll(): void
    {
        $db = Database::getInstance();
        $alertas = [];
        $appUrl = defined('APP_URL') ? APP_URL : 'http://localhost:8080';

        // 1. Inventario Crítico
        try {
            $sqlStock = "SELECT id, nombre, stock_actual, stock_minimo FROM productos WHERE stock_actual <= stock_minimo AND activo = 1 LIMIT 5";
            $productosStock = $db->fetchAll($sqlStock);
            foreach ($productosStock as $p) {
                // Asegurar que la URL use la ruta relativa exacta que tu router ya conoce
                $alertas[] = [
                    'id' => uniqid('stk_'),
                    'tipo' => 'inventario_critico',
                    'titulo' => 'Stock Crítico: ' . $p['nombre'],
                    'mensaje' => "Quedan " . (float)$p['stock_actual'] . " unidades (Mínimo: " . (float)$p['stock_minimo'] . ").",
                    'color' => 'red',
                    'icono' => 'fa-box-open',
                    'url' => baseUrl("productos/{$p['id']}/editar") // Utilizando la función baseUrl() de tus helpers
                ];
            }
        } catch (\Exception $e) {}

        // 2. Compliance: Certificado Digital (Usando tu tabla configuracion_sunat)
        try {
            // Nota: Verifica que la columna se llame 'fecha_vencimiento_cert' en tu tabla configuracion_sunat
            $sqlCert = "SELECT fecha_vencimiento_cert, DATEDIFF(fecha_vencimiento_cert, CURDATE()) as dias_restantes FROM configuracion_sunat LIMIT 1";
            $cert = $db->fetchOne($sqlCert);
            
            if ($cert && isset($cert['dias_restantes'])) {
                $dias = (int)$cert['dias_restantes'];
                if (in_array($dias, [30, 15, 5]) || $dias <= 0) {
                    $alertas[] = [
                        'id' => 'cert_alert',
                        'tipo' => 'critico_fiscal',
                        'titulo' => $dias <= 0 ? 'CERTIFICADO VENCIDO' : 'Vencimiento de Certificado',
                        'mensaje' => $dias <= 0 ? 'El sistema no puede firmar comprobantes.' : "El certificado caduca en {$dias} días.",
                        'color' => 'purple',
                        'icono' => 'fa-file-signature',
                        'url' => $appUrl . "/sunat/config"
                    ];
                }
            }
        } catch (\Exception $e) {}

        // 3. Inteligencia Comercial: Productos inmovilizados (Usando detalle_ventas y ventas)
        try {
            // Nota: Verifica que la columna de fecha en 'ventas' sea 'fecha_emision'
            $sqlRotacion = "
                SELECT p.id, p.nombre 
                FROM productos p 
                WHERE p.activo = 1 
                AND p.id NOT IN (
                    SELECT dv.producto_id 
                    FROM detalle_ventas dv 
                    JOIN ventas v ON dv.venta_id = v.id 
                    WHERE v.fecha_emision >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                )
                LIMIT 3
            ";
            $productosMuertos = $db->fetchAll($sqlRotacion);
            foreach ($productosMuertos as $pm) {
                $alertas[] = [
                    'id' => uniqid('rot_'),
                    'tipo' => 'inteligencia_comercial',
                    'titulo' => 'Inventario Inmovilizado',
                    'mensaje' => "El producto '{$pm['nombre']}' no registra ventas en 90 días.",
                    'color' => 'blue',
                    'icono' => 'fa-chart-line-down',
                    'url' => $appUrl . "/reportes/inventario"
                ];
            }
        } catch (\Exception $e) {}

        // Salida HTTP final
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($alertas);
        exit;
    }
}