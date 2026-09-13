<?php
/**
 * Caja (Cash Register) View — injected into main layout
 *
 * Variables expected from controller:
 *   $cajaAbierta     null | array
 *   $movimientos     array
 *   $totales         array  ['ingresos_pen'=>0, 'egresos_pen'=>0, 'ingresos_usd'=>0, 'egresos_usd'=>0]
 *   $cierreCalculado null | array
 *   $historial       array
 */

$cajaAbierta     = $cajaAbierta ?? null;
$movimientos     = $movimientos ?? [];
$totales         = $totales     ?? ['ingresos_pen'=>0,'egresos_pen'=>0,'ingresos_usd'=>0,'egresos_usd'=>0];
$cierreCalculado = $cierreCalculado ?? null;
$historial       = $historial ?? [];

if (!function_exists('formatMoney')) {
    function formatMoney(float $amount, string $symbol = 'S/'): string {
        return $symbol . ' ' . number_format($amount, 2, '.', ',');
    }
}

// Fecha en español peruano
$diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$mesesAnio  = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$fechaEspanol = $diasSemana[(int)date('w')] . ', ' . date('d') . ' de ' . $mesesAnio[(int)date('n') - 1] . ' de ' . date('Y');
?>

<!-- =====================================================================
     PAGE HEADER CON FECHA EN ESPAÑOL Y CONTRASTE
     ===================================================================== -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-800 dark:text-white flex items-center">
            <i class="fa-solid fa-cash-register text-emerald-500 mr-2"></i>Gestión de Caja
        </h1>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5">
            <i class="fa-regular fa-calendar mr-1"></i><?= $fechaEspanol ?>
        </p>
    </div>
    
    <!-- Status badge -->
    <?php if ($cajaAbierta): ?>
    <span class="inline-flex items-center gap-2 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-400 text-sm font-semibold px-4 py-2 rounded-xl shadow-sm">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        Caja Abierta
    </span>
    <?php else: ?>
    <span class="inline-flex items-center gap-2 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-600 dark:text-rose-400 text-sm font-semibold px-4 py-2 rounded-xl shadow-sm">
        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
        Caja Cerrada
    </span>
    <?php endif; ?>
</div>

<!-- =====================================================================
     STATE A: CAJA CERRADA — Formulario "Abrir Caja"
     ===================================================================== -->
<?php if (!$cajaAbierta): ?>

<div class="max-w-lg mx-auto">
    <div class="bg-white dark:bg-slate-900/70 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800 overflow-hidden">

        <!-- Card header -->
        <div class="bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-5">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-lock-open"></i> Apertura de Caja
            </h2>
            <p class="text-emerald-100 text-sm mt-0.5">Ingresa los montos iniciales para comenzar el turno</p>
        </div>

        <div class="p-6">
            <form action="<?= baseUrl('caja/abrir') ?>" method="POST" id="formAbrirCaja" data-turbo="false" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- Monto PEN -->
                <div class="mb-5">
                    <label for="monto_inicial_pen" class="block text-slate-700 dark:text-slate-300 text-sm font-semibold mb-2">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="text-base">🇵🇪</span> Monto Inicial en Soles (PEN)
                        </span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-semibold text-sm select-none">S/</span>
                        <input
                            type="number"
                            id="monto_inicial_pen"
                            name="monto_inicial_pen"
                            step="0.01"
                            min="0"
                            value="0.00"
                            required
                            class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 rounded-xl px-4 py-3 pl-10 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 transition-all font-bold"
                        >
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="mb-6">
                    <label for="observaciones_apertura" class="block text-slate-700 dark:text-slate-300 text-sm font-semibold mb-2">
                        <i class="fa-solid fa-note-sticky text-slate-400 mr-1"></i> Observaciones (opcional)
                    </label>
                    <textarea
                        id="observaciones_apertura"
                        name="observaciones"
                        rows="2"
                        placeholder="Notas de apertura..."
                        class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 rounded-xl px-4 py-3 text-slate-800 dark:text-white text-sm resize-none focus:outline-none focus:ring-2 focus:ring-emerald-400 transition-all"
                    ></textarea>
                </div>

                <button
                    type="submit"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 px-6 rounded-xl text-sm flex items-center justify-center gap-2 transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0"
                >
                    <i class="fa-solid fa-cash-register"></i>
                    Abrir Caja Ahora
                </button>
            </form>
        </div>
    </div>
</div>

<?php else: /* ---- CAJA ABIERTA ---- */ ?>

<!-- =====================================================================
     STATE B: CAJA ABIERTA — Tarjetas de Información y Métricas
     ===================================================================== -->

<!-- Fila 1: 4 Tarjetas de Información Superior -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">

    <!-- Abierta por -->
    <div class="bg-white dark:bg-slate-900/70 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm p-5 transition-colors">
        <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold mb-1">Abierta por</p>
        <p class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
            <i class="fa-solid fa-user-circle text-sky-500"></i>
            <?= htmlspecialchars($cajaAbierta['abierta_por'] ?? '—') ?>
        </p>
    </div>

    <!-- Cierre automático estimado (CORREGIDO) -->
    <div class="bg-sky-50/80 dark:bg-slate-900/70 rounded-2xl border border-sky-200/60 dark:border-sky-500/30 shadow-sm p-5 transition-colors">
        <p class="text-xs text-sky-600 dark:text-sky-400 uppercase tracking-wider font-semibold mb-1">Cierre estimado</p>
        <p class="text-base font-bold text-sky-900 dark:text-sky-300">
            <?= formatMoney((float)($cierreCalculado['efectivo_sistema_pen'] ?? 0), 'S/') ?>
            <span class="text-sky-400 font-normal mx-1">+</span>
            <?= formatMoney((float)($cierreCalculado['efectivo_sistema_usd'] ?? 0), '$') ?>
        </p>
    </div>

    <!-- Hora de Apertura -->
    <div class="bg-white dark:bg-slate-900/70 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm p-5 transition-colors">
        <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold mb-1">Hora de Apertura</p>
        <p class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
            <i class="fa-solid fa-clock text-emerald-500"></i>
            <?= htmlspecialchars($cajaAbierta['hora_apertura'] ?? '—') ?>
        </p>
    </div>

    <!-- Montos Iniciales -->
    <div class="bg-white dark:bg-slate-900/70 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm p-5 transition-colors">
        <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wider font-semibold mb-1">Montos Iniciales</p>
        <p class="text-base font-bold text-slate-800 dark:text-slate-100">
            <?= formatMoney((float)($cajaAbierta['monto_inicial_pen'] ?? 0), 'S/') ?>
            <span class="text-slate-400 font-normal mx-1">/</span>
            <?= formatMoney((float)($cajaAbierta['monto_inicial_usd'] ?? 0), '$') ?>
        </p>
    </div>

</div>

<!-- Fila 2: 4 Tarjetas de Ingresos y Egresos (CORREGIDAS PARA MODO OSCURO) -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">

    <!-- Ingresos PEN -->
    <div class="bg-emerald-50/80 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/40 rounded-2xl p-4 text-center transition-colors">
        <p class="text-xs text-emerald-700 dark:text-emerald-400 font-bold uppercase tracking-wider mb-1">Ingresos PEN</p>
        <p class="text-xl font-black text-emerald-800 dark:text-emerald-300"><?= formatMoney((float)$totales['ingresos_pen'], 'S/') ?></p>
    </div>

    <!-- Egresos PEN -->
    <div class="bg-rose-50/80 dark:bg-rose-950/20 border border-rose-200/80 dark:border-rose-800/40 rounded-2xl p-4 text-center transition-colors">
        <p class="text-xs text-rose-700 dark:text-rose-400 font-bold uppercase tracking-wider mb-1">Egresos PEN</p>
        <p class="text-xl font-black text-rose-800 dark:text-rose-300"><?= formatMoney((float)$totales['egresos_pen'], 'S/') ?></p>
    </div>

    <!-- Ingresos USD -->
    <div class="bg-emerald-50/80 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-800/40 rounded-2xl p-4 text-center transition-colors">
        <p class="text-xs text-emerald-700 dark:text-emerald-400 font-bold uppercase tracking-wider mb-1">Ingresos USD</p>
        <p class="text-xl font-black text-emerald-800 dark:text-emerald-300"><?= formatMoney((float)$totales['ingresos_usd'], '$') ?></p>
    </div>

    <!-- Egresos USD -->
    <div class="bg-rose-50/80 dark:bg-rose-950/20 border border-rose-200/80 dark:border-rose-800/40 rounded-2xl p-4 text-center transition-colors">
        <p class="text-xs text-rose-700 dark:text-rose-400 font-bold uppercase tracking-wider mb-1">Egresos USD</p>
        <p class="text-xl font-black text-rose-800 dark:text-rose-300"><?= formatMoney((float)$totales['egresos_usd'], '$') ?></p>
    </div>

</div>

<!-- Tabla de Movimientos del Día -->
<div class="bg-white dark:bg-slate-900/70 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm mb-6 overflow-hidden transition-colors">

    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-800">
        <h2 class="text-base font-bold text-slate-800 dark:text-white flex items-center">
            <i class="fa-solid fa-list-ul text-sky-500 mr-2"></i>Movimientos de Hoy
        </h2>
        <span class="text-xs text-slate-400 font-medium"><?= count($movimientos) ?> registro(s)</span>
    </div>

    <?php if (empty($movimientos)): ?>
    <div class="py-12 text-center text-slate-400 dark:text-slate-500">
        <i class="fa-solid fa-inbox text-4xl mb-3 opacity-30 block"></i>
        <p class="text-sm font-medium">No hay movimientos registrados aún</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3.5">Hora</th>
                    <th class="px-4 py-3.5">Tipo</th>
                    <th class="px-4 py-3.5">Descripción</th>
                    <th class="text-center px-4 py-3.5">Moneda</th>
                    <th class="text-right px-6 py-3.5">Monto</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-slate-700 dark:text-slate-300">
                <?php foreach ($movimientos as $mov): ?>
                <?php
                    $esIngreso = strtolower($mov['tipo'] ?? '') === 'ingreso';
                    $simbolo   = strtoupper($mov['moneda'] ?? 'PEN') === 'USD' ? '$' : 'S/';
                ?>
                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-6 py-3 text-slate-400 font-mono text-xs whitespace-nowrap">
                        <?= htmlspecialchars($mov['hora'] ?? '—') ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($esIngreso): ?>
                        <span class="inline-flex items-center gap-1 bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-xs font-bold px-2.5 py-1 rounded-full">
                            <i class="fa-solid fa-arrow-down text-xs"></i> Ingreso
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 text-xs font-bold px-2.5 py-1 rounded-full">
                            <i class="fa-solid fa-arrow-up text-xs"></i> Egreso
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-800 dark:text-slate-200 font-medium">
                        <?= htmlspecialchars($mov['descripcion'] ?? '—') ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-mono font-bold px-2 py-0.5 rounded">
                            <?= htmlspecialchars(strtoupper($mov['moneda'] ?? 'PEN')) ?>
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right font-bold <?= $esIngreso ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' ?> whitespace-nowrap">
                        <?= ($esIngreso ? '+' : '−') . formatMoney((float)($mov['monto'] ?? 0), $simbolo) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<!-- Botón Cerrar Caja -->
<div class="flex justify-end">
    <button
        type="button"
        onclick="document.getElementById('modalCerrarCaja').classList.remove('hidden'); document.getElementById('modalCerrarCaja').classList.add('flex')"
        class="inline-flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white font-bold px-6 py-3 rounded-xl text-sm transition-all duration-200 shadow-md hover:shadow-rose-500/30 hover:-translate-y-0.5 active:translate-y-0"
    >
        <i class="fa-solid fa-lock"></i> Cerrar Caja
    </button>
</div>

<!-- =====================================================================
     MODAL: CERRAR CAJA
     ===================================================================== -->
<div id="modalCerrarCaja" class="hidden fixed inset-0 z-50 items-center justify-center px-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
         onclick="document.getElementById('modalCerrarCaja').classList.add('hidden'); document.getElementById('modalCerrarCaja').classList.remove('flex')"></div>

    <!-- Modal card -->
    <div class="relative z-10 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-transparent dark:border-slate-700">

        <div class="bg-gradient-to-r from-rose-500 to-red-600 px-6 py-5">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-lock"></i> Cerrar Caja
            </h3>
            <p class="text-rose-100 text-sm mt-0.5">El sistema calculará el saldo con las ventas registradas</p>
        </div>

        <form action="<?= baseUrl('caja/cerrar') ?>" method="POST" class="p-6" data-turbo="false" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="apertura_id" value="<?= (int)($cajaAbierta['id'] ?? 0) ?>">

            <div class="mb-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 p-4">
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-1 font-semibold">Saldo detectado al cerrar</p>
                <p class="text-xl font-bold text-slate-800 dark:text-white">
                    <?= formatMoney((float)($cierreCalculado['efectivo_sistema_pen'] ?? 0), 'S/') ?>
                    <span class="text-slate-400 text-sm font-normal">en efectivo PEN</span>
                </p>
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300 mt-1">
                    <?= formatMoney((float)($cierreCalculado['efectivo_sistema_usd'] ?? 0), '$') ?> en efectivo USD
                </p>
            </div>

            <!-- Valores calculados -->
            <div class="mb-4">
                <label for="monto_final_pen" class="block text-slate-700 dark:text-slate-300 text-sm font-semibold mb-2">
                    🇵🇪 Monto Final en Soles (PEN)
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-semibold text-sm select-none">S/</span>
                    <input
                        type="number"
                        id="monto_final_pen"
                        name="monto_final"
                        step="0.01"
                        min="0"
                        value="<?= number_format((float)($cierreCalculado['efectivo_sistema_pen'] ?? 0), 2, '.', '') ?>"
                        readonly
                        aria-readonly="true"
                        class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 rounded-xl px-4 py-3 pl-10 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 transition-all font-bold"
                    >
                </div>
            </div>

            <!-- Monto final USD -->
            <div class="mb-4">
                <label for="monto_final_usd" class="block text-slate-700 dark:text-slate-300 text-sm font-semibold mb-2">
                    🇺🇸 Monto Final en Dólares (USD)
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-semibold text-sm select-none">$</span>
                    <input
                        type="number"
                        id="monto_final_usd"
                        name="monto_final_usd"
                        step="0.01"
                        min="0"
                        value="<?= number_format((float)($cierreCalculado['efectivo_sistema_usd'] ?? 0), 2, '.', '') ?>"
                        readonly
                        aria-readonly="true"
                        class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 rounded-xl px-4 py-3 pl-10 text-slate-800 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 transition-all font-bold"
                    >
                </div>
            </div>

            <!-- Observaciones cierre -->
            <div class="mb-6">
                <label for="observaciones_cierre" class="block text-slate-700 dark:text-slate-300 text-sm font-semibold mb-2">
                    Observaciones (opcional)
                </label>
                <textarea
                    id="observaciones_cierre"
                    name="observaciones"
                    rows="2"
                    placeholder="Notas de cierre..."
                    class="w-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 rounded-xl px-4 py-3 text-slate-800 dark:text-white text-sm resize-none focus:outline-none focus:ring-2 focus:ring-rose-400 transition-all"
                ></textarea>
            </div>

            <div class="flex gap-3">
                <button
                    type="button"
                    onclick="document.getElementById('modalCerrarCaja').classList.add('hidden'); document.getElementById('modalCerrarCaja').classList.remove('flex')"
                    class="flex-1 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold py-2.5 px-4 rounded-xl text-sm hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    class="flex-1 bg-rose-600 hover:bg-rose-700 text-white font-semibold py-2.5 px-4 rounded-xl text-sm flex items-center justify-center gap-2 transition-colors shadow-sm"
                >
                    <i class="fa-solid fa-lock"></i> Confirmar Cierre
                </button>
            </div>
        </form>
    </div>
</div>

<?php endif; /* end caja state */ ?>

<!-- Historial diario de cierres -->
<section class="mt-8 bg-white dark:bg-slate-900/70 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm overflow-hidden transition-colors">
    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div>
            <h2 class="text-base font-bold text-slate-800 dark:text-white flex items-center">
                <i class="fa-solid fa-calendar-days text-sky-500 mr-2"></i>Historial diario de caja
            </h2>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Consulta cuánto quedó registrado en cada fecha</p>
        </div>
        <span class="text-xs text-slate-400 dark:text-slate-500 font-medium"><?= count($historial) ?> día(s)</span>
    </div>
    <?php if (empty($historial)): ?>
        <div class="py-10 text-center text-slate-400 dark:text-slate-500 text-sm font-medium">Aún no hay cierres registrados.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3.5">Fecha</th>
                        <th class="px-4 py-3.5">Apertura / cierre</th>
                        <th class="text-right px-4 py-3.5">Ventas del día</th>
                        <th class="text-right px-6 py-3.5">Saldo detectado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-slate-700 dark:text-slate-300">
                    <?php foreach ($historial as $dia): ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap">
                                <i class="fa-regular fa-calendar text-sky-500 mr-2"></i><?= htmlspecialchars(date('d/m/Y', strtotime($dia['fecha_cierre'] ?? $dia['fecha_apertura']))) ?>
                            </td>
                            <td class="px-4 py-4 text-slate-400 whitespace-nowrap text-xs">
                                <?= htmlspecialchars($dia['fecha_apertura'] ?? '—') ?> / <?= htmlspecialchars($dia['fecha_cierre'] ?? 'Pendiente') ?>
                            </td>
                            <td class="px-4 py-4 text-right text-slate-800 dark:text-slate-200 font-semibold">
                                <?= formatMoney((float)($dia['total_ventas'] ?? 0), 'S/') ?>
                                <span class="block text-xs text-slate-400 font-normal"><?= (int)($dia['cantidad_ventas'] ?? 0) ?> venta(s)</span>
                            </td>
                            <td class="px-6 py-4 text-right font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                <?= formatMoney((float)($dia['saldo_final_efectivo_pen'] ?? 0), 'S/') ?>
                                <span class="block text-xs font-normal text-slate-400"><?= formatMoney((float)($dia['saldo_final_usd'] ?? 0), '$') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>