-- Ejecutar UNA VEZ después de 008, con respaldo previo. No modifica ventas existentes.
USE punto_de_venta;
CREATE TABLE pedidos (
 id bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
 cliente varchar(100) NOT NULL,
 telefono varchar(30) NOT NULL,
 articulo_id int NOT NULL,
 nombre_snapshot varchar(100) NOT NULL,
 cantidad int NOT NULL,
 precio_unitario decimal(10,2) NOT NULL,
 fecha_entrega datetime NOT NULL,
 estado varchar(20) NOT NULL DEFAULT 'Pendiente',
 usuario_id int NOT NULL,
 creado_en datetime NOT NULL,
 actualizado_en datetime NOT NULL,
 version int NOT NULL DEFAULT 0,
 venta_id bigint DEFAULT NULL,
 operacion_id varchar(64) NOT NULL,
 solicitud_hash char(64) NOT NULL,
 UNIQUE KEY pedido_operacion (operacion_id),
 UNIQUE KEY pedido_venta (venta_id),
 INDEX pedidos_entrega (estado,fecha_entrega),
 INDEX pedidos_usuario (usuario_id),
 FOREIGN KEY (venta_id) REFERENCES registros(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE pedidos_eventos (
 id bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
 pedido_id bigint NOT NULL,
 usuario_id int NOT NULL,
 estado varchar(20) NOT NULL,
 creado_en datetime NOT NULL,
 operacion_id varchar(64) NOT NULL,
 solicitud_hash char(64) NOT NULL,
 UNIQUE KEY pedido_evento_operacion (operacion_id),
 FOREIGN KEY (pedido_id) REFERENCES pedidos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
