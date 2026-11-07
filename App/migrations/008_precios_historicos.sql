-- Ejecutar UNA VEZ después de 007_administracion_cuentas.sql, con respaldo previo.
-- No se asignan precios actuales a ventas antiguas.
USE punto_de_venta;
ALTER TABLE registros
 ADD precio_unitario decimal(10,2) DEFAULT NULL,
 ADD importe decimal(20,2) DEFAULT NULL,
 ADD moneda char(3) DEFAULT NULL;
