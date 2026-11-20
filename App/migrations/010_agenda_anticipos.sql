-- Aplicar después de 009 sobre una base existente, con respaldo previo.
USE punto_de_venta;

CREATE TABLE pedidos_pagos (
 id bigint NOT NULL AUTO_INCREMENT PRIMARY KEY,
 pedido_id bigint NOT NULL,
 usuario_id int NOT NULL,
 importe decimal(20,2) NOT NULL,
 creado_en datetime NOT NULL,
 operacion_id varchar(64) NOT NULL,
 UNIQUE KEY pedido_pago_operacion (operacion_id),
 FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
