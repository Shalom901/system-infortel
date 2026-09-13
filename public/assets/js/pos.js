/**
 * Punto de Venta (POS) - Script Principal
 * Maneja la lógica del carrito, búsqueda y cobro interactivo.
 */

// Turbo reemplaza el contenido de la página al navegar. Restablecemos el
// indicador antes de guardar la vista en caché para que los controles del POS
// (incluido "Nuevo Cliente") vuelvan a enlazarse al regresar.
document.addEventListener('turbo:before-cache', () => {
    window.posInitialized = false;
});

// Escuchamos turbo:load para ejecutar cada vez que entras al POS
document.addEventListener('turbo:load', () => {
    const productGrid = document.getElementById('productGrid');
    if (!productGrid) return; // No estamos en la vista del POS, salir limpiamente
    
    // Si esta pantalla específica ya cargó los productos, no repetir
    if (productGrid.dataset.loaded === 'true') return;
    productGrid.dataset.loaded = 'true';
    // --- Variables de Estado ---
    let cart = [];
    let currentClient = CLIENTE_GENERICO; // Definido en index.php
    let currentCategory = 0;
    
    // --- Referencias al DOM ---
    const cartItemsContainer = document.getElementById('cartItems');
    const cartTotalEl = document.getElementById('cartTotal');
    const productSearchInput = document.getElementById('productSearch');
    const categoryButtons = document.querySelectorAll('.cat-btn');
    
    // Checkout Modal
    const checkoutModal = document.getElementById('checkoutModal');
    const btnCobrar = document.getElementById('btnCobrar');
    const closeCheckoutBtn = document.getElementById('closeCheckout');
    const cancelCheckoutBtn = document.getElementById('cancelCheckout');
    const confirmCheckoutBtn = document.getElementById('confirmCheckout');
    const modalTotalEl = document.getElementById('modalTotal');
    
    // Elementos de pago
    const metodoPagoSelect = document.getElementById('metodoPago');
    const montoRecibidoContainer = document.getElementById('montoRecibidoContainer');
    const montoRecibidoInput = document.getElementById('montoRecibido');
    const vueltoContainer = document.getElementById('vueltoContainer');
    const vueltoMontoEl = document.getElementById('vueltoMonto');
    
    // Buscador de Clientes
    const clientSearchInput = document.getElementById('clientSearch');

    // --- Funciones Iniciales ---
    loadProducts();
    renderCart();
    
    // --- Búsqueda y Filtrado de Productos ---
    
    productSearchInput.addEventListener('input', debounce((e) => {
        loadProducts(e.target.value, currentCategory);
    }, 300));
    
    categoryButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Actualizar UI botones
            categoryButtons.forEach(b => {
                b.classList.remove('bg-primary', 'text-white', 'shadow-sm');
                b.classList.add('bg-white', 'dark:bg-slate-800');
            });
            e.target.classList.remove('bg-white', 'dark:bg-slate-800');
            e.target.classList.add('bg-primary', 'text-white', 'shadow-sm');
            
            currentCategory = e.target.dataset.id;
            loadProducts(productSearchInput.value, currentCategory);
        });
    });

    function loadProducts(query = '', categoryId = 0) {
        // Mostrar estado de carga (opcional)
        productGrid.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500"><i class="fa-solid fa-spinner fa-spin text-3xl mb-2"></i><p>Cargando productos...</p></div>';
        
        fetch(`${BASE_URL}/pos/search-product?q=${encodeURIComponent(query)}&categoria_id=${categoryId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderProducts(data.productos);
                }
            })
            .catch(err => {
                console.error('Error cargando productos:', err);
                productGrid.innerHTML = '<div class="col-span-full text-center py-8 text-red-500"><p>Error de conexión</p></div>';
            });
    }
    
    function renderProducts(productos) {
        if (productos.length === 0) {
            productGrid.innerHTML = '<div class="col-span-full text-center py-8 text-gray-500"><p>No se encontraron productos</p></div>';
            return;
        }
        
        productGrid.innerHTML = productos.map(prod => {
            const precioFormateado = parseFloat(prod.precio_venta).toFixed(2);
            const stock = prod.stock_actual;
            const hasStock = stock > 0;
            
            return `
                <div onclick="window.addToCart(${prod.id})" class="bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm p-4 rounded-2xl shadow-sm border border-slate-200/50 dark:border-slate-700/50 ${hasStock ? 'cursor-pointer hover:border-primary/50 dark:hover:border-primary/50 hover:shadow-xl hover:-translate-y-1' : 'opacity-50 grayscale'} transition-all duration-300 group relative overflow-hidden">
                    ${!hasStock ? '<div class="absolute inset-0 bg-white/60 dark:bg-slate-900/60 backdrop-blur-[2px] z-10 flex items-center justify-center font-black text-red-500 text-lg tracking-widest uppercase">Agotado</div>' : ''}
                    <div class="aspect-square rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-800 mb-3 flex items-center justify-center overflow-hidden shadow-inner">
                        ${prod.imagen ? `<img src="${BASE_URL}/uploads/products/${encodeURIComponent(prod.imagen)}" class="w-full h-full object-cover group-hover:scale-110 transition duration-500 ease-out" alt="${escapeHtml(prod.nombre)}" onerror="this.onerror=null; this.src='https://placehold.co/400?text=Sin+Imagen';">` : `<i class="fa-solid fa-box-open text-5xl text-slate-300 dark:text-slate-600 group-hover:scale-110 group-hover:text-primary/40 transition duration-500 ease-out"></i>`}
                    </div>
                    <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200 leading-tight mb-2 h-10 overflow-hidden line-clamp-2 group-hover:text-primary transition-colors">${prod.nombre}</h3>
                    <div class="flex justify-between items-end mt-auto">
                        <span class="font-black text-lg text-primary drop-shadow-sm">S/ ${precioFormateado}</span>
                        <span class="text-xs font-bold ${hasStock ? 'text-emerald-600 bg-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-400' : 'text-red-600 bg-red-100 dark:bg-red-900/40 dark:text-red-400'} px-2 py-1 rounded-lg shadow-sm">${stock} ud</span>
                    </div>
                </div>
            `;
        }).join('');
    }


    // Asegurar cliente global en window
    window.currentClient = (typeof CLIENTE_GENERICO !== 'undefined' && CLIENTE_GENERICO) 
        ? CLIENTE_GENERICO 
        : { id: 1, numero_doc: '00000000', razon_social: 'CLIENTE GENÉRICO' };
    // --- Lógica del Carrito ---
    
    // Necesitamos hacerlo global para el onClick
    window.addToCart = function(productId) {
        // Buscar el producto en los datos cargados o hacer petición al backend
        fetch(`${BASE_URL}/pos/get-product/${productId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const prod = data.producto || data.data; // Dependiendo de la estructura del endpoint
                    const itemInCart = cart.find(item => item.id === prod.id);
                    
                    if (itemInCart) {
                        if (itemInCart.cantidad < prod.stock_actual) {
                            itemInCart.cantidad++;
                        } else {
                            Swal.fire('Stock Insuficiente', `Solo hay ${prod.stock_actual} unidades disponibles.`, 'warning');
                        }
                    } else {
                        cart.push({
                            id: prod.id,
                            nombre: prod.nombre,
                            precio: parseFloat(prod.precio_venta),
                            cantidad: 1,
                            stock: prod.stock_actual,
                            aplica_igv: prod.aplica_igv
                        });
                    }
                    renderCart();
                } else {
                    Swal.fire('Error', data.message || 'No se pudo agregar el producto', 'error');
                }
            })
            .catch(err => console.error('Error getting product:', err));
    }
    
    window.updateQty = function(id, change) {
        const item = cart.find(i => i.id === id);
        if (item) {
            const newQty = item.cantidad + change;
            if (newQty > 0 && newQty <= item.stock) {
                item.cantidad = newQty;
            } else if (newQty > item.stock) {
                Swal.fire('Stock Insuficiente', `Solo hay ${item.stock} unidades.`, 'warning');
            } else if (newQty === 0) {
                window.removeFromCart(id);
                return;
            }
            renderCart();
        }
    }
    
    window.removeFromCart = function(id) {
        cart = cart.filter(i => i.id !== id);
        renderCart();
    }
    
    function renderCart() {
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = `
                <div class="h-full flex flex-col items-center justify-center text-slate-400 dark:text-slate-500">
                    <div class="w-24 h-24 mb-4 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center shadow-inner">
                        <i class="fa-solid fa-cart-shopping text-4xl opacity-50"></i>
                    </div>
                    <p class="font-medium">Tu carrito está vacío</p>
                    <p class="text-xs mt-1 opacity-70">Agrega productos para comenzar a vender</p>
                </div>
            `;
            cartTotalEl.innerText = 'S/ 0.00';
            btnCobrar.disabled = true;
            btnCobrar.classList.add('opacity-50', 'cursor-not-allowed');
            return;
        }
        
        btnCobrar.disabled = false;
        btnCobrar.classList.remove('opacity-50', 'cursor-not-allowed');
        
        let subtotal = 0;
        let totalIgv = 0;
        
        cartItemsContainer.innerHTML = cart.map(item => {
            const itemSubtotal = item.precio * item.cantidad;
            subtotal += itemSubtotal;
            
            return `
                <div class="flex items-center p-3 mb-3 bg-white/90 dark:bg-slate-800/90 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700/50 hover:shadow-md transition-shadow group animate-[fadeIn_0.3s_ease-out]">
                    <div class="flex-1 pr-2">
                        <div class="text-sm font-bold text-slate-700 dark:text-slate-200 leading-tight mb-1 line-clamp-2">${item.nombre}</div>
                        <div class="text-primary font-black text-sm">S/ ${item.precio.toFixed(2)}</div>
                    </div>
                    <div class="flex items-center gap-1 mx-2 bg-slate-50 dark:bg-slate-900 p-1 rounded-xl border border-slate-200/50 dark:border-slate-700/50">
                        <button onclick="window.updateQty(${item.id}, -1)" class="w-8 h-8 flex items-center justify-center bg-white dark:bg-slate-800 rounded-lg text-slate-500 hover:text-primary hover:shadow-sm transition-all shadow-sm border border-slate-100 dark:border-slate-700"><i class="fa-solid fa-minus text-xs"></i></button>
                        <span class="w-8 text-center font-bold text-slate-700 dark:text-slate-300">${item.cantidad}</span>
                        <button onclick="window.updateQty(${item.id}, 1)" class="w-8 h-8 flex items-center justify-center bg-white dark:bg-slate-800 rounded-lg text-slate-500 hover:text-primary hover:shadow-sm transition-all shadow-sm border border-slate-100 dark:border-slate-700"><i class="fa-solid fa-plus text-xs"></i></button>
                    </div>
                    <div class="text-right w-24 font-black text-lg text-slate-700 dark:text-slate-200">
                        S/ ${itemSubtotal.toFixed(2)}
                    </div>
                    <button onclick="window.removeFromCart(${item.id})" class="ml-3 w-10 h-10 flex items-center justify-center text-slate-400 hover:text-red-500 bg-slate-50 hover:bg-red-50 dark:bg-slate-900 dark:hover:bg-red-900/20 rounded-xl transition-all shadow-inner border border-slate-100 dark:border-slate-700">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            `;
        }).join('');
        
        // Agregar estilo de animación si no existe
        if (!document.getElementById('pos-animations')) {
            const style = document.createElement('style');
            style.id = 'pos-animations';
            style.innerHTML = '@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }';
            document.head.appendChild(style);
        }
        
        // Asumiendo que los precios ya incluyen IGV, y calculamos cuánto de ese total es IGV
        // Formula: PrecioSinIGV = Total / 1.18, IGV = Total - PrecioSinIGV
        const igvRatio = 1 + (IGV_PERCENTAGE / 100);
        const subtotalBase = subtotal / igvRatio;
        totalIgv = subtotal - subtotalBase;
        
        const divSubtotal = document.getElementById('posSubtotal');
        const divIgv = document.getElementById('posIgv');
        
        if(divSubtotal) divSubtotal.innerText = `S/ ${subtotalBase.toFixed(2)}`;
        if(divIgv) divIgv.innerText = `S/ ${totalIgv.toFixed(2)}`;
        
        cartTotalEl.innerText = `S/ ${subtotal.toFixed(2)}`;
        cartTotalEl.dataset.total = subtotal.toFixed(2);
    }
    
    // --- Proceso de Cobro (Checkout) ---
    
    btnCobrar.addEventListener('click', () => {
        if(cart.length === 0) return;
        
        const total = parseFloat(cartTotalEl.dataset.total);
        modalTotalEl.innerText = `S/ ${total.toFixed(2)}`;
        montoRecibidoInput.value = total.toFixed(2);
        
        const tipoComprobanteSelect = document.getElementById('tipoComprobante');
        if (currentClient && currentClient.numero_doc && currentClient.numero_doc.length === 11) {
            tipoComprobanteSelect.value = '01'; // Factura
        } else {
            tipoComprobanteSelect.value = '03'; // Boleta
        }
        
        checkoutModal.classList.add('active');
        actualizarVuelto();
    });
    
    const closeModal = () => checkoutModal.classList.remove('active');
    closeCheckoutBtn.addEventListener('click', closeModal);
    cancelCheckoutBtn.addEventListener('click', closeModal);
    
    metodoPagoSelect.addEventListener('change', (e) => {
        if (e.target.value === 'efectivo') {
            montoRecibidoContainer.style.display = 'block';
            vueltoContainer.style.display = 'flex';
        } else {
            montoRecibidoContainer.style.display = 'none';
            vueltoContainer.style.display = 'none';
        }
    });
    
    function actualizarVuelto() {
        const total = parseFloat(cartTotalEl.dataset.total || 0);
        const recibido = parseFloat(montoRecibidoInput.value || 0);
        const vuelto = recibido - total;
        
        if (vuelto >= 0) {
            vueltoMontoEl.innerText = `S/ ${vuelto.toFixed(2)}`;
            vueltoMontoEl.classList.remove('text-red-500');
            vueltoMontoEl.classList.add('text-emerald-500');
            confirmCheckoutBtn.disabled = false;
        } else {
            vueltoMontoEl.innerText = `Falta S/ ${Math.abs(vuelto).toFixed(2)}`;
            vueltoMontoEl.classList.remove('text-emerald-500');
            vueltoMontoEl.classList.add('text-red-500');
            confirmCheckoutBtn.disabled = true;
        }
    }
    
    montoRecibidoInput.addEventListener('input', actualizarVuelto);
    
    // Enviar venta al backend (BLINDADO Y SIN CONGELAMIENTOS)
    confirmCheckoutBtn.addEventListener('click', () => {
        try {
            confirmCheckoutBtn.disabled = true;
            confirmCheckoutBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Procesando...';

            if (!cart || cart.length === 0) {
                Swal.fire('Carrito vacío', 'Agregue productos antes de cobrar', 'warning');
                confirmCheckoutBtn.disabled = false;
                confirmCheckoutBtn.innerHTML = '<i class="fa-solid fa-check mr-2"></i> Confirmar Pago';
                return;
            }

            let calcSubtotal = 0;
            cart.forEach(item => calcSubtotal += (parseFloat(item.precio || 0) * parseFloat(item.cantidad || 1)));
            const igvPercentage = typeof IGV_PERCENTAGE !== 'undefined' ? IGV_PERCENTAGE : 0;
            const igvRatio = 1 + (igvPercentage / 100);
            const subtotalBase = calcSubtotal / igvRatio;
            const igvAmount = calcSubtotal - subtotalBase;
            const calcTotal = calcSubtotal;

            const tipoComprobanteSelect = document.getElementById('tipoComprobante');
            const formatoImpresionSelect = document.getElementById('formatoImpresion');
            const metodoPagoSelect = document.getElementById('metodoPago');
            const montoRecibidoInput = document.getElementById('montoRecibido');

            let tipoComprobante = tipoComprobanteSelect ? tipoComprobanteSelect.value : '03';
            let serieStr = 'B001';

            // 1. Obtener el cliente activo de forma 100% segura (sin errores de variable)
            const clienteActivo = window.currentClient || (typeof currentClient !== 'undefined' ? currentClient : null);

            if (tipoComprobante === '01') {
                serieStr = 'F001';
                const docNum = clienteActivo ? String(clienteActivo.numero_doc || '') : '';
                if (docNum.length !== 11) {
                    Swal.fire('Atención', 'Para emitir Factura, el cliente debe tener un RUC válido (11 dígitos).', 'warning');
                    confirmCheckoutBtn.disabled = false;
                    confirmCheckoutBtn.innerHTML = '<i class="fa-solid fa-check mr-2"></i> Confirmar Pago';
                    return;
                }
            } else if (tipoComprobante === 'NV') {
                serieStr = 'NV01';
            }

            const printFormat = formatoImpresionSelect ? formatoImpresionSelect.value : 'pdf';
            const clienteIdFinal = (clienteActivo && clienteActivo.id) ? parseInt(clienteActivo.id) : 1;
            const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '';

            // Limpiar comas en el monto recibido si las hubiera (ej: 1160,00 -> 1160.00)
            let montoRecibidoRaw = montoRecibidoInput ? montoRecibidoInput.value.replace(',', '.') : '';
            let montoFinal = parseFloat(montoRecibidoRaw);
            if (isNaN(montoFinal)) montoFinal = calcTotal;

            const saleData = {
                cliente_id: clienteIdFinal,
                tipo_comprobante: tipoComprobante,
                serie: serieStr,
                subtotal: subtotalBase.toFixed(4),
                igv: igvAmount.toFixed(4),
                total: calcTotal.toFixed(2),
                items: cart.map(item => {
                    const itemPrecio = parseFloat(item.precio || 0);
                    const itemCant = parseFloat(item.cantidad || 1);
                    const itemTotal = itemPrecio * itemCant;
                    const itemSubtotal = itemTotal / igvRatio;
                    const itemIgv = itemTotal - itemSubtotal;
                    return {
                        producto_id: item.id,
                        nombre: item.nombre,
                        cantidad: itemCant,
                        precio_unit: itemPrecio,
                        subtotal: itemSubtotal.toFixed(4),
                        igv: itemIgv.toFixed(4),
                        total: itemTotal.toFixed(2),
                        aplica_igv: true,
                        tipo_afectacion_igv: '10'
                    };
                }),
                pagos: [
                    {
                        metodo_pago: (metodoPagoSelect && metodoPagoSelect.value) ? metodoPagoSelect.value : 'efectivo',
                        monto: (metodoPagoSelect && metodoPagoSelect.value === 'efectivo') ? montoFinal : calcTotal
                    }
                ]
            };

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch(`${baseUrl}/pos/procesar-venta`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(saleData)
            })
            .then(res => res.json())
            .then(data => {
                // ==========================================
                // VENTA EXITOSA: TRANSICIÓN SUAVE Y ELEGANTE
                // ==========================================
                if (data.success) {
                    // 1. Cerrar el modal de cobro inmediatamente
                    if (typeof closeModal === 'function') closeModal();

                    // 2. Mostrar la notificación Toast arriba a la derecha
                    const numComp = data.numero_comprobante ? `(${data.numero_comprobante})` : '';
                    if (typeof window.toastPOS === 'function') {
                        window.toastPOS(`¡Venta completada! ${numComp}`, 'success');
                    }

                    // 3. Limpiar el carrito y dejar el POS listo en vivo
                    cart = [];
                    if (typeof renderCart === 'function') renderCart();
                    
                    const prodInput = document.getElementById('productSearch');
                    if (typeof loadProducts === 'function') {
                        loadProducts(prodInput ? prodInput.value : '', typeof currentCategory !== 'undefined' ? currentCategory : 0);
                    }

                    // 4. Restablecer a Cliente Genérico
                    if (typeof window.setClienteGenericoPOS === 'function') {
                        window.setClienteGenericoPOS();
                    }

                    // 5. PAUSA ELEGANTE DE 900ms: Te permite ver la notificación antes de abrir el PDF
                    if (data.venta_id) {
                        const urlDoc = (printFormat === 'pdf') 
                            ? `${baseUrl}/pos/pdf/${data.venta_id}` 
                            : `${baseUrl}/pos/ticket/${data.venta_id}`;

                        setTimeout(() => {
                            window.open(urlDoc, '_blank');
                        }, 900); // 👈 900 milisegundos de respiro visual
                    }

                } else {
                    Swal.fire('Error', data.message || 'Error al procesar la venta', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Problema de conexión con el servidor', 'error');
            })
            .finally(() => {
                confirmCheckoutBtn.disabled = false;
                confirmCheckoutBtn.innerHTML = '<i class="fa-solid fa-check mr-2"></i> Confirmar Pago';
            });

        } catch (errorFatal) {
            console.error('Error procesando venta:', errorFatal);
            Swal.fire('Atención', errorFatal.message, 'error');
            confirmCheckoutBtn.disabled = false;
            confirmCheckoutBtn.innerHTML = '<i class="fa-solid fa-check mr-2"></i> Confirmar Pago';
        }
    });
    
// ========================================================
    // BUSCADOR INTELIGENTE DE CLIENTES (TECLADO + RENIEC/SUNAT)
    // ========================================================
    
    let selectedDropdownIndex = -1; // Índice para navegación con flechas ↑ / ↓

    const clientSearchResults = document.getElementById('clientSearchResults');

    // 1. Función para seleccionar un cliente en el POS
    window.selectClient = function(id, doc, nombre) {
        const clienteId = parseInt(id) || 1;

        // Guardar en input oculto y en window
        const hiddenInput = document.getElementById('selectedClientId');
        if (hiddenInput) hiddenInput.value = clienteId;

        window.currentClient = { id: clienteId, numero_doc: String(doc || ''), razon_social: nombre };
        currentClient = window.currentClient;

        // Actualizar tarjeta en pantalla
        const elName = document.getElementById('posClientName');
        const elDoc = document.getElementById('posClientDoc');
        if (elName) elName.textContent = nombre;
        if (elDoc) elDoc.textContent = (doc && String(doc).length === 11 ? 'RUC: ' : 'DNI: ') + (doc || '00000000');

        // Factura automática si es RUC, Boleta si es DNI
        const tipoSelect = document.getElementById('tipoComprobante');
        if (tipoSelect) {
            tipoSelect.value = (doc && String(doc).length === 11) ? '01' : '03';
        }

        if (clientSearchInput) clientSearchInput.value = '';
        if (clientSearchResults) clientSearchResults.classList.add('hidden');
        selectedDropdownIndex = -1;
    };


    // Función global de notificaciones Toast no intrusivas
    window.toastPOS = function(title, icon = 'success') {
        if (typeof Swal !== 'undefined') {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
            Toast.fire({ icon, title });
        }
    };
    var toastPOS = window.toastPOS;

    
    window.setClienteGenericoPOS = function() {
        const genericoId = (typeof CLIENTE_GENERICO !== 'undefined' && CLIENTE_GENERICO) ? CLIENTE_GENERICO.id : 1;
        window.selectClient(genericoId, '00000000', 'CLIENTE GENÉRICO');
        if (typeof window.toastPOS === 'function') {
            window.toastPOS('Cliente Genérico seleccionado', 'info');
        }
    };

    // 3. Búsqueda inteligente: decide si ya existe en la BD o si es nuevo para RENIEC/SUNAT
    window.buscarClienteDirectoPOS = function() {
        if (!clientSearchInput) return;
        const val = clientSearchInput.value.trim();
        if (!val) return;

        // Si son 8 dígitos (DNI) o 11 dígitos (RUC)
        if (/^\d{8}$/.test(val) || /^\d{11}$/.test(val)) {
            const tipoDoc = val.length === 8 ? '1' : '6';
            const btn = document.getElementById('btnLookupClientDirect');
            const icon = document.getElementById('iconLookupClientDirect');

            if (btn) btn.disabled = true;
            if (icon) icon.className = 'fa-solid fa-spinner fa-spin';

            // PASO A: Verificar si YA EXISTE en la base de datos local
            fetch(`${BASE_URL}/api/clientes/buscar?q=${encodeURIComponent(val)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(res => {
                const clientes = res.clientes || [];
                // Buscar coincidencia exacta por número de documento
                const clienteExistente = clientes.find(c => String(c.numero_doc).trim() === val);

                if (clienteExistente) {
                    // ¡YA ESTÁ REGISTRADO! Seleccionarlo directamente (sin intentar duplicarlo)
                    window.selectClient(clienteExistente.id, clienteExistente.numero_doc, clienteExistente.razon_social);
                    toastPOS(`Cliente encontrado en sistema: ${clienteExistente.razon_social}`, 'success');
                    if (btn) btn.disabled = false;
                    if (icon) icon.className = 'fa-solid fa-magnifying-glass';
                } else {
                    // PASO B: ES CLIENTE NUEVO -> Consultar a RENIEC / SUNAT
                    consultarYGuardarNuevoCliente(tipoDoc, val, btn, icon);
                }
            })
            .catch(() => {
                consultarYGuardarNuevoCliente(tipoDoc, val, btn, icon);
            });

        } else {
            // Si escribió texto o nombre y presionó Enter sin seleccionar con flechas
            const firstItem = clientSearchResults ? clientSearchResults.querySelector('.client-result-item') : null;
            if (firstItem) {
                firstItem.click();
            } else {
                toastPOS('Ingrese 8 dígitos (DNI) o 11 dígitos (RUC)', 'info');
            }
        }
    };

    // Función auxiliar para registrar clientes nuevos de RENIEC/SUNAT
    function consultarYGuardarNuevoCliente(tipoDoc, numero, btn, icon) {
        fetch(`${BASE_URL}/api/clientes/buscar-documento?tipo_doc=${tipoDoc}&numero=${encodeURIComponent(numero)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(apiRes => {
            if (apiRes.success && apiRes.data) {
                const nombre = apiRes.data.razon_social || apiRes.data.nombre || '';
                const direccion = apiRes.data.direccion || '';

                if (!nombre) {
                    toastPOS('No se encontró el nombre en RENIEC/SUNAT', 'error');
                    return;
                }

                // Guardar en base de datos una sola vez
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                fetch(`${BASE_URL}/api/clientes/guardar`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ tipo_doc: tipoDoc, numero_doc: numero, razon_social: nombre, direccion: direccion })
                })
                .then(r => r.json())
                .then(saveRes => {
                    const nuevoId = saveRes.cliente_id || (saveRes.data ? saveRes.data.id : null);
                    window.selectClient(nuevoId, numero, nombre);
                    toastPOS(`Nuevo cliente registrado: ${nombre}`, 'success');
                })
                .catch(() => {
                    toastPOS('Error al registrar nuevo cliente', 'error');
                });

            } else {
                toastPOS(apiRes.message || 'Documento no encontrado en RENIEC/SUNAT', 'error');
            }
        })
        .catch(() => {
            toastPOS('Error al conectar con RENIEC/SUNAT', 'error');
        })
        .finally(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.className = 'fa-solid fa-magnifying-glass';
        });
    }

    // 4. Búsqueda reactiva mientras escribe (Muestra desplegable de clientes registrados)
    if (clientSearchInput) {
        clientSearchInput.addEventListener('input', debounce((e) => {
            const q = e.target.value.trim();
            selectedDropdownIndex = -1;

            if (q.length < 2) {
                if (clientSearchResults) clientSearchResults.classList.add('hidden');
                return;
            }

            fetch(`${BASE_URL}/api/clientes/buscar?q=${encodeURIComponent(q)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                const clientes = data.clientes || [];
                if (data.success && clientes.length > 0) {
                    clientSearchResults.innerHTML = clientes.map((c, idx) => `
                        <div class="client-result-item p-2.5 hover:bg-sky-50 dark:hover:bg-slate-700 cursor-pointer text-xs border-b border-slate-100 dark:border-slate-700/50 last:border-0 transition-colors flex items-center justify-between" 
                             data-index="${idx}"
                             onclick="window.selectClient(${c.id}, '${c.numero_doc}', '${c.razon_social.replace(/'/g, "\\'")}')">
                            <div>
                                <div class="font-bold text-slate-800 dark:text-slate-100">${c.razon_social}</div>
                                <div class="text-[10px] text-slate-500">${c.numero_doc || '-'}</div>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded ${String(c.numero_doc).length === 11 ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600'}">
                                ${String(c.numero_doc).length === 11 ? 'RUC' : 'DNI'}
                            </span>
                        </div>
                    `).join('');
                    clientSearchResults.classList.remove('hidden');
                } else {
                    clientSearchResults.innerHTML = '<div class="p-3 text-xs text-slate-500 text-center">No registrado en el sistema. Presiona la lupa 🔍 o Enter para buscar en RENIEC/SUNAT.</div>';
                    clientSearchResults.classList.remove('hidden');
                }
            });
        }, 250));

        // 5. NAVEGACIÓN CON FLECHAS (↑ / ↓) Y TECLA ENTER
        clientSearchInput.addEventListener('keydown', (e) => {
            const items = clientSearchResults ? clientSearchResults.querySelectorAll('.client-result-item') : [];

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (items.length > 0) {
                    selectedDropdownIndex = (selectedDropdownIndex + 1) % items.length;
                    highlightItem(items, selectedDropdownIndex);
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (items.length > 0) {
                    selectedDropdownIndex = (selectedDropdownIndex - 1 + items.length) % items.length;
                    highlightItem(items, selectedDropdownIndex);
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                // Si seleccionó uno de la lista con las flechitas:
                if (selectedDropdownIndex >= 0 && items[selectedDropdownIndex]) {
                    items[selectedDropdownIndex].click();
                } else {
                    // Si presionó enter directo sobre el texto:
                    window.buscarClienteDirectoPOS();
                }
            } else if (e.key === 'Escape') {
                if (clientSearchResults) clientSearchResults.classList.add('hidden');
                selectedDropdownIndex = -1;
            }
        });
    }

    function highlightItem(items, index) {
        items.forEach((it, i) => {
            if (i === index) {
                it.classList.add('bg-sky-500', 'text-white');
                it.classList.remove('hover:bg-sky-50', 'dark:hover:bg-slate-700');
                it.scrollIntoView({ block: 'nearest' });
            } else {
                it.classList.remove('bg-sky-500', 'text-white');
                it.classList.add('hover:bg-sky-50', 'dark:hover:bg-slate-700');
            }
        });
    }

    // Cerrar buscador al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (clientSearchInput && !clientSearchInput.contains(e.target) && clientSearchResults && !clientSearchResults.contains(e.target)) {
            clientSearchResults.classList.add('hidden');
            selectedDropdownIndex = -1;
        }
    });

    // --- Utilidades ---
    function debounce(func, timeout = 300){
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => { func.apply(this, args); }, timeout);
        };
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value || '';
        return element.innerHTML;
    }
});
