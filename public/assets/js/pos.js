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

document.addEventListener('turbo:load', () => {
    // Evitar doble inicialización si turbo dispara varias veces
    if (window.posInitialized) return;
    
    const productGrid = document.getElementById('productGrid');
    if (!productGrid) {
        window.posInitialized = false; 
        return; // No estamos en la vista del POS
    }
    window.posInitialized = true;
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
    
    // Enviar venta al backend
    confirmCheckoutBtn.addEventListener('click', () => {
        confirmCheckoutBtn.disabled = true;
        confirmCheckoutBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Procesando...';
        
        let calcSubtotal = 0;
        cart.forEach(item => calcSubtotal += (item.precio * item.cantidad));
        const igvRatio = 1 + (IGV_PERCENTAGE / 100);
        const subtotalBase = calcSubtotal / igvRatio;
        const igvAmount = calcSubtotal - subtotalBase;
        const calcTotal = calcSubtotal;

        const tipoComprobanteSelect = document.getElementById('tipoComprobante');
        const formatoImpresionSelect = document.getElementById('formatoImpresion');
        
        let tipoComprobante = tipoComprobanteSelect.value;
        let serieStr = 'B001';
        if (tipoComprobante === '01') {
            serieStr = 'F001';
            // Simple validation: Factura usually requires RUC (11 digits)
            if (!currentClient || !currentClient.numero_doc || currentClient.numero_doc.length !== 11) {
                Swal.fire('Atención', 'Para emitir Factura, el cliente debe tener un RUC válido (11 dígitos).', 'warning');
                confirmCheckoutBtn.disabled = false;
                confirmCheckoutBtn.innerHTML = '<i class="fa-solid fa-check mr-2"></i> Confirmar Pago';
                return;
            }
        }
        if (tipoComprobante === 'NV') serieStr = 'NV01';
        
        const printFormat = formatoImpresionSelect.value;

        const saleData = {
            cliente_id: currentClient ? currentClient.id : null,
            tipo_comprobante: tipoComprobante,
            serie: serieStr,
            subtotal: subtotalBase.toFixed(4),
            igv: igvAmount.toFixed(4),
            total: calcTotal.toFixed(2),
            items: cart.map(item => {
                const itemTotal = item.precio * item.cantidad;
                const itemSubtotal = itemTotal / igvRatio;
                const itemIgv = itemTotal - itemSubtotal;
                return {
                    producto_id: item.id,
                    nombre: item.nombre,
                    cantidad: item.cantidad,
                    precio_unit: item.precio,
                    subtotal: itemSubtotal.toFixed(4),
                    igv: itemIgv.toFixed(4),
                    total: itemTotal.toFixed(2),
                    aplica_igv: true,
                    tipo_afectacion_igv: '10'
                };
            }),
            pagos: [
                {
                    metodo_pago: metodoPagoSelect.value,
                    monto: metodoPagoSelect.value === 'efectivo' ? parseFloat(montoRecibidoInput.value) : calcTotal
                }
            ]
        };
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch(`${BASE_URL}/pos/procesar-venta`, {
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
            if (data.success) {
                closeModal();
                Swal.fire({
                    icon: 'success',
                    title: 'Venta Completada',
                    text: 'El comprobante ha sido generado.',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    // Limpiar carrito
                    cart = [];
                    renderCart();
                    loadProducts(productSearchInput.value, currentCategory); // Refrescar stock
                    
                    // Imprimir comprobante según formato elegido
                    if (data.venta_id) {
                        if (printFormat === 'pdf') {
                            window.open(`${BASE_URL}/pos/pdf/${data.venta_id}`, '_blank');
                        } else {
                            window.open(`${BASE_URL}/pos/ticket/${data.venta_id}`, '_blank', 'width=400,height=600');
                        }
                    }
                });
            } else {
                Swal.fire('Error', data.message || 'Error al procesar la venta', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Problema de conexión', 'error');
        })
        .finally(() => {
            confirmCheckoutBtn.disabled = false;
            confirmCheckoutBtn.innerHTML = '<i class="fa-solid fa-check mr-2"></i> Confirmar Pago';
        });
    });
    
    // --- Lógica de Clientes ---
    const btnNuevoCliente = document.getElementById('btnNuevoCliente');
    const btnClienteGenerico = document.getElementById('btnClienteGenerico');
    const clientModal = document.getElementById('clientModal');
    const closeClientModal = document.getElementById('closeClientModal');
    const saveClientBtn = document.getElementById('saveClientBtn');
    const clientSearchResults = document.getElementById('clientSearchResults');
    const lookupClientBtn = document.getElementById('lookupClientBtn');
    const newClientTipoDoc = document.getElementById('newClientTipoDoc');
    const newClientNumero = document.getElementById('newClientNumero');
    const newClientNombre = document.getElementById('newClientNombre');

    if (btnNuevoCliente) btnNuevoCliente.addEventListener('click', () => clientModal.classList.add('active'));
    if (closeClientModal) closeClientModal.addEventListener('click', () => clientModal.classList.remove('active'));

    function updateDocumentConstraints() {
        const isDni = newClientTipoDoc.value === '1';
        newClientNumero.maxLength = isDni ? 8 : 11;
        newClientNumero.placeholder = isDni ? '8 dígitos' : '11 dígitos';
    }

    if (newClientTipoDoc) {
        newClientTipoDoc.addEventListener('change', updateDocumentConstraints);
        updateDocumentConstraints();
    }

    if (lookupClientBtn) lookupClientBtn.addEventListener('click', () => {
        const tipoDoc = newClientTipoDoc.value;
        const numero = newClientNumero.value.trim();
        const longitud = tipoDoc === '1' ? 8 : 11;

        if (!new RegExp(`^\\d{${longitud}}$`).test(numero)) {
            Swal.fire('Documento inválido', `Ingrese exactamente ${longitud} dígitos.`, 'warning');
            return;
        }

        lookupClientBtn.disabled = true;
        fetch(`${BASE_URL}/api/clientes/buscar-documento?tipo_doc=${tipoDoc}&numero=${encodeURIComponent(numero)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                Swal.fire('Sin resultados', data.message || 'No se encontró información.', 'info');
                return;
            }
            const nombre = data.data.razon_social || data.data.nombre || '';
            newClientNombre.value = nombre;
            Swal.fire('Datos encontrados', 'El nombre fue completado automáticamente.', 'success');
        })
        .catch(() => Swal.fire('Error', 'No se pudo consultar el documento.', 'error'))
        .finally(() => { lookupClientBtn.disabled = false; });
    });

    if (btnClienteGenerico) btnClienteGenerico.addEventListener('click', () => {
        currentClient = CLIENTE_GENERICO;
        clientSearchInput.value = '';
        Swal.fire('Seleccionado', 'Cliente Genérico seleccionado', 'success');
    });

    if (saveClientBtn) saveClientBtn.addEventListener('click', () => {
        const tipo_doc = document.getElementById('newClientTipoDoc').value;
        const numero_doc = document.getElementById('newClientNumero').value;
        const razon_social = document.getElementById('newClientNombre').value;

        if (!numero_doc || !razon_social) {
            Swal.fire('Error', 'Complete los campos obligatorios', 'warning');
            return;
        }

        saveClientBtn.disabled = true;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch(`${BASE_URL}/api/clientes/guardar`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ tipo_doc, numero_doc, razon_social })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                clientModal.classList.remove('active');
                currentClient = { id: data.cliente_id, numero_doc, razon_social };
                clientSearchInput.value = razon_social;
                Swal.fire('Éxito', 'Cliente guardado y seleccionado', 'success');
            } else {
                Swal.fire('Error', data.message || 'Error al guardar cliente', 'error');
            }
        })
        .finally(() => saveClientBtn.disabled = false);
    });

    if (clientSearchInput) clientSearchInput.addEventListener('input', debounce((e) => {
        const q = e.target.value;
        if (q.length < 2) {
            clientSearchResults.classList.add('hidden');
            return;
        }
        fetch(`${BASE_URL}/api/clientes/buscar?q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.clientes.length > 0) {
                clientSearchResults.innerHTML = data.clientes.map(c => `
                    <div class="p-2 hover:bg-gray-100 dark:hover:bg-slate-700 cursor-pointer text-sm" onclick="window.selectClient(${c.id}, '${c.numero_doc}', '${c.razon_social.replace(/'/g, "\\'")}')">
                        <div class="font-bold">${c.razon_social}</div>
                        <div class="text-xs text-gray-500">${c.numero_doc}</div>
                    </div>
                `).join('');
                clientSearchResults.classList.remove('hidden');
            } else {
                clientSearchResults.innerHTML = '<div class="p-2 text-sm text-gray-500">No se encontraron clientes</div>';
                clientSearchResults.classList.remove('hidden');
            }
        });
    }, 300));

    window.selectClient = function(id, doc, nombre) {
        currentClient = { id, numero_doc: doc, razon_social: nombre };
        clientSearchInput.value = nombre;
        clientSearchResults.classList.add('hidden');
    };

    // Cierra buscador al hacer click fuera
    document.addEventListener('click', (e) => {
        if (clientSearchInput && !clientSearchInput.contains(e.target) && !clientSearchResults.contains(e.target)) {
            clientSearchResults.classList.add('hidden');
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
