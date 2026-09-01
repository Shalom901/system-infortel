<?php

declare(strict_types=1);

namespace App\Services;

use Greenter\Ws\Services\SoapClient;
use Greenter\Ws\Services\BillSender;
use Greenter\Ws\Services\SummarySender;
use Greenter\Ws\Services\ExtService;
use Greenter\Ws\Services\WsdlProvider;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\SummaryResult;
use Greenter\Model\Response\CdrResponse;
use Greenter\Zip\ZipFly;
use Greenter\Validator\XmlErrorCodeProvider;
use RuntimeException;

/**
 * Servicio de envío a SUNAT usando Greenter SOAP
 *
 * Implementa el envío real de comprobantes electrónicos a SUNAT
 * mediante servicios web SOAP, tanto síncronos (factura, NC, ND)
 * como asíncronos (resumen diario de boletas, comunicación de baja).
 *
 * @package App\Services
 */
class SunatService
{
    private string $ruc;
    private string $usuarioSol;
    private string $claveSol;
    private string $modo;
    private string $certPath;
    private string $certPass;
    private ?SoapClient $soapClient = null;

    // Directorios de almacenamiento
    private string $xmlDir;
    private string $cdrDir;

    public function __construct()
    {
        $this->ruc       = $_ENV['SUNAT_RUC']         ?? '';
        $this->usuarioSol = $_ENV['SUNAT_USUARIO_SOL'] ?? 'MODDATOS';
        // Aceptar tanto 'USUARIO' como 'RUCUSUARIO'. Si el valor proporcionado
        // no empieza con el RUC configurado, anteponer el RUC para formar
        // el usuario SOL en el formato esperado por SUNAT (RUC+USUARIO).
        if (!empty($this->ruc) && !empty($this->usuarioSol) && strpos($this->usuarioSol, $this->ruc) !== 0) {
            $this->usuarioSol = $this->ruc . $this->usuarioSol;
        }
        $this->claveSol  = $_ENV['SUNAT_CLAVE_SOL']   ?? 'moddatos';
        $this->modo      = $_ENV['SUNAT_MODO']        ?? 'beta';
        $this->certPath  = defined('SUNAT_CERT_PATH') ? SUNAT_CERT_PATH : sunatResolvePath($_ENV['SUNAT_CERT_PATH'] ?? 'storage/certificados/certificado.pem');
        $this->certPass  = $_ENV['SUNAT_CERT_PASS']   ?? '';

        $this->xmlDir = STORAGE_PATH . '/xml';
        $this->cdrDir = STORAGE_PATH . '/cdr';

        // Crear directorios si no existen
        foreach ([$this->xmlDir, $this->cdrDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Envía un comprobante electrónico (Factura/Boleta/NC/ND) a SUNAT
     * Método síncrono: envía y recibe CDR en la misma llamada.
     *
     * @param string $xmlContent XML firmado del comprobante
     * @param string $filename   Nombre del archivo (RUC-TIPO-SERIE-CORRELATIVO)
     * @return array Resultado del envío
     */
    public function sendComprobante(string $xmlContent, string $filename): array
    {
        try {
            // Guardar XML firmado antes del envío (copia para diagnóstico)
            $time = date('Ymd_His');
            $signedCopy = $this->xmlDir . '/SIGNED-' . $filename . '-' . $time . '.xml';
            file_put_contents($signedCopy, $xmlContent);

            // Guardar también con el nombre estándar (esto es el archivo enviado)
            $xmlPath = $this->xmlDir . '/' . $filename . '.xml';
            file_put_contents($xmlPath, $xmlContent);

            // Configurar cliente y sender
            $client = $this->getSoapClient();
            $sender = new BillSender();
            $sender->setClient($client);
            $sender->setCodeProvider(new XmlErrorCodeProvider());

            // Enviar a SUNAT
            /** @var BillResult $result */
            $result = $sender->send($filename, $xmlContent);

            if ($result === null) {
                return [
                    'success' => false,
                    'message' => 'No se obtuvo respuesta de SUNAT (timeout)',
                    'codigo'  => 'TIMEOUT',
                    'filename' => $filename,
                ];
            }

            if (!$result->isSuccess()) {
                $error = $result->getError();

                // Guardar XML enviado y detalles de error para depuración
                try {
                    $logDir = STORAGE_PATH . '/logs';
                    if (!is_dir($logDir)) {
                        mkdir($logDir, 0755, true);
                    }
                    $time = date('Ymd_His');
                    $rejectedPath = $this->xmlDir . '/REJECTED-' . $filename . '-' . $time . '.xml';
                    file_put_contents($rejectedPath, $xmlContent);

                    $logMsg = date('c') . " - Rechazo SUNAT - archivo={$filename} - codigo=" . ($error ? $error->getCode() : 'UNKNOWN') . " - mensaje=" . ($error ? $error->getMessage() : 'Error desconocido') . " - xml=" . basename($rejectedPath) . "\n";
                    file_put_contents($logDir . '/sunat_errors.log', $logMsg, FILE_APPEND | LOCK_EX);
                } catch (\Throwable $e) {
                    // no bloquear por errores de logging
                }

                return [
                    'success' => false,
                    'message' => $error ? $error->getMessage() : 'Error desconocido de SUNAT',
                    'codigo'  => $error ? $error->getCode() : 'UNKNOWN',
                    'filename' => $filename,
                ];
            }

            // Procesar CDR
            $cdrResponse = $result->getCdrResponse();
            $cdrZip = $result->getCdrZip();

            $cdrPath = null;
            if ($cdrZip) {
                $cdrPath = $this->cdrDir . '/R-' . $filename . '.zip';
                file_put_contents($cdrPath, $cdrZip);
            }

            $codigoRespuesta = $cdrResponse ? $cdrResponse->getCode() : '0000';
            $mensaje = $cdrResponse ? $cdrResponse->getDescription() : 'Aceptado por SUNAT';

            return [
                'success'          => true,
                'codigo'           => $codigoRespuesta,
                'message'          => $mensaje,
                'cdr_path'         => $cdrPath,
                'cdr_response'     => $this->parseCdrResponse($cdrResponse),
                'filename'         => $filename,
            ];

        } catch (\SoapFault $e) {
            return [
                'success' => false,
                'message' => 'Error SOAP: ' . $e->getMessage(),
                'codigo'  => $e->faultcode ?? 'SOAP_FAULT',
                'filename' => $filename,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
                'codigo'  => 'INTERNAL_ERROR',
                'filename' => $filename,
            ];
        }
    }

    /**
     * Envía un resumen diario de boletas (RC) a SUNAT
     * Método asíncrono: retorna un ticket para consultar después.
     *
     * @param string $xmlContent XML del resumen diario
     * @param string $filename   Nombre del archivo
     * @return array Resultado con ticket para consulta
     */
    public function sendResumenDiario(string $xmlContent, string $filename): array
    {
        try {
            $xmlPath = $this->xmlDir . '/' . $filename . '.xml';
            file_put_contents($xmlPath, $xmlContent);

            $client = $this->getSoapClient();
            $sender = new SummarySender();
            $sender->setClient($client);

            /** @var SummaryResult $result */
            $result = $sender->send($filename, $xmlContent);

            if ($result === null) {
                return [
                    'success' => false,
                    'message' => 'No se obtuvo respuesta de SUNAT',
                    'codigo'  => 'TIMEOUT',
                ];
            }

            if (!$result->isSuccess()) {
                $error = $result->getError();
                return [
                    'success' => false,
                    'message' => $error ? $error->getMessage() : 'Error en resumen diario',
                    'codigo'  => $error ? $error->getCode() : 'UNKNOWN',
                ];
            }

            return [
                'success' => true,
                'ticket'  => $result->getTicket(),
                'message' => 'Resumen diario enviado correctamente. Ticket: ' . $result->getTicket(),
                'filename' => $filename,
            ];

        } catch (\SoapFault $e) {
            return [
                'success' => false,
                'message' => 'Error SOAP: ' . $e->getMessage(),
                'codigo'  => $e->faultcode ?? 'SOAP_FAULT',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
                'codigo'  => 'INTERNAL_ERROR',
            ];
        }
    }

    /**
     * Consulta el estado de un envío asíncrono mediante ticket
     *
     * @param string $ticket Número de ticket SUNAT
     * @return array Resultado de la consulta
     */
    public function consultarTicket(string $ticket): array
    {
        try {
            $client = $this->getSoapClient();
            $extService = new ExtService();
            $extService->setClient($client);

            $result = $extService->getStatus($ticket);

            if ($result === null) {
                return [
                    'success' => false,
                    'message' => 'No se pudo consultar el ticket',
                    'estado'  => 'ERROR',
                ];
            }

            if (!$result->isSuccess()) {
                $error = $result->getError();
                // Si el código es 98, el ticket sigue en proceso
                if ($error && $error->getCode() === '98') {
                    return [
                        'success' => true,
                        'message' => 'El comprobante está siendo procesado por SUNAT',
                        'estado'  => 'EN_PROCESO',
                        'codigo'  => '98',
                    ];
                }

                return [
                    'success' => false,
                    'message' => $error ? $error->getMessage() : 'Error al consultar ticket',
                    'estado'  => 'RECHAZADO',
                    'codigo'  => $error ? $error->getCode() : 'UNKNOWN',
                ];
            }

            // CDR recibido
            $cdrResponse = $result->getCdrResponse();
            $cdrZip = $result->getCdrZip();

            $cdrPath = null;
            if ($cdrZip) {
                $cdrPath = $this->cdrDir . '/TICKET-' . $ticket . '.zip';
                file_put_contents($cdrPath, $cdrZip);
            }

            return [
                'success'      => true,
                'message'      => $cdrResponse ? $cdrResponse->getDescription() : 'Comprobante aceptado',
                'estado'       => 'ACEPTADO',
                'codigo'       => $cdrResponse ? $cdrResponse->getCode() : '0000',
                'cdr_path'     => $cdrPath,
                'cdr_response' => $this->parseCdrResponse($cdrResponse),
            ];

        } catch (\SoapFault $e) {
            return [
                'success' => false,
                'message' => 'Error SOAP: ' . $e->getMessage(),
                'codigo'  => $e->faultcode ?? 'SOAP_FAULT',
                'estado'  => 'ERROR',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
                'codigo'  => 'INTERNAL_ERROR',
                'estado'  => 'ERROR',
            ];
        }
    }

    /**
     * Envía una comunicación de baja (anulación ante SUNAT)
     *
     * @param string $xmlContent XML de comunicación de baja
     * @param string $filename   Nombre del archivo
     * @return array Resultado
     */
    public function sendComunicacionBaja(string $xmlContent, string $filename): array
    {
        try {
            $xmlPath = $this->xmlDir . '/' . $filename . '.xml';
            file_put_contents($xmlPath, $xmlContent);

            $client = $this->getSoapClient();
            $sender = new SummarySender();
            $sender->setClient($client);

            /** @var SummaryResult $result */
            $result = $sender->send($filename, $xmlContent);

            if ($result === null) {
                return [
                    'success' => false,
                    'message' => 'No se obtuvo respuesta de SUNAT',
                ];
            }

            if (!$result->isSuccess()) {
                $error = $result->getError();
                return [
                    'success' => false,
                    'message' => $error ? $error->getMessage() : 'Error en comunicación de baja',
                    'codigo'  => $error ? $error->getCode() : 'UNKNOWN',
                ];
            }

            return [
                'success' => true,
                'ticket'  => $result->getTicket(),
                'message' => 'Comunicación de baja enviada. Ticket: ' . $result->getTicket(),
            ];

        } catch (\SoapFault $e) {
            return [
                'success' => false,
                'message' => 'Error SOAP: ' . $e->getMessage(),
                'codigo'  => $e->faultcode ?? 'SOAP_FAULT',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
                'codigo'  => 'INTERNAL_ERROR',
            ];
        }
    }

    /**
     * Prueba la conexión con SUNAT
     *
     * @return array Resultado de la prueba
     */
    public function testConnection(): array
    {
        try {
            $endpoint = $this->modo === 'beta'
                ? 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService'
                : 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService';

            $client = new SoapClient(WsdlProvider::getBillPath(), [
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ]),
            ]);

            $client->setCredentials($this->usuarioSol, $this->claveSol);
            $client->setService($endpoint);

            // Hacer un llamado simple para probar conexión
            $result = $client->call('getStatus', ['parameters' => [
                'rucComprobante'  => $this->ruc,
                'ticket'          => 'TEST-CONNECTION'
            ]]);

            return [
                'success'  => true,
                'message'  => 'Conexión SOAP establecida correctamente con SUNAT (' . $this->modo . ')',
                'endpoint' => $endpoint,
            ];

        } catch (\SoapFault $e) {
            // Se espera error porque el ticket no es válido, pero la conexión funciona
            if (str_contains($e->getMessage(), 'El ticket no existe') ||
                str_contains($e->getMessage(), 'El ticket')) {
                return [
                    'success'  => true,
                    'message'  => 'Conexión SOAP establecida correctamente con SUNAT (' . $this->modo . ')',
                    'endpoint' => $endpoint,
                ];
            }

            return [
                'success'  => false,
                'message'  => 'Error de conexión: ' . $e->getMessage(),
                'endpoint' => $endpoint,
            ];
        } catch (\Exception $e) {
            return [
                'success'  => false,
                'message'  => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Genera el nombre de archivo estándar SUNAT
     * Formato: RUC-TIPO-SERIE-CORRELATIVO
     *
     * @param string $tipo Tipo de comprobante
     * @param string $serie Serie
     * @param int $numero Correlativo
     * @return string Nombre de archivo
     */
    public function generateFilename(string $tipo, string $serie, int $numero): string
    {
        return $this->ruc . '-' . $tipo . '-' . $serie . '-' . str_pad((string)$numero, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene o crea el cliente SOAP
     */
    private function getSoapClient(): SoapClient
    {
        if ($this->soapClient !== null) {
            return $this->soapClient;
        }

        $endpoint = $this->getEndpoint();

        $this->soapClient = new SoapClient(WsdlProvider::getBillPath(), [
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ]),
        ]);

        $this->soapClient->setCredentials($this->usuarioSol, $this->claveSol);
        $this->soapClient->setService($endpoint);

        return $this->soapClient;
    }

    /**
     * Obtiene el endpoint según el modo
     */
    private function getEndpoint(): string
    {
        if ($this->modo === 'beta') {
            return $_ENV['SUNAT_WS_FACTURA_BETA'] ?? 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService';
        }
        return $_ENV['SUNAT_WS_FACTURA_PROD'] ?? 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService';
    }

    /**
     * Parsea el objeto CdrResponse a un array simple
     */
    private function parseCdrResponse(?CdrResponse $cdr): ?array
    {
        if ($cdr === null) {
            return null;
        }

        return [
            'id'          => $cdr->getId(),
            'code'        => $cdr->getCode(),
            'description' => $cdr->getDescription(),
            'notes'       => $cdr->getNotes(),
        ];
    }
}

