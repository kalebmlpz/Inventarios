-- Ejecutar UNA VEZ después de 004_componentes_existencias.sql, con respaldo previo.
-- Conserva los productos y movimientos existentes.
USE punto_de_venta;
ALTER TABLE registros
 ADD motivo varchar(500) DEFAULT NULL,
 ADD solicitud_hash char(64) DEFAULT NULL;
