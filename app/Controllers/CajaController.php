<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\CajaModel;

class CajaController
{
    private PDO $db;
    private CajaModel $model;

    public function __construct(PDO $db)
    {
        $this->db    = $db;
        $this->model = new CajaModel($db);
    }

    public function index(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $userId = (int)$_SESSION['user_id'];

        // La caja es compartida por los usuarios autorizados.
        $aperturaActiva = $this->model->getCajaAbierta(1);
        $cajaAbierta = $aperturaActiva ? array_merge($aperturaActiva, [
            'abierta_por'       => trim(($aperturaActiva['usuario_nombre'] ?? '') . ' ' . ($aperturaActiva['usuario_apellidos'] ?? '')),
            'hora_apertura'     => $aperturaActiva['fecha_apertura'] ?? null,
            'monto_inicial_pen' => (float)($aperturaActiva['saldo_inicial_pen'] ?? 0),
            'monto_inicial_usd' => (float)($aperturaActiva['saldo_inicial_usd'] ?? 0),
        ]) : null;

        // Historial (usamos caja_id 1 por defecto o el de la apertura activa)
        $cajaId = (int)($aperturaActiva['caja_id'] ?? 1);
        $historial = [];
        try {
            $historial = $this->model->getHistorial($cajaId, 15);
        } catch (\Exception $e) {
            // Si no existe la tabla, simplemente vaciamos
        }

        // Si hay apertura activa, calcular totales del día
        $resumenDia = [];
        $totales = [
            'ingresos_pen' => 0.0,
            'egresos_pen'  => 0.0,
            'ingresos_usd' => 0.0,
            'egresos_usd'  => 0.0,
        ];
        $movimientos = [];
        $cierreCalculado = null;
        if ($aperturaActiva) {
            try {
                $totalesPorMetodo = $this->model->getTotalesByMetodo((int)$aperturaActiva['id']);
            $cierreCalculado = $this->model->getCierreCalculado((int)$aperturaActiva['id'], $aperturaActiva);
                $totales['ingresos_pen'] = array_sum(array_map('floatval', $totalesPorMetodo));

                // Agregar totales de ventas del día
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as cantidad_ventas, COALESCE(SUM(total), 0) as total_ventas,
                           COALESCE(SUM(CASE WHEN pv.metodo_pago='efectivo' THEN pv.monto ELSE 0 END), 0) as total_efectivo
                    FROM ventas v
                    LEFT JOIN pagos_venta pv ON v.id = pv.venta_id
                    WHERE v.caja_apertura_id = :id AND v.estado != 'anulada'
                ");
                $stmt->execute([':id' => $aperturaActiva['id']]);
                $resumenDia = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                $tipoCambio = (float)($_ENV['TIPO_CAMBIO_USD'] ?? 3.7);
                $totales['ingresos_usd'] = $tipoCambio > 0
                    ? round($totales['ingresos_pen'] / $tipoCambio, 2)
                    : 0.0;

                $stmtMovimientos = $this->db->prepare("
                    SELECT v.created_at AS hora, v.total_pen AS monto,
                           CONCAT('Venta ', v.tipo_comprobante, ' ', v.serie, '-', v.numero) AS descripcion
                    FROM ventas v
                    WHERE v.apertura_caja_id = :id AND v.estado != 'anulada'
                    ORDER BY v.created_at DESC
                    LIMIT 50
                ");
                $stmtMovimientos->execute([':id' => $aperturaActiva['id']]);
                foreach ($stmtMovimientos->fetchAll(PDO::FETCH_ASSOC) as $movimiento) {
                    $movimientos[] = [
                        'tipo' => 'ingreso',
                        'hora' => $movimiento['hora'],
                        'descripcion' => $movimiento['descripcion'],
                        'moneda' => 'PEN',
                        'monto' => (float)$movimiento['monto'],
                    ];
                }
            } catch (\Exception $e) {
                $resumenDia = [];
            }
        }

        // Obtener lista de cajas disponibles para abrir
        $cajas = [];
        try {
            $cajas = $this->model->getAllCajas();
            if (empty($cajas)) {
                // Si no hay cajas, crear una por defecto con query directa
                $cajas = [['id' => 1, 'nombre' => 'Caja Principal']];
            }
        } catch (\Exception $e) {
            $cajas = [['id' => 1, 'nombre' => 'Caja Principal']];
        }

        $title = 'Caja';

        ob_start();
        require VIEWS_PATH . '/caja/index.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }

    public function abrir(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $cajaId       = (int)($_POST['caja_id']      ?? 1);
        $montoInicial = (float)($_POST['monto_inicial_pen'] ?? $_POST['monto_inicial'] ?? 0);
        $userId       = (int)$_SESSION['user_id'];

        try {
            $aperturaId = $this->model->abrir($cajaId, $userId, $montoInicial, 0.00);
            $_SESSION['flash'] = ['type' => 'success', 'message' => $aperturaId ? 'Caja disponible para todos los usuarios autorizados.' : 'La caja ya estaba abierta.'];
        } catch (\Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error al abrir caja: ' . $e->getMessage()];
        }

        redirect('/caja');
    }

    public function cerrar(): void
    {
        if (empty($_SESSION['user_id'])) { redirect('/'); return; }

        $aperturaId  = (int)($_POST['apertura_id'] ?? 0);
        $montoFinal  = (float)($_POST['monto_final'] ?? 0);
        $notas       = trim($_POST['observaciones'] ?? '');

        try {
            $this->model->cerrar($aperturaId, [
                'saldo_final_efectivo'     => $montoFinal,
                'saldo_final_efectivo_usd' => 0,
                'usuario_cierre_id'       => (int)$_SESSION['user_id'],
                'notas'                    => $notas,
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Caja cerrada exitosamente.'];
        } catch (\Exception $e) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Error al cerrar caja: ' . $e->getMessage()];
        }

        redirect('/caja');
    }
}
