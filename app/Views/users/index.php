<?php
/**
 * Capa de Presentación: Listado de Usuarios (Index)
 * Inicialización estricta de variables para prevenir Fatal Errors y fugas de memoria.
 */
$usuarios  = $usuarios  ?? [];
$pageTitle = $pageTitle ?? 'Gestión de Usuarios';
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto transition-colors">
    <!-- Header del Módulo -->
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
        <div class="mb-4 sm:mb-0">
            <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold transition-colors">
                <i class="fa-solid fa-users-gear text-sky-500 mr-2"></i><?= htmlspecialchars($pageTitle) ?>
            </h1>
        </div>
        <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
            <!-- Desacoplamos Turbo de la navegación hacia formularios pesados para asegurar estado limpio -->
            <a href="<?= baseUrl('usuarios/crear') ?>" data-turbo="false" class="inline-flex items-center gap-2 bg-sky-500 hover:bg-sky-600 text-white font-medium rounded-xl px-5 py-2.5 transition-all shadow-sm hover:shadow-sky-500/30 hover:-translate-y-0.5">
                <i class="fa-solid fa-plus"></i> Nuevo Usuario
            </a>
        </div>
    </div>

    <!-- Contenedor Principal de la Tabla (Glassmorphism Integration) -->
    <div class="bg-white dark:bg-slate-900/50 shadow-lg rounded-2xl border border-slate-200 dark:border-slate-800 transition-colors overflow-hidden">
        <div class="p-4 overflow-x-auto">
             <table class="table-auto w-full dark:text-slate-300">
                <thead class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 transition-colors">
                    <tr>
                        <th class="px-4 py-4 rounded-tl-xl"><div class="font-semibold text-left">Nombre</div></th>
                        <th class="px-4 py-4"><div class="font-semibold text-left">Usuario</div></th>
                        <th class="px-4 py-4"><div class="font-semibold text-left">Rol</div></th>
                        <th class="px-4 py-4"><div class="font-semibold text-center">Estado</div></th>
                        <th class="px-4 py-4 rounded-tr-xl"><div class="font-semibold text-center">Acciones</div></th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-slate-100 dark:divide-slate-800/50">
                    <?php if (!empty($usuarios)): ?>
                        <?php foreach ($usuarios as $user): ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                            
                            <!-- Columna: Nombre y Avatar -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-sky-400 to-blue-600 flex items-center justify-center text-white font-bold text-xs shrink-0 shadow-sm">
                                        <?= strtoupper(substr($user['nombre'], 0, 1)) ?>
                                    </div>
                                    <span class="font-medium text-slate-800 dark:text-slate-100 transition-colors">
                                        <?= htmlspecialchars($user['nombre'] . ' ' . $user['apellidos']) ?>
                                    </span>
                                </div>
                            </td>
                            
                            <!-- Columna: Username -->
                            <td class="px-4 py-3 font-mono text-slate-500 dark:text-slate-400">
                                @<?= htmlspecialchars($user['username']) ?>
                            </td>
                            
                            <!-- Columna: Rol -->
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-500/20 transition-colors">
                                    <i class="fa-solid fa-shield-halved text-[10px]"></i>
                                    <?= htmlspecialchars($user['rol_nombre'] ?? 'Desconocido') ?>
                                </span>
                            </td>
                            
                            <!-- Columna: Estado -->
                            <td class="px-4 py-3 text-center">
                                <?php if ($user['activo']): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20 transition-colors">
                                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></div> Activo
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-500/20 transition-colors">
                                        <div class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5"></div> Inactivo
                                    </span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Columna: Acciones -->
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <!-- Editar (Bypasses Turbo to hydrate forms natively) -->
                                    <a href="<?= baseUrl('usuarios/' . $user['id'] . '/editar') ?>" data-turbo="false" class="text-sky-600 dark:text-sky-400 hover:text-white bg-sky-50 dark:bg-sky-500/10 hover:bg-sky-500 dark:hover:bg-sky-500 p-2 rounded-lg transition-colors" title="Editar">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <!-- Eliminar (Utiliza el Helper Global configurado en app.php) -->
                                    <button type="button" 
                                            onclick="confirmDelete(<?= $user['id'] ?>, '<?= baseUrl('usuarios/eliminar') ?>', '¿Desactivar/Eliminar al usuario <?= htmlspecialchars($user['nombre']) ?>?')" 
                                            class="text-rose-600 dark:text-rose-400 hover:text-white bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-500 dark:hover:bg-rose-500 p-2 rounded-lg transition-colors" 
                                            title="Eliminar">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                <i class="fa-solid fa-users-slash text-4xl mb-3 opacity-30 block"></i>
                                No se encontraron usuarios en el sistema.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>