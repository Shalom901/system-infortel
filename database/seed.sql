-- Datos por defecto para iniciar el sistema

-- Roles
INSERT INTO roles (nombre, descripcion) VALUES 
('Administrador', 'Acceso total al sistema'),
('Vendedor', 'Acceso a ventas, clientes y caja');

-- Usuario Administrador por defecto (Contraseña: admin123)
-- El hash generado es para 'admin123' usando bcrypt
INSERT INTO usuarios (nombre, apellidos, email, username, password, rol_id, activo, tema) VALUES 
('Admin', 'Principal', 'admin@facturacion.local', 'admin', '$2y$12$7AcwMRTInBvb7.Q5dXlNEOgdmlPwcG4ZfRptsREq5bxUTp0A4/7U2', 1, 1, 'light');

-- Configuración de la Empresa (Por defecto)
INSERT INTO configuracion_empresa (ruc, razon_social, nombre_comercial, direccion, ubigeo, distrito, provincia, departamento, telefono, email, web, moneda_principal, tipo_cambio_usd, igv_porcentaje) VALUES 
('20123456789', 'Empresa de Informática Pucallpa SAC', 'Informatica Pucallpa', 'Av. Centenario 123', '250101', 'Calleria', 'Coronel Portillo', 'Ucayali', '999888777', 'contacto@informaticapucallpa.com', 'www.informaticapucallpa.com', 'PEN', 3.75, 18.00);

-- Configuración SUNAT (Por defecto, modo Beta)
INSERT INTO configuracion_sunat (ruc, usuario_sol, clave_sol_encrypted, modo, serie_factura, serie_boleta, serie_nota_credito, correlativo_factura, correlativo_boleta) VALUES 
('20123456789', 'MODDATOS', 'moddatos', 'beta', 'F001', 'B001', 'FC01', 1, 1);

-- Caja principal
INSERT INTO cajas (nombre, descripcion, activo, estado) VALUES 
('Caja Principal', 'Caja general de la tienda', 1, 'cerrada');

-- Unidades de Medida Básicas (Código SUNAT)
INSERT INTO unidades_medida (codigo, nombre, abreviatura) VALUES 
('NIU', 'Unidades', 'UN'),
('KGM', 'Kilogramos', 'KG'),
('MTR', 'Metros', 'M'),
('BX', 'Cajas', 'CJ');
