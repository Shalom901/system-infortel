<?php
/**
 * Capa de Presentación: Formulario de Usuarios
 * Inicialización estricta para prevenir excepciones de variables indefinidas.
 */
$accion    = $accion    ?? 'crear';
$usuario   = $usuario   ?? [];
$roles     = $roles     ?? [];
$pageTitle = $pageTitle ?? ($accion === 'crear' ? 'Nuevo Usuario' : 'Editar Usuario');
?>

<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <!-- Page header -->
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
        <div class="mb-4 sm:mb-0 flex items-center gap-3">
            <a href="<?= baseUrl('usuarios') ?>" class="text-slate-400 hover:text-sky-500 transition-colors">
                <i class="fa-solid fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold"><?= htmlspecialchars($pageTitle ?? 'Formulario de Usuario') ?></h1>
        </div>
    </div>

    <!-- Form container -->
    <div class="bg-white dark:bg-slate-800 shadow-lg rounded-xl border border-slate-200 dark:border-slate-700 p-6 md:p-8">
        <form action="<?= baseUrl($accion === 'crear' ? 'usuarios/guardar' : 'usuarios/' . ($usuario['id'] ?? '') . '/actualizar') ?>" 
                method="POST" 
                id="formUsuario"
                data-turbo="false" 
                class="space-y-6">
                
                <!-- Token CSRF explícito para evitar rechazos HTTP 403 del backend -->
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <?php if ($accion === 'editar'): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)($usuario['id'] ?? '')) ?>">
                    <input type="hidden" name="_method" value="PUT">
                <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Nombre -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Nombres *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-user text-slate-400"></i>
                        </div>
                        <input type="text" name="nombre" value="<?= htmlspecialchars((string)($usuario['nombre'] ?? '')) ?>" required class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors">
                    </div>
                </div>

                <!-- Apellidos -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Apellidos *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-regular fa-user text-slate-400"></i>
                        </div>
                        <input type="text" name="apellidos" value="<?= htmlspecialchars((string)($usuario['apellidos'] ?? '')) ?>" required class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors">
                    </div>
                </div>

                <!-- Username -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Nombre de Usuario *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-at text-slate-400"></i>
                        </div>
                        <input type="text" name="username" value="<?= htmlspecialchars((string)($usuario['username'] ?? '')) ?>" required class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors">
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Correo Electrónico *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-envelope text-slate-400"></i>
                        </div>
                        <input type="email" name="email" value="<?= htmlspecialchars((string)($usuario['email'] ?? '')) ?>" required class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors">
                    </div>
                </div>

                <!-- Rol -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Rol del Sistema *</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-shield-halved text-slate-400"></i>
                        </div>
                        <select name="rol_id" required class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors appearance-none">
                            <option value="">Seleccione un rol...</option>
                            <?php foreach ($roles as $rol): ?>
                                <option value="<?= $rol['id'] ?>" <?= (($usuario['rol_id'] ?? '') == $rol['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($rol['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                    </div>
                </div>

                <!-- Estado (Solo si es edición) -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Estado</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-toggle-on text-slate-400"></i>
                        </div>
                        <select name="activo" class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors appearance-none">
                            <option value="1" <?= (($usuario['activo'] ?? 1) == 1) ? 'selected' : '' ?>>Activo</option>
                            <option value="0" <?= (($usuario['activo'] ?? 1) == 0) ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-chevron-down"></i>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                        Contraseña <?= $accion === 'crear' ? '*' : '(Dejar en blanco para mantener la actual)' ?>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-slate-400"></i>
                        </div>
                        <input type="password" name="password" <?= $accion === 'crear' ? 'required' : '' ?> minlength="8" class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors">
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Mínimo 8 caracteres.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Confirmar contraseña <?= $accion === 'crear' ? '*' : '(Solo si cambias la contraseña)' ?></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fa-solid fa-lock text-slate-400"></i>
                        </div>
                        <input type="password" name="password_confirm" <?= $accion === 'crear' ? 'required' : '' ?> minlength="8" autocomplete="new-password" class="pl-10 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 transition-colors">
                    </div>
                </div>
            </div>

            <!-- Botones -->
            <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t border-slate-200 dark:border-slate-700">
                <a href="<?= baseUrl('usuarios') ?>" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-medium transition-colors">
                    Cancelar
                </a>
                <button type="submit" class="px-5 py-2.5 bg-sky-500 hover:bg-sky-600 text-white rounded-xl text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <?= $accion === 'crear' ? 'Guardar Usuario' : 'Actualizar Usuario' ?>
                </button>
            </div>
        </form>
    </div>
</div>
