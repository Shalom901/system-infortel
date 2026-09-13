<?php
/**
 * Vista de Listado y Gestión de Comprobantes SUNAT
 * @var array $comprobantes
 * @var array $filtros
 * @var int   $total
 * @var int   $totalPaginas
 * @var int   $paginaActual
 */

$title = 'Gestión de Comprobantes SUNAT';
$filtros = $filtros ?? [];
$periodoActivo = $filtros['periodo'] ?? ($_GET['periodo'] ?? 'today');
?>

<!-- Header y Exportación -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
            <i class="fa-solid fa-file-invoice text-sky-500"></i>
            Gestión de Comprobantes SUNAT
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Consulta, filtra y exporta los comprobantes emitidos ante la SUNAT</p>
    </div>
    
    <!-- Botones de Exportación -->
    <div class="flex items-center gap-2">
        <a href="<?= baseUrl('sunat/exportar/csv?' . http_build_query($filtros)) ?>" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all shadow-sm hover:-translate-y-0.5">
            <i class="fa-solid fa-file-excel"></i> Excel
        </a>
        <a href="<?= baseUrl('sunat/exportar/pdf?' . http_build_query($filtros)) ?>" target="_blank" class="px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all shadow-sm hover:-translate-y-0.5">
            <i class="fa-solid fa-file-pdf"></i> PDF
        </a>
        <a href="<?= baseUrl('sunat/exportar/txt?' . http_build_query($filtros)) ?>" class="px-3.5 py-2 bg-slate-700 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all shadow-sm hover:-translate-y-0.5">
            <i class="fa-solid fa-file-lines"></i> TXT
        </a>
    </div>
</div>

<!-- Filtros y Búsqueda (100% Adaptado a Modo Oscuro) -->
<div class="bg-white dark:bg-slate-900/70 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800 p-5 mb-6 transition-colors">
    
    <!-- Pestañas de Rango Rápido -->
    <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-4 mb-4 overflow-x-auto custom-scrollbar">
        <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mr-2 shrink-0">Filtrar por periodo:</span>
        <button type="button" onclick="setQuickDate('today')" class="quick-date-btn <?= $periodoActivo === 'today' ? 'active' : '' ?> px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all">Hoy</button>
        <button type="button" onclick="setQuickDate('week')" class="quick-date-btn <?= $periodoActivo === 'week' ? 'active' : '' ?> px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">Esta Semana</button>
        <button type="button" onclick="setQuickDate('month')" class="quick-date-btn <?= $periodoActivo === 'month' ? 'active' : '' ?> px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">Este Mes</button>
        <button type="button" onclick="setQuickDate('custom')" class="quick-date-btn <?= $periodoActivo === 'custom' ? 'active' : '' ?> px-3.5 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">Personalizado</button>
    </div>

    <!-- Formulario de Selección con Contraste Oscuro -->
    <form method="GET" action="<?= baseUrl('sunat/listado') ?>" id="filterForm" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
        <input type="hidden" name="periodo" id="inputPeriodo" value="<?= htmlspecialchars($periodoActivo) ?>">
        <input type="hidden" name="desde" id="inputDesde" value="<?= htmlspecialchars($filtros['fecha_desde'] ?? '') ?>">
        <input type="hidden" name="hasta" id="inputHasta" value="<?= htmlspecialchars($filtros['fecha_hasta'] ?? '') ?>">

        <!-- Tipo Comprobante -->
        <div class="md:col-span-3">
            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Tipo</label>
            <select name="tipo" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500 transition-colors">
                <option value="">Todos los Tipos</option>
                <option value="01" <?= ($filtros['tipo'] ?? '') === '01' ? 'selected' : '' ?>>Factura</option>
                <option value="03" <?= ($filtros['tipo'] ?? '') === '03' ? 'selected' : '' ?>>Boleta</option>
                <option value="07" <?= ($filtros['tipo'] ?? '') === '07' ? 'selected' : '' ?>>Nota de Crédito</option>
                <option value="08" <?= ($filtros['tipo'] ?? '') === '08' ? 'selected' : '' ?>>Nota de Débito</option>
            </select>
        </div>

        <!-- Estado SUNAT -->
        <div class="md:col-span-3">
            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Estado</label>
            <select name="estado" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500 transition-colors">
                <option value="">Todos los Estados</option>
                <option value="ACEPTADO" <?= ($filtros['estado'] ?? '') === 'ACEPTADO' ? 'selected' : '' ?>>Aceptado</option>
                <option value="RECHAZADO" <?= ($filtros['estado'] ?? '') === 'RECHAZADO' ? 'selected' : '' ?>>Rechazado</option>
                <option value="PENDIENTE" <?= ($filtros['estado'] ?? '') === 'PENDIENTE' ? 'selected' : '' ?>>Pendiente / En cola</option>
            </select>
        </div>

        <!-- Búsqueda General -->
        <div class="md:col-span-4">
            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Buscar</label>
            <div class="relative">
                <i class="fa-solid fa-search absolute left-3.5 top-3 text-slate-400"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($filtros['q'] ?? '') ?>" placeholder="Cliente o Comprobante..." 
                       class="w-full pl-9 pr-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs font-medium text-slate-700 dark:text-slate-200 focus:outline-none focus:border-sky-500 transition-colors">
            </div>
        </div>

        <!-- Botones de Acción (Filtrar y Limpiar) -->
        <div class="md:col-span-2 flex gap-2">
            <button type="submit" class="flex-1 py-2.5 bg-sky-500 hover:bg-sky-600 text-white rounded-xl text-xs font-bold transition-all shadow-sm flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-filter"></i>
            </button>
            <a href="<?= baseUrl('sunat/listado') ?>" class="p-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-transparent dark:border-slate-700 text-slate-500 dark:text-slate-400 rounded-xl text-xs flex items-center justify-center transition-all" title="Limpiar Filtros">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        </div>
    </form>

    <!-- Selector de Rango Personalizado -->
    <div id="customDateRange" class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 items-end <?= $periodoActivo === 'custom' ? '' : 'hidden' ?>">
        <div>
            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Desde:</label>
            <input type="date" id="customDesde" value="<?= htmlspecialchars($filtros['fecha_desde'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Hasta:</label>
            <input type="date" id="customHasta" value="<?= htmlspecialchars($filtros['fecha_hasta'] ?? '') ?>" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-xs text-slate-700 dark:text-slate-200">
        </div>
        <div>
            <button type="button" onclick="applyCustomDate()" class="w-full py-2 bg-slate-800 hover:bg-slate-900 dark:bg-sky-600 dark:hover:bg-sky-700 text-white rounded-xl text-xs font-bold transition-all shadow-sm">
                Aplicar Rango
            </button>
        </div>
    </div>
</div>

<!-- Tabla de Comprobantes con Cabecera Oscura Corregida -->
<div class="bg-white dark:bg-slate-900/70 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800 overflow-hidden transition-colors">
    <table class="w-full text-xs text-left">
        <thead class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <tr>
                <th class="px-4 py-3.5">Fecha</th>
                <th class="px-4 py-3.5">Documento</th>
                <th class="px-4 py-3.5">Cliente</th>
                <th class="px-4 py-3.5 text-right">Total</th>
                <th class="px-4 py-3.5 text-center">Estado SUNAT</th>
                <th class="px-4 py-3.5 text-center">Acciones</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-slate-700 dark:text-slate-300">
            <?php if (empty($comprobantes)): ?>
                <tr>
                    <td colspan="6" class="text-center py-12 text-slate-400 dark:text-slate-500">
                        <i class="fa-solid fa-file-circle-xmark text-4xl mb-3 opacity-30"></i>
                        <p class="font-medium text-sm">No se encontraron comprobantes para este filtro</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($comprobantes as $c): ?>
                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                        <!-- Fecha y Hora -->
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-800 dark:text-slate-200">
                                <?= date('d/m/Y', strtotime($c['fecha_emision'] ?? $c['fecha_envio'] ?? 'now')) ?>
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono">
                                <?= !empty($c['fecha_envio']) ? date('H:i A', strtotime($c['fecha_envio'])) : '00:00' ?>
                            </div>
                        </td>

                        <!-- Documento con enlace directo al PDF -->
                        <td class="px-4 py-3 font-mono">
                            <a href="<?= baseUrl('pos/pdf/' . $c['venta_id']) ?>" target="_blank" class="font-bold text-sky-600 dark:text-sky-400 hover:underline">
                                <?= htmlspecialchars($c['serie'] . '-' . str_pad((string)$c['numero'], 8, '0', STR_PAD_LEFT)) ?>
                            </a>
                            <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold <?= $c['tipo_comprobante'] === '01' ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400' : 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400' ?>">
                                <?= $c['tipo_comprobante'] === '01' ? 'Factura' : 'Boleta' ?>
                            </span>
                        </td>

                        <!-- Cliente -->
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-800 dark:text-slate-200 truncate max-w-[200px]">
                                <?= htmlspecialchars($c['cliente_nombre'] ?? 'Cliente General') ?>
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono">
                                <?= htmlspecialchars($c['cliente_doc'] ?? '-') ?>
                            </div>
                        </td>

                        <!-- Total -->
                        <td class="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">
                            S/ <?= number_format((float)($c['total'] ?? 0), 2) ?>
                        </td>

                        <!-- Estado SUNAT -->
                        <td class="px-4 py-3 text-center">
                            <?php
                                $estado = strtoupper($c['estado_sunat'] ?? 'PENDIENTE');
                                $color = match($estado) {
                                    'ACEPTADO' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400',
                                    'RECHAZADO' => 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400',
                                    default => 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400'
                                };
                            ?>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $color ?>">
                                <?= ucfirst(strtolower($estado)) ?>
                            </span>
                        </td>

                        <!-- Acciones -->
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <!-- Botón PDF -->
                                <a href="<?= baseUrl('pos/pdf/' . $c['venta_id']) ?>" target="_blank" 
                                   class="w-7 h-7 flex items-center justify-center rounded-lg text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-500 hover:text-white dark:hover:bg-rose-500 dark:hover:text-white transition-all shadow-sm" 
                                   title="Ver PDF">
                                    <i class="fa-solid fa-file-pdf text-xs"></i>
                                </a>

                                <!-- Botón Ticket -->
                                <a href="<?= baseUrl('pos/ticket/' . $c['venta_id']) ?>" target="_blank" 
                                   onclick="window.open(this.href, 'ticket', 'width=400,height=600'); return false;"
                                   class="w-7 h-7 flex items-center justify-center rounded-lg text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10 hover:bg-amber-500 hover:text-white dark:hover:bg-amber-500 dark:hover:text-white transition-all shadow-sm" 
                                   title="Ver Ticket">
                                    <i class="fa-solid fa-receipt text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    .quick-date-btn.active {
        background-color: #0284c7 !important;
        color: #ffffff !important;
    }
    .quick-date-btn:not(.active) {
        color: #64748b;
    }
    .dark .quick-date-btn:not(.active) {
        color: #94a3b8;
    }
    .dark .quick-date-btn:not(.active):hover {
        background-color: #1e293b;
        color: #f8fafc;
    }
</style>

<script>
function setQuickDate(periodo) {
    document.getElementById('inputPeriodo').value = periodo;
    const customRange = document.getElementById('customDateRange');

    if (periodo === 'custom') {
        customRange.classList.remove('hidden');
    } else {
        customRange.classList.add('hidden');
        document.getElementById('filterForm').submit();
    }
}

function applyCustomDate() {
    const desde = document.getElementById('customDesde').value;
    const hasta = document.getElementById('customHasta').value;

    if (!desde || !hasta) {
        alert('Seleccione las fechas de inicio y fin');
        return;
    }

    document.getElementById('inputDesde').value = desde;
    document.getElementById('inputHasta').value = hasta;
    document.getElementById('inputPeriodo').value = 'custom';
    document.getElementById('filterForm').submit();
}
</script>