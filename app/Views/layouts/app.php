<!DOCTYPE html>
<html lang="es" class="<?= ($_SESSION['theme'] ?? 'light') === 'dark' ? 'dark' : '' ?>">
<script>
    // Sistema de Hidratación de Tema Resiliente a Turbo SPA
    function initThemeSystem() {
        var stored = localStorage.getItem('theme');
        var isDark = false;
        
        if (stored === 'dark' || stored === 'light') {
            isDark = (stored === 'dark');
        } else {
            // Fallback al estado de la sesión si no hay localStorage
            isDark = <?= ($_SESSION['theme'] ?? 'light') === 'dark' ? 'true' : 'false' ?>;
        }

        if (isDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    // 1. Ejecución inmediata (para Hard Reloads)
    initThemeSystem();

    // 2. Re-hidratación tras manipulaciones del DOM por Turbo
    document.addEventListener('turbo:load', initThemeSystem);
    document.addEventListener('turbo:render', initThemeSystem);
</script>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (($title ?? '') === 'Caja'): ?>
    <meta name="turbo-cache-control" content="no-cache">
    <meta name="turbo-visit-control" content="reload">
    <?php endif; ?>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230ea5e9'/%3E%3Cpath d='M18 21h28v24H18z' fill='white'/%3E%3Cpath d='M23 27h18M23 33h5m4 0h9m-18 6h18' stroke='%230ea5e9' stroke-width='3' stroke-linecap='round'/%3E%3C/svg%3E">
    <title><?= htmlspecialchars($title ?? 'Dashboard') ?> | <?= defined('APP_NAME') ? APP_NAME : 'FactuPucallpa' ?></title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Hotwire Turbo for SPA feeling without reloads -->
    <script type="module" src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.4/dist/turbo.es2017-esm.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#0ea5e9', 50: '#f0f9ff', 100: '#e0f2fe', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1' },
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <style>

        /* =========================================================
       SISTEMA DE DISEÑO INFORTEL COMP: DESIGN TOKENS
       ========================================================= */
        :root {
            /* -----------------------------------------
            LIGHT MODE (Off-White Foundation)
            ----------------------------------------- */
            --color-bg-body: #f8fafc;       /* Fondo general para evitar fatiga visual (Slate 50) */
            --color-bg-container: #ffffff;  /* Tarjetas y contenedores elevados (Blanco puro) */
            --color-bg-sidebar: #ffffff;    /* Sidebar limpio y corporativo */

            --color-text-primary: #1e293b;  /* Texto principal de alto contraste (Slate 800) */
            --color-text-muted: #64748b;    /* Texto secundario (Slate 500) */
            --color-text-sidebar: #475569;  /* Texto de navegación (Slate 600) */

            --color-border: #e2e8f0;        /* Bordes delimitadores sutiles (Slate 200) */

            /* Brand Colors (Deep Sky Blue Optimizado) */
            --color-primary: #009dff;       /* Azul base saturado */
            --color-primary-hover: #0284c7; /* Azul profundo para interacciones */
            --color-primary-glow: rgba(0, 157, 255, 0.35); /* Sombra de saturación */

            /* Status Colors */
            --color-success: #059669;
            --color-success-bg: rgba(5, 150, 105, 0.08);
            --color-danger: #dc2626;
            --color-danger-bg: rgba(220, 38, 38, 0.08);
        }

        .dark {
            /* -----------------------------------------
            DARK MODE (Deep Cyber Abyss)
            ----------------------------------------- */
            --color-bg-body: #0f172a;       /* Fondo base ultra oscuro (Slate 900) */
            --color-bg-container: #1e293b;  /* Elevación de tarjetas (Slate 800) */
            --color-bg-sidebar: #0b1220;    /* Sidebar abismal, unificando la jerarquía */

            --color-text-primary: #f8fafc;  /* Texto principal luminoso (Slate 50) */
            --color-text-muted: #94a3b8;    /* Texto secundario (Slate 400) */
            --color-text-sidebar: #cbd5e1;  /* Texto de navegación (Slate 300) */

            --color-border: #334155;        /* Bordes estructurales oscuros (Slate 700) */

            /* Brand Colors (Ajuste de luminancia para fondos oscuros) */
            --color-primary: #0ea5e9;       
            --color-primary-hover: #38bdf8; 
            --color-primary-glow: rgba(14, 165, 233, 0.25);
        }


        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; }
        
        /* Premium Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; border: 2px solid transparent; background-clip: padding-box; }
        .dark ::-webkit-scrollbar-thumb { background: #475569; border: 2px solid transparent; background-clip: padding-box; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; border: 2px solid transparent; background-clip: padding-box; }
        
        /* Sidebar Active Link */
        /* Sidebar Active Link - CORREGIDO PARA FONDO CLARO */
        .nav-link { position: relative; overflow: hidden; }
        .nav-link::before {
            content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 3px;
            background: #0ea5e9; transform: scaleY(0); transition: transform 0.2s ease;
            border-top-right-radius: 4px; border-bottom-right-radius: 4px;
        }
        .nav-link.active { 
            background: linear-gradient(90deg, rgba(14,165,233,0.15) 0%, rgba(14,165,233,0.02) 100%); 
            color: #0369a1 !important; /* Texto azul oscuro en lugar de blanco */
            font-weight: 700; 
        }
        .nav-link.active i { color: #0284c7; }
        .nav-link.active::before { transform: scaleY(1); }

        /* =========================================
           SIDEBAR GLASSMORPHISM (Estética Login)
           ========================================= */
        .sidebar-glass {
            background:
                radial-gradient(circle at 10% 10%, rgba(0, 191, 255, 0.15), transparent 60%),
                radial-gradient(circle at 90% 90%, rgba(99, 102, 241, 0.10), transparent 50%),
                linear-gradient(180deg, #f8fafc, #e2e8f0);
            border-right: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 10px 0 30px rgba(15, 23, 42, 0.05);
        }

        @keyframes fadeInDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
        .animate-fade-in { animation: fadeInDown .4s cubic-bezier(0.16, 1, 0.3, 1); }

        /* =========================================
           GLOBAL SYSTEM AUTO-UPGRADE STYLES
           Transforms existing Tailwind cards/tables
           into premium Glassmorphism UI
           ========================================= */
           
        /* Background pattern for the entire app */
        .app-bg {
            background-color: #f8fafc;
            background-image: radial-gradient(at 0% 0%, rgba(14, 165, 233, 0.08) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.05) 0px, transparent 50%);
        }
        .dark .app-bg {
            background-color: #0f172a;
            background-image: radial-gradient(at 0% 0%, rgba(14, 165, 233, 0.15) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.08) 0px, transparent 50%);
        }

        /* Auto-upgrade Cards (.bg-white inside main content area) */
        main > div > .bg-white,
        main > div > div > .bg-white,
        main > div > div > div > .bg-white {
            background-color: rgba(255, 255, 255, 0.75) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
            border: 1px solid rgba(255, 255, 255, 0.6) !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.03), 0 8px 10px -6px rgba(0, 0, 0, 0.01) !important;
            border-radius: 1.25rem !important;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .dark main > div > .bg-white,
        .dark main > div > div > .bg-white,
        .dark main > div > div > div > .bg-white {
            background-color: rgba(30, 41, 59, 0.65) !important;
            border-color: rgba(255, 255, 255, 0.05) !important;
        }
        
        /* Auto-upgrade Datatables / Tables */
        table { border-collapse: separate !important; border-spacing: 0 6px !important; width: 100%; }
        thead th { 
            border-bottom: none !important; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            font-size: 0.75rem; 
            padding: 1rem !important;
            color: #64748b;
        }
        .dark { color: #94a3b8; }
        
        tbody tr {
            background: rgba(255, 255, 255, 0.6);
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: all 0.25s ease;
        }
        tbody tr:hover {
            background: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
        }
        .dark tbody tr { background: rgba(30, 41, 59, 0.4); box-shadow: none; }
        .dark tbody tr:hover { background: rgba(30, 41, 59, 0.8); }
        
        tbody td { border: none !important; padding: 1rem !important; vertical-align: middle; }
        tbody td:first-child { border-top-left-radius: 0.75rem; border-bottom-left-radius: 0.75rem; }
        tbody td:last-child { border-top-right-radius: 0.75rem; border-bottom-right-radius: 0.75rem; }

        /* Generic buttons upgrade */
        .btn-action {
            display: inline-flex; items-center; justify-content: center;
            width: 32px; height: 32px; border-radius: 0.5rem; transition: all 0.2s;
        }
        
        /* Topbar Glass */
        .glass-header {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.4);
        }
        .dark .glass-header {
            background: rgba(15, 23, 42, 0.7);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
    </style>
</head>
<body class="app-bg text-slate-800 dark:text-slate-200 antialiased flex h-screen overflow-hidden">

    <!-- ===================== SIDEBAR (GLASSMORPHISM) ===================== -->
    <aside id="sidebar" style="background-color: var(--color-bg-sidebar); border-color: var(--color-border);" class="w-64 flex-shrink-0 h-full flex flex-col z-30 transition-all duration-300 shadow-2xl relative overflow-hidden">
        
        <!-- Orbe decorativo superior (Replica el efecto del login) -->
        <div class="absolute top-[-50px] left-[-50px] w-48 h-48 bg-sky-400/20 blur-3xl rounded-full pointer-events-none"></div>

        <!-- Logo -->
        <div class="h-16 flex items-center px-6 border-b border-white/60 gap-3 relative z-10">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-sky-600 flex items-center justify-center shadow-lg shadow-primary/30">
                <i class="fa-solid fa-bolt text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-lg font-black text-slate-800 tracking-tight leading-none"><?= defined('APP_NAME') ? APP_NAME : 'FactuPucallpa' ?></h1>
                <p class="text-[10px] text-sky-600 font-bold uppercase tracking-widest mt-1">Premium POS</p>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 overflow-y-auto py-5 px-4 space-y-1 relative z-10 custom-scrollbar">

            <a href="<?= baseUrl('dashboard') ?>" class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/50 text-slate-600 hover:text-sky-800 transition-all text-sm font-medium <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/dashboard') ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high w-5 text-center"></i> Dashboard
            </a>

            <a href="<?= baseUrl('pos') ?>" data-turbo="false" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-gradient-to-r from-sky-500 to-blue-600 text-white font-bold hover:shadow-lg hover:shadow-sky-500/30 hover:-translate-y-0.5 transition-all text-sm my-3 shadow-md shadow-sky-500/20">
                <i class="fa-solid fa-cash-register w-5 text-center"></i> Ir al Punto de Venta
            </a>

            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] px-4 pt-5 pb-2">Catálogo</p>

            <a href="<?= baseUrl('productos') ?>" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/50 text-slate-600 hover:text-sky-800 transition-all text-sm font-medium <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/productos') ? 'active' : '' ?>">
                <i class="fa-solid fa-box w-5 text-center"></i> Productos
            </a>
            <a href="<?= baseUrl('categorias') ?>" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/50 text-slate-600 hover:text-sky-800 transition-all text-sm font-medium <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/categorias') ? 'active' : '' ?>">
                <i class="fa-solid fa-tags w-5 text-center"></i> Categorías
            </a>
            <a href="<?= baseUrl('proveedores') ?>" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/50 text-slate-600 hover:text-sky-800 transition-all text-sm font-medium <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/proveedores') ? 'active' : '' ?>">
                <i class="fa-solid fa-truck w-5 text-center"></i> Proveedores
            </a>

            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] px-4 pt-5 pb-2">Comercial</p>

            <a href="<?= baseUrl('ventas') ?>" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/50 text-slate-600 hover:text-sky-800 transition-all text-sm font-medium <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/ventas') ? 'active' : '' ?>">
                <i class="fa-solid fa-receipt w-5 text-center"></i> Ventas
            </a>
            <a href="<?= baseUrl('cotizaciones') ?>" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/50 text-slate-600 hover:text-sky-800 transition-all text-sm font-medium <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/cotizaciones') ? 'active' : '' ?>">
                <i class="fa-solid fa-file-invoice w-5 text-center"></i> Cotizaciones
            </a>
            <a href="<?= baseUrl('compras') ?>" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/50 text-slate-600 hover:text-sky-800 transition-all text-sm font-medium <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/compras') ? 'active' : '' ?>">
                <i class="fa-solid fa-cart-shopping w-5 text-center"></i> Compras
            </a>
            <a href="<?= baseUrl('caja') ?>" data-turbo="false" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/50 text-slate-600 hover:text-sky-800 transition-all text-sm font-medium <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/caja') ? 'active' : '' ?>">
                <i class="fa-solid fa-vault w-5 text-center"></i> Caja
            </a>

            <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] px-4 pt-5 pb-2">Administración</p>

            <a href="<?= baseUrl('reportes') ?>" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/50 text-slate-600 hover:text-sky-800 transition-all text-sm font-medium <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/reportes') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line w-5 text-center"></i> Reportes
            </a>

            <?php if (isAdmin() || isVendedor()): ?>
            <a href="<?= baseUrl('sunat/config') ?>" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/50 text-slate-600 hover:text-sky-800 transition-all text-sm font-medium <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/sunat') ? 'active' : '' ?>">
                <i class="fa-solid fa-building-columns w-5 text-center"></i> SUNAT
            </a>
            <?php endif; ?>

            <?php if (isAdmin()): ?>
            <a href="<?= baseUrl('usuarios') ?>" class="nav-link flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/50 text-slate-600 hover:text-sky-800 transition-all text-sm font-medium <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/usuarios') ? 'active' : '' ?>">
                <i class="fa-solid fa-users w-5 text-center"></i> Usuarios
            </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- ===================== MAIN CONTENT ===================== -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative">


        <!-- Top Header (Glassmorphism) -->
        <header class="h-16 glass-header flex items-center justify-between px-6 flex-shrink-0 z-20 shadow-sm relative">
            <div class="flex items-center gap-5">
                <button id="toggleSidebar" class="w-10 h-10 flex items-center justify-center rounded-xl text-slate-500 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-800 transition-all focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h2 class="text-lg font-black tracking-tight text-slate-800 dark:text-white"><?= htmlspecialchars($title ?? 'Dashboard') ?></h2>
            </div>

            <!-- Acciones Globales -->
            <div class="flex items-center gap-2 sm:gap-4">
                
                <!-- Contenedor Dinámico de Notificaciones (Lazy Load) -->
                <div class="relative" id="notificationContainer">
                    <button id="notificationBtn" class="relative w-10 h-10 rounded-full flex items-center justify-center text-slate-500 hover:text-primary hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-all focus:outline-none">
                        <i class="fa-regular fa-bell text-xl"></i>
                        <span id="notificationBadge" class="hidden absolute top-2 right-2 flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 border-2 border-white dark:border-slate-900 transition-colors"></span>
                        </span>
                    </button>

                    <!-- Panel Flotante de Notificaciones -->
                    <div id="notificationDropdown" class="hidden absolute right-0 mt-3 w-80 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-700 overflow-hidden transform opacity-0 scale-95 transition-all duration-200 origin-top-right z-50">
                        <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex justify-between items-center">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white">Alertas de Stock</h3>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-red-600 bg-red-100 dark:bg-red-900/30 px-2 py-1 rounded-md">Prioridad</span>
                        </div>
                        <div id="notificationList" class="max-h-72 overflow-y-auto custom-scrollbar p-2"></div>
                    </div>
                </div>

                <!-- Theme toggle -->
                <button id="themeToggle" onclick="toggleTheme()" class="w-10 h-10 rounded-xl flex items-center justify-center bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:text-primary dark:hover:text-primary shadow-sm border border-slate-200 dark:border-slate-700 transition-all hover:-translate-y-0.5 hover:shadow-md">
                    <?php if (($_SESSION['theme'] ?? 'light') === 'dark'): ?>
                        <i class="fa-solid fa-sun text-yellow-500 text-lg"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-moon text-lg"></i>
                    <?php endif; ?>
                </button>

                <div class="h-6 w-px bg-slate-200 dark:bg-slate-700 mx-1 hidden sm:block"></div>

                <!-- Dropdown de Usuario -->
                <div class="relative" id="userMenuContainer">
                    <button id="userMenuBtn" class="w-10 h-10 rounded-full bg-gradient-to-tr from-sky-400 to-primary flex items-center justify-center text-white font-bold text-sm shadow-sm border-2 border-transparent hover:border-sky-200 dark:hover:border-sky-700 transition-all focus:outline-none">
                        <?= strtoupper(substr($_SESSION['user_nombre'] ?? 'U', 0, 1)) ?>
                    </button>
                    
                    <div id="userDropdown" class="hidden absolute right-0 mt-3 w-64 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-700 overflow-hidden transform opacity-0 scale-95 transition-all duration-200 origin-top-right z-50">
                        <div class="p-4 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50 flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-sky-400 to-primary flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                                <?= strtoupper(substr($_SESSION['user_nombre'] ?? 'U', 0, 1)) ?>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-sm font-bold text-slate-800 dark:text-white truncate"><?= htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario') ?></p>
                                <p class="text-xs font-semibold text-sky-600 dark:text-sky-400 uppercase tracking-wider truncate"><?= isAdmin() ? 'Administrador' : 'Vendedor' ?></p>
                            </div>
                        </div>
                        <div class="p-2">
                            <a href="<?= baseUrl('logout') ?>" data-turbo="false" class="flex items-center gap-3 px-3 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-colors">
                                <i class="fa-solid fa-arrow-right-from-bracket w-5 text-center"></i> Cerrar Sesión
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </header>

        <!-- Flash Messages -->
        <?php if (!empty($_SESSION['flash'])): ?>
        <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <script>
            // Verificamos si showFlash ya fue declarada globalmente para evitar duplicidad con Turbo
            if (typeof window.showFlash === 'undefined') {
                window.showFlash = function(message, type) {
                    if (typeof showToast === 'function') {
                        showToast(message, type);
                    }
                };
            }

    // Ejecutar inmediatamente o manejar el ciclo de vida de Turbo
    const executeFlash = () => {
        const msg = <?= json_encode($flash['message'] ?? '') ?>;
        const type = '<?= ($flash['type'] ?? 'success') === 'error' ? 'error' : 'success' ?>';
        if (msg && typeof window.showFlash === 'function') {
            window.showFlash(msg, type);
        }
    };

    if (document.readyState === 'complete') {
        executeFlash();
    } else {
        document.addEventListener('turbo:load', executeFlash, { once: true });
        document.addEventListener('DOMContentLoaded', executeFlash, { once: true });
    }
</script>
<?php endif; ?>

        <!-- Page Content -->
        <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8 relative z-10">
            <?php if (isset($content)) echo $content; ?>
        </div>

    </main>

    <!-- ===================== JS ===================== -->
    <script>
    // ── Theme ─────────────────────────────────────────────
    function toggleTheme() {
        const html = document.documentElement;
        const isDark = html.classList.toggle('dark');
        const theme = isDark ? 'dark' : 'light';
        
        // 1. Guardar en localStorage (inmediato, persistente)
        localStorage.setItem('theme', theme);
        
        // 2. Guardar en cookie para server-side
        document.cookie = `theme=${theme};path=/;max-age=31536000`;
        
        // 3. Actualizar icono del botón
        const btn = document.getElementById('themeToggle');
        if (btn) {
            btn.innerHTML = isDark 
                ? '<i class="fa-solid fa-sun text-yellow-500 text-lg"></i>'
                : '<i class="fa-solid fa-moon text-lg"></i>';
        }
        
        // 4. Notificar a los gráficos Chart.js para que se repinten con colores dark/light
        if (typeof updateDashboard === 'function' && window.__dashboardData) {
            // Pequeño retraso para que los estilos CSS se apliquen
            setTimeout(() => updateDashboard(window.__dashboardData), 100);
        }
        
        // 5. Persistir en sesión via AJAX (sin recargar, sin errores)
        fetch('<?= baseUrl('usuario/tema') ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: '_token=<?= csrf_token() ?>&tema=' + theme
        }).catch(() => {});
    }

    // ── Sidebar toggle ────────────────────────────────────
    document.getElementById('toggleSidebar')?.addEventListener('click', () => {
        const sb = document.getElementById('sidebar');
        sb.classList.toggle('-translate-x-full');
        sb.classList.toggle('w-0');
    });

    // Mantener la posición del menú al cambiar de vista.
    (() => {
        const sidebarNav = document.querySelector('#sidebar nav');
        const sidebarScrollKey = 'facturacion.sidebar.scrollTop';
        if (!sidebarNav || sidebarNav.dataset.scrollPersistenceReady === 'true') {
            return;
        }

        sidebarNav.dataset.scrollPersistenceReady = 'true';
        const savedSidebarScroll = Number(sessionStorage.getItem(sidebarScrollKey) || 0);
        sidebarNav.scrollTop = savedSidebarScroll;
        requestAnimationFrame(() => {
            sidebarNav.scrollTop = savedSidebarScroll;
        });

        sidebarNav.addEventListener('scroll', () => {
            sessionStorage.setItem(sidebarScrollKey, String(sidebarNav.scrollTop));
        }, { passive: true });
    })();

    // Close on backdrop click
    document.querySelectorAll('[id$="Modal"]').forEach(m => {
        m.addEventListener('click', e => { 
            if (e.target === m && typeof closeModal === 'function') closeModal(); 
        });
    });
    </script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* SweetAlert Premium styling */
    .swal2-popup { 
        border-radius: 1.5rem !important; 
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important; 
        font-family: 'Inter', sans-serif;
        border: 1px solid rgba(255,255,255,0.4);
    }
    .dark .swal2-popup { 
        background: rgba(30, 41, 59, 0.95) !important; 
        backdrop-filter: blur(16px);
        border: 1px solid rgba(255,255,255,0.1);
    }

    /* INYECTA ESTAS DOS REGLAS PARA CORREGIR LA TIPOGRAFÍA */
    .dark .swal2-title, 
    .dark .swal2-html-container {
        color: #f8fafc !important;
    }
        .swal2-confirm { border-radius: 0.75rem !important; font-weight: 700 !important; }
        .swal2-cancel { border-radius: 0.75rem !important; font-weight: 600 !important; }
    </style>

    <script>
    // ── Toast ─────────────────────────────────────────────
    window.FacturacionToast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
        // Propiedades 'background' y 'color' ELIMINADAS.
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    });

    function showToast(msg, type = 'success') {
        window.FacturacionToast.fire({
            icon: type,
            title: msg
        });
    }

    // ── Confirm delete generic ────────────────────────────
    function confirmDelete(id, url, msg) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: msg || "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url || '#';
                form.innerHTML = `<input type="hidden" name="id" value="${id}"><input type="hidden" name="_token" value="<?= csrf_token() ?>">`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // ── AJAX form submit helper ───────────────────────────
    function submitFormAjax(e, url) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('[id="btnGuardar"]');
        const btnText = form.querySelector('[id="btnText"]');
        const spinner = form.querySelector('[id="btnSpinner"]');
        if (btn) btn.disabled = true;
        if (btnText) btnText.classList.add('hidden');
        if (spinner) spinner.classList.remove('hidden');

        fetch(url, { method: 'POST', body: new FormData(form) })
            .then(r => r.json())
            .then(data => {
                showToast(data.message || (data.success ? 'Guardado' : 'Error'), data.success !== false ? 'success' : 'error');
            })
            .catch(() => showToast('Error de conexión', 'error'))
            .finally(() => {
                if (btn) btn.disabled = false;
                if (btnText) btnText.classList.remove('hidden');
                if (spinner) spinner.classList.add('hidden');
            });
        return false;
    }

    // ── Auto-dismiss flash alerts ─────────────────────────
    setTimeout(() => {
        document.querySelectorAll('.animate-fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(-10px)';
            el.style.transition = 'all .4s ease-in-out';
            setTimeout(() => el.remove(), 400);
        });
    }, 4000);
    
    // ── User Dropdown Management (Turbo SPA Safe) ─────────
    function initUserDropdown() {
        const btn = document.getElementById('userMenuBtn');
        const dropdown = document.getElementById('userDropdown');
        const container = document.getElementById('userMenuContainer');

        if (!btn || !dropdown || !container) return;

        // Limpieza de eventos previos clonando el nodo (Fix para Turbo)
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);

        newBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (dropdown.classList.contains('hidden')) {
                dropdown.classList.remove('hidden');
                // Timeout mínimo para forzar el reflow del DOM y activar la transición
                setTimeout(() => {
                    dropdown.classList.remove('opacity-0', 'scale-95');
                    dropdown.classList.add('opacity-100', 'scale-100');
                }, 10);
            } else {
                closeDropdown(dropdown);
            }
        });

        // Cierre al hacer click fuera del panel
        document.addEventListener('click', (e) => {
            if (!container.contains(e.target) && !dropdown.classList.contains('hidden')) {
                closeDropdown(dropdown);
            }
        });
    }

    function closeDropdown(dropdown) {
        dropdown.classList.remove('opacity-100', 'scale-100');
        dropdown.classList.add('opacity-0', 'scale-95');
        setTimeout(() => dropdown.classList.add('hidden'), 200); // Espera a que termine la animación
    }

    // Inicialización híbrida
    document.addEventListener('turbo:load', initUserDropdown);
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initUserDropdown();
    }

// ── Sistema de Notificaciones Enterprise (Turbo SPA Safe) ─────────
    function initNotificationSystem() {
        const btn = document.getElementById('notificationBtn');
        const dropdown = document.getElementById('notificationDropdown');
        const container = document.getElementById('notificationContainer');
        const list = document.getElementById('notificationList');
        const badge = document.getElementById('notificationBadge');

        if (!btn || !dropdown || !container) return;

        // Prevención de listeners duplicados por Turbo
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);

        newBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (dropdown.classList.contains('hidden')) {
                const userDropdown = document.getElementById('userDropdown');
                if(userDropdown && !userDropdown.classList.contains('hidden')) closeDropdown(userDropdown);

                dropdown.classList.remove('hidden');
                setTimeout(() => {
                    dropdown.classList.remove('opacity-0', 'scale-95');
                    dropdown.classList.add('opacity-100', 'scale-100');
                }, 10);

                // Llamada al nuevo motor agregador
                fetchSystemAlerts(list, badge);
            } else {
                closeDropdown(dropdown);
            }
        });

        document.addEventListener('click', (e) => {
            if (!container.contains(e.target) && !dropdown.classList.contains('hidden')) {
                closeDropdown(dropdown);
            }
        });
    }

    function fetchSystemAlerts(listElement, badgeElement) {
        listElement.innerHTML = '<div class="flex items-center justify-center p-6 text-slate-400 text-sm"><i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Procesando telemetría...</div>';
        
        fetch('<?= baseUrl('api/notificaciones/todas') ?>') 
            .then(res => res.json())
            .then(data => {
                if(data.length === 0) {
                    listElement.innerHTML = '<div class="p-6 text-center text-sm text-slate-500"><i class="fa-solid fa-shield-check text-emerald-500 text-3xl mb-2 block"></i> Sistemas Operativos</div>';
                    badgeElement.classList.add('hidden');
                    return;
                }
                
                badgeElement.classList.remove('hidden');
                badgeElement.textContent = data.length;
                
                listElement.innerHTML = data.map(alerta => {
                    const bgColors = {
                        'red': 'bg-red-100 dark:bg-red-900/30 text-red-600',
                        'purple': 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 border-purple-500',
                        'orange': 'bg-orange-100 dark:bg-orange-900/30 text-orange-600',
                        'blue': 'bg-blue-100 dark:bg-blue-900/30 text-blue-600'
                    };
                    
                    const theme = bgColors[alerta.color] || bgColors['red'];

                    // Retornamos una etiqueta <a> nativa para soportar la navegación Hotwire Turbo
                    return `
                        <a href="${alerta.url}" data-turbo-action="advance" class="w-full text-left flex items-start gap-3 p-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-xl transition-colors group border-l-2 border-transparent hover:border-${alerta.color}-500">
                            <div class="w-9 h-9 rounded-full ${theme} flex items-center justify-center flex-shrink-0 mt-0.5 group-hover:scale-110 transition-transform">
                                <i class="fa-solid ${alerta.icono} text-sm"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-200 truncate">${alerta.titulo}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">${alerta.mensaje}</p>
                            </div>
                        </a>
                    `;
                }).join('');
            })
            .catch(() => {
                listElement.innerHTML = '<div class="p-4 text-center text-sm text-red-500">Fallo de conexión con el core analítico.</div>';
            });
    }

    // Inyección en el ciclo de vida de Hotwire Turbo
    document.addEventListener('turbo:load', initNotificationSystem);
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initNotificationSystem();
    }
    </script>

    <!-- PΛRADISE Offcanvas Side-Drawer -->
    <div id="stockDrawerOverlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 hidden opacity-0 transition-opacity"></div>
    <div id="stockDrawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white dark:bg-slate-900 shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col border-l border-slate-200 dark:border-slate-800">
        <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-slate-900/50">
            <h3 class="text-lg font-black text-slate-800 dark:text-white">Ajuste de Inventario</h3>
            <button onclick="closeStockDrawer()" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div id="stockDrawerContent" class="p-6 flex-1 overflow-y-auto">
            <!-- El contenido dinámico se inyectará aquí -->
        </div>
    </div>
</body>
</html>
