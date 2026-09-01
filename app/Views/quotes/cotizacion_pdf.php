<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?php 
        $numeroComprobante = $cotizacion['numero'] ?? '-';
        $fechaEmision = !empty($cotizacion['fecha_emision']) ? date('M d, Y', strtotime($cotizacion['fecha_emision'])) : '-';
        $fechaVencimiento = !empty($cotizacion['fecha_vencimiento']) ? date('M d, Y', strtotime($cotizacion['fecha_vencimiento'])) : '-';
        $monedaStr = ($cotizacion['moneda'] ?? 'PEN') === 'USD' ? 'Dólares (USD)' : 'Soles (PEN)';
        $symbol = ($cotizacion['moneda'] ?? 'PEN') === 'USD' ? 'US$' : 'S/';
    ?>
    <title>Cotización <?= htmlspecialchars($numeroComprobante) ?></title>
    <style>
        /* Tipografía y reseteo básico */
        body { font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif; font-size: 12px; color: #334155; margin: 0; padding: 10px 20px; }
        table { width: 100%; border-collapse: collapse; }
        
        /* Paleta de colores Premium */
        .color-primary { color: #00AEEF; }
        .bg-primary { background-color: #00AEEF; color: #ffffff; }
        .bg-dark { background-color: #1e293b; color: #ffffff; }
        .text-dark { color: #0f172a; }
        .text-gray { color: #64748b; }

        /* Cabecera */
        .header-table { border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 20px; }
        .document-title { font-size: 38px; font-weight: 900; text-transform: uppercase; letter-spacing: -1px; text-align: right; margin: 0; line-height: 1; }
        .company-name { font-size: 24px; font-weight: 900; margin: 0; letter-spacing: -0.5px; }
        .company-tagline { font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #94a3b8; margin-top: 2px; }
        
        /* Info de facturación */
        .info-table { margin-bottom: 30px; }
        .info-title { font-size: 11px; font-weight: bold; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 1px; }
        .client-name { font-size: 18px; font-weight: bold; margin: 0 0 5px 0; }
        
        /* Tabla de productos */
        .items-table { margin-bottom: 30px; }
        .items-table th { padding: 12px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; }
        .items-table td { padding: 12px 10px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
        
        /* Totales y notas */
        .bottom-table { margin-top: 20px; }
        .totals-block { width: 100%; border-collapse: collapse; }
        .totals-block td { padding: 8px 10px; font-size: 12px; }
        .totals-block .total-row td { font-size: 15px; font-weight: bold; }
        
        .signature-line { border-top: 1px solid #cbd5e1; width: 200px; margin-top: 40px; padding-top: 5px; text-align: center; font-size: 10px; color: #64748b; }

        /* Footer */
        .footer { margin-top: 40px; font-size: 10px; line-height: 1.5; color: #94a3b8; }
    </style>
</head>
<body>

    <!-- ================= HEADER ================= -->
    <table class="header-table">
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                <?php
                    $logoPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo1.png';
                    if (!file_exists($logoPath)) {
                        $logoPath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo1.png';
                    }

                    if (file_exists($logoPath)) {
                        $logoData = file_get_contents($logoPath);
                        $base64 = 'data:image/png;base64,' . base64_encode($logoData);
                        echo '<img src="' . $base64 . '" alt="Logo Empresa" style="max-height: 170px; margin-bottom: 5px;">';
                    } else {
                        // Fallback con el nombre PΛRADISE
                        echo '<table><tr>';
                        echo '<td style="color:#00AEEF; font-size:35px; font-weight:bold; padding-right:10px;">/</td>';
                        echo '<td><div class="text-dark company-name">'.htmlspecialchars($config['razon_social'] ?? 'PΛRADISE').'</div><div class="company-tagline">Soluciones Tecnológicas</div></td>';
                        echo '</tr></table>';
                    }
                ?>
            </td>
            <td style="width: 50%; vertical-align: top; text-align: right;">
                <h1 class="color-primary document-title">COTIZACIÓN</h1>
                
                <table style="width: 100%; margin-top: 15px; font-size: 11px;">
                    <tr>
                        <td class="text-gray" style="text-align: right; padding-right: 15px;">Nro. Cotización:</td>
                        <td class="text-dark" style="text-align: right; font-weight: bold;"><?= htmlspecialchars($numeroComprobante) ?></td>
                    </tr>
                    <tr>
                        <td class="text-gray" style="text-align: right; padding-right: 15px;">Fecha Emisión:</td>
                        <td class="text-dark" style="text-align: right;"><?= $fechaEmision ?></td>
                    </tr>
                    <tr>
                        <td class="text-gray" style="text-align: right; padding-right: 15px;">Válida hasta:</td>
                        <td class="text-dark" style="text-align: right; font-weight: bold; color: #dc2626;"><?= $fechaVencimiento ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ================= CLIENTE Y EMPRESA ================= -->
    <table class="info-table">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 20px;">
                <div class="info-title color-primary">COTIZADO A:</div>
                <h2 class="client-name text-dark"><?= htmlspecialchars($cotizacion['cliente_nombre'] ?? 'Cliente General') ?></h2>
                <div class="text-gray" style="line-height: 1.6;">
                    Doc: <?= htmlspecialchars($cotizacion['cliente_documento'] ?? '-') ?><br>
                    Moneda: <?= $monedaStr ?>
                </div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div class="info-title text-dark">DATOS DEL EMISOR:</div>
                <div class="text-gray" style="line-height: 1.6;">
                    <strong>RUC:</strong> <?= htmlspecialchars($config['ruc'] ?? '-') ?><br>
                    <?= htmlspecialchars($config['direccion'] ?? 'Pucallpa - Ucayali - Perú') ?><br>
                    Teléfono: <?= htmlspecialchars($config['telefono'] ?? '-') ?><br>
                    Atendido por: <span class="text-dark" style="font-weight: bold;"><?= htmlspecialchars(trim(($cotizacion['usuario_nombre'] ?? '') . ' ' . ($cotizacion['usuario_apellidos'] ?? '')) ?: 'Administrador') ?></span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ================= TABLA DE PRODUCTOS ================= -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="bg-primary" style="text-align: center; width: 8%;">NO.</th>
                <th class="bg-primary" style="text-align: left; width: 47%;">DESCRIPCIÓN DEL PRODUCTO / SERVICIO</th>
                <th class="bg-dark" style="text-align: center; width: 15%;">CANT.</th>
                <th class="bg-dark" style="text-align: right; width: 15%;">P. UNIT</th>
                <th class="bg-dark" style="text-align: right; width: 15%;">IMPORTE</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                if (!empty($cotizacion['items'])):
                    $contador = 1;
                    foreach ($cotizacion['items'] as $item):
            ?>
            <tr>
                <td style="text-align: center; color: #94a3b8;"><?= str_pad((string)$contador, 2, '0', STR_PAD_LEFT) ?></td>
                <td style="font-weight: bold; color: #334155;">
                    <?= htmlspecialchars($item['descripcion'] ?? '') ?>
                    <?php if(!empty($item['unidad_medida']) && $item['unidad_medida'] !== 'NIU'): ?>
                        <span style="font-size: 9px; font-weight: normal; color: #94a3b8;">(<?= htmlspecialchars($item['unidad_medida']) ?>)</span>
                    <?php endif; ?>
                </td>
                <td style="text-align: center; color: #64748b;"><?= number_format((float)($item['cantidad'] ?? 0), 2) ?></td>
                <td style="text-align: right; color: #64748b;"><?= number_format((float)($item['precio_unitario'] ?? 0), 2) ?></td>
                <td style="text-align: right; font-weight: bold; color: #334155;"><?= number_format((float)($item['subtotal_item'] ?? 0), 2) ?></td>
            </tr>
            <?php 
                    $contador++;
                    endforeach;
                else: 
            ?>
            <tr>
                <td colspan="5" style="text-align: center; padding: 20px; color: #94a3b8;">No hay ítems registrados en esta cotización.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ================= TOTALES Y OBSERVACIONES ================= -->
    <table class="bottom-table">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <?php if (!empty($cotizacion['condicion_pago']) || !empty($cotizacion['notas'])): ?>
                    <div class="info-title text-dark">OBSERVACIONES Y CONDICIONES</div>
                    <div class="text-gray" style="font-size: 11px; line-height: 1.6; padding-right: 20px;">
                        <?php if (!empty($cotizacion['condicion_pago'])): ?>
                            <strong class="text-dark">Condición de pago:</strong> <?= htmlspecialchars($cotizacion['condicion_pago']) ?><br>
                        <?php endif; ?>
                        <?php if (!empty($cotizacion['notas'])): ?>
                            <div style="margin-top: 5px;">
                                <?= nl2br(htmlspecialchars($cotizacion['notas'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="signature-line">
                    <?= htmlspecialchars(trim(($cotizacion['usuario_nombre'] ?? '') . ' ' . ($cotizacion['usuario_apellidos'] ?? '')) ?: 'Administrador') ?><br>
                    Asesor Comercial
                </div>
            </td>
            
            <td style="width: 10%;"></td>
            
            <td style="width: 40%; vertical-align: bottom;">
                <table class="totals-block">
                    <tr>
                        <td class="text-gray" style="text-align: right; border-bottom: 1px solid #f1f5f9;">Subtotal:</td>
                        <td class="text-dark" style="text-align: right; font-weight: bold; border-bottom: 1px solid #f1f5f9;"><?= $symbol ?> <?= number_format((float)($cotizacion['subtotal'] ?? 0), 2) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td class="bg-primary" style="text-align: right; padding: 12px 10px; border-radius: 4px 0 0 4px;">TOTAL COTIZADO:</td>
                        <td class="bg-primary" style="text-align: right; padding: 12px 10px; border-radius: 0 4px 4px 0;"><?= $symbol ?> <?= number_format((float)($cotizacion['total'] ?? 0), 2) ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ================= FOOTER ================= -->
    <div class="footer">
        <div class="color-primary" style="font-size: 14px; font-weight: bold; margin-bottom: 10px;">¡Gracias por su preferencia!</div>
        Este documento es una estimación de precios con carácter informativo y no constituye un comprobante de pago válido para efectos tributarios. Los precios y disponibilidad de stock están sujetos a cambios sin previo aviso una vez superada la fecha de validez ("Válida hasta").
    </div>

</body>
</html>