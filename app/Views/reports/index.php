<?php $title = 'Reportes'; ?>
<div class="space-y-5">

    <!-- Header + Filtros de Fecha -->
    <div class="bg-white dark:bg-slate-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-slate-700">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-800 dark:text-white">Reportes de Ventas</h2>
                <p class="text-sm text-gray-500">Análisis del período seleccionado</p>
            </div>
            <form method="GET" class="flex flex-wrap gap-2">
                <input type="date" name="desde" value="<?= htmlspecialchars($fechaDesde ?? date('Y-m-01')) ?>" class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 focus:outline-none focus:border-sky-400">
                <input type="date" name="hasta" value="<?= htmlspecialchars($fechaHasta ?? date('Y-m-d')) ?>" class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 focus:outline-none focus:border-sky-400">
                <select name="tipo" class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 focus:outline-none focus:border-sky-400">
                    <option value="">Todos los comprobantes</option>
                    <option value="01" <?= ($_GET['tipo'] ?? '') === '01' ? 'selected' : '' ?>>Factura</option>
                    <option value="03" <?= ($_GET['tipo'] ?? '') === '03' ? 'selected' : '' ?>>Boleta</option>
                    <option value="NV" <?= ($_GET['tipo'] ?? '') === 'NV' ? 'selected' : '' ?>>Nota de Venta</option>
                </select>
                <button type="submit" class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg text-sm transition">
                    <i class="fa-solid fa-chart-line mr-1"></i> Generar
                </button>
            </form>
        </div>
    </div>

    <!-- Tarjetas Resumen -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total Ventas</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">S/ <?= number_format((float)($resumen['total_ventas'] ?? 0), 2) ?></p>
            <p class="text-xs text-gray-400 mt-1"><?= (int)($resumen['cantidad_ventas'] ?? 0) ?> comprobantes</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total Neto (sin IGV)</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">S/ <?= number_format((float)($resumen['total_neto'] ?? 0), 2) ?></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total IGV</p>
            <p class="text-2xl font-bold text-orange-500 mt-1">S/ <?= number_format((float)($resumen['total_igv'] ?? 0), 2) ?></p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Descuentos</p>
            <p class="text-2xl font-bold text-red-500 mt-1">S/ <?= number_format((float)($resumen['total_descuentos'] ?? 0), 2) ?></p>
        </div>
    </div>

    <!-- Gráfico de Ventas Diarias -->
    <div class="bg-white dark:bg-slate-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-slate-700">
        <h3 class="font-semibold text-gray-700 dark:text-gray-200 mb-4">Evolución de Ventas</h3>
        <canvas id="reporteChart" height="80"></canvas>
    </div>

    <!-- Tablas de Análisis -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <!-- Top Productos -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200">Top 10 Productos</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-500 font-medium">Producto</th>
                        <th class="px-4 py-2 text-right text-gray-500 font-medium">Cantidad</th>
                        <th class="px-4 py-2 text-right text-gray-500 font-medium">Ingreso</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    <?php if (empty($topProductos)): ?>
                        <tr><td colspan="3" class="text-center py-6 text-gray-400">Sin datos</td></tr>
                    <?php else: ?>
                        <?php foreach ($topProductos as $prod): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                            <td class="px-4 py-2"><?= htmlspecialchars($prod['nombre']) ?></td>
                            <td class="px-4 py-2 text-right"><?= number_format((float)$prod['total_vendido'], 0) ?></td>
                            <td class="px-4 py-2 text-right font-medium text-sky-600">S/ <?= number_format((float)$prod['ingreso'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Por Método de Pago -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700">
                <h3 class="font-semibold text-gray-700 dark:text-gray-200">Por Método de Pago</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-500 font-medium">Método</th>
                        <th class="px-4 py-2 text-right text-gray-500 font-medium">Cantidad</th>
                        <th class="px-4 py-2 text-right text-gray-500 font-medium">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    <?php if (empty($porMetodo)): ?>
                        <tr><td colspan="3" class="text-center py-6 text-gray-400">Sin datos</td></tr>
                    <?php else: ?>
                        <?php foreach ($porMetodo as $met): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                            <td class="px-4 py-2 capitalize"><?= htmlspecialchars($met['metodo_pago']) ?></td>
                            <td class="px-4 py-2 text-right"><?= (int)$met['cantidad'] ?></td>
                            <td class="px-4 py-2 text-right font-medium text-sky-600">S/ <?= number_format((float)$met['total'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    const datos = <?= json_encode($ventasDiarias ?? []) ?>;
    const labels = datos.map(d => {
        const f = new Date(d.fecha + 'T00:00:00');
        return f.toLocaleDateString('es-PE', { day: '2-digit', month: 'short' });
    });
    const valores = datos.map(d => parseFloat(d.total));

    const ctx = document.getElementById('reporteChart');
    if (ctx && typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Ventas (S/)',
                    data: valores,
                    backgroundColor: 'rgba(14,165,233,0.7)',
                    borderColor: '#0ea5e9',
                    borderWidth: 1,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => 'S/ ' + v.toFixed(2) } }
                }
            }
        });
    }
})();
</script>
