<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use App\Models\SaleModel;
use App\Services\SunatService;
use App\Services\XmlGeneratorService;
use App\Services\XmlSignerService;

/**
 * Controlador de Operaciones SUNAT
 *
 * Gestiona la configuración, envío, consulta y administración
 * de comprobantes electrónicos hacia SUNAT.
 *
 * @package App\Controllers
 */
class SunatController
{
    private PDO $db;
    private SunatService $sunatService;
    private XmlGeneratorService $xmlGenerator;
    private XmlSignerService $xmlSigner;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->sunatService = new SunatService();
        $this->xmlGenerator = new XmlGeneratorService();
        $this->xmlSigner = new XmlSignerService();
    }

    // =========================================================================
    // CONFIGURACIÓN
    // =========================================================================

    /**
     * Muestra la página de configuración SUNAT
     */
    public function config(): void
    {
        $this->requireSunatViewer();

        // Series configuradas
        $stmtSeries = $this->db->query("SELECT * FROM configuracion_series ORDER BY tipo_comprobante, serie");
        $series = $stmtSeries ? $stmtSeries->fetchAll(PDO::FETCH_ASSOC) : [];

        // Comprobantes pendientes de enviar
        $stmtPendientes = $this->db->query("
            SELECT v.id, CONCAT(v.serie, '-', v.numero) AS numero_comprobante,
                   v.total_pen AS total, v.fecha_emision, v.tipo_comprobante,
                   COALESCE(ce.estado_sunat, 'pendiente') AS estado_sunat,
                   ce.intentos_envio, ce.fecha_envio
            FROM ventas v
            LEFT JOIN comprobantes_electronicos ce ON v.id = ce.venta_id
            WHERE (ce.estado_sunat IS NULL OR ce.estado_sunat IN ('pendiente','rechazado'))
              AND v.tipo_comprobante IN ('01','03')
              AND v.estado != 'anulada'
            ORDER BY v.fecha_emision DESC
            LIMIT 50
        ");
        $pendientes = $stmtPendientes ? $stmtPendientes->fetchAll(PDO::FETCH_ASSOC) : [];

        // Estadísticas
        $stmtStats = $this->db->query("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN estado_sunat = 'aceptado' THEN 1 ELSE 0 END) AS aceptados,
                SUM(CASE WHEN estado_sunat = 'rechazado' THEN 1 ELSE 0 END) AS rechazados,
                SUM(CASE WHEN estado_sunat = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN estado_sunat = 'enviado' THEN 1 ELSE 0 END) AS enviados
            FROM comprobantes_electronicos
        ");
        $stats = $stmtStats ? $stmtStats->fetch(PDO::FETCH_ASSOC) : [];

        $title = 'Configuración SUNAT';

        ob_start();
        require VIEWS_PATH . '/sunat/config.php';
        $content = ob_get_clean();
        require VIEWS_PATH . '/layouts/app.php';
    }

    /**
     * Actualiza la configuración SUNAT en el .env
     */
    public function updateConfig(): void
    {
        if (empty($_SESSION['user_id']) || !isAdmin()) {
            redirect('/dashboard');
            return;
        }

        $envPath = ROOT_PATH . '/.env';
        if (!file_exists($envPath)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Archivo .env no encontrado.'];
            redirect('/sunat/config');
            return;
        }

        $envContent = file_get_contents($envPath);

        $campos = ['SUNAT_RUC', 'SUNAT_USUARIO_SOL', 'SUNAT_CLAVE_SOL', 'SUNAT_MODO', 'SUNAT_CERT_PATH', 'SUNAT_CERT_PASS'];
        foreach ($campos as $campo) {
            if (isset($_POST[$campo])) {
                $valor = trim($_POST[$campo]);
                if (preg_match("/^{$campo}=.*/m", $envContent)) {
                    $envContent = preg_replace("/^{$campo}=.*/m", "{$campo}={$valor}", $envContent);
                } else {
                    $envContent .= "\n{$campo}={$valor}";
                }
            }
        }

        file_put_contents($envPath, $envContent);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Configuración SUNAT actualizada correctamente.'];
        redirect('/sunat/config');
    }

    // =========================================================================
    // ENVÍO DE COMPROBANTES
    // =========================================================================

    /**
     * Envía un comprobante específico a SUNAT
     *
     * POST /sunat/enviar
     * Body: { venta_id: int }
     */
    public function enviar(): void
    {
        header('Content-Type: application/json');
        $this->requireSunatSender();

        $ventaId = (int)($_POST['venta_id'] ?? ($_GET['venta_id'] ?? 0));
        if (!$ventaId) {
            echo json_encode(['success' => false, 'message' => 'ID de venta requerido.']);
            return;
        }

        try {
            $resultado = $this->procesarEnvio($ventaId);
            echo json_encode($resultado);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error al procesar envío: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Envía múltiples comprobantes pendientes a SUNAT
     *
     * POST /sunat/enviar-pendientes
     */
    public function enviarPendientes(): void
    {
        header('Content-Type: application/json');
        $this->requireSunatSender();

        try {
            // Obtener ventas pendientes
            $stmt = $this->db->query("
                SELECT v.id, v.tipo_comprobante, v.serie, v.numero,
                       CONCAT(v.serie, '-', v.numero) AS numero_comprobante
                FROM ventas v
                LEFT JOIN comprobantes_electronicos ce ON v.id = ce.venta_id
                WHERE (ce.estado_sunat IS NULL OR ce.estado_sunat IN ('pendiente','rechazado'))
                  AND v.tipo_comprobante IN ('01','03')
                  AND v.estado = 'pendiente'
                ORDER BY v.fecha_emision ASC
                LIMIT 10
            ");
            $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($pendientes)) {
                echo json_encode(['success' => true, 'message' => 'No hay comprobantes pendientes.', 'enviados' => 0]);
                return;
            }

            $resultados = [];
            $exitosos = 0;
            $fallidos = 0;

            foreach ($pendientes as $venta) {
                $resultado = $this->procesarEnvio((int)$venta['id']);
                $resultados[] = [
                    'id' => $venta['id'],
                    'numero' => $venta['numero_comprobante'],
                    'success' => $resultado['success'],
                    'message' => $resultado['message'],
                ];
                if ($resultado['success']) {
                    $exitosos++;
                } else {
                    $fallidos++;
                }
            }

            echo json_encode([
                'success' => $fallidos === 0,
                'message' => "Procesados: {$exitosos} exitosos, {$fallidos} fallidos.",
                'resultados' => $resultados,
                'total' => count($pendientes),
                'exitosos' => $exitosos,
                'fallidos' => $fallidos,
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // CONSULTA DE TICKETS
    // =========================================================================

    /**
     * Consulta el estado de un ticket de envío asíncrono
     *
     * GET /sunat/consultar-ticket?ticket=XXX
     */
    public function consultarTicket(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        $ticket = trim($_GET['ticket'] ?? '');
        if (empty($ticket)) {
            echo json_encode(['success' => false, 'message' => 'Ticket requerido.']);
            return;
        }

        try {
            $resultado = $this->sunatService->consultarTicket($ticket);
            echo json_encode($resultado);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Consulta todos los tickets pendientes en la base de datos
     */
    public function consultarTicketsPendientes(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        try {
            $stmt = $this->db->query("
                SELECT id, venta_id, ticket_sunat, serie, numero, tipo_comprobante
                FROM comprobantes_electronicos
                WHERE ticket_sunat IS NOT NULL
                  AND ticket_sunat != ''
                  AND estado_sunat = 'enviado'
                LIMIT 20
            ");
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $resultados = [];
            foreach ($tickets as $t) {
                $res = $this->sunatService->consultarTicket($t['ticket_sunat']);
                if ($res['success'] && $res['estado'] === 'ACEPTADO') {
                    // Actualizar estado en BD
                    $stmtUpd = $this->db->prepare("
                        UPDATE comprobantes_electronicos
                        SET estado_sunat = 'aceptado',
                            mensaje_sunat = :mensaje,
                            codigo_respuesta = :codigo,
                            cdr_path = :cdr_path,
                            fecha_envio = NOW(),
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmtUpd->execute([
                        ':id' => $t['id'],
                        ':mensaje' => $res['message'],
                        ':codigo' => $res['codigo'],
                        ':cdr_path' => $res['cdr_path'] ?? null,
                    ]);
                } elseif (!$res['success'] && $res['estado'] === 'RECHAZADO') {
                    $stmtUpd = $this->db->prepare("
                        UPDATE comprobantes_electronicos
                        SET estado_sunat = 'rechazado',
                            mensaje_sunat = :mensaje,
                            codigo_respuesta = :codigo,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmtUpd->execute([
                        ':id' => $t['id'],
                        ':mensaje' => $res['message'],
                        ':codigo' => $res['codigo'],
                    ]);
                }
                $resultados[] = [
                    'ticket' => $t['ticket_sunat'],
                    'venta_id' => $t['venta_id'],
                    'resultado' => $res,
                ];
            }

            echo json_encode([
                'success' => true,
                'total' => count($resultados),
                'resultados' => $resultados,
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // ACCIONES SOBRE COMPROBANTES
    // =========================================================================

    /**
     * Reintenta el envío de un comprobante rechazado
     */
    public function reintentar(): void
    {
        header('Content-Type: application/json');
        $this->requireSunatSender();

        $ventaId = (int)($_POST['venta_id'] ?? 0);
        if (!$ventaId) {
            echo json_encode(['success' => false, 'message' => 'ID de venta requerido.']);
            return;
        }

        try {
            // Resetear estado
            $stmt = $this->db->prepare("
                UPDATE comprobantes_electronicos
                SET estado_sunat = 'pendiente',
                    intentos_envio = 0,
                    mensaje_sunat = NULL,
                    codigo_respuesta = NULL,
                    updated_at = NOW()
                WHERE venta_id = :venta_id
            ");
            $stmt->execute([':venta_id' => $ventaId]);

            // Reintentar envío
            $resultado = $this->procesarEnvio($ventaId);
            echo json_encode($resultado);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // INFORMACIÓN DEL CERTIFICADO
    // =========================================================================

    /**
     * Obtiene información del certificado digital
     */
    public function certInfo(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        $certPath = defined('SUNAT_CERT_PATH') ? SUNAT_CERT_PATH : sunatResolvePath($_ENV['SUNAT_CERT_PATH'] ?? 'storage/certificados/certificado.pem');
        $certPass = $_ENV['SUNAT_CERT_PASS'] ?? '';

        try {
            $info = $this->xmlSigner->getCertInfo($certPath, $certPass);
            echo json_encode([
                'success' => !isset($info['error']),
                'data' => $info,
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prueba la conexión con SUNAT
     */
    public function testConnection(): void
    {
        header('Content-Type: application/json');
        $this->requireAdmin();

        $resultado = $this->sunatService->testConnection();
        echo json_encode($resultado);
    }

    // =========================================================================
    // MÉTODOS PRIVADOS
    // =========================================================================

    /**
     * Procesa el envío completo de un comprobante: Genera XML → Firma → Envía
     *
     * @param int $ventaId ID de la venta
     * @return array Resultado del envío
     */
    private function procesarEnvio(int $ventaId): array
    {
        // 1. Obtener datos de la venta
        $saleModel = new SaleModel($this->db);
        $venta = $saleModel->getById($ventaId);

        if (!$venta) {
            return ['success' => false, 'message' => 'Venta no encontrada.'];
        }

        if ($venta['estado'] === 'anulada') {
            return ['success' => false, 'message' => 'La venta está anulada.'];
        }

        // 2. Obtener datos de la empresa
        $companyData = $this->getEmpresaConfig();

        // 3. Determinar tipo de comprobante
        $tipo = $venta['tipo_comprobante'];
        $serie = $venta['serie'];
        $numero = (int)$venta['numero'];

        // 4. Generar XML según tipo
        try {
            if ($tipo === '01') {
                $xmlContent = $this->xmlGenerator->generateFactura($venta);
            } elseif ($tipo === '03') {
                $xmlContent = $this->xmlGenerator->generateBoleta($venta);
            } else {
                return ['success' => false, 'message' => "Tipo de comprobante {$tipo} no soportado para envío individual."];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error al generar XML: ' . $e->getMessage()];
        }

        // 5. Guardar XML crudo para depuración y luego firmar digitalmente
        $filename = $this->sunatService->generateFilename($tipo, $serie, $numero);
        try {
            $this->xmlGenerator->saveRawXml($xmlContent, $filename);
        } catch (\Throwable $e) {
            // No detener el flujo si falla el guardado de depuración
        }

        // Firmar XML
        $certPath = defined('SUNAT_CERT_PATH') ? SUNAT_CERT_PATH : sunatResolvePath($_ENV['SUNAT_CERT_PATH'] ?? 'storage/certificados/certificado.pem');
        $certPass = $_ENV['SUNAT_CERT_PASS'] ?? '';

        try {
            $xmlFirmado = $this->xmlSigner->sign($xmlContent, $certPath, $certPass);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al firmar XML: ' . $e->getMessage(),
                'codigo'  => 'SIGNATURE_ERROR',
            ];
        }
        // 6. Enviar a SUNAT
        $resultado = $this->sunatService->sendComprobante($xmlFirmado, $filename);

        // 7. Actualizar estado en base de datos
        $estadoSunat = $resultado['success'] ? 'aceptado' : 'rechazado';
        $codigo = $resultado['codigo'] ?? null;
        $mensaje = $resultado['message'] ?? null;
        $cdrPath = $resultado['cdr_path'] ?? null;

        $stmt = $this->db->prepare("
            INSERT INTO comprobantes_electronicos
                (venta_id, tipo_comprobante, serie, numero, fecha_emision,
                 hash_cpe, xml_path, estado_sunat, mensaje_sunat, codigo_respuesta,
                 cdr_path, fecha_envio, intentos_envio, created_at, updated_at)
            VALUES
                (:venta_id, :tipo, :serie, :numero, :fecha,
                 :hash, :xml_path, :estado, :mensaje, :codigo,
                 :cdr_path, NOW(), 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                estado_sunat = VALUES(estado_sunat),
                mensaje_sunat = VALUES(mensaje_sunat),
                codigo_respuesta = VALUES(codigo_respuesta),
                cdr_path = VALUES(cdr_path),
                fecha_envio = NOW(),
                intentos_envio = intentos_envio + 1,
                updated_at = NOW()
        ");

        $xmlSavedPath = STORAGE_PATH . '/xml/' . $filename . '.xml';
        $hash = md5($xmlFirmado);

        $stmt->execute([
            ':venta_id' => $ventaId,
            ':tipo' => $tipo,
            ':serie' => $serie,
            ':numero' => str_pad((string)$numero, 8, '0', STR_PAD_LEFT),
            ':fecha' => $venta['fecha_emision'] ?? date('Y-m-d'),
            ':hash' => $hash,
            ':xml_path' => $xmlSavedPath,
            ':estado' => $estadoSunat,
            ':mensaje' => $mensaje,
            ':codigo' => $codigo,
            ':cdr_path' => $cdrPath,
        ]);

        return $resultado;
    }

    /**
     * Obtiene la configuración de la empresa
     */
    private function getEmpresaConfig(): array
    {
        try {
            $sql = "SELECT clave, valor FROM configuracion WHERE clave LIKE 'empresa_%'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $config = [];
            foreach ($rows as $row) {
                $key = str_replace('empresa_', '', $row['clave']);
                $config[$key] = $row['valor'];
            }
            return $config;
        } catch (\Exception $e) {
            return [
                'razon_social' => $_ENV['EMPRESA_NOMBRE'] ?? 'EMPRESA DE INFORMÁTICA',
                'ruc' => $_ENV['EMPRESA_RUC'] ?? '20123456789',
                'direccion' => $_ENV['EMPRESA_DIR'] ?? 'Pucallpa - Ucayali - Perú',
                'telefono' => $_ENV['EMPRESA_TEL'] ?? '',
                'email' => $_ENV['EMPRESA_EMAIL'] ?? '',
                'ubigeo' => '250001',
                'departamento' => 'Ucayali',
                'provincia' => 'Coronel Portillo',
                'distrito' => 'Calleria',
                'nombre_comercial' => $_ENV['EMPRESA_NOMBRE'] ?? 'EMPRESA DE INFORMÁTICA',
            ];
        }
    }

    /**
     * Verifica que el usuario actual sea administrador
     */
    private function requireAdmin(): void
    {
        if (empty($_SESSION['user_id']) || !isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
            exit;
        }
    }

    private function requireSunatViewer(): void
    {
        if (empty($_SESSION['user_id']) || (!isAdmin() && !isVendedor())) {
            redirect('/dashboard');
            exit;
        }
    }

    private function requireSunatSender(): void
    {
        if (empty($_SESSION['user_id']) || (!isAdmin() && !isVendedor())) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para enviar comprobantes a SUNAT.']);
            exit;
        }
    }
}

