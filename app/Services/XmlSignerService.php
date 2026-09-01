<?php

declare(strict_types=1);

/**
 * Servicio de Firma Digital XML para SUNAT
 *
 * Implementa la especificación XMLDSig (XML Digital Signature) para firmar
 * los comprobantes electrónicos UBL 2.1 según los requerimientos de SUNAT Perú.
 *
 * El proceso de firma delega en el motor XMLDSig de Greenter (`Greenter\XMLSecLibs\Sunat\SignedXml`),
 * una implementación probada y compatible con SUNAT que asegura:
 *  1. Canonicalización C14N (XML-C14N 1.0).
 *  2. Transform `enveloped-signature` (protege el contenido excluyendo la firma).
 *  3. Algoritmos RSA-SHA1 / SHA-1 exigidos por SUNAT.
 *  4. Colocación correcta del nodo <ds:Signature> dentro de ext:ExtensionContent.
 *
 * Esto corrige el error "El documento electrónico ingresado ha sido alterado", que
 * se producía al calcular el digest sobre el documento completo (incluyendo el nodo
 * donde luego se insertaba la firma), provocando que el digest no coincidiera con el
 * contenido final enviado.
 *
 * @package App\Services
 * @author  Sistema de Facturación Pucallpa
 * @version 2.0.0
 */

namespace App\Services;

use DOMDocument;
use RuntimeException;
use Exception;
use Greenter\XMLSecLibs\Sunat\SignedXml;

class XmlSignerService
{
    /** @var string|null Último error producido */
    private ?string $ultimoError = null;

    // =========================================================================
    // MÉTODO PRINCIPAL DE FIRMA
    // =========================================================================

    /**
     * Firma digitalmente un XML UBL 2.1 con el certificado proporcionado.
     *
     * @param string $xmlContent   Contenido XML a firmar (string)
     * @param string $certPath     Ruta absoluta al archivo .pfx / .p12 / .pem
     * @param string $certPassword Contraseña del certificado
     * @return string XML firmado como string
     * @throws RuntimeException Si ocurre algún error durante el proceso
     */
    public function sign(string $xmlContent, string $certPath, string $certPassword): string
    {
        // 1. Validar que el archivo del certificado existe
        if (!file_exists($certPath)) {
            throw new RuntimeException("Certificado digital no encontrado en: {$certPath}");
        }

        // 2. Cargar el certificado y obtener el PEM combinado
        //    (clave privada + certificado) listo para Greenter SignedXml
        $combinedPem = $this->loadCertificate($certPath, $certPassword);

        // 3. Validar que el XML se pueda parsear
        $doc = new DOMDocument('1.0', 'UTF-8');
        if (!$doc->loadXML($xmlContent)) {
            throw new RuntimeException('El XML proporcionado no es válido y no puede ser parseado.');
        }

        // 4. Firmar con el motor XMLDSig oficial de Greenter
        try {
            $signer = new SignedXml();
            $signer->setCertificate($combinedPem);

            $xmlFirmado = $signer->signXml($xmlContent);
        } catch (Exception $e) {
            $this->ultimoError = $e->getMessage();
            throw new RuntimeException('Error al firmar el XML: ' . $e->getMessage());
        }

        if ($xmlFirmado === false || $xmlFirmado === null) {
            throw new RuntimeException('No se pudo serializar el XML firmado.');
        }

        return $xmlFirmado;
    }

    /**
     * Extrae información del certificado para mostrar en la UI.
     *
     * @param string $certPath     Ruta al archivo .pfx
     * @param string $certPassword Contraseña del certificado
     * @return array Datos del certificado: CN, organización, vigencia, etc.
     */
    public function getCertInfo(string $certPath, string $certPassword): array
    {
        if (!file_exists($certPath)) {
            return ['error' => 'Certificado no encontrado'];
        }

        $pfxData = file_get_contents($certPath);

        $certs = [];
        if (!openssl_pkcs12_read($pfxData, $certs, $certPassword)) {
            return ['error' => 'No se pudo leer el certificado. Verifique la contraseña.'];
        }

        $certResource = openssl_x509_read($certs['cert']);
        if (!$certResource) {
            return ['error' => 'Certificado X.509 inválido'];
        }

        $info = openssl_x509_parse($certResource);

        return [
            'cn'            => $info['subject']['CN']  ?? 'Desconocido',
            'organizacion'  => $info['subject']['O']   ?? 'Desconocido',
            'pais'          => $info['subject']['C']   ?? 'PE',
            'emisor_cn'     => $info['issuer']['CN']   ?? 'Desconocido',
            'serie'         => $info['serialNumberHex'] ?? $info['serialNumber'] ?? '',
            'valido_desde'  => date('d/m/Y', $info['validFrom_time_t'] ?? 0),
            'valido_hasta'  => date('d/m/Y', $info['validTo_time_t']   ?? 0),
            'vigente'       => ($info['validTo_time_t'] ?? 0) > time(),
            'dias_restantes'=> max(0, (int)(($info['validTo_time_t'] ?? 0 - time()) / 86400)),
        ];
    }

    /**
     * Retorna el último mensaje de error generado.
     */
    public function getUltimoError(): ?string
    {
        return $this->ultimoError;
    }

    // =========================================================================
    // CARGA DEL CERTIFICADO
    // =========================================================================

    /**
     * Lee el archivo .pfx/.p12/.pem y retorna el PEM combinado
     * (clave privada + certificado) listo para Greenter SignedXml.
     *
     * @param string $certPath     Ruta al archivo
     * @param string $certPassword Contraseña del .pfx
     * @return string PEM combinado (clave privada + certificado)
     * @throws RuntimeException
     */
    private function loadCertificate(string $certPath, string $certPassword): string
    {
        $certData = file_get_contents($certPath);

        if ($certData === false) {
            throw new RuntimeException('No se pudo leer el archivo de certificado.');
        }

        if (str_contains($certData, '-----BEGIN')) {
            // El archivo ya es texto PEM (clave privada + certificado)
            return $this->loadPemCertificate($certData, $certPassword);
        }

        // Es un contenedor binario PKCS#12 (.pfx / .p12)
        $certs = [];
        if (!openssl_pkcs12_read($certData, $certs, $certPassword)) {
            $opensslError = openssl_error_string();
            throw new RuntimeException(
                "No se pudo leer el certificado PKCS#12. " .
                "Verifique que la contraseña sea correcta. " .
                ($opensslError ? "OpenSSL: {$opensslError}" : '')
            );
        }

        if (empty($certs['pkey']) || empty($certs['cert'])) {
            throw new RuntimeException('El archivo .pfx no contiene una clave privada o certificado válido.');
        }

        // Exportar la clave privada del .pfx a PEM
        $privateKey = openssl_pkey_get_private($certs['pkey'], $certPassword);
        if (!$privateKey) {
            throw new RuntimeException('No se pudo extraer la clave privada del certificado PKCS#12.');
        }

        $privatePem = '';
        if (!openssl_pkey_export($privateKey, $privatePem)) {
            $err = openssl_error_string() ?: 'desconocido';
            throw new RuntimeException("No se pudo exportar la clave privada a PEM. OpenSSL: {$err}");
        }

        // Combinar clave privada + certificado
        return $privatePem . $certs['cert'];
    }

    /**
     * Carga el PEM combinado de un archivo PEM.
     *
     * @param string $pemData Contenido del archivo PEM
     * @return string PEM combinado (clave privada + certificado)
     * @throws RuntimeException
     */
    private function loadPemCertificate(string $pemData, string $certPassword): string
    {
        $privateKeyPattern = '/(-----BEGIN (?:ENCRYPTED )?(?:RSA )?PRIVATE KEY-----.*?-----END (?:ENCRYPTED )?(?:RSA )?PRIVATE KEY-----)/s';
        if (!preg_match($privateKeyPattern, $pemData, $privateKeyMatches)) {
            throw new RuntimeException('No se encontró la clave privada en el archivo PEM.');
        }

        $certPattern = '/(-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----)/s';
        if (!preg_match($certPattern, $pemData, $certMatches)) {
            throw new RuntimeException('No se encontró el certificado X.509 en el archivo PEM.');
        }

        $privateKeyPem = $privateKeyMatches[1];

        // Validar que la clave privada se pueda cargar
        $privateKey = openssl_pkey_get_private($privateKeyPem, $certPassword ?: null);
        if (!$privateKey) {
            $opensslError = openssl_error_string();
            throw new RuntimeException(
                'No se pudo extraer la clave privada del archivo PEM. ' .
                ($opensslError ? "OpenSSL: {$opensslError}" : '')
            );
        }

        // Retornar el PEM completo del archivo (clave privada + certificados)
        return $pemData;
    }
}
