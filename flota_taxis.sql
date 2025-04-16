-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-04-2025 a las 17:05:55
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `flota_taxis`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `amonestaciones`
--

CREATE TABLE `amonestaciones` (
  `id` int(11) NOT NULL,
  `conductor_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` text NOT NULL,
  `estado` enum('activa','pagada','anulada') DEFAULT 'activa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `amonestaciones`
--

INSERT INTO `amonestaciones` (`id`, `conductor_id`, `fecha`, `monto`, `descripcion`, `estado`) VALUES
(1, 20, '2025-04-10', 10000.00, '', 'pagada'),
(2, 16, '2025-04-10', 10000.00, '', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cajas`
--

CREATE TABLE `cajas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `tipo` enum('efectivo','banco') DEFAULT NULL,
  `predeterminada` tinyint(1) DEFAULT 0,
  `saldo_actual` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cajas`
--

INSERT INTO `cajas` (`id`, `nombre`, `tipo`, `predeterminada`, `saldo_actual`) VALUES
(1, 'CAJA', 'efectivo', 1, 61000),
(2, 'CCEI BANK', 'banco', 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `categoria` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `categoria`) VALUES
(1, 'Filtros'),
(2, 'Aceites'),
(3, 'Neumáticos'),
(4, 'Llantas'),
(5, 'Cerredos'),
(6, 'Pastillas'),
(7, 'Discos'),
(8, 'Lubricantes'),
(9, 'Baterías'),
(10, 'Bombilla'),
(11, 'Gatos'),
(12, 'Herramientas'),
(13, 'Suspension'),
(14, 'Varios');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conductores`
--

CREATE TABLE `conductores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `dip` varchar(20) NOT NULL,
  `vehiculo_id` int(11) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  `ingreso_dia_obligatorio` int(11) NOT NULL DEFAULT 0,
  `ingreso_dia_libre` int(11) NOT NULL DEFAULT 0,
  `ingreso_obligatorio` int(11) DEFAULT NULL,
  `ingreso_libre` int(11) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `dias_por_ciclo` int(11) DEFAULT 30,
  `salario_mensual` int(11) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT 'Sin dirección'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `conductores`
--

INSERT INTO `conductores` (`id`, `nombre`, `telefono`, `dip`, `vehiculo_id`, `estado`, `ingreso_dia_obligatorio`, `ingreso_dia_libre`, `ingreso_obligatorio`, `ingreso_libre`, `imagen`, `fecha`, `dias_por_ciclo`, `salario_mensual`, `direccion`) VALUES
(15, 'AGUSTIN BAKALE', '222233355', '3421', 13, 'Activo', 0, 0, 14000, 10000, '67f5bd110968e_im1.png', '2025-01-01', 28, 60000, 'Sin dirección'),
(16, 'MIGUEL', '1222', '1278', 14, 'Activo', 0, 0, 14000, 10000, '67f5bd3730542_t1.jpg', '2025-04-01', 30, 60000, 'Sin dirección'),
(17, 'PEDRO DANIEL ITAMBA', '222231252', '1094856', 15, 'Activo', 0, 0, 13000, 10000, NULL, '2025-04-01', 28, 60000, 'Sin dirección'),
(18, 'ROMAN EDU MITOGO', '222990099', '3344', 16, 'Activo', 0, 0, 14000, 10000, NULL, '2025-04-10', 30, 60000, 'Sin dirección'),
(19, 'JOSE MARY AKULU EDU', '222142342', '98765', 17, 'Activo', 0, 0, 14000, 10000, NULL, '2025-04-01', 30, 60000, 'Sin dirección'),
(20, 'FERMIN MOTANGA MEDIKO', '222675432', '987183', 18, 'Activo', 0, 0, 14000, 10000, NULL, '2025-04-01', 30, 60000, 'VIVE EN MONDONG');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ingresos`
--

CREATE TABLE `ingresos` (
  `id` int(11) NOT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `tipo_ingreso` enum('obligatorio','libre') DEFAULT NULL,
  `monto` int(11) DEFAULT NULL,
  `monto_esperado` int(11) DEFAULT NULL,
  `monto_pendiente` int(11) DEFAULT NULL,
  `kilometros` int(11) DEFAULT NULL,
  `recorrido` int(11) DEFAULT NULL,
  `ciclo` int(11) DEFAULT NULL,
  `caja_id` int(11) DEFAULT 1,
  `monto_ingresado` decimal(10,2) NOT NULL,
  `contador_ciclo` int(11) DEFAULT 0,
  `ciclo_completado` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ingresos`
--

INSERT INTO `ingresos` (`id`, `conductor_id`, `fecha`, `tipo_ingreso`, `monto`, `monto_esperado`, `monto_pendiente`, `kilometros`, `recorrido`, `ciclo`, `caja_id`, `monto_ingresado`, `contador_ciclo`, `ciclo_completado`) VALUES
(61, 15, '2025-04-01', 'obligatorio', NULL, 14000, 0, 100500, 0, 0, 1, 14000.00, 1, 0),
(62, 15, '2025-04-02', 'obligatorio', NULL, 14000, 0, 100510, 10, 0, 1, 14000.00, 2, 0),
(63, 15, '2025-04-03', 'obligatorio', NULL, 14000, 1000, 100525, 15, 0, 1, 13000.00, 3, 0),
(65, 15, '2025-04-06', 'libre', NULL, 10000, 1000, 100600, 75, 0, 1, 9000.00, 3, 0),
(66, 15, '2025-04-07', 'obligatorio', NULL, 14000, 2000, 100610, 10, 0, 1, 12000.00, 4, 0),
(67, 20, '2025-04-01', 'obligatorio', NULL, 14000, 0, 100250, 0, 0, 1, 14000.00, 1, 0),
(68, 20, '2025-04-02', 'obligatorio', NULL, 14000, -1000, 100255, 5, 0, 1, 15000.00, 2, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mantenimientos`
--

CREATE TABLE `mantenimientos` (
  `id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `tipo` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `kilometraje_actual` int(11) NOT NULL,
  `km_proximo_mantenimiento` int(11) NOT NULL,
  `estado_antes` text DEFAULT NULL,
  `estado_despues` text DEFAULT NULL,
  `taller` varchar(255) DEFAULT NULL,
  `costo` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mantenimiento_productos`
--

CREATE TABLE `mantenimiento_productos` (
  `id` int(11) NOT NULL,
  `mantenimiento_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`cantidad` * `precio_unitario`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_caja`
--

CREATE TABLE `movimientos_caja` (
  `id` int(11) NOT NULL,
  `caja_id` int(11) NOT NULL,
  `ingreso_id` int(11) DEFAULT NULL,
  `pago_prestamo_id` int(11) DEFAULT NULL,
  `pago_amonestacion_id` int(11) DEFAULT NULL,
  `prestamo_id` int(11) DEFAULT NULL,
  `tipo` enum('ingreso','egreso') NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `movimientos_caja`
--

INSERT INTO `movimientos_caja` (`id`, `caja_id`, `ingreso_id`, `pago_prestamo_id`, `pago_amonestacion_id`, `prestamo_id`, `tipo`, `monto`, `descripcion`, `fecha`) VALUES
(72, 1, 61, NULL, NULL, NULL, 'ingreso', 14000.00, 'Ingreso de AGUSTIN BAKALE', '2025-04-14 23:40:03'),
(73, 1, 62, NULL, NULL, NULL, 'ingreso', 14000.00, 'Ingreso de AGUSTIN BAKALE', '2025-04-14 23:40:22'),
(74, 1, 63, NULL, NULL, NULL, 'ingreso', 10000.00, 'Ingreso de AGUSTIN BAKALE', '2025-04-14 23:40:39'),
(76, 1, 65, NULL, NULL, NULL, 'ingreso', 9000.00, 'Ingreso de AGUSTIN BAKALE', '2025-04-14 23:47:46'),
(77, 1, 66, NULL, NULL, NULL, 'ingreso', 12000.00, 'Ingreso de AGUSTIN BAKALE', '2025-04-14 23:48:12'),
(78, 1, 67, NULL, NULL, NULL, 'ingreso', 10000.00, 'Ingreso de FERMIN MOTANGA MEDIKO', '2025-04-14 23:48:38'),
(79, 1, 68, NULL, NULL, NULL, 'ingreso', 15000.00, 'Ingreso de FERMIN MOTANGA MEDIKO', '2025-04-14 23:49:18'),
(80, 1, 63, NULL, NULL, NULL, '', 2000.00, 'Pago de saldo pendiente - Ingreso ID: 63', '2025-04-15 09:10:49'),
(81, 1, 67, NULL, NULL, NULL, '', 1000.00, 'Pago de saldo pendiente - Ingreso ID: 67', '2025-04-15 18:28:13'),
(82, 1, 67, NULL, NULL, NULL, '', 1000.00, 'Pago de saldo pendiente - Ingreso ID: 67', '2025-04-15 18:28:36'),
(83, 1, 67, NULL, NULL, NULL, '', 2000.00, 'Pago de saldo pendiente - Ingreso ID: 67', '2025-04-15 18:56:51'),
(86, 1, NULL, NULL, NULL, NULL, 'egreso', 5000.00, 'Préstamo a conductor ID: 15', '2025-04-15 20:50:18'),
(87, 1, NULL, NULL, NULL, NULL, 'egreso', 5000.00, 'Préstamo a conductor ID: 15', '2025-04-15 20:50:36'),
(88, 1, NULL, NULL, NULL, 12, 'egreso', 10000.00, 'Préstamo a conductor: PRESTAMO', '2025-04-01 23:00:00'),
(89, 1, NULL, NULL, NULL, 13, 'egreso', 10000.00, 'Préstamo a conductor: ', '2025-04-15 23:00:00'),
(92, 1, 63, NULL, NULL, NULL, '', 1000.00, 'Pago de saldo pendiente - Ingreso ID: 63', '2025-04-16 14:40:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_amonestaciones`
--

CREATE TABLE `pagos_amonestaciones` (
  `id` int(11) NOT NULL,
  `amonestacion_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_ingresos_pendientes`
--

CREATE TABLE `pagos_ingresos_pendientes` (
  `id` int(11) NOT NULL,
  `conductor_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_prestamos`
--

CREATE TABLE `pagos_prestamos` (
  `id` int(11) NOT NULL,
  `prestamo_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_salarios`
--

CREATE TABLE `pagos_salarios` (
  `id` int(11) NOT NULL,
  `conductor_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `ciclo` varchar(50) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prestamos`
--

CREATE TABLE `prestamos` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `conductor_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `saldo_pendiente` decimal(10,2) DEFAULT 0.00,
  `estado` varchar(20) DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `prestamos`
--

INSERT INTO `prestamos` (`id`, `fecha`, `conductor_id`, `monto`, `descripcion`, `saldo_pendiente`, `estado`) VALUES
(10, '2025-04-01', 15, 5000.00, '', 5000.00, 'pendiente'),
(11, '2025-04-01', 15, 5000.00, '', 5000.00, 'pendiente'),
(12, '2025-04-02', 15, 10000.00, 'PRESTAMO', 10000.00, 'pendiente'),
(13, '2025-04-16', 15, 10000.00, '', 10000.00, 'pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `referencia` varchar(100) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 0,
  `precio` decimal(10,2) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `imagen1` varchar(255) DEFAULT NULL,
  `imagen2` varchar(255) DEFAULT NULL,
  `imagen3` varchar(255) DEFAULT NULL,
  `imagen4` varchar(255) DEFAULT NULL,
  `codigo_barras` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `referencia`, `nombre`, `stock`, `stock_minimo`, `precio`, `descripcion`, `categoria_id`, `imagen1`, `imagen2`, `imagen3`, `imagen4`, `codigo_barras`) VALUES
(4, 'ABCD', 'RUEDAS', 1, 1, 5000.00, 'RUEDAS DE COCHE', 3, '67f6ae747157d_t1.jpg', NULL, NULL, NULL, ''),
(5, '123', 'PASTILLAS', 0, 0, 1000.00, 'FRENO', 6, NULL, NULL, NULL, NULL, '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','supervisor','operador') NOT NULL DEFAULT 'operador',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `rol`, `fecha_creacion`, `ultimo_login`) VALUES
(1, 'Administrador', 'admin@taxis.com', '$2y$10$dkw4eOClfOy70jqJiQ4TO.jFlxRyexHgDMMdID70lFi465ZHD0ugq', 'admin', '2025-04-11 22:26:35', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos`
--

CREATE TABLE `vehiculos` (
  `id` int(11) NOT NULL,
  `marca` varchar(50) NOT NULL,
  `modelo` varchar(50) NOT NULL,
  `matricula` varchar(20) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `km_actual` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `km_aceite` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `km_inicial` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `imagen1` varchar(255) DEFAULT NULL,
  `imagen2` varchar(255) DEFAULT NULL,
  `imagen3` varchar(255) DEFAULT NULL,
  `imagen4` varchar(255) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `year` year(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `vehiculos`
--

INSERT INTO `vehiculos` (`id`, `marca`, `modelo`, `matricula`, `numero`, `km_actual`, `km_aceite`, `km_inicial`, `imagen1`, `imagen2`, `imagen3`, `imagen4`, `fecha`, `year`) VALUES
(13, 'TOYOTA', 'COROLLA', 'WN-501-AP', '21-AR', 100610, 105000, 100000, '67f5cb64390a8_t1.jpg', '67f5cb64393ec_t2.jpg', '67f5cb643964e_t3.jpg', '67f5cb64398cc_t4.jpg', NULL, '2000'),
(14, 'TOYOTA', 'AVENSIS', 'WN-123-AR', '22-AR', 100000, 105000, 100000, NULL, NULL, NULL, NULL, NULL, '2000'),
(15, 'NISSAN', 'PRIMERA', 'WN-546-AR', '25-AR', 100000, 105000, 100000, NULL, NULL, NULL, NULL, '2025-03-01', '2001'),
(16, 'TOYOTA', 'CAMRY', 'WN-111-A', '11A', 100000, 105000, 100000, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'RENAULT', 'CLIO', 'WN-222-B', '22B', 100000, 105000, 100000, NULL, NULL, NULL, NULL, NULL, '1902'),
(18, 'MAZDA', 'M3', 'LT-333-C', '33C', 100255, 105000, 100000, NULL, NULL, NULL, NULL, NULL, '2000');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `amonestaciones`
--
ALTER TABLE `amonestaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conductor_id` (`conductor_id`);

--
-- Indices de la tabla `cajas`
--
ALTER TABLE `cajas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `conductores`
--
ALTER TABLE `conductores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dip` (`dip`),
  ADD UNIQUE KEY `unique_vehiculo` (`vehiculo_id`);

--
-- Indices de la tabla `ingresos`
--
ALTER TABLE `ingresos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conductor_id` (`conductor_id`),
  ADD KEY `caja_id` (`caja_id`);

--
-- Indices de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `conductor_id` (`conductor_id`);

--
-- Indices de la tabla `mantenimiento_productos`
--
ALTER TABLE `mantenimiento_productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mantenimiento_id` (`mantenimiento_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD PRIMARY KEY (`id`),
  ADD KEY `caja_id` (`caja_id`),
  ADD KEY `fk_movimiento_ingreso` (`ingreso_id`),
  ADD KEY `fk_movimiento_pago_prestamo` (`pago_prestamo_id`),
  ADD KEY `fk_movimiento_pago_amonestacion` (`pago_amonestacion_id`),
  ADD KEY `fk_movimiento_prestamo` (`prestamo_id`);

--
-- Indices de la tabla `pagos_amonestaciones`
--
ALTER TABLE `pagos_amonestaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `amonestacion_id` (`amonestacion_id`);

--
-- Indices de la tabla `pagos_ingresos_pendientes`
--
ALTER TABLE `pagos_ingresos_pendientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conductor_id` (`conductor_id`);

--
-- Indices de la tabla `pagos_prestamos`
--
ALTER TABLE `pagos_prestamos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prestamo_id` (`prestamo_id`);

--
-- Indices de la tabla `pagos_salarios`
--
ALTER TABLE `pagos_salarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conductor_id` (`conductor_id`);

--
-- Indices de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conductor_id` (`conductor_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_categoria` (`categoria_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricula` (`matricula`),
  ADD UNIQUE KEY `numero` (`numero`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `amonestaciones`
--
ALTER TABLE `amonestaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `cajas`
--
ALTER TABLE `cajas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `conductores`
--
ALTER TABLE `conductores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `ingresos`
--
ALTER TABLE `ingresos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT de la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `mantenimiento_productos`
--
ALTER TABLE `mantenimiento_productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT de la tabla `pagos_amonestaciones`
--
ALTER TABLE `pagos_amonestaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `pagos_ingresos_pendientes`
--
ALTER TABLE `pagos_ingresos_pendientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `pagos_prestamos`
--
ALTER TABLE `pagos_prestamos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `pagos_salarios`
--
ALTER TABLE `pagos_salarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `prestamos`
--
ALTER TABLE `prestamos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `amonestaciones`
--
ALTER TABLE `amonestaciones`
  ADD CONSTRAINT `amonestaciones_ibfk_1` FOREIGN KEY (`conductor_id`) REFERENCES `conductores` (`id`);

--
-- Filtros para la tabla `conductores`
--
ALTER TABLE `conductores`
  ADD CONSTRAINT `conductores_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`);

--
-- Filtros para la tabla `ingresos`
--
ALTER TABLE `ingresos`
  ADD CONSTRAINT `ingresos_ibfk_1` FOREIGN KEY (`conductor_id`) REFERENCES `conductores` (`id`),
  ADD CONSTRAINT `ingresos_ibfk_2` FOREIGN KEY (`caja_id`) REFERENCES `cajas` (`id`);

--
-- Filtros para la tabla `mantenimientos`
--
ALTER TABLE `mantenimientos`
  ADD CONSTRAINT `mantenimientos_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mantenimientos_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `conductores` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `mantenimiento_productos`
--
ALTER TABLE `mantenimiento_productos`
  ADD CONSTRAINT `mantenimiento_productos_ibfk_1` FOREIGN KEY (`mantenimiento_id`) REFERENCES `mantenimientos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mantenimiento_productos_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD CONSTRAINT `fk_movimiento_ingreso` FOREIGN KEY (`ingreso_id`) REFERENCES `ingresos` (`id`),
  ADD CONSTRAINT `fk_movimiento_pago_amonestacion` FOREIGN KEY (`pago_amonestacion_id`) REFERENCES `pagos_amonestaciones` (`id`),
  ADD CONSTRAINT `fk_movimiento_pago_prestamo` FOREIGN KEY (`pago_prestamo_id`) REFERENCES `pagos_prestamos` (`id`),
  ADD CONSTRAINT `fk_movimiento_prestamo` FOREIGN KEY (`prestamo_id`) REFERENCES `prestamos` (`id`),
  ADD CONSTRAINT `movimientos_caja_ibfk_1` FOREIGN KEY (`caja_id`) REFERENCES `cajas` (`id`);

--
-- Filtros para la tabla `pagos_amonestaciones`
--
ALTER TABLE `pagos_amonestaciones`
  ADD CONSTRAINT `pagos_amonestaciones_ibfk_1` FOREIGN KEY (`amonestacion_id`) REFERENCES `amonestaciones` (`id`);

--
-- Filtros para la tabla `pagos_ingresos_pendientes`
--
ALTER TABLE `pagos_ingresos_pendientes`
  ADD CONSTRAINT `pagos_ingresos_pendientes_ibfk_1` FOREIGN KEY (`conductor_id`) REFERENCES `conductores` (`id`);

--
-- Filtros para la tabla `pagos_prestamos`
--
ALTER TABLE `pagos_prestamos`
  ADD CONSTRAINT `pagos_prestamos_ibfk_1` FOREIGN KEY (`prestamo_id`) REFERENCES `prestamos` (`id`);

--
-- Filtros para la tabla `pagos_salarios`
--
ALTER TABLE `pagos_salarios`
  ADD CONSTRAINT `pagos_salarios_ibfk_1` FOREIGN KEY (`conductor_id`) REFERENCES `conductores` (`id`);

--
-- Filtros para la tabla `prestamos`
--
ALTER TABLE `prestamos`
  ADD CONSTRAINT `prestamos_ibfk_1` FOREIGN KEY (`conductor_id`) REFERENCES `conductores` (`id`);

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
