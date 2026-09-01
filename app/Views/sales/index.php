<?php $title = 'Ventas'; ?>
<div class="space-y-5">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">Historial de Ventas</h2>
            <p class="text-sm text-gray-500">Comprobantes emitidos del período seleccionado</p>
        </div>
        <a href="<?= baseUrl('pos') ?>" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl shadow transition-all duration-200 hover:shadow-emerald-200 hover:-translate-y-0.5 active:translate-y-0">
            <i class="fa-solid fa-cash-register"></i> Nueva Venta (POS)
        </a>
    </div>

    <!-- Filtros -->
    <form method="GET" class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="N° comprobante, cliente..." class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 focus:outline-none focus:border-sky-400">
            <select name="tipo" class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 focus:outline-none focus:border-sky-400">
                <option value="">Todos los comprobantes</option>
                <option value="01" <?= ($_GET['tipo'] ?? '') === '01' ? 'selected' : '' ?>>Factura</option>
                <option value="03" <?= ($_GET['tipo'] ?? '') === '03' ? 'selected' : '' ?>>Boleta</option>
                <option value="NV" <?= ($_GET['tipo'] ?? '') === 'NV' ? 'selected' : '' ?>>Nota de Venta / Recibo interno</option>
            </select>
            <input type="date" name="desde" value="<?= htmlspecialchars($_GET['desde'] ?? date('Y-m-01')) ?>" class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 focus:outline-none focus:border-sky-400">
            <input type="date" name="hasta" value="<?= htmlspecialchars($_GET['hasta'] ?? date('Y-m-d')) ?>" class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 focus:outline-none focus:border-sky-400">
            <button type="submit" class="bg-slate-600 hover:bg-slate-700 text-white px-4 py-2 rounded-lg text-sm transition">
                <i class="fa-solid fa-filter mr-1"></i> Filtrar
            </button>
        </div>
    </form>

    <!-- Tabla -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700 text-gray-600 dark:text-gray-300 text-left">
                    <tr>
                        <th class="px-4 py-3 font-semibold">N° Comprobante</th>
                        <th class="px-4 py-3 font-semibold">Tipo</th>
                        <th class="px-4 py-3 font-semibold">Cliente</th>
                        <th class="px-4 py-3 font-semibold">Fecha</th>
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
                            <td colspan="10" class="text-center py-12 text-gray-400">
                                <i class="fa-solid fa-receipt text-3xl mb-2 block"></i>
                                No hay ventas en el período seleccionado
                            </td>
                        </tr>
                    <?php else: ?>
                <?php foreach ($resultado['data'] as $venta): ?>
                        <?php
                            $tipoLabels = ['01' => 'Factura', '03' => 'Boleta', 'NV' => 'Nota Venta'];
                            $tipoLabel  = $tipoLabels[$venta['tipo_comprobante'] ?? ''] ?? $venta['tipo_comprobante'];
                            $estadoClass = ($venta['estado'] ?? '') === 'activo' || ($venta['estado'] ?? '') === 'pendiente' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                            $sunatStatus = $venta['estado_sunat'] ?? 'pendiente';
                            $sunatAccepted = $sunatStatus === 'aceptado';
                            $sunatClass  = $sunatAccepted ? 'text-green-500' : 'text-orange-400';
                            $sunatIcon   = $sunatAccepted ? 'fa-check-circle' : 'fa-clock';
                        ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition">
                            <td class="px-4 py-3 font-mono font-medium text-sky-600 dark:text-sky-400"><?= htmlspecialchars($venta['numero_comprobante'] ?? '-') ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-xs font-medium <?= $venta['tipo_comprobante'] === '01' ? 'bg-violet-100 text-violet-700' : ($venta['tipo_comprobante'] === '03' ? 'bg-sky-100 text-sky-700' : 'bg-gray-100 text-gray-700') ?>">
                                    <?= $tipoLabel ?>
                                </span>
                            </td>
                            <td class="px-4 py-3"><?= htmlspecialchars($venta['cliente_nombre'] ?? 'Sin cliente') ?></td>
                            <td class="px-4 py-3 text-gray-500"><?= isset($venta['fecha_emision']) ? date('d/m/Y H:i', strtotime($venta['fecha_emision'])) : '-' ?></td>
                            <td class="px-4 py-3 text-right">S/ <?= number_format((float)($venta['subtotal'] ?? 0), 2) ?></td>
                            <td class="px-4 py-3 text-right text-orange-500">S/ <?= number_format((float)($venta['igv'] ?? 0), 2) ?></td>
                            <td class="px-4 py-3 text-right font-semibold">S/ <?= number_format((float)($venta['total'] ?? 0), 2) ?></td>
                            <td class="px-4 py-3 text-center">
                                <i class="fa-solid <?= $sunatIcon ?> <?= $sunatClass ?>" title="<?= $sunatAccepted ? 'Aceptado por SUNAT' : 'Pendiente / No enviado' ?>"></i>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $estadoClass ?>">
                                    <?php $estadoTexto = $venta['estado'] ?? 'pendiente'; echo $estadoTexto === 'pendiente' ? 'Activo' : ($estadoTexto === 'anulada' ? 'Anulado' : ucfirst($estadoTexto)); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <?php if (($venta['estado'] ?? '') !== 'anulada'): ?>
                                <button onclick="anularVenta(<?= $venta['id'] ?>)" class="text-red-400 hover:text-red-600 transition p-1" title="Anular">
                                    <i class="fa-solid fa-ban"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Totales del período -->
        <?php if (!empty($resultado['data'])): ?>
        <div class="px-4 py-3 border-t border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-700/50 flex flex-wrap gap-6 text-sm">
            <span class="text-gray-500"><?= $resultado['total'] ?? 0 ?> registros encontrados</span>
        </div>
        <?php endif; ?>

        <!-- Paginación -->
        <?php if (($totalPaginas ?? 1) > 1): ?>
        <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-slate-700">
            <p class="text-sm text-gray-500">Página <?= $paginaActual ?> de <?= $totalPaginas ?></p>
            <div class="flex gap-2">
                <?php if ($paginaActual > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $paginaActual - 1])) ?>" class="px-3 py-1 rounded border dark:border-slate-600 text-sm hover:bg-gray-100 dark:hover:bg-slate-700 transition">‹ Anterior</a>
                <?php endif; ?>
                <?php if ($paginaActual < $totalPaginas): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $paginaActual + 1])) ?>" class="px-3 py-1 rounded border dark:border-slate-600 text-sm hover:bg-gray-100 dark:hover:bg-slate-700 transition">Siguiente ›</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function anularVenta(id) {
    const modalId = 'anularModal';
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
            body: `id=${id}&motivo=${encodeURIComponent(motivo)}&_token=<?= csrf_token() ?>`
        })
        .then(r => r.json())
        .then(data => {
            closeModal(modalId);
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => location.reload(), 1200);
        });
    };
}
</script>
