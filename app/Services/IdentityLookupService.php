<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class IdentityLookupService
{
    private string $token;

    public function __construct()
    {
        $this->token = trim((string)($_ENV['DECOLECTA_TOKEN'] ?? ''));
    }

    public function lookup(string $tipoDocumento, string $numero): array
    {
        if ($this->token === '') {
            throw new RuntimeException('La consulta de documentos no está configurada.');
        }

        $endpoint = $tipoDocumento === '1' ? 'reniec/dni' : 'sunat/ruc';
        $url = 'https://api.decolecta.com/v1/' . $endpoint . '?numero=' . rawurlencode($numero);

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $error !== '') {
            throw new RuntimeException('No se pudo conectar con el servicio de documentos.');
        }

        $data = json_decode($response, true);
        if ($status !== 200 || !is_array($data)) {
            throw new RuntimeException($status === 401 || $status === 403
                ? 'El token de Decolecta no es válido o no tiene acceso.'
                : 'No se encontró información para el documento.');
        }

        return [
            'razon_social' => trim((string)($data['razon_social'] ?? $data['nombre_o_razon_social'] ?? '')),
            'nombre' => trim(implode(' ', array_filter([
                $data['first_name'] ?? $data['nombres'] ?? '',
                $data['first_last_name'] ?? $data['apellido_paterno'] ?? '',
                $data['second_last_name'] ?? $data['apellido_materno'] ?? '',
            ]))),
            'direccion' => trim((string)($data['direccion'] ?? '')),
        ];
    }
}
