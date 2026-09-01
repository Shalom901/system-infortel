<div class="max-w-7xl mx-auto w-full pb-10">
    <!-- Encabezado -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-100 px-3 py-1 text-xs font-bold tracking-wide text-sky-700 dark:bg-sky-900/40 dark:text-sky-300"><i class="fa-solid fa-sparkles"></i> NUEVO PRESUPUESTO</span>
            <h1 class="text-2xl font-bold text-slate-800 dark:text-white">Nueva Cotización</h1>
            <p class="text-sm text-slate-500">Crea un presupuesto con productos o servicios libres</p>
        </div>
        <a href="<?= baseUrl('cotizaciones') ?>" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-medium transition-colors">
            <i class="fa-solid fa-arrow-left mr-2"></i> Volver al historial
        </a>
    </div>

    <!-- Contenedor Principal (Grid) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- COLUMNA IZQUIERDA: Búsqueda y Detalle -->
        <div class="lg:col-span-2 flex flex-col gap-6">
            
            <!-- Panel de Búsqueda de Productos / Items Libres -->
            <div class="bg-white rounded-2xl p-5 relative z-20 border border-slate-100 dark:border-slate-700">
                <div class="mb-4 flex items-center justify-between">
                    <div><h2 class="font-bold text-slate-800 dark:text-white">Agregar conceptos</h2><p class="text-xs text-slate-500">Busca en el catálogo o crea un servicio personalizado.</p></div>
                </div>
                <div class="flex flex-col sm:flex-row gap-4 mb-4">
                    <div class="relative flex-1">
                        <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="searchProductInput" 
                               class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all dark:bg-slate-900/50 dark:border-slate-700 dark:text-white" 
                               placeholder="Buscar producto por nombre o código..." autocomplete="off">
                        <!-- Dropdown Resultados -->
                        <div id="productResultsDropdown" class="absolute w-full mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-50 hidden max-h-64 overflow-y-auto custom-scrollbar">
                            <!-- Resultados aquí -->
                        </div>
                    </div>
                    
                    <button type="button" onclick="openFreeItemModal()" class="flex-shrink-0 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold px-5 py-3 rounded-xl transition-all border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-2">
                        <i class="fa-solid fa-pen-clip text-primary"></i> Ítem Libre
                    </button>
                </div>
            </div>

            <!-- Panel de Lista de Ítems (Carrito de Cotización) -->
            <div class="bg-white rounded-2xl p-0 overflow-hidden flex flex-col flex-1 min-h-[400px] border border-slate-100 dark:border-slate-700">
                <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/20">
                    <span id="quoteItemsCount" class="float-right rounded-full bg-sky-100 px-2.5 py-1 text-xs font-bold text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">0 ítems</span>
                    <h3 class="font-bold text-slate-700 dark:text-slate-300"><i class="fa-solid fa-list-check mr-2"></i> Detalle de Cotización</h3>
                </div>
                
                <div class="flex-1 overflow-y-auto p-2 bg-slate-50/30 dark:bg-slate-900/10 custom-scrollbar" id="quoteItemsList">
                    <!-- Placeholder si está vacío -->
                    <div class="h-full flex flex-col items-center justify-center text-slate-400 opacity-70 py-10" id="emptyQuotePlaceholder">
                        <i class="fa-solid fa-file-invoice-dollar text-5xl mb-3"></i>
                        <p class="font-medium text-sm">No hay ítems en la cotización</p>
                        <p class="text-xs">Busca un producto o agrega un ítem libre</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- COLUMNA DERECHA: Datos del Cliente y Totales -->
        <div class="flex flex-col gap-6 lg:sticky lg:top-5 lg:self-start">
            
            <!-- Panel Cliente -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 dark:border-slate-700">
                <h3 class="font-bold text-slate-700 dark:text-slate-300 mb-4"><i class="fa-solid fa-user-tag mr-2"></i> Datos del Cliente</h3>
                
                <div class="relative mb-4">
                    <i class="fa-solid fa-user absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" id="searchClientInput" 
                           class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-sky-500/20 transition-all dark:bg-slate-900/50 dark:border-slate-700 dark:text-white" 
                           placeholder="Buscar cliente (DNI/RUC o Nombre)..." autocomplete="off">
                    <div id="clientResultsDropdown" class="absolute w-full mt-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl z-50 hidden max-h-48 overflow-y-auto custom-scrollbar"></div>
                </div>

                <div class="bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl border border-slate-100 dark:border-slate-800 relative group">
                    <button type="button" onclick="resetClient()" class="absolute top-2 right-2 text-slate-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity" title="Quitar cliente">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <p class="text-xs text-slate-500 uppercase tracking-wider mb-1 font-bold">Cliente Seleccionado</p>
                    <p class="font-bold text-slate-800 dark:text-white text-sm" id="selectedClientName">CLIENTE GENÉRICO</p>
                    <p class="text-xs text-slate-500 mt-1" id="selectedClientDoc">DNI: 00000000</p>
                </div>
            </div>

            <!-- Panel Ajustes de Cotización -->
            <div class="bg-white rounded-2xl p-5">
                <h3 class="font-bold text-slate-700 dark:text-slate-300 mb-4"><i class="fa-solid fa-gear mr-2"></i> Ajustes</h3>
                
                <div class="space-y-4 text-sm">
                    <div>
                        <label class="block text-slate-500 mb-1 font-medium">Validez (días)</label>
                        <select id="quoteValidez" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl dark:bg-slate-900/50 dark:border-slate-700 dark:text-white">
                            <option value="5">5 días</option>
                            <option value="15" selected>15 días</option>
                            <option value="30">30 días</option>
                            <option value="60">60 días</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 mb-1 font-medium">Condiciones (Opcional)</label>
                        <input type="text" id="quoteCondiciones" placeholder="Ej. Pago al contado, entrega inmediata" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl dark:bg-slate-900/50 dark:border-slate-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-slate-500 mb-1 font-medium">Notas internas (Privado)</label>
                        <input type="text" id="quoteNotas" placeholder="Notas solo para el sistema" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl dark:bg-slate-900/50 dark:border-slate-700 dark:text-white">
                    </div>
                </div>
            </div>

            <!-- Panel Totales y Guardar -->
            <div class="bg-white rounded-2xl p-6 shadow-2xl relative overflow-hidden border border-sky-100 dark:border-slate-700">
                <!-- Decoración -->
                <div class="absolute -right-10 -top-10 w-32 h-32 bg-sky-500/10 rounded-full blur-2xl"></div>
                
                <div class="space-y-3 mb-6 relative z-10">
                    <div class="flex justify-between text-slate-500 text-sm">
                        <span>Subtotal</span>
                        <span class="font-medium" id="lblSubtotal">S/ 0.00</span>
                    </div>
                    <!-- NOTA: IGV oculto por defecto ya que manejas precios finales -->
                    <div class="flex justify-between text-slate-500 text-sm hidden">
                        <span>IGV (18%)</span>
                        <span class="font-medium" id="lblIgv">S/ 0.00</span>
                    </div>
                    <div class="h-px w-full bg-slate-200 dark:bg-slate-700 my-2"></div>
                    <div class="flex justify-between items-end">
                        <span class="text-slate-700 dark:text-slate-300 font-bold uppercase tracking-wider text-xs">Total a Cotizar</span>
                        <span class="text-3xl font-black text-sky-600 dark:text-sky-400" id="lblTotal">S/ 0.00</span>
                    </div>
                </div>

                <button type="button" onclick="saveQuote()" id="btnSaveQuote" class="w-full bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-sky-500/30 transition-all hover:-translate-y-0.5 hover:shadow-sky-500/50 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-file-signature text-lg"></i> 
                    <span id="btnSaveQuoteText">Generar Cotización</span>
                    <i class="fa-solid fa-circle-notch fa-spin hidden" id="btnSaveQuoteSpinner"></i>
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: ÍTEM LIBRE (SERVICIOS)              -->
<!-- ========================================== -->
<div id="freeItemModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center opacity-0 transition-opacity duration-300">
    <div class="bg-white dark:bg-slate-800 w-full max-w-md rounded-2xl shadow-2xl transform scale-95 transition-transform duration-300 p-6 border border-slate-200/50 dark:border-slate-700" id="freeItemModalContent">
        
        <div class="flex justify-between items-center mb-5">
            <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-600 flex items-center justify-center">
                    <i class="fa-solid fa-pen-clip"></i>
                </div>
                Añadir Ítem Libre
            </h3>
            <button onclick="closeFreeItemModal()" class="text-slate-400 hover:text-slate-600 transition-colors w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Descripción / Concepto</label>
                <textarea id="fiDesc" rows="2" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-500/20 outline-none transition-all dark:bg-slate-900/50 dark:border-slate-700 dark:text-white resize-none" placeholder="Ej. Mantenimiento preventivo de red LAN..."></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Precio Final (S/)</label>
                    <input type="number" id="fiPrecio" min="0" step="0.01" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-sky-500/20 outline-none transition-all dark:bg-slate-900/50 dark:border-slate-700 dark:text-white text-lg font-bold text-sky-600">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Cantidad</label>
                    <div class="flex items-center bg-slate-50 border border-slate-200 rounded-xl overflow-hidden dark:bg-slate-900/50 dark:border-slate-700">
                        <button onclick="updateFiQty(-1)" class="px-4 py-3 text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-700 font-bold transition-colors">-</button>
                        <input type="number" id="fiQty" value="1" min="1" step="0.01" class="w-full text-center bg-transparent border-none focus:ring-0 font-bold text-slate-700 dark:text-white p-0">
                        <button onclick="updateFiQty(1)" class="px-4 py-3 text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-700 font-bold transition-colors">+</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-3">
            <button onclick="closeFreeItemModal()" class="px-5 py-2.5 rounded-xl font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">Cancelar</button>
            <button onclick="addFreeItemToQuote()" class="px-5 py-2.5 rounded-xl font-bold text-white bg-sky-500 hover:bg-sky-600 shadow-md transition-all hover:-translate-y-0.5">Añadir a Cotización</button>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- JAVASCRIPT: Lógica de Cotizaciones         -->
<!-- ========================================== -->
<script>
    // Variables Globales
    let quoteItems = [];
    const CLIENTE_GENERICO = <?= $clienteGenerico ?>;
    let currentClient = { ...CLIENTE_GENERICO };
    
    // Configuración general
    const API_CLIENTS = '<?= baseUrl('api/clientes/buscar') ?>';
    const API_PRODUCTS = '<?= baseUrl('api/productos/buscar') ?>';
    const API_SAVE = '<?= baseUrl('cotizaciones/guardar') ?>';

    // Inicializador
    const initQuotes = () => {
        const searchInput = document.getElementById('searchProductInput');
        if (!searchInput) return; 
        
        if (searchInput.dataset.initialized === 'true') return;
        searchInput.dataset.initialized = 'true';

        setupSearchInputs();
        renderQuoteItems();
        
        // Cargar productos por defecto al inicio
        loadDefaultProducts();
    };
    
    document.addEventListener('turbo:load', initQuotes);
    if (document.readyState !== 'loading') initQuotes();

    function loadDefaultProducts() {
        fetch(`${API_PRODUCTS}?limit=10`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(res => {
                const prodDropdown = document.getElementById('productResultsDropdown');
                renderProductDropdown(res.productos || [], prodDropdown, true);
            })
            .catch(err => {
                const prodDropdown = document.getElementById('productResultsDropdown');
                prodDropdown.innerHTML = `<div class="p-4 text-center text-red-500 text-sm">Error cargando productos: ${err.message}</div>`;
                prodDropdown.classList.remove('hidden');
            });
    }

    function renderProductDropdown(productos, dropdown, isDefault = false) {
        dropdown.innerHTML = '';
        if (isDefault && productos.length > 0) {
            dropdown.innerHTML = `<div class="px-3 py-2 bg-slate-100 dark:bg-slate-700/50 text-xs font-bold text-slate-500 uppercase tracking-wider">Productos Sugeridos</div>`;
        }
        
        if (productos.length === 0) {
            dropdown.innerHTML = `<div class="p-4 text-center text-slate-500 text-sm">No se encontraron productos</div>`;
        } else {
            productos.forEach(p => {
                const div = document.createElement('div');
                div.className = "p-3 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer border-b border-slate-100 dark:border-slate-800 last:border-0 transition-colors";
                div.innerHTML = `
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-bold text-sm text-slate-800 dark:text-white">${p.nombre}</p>
                            <p class="text-xs text-slate-500">Cód: ${p.codigo_interno || p.codigo_barras || '-'} | Stock: ${p.stock_actual}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-emerald-600">S/ ${parseFloat(p.precio_venta || p.precio_venta_pen || 0).toFixed(2)}</p>
                        </div>
                    </div>
                `;
                div.onclick = () => {
                    addCatalogProduct(p);
                    const prodInput = document.getElementById('searchProductInput');
                    prodInput.value = '';
                    dropdown.classList.add('hidden');
                    loadDefaultProducts(); // Recargar sugeridos
                };
                dropdown.appendChild(div);
            });
        }
        dropdown.classList.remove('hidden');
    }

    // ---- SETUP BÚSQUEDAS ----
    function setupSearchInputs() {
        const prodInput = document.getElementById('searchProductInput');
        const prodDropdown = document.getElementById('productResultsDropdown');
        let prodTimeout;

        // Mostrar dropdown al enfocar
        prodInput.addEventListener('focus', () => {
            if (prodInput.value.trim().length === 0) {
                prodDropdown.classList.remove('hidden');
            }
        });

        prodInput.addEventListener('input', (e) => {
            clearTimeout(prodTimeout);
            const val = e.target.value.trim();
            
            if (val.length === 0) {
                loadDefaultProducts();
                return;
            }
            
            if (val.length < 2) return;
            
            prodTimeout = setTimeout(() => {
                fetch(`${API_PRODUCTS}?q=${encodeURIComponent(val)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(res => {
                        renderProductDropdown(res.productos || [], prodDropdown, false);
                    })
                    .catch(err => {
                        prodDropdown.innerHTML = `<div class="p-4 text-center text-red-500 text-sm">Error en búsqueda: ${err.message}</div>`;
                        prodDropdown.classList.remove('hidden');
                    });
            }, 300);
        });

        // Ocultar dropdown al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!prodInput.contains(e.target) && !prodDropdown.contains(e.target)) {
                prodDropdown.classList.add('hidden');
            }
        });

        // Búsqueda de Clientes
        const cliInput = document.getElementById('searchClientInput');
        const cliDropdown = document.getElementById('clientResultsDropdown');
        let cliTimeout;

        cliInput.addEventListener('input', (e) => {
            clearTimeout(cliTimeout);
            const val = e.target.value.trim();
            if (val.length < 2) { cliDropdown.classList.add('hidden'); return; }
            
            cliTimeout = setTimeout(() => {
                fetch(`${API_CLIENTS}?q=${encodeURIComponent(val)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(res => {
                        const data = res.clientes || [];
                        cliDropdown.innerHTML = '';
                        if (data.length === 0) {
                            cliDropdown.innerHTML = `<div class="p-3 text-center text-slate-500 text-sm">No se encontraron clientes</div>`;
                        } else {
                            data.forEach(c => {
                                const div = document.createElement('div');
                                div.className = "p-3 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer border-b border-slate-100 dark:border-slate-800 last:border-0 transition-colors";
                                div.innerHTML = `
                                    <p class="font-bold text-sm text-slate-800 dark:text-white">${c.razon_social}</p>
                                    <p class="text-xs text-slate-500">${c.numero_doc}</p>
                                `;
                                div.onclick = () => {
                                    setClient(c);
                                    cliInput.value = '';
                                    cliDropdown.classList.add('hidden');
                                };
                                cliDropdown.appendChild(div);
                            });
                        }
                        cliDropdown.classList.remove('hidden');
                    });
            }, 300);
        });
        
        document.addEventListener('click', (e) => {
            if (!cliInput.contains(e.target) && !cliDropdown.contains(e.target)) cliDropdown.classList.add('hidden');
        });
    }

    function setClient(c) {
        currentClient = c;
        document.getElementById('selectedClientName').textContent = c.razon_social;
        document.getElementById('selectedClientDoc').textContent = c.tipo_doc + ': ' + c.numero_doc;
        showToast('Cliente seleccionado', 'success');
    }

    function resetClient() {
        setClient(CLIENTE_GENERICO);
    }

    // ---- LOGICA DE ITEMS ----

    function addCatalogProduct(prod) {
        const index = quoteItems.findIndex(i => i.producto_id === prod.id);
        const precio = parseFloat(prod.precio_venta || prod.precio_venta_pen || 0);

        if (index !== -1) {
            quoteItems[index].cantidad++;
            quoteItems[index].subtotal = quoteItems[index].precio_unitario * quoteItems[index].cantidad;
            quoteItems[index].total = quoteItems[index].subtotal;
        } else {
            quoteItems.push({
                producto_id: prod.id,
                descripcion: prod.nombre,
                precio_unitario: precio,
                cantidad: 1,
                subtotal: precio,
                total: precio,
                is_free: false
            });
        }
        renderQuoteItems();
        showToast('Producto agregado', 'success');
    }

    // Modal Item Libre
    function openFreeItemModal() {
        const modal = document.getElementById('freeItemModal');
        document.getElementById('fiDesc').value = '';
        document.getElementById('fiPrecio').value = '';
        document.getElementById('fiQty').value = '1';
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            document.getElementById('freeItemModalContent').classList.remove('scale-95');
            document.getElementById('fiDesc').focus();
        }, 10);
    }

    function closeFreeItemModal() {
        const modal = document.getElementById('freeItemModal');
        modal.classList.add('opacity-0');
        document.getElementById('freeItemModalContent').classList.add('scale-95');
        setTimeout(() => { modal.classList.add('hidden'); }, 300);
    }

    function updateFiQty(change) {
        const input = document.getElementById('fiQty');
        let val = parseFloat(input.value) || 1;
        val += change;
        if (val < 0.1) val = 0.1;
        input.value = val.toFixed(2).replace(/\.00$/, '');
    }

    function addFreeItemToQuote() {
        const desc = document.getElementById('fiDesc').value.trim();
        const price = parseFloat(document.getElementById('fiPrecio').value);
        const qty = parseFloat(document.getElementById('fiQty').value);

        if (!desc) { showToast('Ingresa una descripción', 'error'); return; }
        if (isNaN(price) || price < 0) { showToast('Ingresa un precio válido', 'error'); return; }
        if (isNaN(qty) || qty <= 0) { showToast('Cantidad inválida', 'error'); return; }

        quoteItems.push({
            producto_id: null,
            descripcion: desc,
            precio_unitario: price,
            cantidad: qty,
            subtotal: price * qty,
            total: price * qty,
            is_free: true
        });

        closeFreeItemModal();
        renderQuoteItems();
        showToast('Ítem libre agregado', 'success');
    }

    function removeQuoteItem(index) {
        quoteItems.splice(index, 1);
        renderQuoteItems();
    }

    function changeQty(index, change) {
        let val = quoteItems[index].cantidad + change;
        if (val < 0.1) val = 0.1;
        quoteItems[index].cantidad = val;
        recalcItem(index);
        renderQuoteItems();
    }

    function updatePrice(index, newPrice) {
        let p = parseFloat(newPrice);
        if (isNaN(p) || p < 0) p = 0;
        quoteItems[index].precio_unitario = p;
        recalcItem(index);
        renderTotals();
    }

    function recalcItem(index) {
        const item = quoteItems[index];
        item.subtotal = item.precio_unitario * item.cantidad;
        item.total = item.subtotal;
    }

    // ---- RENDER ----
    function renderQuoteItems() {
        const container = document.getElementById('quoteItemsList');
        const placeholder = document.getElementById('emptyQuotePlaceholder');

        const existingCounter = document.getElementById('quoteItemsCount');
        if (existingCounter) existingCounter.textContent = `${quoteItems.length} ${quoteItems.length === 1 ? 'ítem' : 'ítems'}`;

        if (quoteItems.length === 0) {
            container.innerHTML = '';
            container.appendChild(placeholder);
            placeholder.classList.remove('hidden');
            renderTotals();
            return;
        }
        
        if (placeholder) placeholder.classList.add('hidden');
        
        let html = '<div class="space-y-3">';
        quoteItems.forEach((item, index) => {
            const badge = item.is_free 
                ? `<span class="bg-indigo-100 text-indigo-700 text-[10px] font-bold px-2 py-0.5 rounded-md uppercase ml-2">Ítem Libre</span>`
                : '';
                
            html += `
                <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between gap-4 group">
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm text-slate-800 dark:text-white truncate">${item.descripcion} ${badge}</p>
                        <div class="flex items-center gap-4 mt-2">
                            <!-- Input Precio Editable -->
                            <div class="flex items-center bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1">
                                <span class="text-xs font-bold text-slate-400 mr-1">S/</span>
                                <input type="number" value="${item.precio_unitario.toFixed(2)}" step="0.01" min="0"
                                       onchange="updatePrice(${index}, this.value)"
                                       class="w-20 bg-transparent border-none p-0 text-sm font-bold focus:ring-0 text-slate-700 dark:text-slate-200">
                            </div>
                            
                            <!-- Controles Cantidad -->
                            <div class="flex items-center bg-slate-50 border border-slate-200 dark:bg-slate-900 dark:border-slate-700 rounded-lg">
                                <button onclick="changeQty(${index}, -1)" class="w-8 h-7 flex items-center justify-center text-slate-500 hover:text-sky-600 font-bold transition-colors">-</button>
                                <span class="w-10 text-center text-sm font-bold">${item.cantidad.toString().replace(/\.00$/, '')}</span>
                                <button onclick="changeQty(${index}, 1)" class="w-8 h-7 flex items-center justify-center text-slate-500 hover:text-sky-600 font-bold transition-colors">+</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-right pl-4 border-l border-slate-100 dark:border-slate-700 flex flex-col justify-between h-full">
                        <button onclick="removeQuoteItem(${index})" class="text-slate-300 hover:text-red-500 self-end transition-colors" title="Quitar">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                        <p class="font-black text-slate-800 dark:text-white text-lg mt-2">S/ ${item.total.toFixed(2)}</p>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
        renderTotals();
    }

    function renderTotals() {
        let total = quoteItems.reduce((sum, item) => sum + item.total, 0);
        document.getElementById('lblSubtotal').textContent = `S/ ${total.toFixed(2)}`;
        document.getElementById('lblTotal').textContent = `S/ ${total.toFixed(2)}`;
    }

    // ---- GUARDAR COTIZACIÓN ----
    function saveQuote() {
        if (quoteItems.length === 0) {
            showToast('Agrega al menos un ítem a la cotización', 'warning');
            return;
        }

        const btn = document.getElementById('btnSaveQuote');
        const text = document.getElementById('btnSaveQuoteText');
        const spinner = document.getElementById('btnSaveQuoteSpinner');
        
        btn.disabled = true;
        text.classList.add('opacity-50');
        spinner.classList.remove('hidden');

        const total = quoteItems.reduce((sum, item) => sum + item.total, 0);
        
        const payload = {
            cliente_id: currentClient.id,
            validez: document.getElementById('quoteValidez').value,
            condiciones: document.getElementById('quoteCondiciones').value,
            notas: document.getElementById('quoteNotas').value,
            moneda: 'PEN',
            subtotal: total,
            total: total,
            items: quoteItems
        };

        fetch(API_SAVE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('Cotización Generada!', 'success');
                // Limpiar todo y redirigir
                setTimeout(() => {
                    if (typeof Turbo !== 'undefined') {
                        Turbo.visit('<?= baseUrl('cotizaciones') ?>');
                    } else {
                        window.location.href = '<?= baseUrl('cotizaciones') ?>';
                    }
                }, 1500);
            } else {
                showToast(res.message || 'Error al guardar', 'error');
                btn.disabled = false;
                text.classList.remove('opacity-50');
                spinner.classList.add('hidden');
            }
        })
        .catch(err => {
            showToast('Error de conexión', 'error');
            btn.disabled = false;
            text.classList.remove('opacity-50');
            spinner.classList.add('hidden');
        });
    }
</script>
