<?php
// Blindaje de variables para evitar errores de undefined en el formulario
$modo      = $modo      ?? 'crear';
$proveedor = $proveedor ?? [];
?>
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <!-- Page header -->
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
        <div class="mb-4 sm:mb-0 flex items-center gap-3">
            <a href="<?= baseUrl('proveedores') ?>" class="text-slate-400 hover:text-sky-500 transition-colors">
                <i class="fa-solid fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold"><?= htmlspecialchars($title ?? 'Formulario de Proveedor') ?></h1>
        </div>
    </div>

    <!-- Main form -->
    <div class="bg-white dark:bg-slate-800 shadow-lg rounded-xl border border-slate-200 dark:border-slate-700 p-6 md:p-8">
        <form action="<?= baseUrl($modo === 'crear' ? 'proveedores/guardar' : "proveedores/{$proveedor['id']}/actualizar") ?>" method="POST" class="space-y-6" onsubmit="return validarRuc()">
            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
            <?php if ($modo === 'editar'): ?>
                <input type="hidden" name="id" value="<?= htmlspecialchars((string)($proveedor['id'] ?? '')) ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- RUC -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2" for="ruc">RUC *</label>
                    <div class="relative flex gap-2">
                        <div class="relative flex-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-id-card text-slate-400"></i>
                            </div>
                            <input type="text" id="ruc" name="ruc" value="<?= htmlspecialchars((string)($proveedor['ruc'] ?? $proveedor['numero_doc'] ?? '')) ?>" required minlength="11" maxlength="11" pattern="[0-9]{11}" inputmode="numeric" autocomplete="off" class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors">
                        </div>
                        <button type="button" onclick="buscarRuc()" class="px-4 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 rounded-lg text-sm transition-colors flex items-center gap-2">
                            <i class="fa-solid fa-magnifying-glass"></i> <span class="hidden sm:inline">SUNAT</span>
                        </button>
                    </div>
                </div>

                <!-- Razón Social -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Razón Social *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-building text-slate-400"></i>
                        </div>
                        <input type="text" id="razon_social" name="razon_social" value="<?= htmlspecialchars((string)($proveedor['razon_social'] ?? '')) ?>" required class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors">
                    </div>
                </div>

                <!-- Dirección -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Dirección</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-location-dot text-slate-400"></i>
                        </div>
                        <input type="text" id="direccion" name="direccion" value="<?= htmlspecialchars((string)($proveedor['direccion'] ?? '')) ?>" class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors">
                    </div>
                </div>

                <!-- Teléfono -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Teléfono</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-phone text-slate-400"></i>
                        </div>
                        <input type="text" name="telefono" value="<?= htmlspecialchars((string)($proveedor['telefono'] ?? '')) ?>" class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors">
                    </div>
                </div>

                <!-- Correo Electrónico -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Correo Electrónico</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-envelope text-slate-400"></i>
                        </div>
                        <input type="email" name="email" value="<?= htmlspecialchars((string)($proveedor['email'] ?? '')) ?>" class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors">
                    </div>
                </div>

                <!-- Contacto -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Nombre de Contacto (Opcional)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-regular fa-id-badge text-slate-400"></i>
                        </div>
                        <input type="text" name="contacto" value="<?= htmlspecialchars((string)($proveedor['contacto'] ?? '')) ?>" class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors" placeholder="Ej. Juan Pérez">
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t border-slate-200 dark:border-slate-700">
                <a href="<?= baseUrl('proveedores') ?>" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-medium transition-colors">
                    Cancelar
                </a>
                <button type="submit" class="px-5 py-2.5 bg-sky-500 hover:bg-sky-600 text-white rounded-xl text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $modo === 'crear' ? 'Guardar Proveedor' : 'Actualizar Proveedor' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function buscarRuc() {
    const ruc = document.getElementById('ruc').value.trim();
    if (!/^\d{11}$/.test(ruc)) {
        showToast('El RUC debe tener exactamente 11 dígitos', 'warning');
        return;
    }

    fetch('<?= baseUrl('api/proveedores/buscar-ruc') ?>?ruc=' + encodeURIComponent(ruc), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                document.getElementById('razon_social').value = data.data.razon_social || data.data.nombre_comercial || '';
                if (data.data.direccion) document.getElementById('direccion').value = data.data.direccion;
                showToast('Datos encontrados', 'success');
            } else {
                showToast('El RUC no está registrado en tus proveedores', 'info');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error de conexión', 'error');
        });
}

function validarRuc() {
    const ruc = document.getElementById('ruc').value.trim();
    if (!/^\d{11}$/.test(ruc)) {
        showToast('El RUC debe tener exactamente 11 dígitos', 'warning');
        return false;
    }
    return true;
}
</script>
