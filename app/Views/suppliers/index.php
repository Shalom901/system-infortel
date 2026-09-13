<div class="w-full space-y-6">
    <!-- Encabezado alineado -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                🏢 Lista de proveedores
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Directorio y catálogo de proveedores registrados</p>
        </div>
        <div>
            <a href="<?= baseUrl('proveedores/crear') ?>" class="inline-flex items-center gap-2 bg-sky-500 hover:bg-sky-600 text-white font-semibold rounded-xl px-5 py-2.5 transition-all shadow-sm hover:-translate-y-0.5">
                <i class="fa-solid fa-plus"></i> Nuevo Proveedor
            </a>
        </div>
    </div>

    <!-- Tabla con diseño moderno redondeado y sin desfaces -->
    <div class="bg-white dark:bg-slate-800/80 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-700/60 overflow-hidden transition-colors">
        <div class="overflow-x-auto">
            <table class="table-auto w-full text-sm text-left">
                <thead class="text-xs font-bold uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/60 border-b border-slate-200 dark:border-slate-700 tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5">Documento</th>
                        <th class="px-4 py-3.5">Razón Social / Nombre</th>
                        <th class="px-4 py-3.5">Teléfono</th>
                        <th class="px-4 py-3.5">Email</th>
                        <th class="px-4 py-3.5 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-slate-700 dark:text-slate-300">
                    <?php if (!empty($resultado['data'])): ?>
                        <?php foreach ($resultado['data'] as $prov): ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-700/40 transition-colors">
                            <td class="px-4 py-3.5 font-mono text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-bold text-slate-700 dark:text-slate-300"><?= htmlspecialchars($prov['tipo_doc'] ?? 'RUC') ?>:</span> 
                                <?= htmlspecialchars($prov['numero_doc'] ?? '') ?>
                            </td>
                            <td class="px-4 py-3.5 font-bold text-slate-800 dark:text-slate-100">
                                <?= htmlspecialchars($prov['razon_social'] ?? $prov['nombre_comercial'] ?? '') ?>
                            </td>
                            <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                                <?= htmlspecialchars($prov['telefono'] ?? '-') ?>
                            </td>
                            <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">
                                <?= htmlspecialchars($prov['email'] ?? '-') ?>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <!-- Botón Editar con texto e icono (igual a Categorías) -->
                                    <a href="<?= baseUrl('proveedores/' . $prov['id'] . '/editar') ?>" 
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10 hover:bg-sky-500 hover:text-white dark:hover:bg-sky-500 dark:hover:text-white border border-sky-100 dark:border-sky-500/20 transition-all shadow-sm">
                                        <i class="fa-solid fa-pen-to-square"></i> Editar
                                    </a>

                                    <!-- Botón Eliminar con texto e icono (igual a Categorías) -->
                                    <button type="button" 
                                            onclick="confirmDelete(<?= $prov['id'] ?>, '<?= baseUrl('proveedores/' . $prov['id'] . '/eliminar') ?>', '¿Eliminar al proveedor <?= htmlspecialchars($prov['razon_social'] ?? $prov['nombre_comercial']) ?>?')" 
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-500 hover:text-white dark:hover:bg-rose-500 dark:hover:text-white border border-rose-100 dark:border-rose-500/20 transition-all shadow-sm">
                                        <i class="fa-solid fa-trash-can"></i> Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500">
                                No se encontraron proveedores registrados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>