-- Ejecutar UNA VEZ después de 003_catalogo_floreria.sql, con respaldo previo.
USE punto_de_venta;
ALTER TABLE articulo ENGINE=InnoDB;
ALTER TABLE registros ENGINE=InnoDB;
ALTER TABLE registros
 ADD id bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
 ADD tipo varchar(30) NOT NULL DEFAULT 'Venta',
 ADD nombre_snapshot varchar(100) DEFAULT NULL,
 ADD operacion_id varchar(64) DEFAULT NULL,
 ADD modo varchar(20) DEFAULT NULL,
 ADD UNIQUE KEY operacion_unica (operacion_id);
CREATE TABLE inventario_lock (id int PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO inventario_lock VALUES (1);
CREATE TABLE componentes (
 arreglo_id int NOT NULL,
 componente_id int NOT NULL,
 cantidad int NOT NULL,
 PRIMARY KEY(arreglo_id, componente_id),
 FOREIGN KEY(arreglo_id) REFERENCES articulo(id_articulo) ON DELETE CASCADE,
 FOREIGN KEY(componente_id) REFERENCES articulo(id_articulo) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE movimientos (
 id bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
 registro_id bigint NOT NULL,
 sku varchar(50) NOT NULL,
 nombre varchar(100) NOT NULL,
 cantidad int NOT NULL,
 stock_resultante int NOT NULL,
 FOREIGN KEY(registro_id) REFERENCES registros(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE articulo SET modalidad='Preparado' WHERE categoria='Arreglos' AND modalidad='Individual';
