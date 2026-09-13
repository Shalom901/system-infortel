<!DOCTYPE html>
<html lang='es' class='<?= (($_SESSION['theme'] ?? 'light') === 'dark') ? 'dark' : '' ?>'>
<head>
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230ea5e9'/%3E%3Cpath d='M18 21h28v24H18z' fill='white'/%3E%3Cpath d='M23 27h18M23 33h5m4 0h9m-18 6h18' stroke='%230ea5e9' stroke-width='3' stroke-linecap='round'/%3E%3C/svg%3E">
    <title>POS | Facturación</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src='https://cdn.tailwindcss.com'></script>
    <!-- Hotwire Turbo for SPA feeling without reloads -->
    <script type="module" src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.4/dist/turbo.es2017-esm.js"></script>
    <script>
        tailwind.config = { 
            darkMode: 'class', 
            theme: { 
                extend: { 
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { primary: '#0ea5e9' } 
                } 
            } 
        }
    </script>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>
    <style>
        /* Modern Scrollbar for POS */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark ::-webkit-scrollbar-thumb { background: #475569; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .modal { display: none; opacity: 0; transition: opacity 0.3s ease; }
        .modal.active { display: flex; opacity: 1; }
        
        /* Glassmorphism helpers */
        .glass {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .dark .glass {
            background: rgba(30, 41, 59, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        body { font-family: 'Inter', sans-serif; }
    </style>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Sincronizar Modo Oscuro con el Dashboard -->
    <script>
        if (localStorage.getItem('theme') === 'dark' || 
            (!('theme' in localStorage) && <?= (($_SESSION['theme'] ?? 'light') === 'dark') ? 'true' : 'false' ?>)) {
            document.documentElement.classList.add('dark');
        } else if (localStorage.getItem('theme') === 'light') {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class='bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-900 dark:to-slate-950 h-screen flex flex-col overflow-hidden text-slate-800 dark:text-slate-200 antialiased'>
    
    <!-- Topbar (Glassmorphism) -->
    <header class='h-16 glass shadow-sm flex items-center justify-between px-6 shrink-0 z-20 relative'>
        <div class='flex items-center gap-5'>
            <a href='<?= baseUrl('dashboard') ?>' class='w-10 h-10 flex items-center justify-center bg-slate-100 dark:bg-slate-800 rounded-full text-slate-500 hover:text-primary hover:bg-sky-50 dark:hover:bg-sky-900/30 transition-all duration-300'>
                <i class='fa-solid fa-arrow-left text-lg'></i>
            </a>
            <h1 class='font-bold text-xl tracking-tight flex items-center gap-3'>
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-blue-600 flex items-center justify-center text-white shadow-lg shadow-primary/30">
                    <i class='fa-solid fa-cash-register text-lg'></i>
                </div>
                <span>Punto de Venta</span>
            </h1>
        </div>
        <div class='flex items-center gap-6'>
            <span class='px-4 py-1.5 bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800/50 dark:text-emerald-400 rounded-full text-sm font-semibold flex items-center shadow-sm'>
                <div class='w-2 h-2 rounded-full bg-emerald-500 mr-2.5 animate-[pulse_1.5s_ease-in-out_infinite]'></div> Caja Abierta
            </span>
            <div class='flex items-center gap-3 pl-4 border-l border-slate-200 dark:border-slate-700'>
                <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-slate-300 to-slate-200 dark:from-slate-700 dark:to-slate-600 flex items-center justify-center text-slate-600 dark:text-slate-300 font-bold shadow-inner">
                    <?= substr($_SESSION['user_nombre'] ?? 'V', 0, 1) ?>
                </div>
                <div class='text-sm font-semibold'><?= htmlspecialchars($_SESSION['user_nombre'] ?? 'Vendedor') ?></div>
            </div>
        </div>
    </header>

    <!-- Main Layout -->
    <div class='flex-1 flex overflow-hidden p-3 gap-3 z-10'>
        
        <!-- Left Column: Cart (40%) -->
        <div class='w-2/5 flex flex-col glass rounded-3xl shadow-xl overflow-hidden relative border border-white/50 dark:border-slate-700/50 z-10'>
            
            <!-- Selección de Cliente Rápida (Sin Modales) -->
            <div class='p-4 border-b border-slate-200/50 dark:border-slate-700/50 bg-white/40 dark:bg-slate-800/40'>
                <!-- Buscador con botón de búsqueda directa -->
                 <input type="hidden" id="selectedClientId" value="1">
                <div class='relative group flex gap-2'>
                    <div class="relative flex-1">
                        <i class='fa-solid fa-user absolute left-4 top-3.5 text-slate-400 group-focus-within:text-primary transition-colors'></i>
                        <input type='text' id='clientSearch' autocomplete='off' placeholder='DNI, RUC o Nombre del cliente...' 
                               class='w-full pl-11 pr-4 py-2.5 bg-white/90 dark:bg-slate-900/90 backdrop-blur-sm border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 text-xs font-semibold transition-all shadow-inner'>
                        <div id='clientSearchResults' class='absolute z-20 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl mt-1.5 hidden max-h-56 overflow-y-auto custom-scrollbar'></div>
                    </div>
                    <!-- Botón Lupa Directo -->
                    <button type="button" id="btnLookupClientDirect" onclick="buscarClienteDirectoPOS()" 
                            class="px-3.5 py-2.5 bg-primary hover:bg-sky-600 text-white rounded-xl shadow-sm transition-all flex items-center justify-center shrink-0 hover:-translate-y-0.5" 
                            title="Buscar DNI o RUC en vivo (o presiona Enter)">
                        <i class="fa-solid fa-magnifying-glass" id="iconLookupClientDirect"></i>
                    </button>
                </div>
                
                <!-- Tarjeta visual del Cliente Activo en la Venta -->
                <div class='flex items-center justify-between mt-2.5 px-3 py-2 bg-sky-50/80 dark:bg-sky-950/30 border border-sky-200/60 dark:border-sky-800/40 rounded-xl'>
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 text-xs">
                            <i class="fa-solid fa-user-check"></i>
                        </span>
                        <div class="min-w-0">
                            <p id="posClientName" class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">CLIENTE GENÉRICO</p>
                            <p id="posClientDoc" class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">DNI: 00000000</p>
                        </div>
                    </div>
                    <!-- Botón para volver a Genérico con 1 clic -->
                    <button type="button" onclick="setClienteGenericoPOS()" class="text-slate-400 hover:text-red-500 p-1 rounded-lg transition-colors text-xs" title="Restablecer a Cliente Genérico">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>
            </div>

            <!-- Cart Items -->
            <div class='flex-1 overflow-y-auto p-3 custom-scrollbar bg-slate-50/50 dark:bg-slate-900/30' id='cartItems'>
                <!-- Placeholder for empty cart -->
                <div class='h-full flex flex-col items-center justify-center text-slate-400 dark:text-slate-500'>
                    <div class="w-24 h-24 mb-4 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center shadow-inner">
                        <i class='fa-solid fa-cart-shopping text-4xl opacity-50'></i>
                    </div>
                    <p class="font-medium">Tu carrito está vacío</p>
                    <p class="text-xs mt-1 opacity-70">Agrega productos para comenzar a vender</p>
                </div>
            </div>

            <!-- Totals & Pay -->
            <div class='p-6 bg-white/80 dark:bg-slate-800/80 backdrop-blur-md border-t border-slate-200/50 dark:border-slate-700/50 relative overflow-hidden'>
                <!-- Decorative background blob -->
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-primary/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class='flex justify-between items-end mb-5 relative z-10'>
                    <span class='text-lg font-bold text-slate-600 dark:text-slate-400'>TOTAL:</span>
                    <span class='text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-primary to-blue-600 drop-shadow-sm' id="cartTotal">S/ 0.00</span>
                </div>
                
                <button id="btnCobrar" class='w-full py-4.5 bg-gradient-to-r from-emerald-400 to-emerald-600 hover:from-emerald-500 hover:to-emerald-700 text-white text-lg font-bold rounded-2xl shadow-xl shadow-emerald-500/30 transition-all duration-300 transform hover:-translate-y-1 hover:shadow-emerald-500/40 active:translate-y-0 active:shadow-emerald-500/20 flex items-center justify-center relative overflow-hidden group'>
                    <!-- Shine effect on hover -->
                    <div class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent group-hover:animate-[shimmer_1.5s_infinite]"></div>
                    <i class='fa-solid fa-bolt mr-2.5 text-xl'></i> COBRAR (F3)
                </button>
            </div>
        </div>

        <!-- Right Column: Products (60%) -->
        <div class='w-3/5 flex flex-col relative'>
            <!-- Search & Filters -->
            <div class='px-2 pb-4 pt-1'>
                <div class='relative mb-4 group'>
                    <i class='fa-solid fa-search absolute left-5 top-4 text-slate-400 text-lg group-focus-within:text-primary transition-colors'></i>
                    <input type='text' id='productSearch' placeholder='Buscar producto (Código, Barras, Nombre)... [F2]' class='w-full pl-14 pr-14 py-3.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-lg shadow-slate-200/20 dark:shadow-none rounded-2xl focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 text-lg font-semibold transition-all'>
                    <button class='absolute right-3 top-2.5 w-10 h-10 flex items-center justify-center bg-slate-100 dark:bg-slate-700 rounded-xl text-slate-500 hover:text-primary hover:bg-sky-50 dark:hover:bg-sky-900/30 transition-all'>
                        <i class='fa-solid fa-barcode'></i>
                    </button>
                </div>
                
                <!-- Categories -->
                <div class='flex gap-2 overflow-x-auto pb-2 custom-scrollbar px-1' id="categoryFilter">
                    <button data-id="0" class='cat-btn px-5 py-2 bg-gradient-to-r from-primary to-blue-500 text-white rounded-xl text-sm font-bold whitespace-nowrap shadow-md shadow-primary/20 transition-transform active:scale-95'>Todos</button>
                    <?php foreach ($categorias ?? [] as $cat): ?>
                    <button data-id="<?= $cat['id'] ?>" class='cat-btn px-5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-primary/50 dark:hover:border-primary/50 text-slate-600 dark:text-slate-300 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-200 hover:shadow-md active:scale-95'>
                        <?= htmlspecialchars($cat['nombre']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Product Grid -->
            <div class='flex-1 px-2 pb-4 overflow-y-auto custom-scrollbar'>
                <div class='grid grid-cols-3 xl:grid-cols-4 gap-4' id="productGrid">
                    <!-- Products will be rendered here by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modals go here (unchanged structure but updating styles inside them to match) -->
    
    <div id='clientModal' class='modal fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm items-center justify-center'>
        <div class='bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-all duration-300 border border-slate-200 dark:border-slate-700' id="clientModalContent">
            <div class='px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50'>
                <h3 class='text-xl font-bold tracking-tight'>Nuevo Cliente</h3>
                <button id='closeClientModal' class='w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all'>
                    <i class='fa-solid fa-xmark'></i>
                </button>
            </div>
            <div class='p-6 space-y-4'>
                <div class='flex gap-4'>
                    <div class='flex-1'>
                        <label class='block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5'>Tipo Doc.</label>
                        <select id='newClientTipoDoc' class='w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 font-medium transition-all'>
                            <option value='1'>DNI</option>
                            <option value='6'>RUC</option>
                        </select>
                    </div>
                    <div class='flex-[2]'>
                        <label class='block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5'>Número</label>
                        <div class='flex gap-2'>
                            <input type='text' id='newClientNumero' inputmode='numeric' autocomplete='off' class='min-w-0 flex-1 px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 font-medium transition-all'>
                            <button type='button' id='lookupClientBtn' class='px-4 py-3 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 rounded-xl font-semibold transition-all' title='Buscar documento'>
                                <i class='fa-solid fa-magnifying-glass'></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div>
                    <label class='block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5'>Nombre / Razón Social</label>
                    <input type='text' id='newClientNombre' class='w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 font-medium transition-all'>
                </div>
                <button id='saveClientBtn' class='w-full py-3.5 bg-primary hover:bg-sky-600 text-white rounded-xl font-bold text-lg shadow-lg shadow-primary/30 transition-all transform hover:-translate-y-0.5 mt-4'>
                    <i class='fa-solid fa-save mr-2'></i> Guardar Cliente
                </button>
            </div>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div id="checkoutModal" class="modal fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm items-center justify-center">
        <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-all duration-300 border border-slate-200 dark:border-slate-700" id="checkoutModalContent">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
                <h3 class="text-xl font-bold tracking-tight">Procesar Pago</h3>
                <button id="closeCheckout" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 transition-all">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <div class="p-6">
                <div class="text-center mb-6 p-6 bg-slate-50 dark:bg-slate-900 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-inner">
                    <div class="text-sm font-semibold text-slate-500 dark:text-slate-400 mb-1 uppercase tracking-wider">Monto a Cobrar</div>
                    <div class="text-5xl font-black text-transparent bg-clip-text bg-gradient-to-r from-primary to-blue-600" id="modalTotal">S/ 0.00</div>
                </div>
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Comprobante</label>
                            <select id="tipoComprobante" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 font-medium transition-all">
                                <option value="03">Boleta</option>
                                <option value="01">Factura</option>
                                <option value="NV">Nota de Venta / Recibo interno</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Impresión</label>
                            <select id="formatoImpresion" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 font-medium transition-all">
                                <option value="ticket">Ticket (80mm)</option>
                                <option value="pdf">Documento (A4)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Método de Pago</label>
                        <select id="metodoPago" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 font-medium transition-all">
                            <option value="efectivo">Efectivo</option>
                            <option value="yape">Yape</option>
                            <option value="plin">Plin</option>
                            <option value="tarjeta">Tarjeta (POS)</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                    </div>
                    
                    <div id="montoRecibidoContainer">
                        <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Efectivo Recibido</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-slate-400 font-bold">S/</span>
                            <input type="number" id="montoRecibido" step="0.01" class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 font-bold text-xl transition-all">
                        </div>
                    </div>
                    
                    <div id="vueltoContainer" class="flex justify-between items-center p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800/50 rounded-xl hidden">
                        <span class="font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider text-sm">Vuelto:</span>
                        <span class="font-black text-2xl text-emerald-600 dark:text-emerald-400" id="vueltoMonto">S/ 0.00</span>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-5 border-t border-slate-100 dark:border-slate-700 flex gap-4 bg-slate-50/50 dark:bg-slate-800/50">
                <button id="cancelCheckout" class="flex-1 px-4 py-3.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl font-bold hover:bg-slate-300 dark:hover:bg-slate-600 transition-all">Cancelar</button>
                <button id="confirmCheckout" class="flex-[2] px-4 py-3.5 bg-gradient-to-r from-primary to-blue-600 text-white rounded-xl font-bold hover:from-sky-500 hover:to-blue-500 transition-all shadow-lg shadow-primary/30 flex items-center justify-center transform hover:-translate-y-0.5">
                    <i class="fa-solid fa-check mr-2"></i> Confirmar Pago
                </button>
            </div>
        </div>
    </div>

    <style>
        /* Shimmer animation for the pay button */
        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }
    </style>

    <script>
        // Animations for modals
        document.querySelectorAll('.modal').forEach(modal => {
            const observer = new MutationObserver(mutations => {
                mutations.forEach(m => {
                    if(m.attributeName === 'class'){
                        const content = modal.querySelector('div');
                        if(modal.classList.contains('active')){
                            content.classList.remove('scale-95', 'opacity-0');
                            content.classList.add('scale-100', 'opacity-100');
                        } else {
                            content.classList.remove('scale-100', 'opacity-100');
                            content.classList.add('scale-95', 'opacity-0');
                        }
                    }
                });
            });
            observer.observe(modal, {attributes: true});
        });

        var BASE_URL = '<?= baseUrl() ?>';
        var IGV_PERCENTAGE = 0;
        var CLIENTE_GENERICO = <?= json_encode($clienteGenerico ?? null) ?>;
    </script>
    <script src="<?= baseUrl('assets/js/pos.js') ?>"></script>
</body>
</html>
