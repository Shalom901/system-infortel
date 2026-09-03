<?php
$data = $data ?? ['venta' => [], 'config' => []];
$v = $data['venta'] ?? [];
$c = $data['config'] ?? [];
$ubicacionQr = $data['ubicacionQr'] ?? null;

function getTipoComprobante($tipo) {
    switch ($tipo) {
        case '01': return 'FACTURA ELECTRÓNICA';
        case '03': return 'BOLETA DE VENTA ELECTRÓNICA';
        case '07': return 'NOTA DE CRÉDITO ELECTRÓNICA';
        case '08': return 'NOTA DE DÉBITO ELECTRÓNICA';
        case 'NV': return 'NOTA DE VENTA';
        default: return 'TICKET';
    }
}

function getBankIconBase64Ticket($filename, $textoRespaldo) {
    $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . $filename;
    
    if (file_exists($path)) {
        // filter: grayscale(100%) es la magia para que la ticketera lo imprima nítido
        return '<img src="data:image/png;base64,' . base64_encode(file_get_contents($path)) . '" style="height: 12px; vertical-align: middle; margin-right: 4px; filter: grayscale(100%);">';
    }
    
    // Si no encuentra la imagen, muestra el texto entre corchetes [BCP]
    return '<span class="bold">[' . $textoRespaldo . ']</span> ';
}

function getNombrePagoTicket($metodo) {
    $nombres = [
        'efectivo' => 'Efectivo', 'yape' => 'Yape', 'plin' => 'Plin',
        'transferencia' => 'Transferencia', 'bcp' => 'BCP', 'interbank' => 'Interbank',
        'bbva' => 'BBVA', 'scotiabank' => 'Scotiabank', 'tarjeta_credito' => 'Tarjeta',
        'tarjeta_debito' => 'Tarjeta', 'usd' => 'Dólares', 'otro' => 'Otro',
    ];
    $metodo = strtolower(trim((string)$metodo));
    return $nombres[$metodo] ?? ucfirst($metodo);
}

$logoPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'logo1.png';
$logoBase64 = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?= $v['serie'] ?>-<?= $v['numero'] ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
        }
        body {
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        .ticket {
            width: 80mm;
            max-width: 100%;
            background: #fff;
            padding: 15px;
            box-shadow: 0 0 5px rgba(0,0,0,0.2);
        }
        .center {
            text-align: center;
        }
        .bold {
            font-weight: bold;
        }
        .mb-1 { margin-bottom: 5px; }
        .mb-2 { margin-bottom: 10px; }
        .mb-3 { margin-bottom: 15px; }
        
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            vertical-align: top;
        }
        .text-right {
            text-align: right;
        }
        .col-cant { width: 15%; }
        .col-desc { width: 55%; }
        .col-total { width: 30%; }
        
        .totals-table td {
            padding: 2px 0;
        }
        
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            .ticket {
                box-shadow: none;
                padding: 0;
                width: 100%;
            }
        }

        .totals-table td {
            padding: 2px 0;
        }

        /* ======================================================== */
        /* FILTRO DE ALTO CONTRASTE PARA IMPRESORA TÉRMICA (NUEVO)  */
        /* ======================================================== */
        .ticket img {
            filter: grayscale(100%) contrast(200%) brightness(80%);
            image-rendering: pixelated;
        }
        
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            .ticket {
                box-shadow: none;
                padding: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="ticket">
        <!-- Encabezado de la empresa -->
        <div class="center mb-3">
            <?php if ($logoBase64): ?>
                <img src="<?= $logoBase64 ?>" alt="Logo Empresa" style="max-width: 150px; max-height: 100px; object-fit: contain; margin-bottom: 6px;">
            <?php endif; ?>
            <h2 class="bold mb-1"><?= htmlspecialchars($c['razon_social'] ?? 'Empresa') ?></h2>
            <p>RUC: <?= htmlspecialchars($c['ruc'] ?? '') ?></p>
            <p><?= htmlspecialchars($c['direccion'] ?? '') ?></p>
            <p><?= htmlspecialchars($c['distrito'] ?? '') ?> - <?= htmlspecialchars($c['provincia'] ?? '') ?></p>
            <p>Tel: +51 966 422 570<?= htmlspecialchars($c['telefono'] ?? '') ?></p>
        </div>
        
        <div class="divider"></div>
        
        <!-- Datos del comprobante -->
        <div class="center mb-2">
            <p class="bold"><?= getTipoComprobante($v['tipo_comprobante']) ?></p>
            <p class="bold"><?= $v['serie'] ?>-<?= str_pad($v['numero'], 8, '0', STR_PAD_LEFT) ?></p>
        </div>
        
        <div class="divider"></div>
        
        <!-- Datos del cliente -->
        <div class="mb-2">
            <p>Fecha: <?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></p>
            <p>Cliente: <?= htmlspecialchars($v['cliente_nombre'] ?? 'CLIENTE VARIOS') ?></p>
            <?php if (!empty($v['cliente_numero_doc'])): ?>
                <p>Doc: <?= htmlspecialchars($v['cliente_numero_doc']) ?></p>
            <?php endif; ?>
            <p>Cajero: <?= htmlspecialchars($v['usuario_nombre'] ?? 'Admin') ?></p>
        </div>
        
        <div class="divider"></div>
        
        <!-- Detalle de productos -->
        <table class="mb-2">
            <thead>
                <tr>
                    <th class="col-cant">Cant.</th>
                    <th class="col-desc">Descripción</th>
                    <th class="col-total text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($v['items'] as $item): ?>
                    <tr>
                        <td class="col-cant"><?= number_format($item['cantidad'], 2) ?></td>
                        <td class="col-desc">
                            <?= htmlspecialchars($item['descripcion_item']) ?>
                            <br>
                            <small>S/ <?= number_format($item['precio_unitario_pen'], 2) ?></small>
                        </td>
                        <td class="col-total text-right">
                            S/ <?= number_format($item['subtotal_item'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="divider"></div>
        
        <!-- Totales -->
        <table class="totals-table mb-2">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">S/ <?= number_format($v['subtotal_gravado'], 2) ?></td>
            </tr>

            <tr>
                <td class="bold">TOTAL A PAGAR:</td>
                <td class="bold text-right">S/ <?= number_format($v['total_pen'], 2) ?></td>
            </tr>
        </table>
        
        <!-- Métodos de Pago -->
        <?php if (!empty($v['pagos'])): ?>
            <div class="divider"></div>
            <div class="mb-2">
                <p class="bold mb-1">MÉTODOS DE PAGO:</p>
                <?php foreach ($v['pagos'] as $pago): ?>
                    <p>
                        - <?= htmlspecialchars(getNombrePagoTicket($pago['metodo_pago'] ?? '')) ?>: 
                        S/ <?= number_format($pago['monto_pen'], 2) ?>
                    </p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="divider"></div>

        <div class="divider"></div>
        
       <!-- Cuentas y Canales de Pago (Incluyendo Yape) -->
        <div class="divider"></div>
        <div class="mb-2" style="font-size: 11px;">
            <p class="bold mb-1">MÉTODOS DE PAGO / CANALES:</p>
            
            <!-- BCP -->
            <div style="margin-bottom: 5px;">
                <p class="bold" style="display: flex; align-items: center;">
                    <?= getBankIconBase64Ticket('bcp.png', 'BCP') ?>
                    <span>BCP - Cuenta en Soles</span>
                </p>
                <p>Nro: 480-2212912029</p>
                <p>CCI: 00248000221291202925</p>
            </div>
            
            <!-- Interbank -->
            <div style="margin-bottom: 5px;">
                <p class="bold" style="display: flex; align-items: center;">
                    <?= getBankIconBase64Ticket('interbank.png', 'INT') ?>
                    <span>Interbank - Cuenta en Soles</span>
                </p>
                <p>Nro: 200-3004005006</p>
                <p>CCI: 003-200-03004005006-12</p>
            </div>

            <!-- YAPE (INFORTEL COMP E.I.R.L) -->
            <div>
                <p class="bold" style="display: flex; align-items: center;">
                    <?= getBankIconBase64Ticket('yape.png', 'YAPE') ?>
                    <span>YAPE - INFORTEL COMP</span>
                </p>
                <p>Titular: INFORTEL COMP E.I.R.L.</p>
                <p class="bold">Celular: +51 966 422 570</p>
            </div>
        </div>

        <?php if ($ubicacionQr): ?>
            <div class="center mb-2">
                <img src="<?= htmlspecialchars($ubicacionQr, ENT_QUOTES, 'UTF-8') ?>" alt="Ubicación de la empresa" style="width: 125px; height: 125px; image-rendering: pixelated;">
                <p>Escanea para ver nuestra ubicación</p>
            </div>
            <div class="divider"></div>
        <?php endif; ?>
        
        <div class="center">
            <p class="mb-1">¡Gracias por su preferencia!</p>
            
            <p>Representación impresa del comprobante electrónico.</p>
            <br>
            <h1>Términos y Condiciones: </h1>
            <p>1. La garantía de los productos está sujeta a las políticas del fabricante.</p>
            <p>2. No se aceptan devoluciones de los productos una vez abiertos.</p>
            <p>3. Para cambios o devoluciones, presentar el comprobante de compra y el producto en su empaque original.</p>
            <p>4. Los precios y promociones están sujetos a cambios sin previo aviso.</p>
            <p>5. La empresa no se hace responsable por daños ocasionados por el uso indebido de los productos.</p>
        
            <br>© Todos los derechos reservados - InfortelComp </br>
        </div>
        
    </div>
</body>
</html>
