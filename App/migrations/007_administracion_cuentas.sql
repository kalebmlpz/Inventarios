-- Ejecutar UNA VEZ después de 006_cancelaciones_devoluciones.sql, con respaldo previo.
-- Conserva las cuentas y sus contraseñas; todas las cuentas existentes siguen activas.
USE punto_de_venta;
ALTER TABLE usuarios ENGINE=InnoDB;
ALTER TABLE usuarios
 ADD activo tinyint(1) NOT NULL DEFAULT 1,
 ADD version_sesion int NOT NULL DEFAULT 0,
 ADD UNIQUE KEY username_unico (username);
CREATE TABLE cuentas_lock (id int PRIMARY KEY) ENGINE=InnoDB;
INSERT INTO cuentas_lock VALUES (1);
