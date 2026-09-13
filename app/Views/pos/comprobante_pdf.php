<?php 
$ubicacionQr = $data['ubicacionQr'] ?? ($ubicacionQr ?? null);

// --- 1. LÓGICA DE MÉTODOS DE PAGO ---
$nombresPago = [
    'efectivo'        => 'Efectivo',
    'yape'            => 'Yape',
    'plin'            => 'Plin',
    'transferencia'   => 'Transferencia',
    'bcp'             => 'BCP',
    'bbva'            => 'BBVA',
    'scotiabank'      => 'Scotiabank',
    'tarjeta'         => 'Tarjeta',
    'tarjeta_credito' => 'Tarjeta',
    'tarjeta_debito'  => 'Tarjeta',
    'usd'             => 'Dólares',
    'otro'            => 'Otro',
];
$pagos = $venta['pagos'] ?? [];
$metodosPago = [];
foreach ($pagos as $pago) {
    $metodo = strtolower(trim((string)($pago['metodo_pago'] ?? '')));
    if ($metodo !== '') {
        $metodosPago[] = $nombresPago[$metodo] ?? ucfirst($metodo);
    }
}
$metodoPagoTexto = implode(', ', array_unique($metodosPago)) ?: 'Efectivo / Al contado';

// --- 2. FECHAS Y HORAS ---
$fechaEmisionTimestamp = !empty($venta['created_at']) ? strtotime($venta['created_at']) : (!empty($venta['fecha_emision']) ? strtotime($venta['fecha_emision']) : time());
$fechaEmision = date('d/m/Y', $fechaEmisionTimestamp);
$horaTexto = date('H:i', $fechaEmisionTimestamp);

// --- 3. IDENTIFICACIÓN DEL COMPROBANTE ---
$serie = $venta['serie'] ?? 'B001';
$numero = str_pad((string)($venta['numero'] ?? 0), 8, '0', STR_PAD_LEFT);
$numeroComprobante = $serie . '-' . $numero;

$tipoNombre = 'NOTA DE VENTA';
$tipoDoc = $venta['tipo_comprobante'] ?? '';
if ($tipoDoc === '01' || str_starts_with($serie, 'F')) {
    $tipoNombre = 'FACTURA ELECTRÓNICA';
} elseif ($tipoDoc === '03' || str_starts_with($serie, 'B')) {
    $tipoNombre = 'BOLETA ELECTRÓNICA';
}

$esDolar = ($venta['moneda'] ?? 'PEN') === 'USD';
$monedaStr = $esDolar ? 'Dólares (US$)' : 'Soles (S/)';
$symbol = $esDolar ? 'US$' : 'S/';

// --- 4. ÍTEMS Y TOTALES ---
$items = $venta['items'] ?? $venta['items_json'] ?? [];
if (is_string($items)) {
    $items = json_decode($items, true) ?: [];
}

$sumaTotalItems = 0;
foreach ($items as $it) {
    $cant = (float)($it['cantidad'] ?? 1);
    $precio = (float)($it['precio_unitario_pen'] ?? $it['precio_unit'] ?? $it['valor_unitario'] ?? $it['precio_unitario'] ?? 0);
    $tot = (float)($it['total'] ?? ($cant * $precio));
    $sumaTotalItems += $tot;
}

$docTotal = !empty($venta['total_pen']) && (float)$venta['total_pen'] > 0 
    ? (float)$venta['total_pen'] 
    : (!empty($venta['total']) && (float)$venta['total'] > 0 ? (float)$venta['total'] : $sumaTotalItems);

$docIgv = (float)($venta['igv'] ?? 0);
$docSubtotal = !empty($venta['subtotal']) && (float)$venta['subtotal'] > 0 
    ? (float)$venta['subtotal'] 
    : (!empty($venta['subtotal_gravado']) && (float)$venta['subtotal_gravado'] > 0 
        ? (float)$venta['subtotal_gravado'] 
        : ($docTotal - $docIgv));

// Función para incrustar logos bancarios en Base64
if (!function_exists('getBankIconBase64')) {
    function getBankIconBase64($filename, $bgColor, $text, $height = 15) {
        $paths = [
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $filename,
            $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $filename,
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $filename,
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : 'image/png';
                return '<img src="data:' . $mime . ';base64,' . base64_encode(file_get_contents($path)) . '" style="height: ' . $height . 'px; width: auto; vertical-align: middle; margin-right: 5px;">';
            }
        }
        return '<strong style="color: '.$bgColor.'; font-size: 8.5px; margin-right: 4px;">['.$text.']</strong>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title><?= htmlspecialchars($tipoNombre . ' ' . $numeroComprobante) ?></title>
    <style>
        @page { margin: 20px 30px; }
        
        * {
            font-family: 'DejaVu Sans', Arial, sans-serif !important;
            box-sizing: border-box;
        }

        body { font-size: 9.5px; color: #1e293b; line-height: 1.35; margin: 0; padding: 0; background: #ffffff; }
        table { width: 100%; border-collapse: collapse; }

        /* Colores corporativos */
        .color-blue { color: #0090e7; }
        .bg-blue { background-color: #0090e7; color: #ffffff; }
        .bg-dark { background-color: #2b3440; color: #ffffff; }

        /* Banner superior derecho curvado */
        .header-curved-badge { background-color: #0090e7; color: #ffffff; padding: 10px 24px 12px 26px; border-radius: 0 0 0 24px; text-align: right; }

        /* Caja de total superior */
        .card-total-top { background-color: #edf2f7; border-radius: 8px; padding: 9px 14px; text-align: left; }

        /* Tabla de productos con corte diagonal */
        .table-pinterest { border-collapse: separate; border-spacing: 0 4px; margin-top: 10px; margin-bottom: 12px; width: 100%; }
        
        .th-dark { 
            background-color: #2b3440; 
            color: #ffffff; 
            font-size: 8.5px; 
            font-weight: bold; 
            text-transform: uppercase; 
            letter-spacing: 0.8px; 
            padding: 8px 10px; 
            vertical-align: middle; 
            line-height: 1; 
            border: none; 
        }
        
        .th-blue { 
            background-color: #0090e7; 
            color: #ffffff; 
            font-size: 8.5px; 
            font-weight: bold; 
            text-transform: uppercase; 
            letter-spacing: 0.8px; 
            padding: 8px 10px; 
            vertical-align: middle; 
            line-height: 1; 
            border: none; 
        }

        .th-diagonal { 
            width: 20px; 
            padding: 0 !important; 
            margin: 0 !important; 
            line-height: 0 !important; 
            vertical-align: middle !important; 
            border: none !important; 
            background: transparent; 
        }
        .img-diagonal { width: 20px; height: 30px; display: block; border: 0; margin: 0; padding: 0; }

        .row-pill td { background-color: #edf2f7; padding: 7px 10px; font-size: 9.5px; border: none; }
        .row-white td { background-color: #ffffff; padding: 7px 10px; font-size: 9.5px; border-bottom: 1px solid #f1f5f9; }

        /* Títulos de sección */
        .title-block { 
            font-family: 'DejaVu Sans', Arial, sans-serif !important;
            font-size: 9.5px; 
            font-weight: bold; 
            color: #0f172a; 
            text-transform: uppercase; 
            letter-spacing: 0.3px; 
            margin-bottom: 5px; 
        }

        /* Tabla de condiciones */
        .table-cond, .table-cond td, .table-cond .lbl, .table-cond .val { 
            font-family: 'DejaVu Sans', Arial, sans-serif !important;
            font-size: 8.5px; 
            vertical-align: top; 
        }
        .table-cond td { padding: 2.5px 0; }
        .table-cond .lbl { width: 34%; color: #64748b; font-weight: 600; }
        .table-cond .val { width: 66%; color: #0f172a; font-weight: 500; }

        /* Barra Total Inferior */
        .grand-total-bar { background-color: #0090e7; color: #ffffff; border-radius: 6px; padding: 8px 12px; margin-top: 6px; }
    </style>
</head>
<body>

    <!-- ================= 1. CABECERA: LOGO + BANNER AZUL DE COMPROBANTE ================= -->
    <table style="width: 100%; margin-bottom: 8px;">
        <tr>
            <!-- Logo y datos fiscales de Infortel -->
            <td style="width: 50%; vertical-align: top;">
                <?php
                    $logoPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo1.png';
                    if (!file_exists($logoPath)) {
                        $logoPath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo1.png';
                    }
                    if (file_exists($logoPath)): 
                ?>
                    <img src="data:image/png;base64,<?= base64_encode(file_get_contents($logoPath)) ?>" style="max-height: 48px; max-width: 230px; margin-bottom: 2px;">
                <?php else: ?>
                    <div style="font-size: 22px; font-weight: 900; color: #0090e7; letter-spacing: -0.5px;">INFORTEL <span style="color: #0f172a;">COMP</span></div>
                <?php endif; ?>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px; line-height: 1.3;">
                    SOLUCIONES TECNOLÓGICAS Y CONECTIVIDAD &bull; RUC: <?= htmlspecialchars($config['ruc'] ?? '20394062929') ?><br>
                    <?= htmlspecialchars($config['direccion'] ?? 'Jr. Comercio 123, Pucallpa, Ucayali') ?>
                </div>
            </td>

            <!-- Banner Azul Curvado con Tipo y Número -->
            <td style="width: 50%; vertical-align: top; text-align: right;">
                <table style="float: right; border-collapse: collapse;">
                    <tr>
                        <td class="header-curved-badge">
                            <div style="font-size: 17px; font-weight: 900; letter-spacing: 0.5px; line-height: 1; text-transform: uppercase;">
                                <?= $tipoNombre ?>
                            </div>
                            <div style="font-size: 11px; font-weight: bold; opacity: 0.95; margin-top: 3px;">
                                No. <?= htmlspecialchars($numeroComprobante) ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="clear: both;"></div>

    <!-- ================= 2. CLIENTE, FECHAS Y TOTAL A PAGAR ================= -->
    <table style="width: 100%; margin-top: 6px; margin-bottom: 12px;">
        <tr>
            <!-- Columna Izquierda: FACTURADO A -->
            <td style="width: 55%; vertical-align: top;">
                <div style="font-size: 8.5px; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">FACTURADO A:</div>
                <div style="font-size: 14.5px; font-weight: bold; color: #0f172a; margin-top: 2px; margin-bottom: 6px;">
                    <?= htmlspecialchars($venta['cliente_nombre'] ?? $venta['razon_social'] ?? 'CLIENTE GENERAL') ?>
                </div>
                <table style="font-size: 8.5px; color: #475569; width: 100%;">
                    <tr>
                        <td style="width: 65px; font-weight: bold; color: #64748b; padding: 2px 0; font-size: 10px;">RUC / DNI:</td>
                        <td style="padding: 2px 0; color: #0f172a; font-weight: bold; font-size: 10px;">
                            <?= htmlspecialchars($venta['cliente_numero_doc'] ?? $venta['cliente_doc'] ?? '-') ?>
                        </td>
                    </tr>
                    <?php 
                        $dir = trim($venta['cliente_direccion'] ?? $venta['direccion'] ?? '');
                        if (!empty($dir)): 
                    ?>
                    <tr>
                        <td style="width: 65px; font-weight: bold; color: #64748b; padding: 2px 0; font-size: 10px;">Dirección:</td>
                        <td style="padding: 2px 0; color: #0f172a; font-size: 10px; ">
                            <?= htmlspecialchars($dir) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td style="width: 65px; font-weight: bold; color: #64748b; padding: 2px 0; font-size: 10px;">Moneda:</td>
                        <td style="padding: 2px 0; color: #0f172a; font-size: 10px; ">
                            <?= $monedaStr ?>
                        </td>
                    </tr>
                </table>
            </td>

            <!-- Columna Derecha: Fechas y Total alineados -->
            <td style="width: 45%; vertical-align: top; text-align: right;">
                <!-- Fechas -->
                <table style="width: 195px; float: right; margin-bottom: 8px; border-collapse: collapse;">
                    <tr>
                        <td style="width: 48%; text-align: center; vertical-align: top;">
                            <span style="font-size: 8px; font-weight: bold; color: #64748b;">Fecha Emisión</span><br>
                            <span style="font-size: 9.5px; font-weight: bold; color: #0f172a;"><?= $fechaEmision ?></span>
                        </td>
                        <td style="width: 4%; text-align: center; vertical-align: middle; color: #cbd5e1; font-size: 12px;">|</td>
                        <td style="width: 48%; text-align: center; vertical-align: top;">
                            <span style="font-size: 8px; font-weight: bold; color: #64748b;">Hora</span><br>
                            <span style="font-size: 9.5px; font-weight: bold; color: #0f172a;"><?= $horaTexto ?></span>
                        </td>
                    </tr>
                </table>

                <div style="clear: both;"></div>

                <!-- Tarjeta Gris Total a Pagar -->
                <table style="width: 195px; float: right; border-collapse: collapse;">
                    <tr>
                        <td class="card-total-top">
                            <div style="font-size: 8px; font-weight: bold; color: #475569;">Total a Pagar:</div>
                            <div style="font-size: 16.5px; font-weight: bold; color: #0f172a; margin-top: 1px;">
                                <?= $symbol ?> <?= number_format($docTotal, 2) ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ================= 3. TABLA DE PRODUCTOS CON DIAGONAL ALTA RESOLUCIÓN ================= -->
    <table class="table-pinterest">
        <thead>
            <tr>
                <th class="th-dark" style="width: 5%; text-align: center; border-radius: 4px 0 0 4px;">N°</th>
                <th class="th-dark" style="width: 49%; text-align: left; padding-right: 0;">DESCRIPCIÓN DEL PRODUCTO</th>
                
                <!-- Celda diagonal perfectamente acoplada -->
                <th class="th-diagonal">
                    <img class="img-diagonal" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAFAAAACACAIAAAATTXnVAAAB9UlEQVR4nO3S23ECQQxE0Wunw68DcCDkR0Tk4w+7KAzLMjsPTUujjkCnrj5OX9+stM/ZB1gvwaF3PV8WAl/PF1YrzDrg37ysA75tCfAtLyuA77WsAH5YcPBDXmKDn7XEBm8uLHgzL4HBrxYT/CovIcE7WkKC9xcNvJ+XYOC3WoKBSxYHXJKXSODCBQEX5iUGuFxLDPChuQcfyot38FEt3sEVcwyuyItrcN28guvy4hRcrcUpuGX+wC15cQdu1OIO3D5P4Pa8+AJ3mRtwl7x4AffS4gXccQ7AHfOiD+6rRR/cfdLg7nkRB4+YLnhEXmTBg7TIgsdNETwuL4LgoVoEwaOnBR6dFzWwwYTABnnRAdto0QGbTQJslhcFsKUWBbDxJoON8zIdbL+ZYPu8TARP0ZIvbbRZeZkCnqglX3r45uYlC4/d9LxYghW05EuPmkhebMA6WvKl+08qL1m489TyMhQsqCVfuts08zIILKslX7rDlPOShVsnnpe+YH0t+dL1c5GXXmAvWvKla+YoL1n48HzlpRHsTku+9IF5zEs12KmWfOmi+c1LFn4/13k5CvauJV96bwHyUg6OoSVfenth8pKFNxYpL2/BwbTkS/9bvLzsgENqyZf+W9S8ZGEInZdncGwtq790+Lzcg1fQsu5LL5KXBQv/AB26kW+7z6FVAAAAAElFTkSuQmCC">
                </th>

                <th class="th-blue" style="width: 9%; text-align: center;">U.M.</th>
                <th class="th-blue" style="width: 12%; text-align: right;">PRECIO</th>
                <th class="th-blue" style="width: 8%; text-align: center;">CANT.</th>
                <th class="th-blue" style="width: 13%; text-align: right; border-radius: 0 4px 4px 0;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                if (!empty($items)):
                    $contador = 1;
                    foreach ($items as $item):
                        $rowClass = ($contador % 2 === 0) ? 'row-pill' : 'row-white';
                        $cant = (float)($item['cantidad'] ?? 1);
                        $desc = $item['descripcion_item'] ?? $item['descripcion'] ?? $item['nombre'] ?? '';
                        $prec = (float)($item['precio_unitario_pen'] ?? $item['precio_unit'] ?? $item['valor_unitario'] ?? $item['precio_unitario'] ?? 0);
                        $tot = (float)($item['total'] ?? ($cant * $prec));
                        $um = !empty($item['unidad_medida']) && $item['unidad_medida'] !== 'NIU' ? $item['unidad_medida'] : 'UND';
            ?>
            <tr class="<?= $rowClass ?>">
                <td style="text-align: center; font-weight: bold; color: #64748b; border-radius: 4px 0 0 4px;"><?= $contador ?>.</td>
                <td colspan="2" style="font-weight: bold; color: #0f172a;">
                    <?= htmlspecialchars($desc) ?>
                </td>
                <td style="text-align: center; color: #64748b; font-size: 8.5px;">
                    <?= htmlspecialchars($um) ?>
                </td>
                <td style="text-align: right; color: #475569;">
                    <?= number_format($prec, 2) ?>
                </td>
                <td style="text-align: center; font-weight: bold;">
                    <?= number_format($cant, 2) ?>
                </td>
                <td style="text-align: right; font-weight: 900; color: #0f172a; border-radius: 0 4px 4px 0;">
                    <?= number_format($tot, 2) ?>
                </td>
            </tr>
            <?php 
                    $contador++;
                    endforeach;
                else: 
            ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 25px; color: #94a3b8;">No se registraron productos en esta venta.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ================= 4. SECCIÓN INFERIOR: MÉTODOS, CONDICIONES Y TOTALES ================= -->
    <table style="margin-top: 6px;">
        <tr>
            <!-- Columna Izquierda (58%): Cuentas y Condiciones Legales -->
            <td style="width: 58%; vertical-align: top; padding-right: 25px;">
                
                <!-- Métodos de Pago (Logos con tamaño equilibrado) -->
                <div class="title-block">Métodos de Pago y Cuentas Bancarias</div>
                <table style="margin-bottom: 8px; font-size: 8.5px; background-color: #f8fafc; border-radius: 6px; padding: 6px 10px;">
                    <tr>
                        <!-- BCP (Alto 16px) -->
                        <td style="width: 50%; vertical-align: top; padding-right: 6px;">
                            <?= getBankIconBase64('bcp.png', '#FF7A00', 'BCP', 16) ?>
                            <strong style="color: #0f172a; vertical-align: middle; font-size: 12px;">BCP Soles</strong><br>
                            <span style="color: #64748b; font-size: 12px;">Cta:</span> <strong style="font-size: 10px;">480-2212912029</strong><br>
                            <span style="color: #64748b; font-size: 12px;">CCI:</span><strong style="font-size: 10px;" > 00248000221291202925 </strong>
                        </td>
                        <!-- BBVA (Alto 15px) -->
                        <td style="width: 50%; vertical-align: top; padding-left: 6px; border-left: 1px dashed #e2e8f0; ">
                            <?= getBankIconBase64('bbva.png', '#004481', 'BBVA', 15) ?>
                            <strong style="color: #0f172a; vertical-align: middle; font-size: 12px;">BBVA Soles</strong><br>
                            <span style="color: #64748b; font-size: 12px;">Cta:</span> <strong style="font-size: 10px;">0011-0233-0100028882</strong><br>
                            <span style="color: #64748b; font-size: 12px;">CCI:</span><strong style="font-size: 10px;"> 011-233-000100028882-43 </strong>
                        </td>
                    </tr>
                    <tr>
                        <!-- YAPE (Alto 18px para igualar presencia visual) -->
                        <td colspan="2" style="padding-top: 6px; margin-top: 4px; border-top: 1px dashed #e2e8f0;">
                            <?= getBankIconBase64('yape.png', '#722F37', 'YAPE', 18) ?>
                            <strong style="color: black; vertical-align: middle; font-size: 11.5px;">+51 966 422 570</strong> 
                            <span style="color: #64748b; vertical-align: middle; font-size: 11.5px;"> &bull; Titular: INFORTEL COMP E.I.R.L.</span>
                        </td>
                    </tr>
                </table>

                <!-- Términos y Condiciones Fiscales de Venta -->
                <div class="title-block">Información Comercial y Tributaria</div>
                <table class="table-cond">
                    <tr>
                        <td class="lbl">Forma de pago:</td>
                        <td class="val"><strong><?= htmlspecialchars($metodoPagoTexto) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="lbl">Garantía:</td>
                        <td class="val">Por defectos de fabricación de equipos es por 01 año.</td>
                    </tr>
                    <tr>
                        <td class="lbl">Régimen tributario:</td>
                        <td class="val">Bienes transferidos en la Amazonía para ser consumidos en la misma (Ley N° 27037).</td>
                    </tr>
                    <tr>
                        <td class="lbl">Representación:</td>
                        <td class="val">Representación impresa del comprobante electrónico autorizado por SUNAT.</td>
                    </tr>
                    <?php if (!empty($venta['hash_cpe'])): ?>
                    <tr>
                        <td class="lbl">Hash CPE:</td>
                        <td class="val" style="font-family: monospace; font-size: 8px;"><?= htmlspecialchars($venta['hash_cpe']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

            </td>

            <!-- Columna Derecha (42%): Desglose de Totales y Firma -->
            <td style="width: 42%; vertical-align: top;">
                
                <!-- Desglose de Totales -->
                <table style="width: 100%; font-size: 9.5px; margin-bottom: 4px;">
                    <tr>
                        <td style="color: #64748b; padding: 3px 0; text-align: right;">Sub Total:</td>
                        <td style="font-weight: bold; color: #0f172a; text-align: right; width: 45%;">
                            <?= $symbol ?> <?= number_format($docSubtotal, 2) ?>
                        </td>
                    </tr>
                    <?php if ($docIgv > 0): ?>
                    <tr>
                        <td style="color: #64748b; padding: 3px 0; text-align: right;">I.G.V. (18%):</td>
                        <td style="font-weight: bold; color: #0f172a; text-align: right;">
                            <?= $symbol ?> <?= number_format($docIgv, 2) ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td style="color: #64748b; padding: 3px 0; text-align: right;">Op. Exonerada:</td>
                        <td style="font-weight: bold; color: #0f172a; text-align: right;">
                            <?= $symbol ?> <?= number_format($docTotal, 2) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>

                <!-- Barra Azul TOTAL A PAGAR -->
                <div class="grand-total-bar">
                    <table style="width: 100%;">
                        <tr>
                            <td style="font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff;">TOTAL :</td>
                            <td style="font-size: 14px; font-weight: bold; color: #ffffff; text-align: right;">
                                <?= $symbol ?> <?= number_format($docTotal, 2) ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Sello y Firma Autorizada -->
                <div style="margin-top: 28px; text-align: center;">
                    <div style="border-top: 1px solid #94a3b8; width: 170px; margin: 0 auto; padding-top: 3px;">
                        <div style="font-weight: bold; color: #0f172a; font-size: 9px;">
                            <?= htmlspecialchars($config['razon_social'] ?? 'INFORTEL COMP E.I.R.L.') ?>
                        </div>
                        <div style="font-size: 7.5px; color: #64748b;">Sello y Firma Autorizada</div>
                    </div>
                </div>

            </td>
        </tr>
    </table>

    <!-- ================= 5. FOOTER CON QR Y AGRADECIMIENTO ================= -->
    <table style="width: 100%; margin-top: 14px; border-top: 1px solid #e2e8f0; padding-top: 8px;">
        <tr>
            <!-- Pestaña horizontal aerodinámica idéntica a Pinterest (QR Grande 64x64px) -->
            <td style="width: 140px; vertical-align: middle; padding: 0;">
                <?php if (!empty($ubicacionQr)): ?>
                    <table style="width: 92px; height: 82px; border-collapse: collapse; margin: 0; padding: 0;">
                        <tr style="height: 82px; line-height: 0;">
                            <!-- 1. Bloque azul fijo con el QR blanco grande centrado (86px ancho x 82px alto) -->
                            <td style="width: 86px; height: 82px; background-color: #0090e7; vertical-align: middle; text-align: center; padding: 0; margin: 0; line-height: 0;">
                                <div style="width: 70px; height: 71px; background-color: #ffffff; padding: 3px; margin: 0 auto; line-height: 0;">
                                    <img src="<?= htmlspecialchars($ubicacionQr, ENT_QUOTES, 'UTF-8') ?>" style="width: 66px; height: 66px; display: block; border: 0; margin: 0;">
                                </div>
                            </td>

                            
                        </tr>
                    </table>
                <?php endif; ?>
            </td>

            <!-- Agradecimiento y Contacto Institucional -->
            <td style="vertical-align: middle; text-align: center;">
                <div style="font-size: 10px; font-weight: bold; color: #0f172a; letter-spacing: 1px; text-transform: uppercase;">
                    AGRADECEMOS SU PREFERENCIA
                </div>
                <table style="width: 100%; margin-top: 5px; font-size: 8px; color: #64748b;">
                    <tr>
                        <td style="text-align: center; width: 33%;">Tel: <strong>+51 966 422 570</strong></td>
                        <td style="text-align: center; width: 33%;">Email: <strong>contacto@infortel.com</strong></td>
                        <td style="text-align: center; width: 34%;">Dir: <strong>Jr. Comercio 123, Pucallpa</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>