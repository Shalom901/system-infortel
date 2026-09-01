<?php
$modo = $modo ?? 'crear';
$producto = $producto ?? [];
?>
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <!-- Page header -->
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
        <div class="mb-4 sm:mb-0 flex items-center gap-3">
            <a href="<?= baseUrl('productos') ?>" class="text-slate-400 hover:text-sky-500 transition-colors">
                <i class="fa-solid fa-arrow-left text-xl"></i>
            </a>
            <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold"><?= htmlspecialchars($titulo ?? 'Formulario de Producto') ?></h1>
        </div>
    </div>

    <!-- Main form -->
    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 p-4 rounded-lg mb-6 border border-red-200 dark:border-red-800">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?= baseUrl($modo === 'crear' ? 'productos/guardar' : "productos/" . ($producto['id'] ?? '') . "/actualizar") ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <?php if ($modo === 'editar'): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars((string)($producto['id'] ?? '')) ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            
            <!-- Columna Izquierda (Información Principal) -->
            <div class="xl:col-span-2 space-y-6">
                <div class="bg-white dark:bg-slate-800 shadow-lg rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6 border-b border-slate-200 dark:border-slate-700 pb-3">Información General</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Nombre -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Nombre del Producto *</label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars((string)($producto['nombre'] ?? '')) ?>" required class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 px-4 transition-colors" placeholder="Ej. Lector de Códigos 2D">
                        </div>

                        <!-- Código Interno -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Código Interno</label>
                            <input type="text" name="codigo_interno" value="<?= htmlspecialchars((string)($producto['codigo_interno'] ?? '')) ?>" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 px-4 transition-colors" placeholder="Generado automáticamente si se deja vacío">
                        </div>

                        <!-- Categoría -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Categoría *</label>
                            <select name="categoria_id" required class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 px-4 transition-colors">
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categorias ?? [] as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (($producto['categoria_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Marca -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Marca</label>
                            <select name="marca_id" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 px-4 transition-colors">
                                <option value="">Sin marca</option>
                                <?php foreach ($marcas ?? [] as $marca): ?>
                                    <option value="<?= $marca['id'] ?>" <?= (($producto['marca_id'] ?? '') == $marca['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($marca['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Unidad de Medida -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Unidad de Medida *</label>
                            <select name="unidad_medida_id" required class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 px-4 transition-colors">
                                <option value="">Seleccione una unidad</option>
                                <?php foreach ($unidades ?? [] as $uni): ?>
                                    <option value="<?= $uni['id'] ?>" <?= (($producto['unidad_medida_id'] ?? '') == $uni['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($uni['nombre'] . ' (' . $uni['abreviatura'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 shadow-lg rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6 border-b border-slate-200 dark:border-slate-700 pb-3">Gestión de Inventario</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Proveedor -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Proveedor Principal</label>
                            <select name="proveedor_principal_id" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 px-4 transition-colors">
                                <option value="">Ninguno</option>
                                <?php foreach ($proveedores ?? [] as $prov): ?>
                                    <option value="<?= $prov['id'] ?>" <?= (($producto['proveedor_principal_id'] ?? '') == $prov['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prov['razon_social'] . ' - ' . $prov['numero_doc']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Stock Actual (Solo Crear) -->
                        <?php if ($modo === 'crear'): ?>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Stock Inicial</label>
                            <!-- El casteo (float) limpia la presentación automáticamente -->
                            <input type="number" step="0.01" name="stock_actual" value="<?= (float)($producto['stock_actual'] ?? 0) ?>" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 px-4 transition-colors">
                        </div>
                        <?php endif; ?>

                        <!-- Stock Mínimo -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Stock Mínimo (Alerta)</label>
                            <input type="number" step="0.01" name="stock_minimo" value="<?= (float)($producto['stock_minimo'] ?? 0) ?>" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 px-4 transition-colors">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha (Precios e Imagen) -->
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 shadow-lg rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-6 border-b border-slate-200 dark:border-slate-700 pb-3">Precios (PEN)</h2>
                    
                    <div class="space-y-5">
                        <!-- Precio Compra -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Precio de Compra</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-slate-400 font-medium">S/</span>
                                </div>
                                <input type="number" step="0.01" name="precio_compra_pen" 
                                    value="<?= number_format((float)($producto['precio_compra_pen'] ?? 0), 2, '.', '') ?>" 
                                    required class="pl-9 w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-800 dark:text-slate-200 focus:ring-sky-500 focus:border-sky-500 py-2.5 px-4 transition-colors">
                            </div>
                        </div>

                        <!-- Precio Venta -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Precio de Venta (Público) *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-slate-400 font-medium">S/</span>
                                </div>
                                <input type="number" step="0.01" name="precio_venta_pen" 
                                    value="<?= number_format((float)($producto['precio_venta_pen'] ?? 0), 2, '.', '') ?>" 
                                    required class="pl-9 w-full bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700/50 rounded-lg text-sm text-emerald-800 dark:text-emerald-200 focus:ring-emerald-500 focus:border-emerald-500 py-2.5 px-4 transition-colors font-bold">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Imagen -->
                <div class="bg-white dark:bg-slate-800 shadow-lg rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 mb-4 border-b border-slate-200 dark:border-slate-700 pb-3">Imagen</h2>
                    <div class="w-full">
                        <label class="flex flex-col w-full h-32 border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-sky-500 dark:hover:border-sky-400 rounded-xl cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                            <div class="flex flex-col items-center justify-center pt-7">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-400 mb-2"></i>
                                <p class="text-sm tracking-wider text-slate-500 font-medium">Seleccionar imagen</p>
                            </div>
                            <input id="productImageInput" type="file" name="imagen" accept="image/jpeg,image/png,image/gif,image/webp" class="opacity-0" />
                        </label>
                    </div>
                    <div id="productImagePreview" class="mt-4 flex justify-center <?= empty($producto['imagen_path']) ? 'hidden' : '' ?>">
                        <img id="productImagePreviewImage" src="<?= !empty($producto['imagen_path']) ? baseUrl('uploads/products/' . rawurlencode($producto['imagen_path'])) : '' ?>" alt="Vista previa de la imagen" class="h-28 max-w-full rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 object-cover">
                    </div>
                </div>

                <!-- Estado -->
                <div class="bg-white dark:bg-slate-800 shadow-lg rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                    <label class="flex items-center cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" name="activo" value="1" class="sr-only" <?= (($producto['activo'] ?? 1) == 1) ? 'checked' : '' ?>>
                            <div class="block bg-slate-200 dark:bg-slate-700 w-14 h-8 rounded-full transition-colors toggle-bg"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition-transform toggle-dot shadow-sm"></div>
                        </div>
                        <div class="ml-3 text-sm font-medium text-slate-700 dark:text-slate-300 font-semibold">Producto Activo</div>
                    </label>
                    <style>
                        input:checked ~ .toggle-bg { background-color: #0ea5e9; }
                        input:checked ~ .toggle-dot { transform: translateX(100%); }
                    </style>
                </div>
            </div>

        </div>

        <!-- Botones -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
            <a href="<?= baseUrl('productos') ?>" class="px-5 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-medium transition-colors">
                Cancelar
            </a>
            <button type="submit" class="px-5 py-2.5 bg-sky-500 hover:bg-sky-600 text-white rounded-xl text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <?= $modo === 'crear' ? 'Guardar Producto' : 'Actualizar Producto' ?>
            </button>
        </div>
    </form>
</div>

<script>
(() => {
    const input = document.getElementById('productImageInput');
    const container = document.getElementById('productImagePreview');
    const image = document.getElementById('productImagePreviewImage');

    if (!input || !container || !image || input.dataset.previewBound === 'true') return;
    input.dataset.previewBound = 'true';

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;

        if (!file.type.startsWith('image/')) {
            input.value = '';
            container.classList.add('hidden');
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            image.src = event.target.result;
            container.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });
})();
</script>
