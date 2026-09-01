<?php
/**
 * Categories Index View — injected into main layout
 */

$categorias    = $categorias    ?? [];
$editCategoria = $editCategoria ?? null;

// Build a lookup for parent names
$categoriasMap = [];
foreach ($categorias as $cat) {
    $categoriasMap[(int)$cat['id']] = $cat['nombre'];
}
?>

<!-- =====================================================================
     PAGE HEADER
     ===================================================================== -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 transition-colors">
            <i class="fa-solid fa-tags text-violet-500 mr-2"></i>Categorías
        </h1>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-0.5 transition-colors"><?= count($categorias) ?> categoría(s) registrada(s)</p>
    </div>
    <button
        type="button"
        onclick="openModal()"
        class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl shadow transition-all duration-200 hover:shadow-violet-500/30 hover:-translate-y-0.5 active:translate-y-0"
    >
        <i class="fa-solid fa-plus"></i> Nueva Categoría
    </button>
</div>

<!-- =====================================================================
     CATEGORIES TABLE
     ===================================================================== -->
<div class="bg-white dark:bg-slate-900/50 rounded-2xl border border-slate-100 dark:border-slate-800 shadow-sm overflow-hidden transition-colors">

    <?php if (empty($categorias)): ?>
    <!-- Empty state -->
    <div class="py-20 text-center text-slate-400 dark:text-slate-500">
        <i class="fa-solid fa-folder-open text-5xl mb-4 block opacity-30"></i>
        <p class="text-base font-semibold text-slate-500 dark:text-slate-400 mb-1">Sin categorías</p>
        <p class="text-sm mb-5">Comienza creando tu primera categoría de productos</p>
        <button
            type="button"
            onclick="openModal()"
            class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-all"
        >
            <i class="fa-solid fa-plus"></i> Nueva Categoría
        </button>
    </div>

    <?php else: ?>
    <!-- Table header actions / search -->
    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row gap-3 sm:items-center transition-colors">
        <div class="relative flex-1 max-w-xs">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 text-sm"></i>
            <input
                type="text"
                id="searchCategoria"
                placeholder="Buscar categoría..."
                oninput="filterTable(this.value)"
                class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl pl-9 pr-4 py-2 text-sm text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent transition-all"
            >
        </div>
        <p class="text-xs text-slate-400 dark:text-slate-500 sm:ml-auto transition-colors">
            Mostrando <span id="rowCount"><?= count($categorias) ?></span> de <?= count($categorias) ?> categorías
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm" id="categoriasTable">
            <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-700 transition-colors">
                <tr>
                    <th class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide px-6 py-3.5">Nombre</th>
                    <th class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide px-4 py-3.5 hidden md:table-cell">Descripción</th>
                    <th class="text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide px-4 py-3.5 hidden lg:table-cell">Categoría Padre</th>
                    <th class="text-center text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide px-4 py-3.5">Productos</th>
                    <th class="text-center text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide px-6 py-3.5">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50" id="categoriasBody">
                <?php foreach ($categorias as $cat): ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group" data-search="<?= strtolower(htmlspecialchars($cat['nombre'])) ?>">

                    <!-- Nombre -->
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-violet-100 dark:bg-violet-500/10 flex items-center justify-center shrink-0 transition-colors">
                                <i class="fa-solid fa-tag text-violet-500 dark:text-violet-400 text-sm"></i>
                            </div>
                            <span class="font-semibold text-slate-800 dark:text-slate-100 transition-colors">
                                <?= htmlspecialchars($cat['nombre'] ?? '—') ?>
                            </span>
                        </div>
                    </td>

                    <!-- Descripción -->
                    <td class="px-4 py-4 text-slate-500 dark:text-slate-400 hidden md:table-cell max-w-[200px] transition-colors">
                        <span class="line-clamp-2">
                            <?= htmlspecialchars($cat['descripcion'] ?? '—') ?>
                        </span>
                    </td>

                    <!-- Categoría Padre -->
                    <td class="px-4 py-4 hidden lg:table-cell">
                        <?php if (!empty($cat['parent_id'])): ?>
                        <span class="inline-flex items-center gap-1.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400 text-xs font-medium px-2.5 py-1 rounded-full transition-colors">
                            <i class="fa-solid fa-level-up-alt text-xs"></i>
                            <?= htmlspecialchars($cat['parent_nombre'] ?? $categoriasMap[(int)$cat['parent_id']] ?? 'N/A') ?>
                        </span>
                        <?php else: ?>
                        <span class="text-slate-400 dark:text-slate-500 text-xs transition-colors">— Principal —</span>
                        <?php endif; ?>
                    </td>

                    <!-- Productos count -->
                    <td class="px-4 py-4 text-center">
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold transition-colors
                            <?= (int)($cat['total_productos'] ?? 0) > 0 ? 'bg-emerald-100 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400' ?>">
                            <?= (int)($cat['total_productos'] ?? 0) ?>
                        </span>
                    </td>

                    <!-- Acciones -->
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-center gap-2">
                            <!-- Editar -->
                            <button
                                type="button"
                                onclick="openModal(<?= htmlspecialchars(json_encode($cat)) ?>)"
                                class="inline-flex items-center gap-1.5 bg-sky-50 dark:bg-sky-500/10 hover:bg-sky-100 dark:hover:bg-sky-500/20 text-sky-700 dark:text-sky-400 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors"
                                title="Editar categoría"
                            >
                                <i class="fa-solid fa-pen-to-square text-xs"></i> Editar
                            </button>

                            <!-- Eliminar -->
                            <button
                                type="button"
                                onclick="openDeleteModal(<?= (int)$cat['id'] ?>, '<?= addslashes(htmlspecialchars($cat['nombre'])) ?>')"
                                class="inline-flex items-center gap-1.5 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 dark:hover:bg-red-500/20 text-red-600 dark:text-red-400 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors"
                                title="Eliminar categoría"
                            >
                                <i class="fa-solid fa-trash text-xs"></i> Eliminar
                            </button>
                        </div>
                    </td>

                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div><!-- /.table-card -->

<!-- Hidden delete form -->
<form id="formEliminar" action="" method="POST" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="_method" value="DELETE">
</form>

<!-- =====================================================================
     MODAL: CREATE / EDIT CATEGORY
     ===================================================================== -->
<div id="modalCategoria" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>

    <!-- Modal card -->
    <div class="relative z-10 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-transparent dark:border-slate-700 transition-colors">

        <!-- Header -->
        <div class="bg-gradient-to-r from-violet-600 to-purple-700 px-6 py-5 flex items-center justify-between">
            <h3 class="text-lg font-bold text-white flex items-center gap-2" id="modalTitle">
                <i class="fa-solid fa-tag"></i> Nueva Categoría
            </h3>
            <button type="button" onclick="closeModal()" class="text-violet-200 hover:text-white transition-colors p-1">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- Form -->
        <form id="formCategoria" action="/categories" method="POST" class="p-6" novalidate>
            <input type="hidden" name="csrf_token"  id="modalCsrf"   value="<?= csrf_token() ?>">
            <input type="hidden" name="_method"      id="modalMethod"  value="POST">
            <input type="hidden" name="id"           id="modalId"      value="">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">

                <!-- Nombre -->
                <div class="sm:col-span-2">
                    <label for="modalNombre" class="block text-slate-700 dark:text-slate-300 text-sm font-semibold mb-2 transition-colors">
                        Nombre <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="modalNombre"
                        name="nombre"
                        maxlength="100"
                        required
                        placeholder="Ej. Electrónicos"
                        class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent transition-all"
                    >
                </div>

                <!-- Código -->
                <div>
                    <label for="modalCodigo" class="block text-slate-700 dark:text-slate-300 text-sm font-semibold mb-2 transition-colors">
                        Código <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        id="modalCodigo"
                        name="codigo"
                        maxlength="20"
                        required
                        placeholder="Ej. ELEC"
                        class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-slate-800 dark:text-slate-100 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent transition-all"
                        oninput="this.value = this.value.toUpperCase()"
                    >
                </div>

                <!-- Categoría Padre -->
                <div>
                    <label for="modalParentId" class="block text-slate-700 dark:text-slate-300 text-sm font-semibold mb-2 transition-colors">
                        Categoría Padre
                    </label>
                    <select
                        id="modalParentId"
                        name="parent_id"
                        class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent transition-all"
                    >
                        <option value="">— Sin padre (principal) —</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>">
                            <?= htmlspecialchars($cat['nombre']) ?>
                            <?= !empty($cat['codigo']) ? ' (' . htmlspecialchars($cat['codigo']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Descripción -->
                <div class="sm:col-span-2">
                    <label for="modalDescripcion" class="block text-slate-700 dark:text-slate-300 text-sm font-semibold mb-2 transition-colors">
                        Descripción
                    </label>
                    <textarea
                        id="modalDescripcion"
                        name="descripcion"
                        rows="3"
                        maxlength="500"
                        placeholder="Descripción opcional de la categoría..."
                        class="w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-4 py-2.5 text-slate-800 dark:text-slate-100 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent transition-all"
                    ></textarea>
                </div>

            </div>

            <div class="flex gap-3 pt-4 border-t border-slate-100 dark:border-slate-800 transition-colors">
                <button
                    type="button"
                    onclick="closeModal()"
                    class="flex-1 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold py-2.5 px-4 rounded-xl text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    id="modalSubmitBtn"
                    class="flex-1 bg-violet-600 hover:bg-violet-700 text-white font-semibold py-2.5 px-4 rounded-xl text-sm flex items-center justify-center gap-2 transition-colors shadow-sm"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span id="modalSubmitText">Guardar Categoría</span>
                </button>
            </div>

        </form>
    </div>
</div>

<!-- =====================================================================
     MODAL: CONFIRM DELETE
     ===================================================================== -->
<div id="modalEliminar" class="hidden fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm transition-opacity" onclick="closeDeleteModal()"></div>
    <div class="relative z-10 bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden border border-transparent dark:border-slate-700 transition-colors">
        <div class="p-6 text-center">
            <div class="w-16 h-16 bg-red-100 dark:bg-red-500/10 rounded-full flex items-center justify-center mx-auto mb-4 transition-colors">
                <i class="fa-solid fa-triangle-exclamation text-red-500 dark:text-red-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-2 transition-colors">¿Eliminar Categoría?</h3>
            <p class="text-slate-500 dark:text-slate-400 text-sm mb-1 transition-colors">
                Estás a punto de eliminar la categoría:
            </p>
            <p class="text-slate-800 dark:text-slate-200 font-semibold mb-5 transition-colors" id="deleteNombre">—</p>
            <p class="text-xs text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 rounded-lg px-3 py-2 mb-6 transition-colors">
                <i class="fa-solid fa-circle-info mr-1"></i>
                Esta acción no se puede deshacer. Los productos asociados quedarán sin categoría.
            </p>
            <div class="flex gap-3">
                <button
                    type="button"
                    onclick="closeDeleteModal()"
                    class="flex-1 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold py-2.5 px-4 rounded-xl text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    id="confirmDeleteBtn"
                    onclick="submitDelete()"
                    class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-4 rounded-xl text-sm flex items-center justify-center gap-2 transition-colors shadow-sm"
                >
                    <i class="fa-solid fa-trash"></i> Eliminar
                </button>
            </div>
        </div>
    </div>
</div>


<!-- =====================================================================
     JAVASCRIPT
     ===================================================================== -->
<script>
window.currentDeleteId = null;

function openModal(cat) {
    const modal    = document.getElementById('modalCategoria');
    const form     = document.getElementById('formCategoria');
    const title    = document.getElementById('modalTitle');
    const method   = document.getElementById('modalMethod');
    const idInput  = document.getElementById('modalId');
    const submitTxt = document.getElementById('modalSubmitText');

    form.reset();
    idInput.value = '';

    if (cat) {
        title.innerHTML    = '<i class="fa-solid fa-pen-to-square"></i> Editar Categoría';
        method.value       = 'POST';
        idInput.value      = cat.id ?? '';
        submitTxt.textContent = 'Actualizar Categoría';
        form.action        = '<?= baseUrl('categorias/actualizar') ?>';

        document.getElementById('modalNombre').value      = cat.nombre      ?? '';
        document.getElementById('modalCodigo').value      = cat.codigo      ?? '';
        document.getElementById('modalDescripcion').value = cat.descripcion ?? '';

        const parentSelect = document.getElementById('modalParentId');
        parentSelect.value = cat.parent_id ?? '';

        for (let opt of parentSelect.options) {
            opt.disabled = (opt.value == cat.id);
        }
    } else {
        title.innerHTML    = '<i class="fa-solid fa-tag"></i> Nueva Categoría';
        method.value       = 'POST';
        submitTxt.textContent = 'Guardar Categoría';
        form.action        = '<?= baseUrl('categorias/guardar') ?>';

        const parentSelect = document.getElementById('modalParentId');
        for (let opt of parentSelect.options) {
            opt.disabled = false;
        }
    }

    modal.classList.remove('hidden');
    document.getElementById('modalNombre').focus();
}

function closeModal() {
    document.getElementById('modalCategoria').classList.add('hidden');
}

function openDeleteModal(id, nombre) {
    window.currentDeleteId = id;
    document.getElementById('deleteNombre').textContent = nombre;
    document.getElementById('modalEliminar').classList.remove('hidden');
}

function closeDeleteModal() {
    window.currentDeleteId = null;
    document.getElementById('modalEliminar').classList.add('hidden');
}

function submitDelete() {
    if (!window.currentDeleteId) return;

    const btn = document.getElementById('confirmDeleteBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Eliminando...';
    btn.disabled = true;

    const csrfToken = document.querySelector('#formEliminar input[name="csrf_token"]').value;

    const params = new URLSearchParams();
    params.append('id', window.currentDeleteId);
    params.append('csrf_token', csrfToken);
    params.append('_method', 'DELETE');

    fetch('<?= baseUrl('categorias/eliminar') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: params.toString()
    })
    .then(async response => {
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Falla estructural en el backend.');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            if (typeof showToast === 'function') {
                showToast(data.message, 'success');
            }
            setTimeout(() => window.location.reload(), 1000);
        }
    })
    .catch(error => {
        console.error('API Error:', error);
        if (typeof showToast === 'function') {
            showToast(error.message, 'error');
        }
        const btn = document.getElementById('confirmDeleteBtn');
        btn.innerHTML = '<i class="fa-solid fa-trash"></i> Eliminar';
        btn.disabled = false;
    });
}

function filterTable(query) {
    const rows = document.querySelectorAll('#categoriasBody tr');
    const q    = query.toLowerCase().trim();
    let visible = 0;

    rows.forEach(row => {
        const searchText = row.getAttribute('data-search') || '';
        const match = searchText.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    const counter = document.getElementById('rowCount');
    if (counter) counter.textContent = visible;
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeModal();
        closeDeleteModal();
    }
});
</script>