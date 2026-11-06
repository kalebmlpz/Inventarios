-- Ejecutar una sola vez después de 002_roles.sql. Conserva el catálogo existente.
USE punto_de_venta;
ALTER TABLE articulo
 MODIFY nombre varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
 ADD categoria varchar(30) NOT NULL DEFAULT 'Sin clasificar',
 ADD unidad varchar(20) NOT NULL DEFAULT 'Pieza',
 ADD stock_minimo int NOT NULL DEFAULT 0,
 ADD precio decimal(10,2) NOT NULL DEFAULT 0,
 ADD observaciones varchar(500) NOT NULL DEFAULT '',
 ADD modalidad varchar(20) NOT NULL DEFAULT 'Individual';
