<?php
$title = 'Configuración SUNAT';
$puedeAdministrarSunat = isAdmin();
$puedeEnviarSunat = isAdmin() || isVendedor();
?>
<div class="space-y-5">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">Configuración SUNAT</h2>
            <p class="text-sm text-gray-500 dark:text-slate-400">Administra credenciales, envíos y monitoreo de facturación electrónica</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- 1. Botón disponible para TODOS (Vendedor y Administrador) -->
            <a href="<?= baseUrl('sunat/listado') ?>" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-2 shadow-sm hover:-translate-y-0.5">
                <i class="fa-solid fa-list-check"></i> Gestión y Filtros
            </a>

            <!-- 2. Opciones técnicas exclusivas del Administrador -->
            <?php if ($puedeAdministrarSunat): ?>
            <button type="button" onclick="testSunatConnection()" 
                    class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-2 shadow-sm hover:-translate-y-0.5">
                <i class="fa-solid fa-plug"></i> Probar Conexión
            </button>
            <button type="button" onclick="certInfo()" 
                    class="bg-violet-500 hover:bg-violet-600 text-white px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition-all flex items-center gap-2 shadow-sm hover:-translate-y-0.5">
                <i class="fa-solid fa-certificate"></i> Certificado
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600">
                    <i class="fa-solid fa-file-invoice"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Total CPE</p>
                    <p class="text-xl font-bold text-gray-800 dark:text-white"><?= number_format((int)($stats['total'] ?? 0)) ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-600">
                    <i class="fa-solid fa-check-circle"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Aceptados</p>
                    <p class="text-xl font-bold text-green-600"><?= number_format((int)($stats['aceptados'] ?? 0)) ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-100 dark:bg-red-900/30 flex items-center justify-center text-red-600">
                    <i class="fa-solid fa-times-circle"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Rechazados</p>
                    <p class="text-xl font-bold text-red-600"><?= number_format((int)($stats['rechazados'] ?? 0)) ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center text-orange-600">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Pendientes</p>
                    <p class="text-xl font-bold text-orange-600"><?= number_format((int)($stats['pendientes'] ?? 0)) ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($puedeAdministrarSunat): ?>
    <!-- Credenciales SOL -->
    <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-slate-700">
        <h3 class="font-semibold text-gray-700 dark:text-gray-200 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-building-columns text-sky-500"></i>
            Credenciales SOL
        </h3>
        <form method="POST" action="<?= baseUrl('sunat/actualizar') ?>" onsubmit="return submitFormAjax(event, '<?= baseUrl('sunat/actualizar') ?>')">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">RUC de la Empresa</label>
                    <input type="text" name="SUNAT_RUC" value="<?= htmlspecialchars($_ENV['SUNAT_RUC'] ?? '') ?>" maxlength="11" placeholder="20XXXXXXXXX" class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-sm focus:outline-none focus:border-sky-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Modo de Operación</label>
                    <select name="SUNAT_MODO" class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-sm focus:outline-none focus:border-sky-400">
                        <option value="beta"       <?= ($_ENV['SUNAT_MODO'] ?? 'beta') === 'beta'       ? 'selected' : '' ?>>🧪 Beta (Pruebas)</option>
                        <option value="produccion" <?= ($_ENV['SUNAT_MODO'] ?? 'beta') === 'produccion' ? 'selected' : '' ?>>🚀 Producción</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Usuario SOL</label>
                    <input type="text" name="SUNAT_USUARIO_SOL" value="<?= htmlspecialchars($_ENV['SUNAT_USUARIO_SOL'] ?? '') ?>" placeholder="MODDATOS" class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-sm focus:outline-none focus:border-sky-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Clave SOL</label>
                    <input type="password" name="SUNAT_CLAVE_SOL" value="<?= htmlspecialchars($_ENV['SUNAT_CLAVE_SOL'] ?? '') ?>" placeholder="••••••••" class="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg bg-gray-50 dark:bg-slate-700 text-sm focus:outline-none focus:border-sky-400">
                </div>
            </div>

            <!-- Indicador de Modo -->
            <div class="mt-4 p-3 rounded-lg <?= ($_ENV['SUNAT_MODO'] ?? 'beta') === 'produccion' ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' : 'bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800' ?>">
                <p class="text-sm <?= ($_ENV['SUNAT_MODO'] ?? 'beta') === 'produccion' ? 'text-red-700 dark:text-red-400' : 'text-blue-700 dark:text-blue-400' ?>">
                    <?php if (($_ENV['SUNAT_MODO'] ?? 'beta') === 'produccion'): ?>
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                        <strong>¡Modo Producción activo!</strong> Los comprobantes emitidos tendrán validez fiscal ante SUNAT.
                    <?php else: ?>
                        <i class="fa-solid fa-flask mr-1"></i>
                        <strong>Modo Beta/Pruebas activo.</strong> Los comprobantes NO tienen validez fiscal. Úsalo para desarrollo y pruebas.
                    <?php endif; ?>
                </p>
            </div>

            <div class="flex justify-end mt-4">
                <button type="submit" id="btnGuardar" class="bg-sky-500 hover:bg-sky-600 text-white px-6 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
                    <span id="btnText">Guardar Configuración</span>
                    <span id="btnSpinner" class="hidden"><i class="fa-solid fa-spinner fa-spin"></i></span>
                </button>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 text-sm text-blue-700 dark:text-blue-300">
        <i class="fa-solid fa-eye mr-2"></i>
        Tienes acceso de consulta. La configuración y el envío de comprobantes están reservados al administrador.
    </div>
    <?php endif; ?>

    <!-- Series de Comprobantes -->
    <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-slate-700">
        <h3 class="font-semibold text-gray-700 dark:text-gray-200 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-list-ol text-violet-500"></i>
            Series de Comprobantes
        </h3>
        <?php if (empty($series)): ?>
            <p class="text-sm text-gray-400 py-4 text-center">No hay series configuradas. Ejecuta el seeder de base de datos.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-semibold">Tipo</th>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-semibold">Serie</th>
                        <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-300 font-semibold">Correlativo Actual</th>
                        <th class="px-4 py-2 text-center text-gray-600 dark:text-gray-300 font-semibold">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    <?php foreach ($series as $serie): ?>
                    <tr>
                        <td class="px-4 py-2">
                            <?php
                            $tipos = ['01' => 'Factura', '03' => 'Boleta', 'NV' => 'Nota Venta', '07' => 'Nota Crédito', '08' => 'Nota Débito'];
                            echo htmlspecialchars($tipos[$serie['tipo_comprobante'] ?? ''] ?? $serie['tipo_comprobante']);
                            ?>
                        </td>
                        <td class="px-4 py-2 font-mono font-bold text-sky-600"><?= htmlspecialchars($serie['serie']) ?></td>
                        <td class="px-4 py-2 text-right"><?= number_format((int)($serie['correlativo_actual'] ?? $serie['correlativo'] ?? $serie['siguiente_correlativo'] ?? $serie['ultimo_correlativo'] ?? 0)) ?></td>
                        <td class="px-4 py-2 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Activa</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Comprobantes Pendientes - SECCIÓN MEJORADA -->
    <div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm border border-gray-100 dark:border-slate-700">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                <i class="fa-solid fa-clock text-orange-500"></i>
                Comprobantes Pendientes de Enviar a SUNAT
            </h3>
            <?php if ($puedeEnviarSunat && !empty($pendientes)): ?>
            <button onclick="enviarPendientes()" id="btnEnviarPendientes" class="bg-sky-500 hover:bg-sky-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Enviar Todos</span>
                <span id="spinnerPendientes" class="hidden"><i class="fa-solid fa-spinner fa-spin"></i></span>
            </button>
            <?php endif; ?>
        </div>

        <?php if (empty($pendientes)): ?>
            <div class="text-center py-6">
                <i class="fa-solid fa-check-circle text-3xl text-emerald-500 mb-2"></i>
                <p class="text-sm text-gray-400">¡Todos los comprobantes están sincronizados con SUNAT!</p>
            </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-slate-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-semibold">N° Comprobante</th>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-semibold">Tipo</th>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300 font-semibold">Fecha</th>
                        <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-300 font-semibold">Total</th>
                        <th class="px-4 py-2 text-center text-gray-600 dark:text-gray-300 font-semibold">Estado SUNAT</th>
                        <th class="px-4 py-2 text-center text-gray-600 dark:text-gray-300 font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    <?php foreach ($pendientes as $p): ?>
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/50" id="row-<?= $p['id'] ?>">
                        <td class="px-4 py-2 font-medium text-sky-600"><?= htmlspecialchars($p['numero_comprobante']) ?></td>
                        <td class="px-4 py-2"><?= $p['tipo_comprobante'] === '01' ? 'Factura' : 'Boleta' ?></td>
                        <td class="px-4 py-2 text-gray-500"><?= date('d/m/Y', strtotime($p['fecha_emision'])) ?></td>
                        <td class="px-4 py-2 text-right">S/ <?= number_format((float)$p['total'], 2) ?></td>
                        <td class="px-4 py-2 text-center">
                            <?php
                            $badgeClass = match($p['estado_sunat'] ?? 'pendiente') {
                                'aceptado' => 'bg-green-100 text-green-700',
                                'rechazado' => 'bg-red-100 text-red-700',
                                'enviado' => 'bg-blue-100 text-blue-700',
                                default => 'bg-yellow-100 text-yellow-700',
                            };
                            ?>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $badgeClass ?>">
                                <?= htmlspecialchars(ucfirst($p['estado_sunat'] ?? 'Pendiente')) ?>
                            </span>
                            <?php if (!empty($p['intentos_envio'])): ?>
                            <span class="text-xs text-gray-400 ml-1">(<?= (int)$p['intentos_envio'] ?>x)</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <?php if (!$puedeEnviarSunat): ?>
                            <span class="text-xs text-gray-500">Solo consulta</span>
                            <?php elseif (($p['estado_sunat'] ?? 'pendiente') !== 'aceptado'): ?>
                            <button onclick="enviarComprobante(<?= $p['id'] ?>)" class="text-sky-600 hover:text-sky-800 text-xs font-medium transition px-2 py-1 rounded hover:bg-sky-50 dark:hover:bg-sky-900/20">
                                <i class="fa-solid fa-paper-plane"></i> Enviar
                            </button>
                            <?php if (($p['estado_sunat'] ?? '') === 'rechazado'): ?>
                            <button onclick="reintentarEnvio(<?= $p['id'] ?>)" class="text-amber-600 hover:text-amber-800 text-xs font-medium transition px-2 py-1 rounded hover:bg-amber-50 dark:hover:bg-amber-900/20">
                                <i class="fa-solid fa-rotate"></i> Reintentar
                            </button>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-xs text-green-600"><i class="fa-solid fa-check"></i> Aceptado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Result Modal -->
<div id="resultModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 max-w-lg w-full mx-4 shadow-2xl">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-circle-info text-sky-500"></i>
                Resultado de Operación
            </h4>
            <button onclick="closeResultModal()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-times"></i></button>
        </div>
        <div id="resultContent" class="text-sm text-gray-600 dark:text-gray-300 space-y-2 max-h-96 overflow-y-auto"></div>
        <div class="flex justify-end mt-4">
            <button onclick="closeResultModal()" class="px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-200 dark:hover:bg-slate-600 transition">Cerrar</button>
        </div>
    </div>
</div>

<script>
// ========================================================
// NOTIFICACIÓN FLASH / TOAST EN LA ESQUINA SUPERIOR DERECHA
// ========================================================
function toastSUNAT(title, text = '', icon = 'success') {
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end', // 👈 Esquina superior derecha
            showConfirmButton: false, // 👈 CERO botones de "Aceptar" ni "Cerrar"
            timer: 3500, // 👈 Desaparece sola en 3.5 segundos
            timerProgressBar: true
        });
        Toast.fire({ icon, title, text });
    } else {
        // Fallback flotante si no estuviera cargado Swal
        const notif = document.createElement('div');
        notif.className = `fixed top-5 right-5 z-[9999] px-4 py-3 rounded-2xl shadow-2xl text-white text-xs font-bold transition-all duration-300 flex items-center gap-2.5 ${icon === 'success' ? 'bg-emerald-600' : 'bg-rose-600'}`;
        notif.innerHTML = `
            <i class="fa-solid ${icon === 'success' ? 'fa-circle-check' : 'fa-circle-xmark'} text-base"></i>
            <div>
                <div>${title}</div>
                <div class="font-normal text-[11px] opacity-90">${text}</div>
            </div>
        `;
        document.body.appendChild(notif);
        setTimeout(() => {
            notif.style.opacity = '0';
            notif.style.transform = 'translateY(-10px)';
            setTimeout(() => notif.remove(), 300);
        }, 3500);
    }
}

function submitFormAjax(event, url) {
    event.preventDefault();
    const form = event.target;
    const btn = form.querySelector('button[type="submit"]');
    const spinner = document.getElementById('btnSpinner');
    const btnText = document.getElementById('btnText');

    btn.disabled = true;
    if (spinner) spinner.classList.remove('hidden');
    if (btnText) btnText.textContent = 'Guardando...';

    fetch(url, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(res => {
        if (res.redirected) {
            window.location.href = res.url;
            return;
        }
        window.location.reload();
    }).catch(() => {
        window.location.reload();
    });
}

function showResult(html) {
    document.getElementById('resultContent').innerHTML = html;
    document.getElementById('resultModal').classList.remove('hidden');
}

function closeResultModal() {
    document.getElementById('resultModal').classList.add('hidden');
}

// 1. Enviar comprobante individual con Toast (SIN VENTANAS MOLESTAS)
function enviarComprobante(ventaId) {
    const btn = event.target.closest('button');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>';
    }

    fetch('<?= baseUrl('sunat/enviar') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'venta_id=' + ventaId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Notificación elegante en la esquina superior derecha
            toastSUNAT('¡Envío exitoso!', data.message || 'Comprobante recibido y aceptado por SUNAT', 'success');

            // Actualizar la fila en vivo
            const row = document.getElementById('row-' + ventaId);
            if (row) {
                const badge = row.querySelector('span.rounded-full');
                if (badge) {
                    badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400';
                    badge.textContent = 'Aceptado';
                }
                const accionBtn = row.querySelector('td:last-child button');
                if (accionBtn) {
                    accionBtn.outerHTML = '<span class="text-xs text-emerald-600 dark:text-emerald-400 font-bold"><i class="fa-solid fa-check mr-1"></i> Aceptado</span>';
                }
            }
            setTimeout(() => window.location.reload(), 2000);
        } else {
            toastSUNAT('Error en envío', data.message || 'SUNAT no pudo procesar el comprobante', 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar';
            }
        }
    })
    .catch(err => {
        toastSUNAT('Error de conexión', err.message || 'No se pudo conectar con el servidor', 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar';
        }
    });
}

// 2. Enviar todos los comprobantes pendientes con Toast (SIN VENTANAS MOLESTAS)
function enviarPendientes() {
    const btn = document.getElementById('btnEnviarPendientes');
    const spinner = document.getElementById('spinnerPendientes');
    if (btn) btn.disabled = true;
    if (spinner) spinner.classList.remove('hidden');
    const textSpan = btn ? btn.querySelector('span:not(#spinnerPendientes)') : null;
    if (textSpan) textSpan.textContent = 'Enviando...';

    fetch('<?= baseUrl('sunat/enviar-pendientes') ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            toastSUNAT('¡Envío masivo completado!', `${data.message} (Exitosos: ${data.exitosos ?? 0})`, 'success');
        } else {
            toastSUNAT('Atención en envío masivo', data.message || 'Hubo observaciones en algunos comprobantes', 'warning');
        }

        setTimeout(() => window.location.reload(), 2000);
    })
    .catch(err => {
        toastSUNAT('Error', err.message || 'Error de conexión', 'error');
        if (btn) btn.disabled = false;
        if (spinner) spinner.classList.add('hidden');
        if (textSpan) textSpan.textContent = 'Enviar Todos';
    });
}

// 3. Reintentar envío con Toast (SIN VENTANAS MOLESTAS)
function reintentarEnvio(ventaId) {
    const btn = event.target.closest('button');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>';
    }

    fetch('<?= baseUrl('sunat/reintentar') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'venta_id=' + ventaId
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            toastSUNAT('¡Reintento exitoso!', data.message, 'success');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            toastSUNAT('Error en reintento', data.message, 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-rotate"></i> Reintentar';
            }
        }
    })
    .catch(err => {
        toastSUNAT('Error de conexión', err.message, 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rotate"></i> Reintentar';
        }
    });
}

// 4. Probar conexión y Certificado (Conserva su ventana modal porque muestran tablas de datos técnicos)
function testSunatConnection() {
    fetch('<?= baseUrl('sunat/test-connection') ?>', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        let html = '';
        if (data.success) {
            html = `<div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                        <p class="text-green-700 dark:text-green-400 font-medium"><i class="fa-solid fa-plug"></i> Conexión exitosa</p>
                        <p class="text-green-600 dark:text-green-300 mt-1">${data.message}</p>
                        ${data.endpoint ? `<p class="text-xs text-gray-500 mt-1">Endpoint: ${data.endpoint}</p>` : ''}
                    </div>`;
        } else {
            html = `<div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                        <p class="text-red-700 dark:text-red-400 font-medium"><i class="fa-solid fa-times-circle"></i> Error de conexión</p>
                        <p class="text-red-600 dark:text-red-300 mt-1">${data.message}</p>
                    </div>`;
        }
        showResult(html);
    })
    .catch(err => {
        showResult(`<div class="p-3 bg-red-50 rounded-lg"><p class="text-red-700">Error: ${err.message}</p></div>`);
    });
}

function certInfo() {
    fetch('<?= baseUrl('sunat/cert-info') ?>', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        let html = '';
        if (data.success && data.data) {
            const d = data.data;
            html = `<div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                        <table class="w-full text-sm">
                            <tr><td class="py-1 font-medium text-gray-600 dark:text-gray-400 w-40">Propietario:</td><td class="py-1 text-gray-800 dark:text-white">${d.cn || '-'}</td></tr>
                            <tr><td class="py-1 font-medium text-gray-600 dark:text-gray-400">Organización:</td><td class="py-1 text-gray-800 dark:text-white">${d.organizacion || '-'}</td></tr>
                            <tr><td class="py-1 font-medium text-gray-600 dark:text-gray-400">Emisor:</td><td class="py-1 text-gray-800 dark:text-white">${d.emisor_cn || '-'}</td></tr>
                            <tr><td class="py-1 font-medium text-gray-600 dark:text-gray-400">Válido desde:</td><td class="py-1 text-gray-800 dark:text-white">${d.valido_desde || '-'}</td></tr>
                            <tr><td class="py-1 font-medium text-gray-600 dark:text-gray-400">Válido hasta:</td><td class="py-1 text-gray-800 dark:text-white">${d.valido_hasta || '-'}</td></tr>
                            <tr><td class="py-1 font-medium text-gray-600 dark:text-gray-400">Vigente:</td><td class="py-1">${d.vigente ? '<span class="text-green-600 font-medium">✓ Sí</span>' : '<span class="text-red-600 font-medium">✗ No</span>'}</td></tr>
                            ${d.dias_restantes !== undefined ? `<tr><td class="py-1 font-medium text-gray-600 dark:text-gray-400">Días restantes:</td><td class="py-1 text-gray-800 dark:text-white">${d.dias_restantes} días</td></tr>` : ''}
                        </table>
                    </div>`;
        } else {
            html = `<div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800">
                        <p class="text-yellow-700 dark:text-yellow-400 font-medium"><i class="fa-solid fa-exclamation-triangle"></i> No se pudo leer el certificado</p>
                        <p class="text-yellow-600 dark:text-yellow-300 mt-1">${data.message || 'Verifique la ruta y contraseña del certificado digital.'}</p>
                    </div>`;
        }
        showResult(html);
    })
    .catch(err => {
        showResult(`<div class="p-3 bg-red-50 rounded-lg"><p class="text-red-700">Error: ${err.message}</p></div>`);
    });
}
</script>
