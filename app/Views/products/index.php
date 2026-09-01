<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto" data-turbo="false">

<?php
$total        = $total        ?? 0;
$paginaActual = $paginaActual ?? 1;
$totalPaginas = $totalPaginas ?? 1;
$productos    = $productos    ?? [];
$categorias   = $categorias   ?? [];
$filtros      = $filtros      ?? [];
$titulo       = $titulo       ?? 'Productos';
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">
                <i class="fa-solid fa-boxes-stacked text-sky-500 mr-2"></i><?= htmlspecialchars($titulo) ?>
            </h1>
            <?php if ($total > 0): ?>
            <span class="inline-flex items-center gap-1.5 bg-sky-50 dark:bg-sky-500/10 text-sky-600 text-xs font-semibold px-2.5 py-1 rounded-full border border-sky-200 dark:border-sky-500/20">
                <i class="fa-solid fa-cube text-xs"></i> <?= number_format((int)$total) ?> productos
            </span>
            <?php endif; ?>
        </div>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">
            <i class="fa-regular fa-building mr-1"></i>Gesti&oacute;n de inventario y cat&aacute;logo
        </p>
    </div>
    <div class="flex items-center gap-3">
        <button type="button" onclick="openExportModal()"
            class="inline-flex items-center gap-2 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-sky-600 text-sm font-semibold px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-sky-300 transition-all shadow-sm">
            <i class="fa-solid fa-download"></i>
            <span class="hidden sm:inline">Exportar</span>
        </button>
        <a href="<?= baseUrl('productos/crear') ?>"
           class="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-bold px-5 py-2.5 rounded-xl shadow-lg shadow-emerald-500/30 transition-all duration-300 hover:shadow-emerald-500/50 hover:-translate-y-0.5">
            <i class="fa-solid fa-plus text-lg"></i> Nuevo Producto
        </a>
    </div>
</div><!-- /.header -->

<div class="bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 p-4 mb-6">
    <form id="filterForm" method="GET" action="<?= baseUrl('productos') ?>" class="flex flex-col sm:flex-row gap-3 items-end">
        <div class="flex-1 w-full sm:max-w-xs">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Buscar</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($filtros['busqueda'] ?? '') ?>"
                       placeholder="Nombre, c&oacute;digo o barras..."
                       class="w-full border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 dark:bg-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent transition-all">
            </div>
        </div>
        <div class="w-full sm:w-48">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Categor&iacute;a</label>
            <select name="categoria_id" onchange="this.form.submit()"
                    class="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm text-slate-700 dark:text-slate-300 dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent transition-all bg-white dark:bg-slate-800">
                <option value="">Todas las categor&iacute;as</option>
                <?php if (!empty($categorias)):
                    $selectedCat = (int)($filtros['categoria_id'] ?? 0);
                    foreach ($categorias as $cat):
                        $indent = $cat['indent'] ?? '';
                ?>
                <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === $selectedCat ? 'selected' : '' ?>>
                    <?= htmlspecialchars($indent . $cat['nombre']) ?>
                </option>
                <?php endforeach; endif; ?>
            </select>
        </div>
        <div class="w-full sm:w-40">
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Estado</label>
            <select name="estado" onchange="this.form.submit()"
                    class="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm text-slate-700 dark:text-slate-300 dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent transition-all bg-white dark:bg-slate-800">
                <option value="">Activos e Inactivos</option>
                <option value="1" <?= (($filtros['estado'] ?? '') === '1') ? 'selected' : '' ?>>Solo Activos</option>
                <option value="0" <?= (($filtros['estado'] ?? '') === '0') ? 'selected' : '' ?>>Solo Inactivos</option>
            </select>
        </div>
        <div class="flex items-center gap-2 pb-1">
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="bajo_stock" value="1" <?= !empty($filtros['bajo_stock']) ? 'checked' : '' ?> onchange="this.form.submit()" class="sr-only peer">
                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-sky-400 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-red-500"></div>
                <span class="ml-2 text-xs font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">
                    <i class="fa-solid fa-triangle-exclamation text-red-500 mr-1"></i>Solo bajo stock
                </span>
            </label>
        </div>
        <div id="selectedBadge" class="hidden items-center gap-2 bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 text-xs font-semibold px-3 py-2 rounded-xl border border-sky-200 dark:border-sky-500/20">
            <i class="fa-solid fa-check-circle"></i>
            <span id="selectedCount">0</span> seleccionados
            <button type="button" onclick="clearSelection()" class="text-sky-400 hover:text-sky-600 ml-1">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-2 bg-sky-500 hover:bg-sky-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-all shadow-sm">
                <i class="fa-solid fa-filter"></i> Filtrar
            </button>
            <a href="<?= baseUrl('productos') ?>" class="inline-flex items-center gap-2 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:text-slate-800 text-sm font-semibold px-4 py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-rotate"></i> Limpiar
            </a>
        </div>
    </form>
</div>

<div class="bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 overflow-hidden">

    <?php if (empty($productos)): ?>
    <div class="py-20 text-center">
        <div class="w-20 h-20 bg-slate-100 dark:bg-slate-700 rounded-full flex items-center justify-center mx-auto mb-5">
            <i class="fa-solid fa-box-open text-3xl text-slate-400"></i>
        </div>
        <p class="text-lg font-semibold text-slate-600 dark:text-slate-300 mb-2">No hay productos</p>
        <p class="text-sm text-slate-400 mb-6">Comienza agregando tu primer producto al cat&aacute;logo</p>
        <a href="<?= baseUrl('productos/crear') ?>"
           class="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white font-semibold px-6 py-3 rounded-xl shadow-lg shadow-emerald-500/30 transition-all">
            <i class="fa-solid fa-plus"></i> Crear Producto
        </a>
    </div>
    <?php else: ?>

    <div class="overflow-x-auto">
        <table class="w-full text-sm" id="productosTable">
            <thead>
                <tr class="border-b border-slate-100 dark:border-slate-700/50 bg-slate-50/50 dark:bg-slate-900/20">
                    <th class="w-10 px-4 py-3.5">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" class="w-4 h-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 cursor-pointer">
                    </th>
                    <th class="text-left text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-3 py-3.5">C&oacute;digo</th>
                    <th class="text-left text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-3 py-3.5">Producto</th>
                    <th class="text-left text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-3 py-3.5 hidden md:table-cell">Categor&iacute;a</th>
                    <th class="text-right text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-3 py-3.5">P. Compra</th>
                    <th class="text-right text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-3 py-3.5">P. Venta</th>
                    <th class="text-center text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-3 py-3.5">Stock</th>
                    <th class="text-center text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider px-3 py-3.5">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-700/30" id="productosBody">
                <?php foreach ($productos as $prod):
                    $stockActual = (float)$prod['stock_actual'];
                    $stockMinimo = (float)$prod['stock_minimo'];
                    $isLowStock  = $stockActual <= $stockMinimo && $stockMinimo > 0;
                ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors product-row" data-id="<?= (int)$prod['id'] ?>">
                    <td class="px-4 py-3">
                        <input type="checkbox" class="product-checkbox row-checkbox w-4 h-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 cursor-pointer"
                               value="<?= (int)$prod['id'] ?>" data-nombre="<?= htmlspecialchars($prod['nombre']) ?>">
                    </td>
                    <td class="px-3 py-3">
                        <span class="font-mono text-xs font-semibold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-2 py-1 rounded-md"><?= htmlspecialchars($prod['codigo_interno'] ?? '&mdash;') ?></span>
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-3">
                            <?php $imageName = $prod['imagen_path'] ?? ''; $hasImage = $imageName !== '' && file_exists(__DIR__ . '/../../../public/uploads/products/' . $imageName) && filesize(__DIR__ . '/../../../public/uploads/products/' . $imageName) > 0; ?>
                            <div class="w-9 h-9 rounded-lg overflow-hidden bg-slate-100 dark:bg-slate-700 flex-shrink-0 border border-slate-200 dark:border-slate-600">
                                <?php if ($hasImage): ?>
                                <img src="<?= baseUrl('uploads/products/' . rawurlencode($imageName)) ?>" alt="<?= htmlspecialchars($prod['nombre']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-slate-400"><i class="fa-solid fa-image text-xs"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800 dark:text-white truncate max-w-[200px]"><?= htmlspecialchars($prod['nombre']) ?></p>
                                <?php if (!empty($prod['codigo_barras'])): ?>
                                <p class="text-[10px] text-slate-400 font-mono truncate"><i class="fa-solid fa-barcode mr-0.5"></i><?= htmlspecialchars($prod['codigo_barras']) ?></p>
                                <?php endif; ?>
                            </div>
                    </td>
                    <td class="px-3 py-3 hidden md:table-cell">
                        <?php if (!empty($prod['categoria_nombre'])): ?>
                        <span class="inline-flex items-center gap-1 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 text-[11px] font-semibold px-2 py-1 rounded-md"><i class="fa-solid fa-tag text-[10px]"></i><?= htmlspecialchars($prod['categoria_nombre']) ?></span>
                        <?php else: ?><span class="text-slate-400 text-xs">&mdash;</span><?php endif; ?>
                    </td>
                    <td class="px-3 py-3 text-right"><span class="text-slate-600 dark:text-slate-400 text-xs font-medium">S/ <?= number_format((float)$prod['precio_compra_pen'], 2) ?></span></td>
                    <td class="px-3 py-3 text-right"><span class="font-bold text-emerald-600 dark:text-emerald-400">S/ <?= number_format((float)$prod['precio_venta_pen'], 2) ?></span></td>
                    <td class="px-3 py-3 text-center">
                        <?php if ($isLowStock): ?>
                        <div class="inline-flex items-center gap-1.5 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-300 text-xs font-bold px-2.5 py-1 rounded-full border border-red-200 dark:border-red-500/20"><i class="fa-solid fa-triangle-exclamation text-[10px]"></i><?= number_format($stockActual) ?></div>
                        <?php elseif ($stockActual === 0): ?>
                        <span class="inline-flex items-center text-slate-400 text-xs font-medium px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700"><?= number_format($stockActual) ?></span>
                        <?php else: ?>
                        <span class="inline-flex items-center bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-xs font-bold px-2.5 py-1 rounded-full border border-emerald-200 dark:border-emerald-500/20"><i class="fa-solid fa-check-circle text-[10px] mr-1"></i><?= number_format($stockActual) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($prod['unidad_abreviatura'])): ?><span class="text-[10px] text-slate-400 ml-0.5"><?= htmlspecialchars($prod['unidad_abreviatura']) ?></span><?php endif; ?>
                    </td>
                    <td class="px-3 py-3"><div class="flex items-center justify-center gap-1">
                        <a href="<?= baseUrl('productos/' . (int)$prod['id'] . '/editar') ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sky-500 hover:text-sky-700 bg-sky-50 hover:bg-sky-100 dark:bg-sky-500/10 dark:hover:bg-sky-500/20 transition-all" title="Editar"><i class="fa-solid fa-pen-to-square text-xs"></i></a>
                        <button type="button" onclick='confirmDelete(<?= (int)$prod['id'] ?>, "<?= baseUrl('productos/' . (int)$prod['id'] . '/eliminar') ?>", "&iquest;Eliminar el producto <?= htmlspecialchars(str_replace('"', '\"', $prod['nombre']), ENT_QUOTES) ?>?")' class="w-8 h-8 flex items-center justify-center rounded-lg text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-500/10 dark:hover:bg-red-500/20 transition-all" title="Eliminar"><i class="fa-solid fa-trash-can text-xs"></i></button>
                    </div></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $totalPaginas = $totalPaginas ?? 1;
    $paginaActual = $paginaActual ?? 1;
    if ($totalPaginas > 1):
    ?>
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/50 flex flex-col sm:flex-row items-center justify-between gap-3">
        <p class="text-xs text-slate-500 dark:text-slate-400">Mostrando <?= count($productos) ?> de <?= number_format((int)$total) ?> productos</p>
        <div class="flex items-center gap-2">
            <?php if ($paginaActual > 1): ?>
            <a href="?page=<?= $paginaActual - 1 ?>&<?= http_build_query(array_merge($_GET, ['page' => $paginaActual - 1])) ?>" class="px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-600 transition-all"><i class="fa-solid fa-chevron-left"></i> Anterior</a>
            <?php endif; ?>
            <?php $start = max(1, $paginaActual - 2); $end = min($totalPaginas, $paginaActual + 2); for ($i = $start; $i <= $end; $i++): ?>
            <a href="?page=<?= $i ?>&<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all <?= $i === $paginaActual ? 'bg-sky-500 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-600' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($paginaActual < $totalPaginas): ?>
            <a href="?page=<?= $paginaActual + 1 ?>&<?= http_build_query(array_merge($_GET, ['page' => $paginaActual + 1])) ?>" class="px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-600 transition-all">Siguiente <i class="fa-solid fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<form id="deleteForm" action="" method="POST" class="hidden">
    <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
</form>

</div><!-- /.container -->

<script>
(function() {
    'use strict';

    let productCheckboxes = [];
    let selectAllCheckbox = null;
    let selectedBadge = null;
    let selectedCount = null;
    let selectedBadgeModal = null;
    let _exportType = 'pdf';
    let _exportScope = 'all';
    let modalInserted = false;

    const categoryOptions = <?= json_encode(array_map(fn($cat) => ['id' => (int)$cat['id'], 'nombre' => ($cat['indent'] ?? '') . $cat['nombre']], $categorias)) ?>;
    const totalCount = <?= (int)$total ?>;
    const pdfExportUrl = '<?= baseUrl('productos/exportar-pdf') ?>';
    const excelExportUrl = '<?= baseUrl('productos/exportar-excel') ?>';
    const csrfToken = '<?= csrf_token() ?>';

    function queryElements() {
        selectAllCheckbox = document.getElementById('selectAll');
        productCheckboxes = Array.from(document.querySelectorAll('.product-checkbox'));
        selectedBadge = document.getElementById('selectedBadge');
        selectedCount = document.getElementById('selectedCount');
        selectedBadgeModal = document.getElementById('selectedBadgeModal');
    }

    function updateSelectedCount() {
        const checked = document.querySelectorAll('.product-checkbox:checked');
        const count = checked.length;
        if (selectedCount) selectedCount.textContent = count;
        if (selectedBadge) selectedBadge.classList.toggle('hidden', count === 0);
        if (selectedBadgeModal) {
            selectedBadgeModal.textContent = count;
            selectedBadgeModal.classList.toggle('hidden', count === 0);
        }
    }

    window.toggleSelectAll = function(checkbox) {
        productCheckboxes.forEach(function(cb) { cb.checked = checkbox.checked; });
        updateSelectedCount();
    };

    window.clearSelection = function() {
        productCheckboxes.forEach(function(cb) { cb.checked = false; });
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
        updateSelectedCount();
    };

    function attachCheckboxListeners() {
        productCheckboxes.forEach(function(cb) {
            cb.addEventListener('change', updateSelectedCount);
        });
    }

    // ─── MODAL HTML TEMPLATE ───────────────────────────────────────
    function getModalHtml() {
        const catHtml = categoryOptions.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
        return `<div id="exportModal" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeExportModal()"></div>
            <div class="relative z-10 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-fade-in">
                <div class="bg-gradient-to-r from-sky-500 to-blue-600 px-6 py-5 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2"><i class="fa-solid fa-file-export"></i> Exportar Productos</h3>
                    <button type="button" onclick="closeExportModal()" class="text-sky-200 hover:text-white transition-colors p-1"><i class="fa-solid fa-xmark text-xl"></i></button>
                </div>
                <div class="p-6">
                    <input type="hidden" id="exportSelectedIds" value="">
                    <div class="mb-5">
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-3"><i class="fa-regular fa-file-lines text-sky-500 mr-1.5"></i>Tipo de archivo</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex items-center gap-3 p-3 rounded-xl border-2 transition-all duration-200 bg-sky-50 dark:bg-sky-500/10 border-sky-500" id="pdfTypeCard">
                                <div class="w-10 h-10 rounded-lg bg-red-50 dark:bg-red-500/10 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-pdf text-red-500 text-lg"></i></div>
                                <div><p class="font-bold text-sm text-slate-800 dark:text-white">PDF</p><p class="text-[10px] text-slate-400">Documento profesional</p></div>
                                <div class="ml-auto w-5 h-5 rounded-full border-2 border-sky-500 bg-sky-500 flex items-center justify-center"><div class="w-2 h-2 rounded-full bg-white"></div></div>
                            </div>
                            <div class="flex items-center gap-3 p-3 rounded-xl border-2 transition-all duration-200 border-slate-200 dark:border-slate-600 hover:border-emerald-300" id="excelTypeCard">
                                <div class="w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-excel text-emerald-500 text-lg"></i></div>
                                <div><p class="font-bold text-sm text-slate-800 dark:text-white">Excel</p><p class="text-[10px] text-slate-400">Hoja de c&aacute;lculo</p></div>
                                <div class="ml-auto w-5 h-5 rounded-full border-2 border-slate-300 flex items-center justify-center"><div class="w-2 h-2 rounded-full bg-white hidden"></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-5">
                        <label class="block text-sm font-bold text-slate-700 dark:text-slate-200 mb-3"><i class="fa-solid fa-sliders text-sky-500 mr-1.5"></i>&iquest;Qu&eacute; deseas exportar?</label>
                        <div class="space-y-2">
                            <div class="flex items-center gap-3 p-3 rounded-xl border bg-sky-50 dark:bg-sky-500/10 border-sky-500 cursor-pointer transition-all" id="scopeAllCard" onclick="selectScope('all')">
                                <div class="w-5 h-5 rounded-full border-2 border-sky-500 bg-sky-500 flex items-center justify-center shrink-0"><div class="w-2 h-2 rounded-full bg-white"></div></div>
                                <div class="flex-1"><p class="font-semibold text-sm text-slate-800 dark:text-white">Todos los productos</p><p class="text-[11px] text-slate-400">Con los filtros aplicados actualmente</p></div>
                                <span class="text-xs font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10 px-2.5 py-1 rounded-full count-badge">${totalCount}</span>
                            </div>
                            <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer transition-all hover:border-sky-300" id="scopeCategoryCard" onclick="selectScope('category')">
                                <div class="w-5 h-5 rounded-full border-2 border-slate-300 flex items-center justify-center shrink-0"><div class="w-2 h-2 rounded-full bg-white hidden"></div></div>
                                <div class="flex-1"><p class="font-semibold text-sm text-slate-800 dark:text-white">Por categor&iacute;a</p><p class="text-[11px] text-slate-400">Selecciona una categor&iacute;a espec&iacute;fica</p></div>
                            </div>
                            <div id="categorySelectWrapper" class="hidden ml-8 mt-1">
                                <select id="exportCategorySelect" class="w-full border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-sm text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent transition-all"><option value="">Seleccionar categor&iacute;a...</option>${catHtml}</select>
                            </div>
                            <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer transition-all hover:border-sky-300" id="scopeSelectedCard" onclick="selectScope('selected')">
                                <div class="w-5 h-5 rounded-full border-2 border-slate-300 flex items-center justify-center shrink-0"><div class="w-2 h-2 rounded-full bg-white hidden"></div></div>
                                <div class="flex-1"><p class="font-semibold text-sm text-slate-800 dark:text-white">Seleccionados manualmente</p><p class="text-[11px] text-slate-400">Solo los productos que marcaste con checkbox</p></div>
                                <span id="selectedBadgeModal" class="hidden text-xs font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10 px-2.5 py-1 rounded-full">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-slate-100 dark:border-slate-700/50">
                        <button type="button" onclick="closeExportModal()" class="flex-1 border border-slate-200 dark:border-slate-600 text-slate-700 dark:text-slate-300 font-semibold py-2.5 px-4 rounded-xl text-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">Cancelar</button>
                        <button type="button" id="btnExportSubmit" class="flex-1 bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white font-bold py-2.5 px-4 rounded-xl text-sm flex items-center justify-center gap-2 transition-all shadow-lg shadow-sky-500/30 hover:shadow-sky-500/50"><i class="fa-solid fa-download"></i><span id="exportBtnText">Exportar</span></button>
                    </div>
                </div>
            </div>`;
    }

    // ─── SCOPE SELECTION ──────────────────────────────────────────
    window.selectScope = function(scope) {
        _exportScope = scope;
        document.querySelectorAll('[id^="scope"]').forEach(el => {
            if (el.id === 'scope' + scope.charAt(0).toUpperCase() + scope.slice(1) + 'Card') {
                el.className = 'flex items-center gap-3 p-3 rounded-xl border bg-sky-50 dark:bg-sky-500/10 border-sky-500 cursor-pointer transition-all';
                el.querySelector('.rounded-full')?.classList.add('bg-sky-500');
                el.querySelector('.rounded-full div')?.classList.remove('hidden');
            } else if (el.id !== 'categorySelectWrapper') {
                el.className = el.className.replace(/border-sky-500 bg-sky-50/,'').trim();
                el.className = 'flex items-center gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-600 cursor-pointer transition-all hover:border-sky-300';
                el.querySelector('.rounded-full')?.classList.remove('bg-sky-500');
                const inner = el.querySelector('.rounded-full div');
                if (inner) inner.classList.add('hidden');
            }
        });
        document.getElementById('categorySelectWrapper').classList.toggle('hidden', scope !== 'category');
        if (scope === 'category') document.getElementById('categorySelectWrapper').classList.remove('hidden');
    };

    // ─── TYPE SELECTION ───────────────────────────────────────────
    function attachTypeListeners() {
        const pdfCard = document.getElementById('pdfTypeCard');
        const excelCard = document.getElementById('excelTypeCard');
        if (pdfCard && excelCard) {
            pdfCard.onclick = function(e) {
                e.stopPropagation();
                _exportType = 'pdf';
                pdfCard.className = 'flex items-center gap-3 p-3 rounded-xl border-2 transition-all duration-200 bg-sky-50 dark:bg-sky-500/10 border-sky-500';
                excelCard.className = 'flex items-center gap-3 p-3 rounded-xl border-2 transition-all duration-200 border-slate-200 dark:border-slate-600 hover:border-emerald-300';
                pdfCard.querySelectorAll('.rounded-full.bg-white').forEach(el => el.classList.remove('hidden'));
                excelCard.querySelectorAll('.rounded-full').forEach(el => el.classList.remove('bg-sky-500'));
                excelCard.querySelectorAll('.rounded-full div').forEach(el => el.classList.add('hidden'));
            };
            excelCard.onclick = function(e) {
                e.stopPropagation();
                _exportType = 'excel';
                pdfCard.className = 'flex items-center gap-3 p-3 rounded-xl border-2 transition-all duration-200 border-slate-200 dark:border-slate-600 hover:border-sky-300';
                excelCard.className = 'flex items-center gap-3 p-3 rounded-xl border-2 transition-all duration-200 bg-sky-50 dark:bg-sky-500/10 border-sky-500';
                pdfCard.querySelectorAll('.rounded-full').forEach(el => el.classList.remove('bg-sky-500'));
                pdfCard.querySelectorAll('.rounded-full div').forEach(el => el.classList.add('hidden'));
                excelCard.querySelectorAll('.rounded-full.bg-white').forEach(el => el.classList.remove('hidden'));
            };
        }
    }

    // ─── OPEN / CLOSE MODAL ───────────────────────────────────────
    window.openExportModal = function() {
        if (!modalInserted) {
            const div = document.createElement('div');
            div.innerHTML = getModalHtml();
            document.body.appendChild(div.firstElementChild);
            modalInserted = true;
            attachTypeListeners();
            queryElements();
            updateSelectedCount();
            // Re-bind export button
            document.getElementById('btnExportSubmit').addEventListener('click', submitExport);
        }
        document.getElementById('exportModal').classList.remove('hidden');
    };

    window.closeExportModal = function() {
        const modal = document.getElementById('exportModal');
        if (modal) modal.classList.add('hidden');
    };

    // ─── EXPORT SUBMIT ────────────────────────────────────────────
    function submitExport() {
        const btn = document.getElementById('btnExportSubmit');
        const btnText = document.getElementById('exportBtnText');
        btn.disabled = true;
        btnText.textContent = 'Exportando...';

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = _exportType === 'pdf' ? pdfExportUrl : excelExportUrl;
        form.target = '_blank';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_csrf_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        if (_exportScope === 'category') {
            const catId = document.getElementById('exportCategorySelect').value;
            if (!catId) { alert('Selecciona una categoría'); btn.disabled = false; btnText.textContent = 'Exportar'; return; }
            const catInput = document.createElement('input');
            catInput.type = 'hidden';
            catInput.name = 'categoria_id';
            catInput.value = catId;
            form.appendChild(catInput);
        } else if (_exportScope === 'selected') {
            const checkedIds = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
            if (checkedIds.length === 0) { alert('Selecciona al menos un producto'); btn.disabled = false; btnText.textContent = 'Exportar'; return; }
            checkedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_ids[]';
                input.value = id;
                form.appendChild(input);
            });
        }

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);

        btn.disabled = false;
        btnText.textContent = 'Exportar';
        closeExportModal();
    }

    // ─── INIT ─────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
        queryElements();
        attachCheckboxListeners();
    });
})();
</script>