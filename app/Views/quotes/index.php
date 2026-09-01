<?php
$title = 'Cotizaciones';
$quotes = $resultado['data'] ?? [];
$total = (int)($resultado['total'] ?? 0);
$query = $_GET;
$currentPage = max(1, (int)($paginaActual ?? 1));
$pages = max(1, (int)($totalPaginas ?? 1));

$statusStyles = [
    'borrador'   => ['Borrador', 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200'],
    'enviada'    => ['Enviada', 'bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300'],
    'aceptada'   => ['Aceptada', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300'],
    'aprobada'   => ['Aprobada', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300'],
    'rechazada'  => ['Rechazada', 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300'],
    'vencida'    => ['Vencida', 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300'],
    'convertida' => ['Convertida', 'bg-violet-100 text-violet-700 dark:bg-violet-900/50 dark:text-violet-300'],
];
?>

<div class="space-y-6">
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-sky-600 via-sky-600 to-blue-700 px-6 py-7 text-white shadow-xl shadow-sky-500/20 sm:px-8">
        <div class="absolute -right-12 -top-16 h-56 w-56 rounded-full bg-white/10"></div>
        <div class="absolute right-28 bottom-0 h-24 w-24 rounded-full bg-cyan-300/10"></div>
        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 text-xl ring-1 ring-white/20"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <h1 class="text-2xl font-extrabold tracking-tight">Cotizaciones</h1>
                <p class="mt-1 text-sm text-sky-100">Gestiona presupuestos, su vigencia y su conversión a venta.</p>
            </div>
            <a href="<?= baseUrl('cotizaciones/crear') ?>" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold text-sky-700 shadow-lg transition hover:-translate-y-0.5 hover:bg-sky-50">
                <i class="fa-solid fa-plus"></i> Nueva cotización
            </a>
        </div>
    </section>

    <section class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total registradas</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-800 dark:text-white"><?= number_format($total) ?></p>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Mostrando</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-800 dark:text-white"><?= number_format(count($quotes)) ?> <span class="text-sm font-medium text-slate-400">resultados</span></p>
        </div>
        <div class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Filtro activo</p>
            <p class="mt-1 truncate text-lg font-bold text-sky-600 dark:text-sky-400"><?= !empty($_GET['estado']) ? htmlspecialchars(ucfirst($_GET['estado'])) : 'Todos los estados' ?></p>
        </div>
    </section>

    <form method="GET" class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
            <label class="relative xl:col-span-2">
                <span class="sr-only">Buscar</span>
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="search" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Número, cliente o documento..." class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm outline-none transition focus:border-sky-400 focus:ring-4 focus:ring-sky-100 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:focus:ring-sky-900/40">
            </label>
            <select name="estado" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm outline-none transition focus:border-sky-400 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                <option value="">Todos los estados</option>
                <?php foreach ($statusStyles as $value => [$label]): ?>
                    <option value="<?= $value ?>" <?= ($_GET['estado'] ?? '') === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="desde" value="<?= htmlspecialchars($_GET['desde'] ?? '') ?>" aria-label="Desde" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm outline-none transition focus:border-sky-400 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-700 dark:bg-sky-600 dark:hover:bg-sky-500"><i class="fa-solid fa-filter mr-1.5"></i> Filtrar</button>
                <a href="<?= baseUrl('cotizaciones') ?>" title="Limpiar filtros" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-3 text-slate-500 transition hover:bg-slate-50 dark:border-slate-600 dark:hover:bg-slate-700"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </div>
    </form>

    <section class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-700">
            <div><h2 class="font-bold text-slate-800 dark:text-white">Historial de cotizaciones</h2><p class="text-xs text-slate-500">Revisa el estado antes de convertir una cotización.</p></div>
            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300"><?= number_format($total) ?> total</span>
        </div>
        <?php if (empty($quotes)): ?>
            <div class="flex flex-col items-center px-6 py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-sky-50 text-2xl text-sky-500 dark:bg-sky-900/30"><i class="fa-regular fa-file-lines"></i></div>
                <h3 class="font-bold text-slate-700 dark:text-white">No hay cotizaciones para mostrar</h3>
                <p class="mt-1 max-w-sm text-sm text-slate-500">Crea una nueva cotización o ajusta los filtros para encontrar registros anteriores.</p>
                <a href="<?= baseUrl('cotizaciones/crear') ?>" class="mt-5 text-sm font-bold text-sky-600 hover:text-sky-700">Crear cotización <i class="fa-solid fa-arrow-right ml-1"></i></a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500 dark:bg-slate-900/50 dark:text-slate-400"><tr><th class="px-5 py-3 font-semibold">Cotización</th><th class="px-5 py-3 font-semibold">Cliente</th><th class="px-5 py-3 font-semibold">Emisión / vigencia</th><th class="px-5 py-3 text-right font-semibold">Total</th><th class="px-5 py-3 text-center font-semibold">Estado</th><th class="px-5 py-3 text-right font-semibold">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    <?php foreach ($quotes as $cot): ?>
                        <?php $state = $statusStyles[$cot['estado'] ?? 'borrador'] ?? ['Sin estado', 'bg-slate-100 text-slate-600']; $canConvert = in_array($cot['estado'] ?? '', ['enviada', 'aprobada'], true); $issueDate = $cot['fecha_emision'] ?? $cot['fecha'] ?? null; ?>
                        <tr class="transition hover:bg-sky-50/50 dark:hover:bg-slate-700/40">
                            <td class="px-5 py-4"><p class="font-bold text-sky-700 dark:text-sky-400"><?= htmlspecialchars($cot['numero'] ?? '-') ?></p><p class="mt-0.5 text-xs text-slate-400">ID #<?= (int)($cot['id'] ?? 0) ?></p></td>
                            <td class="px-5 py-4"><p class="max-w-[200px] truncate font-semibold text-slate-700 dark:text-slate-200"><?= htmlspecialchars($cot['cliente_nombre'] ?? 'Cliente general') ?></p><p class="mt-0.5 text-xs text-slate-400"><?= htmlspecialchars($cot['cliente_documento'] ?? 'Sin documento') ?></p></td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300"><p><?= $issueDate ? date('d/m/Y', strtotime($issueDate)) : '-' ?></p><p class="mt-0.5 text-xs <?= (($cot['dias_para_vencer'] ?? 1) < 0) ? 'text-rose-500' : 'text-slate-400' ?>"><?= isset($cot['dias_para_vencer']) ? (($cot['dias_para_vencer'] < 0) ? 'Venció hace ' . abs((int)$cot['dias_para_vencer']) . ' días' : ((int)$cot['dias_para_vencer'] . ' días restantes')) : ((int)($cot['validez_dias'] ?? 15) . ' días de validez') ?></p></td>
                            <td class="px-5 py-4 text-right font-extrabold text-slate-800 dark:text-white"><?= htmlspecialchars($cot['moneda'] ?? 'PEN') === 'USD' ? 'US$' : 'S/' ?> <?= number_format((float)($cot['total'] ?? $cot['total_pen'] ?? 0), 2) ?></td>
                            <td class="px-5 py-4 text-center"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold <?= $state[1] ?>"><?= $state[0] ?></span></td>
                            <td class="px-5 py-4"><div class="flex justify-end gap-2"><a href="<?= baseUrl('cotizaciones/pdf/' . (int)$cot['id']) ?>" target="_blank" rel="noopener" title="Abrir PDF para imprimir" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 text-sky-600 transition hover:bg-sky-600 hover:text-white dark:bg-sky-900/30"><i class="fa-solid fa-file-pdf"></i></a><?php if ($canConvert): ?><button type="button" title="Convertir a venta" onclick="convertirVenta(<?= (int)$cot['id'] ?>, '<?= htmlspecialchars($cot['numero'] ?? '', ENT_QUOTES) ?>')" class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 transition hover:bg-emerald-600 hover:text-white dark:bg-emerald-900/30"><i class="fa-solid fa-cash-register"></i></button><?php else: ?><span title="Solo las cotizaciones enviadas o aprobadas pueden convertirse" class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg bg-slate-100 text-slate-300 dark:bg-slate-700"><i class="fa-solid fa-cash-register"></i></span><?php endif; ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
                <nav class="flex items-center justify-between border-t border-slate-100 px-5 py-4 dark:border-slate-700" aria-label="Paginación">
                    <p class="text-xs text-slate-500">Página <?= $currentPage ?> de <?= $pages ?></p>
                    <div class="flex gap-2"><?php foreach (['previous' => $currentPage - 1, 'next' => $currentPage + 1] as $direction => $page): ?><?php $disabled = $page < 1 || $page > $pages; $linkQuery = $query; $linkQuery['page'] = $page; ?><a <?= $disabled ? 'aria-disabled="true"' : 'href="' . baseUrl('cotizaciones') . '?' . htmlspecialchars(http_build_query($linkQuery)) . '"' ?> class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold <?= $disabled ? 'pointer-events-none opacity-40' : 'hover:bg-slate-50 dark:hover:bg-slate-700' ?> dark:border-slate-600 dark:text-slate-200"><i class="fa-solid fa-chevron-<?= $direction === 'previous' ? 'left' : 'right' ?>"></i></a><?php endforeach; ?></div>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<script>
function convertirVenta(id, numero) {
    if (!confirm(`¿Convertir la cotización ${numero || '#' + id} en una venta?`)) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= baseUrl('cotizaciones/convertir') ?>';
    form.innerHTML = `<input type="hidden" name="id" value="${id}"><input type="hidden" name="_token" value="<?= csrf_token() ?>">`;
    document.body.appendChild(form);
    form.submit();
}
</script>
