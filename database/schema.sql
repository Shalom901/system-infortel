-- ============================================================
-- SISTEMA DE FACTURACIÓN ELECTRÓNICA - PUCALLPA
-- Schema completo de base de datos MySQL
-- Compatible con: MySQL 8.0+ / MariaDB 10.6+
-- Charset: utf8mb4 (soporte completo Unicode/Emoji)
-- Autor: Sistema Facturación Pucallpa
-- Fecha: 2024
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "-05:00"; -- Hora Perú (UTC-5)
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- Eliminar base de datos si existe y recrear (solo para instalación limpia)
-- DROP DATABASE IF EXISTS facturacion_pucallpa;
-- CREATE DATABASE facturacion_pucallpa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE facturacion_pucallpa;

-- ============================================================
-- SECCIÓN 1: AUTENTICACIÓN Y USUARIOS
-- ============================================================

-- Tabla: roles
-- Descripción: Roles del sistema (Administrador, Vendedor, etc.)
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id`          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(50)      NOT NULL COMMENT 'Nombre del rol: Administrador, Vendedor',
    `descripcion` VARCHAR(255)     NULL     COMMENT 'Descripción del rol y sus permisos generales',
    `es_sistema`  TINYINT(1)       NOT NULL DEFAULT 0 COMMENT 'Rol del sistema no eliminable',
    `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_roles_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Roles de acceso al sistema';

-- Tabla: permisos
-- Descripción: Permisos granulares por módulo y acción
DROP TABLE IF EXISTS `permisos`;
CREATE TABLE `permisos` (
    `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `modulo`      VARCHAR(50)       NOT NULL COMMENT 'Módulo: ventas, compras, productos, usuarios, reportes, etc.',
    `accion`      VARCHAR(50)       NOT NULL COMMENT 'Acción: ver, crear, editar, eliminar, exportar, anular',
    `descripcion` VARCHAR(255)      NULL     COMMENT 'Descripción legible del permiso',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_permisos_modulo_accion` (`modulo`, `accion`),
    KEY `idx_permisos_modulo` (`modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Permisos granulares del sistema';

-- Tabla: usuarios
-- Descripción: Usuarios del sistema con autenticación bcrypt
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `nombre`           VARCHAR(100)     NOT NULL COMMENT 'Nombre(s) del usuario',
    `apellidos`        VARCHAR(100)     NOT NULL COMMENT 'Apellidos del usuario',
    `email`            VARCHAR(150)     NOT NULL COMMENT 'Correo electrónico único',
    `username`         VARCHAR(50)      NOT NULL COMMENT 'Nombre de usuario único para login',
    `password`         VARCHAR(255)     NOT NULL COMMENT 'Hash bcrypt de la contraseña',
    `rol_id`           TINYINT UNSIGNED NOT NULL COMMENT 'FK a roles',
    `activo`           TINYINT(1)       NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo/bloqueado',
    `tema`             ENUM('light','dark') NOT NULL DEFAULT 'light' COMMENT 'Preferencia de tema visual',
    `intentos_login`   TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Intentos fallidos consecutivos',
    `bloqueado_hasta`  DATETIME         NULL     COMMENT 'Bloqueo temporal por intentos fallidos',
    `ultimo_acceso`    DATETIME         NULL     COMMENT 'Fecha/hora del último login exitoso',
    `avatar_path`      VARCHAR(255)     NULL     COMMENT 'Ruta a imagen de avatar',
    `token_reset`      VARCHAR(100)     NULL     COMMENT 'Token para reset de contraseña',
    `token_reset_exp`  DATETIME         NULL     COMMENT 'Expiración del token de reset',
    `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_usuarios_email`    (`email`),
    UNIQUE KEY `uk_usuarios_username` (`username`),
    KEY `idx_usuarios_rol_id`         (`rol_id`),
    KEY `idx_usuarios_activo`         (`activo`),
    CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios del sistema con autenticación';

-- Tabla: usuario_permisos
-- Descripción: Permisos específicos asignados a un usuario (override del rol)
DROP TABLE IF EXISTS `usuario_permisos`;
CREATE TABLE `usuario_permisos` (
    `usuario_id`  INT UNSIGNED      NOT NULL,
    `permiso_id`  SMALLINT UNSIGNED NOT NULL,
    `concedido`   TINYINT(1)        NOT NULL DEFAULT 1 COMMENT '1=concedido explícito, 0=denegado explícito',
    `created_at`  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`usuario_id`, `permiso_id`),
    KEY `idx_usuario_permisos_permiso` (`permiso_id`),
    CONSTRAINT `fk_up_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`  (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_up_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos`  (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Permisos individuales por usuario (override de rol)';

-- Tabla: rol_permisos
-- Descripción: Permisos asignados a cada rol
DROP TABLE IF EXISTS `rol_permisos`;
CREATE TABLE `rol_permisos` (
    `rol_id`     TINYINT UNSIGNED  NOT NULL,
    `permiso_id` SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (`rol_id`, `permiso_id`),
    KEY `idx_rol_permisos_permiso` (`permiso_id`),
    CONSTRAINT `fk_rp_rol`     FOREIGN KEY (`rol_id`)     REFERENCES `roles`    (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rp_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Permisos asignados a roles';

-- ============================================================
-- SECCIÓN 2: CONFIGURACIÓN DEL SISTEMA
-- ============================================================

-- Tabla: configuracion_empresa
-- Descripción: Datos de la empresa emisora (solo 1 registro)
DROP TABLE IF EXISTS `configuracion_empresa`;
CREATE TABLE `configuracion_empresa` (
    `id`                  TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ruc`                 CHAR(11)         NOT NULL COMMENT 'RUC de 11 dígitos',
    `razon_social`        VARCHAR(150)     NOT NULL COMMENT 'Razón social registrada en SUNAT',
    `nombre_comercial`    VARCHAR(150)     NULL     COMMENT 'Nombre comercial o marca',
    `direccion`           VARCHAR(300)     NOT NULL COMMENT 'Dirección fiscal completa',
    `ubigeo`              CHAR(6)          NULL     COMMENT 'Código UBIGEO de 6 dígitos',
    `distrito`            VARCHAR(100)     NULL,
    `provincia`           VARCHAR(100)     NULL,
    `departamento`        VARCHAR(100)     NULL,
    `telefono`            VARCHAR(20)      NULL,
    `email`               VARCHAR(150)     NULL     COMMENT 'Email de contacto de la empresa',
    `web`                 VARCHAR(150)     NULL,
    `logo_path`           VARCHAR(255)     NULL     COMMENT 'Ruta relativa al logo',
    `moneda_principal`    CHAR(3)          NOT NULL DEFAULT 'PEN' COMMENT 'Moneda principal: PEN o USD',
    `tipo_cambio_usd`     DECIMAL(8,4)     NOT NULL DEFAULT 3.7000 COMMENT 'Tipo de cambio USD→PEN por defecto',
    `igv_porcentaje`      DECIMAL(5,2)     NOT NULL DEFAULT 18.00 COMMENT 'Porcentaje IGV (18.00)',
    `created_at`          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_empresa_ruc` (`ruc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configuración de la empresa emisora (registro único)';

-- Tabla: configuracion_sunat
-- Descripción: Credenciales y series para SUNAT (solo 1 registro activo)
DROP TABLE IF EXISTS `configuracion_sunat`;
CREATE TABLE `configuracion_sunat` (
    `id`                       TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ruc`                      CHAR(11)         NOT NULL COMMENT 'RUC del emisor (mismo que empresa)',
    `usuario_sol`              VARCHAR(20)      NOT NULL COMMENT 'Usuario SOL SUNAT',
    `clave_sol_encrypted`      VARCHAR(500)     NOT NULL COMMENT 'Clave SOL cifrada con AES-256',
    `modo`                     ENUM('beta','produccion') NOT NULL DEFAULT 'beta' COMMENT 'Ambiente SUNAT',
    `certificado_path`         VARCHAR(255)     NULL COMMENT 'Ruta al certificado digital .p12/.pfx',
    `certificado_pass_encrypted` VARCHAR(500)   NULL COMMENT 'Contraseña del certificado cifrada',
    `serie_factura`            CHAR(4)          NOT NULL DEFAULT 'F001' COMMENT 'Serie para facturas (F001)',
    `serie_boleta`             CHAR(4)          NOT NULL DEFAULT 'B001' COMMENT 'Serie para boletas (B001)',
    `serie_nota_credito_f`     CHAR(4)          NOT NULL DEFAULT 'FC01' COMMENT 'Serie NC sobre facturas',
    `serie_nota_credito_b`     CHAR(4)          NOT NULL DEFAULT 'BC01' COMMENT 'Serie NC sobre boletas',
    `serie_nota_debito_f`      CHAR(4)          NOT NULL DEFAULT 'FD01' COMMENT 'Serie ND sobre facturas',
    `serie_nota_debito_b`      CHAR(4)          NOT NULL DEFAULT 'BD01' COMMENT 'Serie ND sobre boletas',
    `correlativo_factura`      INT UNSIGNED     NOT NULL DEFAULT 1,
    `correlativo_boleta`       INT UNSIGNED     NOT NULL DEFAULT 1,
    `correlativo_nc_f`         INT UNSIGNED     NOT NULL DEFAULT 1,
    `correlativo_nc_b`         INT UNSIGNED     NOT NULL DEFAULT 1,
    `correlativo_nd_f`         INT UNSIGNED     NOT NULL DEFAULT 1,
    `correlativo_nd_b`         INT UNSIGNED     NOT NULL DEFAULT 1,
    `endpoint_produccion`      VARCHAR(255)     NOT NULL DEFAULT 'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',
    `endpoint_beta`            VARCHAR(255)     NOT NULL DEFAULT 'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService',
    `created_at`               TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configuración de credenciales y series SUNAT';

-- Tabla: configuracion_series
-- Descripción: Series adicionales configurables por tipo de comprobante
DROP TABLE IF EXISTS `configuracion_series`;
CREATE TABLE `configuracion_series` (
    `id`               SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tipo_comprobante` ENUM('01','03','07','08','NV','GR') NOT NULL COMMENT '01=Factura,03=Boleta,07=NC,08=ND,NV=Nota Venta,GR=Guía Remisión',
    `serie`            CHAR(4)           NOT NULL,
    `correlativo`      INT UNSIGNED      NOT NULL DEFAULT 1,
    `activo`           TINYINT(1)        NOT NULL DEFAULT 1,
    `es_principal`     TINYINT(1)        NOT NULL DEFAULT 0 COMMENT 'Serie principal para el tipo',
    `created_at`       TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_series_tipo_serie` (`tipo_comprobante`, `serie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Series de comprobantes configurables';

-- ============================================================
-- SECCIÓN 3: CATÁLOGO DE PRODUCTOS
-- ============================================================

-- Tabla: categorias
-- Descripción: Jerarquía de categorías de productos (hasta 2 niveles)
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
    `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(100)      NOT NULL,
    `descripcion` VARCHAR(255)      NULL,
    `parent_id`   SMALLINT UNSIGNED NULL COMMENT 'NULL=categoría raíz, si tiene valor=subcategoría',
    `icono`       VARCHAR(50)       NULL COMMENT 'Clase de icono (heroicons, etc.)',
    `color`       VARCHAR(7)        NULL COMMENT 'Color hex p.ej. #3B82F6',
    `orden`       TINYINT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'Orden de presentación',
    `activo`      TINYINT(1)        NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_categorias_parent`  (`parent_id`),
    KEY `idx_categorias_activo`  (`activo`),
    KEY `idx_categorias_orden`   (`orden`),
    CONSTRAINT `fk_categorias_parent` FOREIGN KEY (`parent_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Categorías y subcategorías de productos';

-- Tabla: unidades_medida
-- Descripción: Unidades de medida según catálogo SUNAT
DROP TABLE IF EXISTS `unidades_medida`;
CREATE TABLE `unidades_medida` (
    `id`           SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `codigo`       VARCHAR(3)        NOT NULL COMMENT 'Código SUNAT: NIU, MTR, ZZ, GLL, KGM, etc.',
    `nombre`       VARCHAR(100)      NOT NULL COMMENT 'Nombre: Unidad, Metro, Servicio, etc.',
    `abreviatura`  VARCHAR(10)       NOT NULL COMMENT 'Abreviatura: Und, m, Serv, etc.',
    `activo`       TINYINT(1)        NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_unidades_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Unidades de medida catálogo SUNAT';

-- Tabla: marcas
-- Descripción: Marcas/fabricantes de productos
DROP TABLE IF EXISTS `marcas`;
CREATE TABLE `marcas` (
    `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(100)      NOT NULL,
    `descripcion` VARCHAR(255)      NULL,
    `logo_path`   VARCHAR(255)      NULL,
    `activo`      TINYINT(1)        NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_marcas_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Marcas y fabricantes de productos';

-- Tabla: productos
-- Descripción: Catálogo principal de productos con precios y stock
DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos` (
    `id`                    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `codigo_interno`        VARCHAR(30)       NOT NULL COMMENT 'Código único interno del producto',
    `codigo_barras`         VARCHAR(30)       NULL     COMMENT 'Código de barras EAN13/CODE128/etc.',
    `codigo_barras_tipo`    ENUM('EAN8','EAN13','CODE128','CODE39','QR','UPC','OTRO') NULL,
    `nombre`                VARCHAR(200)      NOT NULL COMMENT 'Nombre del producto',
    `descripcion`           TEXT              NULL     COMMENT 'Descripción detallada',
    `categoria_id`          SMALLINT UNSIGNED NULL,
    `marca_id`              SMALLINT UNSIGNED NULL,
    `unidad_medida_id`      SMALLINT UNSIGNED NOT NULL,
    -- Precios de compra
    `precio_compra_pen`     DECIMAL(12,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Precio compra en soles',
    `precio_compra_usd`     DECIMAL(12,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Precio compra en dólares',
    -- Precios de venta
    `precio_venta_pen`      DECIMAL(12,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Precio venta al público en soles',
    `precio_venta_usd`      DECIMAL(12,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Precio venta al público en dólares',
    `precio_mayorista_pen`  DECIMAL(12,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Precio mayorista en soles',
    -- Stock
    `stock_actual`          DECIMAL(12,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Stock actual disponible',
    `stock_minimo`          DECIMAL(12,4)     NOT NULL DEFAULT 1.0000 COMMENT 'Stock mínimo (alerta)',
    `stock_maximo`          DECIMAL(12,4)     NULL     COMMENT 'Stock máximo (para compras)',
    -- IGV y afectación tributaria
    `aplica_igv`            TINYINT(1)        NOT NULL DEFAULT 1  COMMENT '1=afecto IGV, 0=exonerado/inafecto',
    `tipo_afectacion_igv`   VARCHAR(2)        NOT NULL DEFAULT '10' COMMENT '10=Gravado, 20=Exonerado, 30=Inafecto, 40=Exportación',
    -- Imágenes y medios
    `imagen_path`           VARCHAR(255)      NULL COMMENT 'Ruta relativa a imagen principal',
    -- Estado y relaciones
    `activo`                TINYINT(1)        NOT NULL DEFAULT 1,
    `proveedor_principal_id` INT UNSIGNED     NULL COMMENT 'Proveedor principal del producto',
    `peso_kg`               DECIMAL(8,3)      NULL COMMENT 'Peso en kilogramos',
    `notas`                 TEXT              NULL,
    `created_at`            TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_productos_codigo_interno`  (`codigo_interno`),
    KEY `idx_productos_codigo_barras`         (`codigo_barras`),
    KEY `idx_productos_nombre`                (`nombre`),
    KEY `idx_productos_categoria`             (`categoria_id`),
    KEY `idx_productos_marca`                 (`marca_id`),
    KEY `idx_productos_unidad`                (`unidad_medida_id`),
    KEY `idx_productos_activo`                (`activo`),
    KEY `idx_productos_stock`                 (`stock_actual`),
    KEY `idx_productos_proveedor`             (`proveedor_principal_id`),
    CONSTRAINT `fk_productos_categoria`  FOREIGN KEY (`categoria_id`)         REFERENCES `categorias`     (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_productos_marca`      FOREIGN KEY (`marca_id`)             REFERENCES `marcas`         (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_productos_unidad`     FOREIGN KEY (`unidad_medida_id`)     REFERENCES `unidades_medida`(`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo principal de productos con precios y stock';

-- Tabla: stock_alertas
-- Descripción: Alertas generadas automáticamente por niveles de stock
DROP TABLE IF EXISTS `stock_alertas`;
CREATE TABLE `stock_alertas` (
    `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `producto_id` INT UNSIGNED     NOT NULL,
    `tipo`        ENUM('minimo','agotado','sobre_maximo') NOT NULL COMMENT 'Tipo de alerta de stock',
    `stock_al_generar` DECIMAL(12,4) NOT NULL COMMENT 'Stock en el momento de generar la alerta',
    `leida`       TINYINT(1)       NOT NULL DEFAULT 0 COMMENT '0=no leída, 1=leída',
    `leida_por`   INT UNSIGNED     NULL COMMENT 'Usuario que marcó como leída',
    `leida_at`    DATETIME         NULL,
    `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_alertas_producto`   (`producto_id`),
    KEY `idx_alertas_leida`      (`leida`),
    KEY `idx_alertas_tipo`       (`tipo`),
    CONSTRAINT `fk_alertas_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Alertas de stock mínimo y agotamiento';

-- ============================================================
-- SECCIÓN 4: PROVEEDORES Y CLIENTES
-- ============================================================

-- Tabla: proveedores
-- Descripción: Proveedores de productos y servicios
DROP TABLE IF EXISTS `proveedores`;
CREATE TABLE `proveedores` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tipo_doc`          TINYINT      NOT NULL DEFAULT 6 COMMENT '6=RUC, 1=DNI, 4=Carnet Extranjería',
    `numero_doc`        VARCHAR(15)  NOT NULL COMMENT 'RUC (11 dígitos) o DNI (8 dígitos)',
    `razon_social`      VARCHAR(150) NOT NULL COMMENT 'Razón social o nombre completo',
    `nombre_comercial`  VARCHAR(150) NULL,
    `direccion`         VARCHAR(300) NULL,
    `distrito`          VARCHAR(100) NULL,
    `provincia`         VARCHAR(100) NULL,
    `departamento`      VARCHAR(100) NULL,
    `ubigeo`            CHAR(6)      NULL,
    `telefono`          VARCHAR(20)  NULL,
    `telefono2`         VARCHAR(20)  NULL,
    `email`             VARCHAR(150) NULL,
    `web`               VARCHAR(150) NULL,
    `contacto_nombre`   VARCHAR(150) NULL COMMENT 'Nombre del contacto principal',
    `contacto_telefono` VARCHAR(20)  NULL COMMENT 'Teléfono del contacto',
    `contacto_email`    VARCHAR(150) NULL,
    `moneda_preferida`  CHAR(3)      NOT NULL DEFAULT 'PEN' COMMENT 'PEN o USD',
    `activo`            TINYINT(1)   NOT NULL DEFAULT 1,
    `notas`             TEXT         NULL,
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_proveedores_numero_doc` (`numero_doc`),
    KEY `idx_proveedores_activo`     (`activo`),
    KEY `idx_proveedores_tipo_doc`   (`tipo_doc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Proveedores de bienes y servicios';

-- FK diferida para productos → proveedor_principal_id
ALTER TABLE `productos`
    ADD CONSTRAINT `fk_productos_proveedor` FOREIGN KEY (`proveedor_principal_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Tabla: clientes
-- Descripción: Clientes para facturación y boletas
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tipo_doc`     TINYINT      NOT NULL DEFAULT 1 COMMENT '0=Sin Doc, 1=DNI, 4=Carnet Extranjería, 6=RUC, 7=Pasaporte',
    `numero_doc`   VARCHAR(15)  NULL     COMMENT 'Número de documento',
    `razon_social` VARCHAR(150) NULL     COMMENT 'Razón social (si es empresa/RUC)',
    `nombres`      VARCHAR(100) NULL     COMMENT 'Nombres (si es persona natural)',
    `apellidos`    VARCHAR(100) NULL     COMMENT 'Apellidos (si es persona natural)',
    `direccion`    VARCHAR(300) NULL,
    `distrito`     VARCHAR(100) NULL,
    `provincia`    VARCHAR(100) NULL,
    `departamento` VARCHAR(100) NULL,
    `ubigeo`       CHAR(6)      NULL,
    `telefono`     VARCHAR(20)  NULL,
    `telefono2`    VARCHAR(20)  NULL,
    `email`        VARCHAR(150) NULL,
    `es_empresa`   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1=empresa (RUC), 0=persona natural',
    `activo`       TINYINT(1)   NOT NULL DEFAULT 1,
    `notas`        TEXT         NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_clientes_numero_doc`  (`numero_doc`),
    KEY `idx_clientes_tipo_doc`    (`tipo_doc`),
    KEY `idx_clientes_razon_social`(`razon_social`),
    KEY `idx_clientes_apellidos`   (`apellidos`),
    KEY `idx_clientes_activo`      (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clientes para emisión de comprobantes';

-- ============================================================
-- SECCIÓN 5: COMPRAS
-- ============================================================

-- Tabla: ordenes_compra
-- Descripción: Órdenes de compra a proveedores
DROP TABLE IF EXISTS `ordenes_compra`;
CREATE TABLE `ordenes_compra` (
    `id`                     INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `numero`                 VARCHAR(20)      NOT NULL COMMENT 'Número de OC: OC-2024-001',
    `proveedor_id`           INT UNSIGNED     NOT NULL,
    `fecha`                  DATE             NOT NULL,
    `fecha_entrega_esperada` DATE             NULL,
    `moneda`                 CHAR(3)          NOT NULL DEFAULT 'PEN' COMMENT 'PEN o USD',
    `tipo_cambio`            DECIMAL(8,4)     NOT NULL DEFAULT 3.7000,
    `subtotal_pen`           DECIMAL(14,4)    NOT NULL DEFAULT 0.0000,
    `igv_pen`                DECIMAL(14,4)    NOT NULL DEFAULT 0.0000,
    `total_pen`              DECIMAL(14,4)    NOT NULL DEFAULT 0.0000,
    `subtotal_usd`           DECIMAL(14,4)    NOT NULL DEFAULT 0.0000,
    `total_usd`              DECIMAL(14,4)    NOT NULL DEFAULT 0.0000,
    `estado`                 ENUM('borrador','enviada','recibida_parcial','recibida_total','cancelada') NOT NULL DEFAULT 'borrador',
    `notas`                  TEXT             NULL,
    `usuario_id`             INT UNSIGNED     NOT NULL COMMENT 'Usuario que creó la OC',
    `created_at`             TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_oc_numero`          (`numero`),
    KEY `idx_oc_proveedor`             (`proveedor_id`),
    KEY `idx_oc_estado`                (`estado`),
    KEY `idx_oc_fecha`                 (`fecha`),
    KEY `idx_oc_usuario`               (`usuario_id`),
    CONSTRAINT `fk_oc_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_oc_usuario`   FOREIGN KEY (`usuario_id`)   REFERENCES `usuarios`    (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Órdenes de compra a proveedores';

-- Tabla: detalle_ordenes_compra
DROP TABLE IF EXISTS `detalle_ordenes_compra`;
CREATE TABLE `detalle_ordenes_compra` (
    `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `orden_compra_id`    INT UNSIGNED  NOT NULL,
    `producto_id`        INT UNSIGNED  NOT NULL,
    `descripcion_item`   VARCHAR(255)  NULL COMMENT 'Descripción manual (override del producto)',
    `cantidad_pedida`    DECIMAL(12,4) NOT NULL,
    `cantidad_recibida`  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `precio_unitario`    DECIMAL(12,4) NOT NULL COMMENT 'Precio en la moneda de la OC',
    `moneda`             CHAR(3)       NOT NULL DEFAULT 'PEN',
    `precio_pen`         DECIMAL(12,4) NOT NULL COMMENT 'Precio convertido a soles',
    `igv_item`           DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `subtotal_item`      DECIMAL(14,4) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_doc_orden`    (`orden_compra_id`),
    KEY `idx_doc_producto` (`producto_id`),
    CONSTRAINT `fk_doc_orden`    FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_doc_producto` FOREIGN KEY (`producto_id`)     REFERENCES `productos`      (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Detalle de líneas en órdenes de compra';

-- Tabla: recepciones_compra
-- Descripción: Recepciones físicas de mercadería
DROP TABLE IF EXISTS `recepciones_compra`;
CREATE TABLE `recepciones_compra` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `orden_compra_id` INT UNSIGNED NULL COMMENT 'OC relacionada (puede ser NULL si es recepción directa)',
    `proveedor_id`    INT UNSIGNED NOT NULL,
    `fecha`           DATE         NOT NULL,
    `notas`           TEXT         NULL,
    `usuario_id`      INT UNSIGNED NOT NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rec_orden`     (`orden_compra_id`),
    KEY `idx_rec_proveedor` (`proveedor_id`),
    KEY `idx_rec_usuario`   (`usuario_id`),
    KEY `idx_rec_fecha`     (`fecha`),
    CONSTRAINT `fk_rec_orden`     FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_rec_proveedor` FOREIGN KEY (`proveedor_id`)    REFERENCES `proveedores`    (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_rec_usuario`   FOREIGN KEY (`usuario_id`)      REFERENCES `usuarios`       (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Recepciones de mercadería de proveedores';

-- Tabla: detalle_recepciones
DROP TABLE IF EXISTS `detalle_recepciones`;
CREATE TABLE `detalle_recepciones` (
    `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `recepcion_id`         INT UNSIGNED  NOT NULL,
    `producto_id`          INT UNSIGNED  NOT NULL,
    `cantidad_recibida`    DECIMAL(12,4) NOT NULL,
    `costo_unitario_pen`   DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'Costo en soles',
    `costo_unitario_usd`   DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'Costo en dólares',
    `tipo_cambio`          DECIMAL(8,4)  NOT NULL DEFAULT 3.7000,
    `lote`                 VARCHAR(50)   NULL COMMENT 'Número de lote (opcional)',
    `vencimiento`          DATE          NULL COMMENT 'Fecha de vencimiento del lote',
    PRIMARY KEY (`id`),
    KEY `idx_dr_recepcion` (`recepcion_id`),
    KEY `idx_dr_producto`  (`producto_id`),
    CONSTRAINT `fk_dr_recepcion` FOREIGN KEY (`recepcion_id`) REFERENCES `recepciones_compra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dr_producto`  FOREIGN KEY (`producto_id`)  REFERENCES `productos`          (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Detalle de productos recibidos en cada recepción';

-- Tabla: comprobantes_compra
-- Descripción: Facturas y boletas recibidas de proveedores
DROP TABLE IF EXISTS `comprobantes_compra`;
CREATE TABLE `comprobantes_compra` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `proveedor_id`     INT UNSIGNED  NOT NULL,
    `tipo_comprobante` VARCHAR(2)    NOT NULL COMMENT '01=Factura, 03=Boleta, 07=NC, 08=ND',
    `serie`            VARCHAR(4)    NOT NULL,
    `numero`           VARCHAR(8)    NOT NULL,
    `fecha_emision`    DATE          NOT NULL,
    `moneda`           CHAR(3)       NOT NULL DEFAULT 'PEN',
    `tipo_cambio`      DECIMAL(8,4)  NOT NULL DEFAULT 3.7000,
    `subtotal`         DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `igv`              DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `total_pen`        DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `total_usd`        DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `orden_compra_id`  INT UNSIGNED  NULL,
    `recepcion_id`     INT UNSIGNED  NULL,
    `xml_path`         VARCHAR(255)  NULL COMMENT 'Ruta al XML del comprobante del proveedor',
    `estado`           ENUM('registrado','pagado','anulado') NOT NULL DEFAULT 'registrado',
    `notas`            TEXT          NULL,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_cc_serie_numero`  (`proveedor_id`, `tipo_comprobante`, `serie`, `numero`),
    KEY `idx_cc_proveedor`           (`proveedor_id`),
    KEY `idx_cc_fecha`               (`fecha_emision`),
    KEY `idx_cc_estado`              (`estado`),
    KEY `idx_cc_orden`               (`orden_compra_id`),
    CONSTRAINT `fk_cc_proveedor` FOREIGN KEY (`proveedor_id`)    REFERENCES `proveedores`    (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_cc_orden`     FOREIGN KEY (`orden_compra_id`) REFERENCES `ordenes_compra` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Comprobantes de compra recibidos de proveedores';

-- ============================================================
-- SECCIÓN 6: INVENTARIO / MOVIMIENTOS DE STOCK
-- ============================================================

-- Tabla: movimientos_stock
-- Descripción: Kardex de movimientos de inventario
DROP TABLE IF EXISTS `movimientos_stock`;
CREATE TABLE `movimientos_stock` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `producto_id`      INT UNSIGNED  NOT NULL,
    `tipo_movimiento`  ENUM(
        'entrada_compra',       -- Recepción de mercadería de proveedor
        'salida_venta',         -- Salida por venta al cliente
        'ajuste_positivo',      -- Ajuste de inventario (+)
        'ajuste_negativo',      -- Ajuste de inventario (-)
        'devolucion_cliente',   -- Devolución recibida del cliente
        'devolucion_proveedor', -- Devolución enviada al proveedor
        'traslado_entrada',     -- Traslado desde otra ubicación
        'traslado_salida',      -- Traslado hacia otra ubicación
        'merma',                -- Merma o pérdida
        'inicial'               -- Stock inicial del sistema
    ) NOT NULL,
    `cantidad`         DECIMAL(12,4) NOT NULL COMMENT 'Positivo=entrada, Negativo=salida',
    `stock_antes`      DECIMAL(12,4) NOT NULL COMMENT 'Stock antes del movimiento',
    `stock_despues`    DECIMAL(12,4) NOT NULL COMMENT 'Stock después del movimiento',
    `costo_unitario`   DECIMAL(12,4) NULL     COMMENT 'Costo unitario en soles al momento del movimiento',
    `referencia_tipo`  VARCHAR(20)   NULL     COMMENT 'Tipo de referencia: venta, compra, ajuste, devolucion',
    `referencia_id`    INT UNSIGNED  NULL     COMMENT 'ID del documento de referencia',
    `notas`            VARCHAR(255)  NULL,
    `usuario_id`       INT UNSIGNED  NOT NULL,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ms_producto`      (`producto_id`),
    KEY `idx_ms_tipo`          (`tipo_movimiento`),
    KEY `idx_ms_referencia`    (`referencia_tipo`, `referencia_id`),
    KEY `idx_ms_usuario`       (`usuario_id`),
    KEY `idx_ms_fecha`         (`created_at`),
    CONSTRAINT `fk_ms_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_ms_usuario`  FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios`  (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Kardex de movimientos de inventario';

-- ============================================================
-- SECCIÓN 7: CAJAS Y VENTAS
-- ============================================================

-- Tabla: cajas
-- Descripción: Puntos de venta / cajas registradoras
DROP TABLE IF EXISTS `cajas`;
CREATE TABLE `cajas` (
    `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(50)       NOT NULL COMMENT 'Ej: Caja 1, Caja Principal',
    `descripcion` VARCHAR(150)      NULL,
    `activo`      TINYINT(1)        NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_cajas_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cajas/puntos de venta';

-- Tabla: apertura_caja
-- Descripción: Registro de apertura y cierre de caja con montos por método de pago
DROP TABLE IF EXISTS `caja_aperturas`;
CREATE TABLE `caja_aperturas` (
    `id`                        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `caja_id`                   SMALLINT UNSIGNED NOT NULL,
    `usuario_id`                INT UNSIGNED      NOT NULL COMMENT 'Usuario que abrió la caja',
    `usuario_cierre_id`         INT UNSIGNED      NULL     COMMENT 'Usuario que cerró la caja',
    `fecha_apertura`            DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_cierre`              DATETIME          NULL,
    -- Saldo inicial declarado
    `saldo_inicial_pen`         DECIMAL(12,2)     NOT NULL DEFAULT 0.00 COMMENT 'Efectivo inicial en soles',
    `saldo_inicial_usd`         DECIMAL(12,2)     NOT NULL DEFAULT 0.00 COMMENT 'Efectivo inicial en dólares',
    -- Saldos finales por método de pago
    `saldo_final_efectivo_pen`  DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    `saldo_final_yape`          DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    `saldo_final_plin`          DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    `saldo_final_bcp`           DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    `saldo_final_interbank`     DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    `saldo_final_bbva`          DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    `saldo_final_scotiabank`    DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    `saldo_final_transferencia` DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    `saldo_final_tarjeta`       DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    `saldo_final_usd`           DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    `saldo_final_otro`          DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
    -- Totales calculados
    `total_ventas`              DECIMAL(14,2)     NOT NULL DEFAULT 0.00 COMMENT 'Total vendido en la sesión',
    `total_anulaciones`         DECIMAL(14,2)     NOT NULL DEFAULT 0.00,
    `diferencia`                DECIMAL(12,2)     NOT NULL DEFAULT 0.00 COMMENT 'Diferencia (sobrante/faltante)',
    `estado`                    ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
    `notas_cierre`              TEXT              NULL,
    `created_at`                TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ac_caja`           (`caja_id`),
    KEY `idx_ac_usuario`        (`usuario_id`),
    KEY `idx_ac_estado`         (`estado`),
    KEY `idx_ac_fecha_apertura` (`fecha_apertura`),
    CONSTRAINT `fk_ac_caja`    FOREIGN KEY (`caja_id`)   REFERENCES `cajas`    (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_ac_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Sesiones de apertura y cierre de caja';

-- Tabla: ventas
-- Descripción: Cabecera de ventas / comprobantes emitidos
DROP TABLE IF EXISTS `ventas`;
CREATE TABLE `ventas` (
    `id`                    INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `numero_correlativo`    INT UNSIGNED      NOT NULL COMMENT 'Correlativo interno de venta',
    `caja_id`               SMALLINT UNSIGNED NULL,
    `apertura_caja_id`      INT UNSIGNED      NULL,
    `cliente_id`            INT UNSIGNED      NULL COMMENT 'NULL=cliente genérico',
    `usuario_id`            INT UNSIGNED      NOT NULL COMMENT 'Vendedor',
    -- Comprobante
    `tipo_comprobante`      VARCHAR(2)        NOT NULL COMMENT '01=Factura, 03=Boleta, NV=Nota Venta',
    `serie`                 CHAR(4)           NOT NULL,
    `numero`                VARCHAR(8)        NOT NULL,
    `fecha_emision`         DATE              NOT NULL,
    -- Moneda y tipo de cambio
    `moneda`                CHAR(3)           NOT NULL DEFAULT 'PEN',
    `tipo_cambio`           DECIMAL(8,4)      NOT NULL DEFAULT 3.7000,
    -- Importes tributarios (en soles)
    `subtotal_gravado`      DECIMAL(14,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Base imponible gravada con IGV',
    `subtotal_exonerado`    DECIMAL(14,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Base exonerada de IGV',
    `subtotal_inafecto`     DECIMAL(14,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Base inafecta de IGV',
    `igv`                   DECIMAL(14,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Monto total IGV',
    `descuento_global`      DECIMAL(14,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Descuento global aplicado',
    `total_pen`             DECIMAL(14,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Total en soles',
    `total_usd`             DECIMAL(14,4)     NOT NULL DEFAULT 0.0000 COMMENT 'Total en dólares (si aplica)',
    -- Estado
    `estado`                ENUM('pendiente','pagada','anulada','nc_emitida') NOT NULL DEFAULT 'pendiente',
    `notas`                 TEXT              NULL,
    `created_at`            TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ventas_serie_numero`     (`tipo_comprobante`, `serie`, `numero`),
    KEY `idx_ventas_correlativo`            (`numero_correlativo`),
    KEY `idx_ventas_cliente`                (`cliente_id`),
    KEY `idx_ventas_usuario`                (`usuario_id`),
    KEY `idx_ventas_caja`                   (`caja_id`),
    KEY `idx_ventas_apertura`               (`apertura_caja_id`),
    KEY `idx_ventas_estado`                 (`estado`),
    KEY `idx_ventas_fecha`                  (`fecha_emision`),
    KEY `idx_ventas_tipo`                   (`tipo_comprobante`),
    CONSTRAINT `fk_ventas_cliente`   FOREIGN KEY (`cliente_id`)      REFERENCES `clientes`     (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_ventas_usuario`   FOREIGN KEY (`usuario_id`)      REFERENCES `usuarios`     (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_ventas_caja`      FOREIGN KEY (`caja_id`)         REFERENCES `cajas`        (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_ventas_apertura`  FOREIGN KEY (`apertura_caja_id`) REFERENCES `caja_aperturas`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cabecera de ventas y comprobantes emitidos';

-- Tabla: detalle_ventas
-- Descripción: Líneas de detalle de cada venta
DROP TABLE IF EXISTS `detalle_ventas`;
CREATE TABLE `detalle_ventas` (
    `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `venta_id`               INT UNSIGNED  NOT NULL,
    `producto_id`            INT UNSIGNED  NULL     COMMENT 'NULL=producto libre/servicio manual',
    `descripcion_item`       VARCHAR(250)  NOT NULL COMMENT 'Descripción del item en el comprobante',
    `unidad_medida`          VARCHAR(3)    NOT NULL DEFAULT 'NIU' COMMENT 'Código SUNAT',
    `cantidad`               DECIMAL(12,4) NOT NULL,
    -- Precios en moneda de la venta
    `precio_unitario_pen`    DECIMAL(12,4) NOT NULL COMMENT 'Precio unitario en soles (con IGV si aplica)',
    `precio_unitario_usd`    DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    -- Descuentos
    `descuento_porcentaje`   DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `descuento_pen`          DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    -- Tributario
    `tipo_afectacion_igv`    VARCHAR(2)    NOT NULL DEFAULT '10' COMMENT '10=Gravado, 20=Exonerado, 30=Inafecto',
    `valor_unitario`         DECIMAL(12,6) NOT NULL COMMENT 'Valor unitario sin IGV (base imponible unitaria)',
    `igv_item`               DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'IGV del item',
    `subtotal_item`          DECIMAL(14,4) NOT NULL COMMENT 'Subtotal del item (con IGV si gravado)',
    `subtotal_pen`           DECIMAL(14,4) NOT NULL COMMENT 'Subtotal convertido a soles',
    `orden`                  TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Orden del item en el comprobante',
    PRIMARY KEY (`id`),
    KEY `idx_dv_venta`    (`venta_id`),
    KEY `idx_dv_producto` (`producto_id`),
    CONSTRAINT `fk_dv_venta`    FOREIGN KEY (`venta_id`)   REFERENCES `ventas`   (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dv_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Líneas de detalle de ventas';

-- Tabla: pagos_venta
-- Descripción: Pagos recibidos por cada venta (pueden ser múltiples métodos)
DROP TABLE IF EXISTS `pagos_venta`;
CREATE TABLE `pagos_venta` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `venta_id`    INT UNSIGNED  NOT NULL,
    `metodo_pago` ENUM(
        'efectivo',
        'yape',
        'plin',
        'bcp',
        'interbank',
        'bbva',
        'scotiabank',
        'transferencia',
        'tarjeta_credito',
        'tarjeta_debito',
        'usd',
        'otro'
    ) NOT NULL,
    `monto`       DECIMAL(12,4) NOT NULL COMMENT 'Monto en la moneda especificada',
    `moneda`      CHAR(3)       NOT NULL DEFAULT 'PEN',
    `tipo_cambio` DECIMAL(8,4)  NOT NULL DEFAULT 3.7000,
    `monto_pen`   DECIMAL(12,4) NOT NULL COMMENT 'Equivalente en soles',
    `referencia`  VARCHAR(100)  NULL COMMENT 'N° operación, N° voucher, código Yape, etc.',
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pv_venta`  (`venta_id`),
    KEY `idx_pv_metodo` (`metodo_pago`),
    CONSTRAINT `fk_pv_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pagos recibidos por venta (multi-método)';

-- ============================================================
-- SECCIÓN 8: COMPROBANTES ELECTRÓNICOS SUNAT
-- ============================================================

-- Tabla: comprobantes_electronicos
-- Descripción: Estado y tracking de CPE enviados a SUNAT
DROP TABLE IF EXISTS `comprobantes_electronicos`;
CREATE TABLE `comprobantes_electronicos` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `venta_id`          INT UNSIGNED NOT NULL COMMENT 'FK a ventas',
    `tipo_comprobante`  VARCHAR(2)   NOT NULL,
    `serie`             CHAR(4)      NOT NULL,
    `numero`            VARCHAR(8)   NOT NULL,
    `fecha_emision`     DATE         NOT NULL,
    -- Archivos generados
    `hash_cpe`          VARCHAR(50)  NULL  COMMENT 'Hash digest SHA-1 del XML firmado',
    `xml_path`          VARCHAR(255) NULL  COMMENT 'Ruta al XML firmado',
    `pdf_path`          VARCHAR(255) NULL  COMMENT 'Ruta al PDF/representación impresa',
    -- Estado con SUNAT
    `estado_sunat`      ENUM('pendiente','enviado','aceptado','rechazado','baja_solicitada','dado_de_baja') NOT NULL DEFAULT 'pendiente',
    `cdr_path`          VARCHAR(255) NULL  COMMENT 'Ruta al CDR (Constancia de Recepción)',
    `mensaje_sunat`     TEXT         NULL  COMMENT 'Mensaje de respuesta SUNAT',
    `codigo_respuesta`  VARCHAR(10)  NULL  COMMENT 'Código de respuesta SUNAT (0=OK, 100X=error)',
    `fecha_envio`       DATETIME     NULL  COMMENT 'Fecha/hora del último envío exitoso',
    `intentos_envio`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_cpe_venta`          (`venta_id`),
    UNIQUE KEY `uk_cpe_serie_numero`   (`tipo_comprobante`, `serie`, `numero`),
    KEY `idx_cpe_estado`               (`estado_sunat`),
    KEY `idx_cpe_fecha`                (`fecha_emision`),
    CONSTRAINT `fk_cpe_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Comprobantes electrónicos y su estado en SUNAT';

-- Tabla: cola_sunat
-- Descripción: Cola de procesamiento asíncrono para envíos a SUNAT
DROP TABLE IF EXISTS `cola_sunat`;
CREATE TABLE `cola_sunat` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `comprobante_id`   INT UNSIGNED NOT NULL COMMENT 'FK a comprobantes_electronicos',
    `tipo_operacion`   ENUM('emision','baja','resumen','comunicacion_baja') NOT NULL DEFAULT 'emision',
    `prioridad`        TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1=alta, 5=normal, 10=baja',
    `estado`           ENUM('pendiente','procesando','completado','error_temporal','error_permanente') NOT NULL DEFAULT 'pendiente',
    `intentos`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_intentos`     TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `ultimo_intento`   DATETIME         NULL,
    `proximo_intento`  DATETIME         NULL COMMENT 'Siguiente intento (backoff exponencial)',
    `error_mensaje`    TEXT             NULL,
    `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cola_estado`         (`estado`),
    KEY `idx_cola_prioridad`      (`prioridad`),
    KEY `idx_cola_comprobante`    (`comprobante_id`),
    KEY `idx_cola_proximo`        (`proximo_intento`),
    CONSTRAINT `fk_cola_comprobante` FOREIGN KEY (`comprobante_id`) REFERENCES `comprobantes_electronicos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cola de procesamiento para envíos a SUNAT';

-- Tabla: notas_electronicos
-- Descripción: Notas de crédito y débito electrónicas
DROP TABLE IF EXISTS `notas_electronicos`;
CREATE TABLE `notas_electronicos` (
    `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tipo`                  VARCHAR(2)    NOT NULL COMMENT '07=Nota Crédito, 08=Nota Débito',
    `serie`                 CHAR(4)       NOT NULL,
    `numero`                VARCHAR(8)    NOT NULL,
    `fecha_emision`         DATE          NOT NULL,
    `venta_origen_id`       INT UNSIGNED  NOT NULL COMMENT 'Venta que origina la NC/ND',
    `comprobante_origen_id` INT UNSIGNED  NULL COMMENT 'CPE original que se modifica',
    `motivo_codigo`         VARCHAR(2)    NOT NULL COMMENT 'Código motivo SUNAT: 01,02,...13',
    `motivo_descripcion`    VARCHAR(255)  NOT NULL COMMENT 'Descripción del motivo',
    `valor_nc_nd`           DECIMAL(14,4) NOT NULL DEFAULT 0.0000 COMMENT 'Valor ajustado (positivo=crédito)',
    `igv_nc_nd`             DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `total_nc_nd`           DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `xml_path`              VARCHAR(255)  NULL,
    `pdf_path`              VARCHAR(255)  NULL,
    `estado_sunat`          ENUM('pendiente','enviado','aceptado','rechazado') NOT NULL DEFAULT 'pendiente',
    `hash_cpe`              VARCHAR(50)   NULL,
    `cdr_path`              VARCHAR(255)  NULL,
    `mensaje_sunat`         TEXT          NULL,
    `usuario_id`            INT UNSIGNED  NOT NULL,
    `created_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_nc_serie_numero`   (`tipo`, `serie`, `numero`),
    KEY `idx_nc_venta_origen`         (`venta_origen_id`),
    KEY `idx_nc_estado`               (`estado_sunat`),
    KEY `idx_nc_fecha`                (`fecha_emision`),
    CONSTRAINT `fk_nc_venta_origen`  FOREIGN KEY (`venta_origen_id`)       REFERENCES `ventas`                  (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_nc_cpe_origen`    FOREIGN KEY (`comprobante_origen_id`) REFERENCES `comprobantes_electronicos`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_nc_usuario`       FOREIGN KEY (`usuario_id`)            REFERENCES `usuarios`                (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Notas de crédito y débito electrónicas';

-- ============================================================
-- SECCIÓN 9: COTIZACIONES
-- ============================================================

-- Tabla: cotizaciones
DROP TABLE IF EXISTS `cotizaciones`;
CREATE TABLE `cotizaciones` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `numero`           VARCHAR(20)   NOT NULL COMMENT 'N° cotización: COT-2024-001',
    `cliente_id`       INT UNSIGNED  NULL,
    `usuario_id`       INT UNSIGNED  NOT NULL,
    `fecha_emision`    DATE          NOT NULL,
    `fecha_vencimiento` DATE         NULL,
    `moneda`           CHAR(3)       NOT NULL DEFAULT 'PEN',
    `tipo_cambio`      DECIMAL(8,4)  NOT NULL DEFAULT 3.7000,
    `subtotal_gravado` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `subtotal_exonerado` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `igv`              DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `total_pen`        DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `total_usd`        DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    `estado`           ENUM('borrador','enviada','aprobada','rechazada','vencida','convertida') NOT NULL DEFAULT 'borrador',
    `condicion_pago`   VARCHAR(100)  NULL COMMENT 'Contado, 30 días, etc.',
    `notas`            TEXT          NULL,
    `terminos`         TEXT          NULL COMMENT 'Términos y condiciones de la cotización',
    `venta_id`         INT UNSIGNED  NULL COMMENT 'FK a venta si fue convertida',
    `pdf_path`         VARCHAR(255)  NULL,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_cotizaciones_numero`  (`numero`),
    KEY `idx_cot_cliente`                (`cliente_id`),
    KEY `idx_cot_usuario`                (`usuario_id`),
    KEY `idx_cot_estado`                 (`estado`),
    KEY `idx_cot_fecha`                  (`fecha_emision`),
    KEY `idx_cot_venta`                  (`venta_id`),
    CONSTRAINT `fk_cot_cliente`  FOREIGN KEY (`cliente_id`)  REFERENCES `clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_cot_usuario`  FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
    CONSTRAINT `fk_cot_venta`    FOREIGN KEY (`venta_id`)    REFERENCES `ventas`   (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cotizaciones a clientes (preventa)';

-- Tabla: detalle_cotizaciones
DROP TABLE IF EXISTS `detalle_cotizaciones`;
CREATE TABLE `detalle_cotizaciones` (
    `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `cotizacion_id`        INT UNSIGNED  NOT NULL,
    `producto_id`          INT UNSIGNED  NULL,
    `descripcion`          VARCHAR(250)  NOT NULL,
    `unidad_medida`        VARCHAR(3)    NOT NULL DEFAULT 'NIU',
    `cantidad`             DECIMAL(12,4) NOT NULL,
    `precio_unitario`      DECIMAL(12,4) NOT NULL,
    `descuento_porcentaje` DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
    `tipo_afectacion_igv`  VARCHAR(2)    NOT NULL DEFAULT '10',
    `igv_item`             DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    `subtotal_item`        DECIMAL(14,4) NOT NULL,
    `orden`                TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_dcot_cotizacion` (`cotizacion_id`),
    KEY `idx_dcot_producto`   (`producto_id`),
    CONSTRAINT `fk_dcot_cotizacion` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dcot_producto`   FOREIGN KEY (`producto_id`)   REFERENCES `productos`    (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Líneas de detalle de cotizaciones';

-- ============================================================
-- SECCIÓN 10: SISTEMA / LOGS / AUDITORÍA
-- ============================================================

-- Tabla: logs_sistema
-- Descripción: Auditoría de acciones del sistema
DROP TABLE IF EXISTS `logs_sistema`;
CREATE TABLE `logs_sistema` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`  INT UNSIGNED    NULL COMMENT 'NULL=acción del sistema',
    `accion`      VARCHAR(50)     NOT NULL COMMENT 'Ej: crear, editar, eliminar, anular, exportar',
    `modulo`      VARCHAR(50)     NOT NULL COMMENT 'Ej: ventas, productos, usuarios',
    `descripcion` TEXT            NOT NULL COMMENT 'Descripción detallada de la acción',
    `datos_antes` JSON            NULL COMMENT 'Estado anterior (para auditoría de cambios)',
    `datos_despues` JSON          NULL COMMENT 'Estado nuevo',
    `ip`          VARCHAR(45)     NULL COMMENT 'IP del usuario (soporta IPv6)',
    `user_agent`  VARCHAR(300)    NULL,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ls_usuario` (`usuario_id`),
    KEY `idx_ls_modulo`  (`modulo`),
    KEY `idx_ls_accion`  (`accion`),
    KEY `idx_ls_fecha`   (`created_at`),
    CONSTRAINT `fk_ls_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log de auditoría del sistema';

-- Tabla: logs_acceso
-- Descripción: Registro de intentos de acceso al sistema
DROP TABLE IF EXISTS `logs_acceso`;
CREATE TABLE `logs_acceso` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`     VARCHAR(50)     NOT NULL COMMENT 'Username intentado',
    `ip`           VARCHAR(45)     NOT NULL,
    `exitoso`      TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1=login exitoso, 0=fallido',
    `motivo_fallo` VARCHAR(100)    NULL COMMENT 'wrong_password, user_not_found, user_blocked, etc.',
    `user_agent`   VARCHAR(300)    NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_la_username`  (`username`),
    KEY `idx_la_ip`        (`ip`),
    KEY `idx_la_exitoso`   (`exitoso`),
    KEY `idx_la_fecha`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de intentos de acceso (login)';

-- Tabla: tipo_cambio_historico
-- Descripción: Historial de tipos de cambio USD/PEN
DROP TABLE IF EXISTS `tipo_cambio_historico`;
CREATE TABLE `tipo_cambio_historico` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `fecha`      DATE          NOT NULL,
    `usd_a_pen`  DECIMAL(8,4)  NOT NULL COMMENT 'Tipo de cambio: 1 USD = X PEN',
    `pen_a_usd`  DECIMAL(10,6) NULL     COMMENT 'Inverso calculado',
    `fuente`     VARCHAR(50)   NOT NULL DEFAULT 'manual' COMMENT 'manual, sunat, bcrp',
    `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tc_fecha` (`fecha`),
    KEY `idx_tc_fuente` (`fuente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de tipos de cambio USD→PEN';

-- ============================================================
-- SECCIÓN 11: TRIGGERS
-- ============================================================

DELIMITER $$

-- Trigger: Actualizar stock al registrar recepción de compra
DROP TRIGGER IF EXISTS `trg_after_insert_detalle_recepcion`$$
CREATE TRIGGER `trg_after_insert_detalle_recepcion`
AFTER INSERT ON `detalle_recepciones`
FOR EACH ROW
BEGIN
    DECLARE v_stock_antes DECIMAL(12,4);
    DECLARE v_stock_despues DECIMAL(12,4);

    -- Obtener stock actual antes de actualizar
    SELECT stock_actual INTO v_stock_antes
    FROM productos
    WHERE id = NEW.producto_id;

    SET v_stock_despues = v_stock_antes + NEW.cantidad_recibida;

    -- Actualizar stock del producto
    UPDATE productos
    SET stock_actual = v_stock_despues,
        precio_compra_pen = NEW.costo_unitario_pen,
        precio_compra_usd = NEW.costo_unitario_usd,
        updated_at = NOW()
    WHERE id = NEW.producto_id;

    -- Obtener usuario que hizo la recepción
    INSERT INTO movimientos_stock (
        producto_id,
        tipo_movimiento,
        cantidad,
        stock_antes,
        stock_despues,
        costo_unitario,
        referencia_tipo,
        referencia_id,
        notas,
        usuario_id,
        created_at
    )
    SELECT
        NEW.producto_id,
        'entrada_compra',
        NEW.cantidad_recibida,
        v_stock_antes,
        v_stock_despues,
        NEW.costo_unitario_pen,
        'recepcion',
        NEW.recepcion_id,
        CONCAT('Recepción #', NEW.recepcion_id),
        rc.usuario_id,
        NOW()
    FROM recepciones_compra rc
    WHERE rc.id = NEW.recepcion_id;

    -- Verificar si el stock llegó al mínimo o se agotó
    IF v_stock_despues <= 0 THEN
        INSERT INTO stock_alertas (producto_id, tipo, stock_al_generar)
        VALUES (NEW.producto_id, 'agotado', v_stock_despues)
        ON DUPLICATE KEY UPDATE created_at = NOW();
    END IF;
END$$

-- Trigger: Reducir stock al insertar detalle de venta
DROP TRIGGER IF EXISTS `trg_after_insert_detalle_venta`$$
CREATE TRIGGER `trg_after_insert_detalle_venta`
AFTER INSERT ON `detalle_ventas`
FOR EACH ROW
BEGIN
    DECLARE v_stock_antes    DECIMAL(12,4);
    DECLARE v_stock_despues  DECIMAL(12,4);
    DECLARE v_stock_minimo   DECIMAL(12,4);
    DECLARE v_usuario_id     INT UNSIGNED;

    -- Solo procesar si hay producto asignado
    IF NEW.producto_id IS NOT NULL THEN

        SELECT stock_actual, stock_minimo INTO v_stock_antes, v_stock_minimo
        FROM productos
        WHERE id = NEW.producto_id;

        SET v_stock_despues = v_stock_antes - NEW.cantidad;

        -- Actualizar stock
        UPDATE productos
        SET stock_actual = v_stock_despues,
            updated_at   = NOW()
        WHERE id = NEW.producto_id;

        -- Obtener usuario de la venta
        SELECT usuario_id INTO v_usuario_id
        FROM ventas
        WHERE id = NEW.venta_id;

        -- Registrar movimiento en kardex
        INSERT INTO movimientos_stock (
            producto_id,
            tipo_movimiento,
            cantidad,
            stock_antes,
            stock_despues,
            costo_unitario,
            referencia_tipo,
            referencia_id,
            notas,
            usuario_id,
            created_at
        ) VALUES (
            NEW.producto_id,
            'salida_venta',
            -NEW.cantidad,    -- negativo = salida
            v_stock_antes,
            v_stock_despues,
            NULL,
            'venta',
            NEW.venta_id,
            CONCAT('Venta #', NEW.venta_id),
            v_usuario_id,
            NOW()
        );

        -- Generar alerta si stock llegó al mínimo o se agotó
        IF v_stock_despues <= 0 THEN
            INSERT IGNORE INTO stock_alertas (producto_id, tipo, stock_al_generar)
            VALUES (NEW.producto_id, 'agotado', v_stock_despues);
        ELSEIF v_stock_despues <= v_stock_minimo THEN
            INSERT IGNORE INTO stock_alertas (producto_id, tipo, stock_al_generar)
            VALUES (NEW.producto_id, 'minimo', v_stock_despues);
        END IF;

    END IF;
END$$

-- Trigger: Restaurar stock al anular venta (UPDATE estado a 'anulada')
DROP TRIGGER IF EXISTS `trg_after_update_venta_anulacion`$$
CREATE TRIGGER `trg_after_update_venta_anulacion`
AFTER UPDATE ON `ventas`
FOR EACH ROW
BEGIN
    -- Solo actuar cuando cambia el estado a 'anulada'
    IF NEW.estado = 'anulada' AND OLD.estado != 'anulada' THEN

        -- Restaurar stock de todos los items de la venta
        UPDATE productos p
        INNER JOIN detalle_ventas dv ON dv.producto_id = p.id
        SET p.stock_actual = p.stock_actual + dv.cantidad,
            p.updated_at   = NOW()
        WHERE dv.venta_id = NEW.id
          AND dv.producto_id IS NOT NULL;

        -- Registrar movimientos de restauración
        INSERT INTO movimientos_stock (
            producto_id,
            tipo_movimiento,
            cantidad,
            stock_antes,
            stock_despues,
            referencia_tipo,
            referencia_id,
            notas,
            usuario_id,
            created_at
        )
        SELECT
            dv.producto_id,
            'devolucion_cliente',
            dv.cantidad,
            p.stock_actual - dv.cantidad,  -- stock_antes (ya actualizado arriba)
            p.stock_actual,
            'venta_anulada',
            NEW.id,
            CONCAT('Anulación venta #', NEW.id),
            NEW.usuario_id,
            NOW()
        FROM detalle_ventas dv
        INNER JOIN productos p ON p.id = dv.producto_id
        WHERE dv.venta_id = NEW.id
          AND dv.producto_id IS NOT NULL;

    END IF;
END$$

DELIMITER ;

-- ============================================================
-- SECCIÓN 12: VISTAS (VIEWS)
-- ============================================================

-- Vista: v_productos_stock
-- Muestra productos con información de stock, categoría y alertas
DROP VIEW IF EXISTS `v_productos_stock`;
CREATE VIEW `v_productos_stock` AS
SELECT
    p.id,
    p.codigo_interno,
    p.codigo_barras,
    p.nombre                                          AS producto_nombre,
    p.descripcion,
    c.id                                              AS categoria_id,
    c.nombre                                          AS categoria_nombre,
    pc.nombre                                         AS categoria_padre,
    m.id                                              AS marca_id,
    m.nombre                                          AS marca_nombre,
    um.codigo                                         AS unidad_codigo,
    um.nombre                                         AS unidad_nombre,
    um.abreviatura                                    AS unidad_abreviatura,
    p.precio_compra_pen,
    p.precio_venta_pen,
    p.precio_venta_usd,
    p.precio_mayorista_pen,
    p.stock_actual,
    p.stock_minimo,
    p.stock_maximo,
    p.aplica_igv,
    p.tipo_afectacion_igv,
    p.imagen_path,
    p.activo,
    -- Indicador de alerta de stock
    CASE
        WHEN p.stock_actual <= 0              THEN 'agotado'
        WHEN p.stock_actual <= p.stock_minimo THEN 'minimo'
        ELSE                                       'ok'
    END                                             AS estado_stock,
    -- Valor del inventario (a precio de compra)
    ROUND(p.stock_actual * p.precio_compra_pen, 2)  AS valor_inventario_pen,
    -- Margen de ganancia porcentual
    CASE
        WHEN p.precio_compra_pen > 0
        THEN ROUND(((p.precio_venta_pen - p.precio_compra_pen) / p.precio_compra_pen) * 100, 2)
        ELSE 0
    END                                             AS margen_ganancia_pct,
    -- Alertas no leídas del producto
    (SELECT COUNT(*) FROM stock_alertas sa WHERE sa.producto_id = p.id AND sa.leida = 0) AS alertas_pendientes,
    p.created_at,
    p.updated_at
FROM `productos` p
LEFT JOIN `categorias`      c  ON c.id  = p.categoria_id
LEFT JOIN `categorias`      pc ON pc.id = c.parent_id
LEFT JOIN `marcas`          m  ON m.id  = p.marca_id
LEFT JOIN `unidades_medida` um ON um.id = p.unidad_medida_id;

-- Vista: v_ventas_resumen
-- Resumen de ventas con totales y datos del cliente
DROP VIEW IF EXISTS `v_ventas_resumen`;
CREATE VIEW `v_ventas_resumen` AS
SELECT
    v.id,
    v.numero_correlativo,
    v.tipo_comprobante,
    CONCAT(v.tipo_comprobante, '-', v.serie, '-', LPAD(v.numero, 8, '0')) AS comprobante_display,
    v.serie,
    v.numero,
    v.fecha_emision,
    -- Cliente
    v.cliente_id,
    CASE
        WHEN c.es_empresa = 1   THEN c.razon_social
        WHEN c.id IS NULL       THEN 'Cliente General'
        ELSE CONCAT(c.nombres, ' ', c.apellidos)
    END                                              AS cliente_nombre,
    c.tipo_doc                                       AS cliente_tipo_doc,
    c.numero_doc                                     AS cliente_numero_doc,
    -- Vendedor
    v.usuario_id,
    CONCAT(u.nombre, ' ', u.apellidos)               AS vendedor_nombre,
    -- Caja
    v.caja_id,
    cj.nombre                                        AS caja_nombre,
    -- Moneda y montos
    v.moneda,
    v.tipo_cambio,
    v.subtotal_gravado,
    v.subtotal_exonerado,
    v.subtotal_inafecto,
    v.igv,
    v.descuento_global,
    v.total_pen,
    v.total_usd,
    v.estado,
    -- Comprobante electrónico
    ce.estado_sunat,
    ce.xml_path,
    ce.pdf_path,
    ce.hash_cpe,
    -- Método(s) de pago resumido
    (SELECT GROUP_CONCAT(pv.metodo_pago ORDER BY pv.id SEPARATOR ', ')
     FROM pagos_venta pv WHERE pv.venta_id = v.id)   AS metodos_pago,
    v.notas,
    v.created_at
FROM `ventas` v
LEFT JOIN `clientes`                  c  ON c.id  = v.cliente_id
LEFT JOIN `usuarios`                  u  ON u.id  = v.usuario_id
LEFT JOIN `cajas`                     cj ON cj.id = v.caja_id
LEFT JOIN `comprobantes_electronicos` ce ON ce.venta_id = v.id;

-- Vista: v_caja_resumen_hoy
-- Resumen de caja del día actual
DROP VIEW IF EXISTS `v_caja_resumen_hoy`;
CREATE VIEW `v_caja_resumen_hoy` AS
SELECT
    ac.id                                     AS apertura_id,
    ac.caja_id,
    cj.nombre                                 AS caja_nombre,
    ac.usuario_id,
    CONCAT(u.nombre,' ',u.apellidos)          AS usuario_nombre,
    ac.fecha_apertura,
    ac.saldo_inicial_pen,
    ac.estado,
    -- Totales calculados del día
    COUNT(DISTINCT v.id)                      AS total_transacciones,
    COALESCE(SUM(CASE WHEN v.estado != 'anulada' THEN v.total_pen ELSE 0 END), 0) AS total_ventas_pen,
    COALESCE(SUM(CASE WHEN v.estado = 'anulada' THEN v.total_pen ELSE 0 END), 0)  AS total_anulaciones_pen,
    -- Por método de pago
    COALESCE(SUM(CASE WHEN pv.metodo_pago = 'efectivo'    THEN pv.monto_pen ELSE 0 END), 0) AS total_efectivo,
    COALESCE(SUM(CASE WHEN pv.metodo_pago = 'yape'        THEN pv.monto_pen ELSE 0 END), 0) AS total_yape,
    COALESCE(SUM(CASE WHEN pv.metodo_pago = 'plin'        THEN pv.monto_pen ELSE 0 END), 0) AS total_plin,
    COALESCE(SUM(CASE WHEN pv.metodo_pago IN ('bcp','interbank','bbva','scotiabank','transferencia') THEN pv.monto_pen ELSE 0 END), 0) AS total_transferencias
FROM `caja_aperturas` ac
INNER JOIN `cajas`   cj ON cj.id = ac.caja_id
INNER JOIN `usuarios` u ON u.id  = ac.usuario_id
LEFT  JOIN `ventas`   v  ON v.apertura_caja_id = ac.id
LEFT  JOIN `pagos_venta` pv ON pv.venta_id = v.id AND v.estado != 'anulada'
WHERE DATE(ac.fecha_apertura) = CURDATE()
GROUP BY ac.id, ac.caja_id, cj.nombre, ac.usuario_id, u.nombre, u.apellidos, ac.fecha_apertura, ac.saldo_inicial_pen, ac.estado;

-- Vista: v_kardex_producto
-- Kardex completo por producto
DROP VIEW IF EXISTS `v_kardex_producto`;
CREATE VIEW `v_kardex_producto` AS
SELECT
    ms.id,
    ms.producto_id,
    p.codigo_interno,
    p.nombre                              AS producto_nombre,
    ms.tipo_movimiento,
    ms.cantidad,
    ms.stock_antes,
    ms.stock_despues,
    ms.costo_unitario,
    ROUND(ms.cantidad * ms.costo_unitario, 4) AS valor_movimiento,
    ms.referencia_tipo,
    ms.referencia_id,
    ms.notas,
    ms.usuario_id,
    CONCAT(u.nombre,' ',u.apellidos)      AS usuario_nombre,
    ms.created_at
FROM `movimientos_stock` ms
INNER JOIN `productos` p ON p.id = ms.producto_id
INNER JOIN `usuarios`  u ON u.id = ms.usuario_id;

-- ============================================================
-- HABILITAR FOREIGN KEYS
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN DEL SCHEMA
-- Versión: 1.0.0
-- Tablas creadas: 32
-- Triggers: 3
-- Views: 4
-- ============================================================
