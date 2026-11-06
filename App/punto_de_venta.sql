-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 22-05-2023 a las 06:57:14
-- Versión del servidor: 8.0.31
-- Versión de PHP: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `punto_de_venta`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `articulo`
--

DROP TABLE IF EXISTS `articulo`;
CREATE TABLE IF NOT EXISTS `articulo` (
  `id_articulo` int NOT NULL AUTO_INCREMENT,
  `sku` int NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `marca` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `modelo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci NOT NULL,
  `departamento` int NOT NULL,
  `clase` int NOT NULL,
  `familia` int NOT NULL,
  `fecha_alta` date NOT NULL,
  `stock` int NOT NULL,
  `cantidad` int NOT NULL,
  `descontinuado` int NOT NULL,
  `fecha_baja` date NOT NULL,
  `categoria` varchar(30) NOT NULL DEFAULT 'Sin clasificar',
  `unidad` varchar(20) NOT NULL DEFAULT 'Pieza',
  `stock_minimo` int NOT NULL DEFAULT 0,
  `precio` decimal(10,2) NOT NULL DEFAULT 0,
  `observaciones` varchar(500) NOT NULL DEFAULT '',
  `modalidad` varchar(20) NOT NULL DEFAULT 'Individual',
  PRIMARY KEY (`id_articulo`)
) ENGINE=MyISAM AUTO_INCREMENT=1300 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `articulo`
--

INSERT INTO `articulo` (`id_articulo`, `sku`, `nombre`, `marca`, `modelo`, `departamento`, `clase`, `familia`, `fecha_alta`, `stock`, `cantidad`, `descontinuado`, `fecha_baja`) VALUES
(1289, 123456, 'Teclado', 'Red Dragon', 'K66', 2, 1, 2, '2023-05-21', 12, 2, 0, '1900-01-01'),
(1290, 312, 'asd', 'asd', 'asd', 2, 1, 2, '2023-05-21', 21, 2, 0, '1900-01-01'),
(1291, 123, 'Silla', 'Uno', 'Dos', 3, 1, 3, '2023-05-21', 12, 1, 0, '1900-01-01'),
(1296, 1, 'Pan', 'Aurrerá', 'Pan integral', 1, 1, 1, '2023-05-21', 12, 2, 0, '1900-01-01'),
(1297, 10, 'Pan', 'Aurrera', 'Pan integral', 1, 1, 1, '2023-05-21', 10, 5, 0, '1900-01-01'),
(1298, 2, 'Pan Tacha', 'Aurrera', 'Pan integral', 2, 1, 2, '2023-05-21', 10, 5, 0, '1900-01-01'),
(1299, 4, 'Silla Plegable', 'Aurrera', 'AirCin', 3, 1, 2, '2023-05-22', 10, 5, 0, '1900-01-01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clases`
--

DROP TABLE IF EXISTS `clases`;
CREATE TABLE IF NOT EXISTS `clases` (
  `id_clase` int NOT NULL AUTO_INCREMENT,
  `num_clase` int DEFAULT NULL,
  `nombre_clase` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `id_dep` int DEFAULT NULL,
  PRIMARY KEY (`id_clase`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `clases`
--

INSERT INTO `clases` (`id_clase`, `num_clase`, `nombre_clase`, `id_dep`) VALUES
(1, 1, 'COMESTIBLES', 1),
(2, 2, 'LICUADORAS', 1),
(3, 3, 'BATIDORAS', 1),
(4, 4, 'CAFETERAS', 1),
(5, 1, 'AMPLIFICADORES CAR AUDIO', 2),
(6, 2, 'AUTO STEREOS', 2),
(7, 1, 'COLCHON', 3),
(8, 2, 'JUEGO BOX', 3),
(9, 1, 'SALAS', 4),
(10, 2, 'COMPLEMENTOS PARA SALA', 4),
(11, 3, 'SOFAS CAMA', 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departamentos`
--

DROP TABLE IF EXISTS `departamentos`;
CREATE TABLE IF NOT EXISTS `departamentos` (
  `id_dep` int NOT NULL,
  `nombre_departamento` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  PRIMARY KEY (`id_dep`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `departamentos`
--

INSERT INTO `departamentos` (`id_dep`, `nombre_departamento`) VALUES
(1, 'DOMESTICOS'),
(2, 'ELECTRONICA'),
(3, 'MUEBLE SUELTO'),
(4, 'SALAS, RECAMARAS, COMEDORES');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `familias`
--

DROP TABLE IF EXISTS `familias`;
CREATE TABLE IF NOT EXISTS `familias` (
  `id_familia` int NOT NULL,
  `num_familia` int NOT NULL,
  `nombre_familia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci DEFAULT NULL,
  `num_clase` int DEFAULT NULL,
  `id_dep` int NOT NULL,
  PRIMARY KEY (`id_familia`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `familias`
--

INSERT INTO `familias` (`id_familia`, `num_familia`, `nombre_familia`, `num_clase`, `id_dep`) VALUES
(24, 9, 'PAQUETE CON CD', 2, 2),
(23, 8, 'PAQUETE SIN MECANISMO', 2, 2),
(22, 7, 'MULTIMEDIA', 2, 2),
(21, 6, 'CON CD', 2, 2),
(20, 5, 'SIN MECANISMO', 2, 2),
(19, 4, 'ALARMA AUTO/CASA/OFICINA', 2, 2),
(18, 3, 'AMPLIFICADOR', 2, 2),
(17, 2, 'ACCESORIOS CAR AUDIO', 2, 2),
(16, 1, 'AUTOESTEREO CD/BO', 2, 2),
(15, 3, 'AMPLIFICADORES', 1, 2),
(14, 2, 'KIT DE INSTALACION', 1, 2),
(13, 1, 'AMPLIFICADOR/RECEPTOR', 1, 2),
(12, 2, 'PERCOLADORAS', 4, 1),
(11, 1, 'CAFETERAS', 4, 1),
(10, 7, 'EXCLUSIVO EN LINEA', 3, 1),
(9, 6, 'MULTIPRACTICOS', 3, 1),
(8, 5, 'BATIDORA FUENTE DE SO', 3, 1),
(7, 4, 'BATIDORA PEDESTAL', 3, 1),
(6, 3, 'PICADORA', 3, 1),
(5, 2, 'PROCESADOR', 3, 1),
(4, 1, 'BATIDORA MANUAL', 3, 1),
(3, 2, 'EXCLUSIVO EN LINEA', 2, 1),
(2, 1, 'LICUADORAS', 2, 1),
(1, 0, 'SIN NOMBRE', 1, 1),
(25, 1, 'PILLOW TOP KS', 1, 3),
(26, 2, 'PILLOW TOP DOBLE KS', 1, 3),
(27, 3, 'HULE ESPUMA KS', 1, 3),
(28, 1, 'ESTANDAR INDIVIDUAL', 2, 3),
(29, 2, 'PILLOW TOP INDIVIDUAL', 2, 3),
(30, 3, 'PILLOW TOP DOBLE INDIVIDUAL', 2, 3),
(31, 1, 'ESQUINERAS SUPERIORES', 1, 4),
(32, 2, 'TIPO L SECCIONAL', 1, 4),
(33, 1, 'SILLON OCASIONAL', 2, 4),
(34, 2, 'PUFF', 2, 4),
(35, 3, 'BAUL', 2, 4),
(36, 4, 'TABURETE', 2, 4),
(37, 1, 'SOFA CAMA TAPIZADO', 3, 4),
(38, 2, 'SOFA CAMA CLASICO', 3, 4),
(39, 3, 'ESTUDIO', 3, 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registros`
--

DROP TABLE IF EXISTS `registros`;
CREATE TABLE IF NOT EXISTS `registros` (
  `sku` varchar(50) DEFAULT NULL,
  `cantidad` int DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `usuario_id` int DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `registros`
--

INSERT INTO `registros` (`sku`, `cantidad`, `fecha`) VALUES
('123', 12, '2023-05-21'),
('1', 4, '2023-05-21'),
('123', 21, '2023-05-21'),
('312', 5, '2023-05-21'),
('312', 700, '2023-05-21'),
('1', 1, '2023-05-21'),
('1', 5, '2023-05-21'),
('123', 21, '2023-05-21'),
('4', 3, '2023-05-22'),
('1', 5, '2023-05-22'),
('1', 2, '2023-05-22');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('Administrador','Cajero') NOT NULL DEFAULT 'Cajero',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `password`, `rol`) VALUES
(1, 'Admin', 'Admin123', 'Administrador');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Ejecutar UNA VEZ después de 003_catalogo_floreria.sql, con respaldo previo.

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
