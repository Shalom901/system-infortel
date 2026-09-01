<?php

declare(strict_types=1);

/**
 * =============================================================
 * CONFIGURACIÓN SUNAT - FACTURACIÓN ELECTRÓNICA PERÚ
 * Sistema de Facturación - Empresa de Informática Pucallpa
 * =============================================================
 *
 * Define URLs de endpoints, códigos de tributos, tipos de
 * afectación IGV y versiones UBL para integración con SUNAT.
 *
 * Referencia: Resolución de Superintendencia N° 097-2012/SUNAT
 * y modificatorias para Facturación Electrónica 2.X
 */

// -----------------------------------------------
// MODO DE OPERACIÓN SUNAT
// -----------------------------------------------
define('SUNAT_MODO',       $_ENV['SUNAT_MODO']        ?? 'beta');
define('SUNAT_RUC',        $_ENV['SUNAT_RUC']         ?? '');
define('SUNAT_USUARIO',    $_ENV['SUNAT_USUARIO_SOL'] ?? 'MODDATOS');
define('SUNAT_CLAVE',      $_ENV['SUNAT_CLAVE_SOL']   ?? 'moddatos');
define('SUNAT_CERT_PASS',  $_ENV['SUNAT_CERT_PASS']   ?? '');

// -----------------------------------------------
// ENDPOINTS SUNAT - AMBIENTE BETA (PRUEBAS)
// -----------------------------------------------
define('SUNAT_ENDPOINTS_BETA', [
    // Servicio de envío de comprobantes electrónicos (Factura / NC / ND)
    'factura'            => 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
    // Servicio de envío de boletas y resúmenes diarios
    'boleta'             => 'https://e-beta.sunat.gob.pe/ol-ti-itcpgem-beta/billService',
    // Servicio de comunicación de bajas (voiding)
    'baja'               => 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
    // Servicio de resúmenes diarios de boletas
    'resumen'            => 'https://e-beta.sunat.gob.pe/ol-ti-itcpgem-beta/billService',
    // Consulta de CDR (Constancia de Recepción)
    'consulta_cdr'       => 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
    // Servicio OSE para nota de crédito/débito en boletas
    'ose_boleta'         => 'https://e-beta.sunat.gob.pe/ol-ti-itcpgem-beta/billService',
]);

// -----------------------------------------------
// ENDPOINTS SUNAT - AMBIENTE PRODUCCIÓN
// -----------------------------------------------
define('SUNAT_ENDPOINTS_PRODUCCION', [
    // Servicio de envío de comprobantes electrónicos (Factura / NC / ND)
    'factura'            => 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
    // Servicio de envío de boletas y resúmenes diarios
    'boleta'             => 'https://e-factura.sunat.gob.pe/ol-ti-itcpgem/billService',
    // Servicio de comunicación de bajas
    'baja'               => 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
    // Servicio de resúmenes diarios de boletas
    'resumen'            => 'https://e-factura.sunat.gob.pe/ol-ti-itcpgem/billService',
    // Consulta de CDR
    'consulta_cdr'       => 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
    // Servicio OSE para nota de crédito/débito en boletas
    'ose_boleta'         => 'https://e-factura.sunat.gob.pe/ol-ti-itcpgem/billService',
]);

/**
 * Obtener el endpoint SUNAT activo según el modo configurado.
 *
 * @param  string $servicio Clave del servicio: 'factura', 'boleta', 'baja', 'resumen', etc.
 * @return string           URL del endpoint
 */
function sunatResolvePath(string $path): string
{
    $path = trim($path);

    // Si ya es una ruta absoluta (Windows o Linux/Unix), devolverla tal cual.
    if (preg_match('#^(?:[A-Za-z]:[\\/]|\\\\|/)#', $path)) {
        return $path;
    }

    // Normalizar rutas relativas con o sin prefijo storage/
    $relative = preg_replace('#^storage[\\/]+#i', '', $path);
    $relative = trim($relative, '\\/');

    return ROOT_PATH . '/storage/' . str_replace(['\\', '/'], '/', $relative);
}

define('SUNAT_CERT_PATH',  sunatResolvePath($_ENV['SUNAT_CERT_PATH'] ?? 'storage/certificados/certificado.pem'));

function sunatEndpoint(string $servicio): string
{
    $endpoints = SUNAT_MODO === 'produccion'
        ? SUNAT_ENDPOINTS_PRODUCCION
        : SUNAT_ENDPOINTS_BETA;

    return $endpoints[$servicio] ?? throw new \InvalidArgumentException(
        "Servicio SUNAT '{$servicio}' no reconocido."
    );
}

// -----------------------------------------------
// CÓDIGOS DE TRIBUTOS (Tabla 05 SUNAT)
// -----------------------------------------------
define('TRIBUTOS_SUNAT', [
    // IGV - Impuesto General a las Ventas
    '1000' => [
        'codigo'      => '1000',
        'nombre'      => 'IGV',
        'descripcion' => 'Impuesto General a las Ventas',
        'tipo_codigo' => 'VAT',            // Código UN/ECE 5153
        'porcentaje'  => 18.00,
        'categoria'   => 'S',              // Estándar
    ],
    // ISC - Impuesto Selectivo al Consumo
    '2000' => [
        'codigo'      => '2000',
        'nombre'      => 'ISC',
        'descripcion' => 'Impuesto Selectivo al Consumo',
        'tipo_codigo' => 'EXC',
        'porcentaje'  => 0.00,             // Variable por producto
        'categoria'   => 'S',
    ],
    // ICBPER - Impuesto al Consumo de Bolsas Plásticas
    '7152' => [
        'codigo'      => '7152',
        'nombre'      => 'ICBPER',
        'descripcion' => 'Impuesto al Consumo de Bolsas Plásticas',
        'tipo_codigo' => 'OTH',
        'monto'       => 0.20,             // S/ 0.20 por bolsa
        'categoria'   => 'S',
    ],
    // GRA - Gratuito (sin IGV ni precio)
    '9996' => [
        'codigo'      => '9996',
        'nombre'      => 'GRA',
        'descripcion' => 'Operación Gratuita',
        'tipo_codigo' => 'FRE',
        'porcentaje'  => 0.00,
        'categoria'   => 'O',
    ],
    // EXO - Exonerado de IGV
    '9997' => [
        'codigo'      => '9997',
        'nombre'      => 'EXO',
        'descripcion' => 'Exonerado del IGV',
        'tipo_codigo' => 'VAT',
        'porcentaje'  => 0.00,
        'categoria'   => 'E',
    ],
    // INA - Inafecto de IGV
    '9998' => [
        'codigo'      => '9998',
        'nombre'      => 'INA',
        'descripcion' => 'Inafecto del IGV',
        'tipo_codigo' => 'FRE',
        'porcentaje'  => 0.00,
        'categoria'   => 'O',
    ],
]);

// -----------------------------------------------
// CÓDIGOS DE TIPO DE AFECTACIÓN IGV (Tabla 07 SUNAT)
// -----------------------------------------------
define('TIPOS_AFECTACION_IGV', [
    // ── OPERACIONES GRAVADAS ──────────────────
    '10' => [
        'descripcion'  => 'Gravado - Operación Onerosa',
        'afecto_igv'   => true,
        'tributo_igv'  => '1000',
        'categoria'    => 'S',
    ],
    '11' => [
        'descripcion'  => 'Gravado - Retiro por Premio',
        'afecto_igv'   => true,
        'tributo_igv'  => '1000',
        'categoria'    => 'S',
    ],
    '12' => [
        'descripcion'  => 'Gravado - Retiro por Donación',
        'afecto_igv'   => true,
        'tributo_igv'  => '1000',
        'categoria'    => 'S',
    ],
    '13' => [
        'descripcion'  => 'Gravado - Retiro',
        'afecto_igv'   => true,
        'tributo_igv'  => '1000',
        'categoria'    => 'S',
    ],
    '14' => [
        'descripcion'  => 'Gravado - Retiro por Publicidad',
        'afecto_igv'   => true,
        'tributo_igv'  => '1000',
        'categoria'    => 'S',
    ],
    '15' => [
        'descripcion'  => 'Gravado - Bonificaciones',
        'afecto_igv'   => true,
        'tributo_igv'  => '1000',
        'categoria'    => 'S',
    ],
    '16' => [
        'descripcion'  => 'Gravado - Retiro por Entrega a Trabajadores',
        'afecto_igv'   => true,
        'tributo_igv'  => '1000',
        'categoria'    => 'S',
    ],
    '17' => [
        'descripcion'  => 'Gravado - IVAP (Arroz Pilado)',
        'afecto_igv'   => true,
        'tributo_igv'  => '1016',
        'categoria'    => 'S',
    ],
    // ── OPERACIONES EXONERADAS ────────────────
    '20' => [
        'descripcion'  => 'Exonerado - Operación Onerosa',
        'afecto_igv'   => false,
        'tributo_igv'  => '9997',
        'categoria'    => 'E',
    ],
    '21' => [
        'descripcion'  => 'Exonerado - Transferencia Gratuita',
        'afecto_igv'   => false,
        'tributo_igv'  => '9997',
        'categoria'    => 'E',
    ],
    // ── OPERACIONES INAFECTAS ─────────────────
    '30' => [
        'descripcion'  => 'Inafecto - Operación Onerosa',
        'afecto_igv'   => false,
        'tributo_igv'  => '9998',
        'categoria'    => 'O',
    ],
    '31' => [
        'descripcion'  => 'Inafecto - Retiro por Bonificación',
        'afecto_igv'   => false,
        'tributo_igv'  => '9998',
        'categoria'    => 'O',
    ],
    '32' => [
        'descripcion'  => 'Inafecto - Retiro',
        'afecto_igv'   => false,
        'tributo_igv'  => '9998',
        'categoria'    => 'O',
    ],
    '33' => [
        'descripcion'  => 'Inafecto - Retiro por Muestras Médicas',
        'afecto_igv'   => false,
        'tributo_igv'  => '9998',
        'categoria'    => 'O',
    ],
    '34' => [
        'descripcion'  => 'Inafecto - Retiro por Convenio Colectivo',
        'afecto_igv'   => false,
        'tributo_igv'  => '9998',
        'categoria'    => 'O',
    ],
    '35' => [
        'descripcion'  => 'Inafecto - Retiro por Premio',
        'afecto_igv'   => false,
        'tributo_igv'  => '9998',
        'categoria'    => 'O',
    ],
    '36' => [
        'descripcion'  => 'Inafecto - Retiro por Publicidad',
        'afecto_igv'   => false,
        'tributo_igv'  => '9998',
        'categoria'    => 'O',
    ],
    // ── OPERACIONES GRATUITAS ─────────────────
    '40' => [
        'descripcion'  => 'Exportación de Bienes o Servicios',
        'afecto_igv'   => false,
        'tributo_igv'  => '9995',
        'categoria'    => 'G',
    ],
]);

// -----------------------------------------------
// VERSIONES UBL Y CUSTOMIZATION SUNAT
// -----------------------------------------------
define('SUNAT_UBL_VERSION', '2.1');

define('SUNAT_CUSTOMIZATION_IDS', [
    '01'  => '2.0',    // Factura Electrónica
    '03'  => '2.0',    // Boleta de Venta Electrónica
    '07'  => '1.0',    // Nota de Crédito Electrónica
    '08'  => '1.0',    // Nota de Débito Electrónica
    'RA'  => '1.0',    // Resumen Diario (boletas)
    'RC'  => '1.0',    // Comunicación de Baja
]);

// -----------------------------------------------
// CODIGOS DE UNIDADES DE MEDIDA (Tabla 06 SUNAT)
// -----------------------------------------------
define('UNIDADES_MEDIDA', [
    'NIU' => 'Unidad (bienes)',
    'ZZ'  => 'Unidad (servicios)',
    'KGM' => 'Kilogramo',
    'MTR' => 'Metro',
    'LTR' => 'Litro',
    'BX'  => 'Caja',
    'BG'  => 'Bolsa',
    'SET' => 'Juego',
    'DZN' => 'Docena',
    'HUR' => 'Hora',
    'DAY' => 'Día',
    'MON' => 'Mes',
    'GLL' => 'Galón',
    'TNE' => 'Tonelada métrica',
    'MTQ' => 'Metro cúbico',
    'MTK' => 'Metro cuadrado',
    'PR'  => 'Par',
    'PK'  => 'Paquete',
    'GL'  => 'Galón',
    'TU'  => 'Tubo',
]);

// -----------------------------------------------
// MOTIVOS DE NOTA DE CRÉDITO (Tabla 09 SUNAT)
// -----------------------------------------------
define('MOTIVOS_NOTA_CREDITO', [
    '01' => 'Anulación de la operación',
    '02' => 'Anulación por error en el RUC',
    '03' => 'Corrección por error en la descripción',
    '04' => 'Descuento global',
    '05' => 'Descuento por ítem',
    '06' => 'Devolución total',
    '07' => 'Devolución por ítem',
    '08' => 'Bonificación',
    '09' => 'Disminución en el valor',
    '10' => 'Otros conceptos',
    '11' => 'Ajustes de operaciones de exportación',
    '12' => 'Ajustes afectos al IVAP',
    '13' => 'Corrección del IGV',
]);

// -----------------------------------------------
// MOTIVOS DE NOTA DE DÉBITO (Tabla 10 SUNAT)
// -----------------------------------------------
define('MOTIVOS_NOTA_DEBITO', [
    '01' => 'Intereses por mora',
    '02' => 'Aumento en el valor',
    '03' => 'Penalidades/ otros conceptos',
    '10' => 'Ajustes de operaciones de exportación',
    '11' => 'Ajustes afectos al IVAP',
]);
