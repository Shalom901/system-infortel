<?php $ubicacionQr = $data['ubicacionQr'] ?? null; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <?php 
            $nombresPago = [
                'efectivo' => 'Efectivo', 'yape' => 'Yape', 'plin' => 'Plin',
                'transferencia' => 'Transferencia', 'bcp' => 'BCP', 'interbank' => 'Interbank',
                'bbva' => 'BBVA', 'scotiabank' => 'Scotiabank', 'tarjeta_credito' => 'Tarjeta',
                'tarjeta_debito' => 'Tarjeta', 'usd' => 'Dólares', 'otro' => 'Otro',
            ];
            $pagos = $venta['pagos'] ?? [];
            $metodosPago = [];
            foreach ($pagos as $pago) {
                $metodo = strtolower(trim((string)($pago['metodo_pago'] ?? '')));
                if ($metodo !== '') $metodosPago[] = $nombresPago[$metodo] ?? ucfirst($metodo);
            }
            $metodoPagoTexto = implode(', ', array_unique($metodosPago)) ?: 'No registrado';
            $fechaEmision = !empty($venta['created_at']) ? strtotime($venta['created_at']) : strtotime($venta['fecha_emision'] ?? 'now');
            $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $fechaTexto = date('d', $fechaEmision) . ' de ' . $meses[(int)date('n', $fechaEmision) - 1] . ' de ' . date('Y', $fechaEmision);
            $horaTexto = date('H:i', $fechaEmision);
        ?>
        <?php
        $serie = $venta['serie'] ?? 'B001';
        $numero = str_pad((string)($venta['numero'] ?? 0), 8, '0', STR_PAD_LEFT);
        $numeroComprobante = $serie . '-' . $numero;

        $tipoNombre = 'NOTA DE VENTA';
        $tipoDoc = $venta['tipo_comprobante'] ?? '';
        if ($tipoDoc === '01' || str_starts_with($serie, 'F')) $tipoNombre = 'FACTURA ELECTRÓNICA';
        if ($tipoDoc === '03' || str_starts_with($serie, 'B')) $tipoNombre = 'BOLETA ELECTRÓNICA';
    ?>
    <title>Comprobante <?= htmlspecialchars($numeroComprobante) ?></title>
    <style>
        /* Tipografía y reseteo básico */
        body { font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif; font-size: 12px; color: #334155; margin: 0; padding: 10px 20px; }
        table { width: 100%; border-collapse: collapse; }
        
        /* Paleta de colores */
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
        
        /* Totales y firmas */
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
                        echo '<table><tr>';
                        echo '<td style="color:#00AEEF; font-size:35px; font-weight:bold; padding-right:10px;">/</td>';
                        echo '<td><div class="text-dark company-name">'.htmlspecialchars($config['razon_social'] ?? 'PΛRADISE').'</div><div class="company-tagline">Soluciones Tecnológicas</div></td>';
                        echo '</tr></table>';
                    }
                ?>
            </td>
            <td style="width: 50%; vertical-align: top; text-align: right;">
                <h1 class="color-primary document-title"><?= $tipoNombre ?></h1>
                
                <table style="width: 100%; margin-top: 15px; font-size: 11px;">
                    <tr>
                        <td class="text-gray" style="text-align: right; padding-right: 15px;">Nro. Comprobante:</td>
                        <td class="text-dark" style="text-align: right; font-weight: bold;"><?= htmlspecialchars($numeroComprobante) ?></td>
                    </tr>
                    <tr>
                        <td class="text-gray" style="text-align: right; padding-right: 15px;">Fecha Emisión:</td>
                        <td class="text-dark" style="text-align: right;"><?= htmlspecialchars($fechaTexto) ?></td>
                    </tr>
                    <tr>
                        <td class="text-gray" style="text-align: right; padding-right: 15px;">Hora:</td>
                        <td class="text-dark" style="text-align: right;"><?= htmlspecialchars($horaTexto) ?></td>
                    </tr>
                    <tr>
                        <td class="text-gray" style="text-align: right; padding-right: 15px;">Moneda:</td>
                        <td class="text-dark" style="text-align: right;"><?= ($venta['moneda'] ?? 'PEN') === 'PEN' ? 'Soles (PEN)' : 'Dólares (USD)' ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ================= CLIENTE Y EMPRESA ================= -->
    <table class="info-table">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 20px;">
                <div class="info-title color-primary">FACTURADO A:</div>
                <h2 class="client-name text-dark"><?= htmlspecialchars($venta['cliente_nombre'] ?? $venta['razon_social'] ?? 'Cliente Genérico') ?></h2>
                <div class="text-gray" style="line-height: 1.6;">
                    Doc: <?= htmlspecialchars($venta['cliente_numero_doc'] ?? '-') ?><br>
                    <?= !empty($venta['cliente_direccion']) ? htmlspecialchars($venta['cliente_direccion']) . '<br>' : '' ?>
                </div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div class="info-title text-dark">DATOS DEL EMISOR:</div>
                <div class="text-gray" style="line-height: 1.6;">
                    <strong>RUC:</strong> <?= htmlspecialchars($config['ruc'] ?? '20123456789') ?><br>
                    <?= htmlspecialchars($config['direccion'] ?? 'Pucallpa - Ucayali - Perú') ?><br>
                    Teléfono: <?= htmlspecialchars($config['telefono'] ?? '-') ?><br>
                    Vendedor: <?= htmlspecialchars($venta['vendedor_nombre'] ?? 'Administrador') ?>
                </div>
            </td>
        </tr>
    </table>

    <!-- ================= TABLA DE PRODUCTOS ================= -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="bg-primary" style="text-align: center; width: 8%;">NO.</th>
                <th class="bg-primary" style="text-align: left; width: 47%;">DESCRIPCIÓN DEL PRODUCTO</th>
                <th class="bg-dark" style="text-align: center; width: 15%;">CANT.</th>
                <th class="bg-dark" style="text-align: right; width: 15%;">P. UNIT</th>
                <th class="bg-dark" style="text-align: right; width: 15%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php 
                $items = $venta['items'] ?? $venta['items_json'] ?? [];
                if (is_string($items)) $items = json_decode($items, true);

                $sumaTotalItems = 0; // <--- VARIABLE PARA SUMAR EL TOTAL DINÁMICAMENTE

                if (!empty($items)):
                    $contador = 1;
                    foreach ($items as $item):
                        $cantidad = (float)($item['cantidad'] ?? 1);
                        $descripcion = $item['descripcion_item'] ?? $item['descripcion'] ?? $item['nombre'] ?? '';
                        $precioUnit = (float)($item['precio_unitario_pen'] ?? $item['precio_unit'] ?? $item['valor_unitario'] ?? 0);
                        $totalItem = (float)($item['total'] ?? ($cantidad * $precioUnit));
                        
                        $sumaTotalItems += $totalItem; // <--- ACUMULAMOS LA SUMA
            ?>
            <tr>
                <td style="text-align: center; color: #94a3b8;"><?= str_pad((string)$contador, 2, '0', STR_PAD_LEFT) ?></td>
                <td style="font-weight: bold; color: #334155;">
                    <?= htmlspecialchars($descripcion) ?>
                    <?php if(!empty($item['unidad_medida']) && $item['unidad_medida'] !== 'NIU'): ?>
                        <span style="font-size: 9px; font-weight: normal; color: #94a3b8;">(<?= htmlspecialchars($item['unidad_medida']) ?>)</span>
                    <?php endif; ?>
                </td>
                <td style="text-align: center; color: #64748b;"><?= number_format($cantidad, 2) ?></td>
                <td style="text-align: right; color: #64748b;"><?= number_format($precioUnit, 2) ?></td>
                <td style="text-align: right; font-weight: bold; color: #334155;"><?= number_format($totalItem, 2) ?></td>
            </tr>
            <?php 
                    $contador++;
                    endforeach;
                else: 
            ?>
            <tr>
                <td colspan="5" style="text-align: center; padding: 20px; color: #94a3b8;">No hay productos registrados en esta venta.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ================= TOTALES Y MÉTODO DE PAGO ================= -->
    <?php
        // CÁLCULO INTELIGENTE DE TOTALES
        // Si el controlador no mandó la variable total, usamos la suma dinámica que acabamos de hacer
        $docTotal = !empty($venta['total']) && (float)$venta['total'] > 0 ? (float)$venta['total'] : $sumaTotalItems;
        $docIgv = (float)($venta['igv'] ?? 0);
        $docSubtotal = !empty($venta['subtotal']) && (float)$venta['subtotal'] > 0 ? (float)$venta['subtotal'] : ($docTotal - $docIgv);
    ?>
    <table class="bottom-table">
        <tr>
            <td style="width: 50%; vertical-align: bottom;">
                <div class="info-title text-dark">MÉTODO DE PAGO</div>
                <table style="width: 100%; font-size: 11px; margin-top: 10px;">
                    <tr>
                        <td class="text-gray" style="width: 120px; padding-bottom: 5px;">Forma de pago:</td>
                        <td class="text-dark" style="font-weight: bold; padding-bottom: 5px;"><?= htmlspecialchars($metodoPagoTexto) ?></td>
                    </tr>
                    <tr>
                        <td class="text-gray" style="padding-bottom: 5px;">Moneda Oficial:</td>
                        <td class="text-dark" style="font-weight: bold;"><?= ($venta['moneda'] ?? 'PEN') === 'PEN' ? 'Soles (S/)' : 'Dólares ($)' ?></td>
                    </tr>
                </table>
                
                <div class="signature-line">
                    <?= htmlspecialchars($config['razon_social'] ?? 'La Empresa') ?><br>
                    Sello / Firma Autorizada
                </div>
            </td>
            
            <td style="width: 10%;"></td> 
            
            <td style="width: 40%; vertical-align: bottom;">
                <table class="totals-block">
                    <tr>
                        <td class="text-gray" style="text-align: right; border-bottom: 1px solid #f1f5f9;">Subtotal:</td>
                        <td class="text-dark" style="text-align: right; font-weight: bold; border-bottom: 1px solid #f1f5f9;">S/ <?= number_format($docSubtotal, 2) ?></td>
                    </tr>
                    
                    <?php if ($docIgv > 0): ?>
                    <tr>
                        <td class="text-gray" style="text-align: right; border-bottom: 1px solid #f1f5f9;">I.G.V (18%):</td>
                        <td class="text-dark" style="text-align: right; font-weight: bold; border-bottom: 1px solid #f1f5f9;">S/ <?= number_format($docIgv, 2) ?></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td class="text-gray" style="text-align: right; border-bottom: 1px solid #f1f5f9;">Op. Exonerada:</td>
                        <td class="text-dark" style="text-align: right; font-weight: bold; border-bottom: 1px solid #f1f5f9;">S/ <?= number_format($docTotal, 2) ?></td>
                    </tr>
                    <?php endif; ?>

                    <tr class="total-row">
                        <td class="bg-primary" style="text-align: right; padding: 12px 10px; border-radius: 4px 0 0 4px;">Total:</td>
                        <td class="bg-primary" style="text-align: right; padding: 12px 10px; border-radius: 0 4px 4px 0;">S/ <?= number_format($docTotal, 2) ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ================= FOOTER ================= -->
    <div class="footer">
        <div class="color-primary" style="font-size: 14px; font-weight: bold; margin-bottom: 15px;">¡Gracias por su preferencia!</div>
        <table style="width: 100%;">
            <tr>
                <td style="width: 40%; vertical-align: top;">
                    <div class="text-dark" style="font-weight: bold; margin-bottom: 5px;">Contáctanos:</div>
                    Teléfono: <?= htmlspecialchars($config['telefono'] ?? '-') ?><br>
                    Email: contacto@tuempresa.com<br>
                    Web: www.paradise.com
                </td>
                <td style="width: 60%; vertical-align: top; padding-left: 20px;">
                    <div class="text-dark" style="font-weight: bold; margin-bottom: 5px;">Términos y Condiciones:</div>
                    Representación impresa del comprobante de venta electrónico. Los productos adquiridos están sujetos a las políticas de garantía vigentes. Consulte su documento electrónico en nuestra plataforma web.
                </td>
            </tr>
        </table>
        <?php if ($ubicacionQr): ?>
            <div style="text-align: center; margin-top: 24px;">
                <img src="<?= htmlspecialchars($ubicacionQr, ENT_QUOTES, 'UTF-8') ?>" alt="Ubicación de la empresa" style="width: 115px; height: 115px;">
                <div class="text-gray" style="margin-top: 5px;">Escanea para ver la ubicación de la empresa</div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>