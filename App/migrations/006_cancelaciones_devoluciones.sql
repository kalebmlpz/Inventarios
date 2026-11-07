-- Ejecutar UNA VEZ después de 005_entradas_mermas.sql, con respaldo previo.
-- Conserva los registros anteriores; no se infieren identidades a partir de códigos reutilizables.
USE punto_de_venta;
ALTER TABLE registros
 ADD articulo_id int DEFAULT NULL,
 ADD unidad_snapshot varchar(20) DEFAULT NULL,
 ADD categoria_snapshot varchar(30) DEFAULT NULL,
 ADD venta_origen_id bigint DEFAULT NULL,
 ADD tratamiento varchar(30) DEFAULT NULL,
 ADD INDEX venta_origen (venta_origen_id);
ALTER TABLE movimientos
 ADD articulo_id int DEFAULT NULL,
 ADD unidad_snapshot varchar(20) DEFAULT NULL,
 ADD categoria_snapshot varchar(30) DEFAULT NULL;
