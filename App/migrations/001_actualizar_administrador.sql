-- Ejecutar una sola vez sobre la base existente punto_de_venta.
-- Conserva productos y registros; actualiza la cuenta original (id 1).
USE punto_de_venta;
UPDATE usuarios
SET username = 'Admin', password = 'Admin123'
WHERE id = 1;
