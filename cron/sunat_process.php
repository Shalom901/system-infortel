<?php

declare(strict_types=1);

/**
 * =============================================================
 * PROCESADOR DE COLA SUNAT - CRON
 * Sistema de Facturación Electrónica - Pucallpa
 * =============================================================
 *
 * Procesa los comprobantes electrónicos pendientes de envío
 * a SUNAT. Debe ejecutarse periódicamente vía cron:
 *
 *   * /5 * * * * php /ruta/a/cron/sunat_process.php
 *
 * Flujo:
 *   1. Obtiene registros de cola_sunat con estado 'pendiente'
 *   2. Para cada uno: genera XML, firma y envía a SUNAT
 *   3. Actualiza estado según respuesta
 *   4. Implementa backoff exponencial para reintentos
 *
 * @package App\Cron
 */

// ─── INICIALIZACIÓN ────────────────────────────────────────────────
define('CRON_MODE', true);
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\SunatService;
use App\Services\XmlGeneratorService;
use App\Services\XmlSignerService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// ─── ENTORNO ───────────────────────────────────────────────────────
$dotenv = Dotenv::createImmutable(ROOT_PATH);
$dotenv->safeLoad();

require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/config/app.php';

// ─── LOGGER ────────────────────────────────────────────────────────
$logDir = ROOT_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logger = new Logger('sunat_cron');
$logger->pushHandler(new StreamHandler($logDir . '/sunat_cron.log', Logger::INFO));

$logger->info('===== INICIO PROCESAMIENTO COLA SUNAT =====');

// ─── BASE DE DATOS ────────────────────────────────────────────────
global $db;
if (!isset($db) || !$db instanceof PDO) {
    $logger->error('No se pudo conectar a la base de datos');
    exit(1);
}

try {
    // ─── SERVICIOS ────────────────────────────────────────────────
    $sunatService   = new SunatService();
    $xmlGenerator   = new XmlGeneratorService();
    $xmlSigner      = new XmlSignerService();

    $certPath  = defined('SUNAT_CERT_PATH') ? SUNAT_CERT_PATH : sunatResolvePath($_ENV['SUNAT_CERT_PATH'] ?? 'storage/certificados/certificado.pem');
    $certPass  = $_ENV['SUNAT_CERT_PASS'] ?? '';

    // ─── 1. OBTENER PENDIENTES DE COLA ────────────────────────────
    $stmt = $db->prepare("
        SELECT
            cs.id          AS cola_id,
            cs.comprobante_id,
            cs.tipo_operacion,
            cs.intentos,
            cs.max_intentos,
            cs.prioridad,
            ce.id          AS cpe_id,
            ce.venta_id,
            ce.tipo_comprobante,
            ce.serie,
            ce.numero,
            ce.estado_sunat,
            ce.intentos_envio,
            ce.xml_path
        FROM cola_sunat cs
        INNER JOIN comprobantes_electronicos ce ON ce.id = cs.comprobante_id
        WHERE cs.estado = 'pendiente'
          AND (cs.proximo_intento IS NULL OR cs.proximo_intento <= NOW())
        ORDER BY cs.prioridad ASC, cs.created_at ASC
        LIMIT 5
    ");
    $stmt->execute();
    $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pendientes)) {
        $logger->info('No hay comprobantes pendientes en cola.');
        echo "No hay comprobantes pendientes.\n";
        exit(0);
    }

    $logger->info('Procesando ' . count($pendientes) . ' comprobante(s) de la cola.');
    echo "Procesando " . count($pendientes) . " comprobante(s)...\n";

    $procesados   = 0;
    $exitosos     = 0;
    $fallidos     = 0;

    foreach ($pendientes as $item) {
        $colaId           = (int)$item['cola_id'];
        $comprobanteId    = (int)$item['comprobante_id'];
        $ventaId          = (int)$item['venta_id'];
        $tipoComprobante  = $item['tipo_comprobante'];
        $serie            = $item['serie'];
        $numero           = $item['numero'];
        $intentos         = (int)$item['intentos'];
        $maxIntentos      = (int)$item['max_intentos'];

        $procesados++;
        $logger->info("Procesando [{$procesados}/" . count($pendientes) . "]: {$tipoComprobante}-{$serie}-{$numero} (cola_id={$colaId})");
        echo "  → {$tipoComprobante}-{$serie}-{$numero}... ";

        try {
            // ─── MARCAR COMO PROCESANDO ───────────────────────────
            $stmtUpd = $db->prepare("
                UPDATE cola_sunat
                SET estado = 'procesando', ultimo_intento = NOW()
                WHERE id = :id
            ");
            $stmtUpd->execute([':id' => $colaId]);

            // ─── 2. OBTENER DATOS DE VENTA ────────────────────────
            $stmtVenta = $db->prepare("
                SELECT v.*, c.razon_social AS cliente_nombre,
                       c.tipo_doc AS cliente_tipo_doc,
                       c.numero_doc AS cliente_numero_doc,
                       c.direccion AS cliente_direccion
                FROM ventas v
                LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE v.id = :id
            ");
            $stmtVenta->execute([':id' => $ventaId]);
            $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

            if (!$venta) {
                throw new \RuntimeException("Venta #{$ventaId} no encontrada");
            }

            // Obtener items
            $stmtItems = $db->prepare("
                SELECT dv.*, p.codigo_interno, p.codigo_barras
                FROM detalle_ventas dv
                LEFT JOIN productos p ON dv.producto_id = p.id
                WHERE dv.venta_id = :venta_id
                ORDER BY dv.id ASC
            ");
            $stmtItems->execute([':venta_id' => $ventaId]);
            $venta['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            // Obtener pagos
            $stmtPagos = $db->prepare("
                SELECT * FROM pagos_venta WHERE venta_id = :venta_id ORDER BY id ASC
            ");
            $stmtPagos->execute([':venta_id' => $ventaId]);
            $venta['pagos'] = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

            // ─── 3. GENERAR XML ───────────────────────────────────
            if ($tipoComprobante === '01') {
                $xmlContent = $xmlGenerator->generateFactura($venta);
            } elseif ($tipoComprobante === '03') {
                $xmlContent = $xmlGenerator->generateBoleta($venta);
            } else {
                throw new \RuntimeException("Tipo {$tipoComprobante} no soportado");
            }

            // ─── 4. FIRMAR XML ────────────────────────────────────
            try {
                $xmlFirmado = $xmlSigner->sign($xmlContent, $certPath, $certPass);
            } catch (\Exception $e) {
                $logger->warning("No se pudo firmar XML: " . $e->getMessage());
                $xmlFirmado = $xmlContent; // Continuar sin firma (para pruebas)
            }

            // ─── 5. ENVIAR A SUNAT ────────────────────────────────
            $filename = $sunatService->generateFilename($tipoComprobante, $serie, (int)$numero);
            $resultado = $sunatService->sendComprobante($xmlFirmado, $filename);

            // ─── 6. ACTUALIZAR ESTADOS ────────────────────────────
            $hash = md5($xmlFirmado);
            $xmlSavedPath = STORAGE_PATH . '/xml/' . $filename . '.xml';

            if ($resultado['success']) {
                // ÉXITO
                $exitosos++;
                $logger->info("✓ Envío exitoso: {$tipoComprobante}-{$serie}-{$numero}");

                $stmtUpdCpe = $db->prepare("
                    UPDATE comprobantes_electronicos
                    SET estado_sunat = 'aceptado',
                        hash_cpe = :hash,
                        xml_path = :xml_path,
                        cdr_path = :cdr_path,
                        mensaje_sunat = :mensaje,
                        codigo_respuesta = :codigo,
                        fecha_envio = NOW(),
                        intentos_envio = intentos_envio + 1,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtUpdCpe->execute([
                    ':id'        => $comprobanteId,
                    ':hash'      => $hash,
                    ':xml_path'  => $xmlSavedPath,
                    ':cdr_path'  => $resultado['cdr_path'] ?? null,
                    ':mensaje'   => $resultado['message'],
                    ':codigo'    => $resultado['codigo'],
                ]);

                $stmtUpdCola = $db->prepare("
                    UPDATE cola_sunat
                    SET estado = 'completado',
                        intentos = intentos + 1,
                        ultimo_intento = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtUpdCola->execute([':id' => $colaId]);

                echo "✓ ACEPTADO ({$resultado['codigo']})\n";

            } else {
                // FALLO
                $nuevosIntentos = $intentos + 1;
                $logger->warning("✗ Error en envío: {$resultado['message']}");

                $stmtUpdCpe = $db->prepare("
                    UPDATE comprobantes_electronicos
                    SET estado_sunat = CASE WHEN :intentos >= max_intentos THEN 'rechazado' ELSE 'pendiente' END,
                        mensaje_sunat = :mensaje,
                        codigo_respuesta = :codigo,
                        intentos_envio = intentos_envio + 1,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtUpdCpe->execute([
                    ':id'       => $comprobanteId,
                    ':intentos' => $nuevosIntentos,
                    ':mensaje'  => $resultado['message'],
                    ':codigo'   => $resultado['codigo'],
                ]);

                if ($nuevosIntentos >= $maxIntentos) {
                    // Error permanente
                    $stmtUpdCola = $db->prepare("
                        UPDATE cola_sunat
                        SET estado = 'error_permanente',
                            intentos = intentos + 1,
                            ultimo_intento = NOW(),
                            error_mensaje = :error,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmtUpdCola->execute([
                        ':id'    => $colaId,
                        ':error' => $resultado['message'],
                    ]);
                    $fallidos++;
                    echo "✗ ERROR PERMANENTE: {$resultado['message']}\n";
                } else {
                    // Error temporal - backoff exponencial
                    $backoffSeconds = min(pow(30, $nuevosIntentos), 3600); // 30s, 900s, 27000s -> max 1h
                    $proximoIntento = date('Y-m-d H:i:s', time() + $backoffSeconds);

                    $stmtUpdCola = $db->prepare("
                        UPDATE cola_sunat
                        SET estado = 'error_temporal',
                            intentos = intentos + 1,
                            ultimo_intento = NOW(),
                            proximo_intento = :proximo,
                            error_mensaje = :error,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmtUpdCola->execute([
                        ':id'      => $colaId,
                        ':proximo' => $proximoIntento,
                        ':error'   => $resultado['message'],
                    ]);
                    $logger->info("Reintento #{$nuevosIntentos} programado para: {$proximoIntento}");
                    $fallidos++;
                    echo "✗ ERROR TEMPORAL (reintento #{$nuevosIntentos} en {$backoffSeconds}s): {$resultado['message']}\n";
                }
            }

        } catch (\Exception $e) {
            $logger->error("Error procesando cola_id={$colaId}: " . $e->getMessage());

            // Marcar error en cola
            try {
                $stmtUpdCola = $db->prepare("
                    UPDATE cola_sunat
                    SET estado = 'error_temporal',
                        intentos = intentos + 1,
                        ultimo_intento = NOW(),
                        error_mensaje = :error,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtUpdCola->execute([
                    ':id'    => $colaId,
                    ':error' => $e->getMessage(),
                ]);
            } catch (\Exception $e2) {
                $logger->error("Error adicional al actualizar cola: " . $e2->getMessage());
            }

            $fallidos++;
            echo "✗ ERROR: {$e->getMessage()}\n";
        }
    }

    // ─── RESUMEN ──────────────────────────────────────────────────
    $logger->info("===== FIN PROCESAMIENTO: {$exitosos} exitosos, {$fallidos} fallidos (de {$procesados}) =====");
    echo "\nResumen: {$exitosos} exitosos, {$fallidos} fallidos (de {$procesados})\n";

} catch (\Exception $e) {
    $logger->critical('Error general en cron: ' . $e->getMessage());
    echo 'Error crítico: ' . $e->getMessage() . "\n";
    exit(1);
}

