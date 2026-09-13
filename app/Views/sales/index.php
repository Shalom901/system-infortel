<?php 
/**
 * Vista de Historial de Ventas
 * @var array $resultado
 * @var int   $totalPaginas
 * @var int   $paginaActual
 */

$title        = 'Ventas'; 
$resultado    = $resultado    ?? ['data' => [], 'total' => 0];
$paginaActual = $paginaActual ?? (int)($_GET['page'] ?? 1);
$totalPaginas = $totalPaginas ?? (int)ceil(max(1, (int)($resultado['total'] ?? 0)) / 25);
?>
<div class="space-y-5">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">Historial de Ventas</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400">Comprobantes emitidos del período seleccionado</p>
        </div>
        <a href="<?= baseUrl('pos') ?>" data-turbo="false" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl shadow transition-all duration-200 hover:shadow-emerald-200 hover:-translate-y-0.5 active:translate-y-0">
            <i class="fa-solid fa-cash-register"></i> Nueva Venta (POS)
        </a>
    </div>

    <!-- Filtros -->
    <form method="GET" class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="N° comprobante, cliente..." class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 dark:text-white focus:outline-none focus:border-sky-400">
            <select name="tipo" class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 dark:text-white focus:outline-none focus:border-sky-400">
                <option value="">Todos los comprobantes</option>
                <option value="01" <?= ($_GET['tipo'] ?? '') === '01' ? 'selected' : '' ?>>Factura</option>
                <option value="03" <?= ($_GET['tipo'] ?? '') === '03' ? 'selected' : '' ?>>Boleta</option>
                <option value="NV" <?= ($_GET['tipo'] ?? '') === 'NV' ? 'selected' : '' ?>>Nota de Venta / Recibo interno</option>
            </select>
            <input type="date" name="desde" value="<?= htmlspecialchars($_GET['desde'] ?? date('Y-m-01')) ?>" class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 dark:text-white focus:outline-none focus:border-sky-400">
            <input type="date" name="hasta" value="<?= htmlspecialchars($_GET['hasta'] ?? date('Y-m-d')) ?>" class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 dark:text-white focus:outline-none focus:border-sky-400">
            <button type="submit" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-filter"></i> Filtrar
            </button>
        </div>
    </form>

    <!-- Tabla de Ventas -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700/60 text-gray-600 dark:text-gray-300 text-left text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 font-semibold">N° Comprobante</th>
                        <th class="px-4 py-3 font-semibold">Tipo</th>
                        <th class="px-4 py-3 font-semibold">Cliente</th>
                        <th class="px-4 py-3 font-semibold">Fecha y Hora</th>
                        <th class="px-4 py-3 font-semibold text-right">Subtotal</th>
                        <th class="px-4 py-3 font-semibold text-right">IGV</th>
                        <th class="px-4 py-3 font-semibold text-right">Total</th>
                        <th class="px-4 py-3 font-semibold text-center">SUNAT</th>
                        <th class="px-4 py-3 font-semibold text-center">Estado</th>
                        <th class="px-4 py-3 font-semibold text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    <?php if (empty($resultado['data'])): ?>
                        <tr>
                            <td colspan="10" class="text-center py-12 text-gray-400 dark:text-slate-500">
                                <i class="fa-solid fa-receipt text-3xl mb-2 block opacity-30"></i>
                                No hay ventas registradas en el período seleccionado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($resultado['data'] as $venta): ?>
                            <?php
                                $tipoLabels = ['01' => 'Factura', '03' => 'Boleta', 'NV' => 'Nota Venta'];
                                $tipoLabel  = $tipoLabels[$venta['tipo_comprobante'] ?? ''] ?? $venta['tipo_comprobante'];
                                $estadoClass = ($venta['estado'] ?? '') === 'activo' || ($venta['estado'] ?? '') === 'pendiente' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400';
                                $sunatStatus = $venta['estado_sunat'] ?? 'pendiente';
                                $sunatAccepted = $sunatStatus === 'aceptado';
                                $sunatClass  = $sunatAccepted ? 'text-emerald-500' : 'text-amber-400';
                                $sunatIcon   = $sunatAccepted ? 'fa-check-circle' : 'fa-clock';
                                $numComp     = htmlspecialchars($venta['numero_comprobante'] ?? ($venta['serie'] . '-' . str_pad((string)($venta['numero'] ?? 0), 8, '0', STR_PAD_LEFT)));
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/40 transition-colors">
                                <!-- N° Comprobante (Enlace directo al PDF) -->
                                <td class="px-4 py-3 font-mono font-bold">
                                    <a href="/pos/pdf/<?= $venta['id'] ?>" target="_blank" class="text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1.5" title="Abrir comprobante PDF">
                                        <i class="fa-regular fa-file-lines text-xs opacity-70"></i>
                                        <?= $numComp ?>
                                    </a>
                                </td>

                                <!-- Tipo -->
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold <?= $venta['tipo_comprobante'] === '01' ? 'bg-violet-100 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400' : ($venta['tipo_comprobante'] === '03' ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400' : 'bg-gray-100 text-gray-700 dark:bg-slate-700 dark:text-slate-300') ?>">
                                        <?= $tipoLabel ?>
                                    </span>
                                </td>

                                <!-- Cliente -->
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">
                                    <?= htmlspecialchars($venta['cliente_nombre'] ?? 'Cliente General') ?>
                                </td>

                                <!-- Fecha y Hora exacta de despacho -->
                                <!-- Fecha y Hora real -->
                                <td class="px-4 py-3 text-gray-500 dark:text-slate-400 text-xs">
                                    <div class="font-medium text-slate-700 dark:text-slate-300">
                                        <?= date('d/m/Y', strtotime($venta['created_at'] ?? $venta['fecha_emision'])) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono">
                                        <i class="fa-regular fa-clock mr-1 text-[10px]"></i><?= date('h:i A', strtotime($venta['created_at'] ?? $venta['fecha_emision'])) ?>
                                    </div>
                                </td>

                                <!-- Montos -->
                                <td class="px-4 py-3 text-right font-medium">S/ <?= number_format((float)($venta['subtotal'] ?? 0), 2) ?></td>
                                <td class="px-4 py-3 text-right text-orange-500 font-medium">S/ <?= number_format((float)($venta['igv'] ?? 0), 2) ?></td>
                                <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">S/ <?= number_format((float)($venta['total'] ?? 0), 2) ?></td>

                                <!-- Estado SUNAT -->
                                <td class="px-4 py-3 text-center text-sm">
                                    <i class="fa-solid <?= $sunatIcon ?> <?= $sunatClass ?>" title="<?= $sunatAccepted ? 'Aceptado por SUNAT' : 'Pendiente / No enviado' ?>"></i>
                                </td>

                                <!-- Estado Venta -->
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $estadoClass ?>">
                                        <?php $estadoTexto = $venta['estado'] ?? 'pendiente'; echo $estadoTexto === 'pendiente' ? 'Activo' : ($estadoTexto === 'anulada' ? 'Anulado' : ucfirst($estadoTexto)); ?>
                                    </span>
                                </td>

                                <!-- ACCIONES (PDF, Ticket y Anular) -->
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <!-- 1. Botón Ver/Imprimir PDF A4 -->
                                        <a href="/pos/pdf/<?= $venta['id'] ?>" target="_blank" 
                                           class="w-7 h-7 flex items-center justify-center rounded-lg text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-500 hover:text-white dark:hover:bg-rose-500 dark:hover:text-white border border-rose-100 dark:border-rose-500/20 transition-all shadow-sm" 
                                           title="Ver Comprobante PDF (A4)">
                                            <i class="fa-solid fa-file-pdf text-xs"></i>
                                        </a>

                                        <!-- 2. Botón Imprimir Ticket Térmico (80mm) -->
                                        <a href="/pos/ticket/<?= $venta['id'] ?>" target="_blank" 
                                           onclick="window.open(this.href, 'ticket', 'width=400,height=600'); return false;"
                                           class="w-7 h-7 flex items-center justify-center rounded-lg text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10 hover:bg-amber-500 hover:text-white dark:hover:bg-amber-500 dark:hover:text-white border border-amber-100 dark:border-amber-500/20 transition-all shadow-sm" 
                                           title="Imprimir Ticket Térmico (80mm)">
                                            <i class="fa-solid fa-receipt text-xs"></i>
                                        </a>

                                        <!-- 3. Botón Anular Venta -->
                                        <?php if (($venta['estado'] ?? '') !== 'anulada'): ?>
                                        <button type="button" onclick="anularVenta(<?= $venta['id'] ?>)" 
                                                class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-red-500 bg-slate-50 dark:bg-slate-700/50 hover:bg-red-50 dark:hover:bg-red-900/20 border border-slate-200 dark:border-slate-600 transition-all shadow-sm" 
                                                title="Anular Venta">
                                            <i class="fa-solid fa-ban text-xs"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Totales del período -->
        <?php if (!empty($resultado['data'])): ?>
        <div class="px-4 py-3 border-t border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex flex-wrap gap-6 text-xs text-slate-500 dark:text-slate-400 font-medium">
            <span>Mostrando <?= count($resultado['data']) ?> de <?= $resultado['total'] ?? count($resultado['data']) ?> ventas registradas</span>
        </div>
        <?php endif; ?>

        <!-- Paginación -->
        <?php if ($totalPaginas > 1): ?>
        <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-slate-700 text-xs">
            <p class="text-gray-500 dark:text-slate-400 font-medium">Página <?= $paginaActual ?> de <?= $totalPaginas ?></p>
            <div class="flex gap-2">
                <?php if ($paginaActual > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $paginaActual - 1])) ?>" class="px-3 py-1.5 rounded border border-gray-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-700 transition font-medium">‹ Anterior</a>
                <?php endif; ?>
                <?php if ($paginaActual < $totalPaginas): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $paginaActual + 1])) ?>" class="px-3 py-1.5 rounded border border-gray-200 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-gray-100 dark:hover:bg-slate-700 transition font-medium">Siguiente ›</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function anularVenta(id) {
    const modalId = 'anularModal';
    if (typeof openModal === 'function') {
        openModal(modalId);
        document.getElementById(`${modalId}-title`).innerText = 'Anular Venta';
        document.getElementById(`${modalId}-content`).innerHTML = `
            <p class="mb-3">¿Está seguro de que desea anular este comprobante? Esta acción restaurará el stock y no se puede revertir.</p>
            <input id="motivoAnulacion" type="text" placeholder="Motivo de anulación..." class="w-full px-3 py-2 border rounded-lg text-sm" />
        `;
        document.getElementById(`${modalId}-confirm`).className = 'px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded text-sm';
        document.getElementById(`${modalId}-confirm`).onclick = function() {
            const motivo = document.getElementById('motivoAnulacion').value || 'Anulación solicitada';
            fetch('<?= baseUrl('ventas/anular') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `id=${id}&motivo=${encodeURIComponent(motivo)}&_token=<?= function_exists('csrf_token') ? csrf_token() : '' ?>`
            })
            .then(r => r.json())
            .then(data => {
                closeModal(modalId);
                if (typeof showToast === 'function') {
                    showToast(data.message, data.success ? 'success' : 'error');
                } else {
                    alert(data.message);
                }
                if (data.success) setTimeout(() => location.reload(), 1200);
            });
        };
    } else {
        if (confirm('¿Está seguro de que desea anular este comprobante? Esta acción restaurará el stock.')) {
            fetch('<?= baseUrl('ventas/anular') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `id=${id}&motivo=Anulacion+directa&_token=<?= function_exists('csrf_token') ? csrf_token() : '' ?>`
            })
            .then(r => r.json())
            .then(data => {
                alert(data.message || 'Venta procesada');
                if (data.success) location.reload();
            });
        }
    }
}
</script>