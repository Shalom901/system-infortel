<?php
/**
 * @var int $paginaActual
 * @var int $totalPaginas
 * @var array $resultado
 * @var array $proveedores
 */
$title = 'Compras'; 
?>
<div class="space-y-5">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">Compras</h2>
            <p class="text-sm text-gray-500">Registro de órdenes de compra a proveedores</p>
        </div>
        <button onclick="openModal('modalNuevaCompra')" class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
            <i class="fa-solid fa-plus"></i> Nueva Compra
        </button>
    </div>

    <!-- Filtros -->
    <form method="GET" class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Buscar por factura o proveedor..." class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 focus:outline-none focus:border-sky-400">
            <input type="date" name="desde" value="<?= htmlspecialchars($_GET['desde'] ?? '') ?>" class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 focus:outline-none focus:border-sky-400">
            <input type="date" name="hasta" value="<?= htmlspecialchars($_GET['hasta'] ?? '') ?>" class="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 focus:outline-none focus:border-sky-400">
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
                        <th class="px-4 py-3 font-semibold">N° Factura</th>
                        <th class="px-4 py-3 font-semibold">Proveedor</th>
                        <th class="px-4 py-3 font-semibold">Fecha</th>
                        <th class="px-4 py-3 font-semibold text-right">Subtotal</th>
                        <th class="px-4 py-3 font-semibold text-right">IGV</th>
                        <th class="px-4 py-3 font-semibold text-right">Total</th>
                        <th class="px-4 py-3 font-semibold text-center">Estado</th>
                        <th class="px-4 py-3 font-semibold text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    <?php if (empty($resultado['data'])): ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-gray-400">
                                <i class="fa-solid fa-truck text-3xl mb-2 block"></i>
                                No hay compras registradas
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($resultado['data'] as $compra): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition">
                            <td class="px-4 py-3 font-medium text-sky-600 dark:text-sky-400"><?= htmlspecialchars($compra['numero'] ?? '-') ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($compra['proveedor_nombre'] ?? '-') ?></td>
                            <td class="px-4 py-3 text-gray-500"><?= date('d/m/Y', strtotime($compra['fecha_emision'] ?? 'now')) ?></td>
                            <td class="px-4 py-3 text-right">S/ <?= number_format((float)($compra['subtotal'] ?? 0), 2) ?></td>
                            <td class="px-4 py-3 text-right">S/ <?= number_format((float)($compra['igv'] ?? 0), 2) ?></td>
                            <td class="px-4 py-3 text-right font-semibold">S/ <?= number_format((float)($compra['total'] ?? 0), 2) ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= ($compra['estado'] ?? '') === 'activo' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                    <?= ucfirst($compra['estado'] ?? 'activo') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button type="button" onclick="showToast('Función no disponible', 'info')" class="text-red-400 hover:text-red-600 transition p-1" title="Eliminar">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

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

<!-- Modal Nueva Compra -->
<div id="modalNuevaCompra" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900 bg-opacity-50">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-xl w-full p-6 mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">Nueva Compra</h3>
            <button onclick="closeModal('modalNuevaCompra')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>
        <form method="POST" action="<?= baseUrl('compras/guardar') ?>">
            <?= csrf_field() ?>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor</label>
                    <select name="proveedor_id" required class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-sm focus:outline-none focus:border-sky-400">
                        <option value="">Seleccionar proveedor...</option>
                        <?php foreach ($proveedores ?? [] as $prov): ?>
                            <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['razon_social']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">N° Factura Proveedor</label>
                        <input type="text" name="numero_factura" required class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-sm focus:outline-none focus:border-sky-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                        <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-sm focus:outline-none focus:border-sky-400">
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subtotal</label>
                        <input type="number" step="0.01" name="subtotal" id="comp_subtotal" value="0.00" required class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-sm focus:outline-none focus:border-sky-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">IGV (18%)</label>
                        <input type="number" step="0.01" name="igv" id="comp_igv" value="0.00" readonly class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Total</label>
                        <input type="number" step="0.01" name="total" id="comp_total" value="0.00" readonly class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-sm font-semibold">
                    </div>
                </div>
            </div>
            <div class="flex gap-3 mt-5">
                <button type="button" onclick="closeModal('modalNuevaCompra')" class="flex-1 px-4 py-2 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 rounded-lg text-sm transition">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-sky-500 hover:bg-sky-600 text-white rounded-lg text-sm transition font-medium">Guardar Compra</button>
            </div>
        </form>
    </div>
</div>
<script>
document.getElementById('comp_subtotal')?.addEventListener('input', function() {
    const sub = parseFloat(this.value) || 0;
    const igv = sub * 0.18;
    document.getElementById('comp_igv').value = igv.toFixed(2);
    document.getElementById('comp_total').value = (sub + igv).toFixed(2);
});
</script>
