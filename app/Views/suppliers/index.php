<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
        <div class="mb-4 sm:mb-0">
            <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold">Proveedores 🏢</h1>
        </div>
        <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
            <a href="<?= baseUrl('proveedores/crear') ?>" class="inline-flex items-center gap-2 bg-sky-500 hover:bg-sky-600 text-white font-medium rounded-xl px-5 py-2.5 transition-colors shadow-sm">
                <i class="fa-solid fa-plus"></i> Nuevo Proveedor
            </a>
        </div>
    </div>
<!-- FIN BLOQUE DE DEPURACIÓN -->
    <div class="bg-white dark:bg-slate-800 shadow-lg rounded-sm border border-slate-200 dark:border-slate-700">
        <div class="p-3">
             <table class="table-auto w-full dark:text-slate-300">
                <thead class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/20 border-t border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-2 first:pl-5 last:pr-5 py-3"><div class="font-semibold text-left">Documento</div></th>
                        <th class="px-2 first:pl-5 last:pr-5 py-3"><div class="font-semibold text-left">Razón Social/Nombre</div></th>
                        <th class="px-2 first:pl-5 last:pr-5 py-3"><div class="font-semibold text-left">Teléfono</div></th>
                        <th class="px-2 first:pl-5 last:pr-5 py-3"><div class="font-semibold text-left">Email</div></th>
                        <th class="px-2 first:pl-5 last:pr-5 py-3"><div class="font-semibold text-center">Acciones</div></th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-slate-200 dark:divide-slate-700">
                    <?php if (!empty($resultado['data'])): ?>
                        <?php foreach ($resultado['data'] as $prov): ?>
                        <tr>
                            <td class="px-2 first:pl-5 last:pr-5 py-3"><?= htmlspecialchars($prov['tipo_doc'] ?? 'RUC') ?>: <?= htmlspecialchars($prov['numero_doc'] ?? '') ?></td>
                            <td class="px-2 first:pl-5 last:pr-5 py-3"><?= htmlspecialchars($prov['razon_social'] ?? $prov['nombre_comercial'] ?? '') ?></td>
                            <td class="px-2 first:pl-5 last:pr-5 py-3"><?= htmlspecialchars($prov['telefono'] ?? '-') ?></td>
                            <td class="px-2 first:pl-5 last:pr-5 py-3"><?= htmlspecialchars($prov['email'] ?? '-') ?></td>
                            <td class="px-2 first:pl-5 last:pr-5 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="<?= baseUrl('proveedores/' . $prov['id'] . '/editar') ?>" class="text-sky-500 hover:text-sky-700 bg-sky-50 hover:bg-sky-100 p-2 rounded-lg transition-colors" title="Editar">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <button type="button" onclick="confirmDelete(<?= $prov['id'] ?>, '<?= baseUrl('proveedores/' . $prov['id'] . '/eliminar') ?>', '¿Eliminar al proveedor <?= htmlspecialchars($prov['razon_social'] ?? $prov['nombre_comercial']) ?>?')" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors" title="Eliminar">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-2 first:pl-5 last:pr-5 py-3 text-center">No se encontraron proveedores.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
