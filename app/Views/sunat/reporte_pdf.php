<?php
/**
 * @var array $comprobantes
 */
$comprobantes = $comprobantes ?? [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Comprobantes SUNAT</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 15px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        .header h2 { margin: 0; color: #1e3a8a; font-size: 18px; }
        .header p { margin: 3px 0; color: #64748b; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #2563eb; color: #fff; padding: 7px 5px; text-align: left; font-size: 10px; text-transform: uppercase; }
        td { padding: 6px 5px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge { padding: 3px 6px; border-radius: 4px; font-weight: bold; font-size: 9px; }
        .badge-aceptado { background-color: #dcfce7; color: #15803d; }
        .badge-rechazado { background-color: #fee2e2; color: #b91c1c; }
        .badge-pendiente { background-color: #f1f5f9; color: #475569; }
        .footer { margin-top: 25px; text-align: right; font-size: 10px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <h2>REPORTE DE COMPROBANTES ELECTRÓNICOS</h2>
        <p>Generado el: <?= date('d/m/Y H:i:s') ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Comprobante</th>
                <th>Tipo</th>
                <th>Cliente</th>
                <th>Doc. Cliente</th>
                <th class="text-right">Total</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($comprobantes)): ?>
                <tr>
                    <td colspan="7" class="text-center" style="padding: 20px;">No se encontraron registros.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($comprobantes as $c): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($c['fecha_emision_real'])) ?></td>
                        <td><strong><?= htmlspecialchars($c['serie'] . '-' . $c['numero']) ?></strong></td>
                        <td><?= $c['tipo_comprobante'] === '01' ? 'Factura' : 'Boleta' ?></td>
                        <td><?= htmlspecialchars($c['cliente_nombre'] ?? 'Cliente General') ?></td>
                        <td><?= htmlspecialchars($c['cliente_doc'] ?? '-') ?></td>
                        <td class="text-right"><strong>S/ <?= number_format((float)$c['total'], 2) ?></strong></td>
                        <td class="text-center">
                            <?php 
                                $est = strtolower($c['estado_sunat'] ?? $c['estado'] ?? 'pendiente');
                                $badgeClass = 'badge-pendiente';
                                if($est === 'aceptado' || $est === '3') $badgeClass = 'badge-aceptado';
                                elseif($est === 'rechazado' || $est === '4') $badgeClass = 'badge-rechazado';
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= strtoupper($est) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Total de Comprobantes: <?= count($comprobantes) ?></p>
    </div>
</body>
</html>