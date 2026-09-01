<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= defined('APP_NAME') ? APP_NAME : 'PΛRADISE' ?> — Portal Administrativo</title>

    <!-- Tipografía -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <!-- MOTOR TAILWIND CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"Space Grotesk"', 'monospace']
                    }
                }
            }
        }
    </script>

    <style>
    :root {
        --bg-0: #cbd5e1; 
        --bg-1: #e2e8f0;
        --ink: #0b1220;
        --muted: #5b6b82;
        --muted-2: #93a1b5;
        --line: rgba(15, 23, 42, .10);
        --line-strong: rgba(15, 23, 42, .18);

        --glass: rgba(255, 255, 255, .58);
        --glass-strong: rgba(255, 255, 255, .78);
        --glass-border: rgba(255, 255, 255, .75);

        /* --- AJUSTE DE CONTRASTE --- */
        --sky: #009dff;          /* Más saturado, menos luz blanca que 00bfff */
        --sky-deep: #026aa7;     /* Más oscuro para generar tensión en gradientes */
        --sky-ink: #014773;      /* Texto azul muy oscuro para legibilidad perfecta */
        --teal: #08b4ce;         /* Teal ajustado para armonizar */

        --success: #059669;
        --success-bg: rgba(5, 150, 105, .08);
        --danger: #dc2626;
        --danger-bg: rgba(220, 38, 38, .08);

        --shadow-card: 0 40px 90px -30px rgba(15, 43, 74, .35), 0 2px 0 rgba(255,255,255,.7) inset;
        --shadow-soft: 0 20px 45px -25px rgba(15, 43, 74, .3);
    }

    
    * { box-sizing: border-box; }

    html { background: var(--bg-0); }
    

    /* Contenedor fluido para asegurar que el fondo se extienda en todo el body */
    body {
        position: relative;
        min-height: 100vh;
        margin: 0;
        color: var(--ink);
        font-family: 'Plus Jakarta Sans', sans-serif;
        background:
            radial-gradient(circle at 18% 12%, rgba(0, 191, 255, .16), transparent 42%),
            radial-gradient(circle at 88% 82%, rgba(99, 102, 241, .10), transparent 40%),
            linear-gradient(180deg, var(--bg-1), var(--bg-0));
        overflow-x: hidden;
    }

    a { text-decoration: none; }

    /* -------------------------------------------------
       ESTRUCTURA GENERAL
    ------------------------------------------------- */

    .stage {
        position: relative;
        min-height: 100vh;
        width: 100vw;
        display: grid;
        grid-template-columns: 1fr;
        overflow-x: hidden;
    }

    @media (min-width: 1024px) {
        .stage {
            /* Dividimos la pantalla en dos columnas estables: Izquierda más ancha, derecha equilibrada */
            grid-template-columns: 1.2fr 1fr;
        }
    }

    /* -------------------------------------------------
       PANEL IZQUIERDO — ESCENA "GLASS NETWORK"
    ------------------------------------------------- */

    /* -------------------------------------------------
       PANEL IZQUIERDO — ESCENA DE MARCA
       ------------------------------------------------- */
    .hero {
        position: relative;
        overflow: hidden;
        min-height: 320px;
        display: flex;
        align-items: center;
        isolation: isolate;
        border: none !important;
        background: transparent !important;
        grid-column: 1;
    }

    @media (min-width: 1024px) {
        .hero {
            min-height: 100vh;
        }
    }

    .hero-grid {
        position: absolute;
        inset: -10%;
        z-index: 0;
        opacity: .55;
        background-image:
            linear-gradient(rgba(3, 105, 161, .09) 1px, transparent 1px),
            linear-gradient(90deg, rgba(3, 105, 161, .09) 1px, transparent 1px);
        background-size: 46px 46px;
        mask-image: radial-gradient(circle at 30% 35%, black, transparent 72%);
        animation: gridDrift 24s linear infinite;
    }

    .hero-sheen {
        position: absolute;
        inset: 0;
        z-index: 1;
        background: linear-gradient(115deg, rgba(255,255,255,.55) 0%, transparent 30%, transparent 70%, rgba(0,191,255,.10) 100%);
        pointer-events: none;
    }

    .orb-field {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        z-index: 2;
        pointer-events: none;
    }

    .orb-bottom-left {
        position: absolute;
        z-index: -1;
        width: 260px; 
        height: 260px;
        left: -90px; 
        bottom: -90px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(0, 191, 255, .28), transparent 70%);
        filter: blur(10px);
        pointer-events: none; /* Asegura que no bloquee los clics en el formulario */
    }
    .orb {
        position: absolute;
        border-radius: 50%;
        background:
            radial-gradient(circle at 30% 26%, rgba(255,255,255,.95), rgba(255,255,255,.25) 38%, rgba(0,191,255,.30) 68%, rgba(0,191,255,.05) 100%);
        box-shadow:
            inset 0 0 34px rgba(255,255,255,.55),
            0 30px 55px -20px rgba(2,132,199,.45),
            0 0 0 1px rgba(255,255,255,.5);
        animation: orbFloat 9s ease-in-out infinite alternate;
    }

    .orb-a { width: 150px; height: 150px; top: 10%; left: 8%; animation-duration: 10s; }
    .orb-b { width: 90px;  height: 90px;  top: 60%; left: 22%; animation-duration: 8s; animation-delay: -2s; }
    .orb-c { width: 60px;  height: 60px;  top: 30%; left: 52%; animation-duration: 7s; animation-delay: -4s; }
    .orb-d { width: 210px; height: 210px; top: 62%; left: 68%; animation-duration: 12s; animation-delay: -6s; }
    .orb-e { width: 40px;  height: 40px;  top: 14%; left: 66%; animation-duration: 6s; animation-delay: -1s; }

    .orb-lines {
        position: absolute;
        inset: 0;
        z-index: 1;
        width: 100%;
        height: 100%;
    }

    .orb-lines path {
        fill: none;
        stroke: rgba(2, 132, 199, .35);
        stroke-width: 1.8;
        stroke-dasharray: 4 7;
        animation: dashFlow 6s linear infinite;
    }

    .chip {
        position: absolute;
        z-index: 3;
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .55rem .9rem;
        border-radius: 999px;
        background: var(--glass);
        border: 1px solid var(--glass-border);
        backdrop-filter: blur(14px) saturate(160%);
        -webkit-backdrop-filter: blur(14px) saturate(160%);
        box-shadow: var(--shadow-soft);
        font-family: 'Space Grotesk', monospace;
        font-size: .68rem;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--sky-ink);
        animation: chipFloat 7s ease-in-out infinite alternate;
    }

    .chip i { color: var(--sky); font-size: .78rem; }
    .chip-1 { top: 16%; right: 10%; animation-delay: -1s; }
    .chip-2 { bottom: 20%; left: 6%; animation-delay: -3s; }

    .status-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: var(--sky);
        box-shadow: 0 0 0 0 rgba(0,191,255,.6);
        animation: pulseDot 2.2s ease-out infinite;
    }

    .hero-content {
        position: relative;
        z-index: 4;
        width: 100%;
        padding: 3rem 2rem;
    }

    @media (min-width: 640px) { .hero-content { padding: 3rem 3.5rem; } }
    @media (min-width: 1024px) { .hero-content { padding: 3rem 5.5rem; } }

    .brand-mark {
        display: inline-flex;
        align-items: center;
        gap: .85rem;
        margin-bottom: 2.4rem;
    }

    .brand-mark .icon-box {
        width: 46px; height: 46px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 12px;
        background: linear-gradient(145deg, rgba(255,255,255,.9), rgba(224,242,254,.6));
        border: 1px solid var(--glass-border);
        box-shadow: var(--shadow-soft);
        color: var(--sky-deep);
        font-size: 1.05rem;
    }

    .brand-mark span {
        font-family: 'Space Grotesk', monospace;
        font-size: .72rem;
        letter-spacing: .32em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .accent-line {
        width: 70px; height: 3px;
        margin-bottom: 1.6rem;
        border-radius: 99px;
        background: linear-gradient(90deg, var(--sky), var(--teal));
        box-shadow: 0 0 20px rgba(0,191,255,.55);
        transition: width .5s ease;
    }

    .hero:hover .accent-line { width: 116px; }

    .hero-title {
        font-family: 'Space Grotesk', monospace;
        font-weight: 700;
        line-height: .92;
        letter-spacing: -.03em;
        font-size: clamp(2.6rem, 6vw, 5.4rem);
    }

    .hero-title .line-outline {
        color: transparent;
        -webkit-text-stroke: 1.4px rgba(11, 18, 32, .55);
    }

    .hero-title .line-solid {
        color: var(--ink);
        background: linear-gradient(90deg, var(--ink), var(--sky-deep) 130%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    .hero-quote {
        max-width: 30rem;
        margin-top: 1.8rem;
        padding-left: 1.1rem;
        border-left: 2px solid rgba(2,132,199,.35);
        color: var(--muted);
        font-size: .95rem;
        font-weight: 300;
        line-height: 1.6;
    }

    /* -------------------------------------------------
       PANEL DERECHO — TARJETA DE ACCESO
    ------------------------------------------------- */

   /* -------------------------------------------------
       PANEL DERECHO — TARJETA DE ACCESO FLOTANTE
       ------------------------------------------------- */
    .panel-wrap {
        position: relative;
        grid-column: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1.5rem 4rem;
        background: transparent !important;
        box-shadow: none !important;
        border: none !important;
        z-index: 10;
    }

    /* Aseguramos que la tarjeta responda bien en pantallas medianas o si se redimensiona */
    @media (max-width: 1200px) {
        .panel-wrap {
            padding-right: 6vw;
        }
    }

    @media (min-width: 1024px) {
        .panel-wrap {
            grid-column: 2; /* Se posiciona exclusivamente en la columna derecha del Grid */
            padding: 3rem 4rem 3rem 2rem;
            justify-content: flex-start; /* Deja un espacio natural elegante respecto al centro */
        }
    }

    @media (min-width: 1024px) {
        .panel-wrap {
            flex: 1 1 0%;
            padding: 3rem;
        }
    }

    .glass-card {
        position: relative;
        width: 100%;
        max-width: 420px;
        padding: 2.6rem 2.1rem 2.4rem;
        pointer-events: auto;
        border-radius: 28px;
        background: var(--glass-strong);
        border: 1px solid var(--glass-border);
        backdrop-filter: blur(22px) saturate(170%);
        -webkit-backdrop-filter: blur(22px) saturate(170%);
        box-shadow: var(--shadow-card);
        transition: transform .25s ease;
        will-change: transform;
        animation: cardIn .7s cubic-bezier(.16,1,.3,1) both;
    }

    .glass-card::before {
        content: "";
        position: absolute;
        top: -1px; left: 10%; right: 10%;
        height: 3px;
        border-radius: 99px;
        background: linear-gradient(90deg, transparent, var(--sky), var(--teal), transparent);
    }

    .glass-card::after {
        content: "";
        position: absolute;
        z-index: -1;
        width: 260px; height: 260px;
        right: -90px; top: -90px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(0,191,255,.28), transparent 70%);
        filter: blur(10px);
    }

    .card-head { margin-bottom: 2rem; animation: fadeUp .6s ease .05s both; }

    .card-head h2 {
        font-family: 'Space Grotesk', monospace;
        font-size: 2.1rem;
        font-weight: 600;
        letter-spacing: -.02em;
        color: var(--ink);
        margin: 0 0 .5rem;
    }

    .card-head p {
        margin: 0;
        font-size: .86rem;
        font-weight: 400;
        color: var(--muted);
    }

    /* Alertas */

    .alert {
        display: flex;
        align-items: center;
        gap: .7rem;
        padding: .8rem 1rem;
        border-radius: 12px;
        margin-bottom: 1.6rem;
        font-family: 'Space Grotesk', monospace;
        font-size: .74rem;
        border: 1px solid;
        animation: fadeUp .5s ease both;
    }

    .alert-error {
        color: var(--danger);
        border-color: rgba(220,38,38,.28);
        background: var(--danger-bg);
    }

    .alert-success {
        color: var(--success);
        border-color: rgba(5,150,105,.28);
        background: var(--success-bg);
    }

    /* Inputs */

    .input-group {
        position: relative;
        margin-bottom: 2.3rem;
        animation: fadeUp .6s ease .1s both;
    }

    .ghost-input {
        width: 100%;
        min-height: 46px;
        padding: .6rem 2rem .6rem 0;
        color: var(--ink);
        font-size: 1rem;
        font-weight: 500;
        background: transparent !important;
        border: 0;
        border-bottom: 1.5px solid var(--line-strong);
        border-radius: 0;
        outline: none;
        box-shadow: none !important;
        transition: border-color .3s ease, transform .3s ease;
    }

    .ghost-input:hover { border-bottom-color: rgba(3,105,161,.4); }

    .ghost-input:focus {
        border-bottom-color: transparent;
        transform: translateY(-1px);
    }

    .input-group::after {
        content: "";
        position: absolute;
        left: 0; right: 0; bottom: 0;
        height: 2px;
        border-radius: 99px;
        background: linear-gradient(90deg, var(--sky), var(--teal));
        transform: scaleX(0);
        transform-origin: left;
        transition: transform .45s cubic-bezier(.16,1,.3,1);
    }

    .input-group:focus-within::after {
        transform: scaleX(1);
        box-shadow: 0 4px 16px rgba(0,191,255,.35);
    }

    .ghost-label {
        position: absolute;
        top: .75rem; left: 0;
        color: var(--muted-2);
        font-size: .85rem;
        pointer-events: none;
        transition: all .3s cubic-bezier(.16,1,.3,1);
    }

    .ghost-input:focus ~ .ghost-label,
    .ghost-input:not(:placeholder-shown) ~ .ghost-label {
        top: -1.1rem;
        color: var(--sky-ink);
        font-size: .64rem;
        font-weight: 800;
        letter-spacing: .16em;
        text-transform: uppercase;
    }

    .input-icon {
        position: absolute;
        top: .8rem; right: 0;
        color: var(--muted-2);
        transition: color .3s ease, transform .3s ease;
    }

    .input-group:focus-within .input-icon {
        color: var(--sky-deep);
        transform: scale(1.1);
    }

    .ghost-input:-webkit-autofill,
    .ghost-input:-webkit-autofill:hover,
    .ghost-input:-webkit-autofill:focus {
        -webkit-box-shadow: 0 0 0 40px #fbfdff inset !important;
        -webkit-text-fill-color: var(--ink) !important;
        transition: background-color 5000s ease-in-out 0s;
    }

    /* Botón */

    .btn-premium {
        min-height: 56px;
        position: relative;
        overflow: hidden;
        color: #fff;
        background: linear-gradient(110deg, var(--sky), var(--sky-deep));
        border: 1px solid rgba(255,255,255,.5);
        border-radius: 12px;
        box-shadow: 0 12px 24px -8px rgba(0, 157, 255, 0.4), 
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        transition: transform .3s ease, box-shadow .3s ease, filter .3s ease;
        animation: fadeUp .6s ease .16s both;
    }

    .btn-premium::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(110deg, transparent 20%, rgba(255,255,255,.55), transparent 80%);
        transform: translateX(-120%);
        transition: transform .7s ease;
    }

    .btn-premium:hover {
        filter: brightness(1.06);
        transform: translateY(-2px);
        box-shadow: 0 16px 32px -8px rgba(0, 157, 255, 0.6), 
                inset 0 1px 0 rgba(255, 255, 255, 0.4);
    }

    .btn-premium:hover::before { transform: translateX(120%); }
    .btn-premium:active { transform: translateY(-1px) scale(.99); }

    .btn-premium:disabled {
        cursor: wait;
        opacity: .8;
        transform: none;
    }

    .btn-premium .btn-text,
    .btn-premium .btn-arrow {
        position: relative;
        z-index: 2;
        transition: transform .3s ease;
    }

    .btn-premium:hover .btn-text { transform: translateX(4px); }
    .btn-premium:hover .btn-arrow { transform: translateX(-4px); }

    .trust-line {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-top: 1.4rem;
        font-size: .72rem;
        color: var(--muted-2);
    }

    .trust-line i { color: var(--sky-deep); }

    /* Footer link */

    .card-footer {
        margin-top: 1.8rem;
        padding-top: 1.3rem;
        border-top: 1px solid var(--line);
        animation: fadeUp .6s ease .2s both;
    }

    .card-footer a {
        font-size: .7rem;
        color: var(--muted);
        display: flex;
        align-items: center;
        gap: .5rem;
        text-transform: uppercase;
        letter-spacing: .12em;
        font-family: 'Space Grotesk', monospace;
        transition: color .25s ease;
    }

    .card-footer a:hover { color: var(--sky-deep); }

    /* Foco visible accesible */

    a:focus-visible,
    button:focus-visible {
        outline: 2px solid var(--sky-deep);
        outline-offset: 3px;
        border-radius: 6px;
    }

    /* -------------------------------------------------
       ANIMACIONES
    ------------------------------------------------- */

    @keyframes gridDrift {
        from { transform: translate3d(0,0,0); }
        to { transform: translate3d(46px, 46px, 0); }
    }

    @keyframes orbFloat {
        from { transform: translate3d(0,0,0) scale(1); }
        to   { transform: translate3d(14px,-18px,0) scale(1.06); }
    }

    @keyframes chipFloat {
        from { transform: translateY(0); }
        to   { transform: translateY(-10px); }
    }

    @keyframes dashFlow {
        to { stroke-dashoffset: -110; }
    }

    @keyframes pulseDot {
        0%   { box-shadow: 0 0 0 0 rgba(0,191,255,.55); }
        70%  { box-shadow: 0 0 0 9px rgba(0,191,255,0); }
        100% { box-shadow: 0 0 0 0 rgba(0,191,255,0); }
    }

    @keyframes cardIn {
        from { opacity: 0; transform: translate3d(0,26px,0) scale(.97); filter: blur(6px); }
        to   { opacity: 1; transform: translate3d(0,0,0) scale(1); filter: blur(0); }
    }

    @keyframes fadeUp {
        from { opacity: 0; transform: translate3d(0,14px,0); }
        to   { opacity: 1; transform: translate3d(0,0,0); }
    }

    /* -------------------------------------------------
       RESPONSIVE
    ------------------------------------------------- */

    @media (max-width: 1023px) {
        .hero { min-height: 260px; }
        .hero-content { padding-top: 2.4rem; padding-bottom: 2.4rem; }
        .hero-quote { display: none; }
        .chip-2 { display: none; }
        .orb-d { display: none; }
    }

    @media (max-width: 639px) {
        .hero { min-height: 220px; }
        .hero-title { font-size: clamp(2.1rem, 12vw, 3rem); }
        .brand-mark { margin-bottom: 1.2rem; }
        .chip-1 { display: none; }
        .glass-card { padding: 2.1rem 1.4rem 1.9rem; border-radius: 22px; }
        .card-head h2 { font-size: 1.7rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: .01ms !important;
            scroll-behavior: auto !important;
        }
    }
    </style>
</head>
<body>

    <div class="stage">

        <!-- ==========================================
             PANEL IZQUIERDO — ESCENA DE MARCA
             ========================================== -->
        <div class="hero">
            <div class="hero-grid"></div>
            <div class="hero-sheen"></div>

            <div class="orb-field" aria-hidden="true">
                <svg class="orb-lines" viewBox="0 0 800 600" preserveAspectRatio="none">
                    <path d="M 60 90 C 200 160, 260 220, 420 260" />
                    <path d="M 120 380 C 260 340, 340 300, 520 380" />
                    <path d="M 420 260 C 500 300, 560 340, 620 420" />
                </svg>
                <div class="orb orb-a"></div>
                <div class="orb orb-b"></div>
                <div class="orb orb-c"></div>
                <div class="orb orb-d"></div>
                <div class="orb orb-e"></div>

                <!--<div class="chip chip-1"><span class="status-dot"></span> Red en línea</div> -->
                <div class="chip chip-2"><i class="fa-solid fa-shield-halved"></i> Acceso cifrado</div>
            </div>

            <div class="hero-content">
                <div class="brand-mark">
                    <div class="icon-box"><i class="fa-solid fa-server"></i></div>
                    <span>V1</span>
                </div>

                <div class="accent-line"></div>

                <h1 class="hero-title">
                    <span class="line-outline">SISTEMA</span><br>
                    <span class="line-solid">INFORTEL COMP</span>
                </h1>

                <p class="hero-quote">
                    "Optimizamos el flujo de datos. Construimos la red transaccional de alta velocidad del mañana."
                </p>
            </div>
        </div>

        <!-- ==========================================
             PANEL DERECHO — TARJETA DE ACCESO
             ========================================== -->
        <div class="panel-wrap">
            <div class="glass-card">
                <!-- NUEVO: Destello volumétrico inferior izquierdo -->
                <div class="orb-bottom-left"></div>
                <div class="card-head">
                    <h2>Bienvenido</h2>
                    <p>Ingrese sus credenciales de acceso autorizado.</p>
                </div>

                <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-check"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
                <?php endif; ?>

                <form action="<?= baseUrl('login') ?>" method="POST" id="loginForm" data-turbo="false" autocomplete="off" novalidate>

                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?? csrf_token() ?>">

                    <div style="position: absolute; left: -9999px; opacity: 0;" aria-hidden="true">
                        <input type="text" name="website_url" tabindex="-1">
                    </div>

                    <!-- Input: Username -->
                    <div class="input-group">
                        <input type="text" id="username" name="username" required placeholder=" "
                               value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                               class="ghost-input peer">
                        <label for="username" class="ghost-label">ID de Usuario</label>
                        <i class="fa-regular fa-user input-icon"></i>
                    </div>

                    <!-- Input: Password -->
                    <div class="input-group mb-12">
                        <input type="password" id="password" name="password" required placeholder=" "
                               class="ghost-input peer pr-10 font-mono text-xl tracking-[0.3em]">
                        <label for="password" class="ghost-label tracking-normal font-sans">Clave de Acceso</label>

                        <button
                            type="button"
                            id="togglePassword"
                            tabindex="-1"
                            aria-label="Mostrar contraseña"
                            class="absolute right-0 top-[0.55rem] text-slate-400 hover:text-sky-600 transition-colors p-1 z-10">
                            <i id="eyeIcon" class="fa-solid fa-lock text-sm"></i>
                        </button>
                    </div>

                    <!-- Submit Action -->
                    <button type="submit" id="btnSubmit" class="btn-premium w-full py-4 px-6 font-bold tracking-[0.15em] text-[11px] uppercase">
                        <span id="btnText" class="btn-text">Ingresar al Sistema</span>
                        <i id="btnIcon" class="fa-solid fa-arrow-right btn-arrow text-sm"></i>

                        <svg id="btnLoader" class="hidden animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>

                    <div class="trust-line">
                        <i class="fa-solid fa-lock"></i> Conexión cifrada de extremo a extremo
                    </div>

                    <!-- Footer Link 
                    <div class="card-footer">
                        <a href="#">
                            <i class="fa-solid fa-arrow-left-long"></i> Volver al portal público
                        </a> 
                    </div> -->
                </form>
            </div>
        </div>

    </div>

    <script>
    // ---------------------------------------------------------
    // LÓGICA DE INICIO DE SESIÓN — SIN CAMBIOS FUNCIONALES
    // ---------------------------------------------------------
    const toggleBtn = document.getElementById('togglePassword');
    const pwdInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('btnSubmit');
    const btnText = document.getElementById('btnText');
    const btnIcon = document.getElementById('btnIcon');
    const btnLoader = document.getElementById('btnLoader');

    toggleBtn.addEventListener('click', () => {
        const isHidden = pwdInput.type === 'password';

        pwdInput.type = isHidden ? 'text' : 'password';

        eyeIcon.className = isHidden
            ? 'fa-solid fa-eye text-sky-600'
            : 'fa-solid fa-lock text-slate-400';

        toggleBtn.setAttribute(
            'aria-label',
            isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña'
        );
    });

    loginForm.addEventListener('submit', function () {
        submitBtn.disabled = true;
        submitBtn.setAttribute('aria-busy', 'true');

        btnText.textContent = 'AUTORIZANDO...';
        btnIcon.classList.add('hidden');
        btnLoader.classList.remove('hidden');

        submitBtn.style.background =
            'linear-gradient(110deg, #0369a1, #0284c7, #0891b2)';
    });

    const userField = document.getElementById('username');

    if (userField && userField.value.trim() === '') {
        userField.focus();
    }

    // Microinteracción 3D en el botón (idéntica a la original)
    submitBtn.addEventListener('pointermove', (event) => {
        if (submitBtn.disabled) return;

        const rect = submitBtn.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;

        const rotateX = ((y / rect.height) - .5) * -3;
        const rotateY = ((x / rect.width) - .5) * 3;

        submitBtn.style.transform =
            `translateY(-3px) perspective(700px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
    });

    submitBtn.addEventListener('pointerleave', () => {
        if (!submitBtn.disabled) {
            submitBtn.style.transform = '';
        }
    });

    // ---------------------------------------------------------
    // DECORATIVO — no toca formulario, envío ni validación
    // ---------------------------------------------------------
    (function decorativeParallax() {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const finePointer = window.matchMedia('(pointer: fine)').matches;
        if (reduceMotion || !finePointer) return;

        const hero = document.querySelector('.hero');
        const orbs = document.querySelectorAll('.orb');
        const card = document.querySelector('.glass-card');

        if (hero) {
            hero.addEventListener('pointermove', (e) => {
                const rect = hero.getBoundingClientRect();
                const px = (e.clientX - rect.left) / rect.width - .5;
                const py = (e.clientY - rect.top) / rect.height - .5;

                orbs.forEach((orb, i) => {
                    const depth = (i + 1) * 6;
                    orb.style.transform = `translate3d(${px * depth}px, ${py * depth}px, 0)`;
                });
            });

            hero.addEventListener('pointerleave', () => {
                orbs.forEach((orb) => { orb.style.transform = ''; });
            });
        }

        if (card) {
            card.addEventListener('pointermove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width - .5;
                const y = (e.clientY - rect.top) / rect.height - .5;

                card.style.transform =
                    `perspective(1200px) rotateX(${y * -2}deg) rotateY(${x * 2}deg)`;
            });

            card.addEventListener('pointerleave', () => {
                card.style.transform = '';
            });
        }
    })();
    </script>

</body>
</html>