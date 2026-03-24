-- Script de configuración para Facturación Electrónica ARCA
-- Ejecutar este script para crear las tablas necesarias

USE c2880275_ventas;

-- Tabla para configuración de facturación electrónica
CREATE TABLE IF NOT EXISTS facturacion_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuit BIGINT NOT NULL COMMENT 'CUIT del emisor',
    razon_social VARCHAR(255) NOT NULL COMMENT 'Razón social del negocio',
    punto_venta INT NOT NULL COMMENT 'Punto de venta habilitado en ARCA',
    cert_path VARCHAR(255) NOT NULL COMMENT 'Ruta al certificado digital (.crt)',
    key_path VARCHAR(255) NOT NULL COMMENT 'Ruta a la clave privada (.key)',
    produccion TINYINT(1) DEFAULT 0 COMMENT '0=Testing, 1=Producción',
    inicio_actividades DATE COMMENT 'Fecha de inicio de actividades',
    ingresos_brutos VARCHAR(50) COMMENT 'Número de Ingresos Brutos',
    iva_condition VARCHAR(50) DEFAULT 'IVA Responsable Inscripto' COMMENT 'Condición frente al IVA',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para almacenar facturas electrónicas
CREATE TABLE IF NOT EXISTS facturas_electronicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_venta INT NOT NULL COMMENT 'ID de la venta asociada',
    tipo_comprobante INT NOT NULL COMMENT '1=FA, 6=FB, 11=FC, 3=NCA, 8=NCB, 13=NCC',
    punto_venta INT NOT NULL COMMENT 'Punto de venta',
    numero_comprobante BIGINT NOT NULL COMMENT 'Número de comprobante',
    fecha_emision DATE NOT NULL COMMENT 'Fecha de emisión',
    cae VARCHAR(14) COMMENT 'Código de Autorización Electrónica',
    vencimiento_cae DATE COMMENT 'Fecha de vencimiento del CAE',
    total DECIMAL(10,2) NOT NULL COMMENT 'Total de la factura',
    iva_total DECIMAL(10,2) DEFAULT 0 COMMENT 'Total de IVA',
    neto_gravado DECIMAL(10,2) DEFAULT 0 COMMENT 'Neto gravado',
    estado VARCHAR(20) DEFAULT 'pendiente' COMMENT 'pendiente, aprobado, rechazado, error',
    xml_request TEXT COMMENT 'Request XML enviado a ARCA',
    xml_response TEXT COMMENT 'Response XML recibido de ARCA',
    observaciones TEXT COMMENT 'Observaciones y errores',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_venta) REFERENCES ventas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_comprobante (tipo_comprobante, punto_venta, numero_comprobante),
    INDEX idx_id_venta (id_venta),
    INDEX idx_fecha (fecha_emision),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para tipos de comprobante
CREATE TABLE IF NOT EXISTS tipos_comprobante (
    id INT PRIMARY KEY,
    codigo VARCHAR(10) NOT NULL,
    descripcion VARCHAR(100) NOT NULL,
    discrimina_iva TINYINT(1) DEFAULT 0 COMMENT '1 si discrimina IVA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar tipos de comprobante
INSERT INTO tipos_comprobante (id, codigo, descripcion, discrimina_iva) VALUES
(1, 'FA', 'Factura A', 1),
(6, 'FB', 'Factura B', 1),
(11, 'FC', 'Factura C', 0),
(3, 'NCA', 'Nota de Crédito A', 1),
(8, 'NCB', 'Nota de Crédito B', 1),
(13, 'NCC', 'Nota de Crédito C', 0),
(2, 'NDA', 'Nota de Débito A', 1),
(7, 'NDB', 'Nota de Débito B', 1),
(12, 'NDC', 'Nota de Débito C', 0)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Agregar campos a la tabla cliente para facturación
-- Nota: Si las columnas ya existen, el script PHP ignora el error automáticamente
ALTER TABLE cliente ADD COLUMN cuit VARCHAR(13) AFTER dni;
ALTER TABLE cliente ADD COLUMN condicion_iva VARCHAR(50) DEFAULT 'Consumidor Final' AFTER cuit;
ALTER TABLE cliente ADD COLUMN tipo_documento INT DEFAULT 96 COMMENT '96=DNI, 80=CUIT' AFTER condicion_iva;

-- Crear tabla de condiciones IVA
CREATE TABLE IF NOT EXISTS condiciones_iva (
    id INT PRIMARY KEY,
    descripcion VARCHAR(100) NOT NULL,
    codigo_afip INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar condiciones IVA
INSERT INTO condiciones_iva (id, descripcion, codigo_afip) VALUES
(1, 'IVA Responsable Inscripto', 1),
(2, 'IVA Responsable no Inscripto', 2),
(3, 'IVA no Responsable', 3),
(4, 'IVA Sujeto Exento', 4),
(5, 'Consumidor Final', 5),
(6, 'Responsable Monotributo', 6),
(7, 'Sujeto no Categorizado', 7),
(8, 'Proveedor del Exterior', 8),
(9, 'Cliente del Exterior', 9),
(10, 'IVA Liberado - Ley N° 19.640', 10)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- Agregar índices para mejorar performance (se ignoran si ya existen)
CREATE INDEX IF NOT EXISTS idx_ventas_fecha ON ventas (fecha);
CREATE INDEX IF NOT EXISTS idx_ventas_cliente ON ventas (id_cliente);

-- Vista para reportes de facturación
CREATE OR REPLACE VIEW vista_facturas_completas AS
SELECT 
    f.id,
    f.id_venta,
    f.tipo_comprobante,
    tc.descripcion as tipo_comprobante_desc,
    CONCAT(LPAD(f.punto_venta, 4, '0'), '-', LPAD(f.numero_comprobante, 8, '0')) as numero_completo,
    f.fecha_emision,
    f.cae,
    f.vencimiento_cae,
    f.total,
    f.iva_total,
    f.neto_gravado,
    f.estado,
    v.id_cliente,
    c.nombre as cliente_nombre,
    c.dni as cliente_dni,
    c.cuit as cliente_cuit,
    c.condicion_iva as cliente_condicion_iva,
    u.nombre as vendedor_nombre
FROM facturas_electronicas f
INNER JOIN ventas v ON f.id_venta = v.id
LEFT JOIN cliente c ON v.id_cliente = c.idcliente
LEFT JOIN usuario u ON v.id_usuario = u.idusuario
LEFT JOIN tipos_comprobante tc ON f.tipo_comprobante = tc.id;

-- Trigger para validar que una venta no tenga múltiples facturas aprobadas
DELIMITER //

CREATE TRIGGER IF NOT EXISTS before_insert_factura
BEFORE INSERT ON facturas_electronicas
FOR EACH ROW
BEGIN
    DECLARE factura_existente INT;
    
    -- Verificar si ya existe una factura aprobada para esta venta
    SELECT COUNT(*) INTO factura_existente
    FROM facturas_electronicas
    WHERE id_venta = NEW.id_venta 
    AND estado = 'aprobado';
    
    IF factura_existente > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe una factura aprobada para esta venta';
    END IF;
END//

DELIMITER ;

-- Insertar configuración inicial de ejemplo (ACTUALIZAR CON TUS DATOS REALES)
INSERT INTO facturacion_config 
    (cuit, razon_social, punto_venta, cert_path, key_path, produccion, iva_condition) 
VALUES 
    (20123456789, 'OPTICA - Nombre del Negocio', 1, '/path/to/cert.crt', '/path/to/key.key', 0, 'IVA Responsable Inscripto')
ON DUPLICATE KEY UPDATE 
    razon_social = VALUES(razon_social);

-- Mostrar resumen de tablas creadas
SELECT 'Configuración completada. Tablas creadas:' as mensaje;
SHOW TABLES LIKE '%factur%';

-- Verificar estructura
SELECT 
    TABLE_NAME, 
    COLUMN_NAME, 
    COLUMN_TYPE, 
    IS_NULLABLE, 
    COLUMN_KEY,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'c2880275_ventas'
AND TABLE_NAME IN ('facturacion_config', 'facturas_electronicas', 'tipos_comprobante', 'condiciones_iva')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

