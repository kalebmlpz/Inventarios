-- Ejecutar UNA VEZ sobre una base existente. No borra productos ni registros.
USE punto_de_venta;
ALTER TABLE usuarios MODIFY password varchar(255) NOT NULL,
 ADD COLUMN rol enum('Administrador','Cajero') NOT NULL DEFAULT 'Cajero';
UPDATE usuarios SET rol = 'Administrador' WHERE id = 1;
ALTER TABLE registros ADD COLUMN usuario_id int DEFAULT NULL;
-- Los registros anteriores no tienen autor conocido: solo los ve el Administrador.
