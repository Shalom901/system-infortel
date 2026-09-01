<?php include __DIR__ . '/../layouts/app.php'; ?>
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <div class="mb-8">
        <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold">Agregar Producto ✨</h1>
    </div>
    
    <div class="bg-white dark:bg-slate-800 shadow-lg rounded-sm border border-slate-200 dark:border-slate-700 p-5">
        <form action="/productos" method="POST">
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium mb-1 dark:text-slate-300" for="codigo">Código Interno</label>
                    <input id="codigo" name="codigo" class="form-input w-full dark:bg-slate-900 border-slate-300 dark:border-slate-700 rounded-md shadow-sm" type="text" required />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 dark:text-slate-300" for="nombre">Nombre del Producto</label>
                    <input id="nombre" name="nombre" class="form-input w-full dark:bg-slate-900 border-slate-300 dark:border-slate-700 rounded-md shadow-sm" type="text" required />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 dark:text-slate-300" for="precio_pen">Precio Venta (PEN)</label>
                    <input id="precio_pen" name="precio_pen" class="form-input w-full dark:bg-slate-900 border-slate-300 dark:border-slate-700 rounded-md shadow-sm" type="number" step="0.01" required />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 dark:text-slate-300" for="stock">Stock Inicial</label>
                    <input id="stock" name="stock" class="form-input w-full dark:bg-slate-900 border-slate-300 dark:border-slate-700 rounded-md shadow-sm" type="number" required />
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <a href="/productos" class="btn border-slate-200 hover:border-slate-300 text-slate-600 dark:text-slate-300 dark:border-slate-700 dark:hover:border-slate-600 px-4 py-2 rounded">Cancelar</a>
                <button type="submit" class="btn bg-indigo-500 hover:bg-indigo-600 text-white px-4 py-2 rounded">Guardar Producto</button>
            </div>
        </form>
    </div>
</div>
