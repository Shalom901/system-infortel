<?php
/**
 * Caja (Cash Register) View — injected into main layout
 *
 * Variables expected from controller:
 *   $cajaAbierta  null | array  [
 *                     'id'            => int,
 *                     'abierta_por'   => string,
 *                     'hora_apertura' => string,
 *                     'monto_inicial_pen' => float,
 *                     'monto_inicial_usd' => float,
 *                  ]
 *   $movimientos  array  [['tipo'=>'ingreso|egreso', 'descripcion'=>'', 'monto'=>0.00,
 *                           'moneda'=>'PEN|USD', 'hora'=>''], ...]
 *   $totales      array  ['ingresos_pen'=>0, 'egresos_pen'=>0, 'ingresos_usd'=>0, 'egresos_usd'=>0]
 */

$cajaAbierta = $cajaAbierta ?? null;
$movimientos = $movimientos ?? [];
$totales     = $totales     ?? ['ingresos_pen'=>0,'egresos_pen'=>0,'ingresos_usd'=>0,'egresos_usd'=>0];
$cierreCalculado = $cierreCalculado ?? null;
$historial   = $historial ?? [];

if (!function_exists('formatMoney')) {
    function formatMoney(float $amount, string $symbol = 'S/'): string {
        return $symbol . ' ' . number_format($amount, 2, '.', ',');
    }
}
?>

<!-- =====================================================================
     PAGE HEADER
     ===================================================================== -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">
            <i class="fa-solid fa-cash-register text-emerald-500 mr-2"></i>Gestión de Caja
        </h1>
        <p class="text-slate-500 text-sm mt-0.5"><?= date('l, d \d\e F \d\e Y') ?></p>
    </div>
    <!-- Status badge -->
    <?php if ($cajaAbierta): ?>
    <span class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold px-4 py-2 rounded-xl">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        Caja Abierta
    </span>
    <?php else: ?>
    <span class="inline-flex items-center gap-2 bg-red-50 border border-red-200 text-red-600 text-sm font-semibold px-4 py-2 rounded-xl">
        <span class="w-2 h-2 rounded-full bg-red-500"></span>
        Caja Cerrada
    </span>
    <?php endif; ?>
</div>

<!-- =====================================================================
     STATE A: CAJA CERRADA — Show "Abrir Caja" form
     ===================================================================== -->
<?php if (!$cajaAbierta): ?>

<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

        <!-- Card header -->
        <div class="bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-5">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-lock-open"></i> Apertura de Caja
            </h2>
            <p class="text-emerald-100 text-sm mt-0.5">Ingresa los montos iniciales para comenzar</p>
        </div>

        <div class="p-6">
            <form action="<?= baseUrl('caja/abrir') ?>" method="POST" id="formAbrirCaja" data-turbo="false" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- Monto PEN -->
                <div class="mb-5">
                    <label for="monto_inicial_pen" class="block text-slate-700 text-sm font-semibold mb-2">
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
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 pl-10 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition-all"
                        >
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="mb-6">
                    <label for="observaciones_apertura" class="block text-slate-700 text-sm font-semibold mb-2">
                        <i class="fa-solid fa-note-sticky text-slate-400 mr-1"></i> Observaciones (opcional)
                    </label>
                    <textarea
                        id="observaciones_apertura"
                        name="observaciones"
                        rows="2"
                        placeholder="Notas de apertura..."
                        class="w-full border border-slate-200 rounded-xl px-4 py-3 text-slate-800 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition-all"
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
     STATE B: CAJA ABIERTA — Info + Close button + Movements table
     ===================================================================== -->

<!-- Info cards row -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">

    <!-- Opened by -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        <p class="text-xs text-slate-500 uppercase tracking-wide font-medium mb-1">Abierta por</p>
        <p class="text-base font-bold text-slate-800 flex items-center gap-2">
            <i class="fa-solid fa-user-circle text-sky-400"></i>
            <?= htmlspecialchars($cajaAbierta['abierta_por'] ?? '—') ?>
        </p>
    </div>

    <div class="bg-sky-50 rounded-2xl border border-sky-200 shadow-sm p-5">
        <p class="text-xs text-sky-600 uppercase tracking-wide font-medium mb-1">Cierre automático estimado</p>
        <p class="text-base font-bold text-sky-800">
            <?= formatMoney((float)($cierreCalculado['efectivo_sistema_pen'] ?? 0), 'S/') ?>
            <span class="text-sky-500 font-normal">+</span>
            <?= formatMoney((float)($cierreCalculado['efectivo_sistema_usd'] ?? 0), '$') ?>
        </p>
    </div>

    <!-- Time -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        <p class="text-xs text-slate-500 uppercase tracking-wide font-medium mb-1">Hora de Apertura</p>
        <p class="text-base font-bold text-slate-800 flex items-center gap-2">
            <i class="fa-solid fa-clock text-emerald-400"></i>
            <?= htmlspecialchars($cajaAbierta['hora_apertura'] ?? '—') ?>
        </p>
    </div>

    <!-- Initial amounts -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
        <p class="text-xs text-slate-500 uppercase tracking-wide font-medium mb-1">Montos Iniciales</p>
        <p class="text-sm font-bold text-slate-800">
            <?= formatMoney((float)($cajaAbierta['monto_inicial_pen'] ?? 0), 'S/') ?>
            <span class="text-slate-400 font-normal mx-1">/</span>
            <?= formatMoney((float)($cajaAbierta['monto_inicial_usd'] ?? 0), '$') ?>
        </p>
    </div>

</div>

<!-- Summary totals -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">

    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-center">
        <p class="text-xs text-emerald-600 font-semibold uppercase tracking-wide mb-1">Ingresos PEN</p>
        <p class="text-lg font-bold text-emerald-700"><?= formatMoney((float)$totales['ingresos_pen'], 'S/') ?></p>
    </div>

    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
        <p class="text-xs text-red-600 font-semibold uppercase tracking-wide mb-1">Egresos PEN</p>
        <p class="text-lg font-bold text-red-700"><?= formatMoney((float)$totales['egresos_pen'], 'S/') ?></p>
    </div>

    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-center">
        <p class="text-xs text-emerald-600 font-semibold uppercase tracking-wide mb-1">Ingresos USD</p>
        <p class="text-lg font-bold text-emerald-700"><?= formatMoney((float)$totales['ingresos_usd'], '$') ?></p>
    </div>

    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
        <p class="text-xs text-red-600 font-semibold uppercase tracking-wide mb-1">Egresos USD</p>
        <p class="text-lg font-bold text-red-700"><?= formatMoney((float)$totales['egresos_usd'], '$') ?></p>
    </div>

</div>

<!-- Movements table -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm mb-6 overflow-hidden">

    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
        <h2 class="text-base font-bold text-slate-800">
            <i class="fa-solid fa-list-ul text-slate-400 mr-2"></i>Movimientos de Hoy
        </h2>
        <span class="text-xs text-slate-400"><?= count($movimientos) ?> registro(s)</span>
    </div>

    <?php if (empty($movimientos)): ?>
    <div class="py-12 text-center text-slate-400">
        <i class="fa-solid fa-inbox text-4xl mb-3 opacity-40 block"></i>
        <p class="text-sm">No hay movimientos registrados aún</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="text-left text-xs font-semibold text-slate-500 px-6 py-3">Hora</th>
                    <th class="text-left text-xs font-semibold text-slate-500 px-4 py-3">Tipo</th>
                    <th class="text-left text-xs font-semibold text-slate-500 px-4 py-3">Descripción</th>
                    <th class="text-center text-xs font-semibold text-slate-500 px-4 py-3">Moneda</th>
                    <th class="text-right text-xs font-semibold text-slate-500 px-6 py-3">Monto</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($movimientos as $mov): ?>
                <?php
                    $esIngreso = strtolower($mov['tipo'] ?? '') === 'ingreso';
                    $simbolo   = strtoupper($mov['moneda'] ?? 'PEN') === 'USD' ? '$' : 'S/';
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-3 text-slate-500 font-mono text-xs whitespace-nowrap">
                        <?= htmlspecialchars($mov['hora'] ?? '—') ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($esIngreso): ?>
                        <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                            <i class="fa-solid fa-arrow-down text-xs"></i> Ingreso
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 bg-red-50 text-red-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                            <i class="fa-solid fa-arrow-up text-xs"></i> Egreso
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-700">
                        <?= htmlspecialchars($mov['descripcion'] ?? '—') ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-block bg-slate-100 text-slate-600 text-xs font-mono font-bold px-2 py-0.5 rounded">
                            <?= htmlspecialchars(strtoupper($mov['moneda'] ?? 'PEN')) ?>
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right font-bold <?= $esIngreso ? 'text-emerald-600' : 'text-red-600' ?> whitespace-nowrap">
                        <?= ($esIngreso ? '+' : '−') . formatMoney((float)($mov['monto'] ?? 0), $simbolo) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div><!-- /.movements table -->

<!-- Cerrar Caja button / form -->
<div class="flex justify-end">
    <button
        type="button"
        onclick="document.getElementById('modalCerrarCaja').classList.remove('hidden'); document.getElementById('modalCerrarCaja').classList.add('flex')"
        class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-3 rounded-xl text-sm transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0"
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
    <div class="relative z-10 bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

        <div class="bg-gradient-to-r from-red-500 to-rose-600 px-6 py-5">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-lock"></i> Cerrar Caja
            </h3>
            <p class="text-red-100 text-sm mt-0.5">El sistema calculará el saldo con las ventas registradas</p>
        </div>

        <form action="<?= baseUrl('caja/cerrar') ?>" method="POST" class="p-6" data-turbo="false" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="apertura_id" value="<?= (int)($cajaAbierta['id'] ?? 0) ?>">

            <div class="mb-4 rounded-xl bg-slate-50 border border-slate-200 p-4">
                <p class="text-xs text-slate-500 mb-1">Saldo detectado al cerrar</p>
                <p class="text-xl font-bold text-slate-800">
                    <?= formatMoney((float)($cierreCalculado['efectivo_sistema_pen'] ?? 0), 'S/') ?>
                    <span class="text-slate-400 text-sm font-normal">en efectivo PEN</span>
                </p>
                <p class="text-sm font-semibold text-slate-600 mt-1">
                    <?= formatMoney((float)($cierreCalculado['efectivo_sistema_usd'] ?? 0), '$') ?> en efectivo USD
                </p>
            </div>

            <!-- Valores calculados enviados para compatibilidad -->
            <div class="mb-4">
                <label for="monto_final_pen" class="block text-slate-700 text-sm font-semibold mb-2">
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
                        class="w-full border border-slate-200 rounded-xl px-4 py-3 pl-10 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition-all"
                    >
                </div>
            </div>

            <!-- Monto final USD -->
            <div class="mb-4">
                <label for="monto_final_usd" class="block text-slate-700 text-sm font-semibold mb-2">
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
                        class="w-full border border-slate-200 rounded-xl px-4 py-3 pl-10 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition-all"
                    >
                </div>
            </div>

            <!-- Observaciones cierre -->
            <div class="mb-6">
                <label for="observaciones_cierre" class="block text-slate-700 text-sm font-semibold mb-2">
                    Observaciones (opcional)
                </label>
                <textarea
                    id="observaciones_cierre"
                    name="observaciones"
                    rows="2"
                    placeholder="Notas de cierre..."
                    class="w-full border border-slate-200 rounded-xl px-4 py-3 text-slate-800 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent transition-all"
                ></textarea>
            </div>

            <div class="flex gap-3">
                <button
                    type="button"
                    onclick="document.getElementById('modalCerrarCaja').classList.add('hidden'); document.getElementById('modalCerrarCaja').classList.remove('flex')"
                    class="flex-1 border border-slate-200 text-slate-700 font-semibold py-2.5 px-4 rounded-xl text-sm hover:bg-slate-50 transition-colors"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-4 rounded-xl text-sm flex items-center justify-center gap-2 transition-colors"
                >
                    <i class="fa-solid fa-lock"></i> Confirmar Cierre
                </button>
            </div>
        </form>
    </div>
</div>

<?php endif; /* end caja state */ ?>

<!-- Historial diario de cierres -->
<section class="mt-8 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h2 class="text-base font-bold text-slate-800"><i class="fa-solid fa-calendar-days text-sky-500 mr-2"></i>Historial diario de caja</h2>
            <p class="text-xs text-slate-400 mt-1">Consulta cuánto quedó registrado en cada fecha</p>
        </div>
        <span class="text-xs text-slate-400"><?= count($historial) ?> día(s)</span>
    </div>
    <?php if (empty($historial)): ?>
        <div class="py-10 text-center text-slate-400 text-sm">Aún no hay cierres registrados.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left text-xs font-semibold text-slate-500 px-6 py-3">Fecha</th>
                        <th class="text-left text-xs font-semibold text-slate-500 px-4 py-3">Apertura / cierre</th>
                        <th class="text-right text-xs font-semibold text-slate-500 px-4 py-3">Ventas del día</th>
                        <th class="text-right text-xs font-semibold text-slate-500 px-6 py-3">Saldo detectado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($historial as $dia): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-semibold text-slate-800 whitespace-nowrap">
                                <i class="fa-regular fa-calendar text-sky-500 mr-2"></i><?= htmlspecialchars(date('d/m/Y', strtotime($dia['fecha_cierre'] ?? $dia['fecha_apertura']))) ?>
                            </td>
                            <td class="px-4 py-4 text-slate-500 whitespace-nowrap">
                                <?= htmlspecialchars($dia['fecha_apertura'] ?? '—') ?> / <?= htmlspecialchars($dia['fecha_cierre'] ?? 'Pendiente') ?>
                            </td>
                            <td class="px-4 py-4 text-right text-slate-700">
                                <?= formatMoney((float)($dia['total_ventas'] ?? 0), 'S/') ?>
                                <span class="block text-xs text-slate-400"><?= (int)($dia['cantidad_ventas'] ?? 0) ?> venta(s)</span>
                            </td>
                            <td class="px-6 py-4 text-right font-bold text-emerald-700 whitespace-nowrap">
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
