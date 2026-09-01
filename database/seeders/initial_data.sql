-- ============================================================
-- DATOS INICIALES DEL SISTEMA DE FACTURACIÓN
-- Empresa: Empresa de Informática / Tecnología - Pucallpa
-- Versión: 1.0.0
-- IMPORTANTE: Ejecutar DESPUÉS de schema.sql
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-05:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. ROLES DEL SISTEMA
-- ============================================================

INSERT INTO `roles` (`id`, `nombre`, `descripcion`, `es_sistema`) VALUES
(1, 'Administrador', 'Acceso total al sistema: usuarios, configuración, reportes, ventas, compras, inventario', 1),
(2, 'Vendedor',      'Acceso a ventas, cotizaciones, productos y clientes. Sin acceso a configuración ni reportes financieros', 1),
(3, 'Almacenero',    'Acceso a inventario, recepciones de compra y productos. Sin acceso a ventas directas', 0),
(4, 'Contador',      'Acceso de solo lectura a reportes financieros, comprobantes y exportaciones contables', 0);

-- ============================================================
-- 2. PERMISOS DEL SISTEMA (granulares por módulo)
-- ============================================================

INSERT INTO `permisos` (`modulo`, `accion`, `descripcion`) VALUES
-- Módulo: Dashboard
('dashboard', 'ver',           'Ver panel de control y estadísticas'),
-- Módulo: Ventas
('ventas',    'ver',           'Ver listado y detalle de ventas'),
('ventas',    'crear',         'Crear nuevas ventas/comprobantes'),
('ventas',    'editar',        'Editar ventas en estado borrador'),
('ventas',    'anular',        'Anular ventas emitidas'),
('ventas',    'exportar',      'Exportar ventas a Excel/PDF'),
('ventas',    'ver_costo',     'Ver costo de productos en ventas'),
-- Módulo: Cotizaciones
('cotizaciones', 'ver',        'Ver cotizaciones'),
('cotizaciones', 'crear',      'Crear cotizaciones'),
('cotizaciones', 'editar',     'Editar cotizaciones'),
('cotizaciones', 'eliminar',   'Eliminar cotizaciones en borrador'),
('cotizaciones', 'convertir',  'Convertir cotización a venta'),
-- Módulo: Clientes
('clientes',  'ver',           'Ver listado de clientes'),
('clientes',  'crear',         'Crear clientes'),
('clientes',  'editar',        'Editar datos de clientes'),
('clientes',  'eliminar',      'Eliminar clientes sin movimientos'),
('clientes',  'exportar',      'Exportar listado de clientes'),
-- Módulo: Productos
('productos', 'ver',           'Ver catálogo de productos'),
('productos', 'crear',         'Crear productos'),
('productos', 'editar',        'Editar productos'),
('productos', 'eliminar',      'Eliminar productos sin movimientos'),
('productos', 'exportar',      'Exportar catálogo'),
('productos', 'ajustar_stock', 'Realizar ajustes de inventario'),
-- Módulo: Compras
('compras',   'ver',           'Ver órdenes de compra'),
('compras',   'crear',         'Crear órdenes de compra'),
('compras',   'editar',        'Editar órdenes de compra'),
('compras',   'recibir',       'Registrar recepciones de mercadería'),
('compras',   'cancelar',      'Cancelar órdenes de compra'),
-- Módulo: Proveedores
('proveedores', 'ver',         'Ver listado de proveedores'),
('proveedores', 'crear',       'Crear proveedores'),
('proveedores', 'editar',      'Editar proveedores'),
('proveedores', 'eliminar',    'Eliminar proveedores sin movimientos'),
-- Módulo: Caja
('caja',      'ver',           'Ver estado de caja actual'),
('caja',      'abrir',         'Abrir caja al inicio del día'),
('caja',      'cerrar',        'Cerrar caja al final del día'),
('caja',      'ver_todas',     'Ver todas las cajas (no solo la propia)'),
-- Módulo: SUNAT
('sunat',     'ver',           'Ver estado de comprobantes electrónicos'),
('sunat',     'enviar',        'Enviar/reenviar comprobantes a SUNAT'),
('sunat',     'baja',          'Solicitar baja de comprobantes'),
-- Módulo: Reportes
('reportes',  'ventas',        'Ver reportes de ventas'),
('reportes',  'inventario',    'Ver reportes de inventario'),
('reportes',  'compras',       'Ver reportes de compras'),
('reportes',  'financiero',    'Ver reportes financieros (margen, utilidad)'),
('reportes',  'exportar',      'Exportar reportes a Excel/PDF'),
-- Módulo: Usuarios
('usuarios',  'ver',           'Ver listado de usuarios'),
('usuarios',  'crear',         'Crear usuarios'),
('usuarios',  'editar',        'Editar usuarios'),
('usuarios',  'eliminar',      'Desactivar usuarios'),
('usuarios',  'cambiar_rol',   'Cambiar rol de usuario'),
-- Módulo: Configuración
('configuracion', 'ver',       'Ver configuración del sistema'),
('configuracion', 'editar',    'Editar configuración del sistema'),
('configuracion', 'sunat',     'Configurar credenciales SUNAT');

-- ============================================================
-- 3. PERMISOS POR ROL
-- ============================================================

-- Administrador: todos los permisos
INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT 1, id FROM `permisos`;

-- Vendedor: permisos de ventas y operaciones básicas
INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT 2, id FROM `permisos`
WHERE (`modulo` = 'dashboard'     AND `accion` = 'ver')
   OR (`modulo` = 'ventas'        AND `accion` IN ('ver','crear','anular'))
   OR (`modulo` = 'cotizaciones'  AND `accion` IN ('ver','crear','editar','convertir'))
   OR (`modulo` = 'clientes'      AND `accion` IN ('ver','crear','editar'))
   OR (`modulo` = 'productos'     AND `accion` IN ('ver'))
   OR (`modulo` = 'caja'          AND `accion` IN ('ver','abrir','cerrar'))
   OR (`modulo` = 'sunat'         AND `accion` IN ('ver'))
   OR (`modulo` = 'reportes'      AND `accion` IN ('ventas'));

-- Almacenero: inventario y compras
INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT 3, id FROM `permisos`
WHERE (`modulo` = 'dashboard'     AND `accion` = 'ver')
   OR (`modulo` = 'productos'     AND `accion` IN ('ver','crear','editar','ajustar_stock'))
   OR (`modulo` = 'compras'       AND `accion` IN ('ver','crear','editar','recibir'))
   OR (`modulo` = 'proveedores'   AND `accion` IN ('ver','crear','editar'))
   OR (`modulo` = 'reportes'      AND `accion` IN ('inventario','compras'));

-- Contador: solo lectura y reportes
INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT 4, id FROM `permisos`
WHERE (`modulo` = 'dashboard'     AND `accion` = 'ver')
   OR (`modulo` = 'ventas'        AND `accion` IN ('ver','exportar'))
   OR (`modulo` = 'compras'       AND `accion` = 'ver')
   OR (`modulo` = 'sunat'         AND `accion` = 'ver')
   OR (`modulo` = 'reportes'      AND `accion` IN ('ventas','inventario','compras','financiero','exportar'));

-- ============================================================
-- 4. USUARIO ADMINISTRADOR POR DEFECTO
-- Credenciales: admin / admin123
-- Hash bcrypt generado con cost=12
-- IMPORTANTE: Cambiar contraseña en el primer acceso
-- ============================================================

INSERT INTO `usuarios` (
    `nombre`,
    `apellidos`,
    `email`,
    `username`,
    `password`,
    `rol_id`,
    `activo`,
    `tema`
) VALUES (
    'Administrador',
    'Sistema',
    'admin@empresa.com',
    'admin',
    -- Hash bcrypt de 'admin123' con cost=12
    '$2y$12$7AcwMRTInBvb7.Q5dXlNEOgdmlPwcG4ZfRptsREq5bxUTp0A4/7U2',
    1,
    1,
    'light'
);

-- ============================================================
-- 5. CATEGORÍAS DE PRODUCTOS
-- Empresa de informática / tecnología en Pucallpa
-- ============================================================

-- Categorías principales (parent_id = NULL)
INSERT INTO `categorias` (`id`, `nombre`, `descripcion`, `parent_id`, `icono`, `color`, `orden`, `activo`) VALUES
-- Nivel 1: Categorías principales
(1,  'Cámaras de Seguridad',       'Sistemas CCTV, cámaras IP, analógicas y accesorios',             NULL, 'camera',        '#EF4444', 1,  1),
(2,  'Equipos de Red',             'Cables, conectores, switches, routers y puntos de acceso',        NULL, 'wifi',          '#3B82F6', 2,  1),
(3,  'Memorias RAM',               'Módulos de memoria RAM para escritorio y laptop',                 NULL, 'cpu-chip',      '#8B5CF6', 3,  1),
(4,  'Almacenamiento',             'Discos duros, SSD y memorias USB',                               NULL, 'server',        '#F59E0B', 4,  1),
(5,  'Fuentes de Poder',           'Fuentes de alimentación para PC y servidores',                    NULL, 'bolt',          '#10B981', 5,  1),
(6,  'Tarjetas Gráficas',          'GPUs NVIDIA y AMD para gaming y diseño',                          NULL, 'squares-2x2',   '#EC4899', 6,  1),
(7,  'Procesadores',               'CPUs Intel y AMD',                                                NULL, 'cpu-chip',      '#6366F1', 7,  1),
(8,  'Laptops y Computadoras',     'Laptops, desktops y all-in-one',                                  NULL, 'computer-desktop','#0EA5E9',8,  1),
(9,  'Periféricos',                'Mouse, teclados, audífonos, webcams y más',                       NULL, 'cursor-arrow-rays','#84CC16',9, 1),
(10, 'Accesorios USB',             'Hubs USB, adaptadores, docks y splitters',                        NULL, 'plug',          '#F97316', 10, 1),
(11, 'Software y Licencias',       'Licencias de software, antivirus y sistemas operativos',          NULL, 'code-bracket',  '#14B8A6', 11, 1),
(12, 'Mantenimiento y Servicios',  'Servicios técnicos, limpieza, reparación y configuración',        NULL, 'wrench-screwdriver','#A78BFA',12, 1),
(13, 'Monitores y Pantallas',      'Monitores LED, LCD y pantallas táctiles',                         NULL, 'tv',            '#FB923C', 13, 1),
(14, 'Impresoras y Escáneres',     'Impresoras de inyección, láser y escáneres',                      NULL, 'printer',       '#4ADE80', 14, 1),
(15, 'UPS y Estabilizadores',      'Sistemas de alimentación ininterrumpida y estabilizadores',       NULL, 'battery-100',   '#2DD4BF', 15, 1),
(16, 'Componentes PC',             'Placas madre, gabinetes, coolers y accesorios internos',          NULL, 'cog',           '#F43F5E', 16, 1),
(17, 'Telefonía',                  'Celulares, accesorios para celular y telefonía IP',               NULL, 'phone',         '#60A5FA', 17, 1),
(18, 'Seguridad Informática',      'Antivirus, firewalls físicos y tokens de seguridad',              NULL, 'shield-check',  '#34D399', 18, 1),

-- ============================================================
-- Subcategorías: Cámaras de Seguridad (parent_id=1)
(101, 'Cámaras IP / PoE',        'Cámaras de red con protocolo IP y alimentación PoE',   1, NULL, NULL, 1, 1),
(102, 'Cámaras Analógicas',      'Cámaras HDCVI, AHD, TVI para DVR',                    1, NULL, NULL, 2, 1),
(103, 'Cámaras Tipo Domo',       'Cámaras en formato domo (interior/exterior)',           1, NULL, NULL, 3, 1),
(104, 'Cámaras Tipo Bala',       'Cámaras en formato bala para exterior',                1, NULL, NULL, 4, 1),
(105, 'Cámaras PTZ',             'Cámaras motorizadas Pan-Tilt-Zoom',                    1, NULL, NULL, 5, 1),
(106, 'DVR / NVR / Grabadores',  'Grabadores de video digital y en red',                 1, NULL, NULL, 6, 1),
(107, 'Accesorios CCTV',         'Fuentes, soportes, conectores para CCTV',              1, NULL, NULL, 7, 1),

-- Subcategorías: Equipos de Red (parent_id=2)
(201, 'Cables Ethernet Cat5e',   'Cable UTP Cat5e por metro o en rollo',                 2, NULL, NULL, 1, 1),
(202, 'Cables Ethernet Cat6',    'Cable UTP Cat6 para Gigabit Ethernet',                 2, NULL, NULL, 2, 1),
(203, 'Cables Ethernet Cat6a',   'Cable UTP Cat6a para 10 Gigabit',                      2, NULL, NULL, 3, 1),
(204, 'Cables Ethernet Cat7',    'Cable Cat7 apantallado para alta velocidad',           2, NULL, NULL, 4, 1),
(205, 'Conectores RJ45',         'Conectores RJ45 y herramientas de crimpado',           2, NULL, NULL, 5, 1),
(206, 'Conectores RJ11',         'Conectores RJ11 para telefonía',                       2, NULL, NULL, 6, 1),
(207, 'Switches',                'Switches no administrables y administrables',           2, NULL, NULL, 7, 1),
(208, 'Routers',                 'Routers SOHO y empresariales',                         2, NULL, NULL, 8, 1),
(209, 'Access Points',           'Puntos de acceso WiFi interior/exterior',              2, NULL, NULL, 9, 1),
(210, 'Patch Panels',            'Paneles de parcheo Cat5e/Cat6',                        2, NULL, NULL, 10, 1),
(211, 'Fibra Óptica',            'Cable, conectores y transceptores de fibra óptica',    2, NULL, NULL, 11, 1),

-- Subcategorías: Memorias RAM (parent_id=3)
(301, 'DDR4 Desktop',            'Memoria DDR4 para computadoras de escritorio',         3, NULL, NULL, 1, 1),
(302, 'DDR4 Laptop',             'Memoria DDR4 SO-DIMM para laptops',                   3, NULL, NULL, 2, 1),
(303, 'DDR5 Desktop',            'Memoria DDR5 última generación para desktop',          3, NULL, NULL, 3, 1),
(304, 'DDR5 Laptop',             'Memoria DDR5 SO-DIMM para laptops recientes',          3, NULL, NULL, 4, 1),
(305, 'DDR3 Legacy',             'Memoria DDR3 para equipos de generación anterior',     3, NULL, NULL, 5, 1),

-- Subcategorías: Almacenamiento (parent_id=4)
(401, 'SSD SATA',               'Discos SSD interfaz SATA 2.5"',                        4, NULL, NULL, 1, 1),
(402, 'SSD NVMe M.2',           'Discos SSD NVMe formato M.2',                          4, NULL, NULL, 2, 1),
(403, 'HDD Desktop 3.5"',       'Discos duros para escritorio 3.5 pulgadas',            4, NULL, NULL, 3, 1),
(404, 'HDD Laptop 2.5"',        'Discos duros portátiles 2.5 pulgadas',                 4, NULL, NULL, 4, 1),
(405, 'Memorias USB',           'Memorias flash USB 2.0 y 3.0',                         4, NULL, NULL, 5, 1),
(406, 'Tarjetas SD / MicroSD',  'Tarjetas de memoria SD y MicroSD',                     4, NULL, NULL, 6, 1),
(407, 'Discos Externos',        'Discos duros externos portátiles',                      4, NULL, NULL, 7, 1),

-- Subcategorías: Fuentes de Poder (parent_id=5)
(501, 'Fuentes 80 Plus Bronze',  'Fuentes certificadas 80 Plus Bronze',                  5, NULL, NULL, 1, 1),
(502, 'Fuentes 80 Plus Gold',    'Fuentes certificadas 80 Plus Gold (alta eficiencia)',  5, NULL, NULL, 2, 1),
(503, 'Fuentes 80 Plus Platinum','Fuentes certificadas 80 Plus Platinum',               5, NULL, NULL, 3, 1),
(504, 'Fuentes Sin Certificación','Fuentes básicas sin certificación 80 Plus',          5, NULL, NULL, 4, 1),
(505, 'Fuentes Modular',         'Fuentes modulares y semi-modulares',                   5, NULL, NULL, 5, 1),

-- Subcategorías: Tarjetas Gráficas (parent_id=6)
(601, 'GPU NVIDIA GeForce',      'Tarjetas gráficas NVIDIA GeForce para gaming',        6, NULL, NULL, 1, 1),
(602, 'GPU NVIDIA RTX',          'Tarjetas NVIDIA RTX con Ray Tracing',                 6, NULL, NULL, 2, 1),
(603, 'GPU AMD Radeon',          'Tarjetas gráficas AMD Radeon',                        6, NULL, NULL, 3, 1),
(604, 'GPU para Workstation',    'Tarjetas profesionales NVIDIA Quadro / AMD Radeon Pro',6, NULL, NULL, 4, 1),

-- Subcategorías: Periféricos (parent_id=9)
(901, 'Mouse',                   'Mouse cableado e inalámbrico',                        9, NULL, NULL, 1, 1),
(902, 'Teclados',                'Teclados membrana y mecánicos',                       9, NULL, NULL, 2, 1),
(903, 'Audífonos / Headsets',    'Audífonos gaming, estudio y comunicación',             9, NULL, NULL, 3, 1),
(904, 'Webcams',                 'Cámaras web para videoconferencia',                   9, NULL, NULL, 4, 1),
(905, 'Gamepads / Joysticks',    'Controles para gaming PC y consola',                  9, NULL, NULL, 5, 1),
(906, 'Alfombrillas',            'Mousepads y alfombrillas de escritorio',               9, NULL, NULL, 6, 1),

-- Subcategorías: Accesorios USB (parent_id=10)
(1001, 'Hubs USB',              'Concentradores USB 2.0 y 3.0',                        10, NULL, NULL, 1, 1),
(1002, 'Adaptadores USB-C',     'Adaptadores USB-C a HDMI, VGA, Ethernet, etc.',       10, NULL, NULL, 2, 1),
(1003, 'Cables USB',            'Cables USB Tipo-A, Tipo-C, Micro, Mini',              10, NULL, NULL, 3, 1),
(1004, 'Docking Stations',      'Estaciones de acoplamiento multifunción',              10, NULL, NULL, 4, 1),

-- Subcategorías: Software y Licencias (parent_id=11)
(1101, 'Windows',               'Licencias Microsoft Windows',                          11, NULL, NULL, 1, 1),
(1102, 'Microsoft Office',      'Licencias Microsoft 365 y Office',                     11, NULL, NULL, 2, 1),
(1103, 'Antivirus',             'Licencias de antivirus y seguridad',                   11, NULL, NULL, 3, 1),
(1104, 'Software Diseño',       'Adobe, CorelDRAW y otros',                            11, NULL, NULL, 4, 1),
(1105, 'Software Contabilidad', 'CONTASOL, CONCAR y otros',                            11, NULL, NULL, 5, 1),
(1106, 'Otros Software',        'Software diverso y licencias varias',                  11, NULL, NULL, 6, 1),

-- Subcategorías: Mantenimiento y Servicios (parent_id=12)
(1201, 'Mantenimiento PC',      'Servicio de mantenimiento preventivo y correctivo',    12, NULL, NULL, 1, 1),
(1202, 'Formateo e Instalación','Servicio de formateo e instalación de sistema',        12, NULL, NULL, 2, 1),
(1203, 'Recuperación de Datos', 'Servicio de recuperación de datos de discos',          12, NULL, NULL, 3, 1),
(1204, 'Instalación de Redes',  'Servicio de cableado estructurado e instalación',      12, NULL, NULL, 4, 1),
(1205, 'Configuración Equipos', 'Configuración de routers, switches, cámaras, etc.',   12, NULL, NULL, 5, 1),
(1206, 'Reparación Hardware',   'Reparación de placas, pantallas, teclados, etc.',     12, NULL, NULL, 6, 1),

-- Subcategorías: Componentes PC (parent_id=16)
(1601, 'Placas Madre',          'Motherboards Intel y AMD',                             16, NULL, NULL, 1, 1),
(1602, 'Gabinetes',             'Cases ATX, Micro-ATX y Mini-ITX',                     16, NULL, NULL, 2, 1),
(1603, 'Coolers y Refrigeración','Disipadores y refrigeración líquida para CPU',       16, NULL, NULL, 3, 1),
(1604, 'Pasta Térmica',         'Pasta térmica para procesadores',                      16, NULL, NULL, 4, 1),
(1605, 'Tarjetas de Sonido',    'Tarjetas de sonido externas e internas',               16, NULL, NULL, 5, 1);

-- ============================================================
-- 6. UNIDADES DE MEDIDA (Catálogo SUNAT)
-- Referencia: Tabla 6 Anexo V - Comprobantes de Pago SUNAT
-- ============================================================

INSERT INTO `unidades_medida` (`codigo`, `nombre`, `abreviatura`, `activo`) VALUES
('NIU', 'Unidad',                    'Und',    1),
('MTR', 'Metro',                     'm',      1),
('MTK', 'Metro Cuadrado',            'm²',     1),
('MTQ', 'Metro Cúbico',              'm³',     1),
('ZZ',  'Unidad de Servicio',        'Serv',   1),
('KGM', 'Kilogramo',                 'Kg',     1),
('GLL', 'Galón',                     'Gal',    1),
('LTR', 'Litro',                     'L',      1),
('MLT', 'Mililitro',                 'mL',     1),
('GRM', 'Gramo',                     'g',      1),
('TNE', 'Tonelada',                  'Ton',    1),
('HUR', 'Hora',                      'hr',     1),
('DAY', 'Día',                       'día',    1),
('MON', 'Mes',                       'mes',    1),
('ANN', 'Año',                       'año',    1),
('BX',  'Caja',                      'Cja',    1),
('BG',  'Bolsa',                     'Bls',    1),
('PK',  'Paquete',                   'Paq',    1),
('PR',  'Par',                       'Par',    1),
('DZN', 'Docena',                    'Doc',    1),
('GRS', 'Gruesa (144 unidades)',      'Grs',    1),
('SET', 'Juego / Set',               'Set',    1),
('ROL', 'Rollo',                     'Rol',    1),
('MTF', 'Metro Lineal de fibra',     'mtf',    1),
('PZA', 'Pieza',                     'Pza',    1);

-- ============================================================
-- 7. MARCAS / FABRICANTES
-- ============================================================

INSERT INTO `marcas` (`nombre`, `descripcion`, `activo`) VALUES
-- Redes y Cámaras
('TP-Link',      'Equipos de red, cámaras IP y accesorios',                        1),
('D-Link',       'Equipos de red y vigilancia',                                     1),
('Hikvision',    'Cámaras de seguridad y sistemas DVR/NVR',                         1),
('Dahua',        'Cámaras de seguridad y sistemas de videovigilancia',              1),
('Ubiquiti',     'Equipos de red UniFi y airMAX',                                  1),
('Mikrotik',     'Routers y equipos de red profesionales',                          1),
('Cisco',        'Equipos de red empresariales',                                    1),
('Netgear',      'Switches, routers y equipos de red',                              1),
('Tenda',        'Equipos de red económicos',                                       1),
-- Memorias y Almacenamiento
('Kingston',     'Memorias RAM, SSD y memorias USB',                                1),
('Corsair',      'Memorias RAM gaming, SSDs y periféricos',                         1),
('Samsung',      'SSD, memorias y pantallas',                                       1),
('Seagate',      'Discos duros HDD y SSD',                                          1),
('Western Digital (WD)', 'Discos duros, SSD y memorias flash',                     1),
('Crucial',      'Memorias RAM y SSD (marca de Micron)',                            1),
('Lexar',        'Memorias RAM, SSD y tarjetas de memoria',                         1),
('Sandisk',      'Memorias USB, tarjetas SD y SSD portátiles',                      1),
('G.Skill',      'Memorias RAM gaming de alto rendimiento',                         1),
('Patriot',      'Memorias RAM y SSD gaming',                                       1),
-- Procesadores
('Intel',        'Procesadores Core i3/i5/i7/i9',                                  1),
('AMD',          'Procesadores Ryzen y EPYC',                                       1),
-- Placas madre y GPU
('ASUS',         'Placas madre, laptops, GPUs y periféricos',                       1),
('MSI',          'Placas madre, GPUs gaming y laptops',                             1),
('Gigabyte',     'Placas madre, GPUs y accesorios',                                 1),
('ASRock',       'Placas madre económicas y de gama media',                         1),
('EVGA',         'Fuentes de poder y tarjetas gráficas NVIDIA',                    1),
('Sapphire',     'Tarjetas gráficas AMD Radeon',                                    1),
('Zotac',        'Tarjetas gráficas NVIDIA y mini PCs',                             1),
('XFX',          'Tarjetas gráficas AMD',                                           1),
-- Periféricos
('Logitech',     'Mouse, teclados, webcams y audífonos',                            1),
('Redragon',     'Periféricos gaming económicos',                                   1),
('Razer',        'Periféricos gaming de alto rendimiento',                          1),
('HyperX',       'Headsets, memorias y periféricos gaming',                         1),
('Genius',       'Periféricos y accesorios económicos',                             1),
-- Laptops y Desktops
('HP',           'Laptops, impresoras y periféricos',                               1),
('Dell',         'Laptops, desktops y monitores',                                   1),
('Lenovo',       'Laptops ThinkPad, IdeaPad y desktops',                            1),
('Acer',         'Laptops, monitores y proyectores',                                1),
('Apple',        'MacBook, iMac y accesorios',                                      1),
-- Monitores
('LG',           'Monitores, televisores y proyectores',                            1),
('BenQ',         'Monitores gaming, diseño y presentaciones',                       1),
('ViewSonic',    'Monitores y proyectores',                                         1),
-- Impresoras
('Epson',        'Impresoras EcoTank, ticketeras y escáneres',                      1),
('Brother',      'Impresoras láser y multifuncionales',                              1),
('Canon',        'Impresoras y escáneres',                                          1),
-- Fuentes de poder
('Thermaltake',  'Fuentes de poder, gabinetes y refrigeración',                    1),
('be quiet!',    'Fuentes de poder silenciosas y gabinetes',                        1),
('Seasonic',     'Fuentes de poder de alta calidad',                                1),
('Cougar',       'Fuentes, gabinetes y periféricos gaming',                         1),
('Aerocool',     'Fuentes y gabinetes económicos',                                  1),
-- UPS
('APC',          'UPS, reguladores y accesorios de energía',                        1),
('Eaton',        'UPS y soluciones de energía',                                     1),
('Forza',        'UPS y reguladores económicos',                                    1),
-- Genéricos
('Genérico',     'Productos sin marca específica',                                  1),
('Otras Marcas', 'Marcas varias no listadas',                                       1);

-- ============================================================
-- 8. PROVEEDOR INICIAL: DELTRON PERÚ
-- ============================================================

INSERT INTO `proveedores` (
    `tipo_doc`,
    `numero_doc`,
    `razon_social`,
    `nombre_comercial`,
    `direccion`,
    `distrito`,
    `provincia`,
    `departamento`,
    `telefono`,
    `email`,
    `web`,
    `contacto_nombre`,
    `moneda_preferida`,
    `activo`,
    `notas`
) VALUES
(
    6,
    '20338420822',
    'DELTRON S.A.',
    'Deltron Perú',
    'Av. Argentina 5285, Carmen de la Legua, Callao',
    'Carmen de la Legua',
    'Callao',
    'Callao',
    '(01) 5180000',
    'ventas@deltron.com.pe',
    'https://www.deltron.com.pe',
    'Ejecutivo de Ventas',
    'PEN',
    1,
    'Distribuidor mayorista de tecnología. Proveedor principal de componentes y periféricos.'
),
(
    6,
    '20503503399',
    'INTCOMEX PERU S.A.C.',
    'Intcomex',
    'Av. La Marina 2093, San Miguel, Lima',
    'San Miguel',
    'Lima',
    'Lima',
    '(01) 6127000',
    'ventas@intcomex.com',
    'https://www.intcomex.com',
    'Ejecutivo de Cuenta',
    'USD',
    1,
    'Distribuidor mayorista de tecnología. Especializado en redes y seguridad.'
),
(
    6,
    '20601287736',
    'INGRAM MICRO PERU S.R.L.',
    'Ingram Micro',
    'Av. Circunvalación del Golf Los Incas 134, Santiago de Surco, Lima',
    'Santiago de Surco',
    'Lima',
    'Lima',
    '(01) 6273900',
    'ventas.pe@ingrammicro.com',
    'https://www.ingrammicro.com',
    'Canal Manager',
    'USD',
    1,
    'Distribuidor mayorista. Proveedor de licencias Microsoft, Cisco y marca premium.'
),
(
    6,
    '20516756897',
    'COMPUGRAF S.A.C.',
    'Compugraf',
    'Jr. Ica 441, Cercado de Lima',
    'Cercado',
    'Lima',
    'Lima',
    '(01) 4281919',
    'ventas@compugraf.com.pe',
    'https://www.compugraf.com.pe',
    'Ventas',
    'PEN',
    1,
    'Distribuidor de consumibles e impresoras Epson y HP.'
);

-- ============================================================
-- 9. CLIENTE GENÉRICO (para ventas sin identificar)
-- ============================================================

INSERT INTO `clientes` (
    `id`,
    `tipo_doc`,
    `numero_doc`,
    `razon_social`,
    `nombres`,
    `apellidos`,
    `es_empresa`,
    `activo`,
    `notas`
) VALUES
(
    1,
    0,      -- Sin documento
    NULL,
    NULL,
    'Cliente',
    'General',
    0,
    1,
    'Cliente genérico para ventas al contado sin datos del comprador'
);

-- ============================================================
-- 10. CONFIGURACIÓN DE EMPRESA (ejemplo para personalizar)
-- ============================================================

INSERT INTO `configuracion_empresa` (
    `ruc`,
    `razon_social`,
    `nombre_comercial`,
    `direccion`,
    `ubigeo`,
    `distrito`,
    `provincia`,
    `departamento`,
    `telefono`,
    `email`,
    `web`,
    `moneda_principal`,
    `tipo_cambio_usd`,
    `igv_porcentaje`
) VALUES (
    '20600000000',            -- ⚠ REEMPLAZAR con RUC real
    'EMPRESA EJEMPLO S.A.C.', -- ⚠ REEMPLAZAR con razón social real
    'Tech Pucallpa',          -- ⚠ REEMPLAZAR con nombre comercial real
    'Jr. Comercio 123, Pucallpa',
    '250101',                 -- Ubigeo Pucallpa, Ucayali
    'Callería',
    'Coronel Portillo',
    'Ucayali',
    '(061) 000000',
    'info@empresa.com',
    'https://www.empresa.com',
    'PEN',
    3.7000,
    18.00
);

-- ============================================================
-- 11. CONFIGURACIÓN SUNAT (valores de ejemplo - beta por defecto)
-- ============================================================

INSERT INTO `configuracion_sunat` (
    `ruc`,
    `usuario_sol`,
    `clave_sol_encrypted`,
    `modo`,
    `serie_factura`,
    `serie_boleta`,
    `serie_nota_credito_f`,
    `serie_nota_credito_b`,
    `serie_nota_debito_f`,
    `serie_nota_debito_b`,
    `correlativo_factura`,
    `correlativo_boleta`,
    `correlativo_nc_f`,
    `correlativo_nc_b`,
    `correlativo_nd_f`,
    `correlativo_nd_b`
) VALUES (
    '20600000000',
    'USUARIOSOL',       -- ⚠ REEMPLAZAR
    'CLAVE_ENCRIPTADA', -- ⚠ Se encriptará desde el instalador
    'beta',             -- Iniciar en modo beta/pruebas
    'F001',
    'B001',
    'FC01',
    'BC01',
    'FD01',
    'BD01',
    1,
    1,
    1,
    1,
    1,
    1
);

-- ============================================================
-- 12. SERIES DE COMPROBANTES
-- ============================================================

INSERT INTO `configuracion_series` (`tipo_comprobante`, `serie`, `correlativo`, `activo`, `es_principal`) VALUES
('01', 'F001', 1, 1, 1),  -- Factura principal
('03', 'B001', 1, 1, 1),  -- Boleta principal
('07', 'FC01', 1, 1, 0),  -- NC sobre factura
('07', 'BC01', 1, 1, 0),  -- NC sobre boleta
('08', 'FD01', 1, 1, 0),  -- ND sobre factura
('08', 'BD01', 1, 1, 0),  -- ND sobre boleta
('NV', 'NV01', 1, 1, 1);  -- Nota de venta (sin valor tributario)

-- ============================================================
-- 13. CAJA PRINCIPAL
-- ============================================================

INSERT INTO `cajas` (`id`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'Caja 1',        'Caja principal de ventas',  1),
(2, 'Caja 2',        'Caja secundaria',            0),
(3, 'Caja Online',   'Ventas remotas y delivery',  0);

-- ============================================================
-- 14. TIPO DE CAMBIO INICIAL
-- ============================================================

INSERT INTO `tipo_cambio_historico` (`fecha`, `usd_a_pen`, `pen_a_usd`, `fuente`) VALUES
(CURDATE(), 3.7000, 0.270270, 'manual');

-- ============================================================
-- 15. PRODUCTOS DE EJEMPLO (informática)
-- ============================================================

-- Obtener IDs de unidades para referencia
-- NIU = Unidad, MTR = Metro, ZZ = Servicio, ROL = Rollo

INSERT INTO `productos` (
    `codigo_interno`,
    `codigo_barras`,
    `codigo_barras_tipo`,
    `nombre`,
    `descripcion`,
    `categoria_id`,
    `marca_id`,
    `unidad_medida_id`,
    `precio_compra_pen`,
    `precio_venta_pen`,
    `precio_mayorista_pen`,
    `stock_actual`,
    `stock_minimo`,
    `aplica_igv`,
    `tipo_afectacion_igv`,
    `activo`,
    `proveedor_principal_id`
) VALUES
-- Cables (MTR=metro, ROL=rollo)
('CAB-CAT6-001', NULL, NULL,
 'Cable UTP Cat6 (precio x metro)',
 'Cable de red UTP Cat6 Exterior/Interior - precio por metro lineal. Ideal para instalaciones de redes Gigabit.',
 202, -- Cat6
 (SELECT id FROM marcas WHERE nombre = 'Genérico' LIMIT 1),
 (SELECT id FROM unidades_medida WHERE codigo = 'MTR' LIMIT 1),
 1.20, 2.50, 2.00, 500.00, 100.00, 1, '10', 1, 1),

('CAB-CAT6-ROL', NULL, NULL,
 'Cable UTP Cat6 Rollo x 305m',
 'Caja/Rollo de cable UTP Cat6 de 305 metros. Certificado Fluke. Ideal para proyectos de cableado.',
 202,
 (SELECT id FROM marcas WHERE nombre = 'Genérico' LIMIT 1),
 (SELECT id FROM unidades_medida WHERE codigo = 'ROL' LIMIT 1),
 180.00, 320.00, 290.00, 15.00, 3.00, 1, '10', 1, 1),

-- Conectores
('CON-RJ45-001', NULL, NULL,
 'Conector RJ45 Cat6 (precio x unidad)',
 'Conector RJ45 Cat6 UTP para crimpar. Pack disponible.',
 205,
 (SELECT id FROM marcas WHERE nombre = 'Genérico' LIMIT 1),
 (SELECT id FROM unidades_medida WHERE codigo = 'NIU' LIMIT 1),
 0.30, 0.80, 0.60, 1000.00, 200.00, 1, '10', 1, 1),

-- Memorias USB
('MEM-USB-001', '4895028530015', 'EAN13',
 'Memoria USB Kingston 32GB USB 3.0',
 'Pendrive Kingston DataTraveler 32GB USB 3.0. Velocidad lectura hasta 100MB/s.',
 405,
 (SELECT id FROM marcas WHERE nombre = 'Kingston' LIMIT 1),
 (SELECT id FROM unidades_medida WHERE codigo = 'NIU' LIMIT 1),
 18.00, 35.00, 30.00, 50.00, 10.00, 1, '10', 1, 1),

-- SSD
('ALM-SSD-001', NULL, NULL,
 'SSD Kingston A400 240GB SATA',
 'Disco de estado sólido Kingston A400 240GB SATA 2.5". Velocidad lectura 500MB/s, escritura 350MB/s.',
 401,
 (SELECT id FROM marcas WHERE nombre = 'Kingston' LIMIT 1),
 (SELECT id FROM unidades_medida WHERE codigo = 'NIU' LIMIT 1),
 85.00, 149.00, 130.00, 20.00, 5.00, 1, '10', 1, 1),

-- Memorias RAM
('RAM-DDR4-001', NULL, NULL,
 'Memoria RAM Kingston 8GB DDR4 2666MHz',
 'Módulo de memoria Kingston ValueRAM 8GB DDR4 2666MHz. Compatible con la mayoría de motherboards Intel y AMD.',
 301,
 (SELECT id FROM marcas WHERE nombre = 'Kingston' LIMIT 1),
 (SELECT id FROM unidades_medida WHERE codigo = 'NIU' LIMIT 1),
 80.00, 135.00, 120.00, 15.00, 5.00, 1, '10', 1, 1),

-- Cámara IP
('CAM-IP-001', NULL, NULL,
 'Cámara IP TP-Link Tapo C200 1080p',
 'Cámara IP WiFi TP-Link Tapo C200. Resolución Full HD 1080p, visión nocturna, detección de movimiento, audio bidireccional.',
 101,
 (SELECT id FROM marcas WHERE nombre = 'TP-Link' LIMIT 1),
 (SELECT id FROM unidades_medida WHERE codigo = 'NIU' LIMIT 1),
 120.00, 189.00, 170.00, 10.00, 3.00, 1, '10', 1, 1),

-- Switch
('RED-SW-001', NULL, NULL,
 'Switch TP-Link 8 puertos 10/100Mbps',
 'Switch no administrable TP-Link TL-SF1008D 8 puertos 10/100Mbps. Plug & Play, sin configuración.',
 207,
 (SELECT id FROM marcas WHERE nombre = 'TP-Link' LIMIT 1),
 (SELECT id FROM unidades_medida WHERE codigo = 'NIU' LIMIT 1),
 35.00, 65.00, 55.00, 20.00, 5.00, 1, '10', 1, 1),

-- Mouse
('PER-MOU-001', NULL, NULL,
 'Mouse Logitech M90 USB Óptico',
 'Mouse óptico con cable USB Logitech M90. 1000 DPI, ambidiestro, plug & play.',
 901,
 (SELECT id FROM marcas WHERE nombre = 'Logitech' LIMIT 1),
 (SELECT id FROM unidades_medida WHERE codigo = 'NIU' LIMIT 1),
 20.00, 38.00, 33.00, 30.00, 10.00, 1, '10', 1, 1),

-- Servicio de mantenimiento (exonerado IGV para algunos casos o gravado)
('SER-MTT-001', NULL, NULL,
 'Servicio de Mantenimiento PC',
 'Servicio de mantenimiento preventivo: limpieza interna, aplicación pasta térmica, revisión general.',
 1201,
 (SELECT id FROM marcas WHERE nombre = 'Genérico' LIMIT 1),
 (SELECT id FROM unidades_medida WHERE codigo = 'ZZ' LIMIT 1),
 0.00, 50.00, 40.00, 9999.00, 0.00, 1, '10', 1, 1);

-- ============================================================
-- 16. LOG INICIAL DEL SISTEMA
-- ============================================================

INSERT INTO `logs_sistema` (`usuario_id`, `accion`, `modulo`, `descripcion`, `ip`) VALUES
(1, 'instalacion', 'sistema', 'Sistema instalado y configurado exitosamente. Base de datos inicializada con datos por defecto.', '127.0.0.1');

-- ============================================================
-- RESTAURAR CONFIGURACIÓN
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN DE DATOS INICIALES
-- ============================================================
-- NOTAS IMPORTANTES DESPUÉS DE LA INSTALACIÓN:
-- 1. Cambiar la contraseña del usuario 'admin' en el primer acceso
-- 2. Actualizar RUC y datos de la empresa en configuracion_empresa
-- 3. Ingresar credenciales SOL reales en configuracion_sunat
-- 4. Subir certificado digital (.p12/.pfx) para firma XML
-- 5. Activar modo 'produccion' solo cuando se hayan probado en beta
-- ============================================================
