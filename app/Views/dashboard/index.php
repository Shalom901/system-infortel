<?php
/**
 * Dashboard View Profesional — injected into main layout
 *
 * Variables expected from controller:
 *   $summary      array  Metricas del dashboard
 *   $salesTrend   array  Datos de tendencia 30 días
 *   $payments     array  Desglose de pagos por método
 *   $hourlySales  array  Ventas por hora del día
 *   $recentSales  array  Últimas ventas
 *   $ventasHoy    array  Legacy compatibility
 *   $comprasMes   float  Legacy
 *   $bajoStock    int    Legacy
 *   $ventasRecientes array Legacy
 *   $datosGrafico array  Legacy
 */

// Soporte legacy
$ventasHoy       = $ventasHoy       ?? ['cantidad' => 0, 'monto' => 0.00, 'ventas_hoy' => 0, 'monto_hoy' => 0.00, 'ticket_promedio' => 0, 'mejor_hora' => ['label' => '—', 'amount' => 0], 'hora_baja' => ['label' => '—', 'amount' => 0]];
$comprasMes      = $comprasMes      ?? 0.00;
$bajoStock       = $bajoStock       ?? 0;
$ventasRecientes = $ventasRecientes ?? [];
$datosGrafico    = $datosGrafico    ?? ['labels' => [], 'data' => [], 'counts' => []];
$summary         = $summary         ?? $ventasHoy;
$salesTrend      = $salesTrend      ?? $datosGrafico;
$payments        = $payments        ?? ['labels' => ['Efectivo', 'Yape', 'Plin', 'Transferencias', 'Tarjetas', 'Otros'], 'data' => [0,0,0,0,0,0]];
$hourlySales     = $hourlySales     ?? ['labels' => [], 'data' => [], 'counts' => [], 'best' => ['label' => '—', 'amount' => 0], 'lowest' => ['label' => '—', 'amount' => 0]];
$recentSales     = $recentSales     ?? $ventasRecientes;

if (!function_exists('formatMoney')) {
    function formatMoney(float $amount, string $symbol = 'S/'): string {
        return $symbol . ' ' . number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('formatTrend')) {
    function formatTrend(float $current, float $previous): array {
        if ($previous <= 0) return ['icon' => 'fa-minus', 'color' => 'text-slate-400', 'text' => '—'];
        $diff = (($current - $previous) / $previous) * 100;
        if ($diff > 0) return ['icon' => 'fa-arrow-up', 'color' => 'text-emerald-500', 'text' => '+' . number_format($diff, 1) . '%'];
        if ($diff < 0) return ['icon' => 'fa-arrow-down', 'color' => 'text-red-500', 'text' => number_format($diff, 1) . '%'];
        return ['icon' => 'fa-minus', 'color' => 'text-slate-400', 'text' => '0%'];
    }
}
?>

<!-- =====================================================================
     PAGE HEADER CON HORA EN VIVO
     ===================================================================== -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-8 animate-fade-in">
    <div>
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">
                <i class="fa-solid fa-gauge-high text-sky-500 mr-2"></i>Dashboard
            </h1>
            <span id="liveIndicator" class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 text-xs font-semibold px-2.5 py-1 rounded-full border border-emerald-200">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                EN VIVO
            </span>
        </div>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5">
            <i class="fa-regular fa-calendar mr-1"></i><?= date('d/m/Y') ?> 
            · <span id="clockDisplay"><?= date('H:i:s') ?></span>
            · <span id="lastUpdatedText" class="text-sky-500">Actualizado ahora</span>
        </p>
    </div>
    <div class="flex items-center gap-3">
        <button onclick="refreshDashboard()" class="inline-flex items-center gap-2 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-sky-600 text-sm font-semibold px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-sky-300 transition-all shadow-sm">
            <i id="refreshIcon" class="fa-solid fa-rotate"></i>
            <span class="hidden sm:inline">Actualizar</span>
        </button>
        <a href="/pos"
           class="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white text-sm font-bold px-6 py-2.5 rounded-xl shadow-lg shadow-emerald-500/30 transition-all duration-300 hover:shadow-emerald-500/50 hover:-translate-y-0.5">
            <i class="fa-solid fa-plus text-lg"></i> Nueva Venta
        </a>
    </div>
</div>

<!-- =====================================================================
     STATS CARDS — 6 MÉTRICAS CLAVE
     ===================================================================== -->
<div id="statsGrid" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6 gap-4 mb-8">

    <!-- Ventas Hoy — Cantidad -->
    <div class="card-stats bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 p-5 hover:shadow-md transition-all duration-300">
        <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-blue-50 dark:bg-blue-500/10">
                <i class="fa-solid fa-receipt text-blue-500 text-lg"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Hoy</span>
        </div>
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Ventas Hoy</p>
        <div class="flex items-end justify-between">
            <p id="statVentasHoy" class="text-2xl font-bold text-slate-800 dark:text-white">
                <?= number_format((int)($summary['ventas_hoy'] ?? 0)) ?>
            </p>
            <span id="trendVentasHoy" class="text-xs font-semibold flex items-center gap-1 <?= formatTrend($summary['ventas_hoy'] ?? 0, max(1, ($summary['ventas_hoy'] ?? 0) - 1))['color'] ?>">
                <i class="fa-solid <?= formatTrend($summary['ventas_hoy'] ?? 0, max(1, ($summary['ventas_hoy'] ?? 0) - 1))['icon'] ?>"></i>
                <?= formatTrend($summary['ventas_hoy'] ?? 0, max(1, ($summary['ventas_hoy'] ?? 0) - 1))['text'] ?>
            </span>
        </div>
        <p class="text-[11px] text-slate-400 mt-1">comprobantes emitidos</p>
    </div>

    <!-- Monto Ventas Hoy -->
    <div class="card-stats bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 p-5 hover:shadow-md transition-all duration-300">
        <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-emerald-50 dark:bg-emerald-500/10">
                <i class="fa-solid fa-sack-dollar text-emerald-500 text-lg"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Hoy</span>
        </div>
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Monto Ventas</p>
        <div class="flex items-end justify-between">
            <p id="statMontoHoy" class="text-2xl font-bold text-slate-800 dark:text-white truncate">
                <?= formatMoney((float)($summary['monto_hoy'] ?? 0)) ?>
            </p>
            <span id="trendMontoHoy" class="text-xs font-semibold flex items-center gap-1 text-emerald-500">
                <i class="fa-solid fa-arrow-up"></i> +12.5%
            </span>
        </div>
        <p class="text-[11px] text-slate-400 mt-1">ingresos del día</p>
    </div>

    <!-- Ticket Promedio -->
    <div class="card-stats bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 p-5 hover:shadow-md transition-all duration-300">
        <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-purple-50 dark:bg-purple-500/10">
                <i class="fa-solid fa-receipt text-purple-500 text-lg"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">30 días</span>
        </div>
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Ticket Promedio</p>
        <div class="flex items-end justify-between">
            <p id="statTicketPromedio" class="text-2xl font-bold text-slate-800 dark:text-white truncate">
                <?= formatMoney((float)($summary['ticket_promedio'] ?? 0)) ?>
            </p>
        </div>
        <p class="text-[11px] text-slate-400 mt-1">por comprobante</p>
    </div>

    <!-- Mejor Hora -->
    <div class="card-stats bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 p-5 hover:shadow-md transition-all duration-300">
        <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-amber-50 dark:bg-amber-500/10">
                <i class="fa-solid fa-clock text-amber-500 text-lg"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">TOP</span>
        </div>
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Mejor Hora</p>
        <div class="flex items-end justify-between">
            <div>
                <p id="statMejorHora" class="text-2xl font-bold text-slate-800 dark:text-white">
                    <?= htmlspecialchars($summary['mejor_hora']['label'] ?? '—') ?>
                </p>
                <p id="statMejorHoraMonto" class="text-xs text-slate-500 mt-0.5">
                    <?= formatMoney((float)($summary['mejor_hora']['amount'] ?? 0)) ?>
                </p>
            </div>
        </div>
        <p class="text-[11px] text-slate-400 mt-1">mayor volumen de ventas</p>
    </div>

    <!-- Hora Baja -->
    <div class="card-stats bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 p-5 hover:shadow-md transition-all duration-300">
        <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-rose-50 dark:bg-rose-500/10">
                <i class="fa-solid fa-moon text-rose-500 text-lg"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">BAJA</span>
        </div>
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Hora Baja</p>
        <div class="flex items-end justify-between">
            <div>
                <p id="statHoraBaja" class="text-2xl font-bold text-slate-800 dark:text-white">
                    <?= htmlspecialchars($summary['hora_baja']['label'] ?? '—') ?>
                </p>
                <p id="statHoraBajaMonto" class="text-xs text-slate-500 mt-0.5">
                    <?= formatMoney((float)($summary['hora_baja']['amount'] ?? 0)) ?>
                </p>
            </div>
        </div>
        <p class="text-[11px] text-slate-400 mt-1">menor actividad</p>
    </div>

    <!-- Bajo Stock -->
    <div class="card-stats bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 p-5 hover:shadow-md transition-all duration-300">
        <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 bg-red-50 dark:bg-red-500/10">
                <i class="fa-solid fa-triangle-exclamation text-red-500 text-lg"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">ALERTA</span>
        </div>
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Bajo Stock</p>
        <div class="flex items-end justify-between">
            <p id="statBajoStock" class="text-2xl font-bold text-slate-800 dark:text-white">
                <?= number_format((int)$bajoStock) ?>
            </p>
        </div>
        <p class="text-[11px] text-slate-400 mt-1">productos por reabastecer</p>
    </div>

</div><!-- /.stats-grid -->

<!-- =====================================================================
     GRÁFICOS PRINCIPALES — 3 COLUMNAS
     ===================================================================== -->
<div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">

    <!-- ---- Gráfico 1: Tendencia 30 días (Líneas) ---- -->
    <div class="xl:col-span-2 bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 p-6">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-base font-bold text-slate-800 dark:text-white">Tendencia de Ventas — 30 Días</h2>
                <p class="text-xs text-slate-400 mt-0.5">Monto total S/ por día — identifica picos y valles</p>
            </div>
            <div class="flex items-center gap-2">
                <span id="trendTotalPeriod" class="inline-flex items-center gap-1.5 bg-sky-50 dark:bg-sky-500/10 text-sky-600 text-xs font-semibold px-3 py-1.5 rounded-full">
                    <i class="fa-solid fa-chart-line text-xs"></i> Total 30d: <span id="total30dAmount">S/ 0</span>
                </span>
            </div>
        </div>
        <div class="relative" style="height: 280px;">
            <canvas id="trendChart"></canvas>
        </div>
    </div>

    <!-- ---- Gráfico 2: Pagos por Método (Doughnut) ---- -->
    <div class="bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 p-6">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-base font-bold text-slate-800 dark:text-white">Métodos de Pago</h2>
                <p class="text-xs text-slate-400 mt-0.5">Distribución últimos 30 días</p>
            </div>
            <span class="inline-flex items-center gap-1.5 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 text-xs font-semibold px-3 py-1.5 rounded-full">
                <i class="fa-solid fa-chart-pie text-xs"></i> <?= array_sum($payments['data']) > 0 ? count(array_filter($payments['data'])) : 0 ?> métodos
            </span>
        </div>
        <div class="relative flex items-center justify-center" style="height: 260px;">
            <canvas id="paymentChart"></canvas>
        </div>
        <!-- Leyenda personalizada -->
        <div id="paymentLegend" class="grid grid-cols-2 gap-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/50">
            <?php 
            $paymentColors = ['#0ea5e9', '#10b981', '#8b5cf6', '#f59e0b', '#f97316', '#94a3b8'];
            foreach ($payments['labels'] as $i => $label): 
                $pct = array_sum($payments['data']) > 0 ? round(($payments['data'][$i] / array_sum($payments['data'])) * 100, 1) : 0;
                $color = $paymentColors[$i % count($paymentColors)];
            ?>
            <div class="flex items-center gap-2 text-xs">
                <span class="w-3 h-3 rounded-full shrink-0" style="background: <?= $color ?>"></span>
                <span class="text-slate-600 dark:text-slate-300"><?= htmlspecialchars($label) ?></span>
                <span class="ml-auto font-semibold text-slate-800 dark:text-white"><?= $pct ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- /.grid-graficos-1 -->

<!-- =====================================================================
     SEGUNDA FILA DE GRÁFICOS
     ===================================================================== -->
<div class="grid grid-cols-1 xl:grid-cols-5 gap-6">

    <!-- ---- Tarjeta Combinada: Ventas por Hora + Top Productos Más Vendidos ---- -->
    <div class="xl:col-span-3 bg-white dark:bg-slate-900/70 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800 p-6 flex flex-col justify-between transition-colors">
        
        <!-- PARTE SUPERIOR: Gráfico de Horas Compacto -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="fa-solid fa-chart-column text-amber-500"></i> Ventas por Hora del Día
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Distribución de ingresos a lo largo de la jornada comercial</p>
                </div>
                <span class="inline-flex items-center gap-1.5 bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 text-xs font-bold px-3 py-1 rounded-full border border-amber-200/60 dark:border-amber-800/40">
                    <i class="fa-solid fa-bolt text-xs"></i> Pico: <span id="peakHourLabel"><?= htmlspecialchars($hourlySales['best']['label'] ?? '—') ?></span>
                </span>
            </div>

            <!-- Gráfico de barras ajustado a 180px -->
            <div class="relative" style="height: 180px;">
                <canvas id="hourlyChart"></canvas>
            </div>
        </div>

        <!-- PARTE INFERIOR: Top Productos Más Vendidos con Barras de Progreso -->
        <div class="pt-5 border-t border-slate-100 dark:border-slate-800/80">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-fire text-rose-500"></i> Productos de Mayor Rotación
                </h3>
                <span class="text-xs text-slate-400 font-medium">Top 4 más vendidos</span>
            </div>

            <?php
                // Cálculo de máximos para la escala de las barras
                $maxVendido = !empty($topProducts) ? max(array_column($topProducts, 'total_vendido')) : 1;
                if ($maxVendido <= 0) $maxVendido = 1;
            ?>

            <?php if (empty($topProducts)): ?>
                <div class="py-6 text-center text-slate-400 dark:text-slate-500 text-xs">
                    <i class="fa-solid fa-boxes-stacked text-2xl mb-2 opacity-30 block"></i>
                    Aún no hay suficientes ventas registradas para calcular los productos estrella.
                </div>
            <?php else: ?>
                <div class="space-y-3.5">
                    <?php 
                    $coloresBarra = [
                        'from-sky-500 to-blue-600',
                        'from-emerald-500 to-teal-600',
                        'from-violet-500 to-purple-600',
                        'from-amber-500 to-orange-600',
                    ];
                    foreach ($topProducts as $idx => $prod): 
                        $porcentaje = min(100, round(((float)$prod['total_vendido'] / $maxVendido) * 100));
                        $gradiente = $coloresBarra[$idx % count($coloresBarra)];
                    ?>
                    <div>
                        <!-- Fila con Nombre, Código, Cantidad y Monto Total -->
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <div class="flex items-center gap-2 min-w-0 pr-2">
                                <span class="w-5 h-5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 font-mono font-bold flex items-center justify-center text-[10px] shrink-0">
                                    #<?= $idx + 1 ?>
                                </span>
                                <span class="font-bold text-slate-800 dark:text-slate-200 truncate">
                                    <?= htmlspecialchars($prod['nombre']) ?>
                                </span>
                                <?php if (!empty($prod['codigo_interno'])): ?>
                                <span class="hidden sm:inline-block text-[10px] text-slate-400 font-mono">
                                    (<?= htmlspecialchars($prod['codigo_interno']) ?>)
                                </span>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center gap-3 shrink-0">
                                <span class="font-semibold text-slate-500 dark:text-slate-400">
                                    <strong class="text-slate-800 dark:text-white"><?= number_format((float)$prod['total_vendido'], 0) ?></strong> uds
                                </span>
                                <span class="font-bold text-sky-600 dark:text-sky-400 min-w-[75px] text-right">
                                    <?= formatMoney((float)$prod['total_monto']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Barra de Progreso Visual con Gradiente -->
                        <div class="w-full bg-slate-100 dark:bg-slate-800 rounded-full h-2 overflow-hidden shadow-inner">
                            <div class="h-full rounded-full bg-gradient-to-r <?= $gradiente ?> transition-all duration-700 ease-out" 
                                 style="width: <?= $porcentaje ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ---- Ventas Recientes ---- -->
    <div class="xl:col-span-2 bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 p-6 flex flex-col">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-base font-bold text-slate-800 dark:text-white">
                <i class="fa-solid fa-clock-rotate-left text-sky-500 mr-1.5"></i>Ventas Recientes
            </h2>
            <a href="/ventas" class="text-xs text-sky-600 hover:text-sky-800 font-semibold transition-colors">
                Ver todas <i class="fa-solid fa-arrow-right ml-0.5"></i>
            </a>
        </div>

        <?php if (empty($recentSales)): ?>
        <div class="flex-1 flex flex-col items-center justify-center text-slate-400 dark:text-slate-500 py-10">
            <i class="fa-solid fa-inbox text-5xl mb-4 opacity-30"></i>
            <p class="text-sm font-medium">No hay ventas registradas hoy</p>
            <a href="/pos" class="mt-3 text-xs text-sky-600 hover:text-sky-800 font-semibold">
                <i class="fa-solid fa-plus mr-1"></i>Crear primera venta
            </a>
        </div>
        <?php else: ?>
        <!-- Agrega max-h-[380px] overflow-y-auto custom-scrollbar -->
        <div class="overflow-x-auto flex-1 -mx-2 max-h-[380px] overflow-y-auto custom-scrollbar">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 dark:border-slate-700/50">
                        <th class="text-left text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider pb-3 pr-2">Comprobante</th>
                        <th class="text-left text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider pb-3 pr-2">Cliente</th>
                        <th class="text-right text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider pb-3 pr-2">Total</th>
                        <th class="text-right text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider pb-3">Hora</th>
                    </tr>
                </thead>
                <tbody id="recentSalesBody" class="divide-y divide-slate-50 dark:divide-slate-700/30">
                    <?php foreach ($recentSales as $venta): ?>
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors rounded-lg">
                        <td class="py-2.5 pr-2">
                            <span class="inline-block bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-500/10 dark:to-indigo-500/10 text-blue-700 dark:text-blue-400 text-[11px] font-mono font-bold px-2 py-1 rounded-md">
                                <?= htmlspecialchars($venta['comprobante'] ?? '—') ?>
                            </span>
                        </td>
                        <td class="py-2.5 pr-2 text-slate-700 dark:text-slate-300 truncate max-w-[100px] text-xs font-medium">
                            <?= htmlspecialchars($venta['cliente'] ?? '—') ?>
                        </td>
                        <td class="py-2.5 pr-2 text-right font-bold text-slate-800 dark:text-white text-sm whitespace-nowrap">
                            <?= formatMoney((float)($venta['total'] ?? 0)) ?>
                        </td>
                        <td class="py-2.5 text-right text-slate-400 dark:text-slate-500 whitespace-nowrap text-[11px] font-medium">
                            <?= htmlspecialchars($venta['fecha'] ?? '—') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Total de ventas recientes -->
        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700/50 flex items-center justify-between">
            <span class="text-xs text-slate-500 dark:text-slate-400">
                <span id="recentCount"><?= count($recentSales) ?></span> ventas mostradas
            </span>
            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                Total: <span id="recentTotal"><?= formatMoney(array_sum(array_column($recentSales, 'total'))) ?></span>
            </span>
        </div>

    </div><!-- /.recent sales -->

</div><!-- /.grid.main-content -->


<!-- =====================================================================
     CHARTS JS — INICIALIZACIÓN Y AUTO-REFRESH
     ===================================================================== -->
<script>
(function () {
    'use strict';

    // ─── DATA FROM PHP ────────────────────────────────────────────────
    const INITIAL_DATA = {
        summary: <?= json_encode($summary, JSON_UNESCAPED_UNICODE) ?>,
        sales_trend: <?= json_encode($salesTrend, JSON_UNESCAPED_UNICODE) ?>,
        payments: <?= json_encode($payments, JSON_UNESCAPED_UNICODE) ?>,
        hourly_sales: <?= json_encode($hourlySales, JSON_UNESCAPED_UNICODE) ?>,
        recent_sales: <?= json_encode($recentSales, JSON_UNESCAPED_UNICODE) ?>
    };

    // ─── COLOR PALETTE ────────────────────────────────────────────────
    const COLORS = {
        trend: {
            line: 'rgba(14, 165, 233, 1)',
            fill: 'rgba(14, 165, 233, 0.15)',
            fillTop: 'rgba(14, 165, 233, 0.3)',
            point: 'rgba(14, 165, 233, 1)',
            pointHover: 'rgba(56, 189, 248, 1)',
            grid: 'rgba(148, 163, 184, 0.1)',
            zero: 'rgba(148, 163, 184, 0.3)'
        },
        payment: ['#0ea5e9', '#10b981', '#8b5cf6', '#f59e0b', '#f97316', '#94a3b8'],
        hourly: {
            bar: 'rgba(245, 158, 11, 0.7)',
            barHover: 'rgba(245, 158, 11, 0.9)',
            border: 'rgba(245, 158, 11, 1)'
        }
    };

    // ─── GLOBAL CHART REFERENCES ──────────────────────────────────────
    let trendChart = null;
    let paymentChart = null;
    let hourlyChart = null;
    let refreshInterval = null;
    let isRefreshing = false;

    // ─── DARK MODE DETECTION ──────────────────────────────────────────
    function isDarkMode() {
        return document.documentElement.classList.contains('dark');
    }

    // ─── FORMAT HELPERS ───────────────────────────────────────────────
    function formatMoney(amount) {
        return 'S/ ' + Number(amount).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatNumber(num) {
        return Number(num).toLocaleString('es-PE');
    }

    // ─── DESTROY CHART SAFELY ─────────────────────────────────────────
    function destroyChart(chart) {
        if (chart) {
            chart.destroy();
        }
        Chart.getChart?.(chart?.canvas)?.destroy();
        return null;
    }

    // ─── CREATE TREND CHART (LÍNEA CON ÁREA) ─────────────────────────
// ─── CREATE TREND CHART (LÍNEA CON GRADIENTE DESVANECIDO PREMIUM) ───
    function createTrendChart(data) {
        const ctx = document.getElementById('trendChart');
        if (!ctx) return null;
        destroyChart(trendChart);

        const labels = data.labels || [];
        const values = data.data || [];
        const dark = isDarkMode();
        const canvas = ctx.getContext('2d');

        // Gradiente vertical: de azul vibrante arriba a 100% transparente abajo
        const gradient = canvas.createLinearGradient(0, 10, 0, 260);
        if (dark) {
            gradient.addColorStop(0, 'rgba(14, 165, 233, 0.40)');   // Azul neón arriba
            gradient.addColorStop(0.5, 'rgba(14, 165, 233, 0.10)'); // Transición media
            gradient.addColorStop(1, 'rgba(14, 165, 233, 0.00)');   // Completamente transparente abajo
        } else {
            gradient.addColorStop(0, 'rgba(14, 165, 233, 0.45)');   // Azul cielo suave arriba
            gradient.addColorStop(0.5, 'rgba(14, 165, 233, 0.12)'); // Transición media
            gradient.addColorStop(1, 'rgba(14, 165, 233, 0.00)');   // Completamente transparente abajo
        }

        trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Ventas (S/)',
                    data: values,
                    backgroundColor: gradient,
                    borderColor: '#0284c7', // Azul nítido
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4, // Curvatura orgánica suave (efecto spline)
                    
                    // Puntos limpios: ocultos en reposo, aparecen con efecto al pasar el cursor
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#0284c7',
                    pointHoverBackgroundColor: '#0284c7',
                    pointBorderColor: '#ffffff',
                    pointHoverBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointHoverBorderWidth: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: dark ? '#1e293b' : '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: '#38bdf8', // Monto resaltado en celeste
                        bodyFont: { weight: 'bold', size: 13 },
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false, // Ocultar cuadro de color para mayor limpieza
                        callbacks: {
                            label: ctx => 'Venta: S/ ' + Number(ctx.parsed.y).toLocaleString('es-PE', { minimumFractionDigits: 2 })
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { 
                            color: dark ? '#64748b' : '#94a3b8', 
                            font: { size: 10 },
                            maxTicksLimit: 12,
                            maxRotation: 0 // Etiquetas horizontales sin inclinación forzada
                        }
                    },
                    y: {
                        grid: { 
                            color: dark ? 'rgba(148, 163, 184, 0.06)' : 'rgba(148, 163, 184, 0.12)', 
                            drawBorder: false 
                        },
                        ticks: {
                            color: dark ? '#64748b' : '#94a3b8',
                            font: { size: 10 },
                            callback: v => 'S/ ' + Number(v).toLocaleString('es-PE'),
                            maxTicksLimit: 6
                        },
                        beginAtZero: true
                    }
                }
            }
        });

        return trendChart;
    }
    // ─── CREATE PAYMENT CHART (DOUGHNUT) ──────────────────────────────
    function createPaymentChart(data) {
        destroyChart(paymentChart);

        let ctx = document.getElementById('paymentChart');

        const labels = data.labels || [];
        const values = data.data || [];
        const hasData = values.some(v => v > 0);

        if (!hasData) {
            // Mostrar mensaje de sin datos
            if (ctx) {
                const parent = ctx.parentElement;
                parent.innerHTML = `
                    <div class="flex flex-col items-center justify-center text-slate-400 dark:text-slate-500 h-full">
                        <i class="fa-solid fa-chart-pie text-5xl mb-3 opacity-30"></i>
                        <p class="text-sm font-medium">Sin datos de pagos</p>
                        <p class="text-xs mt-1">No hay ventas en los últimos 30 días</p>
                    </div>
                `;
            }
            return null;
        }

        // El estado vacío reemplaza el canvas; lo recreamos cuando aparecen pagos.
        if (!ctx) {
            const parent = document.querySelector('#paymentLegend')?.previousElementSibling;
            if (parent) parent.innerHTML = '<canvas id="paymentChart"></canvas>';
            ctx = document.getElementById('paymentChart');
        }
        if (!ctx) return null;

        const dark = isDarkMode();

        paymentChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: COLORS.payment.slice(0, labels.length),
                    borderColor: dark ? '#1e293b' : '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 12,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: dark ? '#1e293b' : '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: '#f8fafc',
                        padding: 14,
                        cornerRadius: 12,
                        callbacks: {
                            label: ctx => {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ' ' + ctx.label + ': S/ ' + Number(ctx.parsed).toLocaleString('es-PE', {minimumFractionDigits: 2}) + ' (' + pct + '%)';
                            }
                        }
                    }
                },
                animation: {
                    animateRotate: true,
                    duration: 1000
                }
            }
        });

        return paymentChart;
    }

    // ─── CREATE HOURLY CHART (BARRAS) ─────────────────────────────────
    function createHourlyChart(data) {
        const ctx = document.getElementById('hourlyChart');
        if (!ctx) return null;
        destroyChart(hourlyChart);

        const labels = data.labels || [];
        const values = data.data || [];
        const dark = isDarkMode();

        // Encontrar la hora pico para resaltarla
        const maxVal = Math.max(...values, 0);

        hourlyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Ventas (S/)',
                    data: values,
                    backgroundColor: values.map(v => 
                        v === maxVal && v > 0 
                            ? (dark ? 'rgba(245, 158, 11, 0.9)' : COLORS.hourly.barHover)
                            : (dark ? 'rgba(245, 158, 11, 0.3)' : COLORS.hourly.bar)
                    ),
                    borderColor: values.map(v => 
                        v === maxVal && v > 0 ? COLORS.hourly.border : 'transparent'
                    ),
                    borderWidth: values.map(v => v === maxVal && v > 0 ? 2 : 0),
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: dark ? '#1e293b' : '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: '#f8fafc',
                        padding: 14,
                        cornerRadius: 12,
                        callbacks: {
                            label: ctx => ' S/ ' + Number(ctx.parsed.y).toLocaleString('es-PE', {minimumFractionDigits: 2})
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { 
                            color: dark ? '#64748b' : '#94a3b8', 
                            font: { size: 9 },
                            maxTicksLimit: 12,
                            maxRotation: 0
                        }
                    },
                    y: {
                        grid: { 
                            color: dark ? 'rgba(148, 163, 184, 0.06)' : 'rgba(148, 163, 184, 0.12)', 
                            drawBorder: false 
                        },
                        ticks: {
                            color: dark ? '#64748b' : '#94a3b8',
                            font: { size: 10 },
                            callback: v => 'S/ ' + Number(v).toLocaleString('es-PE'),
                            maxTicksLimit: 6
                        },
                        beginAtZero: true
                    }
                }
            }
        });

        return hourlyChart;
    }

    // ─── UPDATE STATS CARDS ───────────────────────────────────────────
    function updateStats(summary) {
        // Ventas hoy
        const elVentasHoy = document.getElementById('statVentasHoy');
        if (elVentasHoy) elVentasHoy.textContent = formatNumber(summary.ventas_hoy || 0);

        // Monto hoy
        const elMontoHoy = document.getElementById('statMontoHoy');
        if (elMontoHoy) elMontoHoy.textContent = formatMoney(summary.monto_hoy || 0);

        // Ticket promedio
        const elTicket = document.getElementById('statTicketPromedio');
        if (elTicket) elTicket.textContent = formatMoney(summary.ticket_promedio || 0);

        // Mejor hora
        const elMejorHora = document.getElementById('statMejorHora');
        if (elMejorHora && summary.mejor_hora) elMejorHora.textContent = summary.mejor_hora.label || '—';
        const elMejorHoraMonto = document.getElementById('statMejorHoraMonto');
        if (elMejorHoraMonto && summary.mejor_hora) elMejorHoraMonto.textContent = formatMoney(summary.mejor_hora.amount || 0);

        // Hora baja
        const elHoraBaja = document.getElementById('statHoraBaja');
        if (elHoraBaja && summary.hora_baja) elHoraBaja.textContent = summary.hora_baja.label || '—';
        const elHoraBajaMonto = document.getElementById('statHoraBajaMonto');
        if (elHoraBajaMonto && summary.hora_baja) elHoraBajaMonto.textContent = formatMoney(summary.hora_baja.amount || 0);

        // Bajo stock
        const elBajoStock = document.getElementById('statBajoStock');
        if (elBajoStock) elBajoStock.textContent = formatNumber(summary.bajo_stock || 0);

        // Total 30 días
        const elTotal30d = document.getElementById('total30dAmount');
        if (elTotal30d) {
            const total = (data && data.sales_trend && data.sales_trend.data) 
                ? data.sales_trend.data.reduce((a, b) => a + b, 0) 
                : 0;
            elTotal30d.textContent = formatMoney(total);
        }

        // Peak hour label
        const elPeakHour = document.getElementById('peakHourLabel');
        if (elPeakHour && summary.mejor_hora) elPeakHour.textContent = summary.mejor_hora.label || '—';
    }

    // ─── UPDATE RECENT SALES TABLE ────────────────────────────────────
    function updateRecentSales(sales) {
        const tbody = document.getElementById('recentSalesBody');
        if (!tbody) return;

        if (!sales || sales.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-8 text-slate-400 dark:text-slate-500">
                        <i class="fa-solid fa-inbox text-3xl mb-2 opacity-30"></i>
                        <p class="text-sm">No hay ventas recientes</p>
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        sales.forEach(sale => {
            html += `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors rounded-lg">
                    <td class="py-2.5 pr-2">
                        <span class="inline-block bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-500/10 dark:to-indigo-500/10 text-blue-700 dark:text-blue-400 text-[11px] font-mono font-bold px-2 py-1 rounded-md">
                            ${sale.comprobante || '—'}
                        </span>
                    </td>
                    <td class="py-2.5 pr-2 text-slate-700 dark:text-slate-300 truncate max-w-[100px] text-xs font-medium">
                        ${sale.cliente || '—'}
                    </td>
                    <td class="py-2.5 pr-2 text-right font-bold text-slate-800 dark:text-white text-sm whitespace-nowrap">
                        ${formatMoney(sale.total || 0)}
                    </td>
                    <td class="py-2.5 text-right text-slate-400 dark:text-slate-500 whitespace-nowrap text-[11px] font-medium">
                        ${sale.fecha || '—'}
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;

        // Update count and total
        const countEl = document.getElementById('recentCount');
        if (countEl) countEl.textContent = sales.length;

        const totalEl = document.getElementById('recentTotal');
        if (totalEl) {
            const total = sales.reduce((sum, s) => sum + parseFloat(s.total || 0), 0);
            totalEl.textContent = formatMoney(total);
        }
    }

    // ─── UPDATE PAYMENT LEGEND ────────────────────────────────────────
    function updatePaymentLegend(payments) {
        const legend = document.getElementById('paymentLegend');
        if (!legend || !payments) return;

        const labels = payments.labels || [];
        const values = payments.data || [];
        const total = values.reduce((a, b) => a + b, 0);
        const colors = ['#0ea5e9', '#10b981', '#8b5cf6', '#f59e0b', '#f97316', '#94a3b8'];

        let html = '';
        labels.forEach((label, i) => {
            const pct = total > 0 ? ((values[i] / total) * 100).toFixed(1) : 0;
            const color = colors[i % colors.length];
            html += `
                <div class="flex items-center gap-2 text-xs">
                    <span class="w-3 h-3 rounded-full shrink-0" style="background: ${color}"></span>
                    <span class="text-slate-600 dark:text-slate-300">${label}</span>
                    <span class="ml-auto font-semibold text-slate-800 dark:text-white">${pct}%</span>
                </div>
            `;
        });
        legend.innerHTML = html;
    }

    // ─── MAIN UPDATE FUNCTION ─────────────────────────────────────────
    let data = INITIAL_DATA;

    function updateDashboard(newData) {
        if (!newData) return;
        data = newData;

        // Exponer datos globalmente para que el theme toggle pueda repintar
        window.__dashboardData = newData;

        // 1. Actualizar cards
        if (newData.summary) updateStats(newData.summary);

        // 2. Re-crear gráficos
        if (newData.sales_trend) createTrendChart(newData.sales_trend);
        if (newData.payments) {
            createPaymentChart(newData.payments);
            updatePaymentLegend(newData.payments);
        }
        if (newData.hourly_sales) createHourlyChart(newData.hourly_sales);

        // 3. Actualizar tabla de ventas recientes
        if (newData.recent_sales) updateRecentSales(newData.recent_sales);

        // 4. Actualizar total 30d
        const elTotal30d = document.getElementById('total30dAmount');
        if (elTotal30d && newData.sales_trend && newData.sales_trend.data) {
            const total = newData.sales_trend.data.reduce((a, b) => a + b, 0);
            elTotal30d.textContent = formatMoney(total);
        }

        // 5. Actualizar timestamp
        const elLastUpdate = document.getElementById('lastUpdatedText');
        if (elLastUpdate) {
            elLastUpdate.textContent = 'Actualizado ' + new Date().toLocaleTimeString('es-PE');
        }
    }

    // ─── FETCH LIVE DATA ──────────────────────────────────────────────
    async function refreshDashboard() {
        if (isRefreshing) return;
        isRefreshing = true;

        const icon = document.getElementById('refreshIcon');
        if (icon) icon.classList.add('fa-spin');

        try {
            const response = await fetch('/dashboard/live-data', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-cache'
            });

            if (!response.ok) throw new Error('Error ' + response.status);

            const result = await response.json();
            if (result.success && result.data) {
                updateDashboard(result.data);
            }
        } catch (err) {
            console.warn('Dashboard refresh error:', err);
        } finally {
            isRefreshing = false;
            if (icon) icon.classList.remove('fa-spin');
        }
    }

    // ─── CLOCK DISPLAY ────────────────────────────────────────────────
    function updateClock() {
        const el = document.getElementById('clockDisplay');
        if (el) {
            const now = new Date();
            el.textContent = now.toLocaleTimeString('es-PE', { hour12: false });
        }
    }
    setInterval(updateClock, 1000);

    // ─── INIT ─────────────────────────────────────────────────────────
    function init() {
        // Esperar que Chart.js cargue
        function waitForChart() {
            if (typeof Chart === 'undefined') {
                setTimeout(waitForChart, 100);
                return;
            }
            
            // Registrar plugin para fondo oscuro
            if (typeof Chart.register === 'function') {
                // No plugin needed, handled in options
            }

            // Inicializar gráficos con datos iniciales
            updateDashboard(INITIAL_DATA);

            // Iniciar auto-refresh cada 30 segundos
            refreshInterval = setInterval(refreshDashboard, 30000);
        }
        waitForChart();
    }

    // ─── EXPOSE TO GLOBAL ─────────────────────────────────────────────
    window.refreshDashboard = refreshDashboard;
    window.updateDashboard = updateDashboard;

    // ─── START ────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
</script>
