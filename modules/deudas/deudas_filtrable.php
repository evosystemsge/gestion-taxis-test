<?php
include '../../layout/header.php';
require '../../config/database.php';

// Verificar si es una solicitud AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Manejar solicitudes AJAX
if ($isAjax && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['action']) {
            case 'obtener_pagos_prestamo':
                if (!isset($_GET['prestamo_id'])) {
                    throw new Exception('ID de préstamo no especificado');
                }
                
                $stmt = $pdo->prepare("
                    SELECT fecha, monto, descripcion 
                    FROM pagos_prestamos 
                    WHERE prestamo_id = ? 
                    ORDER BY fecha DESC
                ");
                $stmt->execute([$_GET['prestamo_id']]);
                $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($pagos);
                exit;
                
            case 'obtener_prestamo':
                if (!isset($_GET['id'])) {
                    throw new Exception('ID de préstamo no especificado');
                }
                
                $stmt = $pdo->prepare("
                    SELECT p.*, c.nombre AS conductor_nombre 
                    FROM prestamos p
                    JOIN conductores c ON p.conductor_id = c.id
                    WHERE p.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $prestamo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$prestamo) {
                    throw new Exception('Préstamo no encontrado');
                }
                
                echo json_encode($prestamo);
                exit;
                
            case 'obtener_pagos_amonestacion':
                if (!isset($_GET['amonestacion_id'])) {
                    throw new Exception('ID de amonestación no especificado');
                }
                
                $stmt = $pdo->prepare("
                    SELECT fecha, monto, descripcion 
                    FROM pagos_amonestaciones 
                    WHERE amonestacion_id = ? 
                    ORDER BY fecha DESC
                ");
                $stmt->execute([$_GET['amonestacion_id']]);
                $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($pagos);
                exit;
                
            case 'obtener_detalle_pago':
                if (!isset($_GET['tipo']) || !isset($_GET['id'])) {
                    throw new Exception('Parámetros incompletos');
                }
                
                $tipo = $_GET['tipo'];
                $id = $_GET['id'];
                
                switch ($tipo) {
                    case 'prestamo':
                        $stmt = $pdo->prepare("
                            SELECT pp.*, c.nombre AS conductor_nombre 
                            FROM pagos_prestamos pp
                            JOIN prestamos p ON pp.prestamo_id = p.id
                            JOIN conductores c ON p.conductor_id = c.id
                            WHERE pp.id = ?
                        ");
                        break;
                    case 'amonestacion':
                        $stmt = $pdo->prepare("
                            SELECT pa.*, c.nombre AS conductor_nombre 
                            FROM pagos_amonestaciones pa
                            JOIN amonestaciones a ON pa.amonestacion_id = a.id
                            JOIN conductores c ON a.conductor_id = c.id
                            WHERE pa.id = ?
                        ");
                        break;
                    case 'ingreso':
                        $stmt = $pdo->prepare("
                            SELECT pip.*, c.nombre AS conductor_nombre 
                            FROM pagos_ingresos_pendientes pip
                            JOIN conductores c ON pip.conductor_id = c.id
                            WHERE pip.id = ?
                        ");
                        break;
                    default:
                        throw new Exception('Tipo de pago no válido');
                }
                
                $stmt->execute([$id]);
                $pago = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$pago) {
                    throw new Exception('Pago no encontrado');
                }
                
                $pago['tipo'] = $tipo;
                echo json_encode($pago);
                exit;
                
            case 'obtener_deudas_conductor':
                if (!isset($_GET['conductor_id'])) {
                    throw new Exception('ID de conductor no especificado');
                }
                
                $deudas = [];
                
                // Amonestaciones activas
                $stmt = $pdo->prepare("
                    SELECT id, monto, 'amonestacion' AS tipo, 'Amonestación' AS descripcion
                    FROM amonestaciones 
                    WHERE conductor_id = ? AND estado = 'activa'
                ");
                $stmt->execute([$_GET['conductor_id']]);
                $deudas = array_merge($deudas, $stmt->fetchAll(PDO::FETCH_ASSOC));
                
                // Préstamos pendientes
                $stmt = $pdo->prepare("
                    SELECT id, saldo_pendiente AS monto, 'prestamo' AS tipo, 'Préstamo' AS descripcion
                    FROM prestamos 
                    WHERE conductor_id = ? AND estado = 'pendiente'
                ");
                $stmt->execute([$_GET['conductor_id']]);
                $deudas = array_merge($deudas, $stmt->fetchAll(PDO::FETCH_ASSOC));
                
                // Ingresos pendientes
                $stmt = $pdo->prepare("
                    SELECT id, monto_pendiente AS monto, 'ingreso' AS tipo, 'Ingreso pendiente' AS descripcion
                    FROM ingresos 
                    WHERE conductor_id = ? AND monto_pendiente > 0
                ");
                $stmt->execute([$_GET['conductor_id']]);
                $deudas = array_merge($deudas, $stmt->fetchAll(PDO::FETCH_ASSOC));
                
                echo json_encode(['deudas' => $deudas]);
                exit;
                
            case 'obtener_detalle_conductor':
                if (!isset($_GET['conductor_id'])) {
                    throw new Exception('ID de conductor no especificado');
                }
                
                // Obtener información básica del conductor
                $stmt = $pdo->prepare("
                    SELECT c.*, v.placa AS vehiculo_placa, v.modelo AS vehiculo_modelo
                    FROM conductores c
                    LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
                    WHERE c.id = ?
                ");
                $stmt->execute([$_GET['conductor_id']]);
                $conductor = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$conductor) {
                    throw new Exception('Conductor no encontrado');
                }
                
                // Obtener historial de pagos de salario
                $stmt = $pdo->prepare("
                    SELECT fecha, monto, descripcion
                    FROM pagos_salarios
                    WHERE conductor_id = ?
                    ORDER BY fecha DESC
                ");
                $stmt->execute([$_GET['conductor_id']]);
                $pagos_salario = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'conductor' => $conductor,
                    'pagos_salario' => $pagos_salario
                ]);
                exit;
                
            default:
                throw new Exception('Acción no válida');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Obtener la caja predeterminada al inicio del script
$caja_predeterminada = $pdo->query("SELECT id FROM cajas WHERE predeterminada = 1 LIMIT 1")->fetch();
if (!$caja_predeterminada) {
    die("No se ha configurado una caja predeterminada");
}
$caja_id = $caja_predeterminada['id'];

// LÓGICA DE ACCIONES: Agregar / Editar / Eliminar
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // Agregar préstamo
    if ($accion == 'agregar_prestamo') {
        $fecha = $_POST['fecha'] ?? '';
        $conductor_id = $_POST['conductor_id'] ?? 0;
        $monto = $_POST['monto'] ?? 0;
        $descripcion = $_POST['descripcion'] ?? '';

        if (empty($fecha) || $conductor_id <= 0 || $monto <= 0) {
            die("<script>alert('Datos incompletos o inválidos'); window.location.href='deudas.php';</script>");
        }

        $pdo->beginTransaction();
        try {
            // Insertar préstamo
            $stmt = $pdo->prepare("
                INSERT INTO prestamos (fecha, conductor_id, monto, descripcion, saldo_pendiente, estado)
                VALUES (?, ?, ?, ?, ?, 'pendiente')
            ");
            $stmt->execute([$fecha, $conductor_id, $monto, $descripcion, $monto]);
            $prestamo_id = $pdo->lastInsertId();
            
            // Registrar movimiento en caja (egreso)
            $stmt = $pdo->prepare("
                INSERT INTO movimientos_caja (
                    caja_id, 
                    tipo, 
                    monto, 
                    descripcion, 
                    fecha,
                    prestamo_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $caja_id,
                'egreso',
                $monto,
                "Préstamo a conductor ID: $conductor_id",
                $fecha,
                $prestamo_id
            ]);
            
            // Actualizar saldo de la caja
            $stmt = $pdo->prepare("
                UPDATE cajas 
                SET saldo_actual = saldo_actual - ?
                WHERE id = ?
            ");
            $stmt->execute([$monto, $caja_id]);
            
            $pdo->commit();
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("<script>alert('Error al registrar el préstamo: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }

    // Editar préstamo
    elseif ($accion == 'editar_prestamo') {
        $id = $_POST['id'] ?? 0;
        $fecha = $_POST['fecha'] ?? '';
        $conductor_id = $_POST['conductor_id'] ?? 0;
        $monto = $_POST['monto'] ?? 0;
        $descripcion = $_POST['descripcion'] ?? '';
        
        if ($id <= 0 || empty($fecha) || $conductor_id <= 0 || $monto <= 0) {
            die("<script>alert('Datos incompletos o inválidos'); window.location.href='deudas.php';</script>");
        }
        
        $pdo->beginTransaction();
        try {
            // Obtener el préstamo actual para calcular la diferencia
            $stmt = $pdo->prepare("SELECT monto FROM prestamos WHERE id = ?");
            $stmt->execute([$id]);
            $prestamo_actual = $stmt->fetch();
            
            if (!$prestamo_actual) {
                throw new Exception('Préstamo no encontrado');
            }
            
            $diferencia = $monto - $prestamo_actual['monto'];
            
            // Actualizar préstamo
            $stmt = $pdo->prepare("
                UPDATE prestamos 
                SET fecha = ?, conductor_id = ?, monto = ?, descripcion = ?, saldo_pendiente = saldo_pendiente + ?
                WHERE id = ?
            ");
            $stmt->execute([$fecha, $conductor_id, $monto, $descripcion, $diferencia, $id]);
            
            if ($diferencia != 0) {
                // Registrar movimiento en caja
                $tipo = $diferencia > 0 ? 'egreso' : 'ingreso';
                $abs_diferencia = abs($diferencia);
                
                $stmt = $pdo->prepare("
                    INSERT INTO movimientos_caja (
                        caja_id, 
                        tipo, 
                        monto, 
                        descripcion, 
                        fecha,
                        prestamo_id
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $caja_id,
                    $tipo,
                    $abs_diferencia,
                    "Ajuste préstamo ID: $id",
                    $fecha,
                    $id
                ]);
                
                // Actualizar saldo de la caja
                $stmt = $pdo->prepare("
                    UPDATE cajas 
                    SET saldo_actual = saldo_actual - ?
                    WHERE id = ?
                ");
                $stmt->execute([$diferencia, $caja_id]);
            }
            
            $pdo->commit();
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("<script>alert('Error al editar el préstamo: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }

    // Agregar pago de préstamo
    elseif ($accion == 'agregar_pago_prestamo') {
        $prestamo_id = $_POST['prestamo_id'] ?? 0;
        $fecha = $_POST['fecha'] ?? '';
        $monto = $_POST['monto'] ?? 0;
        $descripcion = $_POST['descripcion'] ?? '';
    
        if ($prestamo_id <= 0 || empty($fecha) || $monto <= 0) {
            die("<script>alert('Datos incompletos o inválidos'); window.location.href='deudas.php';</script>");
        }
    
        $pdo->beginTransaction();
        try {
            // 1. Insertar pago en tabla pagos_prestamos
            $stmt = $pdo->prepare("
                INSERT INTO pagos_prestamos (prestamo_id, fecha, monto, descripcion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$prestamo_id, $fecha, $monto, $descripcion]);
            $pago_id = $pdo->lastInsertId();
    
            // 2. Actualizar saldo pendiente del préstamo
            $stmt = $pdo->prepare("
                UPDATE prestamos 
                SET saldo_pendiente = saldo_pendiente - ?
                WHERE id = ?
            ");
            $stmt->execute([$monto, $prestamo_id]);
    
            // 3. Registrar movimiento en caja
            $stmt = $pdo->prepare("
                INSERT INTO movimientos_caja (
                    caja_id, 
                    tipo, 
                    monto, 
                    descripcion, 
                    fecha,
                    pago_prestamo_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $caja_id,
                'ingreso',
                $monto,
                "Pago préstamo ID: $prestamo_id",
                $fecha,
                $pago_id
            ]);
    
            // 4. Actualizar saldo de la caja predeterminada
            $stmt = $pdo->prepare("
                UPDATE cajas 
                SET saldo_actual = saldo_actual + ?
                WHERE id = ?
            ");
            $stmt->execute([$monto, $caja_id]);
    
            // 5. Verificar si el préstamo está completamente pagado
            $stmt = $pdo->prepare("SELECT saldo_pendiente FROM prestamos WHERE id = ?");
            $stmt->execute([$prestamo_id]);
            $saldo = $stmt->fetchColumn();
    
            if ($saldo <= 0) {
                $stmt = $pdo->prepare("UPDATE prestamos SET estado = 'pagado' WHERE id = ?");
                $stmt->execute([$prestamo_id]);
            }
    
            $pdo->commit();
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("<script>alert('Error al registrar el pago: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }

    // Agregar amonestación
    elseif ($accion == 'agregar_amonestacion') {
        $fecha = $_POST['fecha'] ?? '';
        $conductor_id = $_POST['conductor_id'] ?? 0;
        $monto = $_POST['monto'] ?? 0;
        $descripcion = $_POST['descripcion'] ?? '';

        if (empty($fecha) || $conductor_id <= 0 || $monto <= 0) {
            die("<script>alert('Datos incompletos o inválidos'); window.location.href='deudas.php';</script>");
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO amonestaciones (fecha, conductor_id, monto, descripcion, estado)
                VALUES (?, ?, ?, ?, 'activa')
            ");
            $stmt->execute([$fecha, $conductor_id, $monto, $descripcion]);
            
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            die("<script>alert('Error al registrar amonestación: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }

    // Agregar pago de amonestación
    elseif ($accion == 'agregar_pago_amonestacion') {
        $amonestacion_id = $_POST['amonestacion_id'] ?? 0;
        $fecha = $_POST['fecha'] ?? '';
        $monto = $_POST['monto'] ?? 0;
        $descripcion = $_POST['descripcion'] ?? '';
    
        if ($amonestacion_id <= 0 || empty($fecha) || $monto <= 0) {
            die("<script>alert('Datos incompletos o inválidos'); window.location.href='deudas.php';</script>");
        }
    
        $pdo->beginTransaction();
        try {
            // 1. Insertar pago en tabla pagos_amonestaciones
            $stmt = $pdo->prepare("
                INSERT INTO pagos_amonestaciones (amonestacion_id, fecha, monto, descripcion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$amonestacion_id, $fecha, $monto, $descripcion]);
            $pago_id = $pdo->lastInsertId();
    
            // 2. Registrar movimiento en caja
            $stmt = $pdo->prepare("
                INSERT INTO movimientos_caja (
                    caja_id, 
                    tipo, 
                    monto, 
                    descripcion, 
                    fecha,
                    pago_amonestacion_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $caja_id,
                'ingreso',
                $monto,
                "Pago amonestación ID: $amonestacion_id - " . substr($descripcion, 0, 245),
                $fecha,
                $pago_id
            ]);
    
            // 3. Actualizar saldo de la caja predeterminada
            $stmt = $pdo->prepare("
                UPDATE cajas 
                SET saldo_actual = saldo_actual + ?
                WHERE id = ?
            ");
            $stmt->execute([$monto, $caja_id]);
    
            // 4. Verificar si la amonestación está completamente pagada
            $stmt = $pdo->prepare("SELECT monto FROM amonestaciones WHERE id = ?");
            $stmt->execute([$amonestacion_id]);
            $monto_amonestacion = $stmt->fetchColumn();
    
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos_amonestaciones WHERE amonestacion_id = ?");
            $stmt->execute([$amonestacion_id]);
            $total_pagado = $stmt->fetchColumn();
    
            if ($total_pagado >= $monto_amonestacion) {
                $stmt = $pdo->prepare("UPDATE amonestaciones SET estado = 'pagada' WHERE id = ?");
                $stmt->execute([$amonestacion_id]);
            }
    
            $pdo->commit();
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("<script>alert('Error al registrar el pago: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }

    // Pagar ingreso pendiente
    elseif ($accion == 'pagar_ingreso_pendiente') {
        $conductor_id = $_POST['conductor_id'] ?? 0;
        $fecha = $_POST['fecha'] ?? '';
        $monto = $_POST['monto'] ?? 0;
        $descripcion = $_POST['descripcion'] ?? '';
    
        if ($conductor_id <= 0 || empty($fecha) || $monto <= 0) {
            die("<script>alert('Datos incompletos o inválidos'); window.location.href='deudas.php';</script>");
        }
    
        $pdo->beginTransaction();
        try {
            // 1. Registrar pago en tabla pagos_ingresos_pendientes
            $stmt = $pdo->prepare("
                INSERT INTO pagos_ingresos_pendientes (conductor_id, fecha, monto, descripcion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$conductor_id, $fecha, $monto, $descripcion]);
            $pago_id = $pdo->lastInsertId();
    
            // 2. Registrar movimiento en caja
            $stmt = $pdo->prepare("
                INSERT INTO movimientos_caja (
                    caja_id, 
                    tipo, 
                    monto, 
                    descripcion, 
                    fecha,
                    pago_ingreso_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $caja_id,
                'ingreso',
                $monto,
                "Pago ingreso pendiente conductor ID: $conductor_id - " . substr($descripcion, 0, 245),
                $fecha,
                $pago_id
            ]);
    
            // 3. Actualizar saldo de la caja predeterminada
            $stmt = $pdo->prepare("
                UPDATE cajas 
                SET saldo_actual = saldo_actual + ?
                WHERE id = ?
            ");
            $stmt->execute([$monto, $caja_id]);
    
            // 4. Actualizar ingresos pendientes del conductor
            $stmt = $pdo->prepare("
                UPDATE ingresos 
                SET monto_pendiente = monto_pendiente - ?
                WHERE conductor_id = ? AND monto_pendiente > 0
                ORDER BY fecha DESC
                LIMIT 1
            ");
            $stmt->execute([$monto, $conductor_id]);
    
            $pdo->commit();
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("<script>alert('Error al registrar el pago: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }
    
    // Pagar salario
    elseif ($accion == 'pagar_salario') {
        $conductor_id = $_POST['conductor_id'] ?? 0;
        $fecha = $_POST['fecha'] ?? '';
        $monto = $_POST['monto'] ?? 0;
        $descripcion = $_POST['descripcion'] ?? '';
        $total_pagado = $_POST['total_pagado'] ?? 0;
        
        if ($conductor_id <= 0 || empty($fecha) || $monto <= 0) {
            die("<script>alert('Datos incompletos o inválidos'); window.location.href='deudas.php';</script>");
        }
        
        $pdo->beginTransaction();
        try {
            // 1. Registrar pago de salario
            $stmt = $pdo->prepare("
                INSERT INTO pagos_salarios (conductor_id, fecha, monto, descripcion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$conductor_id, $fecha, $total_pagado, $descripcion]);
            $pago_id = $pdo->lastInsertId();
            
            // 2. Registrar movimiento en caja
            $stmt = $pdo->prepare("
                INSERT INTO movimientos_caja (
                    caja_id, 
                    tipo, 
                    monto, 
                    descripcion, 
                    fecha,
                    pago_salario_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $caja_id,
                'egreso',
                $total_pagado,
                "Pago salario conductor ID: $conductor_id",
                $fecha,
                $pago_id
            ]);
            
            // 3. Actualizar saldo de la caja
            $stmt = $pdo->prepare("
                UPDATE cajas 
                SET saldo_actual = saldo_actual - ?
                WHERE id = ?
            ");
            $stmt->execute([$total_pagado, $caja_id]);
            
            // 4. Procesar descuentos por deudas
            if (isset($_POST['pago_deuda'])) {
                foreach ($_POST['pago_deuda'] as $deuda_id => $monto_pago) {
                    $monto_pago = floatval($monto_pago);
                    if ($monto_pago > 0) {
                        // Determinar el tipo de deuda
                        $tipo = $_POST['tipo_deuda'][$deuda_id] ?? '';
                        
                        if ($tipo === 'amonestacion') {
                            // Registrar pago de amonestación
                            $stmt = $pdo->prepare("
                                INSERT INTO pagos_amonestaciones (amonestacion_id, fecha, monto, descripcion)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $deuda_id,
                                $fecha,
                                $monto_pago,
                                "Descuento de salario"
                            ]);
                            
                            // Verificar si está completamente pagada
                            $stmt = $pdo->prepare("
                                SELECT a.monto, COALESCE(SUM(pa.monto), 0) as total_pagado
                                FROM amonestaciones a
                                LEFT JOIN pagos_amonestaciones pa ON a.id = pa.amonestacion_id
                                WHERE a.id = ?
                                GROUP BY a.id
                            ");
                            $stmt->execute([$deuda_id]);
                            $amonestacion = $stmt->fetch();
                            
                            if ($amonestacion && $amonestacion['total_pagado'] >= $amonestacion['monto']) {
                                $stmt = $pdo->prepare("UPDATE amonestaciones SET estado = 'pagada' WHERE id = ?");
                                $stmt->execute([$deuda_id]);
                            }
                            
                        } elseif ($tipo === 'prestamo') {
                            // Registrar pago de préstamo
                            $stmt = $pdo->prepare("
                                INSERT INTO pagos_prestamos (prestamo_id, fecha, monto, descripcion)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $deuda_id,
                                $fecha,
                                $monto_pago,
                                "Descuento de salario"
                            ]);
                            
                            // Actualizar saldo pendiente del préstamo
                            $stmt = $pdo->prepare("
                                UPDATE prestamos 
                                SET saldo_pendiente = saldo_pendiente - ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$monto_pago, $deuda_id]);
                            
                            // Verificar si está completamente pagado
                            $stmt = $pdo->prepare("SELECT saldo_pendiente FROM prestamos WHERE id = ?");
                            $stmt->execute([$deuda_id]);
                            $saldo = $stmt->fetchColumn();
                            
                            if ($saldo <= 0) {
                                $stmt = $pdo->prepare("UPDATE prestamos SET estado = 'pagado' WHERE id = ?");
                                $stmt->execute([$deuda_id]);
                            }
                        }
                    }
                }
            }
            
            $pdo->commit();
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("<script>alert('Error al registrar el pago de salario: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }
    
    // Cambiar estado de amonestación
    elseif ($accion == 'cambiar_estado_amonestacion') {
        $id = $_POST['id'] ?? 0;
        $estado = $_POST['estado'] ?? '';
        
        if ($id <= 0 || !in_array($estado, ['activa', 'pagada', 'anulado'])) {
            die("<script>alert('Datos incompletos o inválidos'); window.location.href='deudas.php';</script>");
        }

        try {
            $stmt = $pdo->prepare("UPDATE amonestaciones SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $id]);
            
            header("Location: deudas.php");
            exit;
        } catch (Exception $e) {
            die("<script>alert('Error al cambiar estado: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>");
        }
    }

    // Eliminar préstamo
    elseif ($accion == 'eliminar_prestamo') {
        $id = $_POST['id'] ?? 0;
        
        if ($id <= 0) {
            die("<script>alert('ID inválido'); window.location.href='deudas.php';</script>");
        }
        
        $pdo->beginTransaction();
        try {
            // Eliminar pagos asociados
            $stmt = $pdo->prepare("DELETE FROM pagos_prestamos WHERE prestamo_id = ?");
            $stmt->execute([$id]);
            
            // Eliminar préstamo
            $stmt = $pdo->prepare("DELETE FROM prestamos WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            echo "<script>alert('Préstamo eliminado correctamente.'); window.location.href='deudas.php';</script>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Error al eliminar el préstamo: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>";
        }
    }

    // Eliminar amonestación
    elseif ($accion == 'eliminar_amonestacion') {
        $id = $_POST['id'] ?? 0;
        
        if ($id <= 0) {
            die("<script>alert('ID inválido'); window.location.href='deudas.php';</script>");
        }
        
        $pdo->beginTransaction();
        try {
            // Eliminar pagos asociados
            $stmt = $pdo->prepare("DELETE FROM pagos_amonestaciones WHERE amonestacion_id = ?");
            $stmt->execute([$id]);
            
            // Eliminar amonestación
            $stmt = $pdo->prepare("DELETE FROM amonestaciones WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            echo "<script>alert('Amonestación eliminada correctamente.'); window.location.href='deudas.php';</script>";
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Error al eliminar la amonestación: " . addslashes($e->getMessage()) . "'); window.location.href='deudas.php';</script>";
        }
    }
}

// Obtener conductores para selects
$conductores = $pdo->query("SELECT id, nombre FROM conductores ORDER BY nombre")->fetchAll();

// Obtener conductores completos con información adicional
$conductoresCompletos = [];
$stmt = $pdo->query("
    SELECT c.id, c.nombre, c.salario_mensual, c.dias_por_ciclo,
           (SELECT COUNT(*) FROM ingresos WHERE conductor_id = c.id AND tipo_ingreso = 'obligatorio' AND ciclo_completado = 1) AS ciclos_completados,
           (SELECT COUNT(*) FROM ingresos WHERE conductor_id = c.id AND tipo_ingreso = 'obligatorio' AND ciclo = (SELECT MAX(ciclo) FROM ingresos WHERE conductor_id = c.id)) AS dias_trabajados_ultimo_ciclo,
           (SELECT COALESCE(SUM(monto_pendiente), 0) FROM ingresos WHERE conductor_id = c.id AND monto_pendiente > 0) AS total_pendiente,
           (SELECT COALESCE(SUM(saldo_pendiente), 0) FROM prestamos WHERE conductor_id = c.id AND estado = 'pendiente') AS total_prestamos,
           (SELECT COALESCE(SUM(monto), 0) FROM amonestaciones WHERE conductor_id = c.id AND estado = 'activa') AS total_amonestaciones
    FROM conductores c
    ORDER BY c.nombre
");
$conductoresCompletos = $stmt->fetchAll();

// Obtener resumen de deudas por conductor
$resumenDeudas = $pdo->query("
    SELECT 
        c.id AS conductor_id,
        c.nombre AS conductor_nombre,
        COALESCE(SUM(i.monto_pendiente), 0) AS total_pendiente,
        COALESCE(SUM(p.saldo_pendiente), 0) AS total_prestamos,
        COALESCE(SUM(CASE WHEN a.estado = 'activa' THEN a.monto ELSE 0 END), 0) AS total_amonestaciones
    FROM conductores c
    LEFT JOIN ingresos i ON c.id = i.conductor_id AND i.monto_pendiente > 0
    LEFT JOIN prestamos p ON c.id = p.conductor_id AND p.estado = 'pendiente'
    LEFT JOIN amonestaciones a ON c.id = a.conductor_id AND a.estado = 'activa'
    GROUP BY c.id, c.nombre
    HAVING total_pendiente > 0 OR total_prestamos > 0 OR total_amonestaciones > 0
")->fetchAll();

// Obtener ingresos pendientes
$ingresosPendientes = $pdo->query("
    SELECT i.*, c.nombre AS conductor_nombre
    FROM ingresos i
    JOIN conductores c ON i.conductor_id = c.id
    WHERE i.monto_pendiente > 0
    ORDER BY i.fecha DESC
")->fetchAll();

// Obtener préstamos
$prestamos = $pdo->query("
    SELECT p.*, c.nombre AS conductor_nombre
    FROM prestamos p
    JOIN conductores c ON p.conductor_id = c.id
    ORDER BY p.fecha DESC
")->fetchAll();

// Obtener amonestaciones
$amonestaciones = $pdo->query("
    SELECT a.*, c.nombre AS conductor_nombre
    FROM amonestaciones a
    JOIN conductores c ON a.conductor_id = c.id
    ORDER BY a.fecha DESC
")->fetchAll();

// Obtener historial de pagos
$historialPagos = $pdo->query("
    (SELECT 'prestamo' AS tipo, pp.id, pp.fecha, pp.monto, pp.descripcion, c.nombre AS conductor_nombre, p.id AS referencia_id, p.conductor_id
     FROM pagos_prestamos pp
     JOIN prestamos p ON pp.prestamo_id = p.id
     JOIN conductores c ON p.conductor_id = c.id)
    
    UNION ALL
    
    (SELECT 'amonestacion' AS tipo, pa.id, pa.fecha, pa.monto, pa.descripcion, c.nombre AS conductor_nombre, a.id AS referencia_id, a.conductor_id
     FROM pagos_amonestaciones pa
     JOIN amonestaciones a ON pa.amonestacion_id = a.id
     JOIN conductores c ON a.conductor_id = c.id)
    
    UNION ALL
    
    (SELECT 'ingreso' AS tipo, pip.id, pip.fecha, pip.monto, pip.descripcion, c.nombre AS conductor_nombre, NULL AS referencia_id, pip.conductor_id
     FROM pagos_ingresos_pendientes pip
     JOIN conductores c ON pip.conductor_id = c.id)
    
    UNION ALL
    
    (SELECT 'salario' AS tipo, ps.id, ps.fecha, ps.monto, ps.descripcion, c.nombre AS conductor_nombre, NULL AS referencia_id, ps.conductor_id
     FROM pagos_salarios ps
     JOIN conductores c ON ps.conductor_id = c.id)
    
    ORDER BY fecha DESC
")->fetchAll();

// Obtener pagos de salarios para la pestaña de salarios
$pagosSalarios = $pdo->query("
    SELECT ps.*, c.nombre AS conductor_nombre
    FROM pagos_salarios ps
    JOIN conductores c ON ps.conductor_id = c.id
    ORDER BY ps.fecha DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Deudas</title>
    <style>
    /* ============ ESTILOS PRINCIPALES ============ */
    :root {
        --color-primario: #004b87;
        --color-secundario: #003366;
        --color-texto: #333;
        --color-fondo: #f8f9fa;
        --color-borde: #e2e8f0;
        --color-exito: #28a745;
        --color-advertencia: #ffc107;
        --color-peligro: #dc3545;
        --color-info: #17a2b8;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f5f5f5;
        color: var(--color-texto);
        line-height: 1.6;
    }
    
    .container {
        max-width: 1250px;
        margin: 20px auto;
        background: #fff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    
    h2 {
        margin-top:0px;
        color: var(--color-primario);
        font-size: 1.8rem;
        margin-bottom: 5px;
        text-align: center;
        font-weight: 600;
    }
    
    /* ============ PESTAÑAS ============ */
    .tabs {
        display: flex;
        border-bottom: 1px solid var(--color-borde);
        margin-bottom: 20px;
    }
    
    .tab {
        padding: 10px 20px;
        cursor: pointer;
        border: 1px solid transparent;
        border-bottom: none;
        border-radius: 4px 4px 0 0;
        margin-right: 5px;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .tab:hover {
        background-color: rgba(0, 75, 135, 0.05);
    }
    
    .tab.active {
        border-color: var(--color-borde);
        border-bottom-color: #fff;
        background: #fff;
        color: var(--color-primario);
        font-weight: 600;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* ============ TABLA ============ */
    .table-container {
        overflow-x: auto;
        margin-top: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
    }
    
    .table th, .table td {
        padding: 14px 12px;
        text-align: left;
        border-bottom: 1px solid var(--color-borde);
    }
    
    .table th {
        background-color: var(--color-primario);
        color: white;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        position: sticky;
        top: 0;
    }
    
    .table td {
        background-color: #fff;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }
    
    .table tr:hover td {
        background-color: rgba(0, 75, 135, 0.05);
    }
    
    /* Estilo para filas con pendientes */
    .table tr.alerta-pendiente td {
        background-color: #ffebee !important;
        color: #dc3545 !important;
        font-weight: bold;
    }
    
    /* ============ BOTONES ============ */
    .btn {
        padding: 8px 8px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
    }
    
    .btn-nuevo {
        background-color: var(--color-exito);
        color: white;
    }
    
    .btn-nuevo:hover {
        background-color: #218838;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-editar {
        background-color: var(--color-advertencia);
        color: #212529;
    }
    
    .btn-editar:hover {
        background-color: #e0a800;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-ver {
        background-color: var(--color-info);
        color: white;
    }
    
    .btn-ver:hover {
        background-color: #138496;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-eliminar {
        background-color: var(--color-peligro);
        color: white;
    }
    
    .btn-eliminar:hover {
        background-color: #c82333;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-filtro {
        background-color: white;
        border: 1px solid var(--color-borde);
        color: var(--color-texto);
    }
    
    .btn-filtro:hover {
        background-color: #f1f5f9;
    }
    
    /* ============ CONTROLES DE TABLA ============ */
    .table-controls {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .table-controls input, 
    .table-controls select {
        flex: 1;
        min-width: 200px;
        max-width: 300px;
        padding: 10px 12px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.95rem;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 25px;
        gap: 8px;
        align-items: center;
    }
    
    /* ============ MODALES ============ */
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        inset: 0;
        font-family: 'Inter', sans-serif;
    }
    
    .modal__overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.4);
        backdrop-filter: blur(4px);
    }
    
    .modal__container {
        position: relative;
        background: white;
        width: 800px;
        max-width: 95%;
        max-height: 95vh;
        margin: auto;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        animation: modalFadeIn 0.3s ease-out;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .modal__close {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: white;
        border: 1px solid var(--color-borde);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        z-index: 10;
    }
    
    .modal__close:hover {
        background: #f1f5f9;
        transform: rotate(90deg);
    }
    
    .modal__close svg {
        width: 18px;
        height: 18px;
        color: #64748b;
    }
    
    .modal__header {
        padding: 24px 24px 16px;
        border-bottom: 1px solid var(--color-borde);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .modal__title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }
    
    .modal__title--highlight {
        color: var(--color-primario);
        font-weight: 600;
    }
    
    .modal__body {
        padding: 0 24px;
        overflow-y: auto;
        flex-grow: 1;
    }
    
    .modal__form {
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding: 16px 0;
    }
    
    .modal__form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .modal__form-row {
        display: flex;
        gap: 16px;
    }
    
    .modal__form-row .modal__form-group {
        flex: 1;
    }
    
    .modal__form-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .modal__form-input {
        padding: 10px 12px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.95rem;
        transition: border-color 0.2s ease;
        width: 100%;
    }
    
    .modal__form-input:focus {
        outline: none;
        border-color: var(--color-primario);
        box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.1);
    }
    
    .modal__footer {
        padding: 16px 24px;
        border-top: 1px solid var(--color-borde);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .modal__action-btn {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        background: white;
        border: 1px solid var(--color-borde);
        color: #1e293b;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .modal__action-btn:hover {
        background: #f1f5f9;
        transform: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .modal__action-btn svg {
        width: 14px;
        height: 14px;
    }
    
    .modal__action-btn--primary {
        background: var(--color-primario);
        border-color: var(--color-primario);
        color: white;
    }
    
    .modal__action-btn--primary:hover {
        background: var(--color-secundario);
    }
    
    /* ============ TABLA DE DETALLES ============ */
    .modal__data-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--color-borde);
        border-radius: 8px;
        overflow: hidden;
    }
    
    .modal__data-table tr:not(:last-child) {
        border-bottom: 1px solid var(--color-borde);
    }
    
    .modal__data-label {
        padding: 12px 16px;
        font-size: 0.85rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        background-color: #f8fafc;
        text-align: left;
        border-right: 1px solid var(--color-borde);
        width: 40%;
    }
    
    .modal__data-value {
        padding: 12px 16px;
        font-size: 1rem;
        color: #1e293b;
        font-weight: 500;
        background-color: white;
    }
    
    /* ============ BOTONES FLOTANTES ============ */
    .action-buttons {
        position: fixed;
        bottom: 20px;
        right: 20px;
        display: flex;
        gap: 10px;
        z-index: 100;
    }
    
    .action-button {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--color-primario);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        cursor: pointer;
        transition: all 0.3s;
        border: none;
    }
    
    .action-button:hover {
        transform: scale(1.1);
        background: var(--color-secundario);
    }
    
    /* Estilos para los modales y botones */
    .modal__action-btn--danger {
        background-color: var(--color-peligro);
        border-color: var(--color-peligro);
        color: white;
    }
    
    .modal__action-btn--danger:hover {
        background-color: #c82333;
    }
    
    .historial-container {
        margin-top: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid var(--color-borde);
    }
    
    .historial-title {
        color: var(--color-primario);
        margin-bottom: 15px;
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    .historial-content {
        max-height: 300px;
        overflow-y: auto;
    }
    
    /* Estilos para la tabla de deudas en el modal de pago de salario */
    .deuda-item {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
        align-items: center;
    }
    
    .deuda-item label {
        flex: 1;
        font-weight: 500;
    }
    
    .deuda-item input {
        width: 100px;
        padding: 8px;
        border: 1px solid var(--color-borde);
        border-radius: 4px;
    }
    
    /* ============ RESPONSIVE ============ */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }
        
        .modal__container {
            max-width: 98%;
            max-height: 98vh;
        }
        
        .modal__form-row {
            flex-direction: column;
            gap: 16px;
        }
        
        .table-controls input, 
        .table-controls select {
            max-width: 100%;
        }
        
        .modal__footer {
            flex-direction: column;
        }
        
        .modal__action-btn {
            width: 100%;
            justify-content: center;
        }
        
        .tabs {
            flex-wrap: wrap;
        }
        
        .tab {
            flex: 1;
            min-width: 120px;
            text-align: center;
        }
    }
    
    /* ============ RESÚMENES ============ */
    .resumen-container {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid var(--color-borde);
    }
    
    .resumen-title {
        color: var(--color-primario);
        margin-bottom: 15px;
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    .resumen-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
    }
    
    .resumen-item {
        background: white;
        border-radius: 6px;
        padding: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        border: 1px solid var(--color-borde);
    }
    
    .resumen-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .resumen-item-title {
        font-weight: 600;
        color: var(--color-primario);
    }
    
    .resumen-item-value {
        font-size: 1.2rem;
        font-weight: 600;
    }
    
    .resumen-item-value.pendiente {
        color: var(--color-peligro);
    }
    
    .resumen-item-value.pagado {
        color: var(--color-exito);
    }
    
    .resumen-item-details {
        font-size: 0.85rem;
        color: #64748b;
    }
    
    /* ============ BADGES DE ESTADO ============ */
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .badge-pendiente {
        background-color: #ffebee;
        color: #dc3545;
    }
    
    .badge-pagado {
        background-color: #e8f5e9;
        color: #28a745;
    }
    
    .badge-anulado {
        background-color: #e3f2fd;
        color: #2196f3;
    }
    
    .badge-activo {
        background-color: #fff8e1;
        color: #ff9800;
    }
    </style>
</head>
<body>
    <div class="container">
        <h2>Gestión de Deudas</h2>
        
        <!-- Resumen de deudas por conductor -->
        <div class="resumen-container">
            <h3 class="resumen-title">Resumen de Deudas por Conductor</h3>
            <div class="resumen-grid">
                <?php foreach ($resumenDeudas as $resumen): ?>
                    <div class="resumen-item">
                        <div class="resumen-item-header">
                            <span class="resumen-item-title"><?= htmlspecialchars($resumen['conductor_nombre']) ?></span>
                            <button class="btn btn-ver" onclick="verDetalleConductor(<?= $resumen['conductor_id'] ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                        <div class="resumen-item-details">
                            <div>Ingresos pendientes: <span class="resumen-item-value <?= $resumen['total_pendiente'] > 0 ? 'pendiente' : '' ?>">
                                <?= number_format($resumen['total_pendiente'], 0, ',', '.') ?> XAF</span></div>
                            <div>Préstamos pendientes: <span class="resumen-item-value <?= $resumen['total_prestamos'] > 0 ? 'pendiente' : '' ?>">
                                <?= number_format($resumen['total_prestamos'], 0, ',', '.') ?> XAF</span></div>
                            <div>Amonestaciones: <span class="resumen-item-value <?= $resumen['total_amonestaciones'] > 0 ? 'pendiente' : '' ?>">
                                <?= number_format($resumen['total_amonestaciones'], 0, ',', '.') ?> XAF</span></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Pestañas -->
        <div class="tabs">
            <div class="tab active" data-tab="ingresos-pendientes">Ingresos Pendientes</div>
            <div class="tab" data-tab="prestamos">Préstamos</div>
            <div class="tab" data-tab="amonestaciones">Amonestaciones</div>
            <div class="tab" data-tab="historial">Historial de Pagos</div>
            <div class="tab" data-tab="conductores">Lista de Conductores</div>
            <div class="tab" data-tab="salarios">Salarios</div>
        </div>
        
        <!-- Controles de tabla -->
        <div class="table-controls">
            <input type="text" id="searchInput" placeholder="Buscar..." class="modal__form-input">
            <select id="filterConductor" class="modal__form-input">
                <option value="">Todos los conductores</option>
                <?php foreach ($conductores as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterEstado" class="modal__form-input" style="display: none;">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="pagado">Pagado</option>
                <option value="anulado">Anulado</option>
                <option value="activo">Activo</option>
            </select>
            <button id="openModalNuevo" class="btn btn-nuevo" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nuevo
            </button>
        </div>
        
        <!-- Contenido de pestañas -->
        
        <!-- Pestaña Ingresos Pendientes -->
        <div class="tab-content active" id="ingresos-pendientes-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Monto Esperado</th>
                            <th>Monto Ingresado</th>
                            <th>Pendiente</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyIngresos">
                        <?php foreach ($ingresosPendientes as $ingreso): ?>
                            <tr data-conductor="<?= $ingreso['conductor_id'] ?>">
                                <td><?= $ingreso['id'] ?></td>
                                <td><?= $ingreso['fecha'] ?></td>
                                <td><?= htmlspecialchars($ingreso['conductor_nombre']) ?></td>
                                <td><?= number_format($ingreso['monto_esperado'], 0, ',', '.') ?> XAF</td>
                                <td><?= number_format($ingreso['monto_ingresado'], 0, ',', '.') ?> XAF</td>
                                <td><?= number_format($ingreso['monto_pendiente'], 0, ',', '.') ?> XAF</td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verIngreso(<?= $ingreso['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <button class="btn btn-nuevo" onclick="abrirModalPagoIngreso(<?= $ingreso['conductor_id'] ?>, <?= $ingreso['monto_pendiente'] ?>, '<?= htmlspecialchars($ingreso['conductor_nombre']) ?>')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 2v20M2 12h20"></path>
                                            </svg>
                                            Pagar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pestaña Préstamos -->
        <div class="tab-content" id="prestamos-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Monto</th>
                            <th>Saldo Pendiente</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyPrestamos">
                        <?php foreach ($prestamos as $prestamo): ?>
                            <tr data-conductor="<?= $prestamo['conductor_id'] ?>" data-estado="<?= $prestamo['estado'] ?>">
                                <td><?= $prestamo['id'] ?></td>
                                <td><?= $prestamo['fecha'] ?></td>
                                <td><?= htmlspecialchars($prestamo['conductor_nombre']) ?></td>
                                <td><?= number_format($prestamo['monto'], 0, ',', '.') ?> XAF</td>
                                <td><?= number_format($prestamo['saldo_pendiente'], 0, ',', '.') ?> XAF</td>
                                <td>
                                    <span class="badge badge-<?= $prestamo['estado'] ?>">
                                        <?= ucfirst($prestamo['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verPrestamo(<?= $prestamo['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <button class="btn btn-nuevo" onclick="abrirModalPagoPrestamo(<?= $prestamo['id'] ?>, <?= $prestamo['saldo_pendiente'] ?>, '<?= htmlspecialchars($prestamo['conductor_nombre']) ?>')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 2v20M2 12h20"></path>
                                            </svg>
                                            Pagar
                                        </button>
                                        <button class="btn btn-editar" onclick="editarPrestamo(<?= $prestamo['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar este préstamo?')">
                                            <input type="hidden" name="id" value="<?= $prestamo['id'] ?>">
                                            <input type="hidden" name="accion" value="eliminar_prestamo">
                                            <button type="submit" class="btn btn-eliminar">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pestaña Amonestaciones -->
        <div class="tab-content" id="amonestaciones-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyAmonestaciones">
                        <?php foreach ($amonestaciones as $amonestacion): ?>
                            <tr data-conductor="<?= $amonestacion['conductor_id'] ?>" data-estado="<?= $amonestacion['estado'] ?>">
                                <td><?= $amonestacion['id'] ?></td>
                                <td><?= $amonestacion['fecha'] ?></td>
                                <td><?= htmlspecialchars($amonestacion['conductor_nombre']) ?></td>
                                <td><?= number_format($amonestacion['monto'], 0, ',', '.') ?> XAF</td>
                                <td>
                                    <span class="badge badge-<?= $amonestacion['estado'] ?>">
                                        <?= ucfirst($amonestacion['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verAmonestacion(<?= $amonestacion['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        <!----------------->
                                        </button>
                                        <button class="btn btn-nuevo" onclick="abrirModalPagoAmonestacion(<?= $amonestacion['id'] ?>, <?= $amonestacion['monto'] ?>, '<?= htmlspecialchars($amonestacion['conductor_nombre']) ?>')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 2v20M2 12h20"></path>
                                            </svg>
                                            Pagar
                                        </button>
                                        <button class="btn btn-eliminar" onclick="cambiarEstadoAmonestacion(<?= $amonestacion['id'] ?>, 'anulado')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                            </svg>
                                            Anular
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pestaña Historial de Pagos -->
        <div class="tab-content" id="historial-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Conductor</th>
                            <th>Monto</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyHistorial">
                        <?php foreach ($historialPagos as $pago): ?>
                            <tr data-conductor="<?= $pago['conductor_id'] ?>">
                                <td><?= $pago['id'] ?></td>
                                <td><?= $pago['fecha'] ?></td>
                                <td><?= ucfirst($pago['tipo']) ?></td>
                                <td><?= htmlspecialchars($pago['conductor_nombre']) ?></td>
                                <td><?= number_format($pago['monto'], 0, ',', '.') ?> XAF</td>
                                <td><?= htmlspecialchars($pago['descripcion']) ?></td>
                                <td>
                                    <button class="btn btn-ver" onclick="verDetallePago('<?= $pago['tipo'] ?>', <?= $pago['id'] ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pestaña Lista de Conductores -->
        <div class="tab-content" id="conductores-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Salario Mensual</th>
                            <th>Días por Ciclo</th>
                            <th>Ciclos Completados</th>
                            <th>Días Trabajados</th>
                            <th>Total Pendiente</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyConductores">
                        <?php foreach ($conductoresCompletos as $conductor): ?>
                            <tr>
                                <td><?= $conductor['id'] ?></td>
                                <td><?= htmlspecialchars($conductor['nombre']) ?></td>
                                <td><?= number_format($conductor['salario_mensual'], 0, ',', '.') ?> XAF</td>
                                <td><?= $conductor['dias_por_ciclo'] ?></td>
                                <td><?= $conductor['ciclos_completados'] ?></td>
                                <td><?= $conductor['dias_trabajados_ultimo_ciclo'] ?> / <?= $conductor['dias_por_ciclo'] ?></td>
                                <td><?= number_format($conductor['total_pendiente'], 0, ',', '.') ?> XAF</td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verDetalleConductor(<?= $conductor['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <button class="btn btn-nuevo" onclick="abrirModalPagoSalario(<?= $conductor['id'] ?>, '<?= htmlspecialchars($conductor['nombre']) ?>', <?= $conductor['salario_mensual'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 2v20M2 12h20"></path>
                                            </svg>
                                            Pagar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pestaña Salarios -->
        <div class="tab-content" id="salarios-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Monto</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodySalarios">
                        <?php foreach ($pagosSalarios as $pago): ?>
                            <tr>
                                <td><?= $pago['id'] ?></td>
                                <td><?= $pago['fecha'] ?></td>
                                <td><?= htmlspecialchars($pago['conductor_nombre']) ?></td>
                                <td><?= number_format($pago['monto'], 0, ',', '.') ?> XAF</td>
                                <td><?= htmlspecialchars($pago['descripcion']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verDetallePago('salario', <?= $pago['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <button class="btn btn-ver" onclick="imprimirNomina(<?= $pago['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                                <rect x="6" y="14" width="12" height="8"></rect>
                                            </svg>
                                            Imprimir
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Botones flotantes -->
        <div class="action-buttons">
            <button class="action-button" onclick="abrirModalNuevoPrestamo()">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
            </button>
            <button class="action-button" onclick="abrirModalNuevaAmonestacion()">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
            </button>
        </div>
        
        <!-- Modal para ver detalles de ingreso pendiente -->
        <div class="modal" id="modalVerIngreso">
            <div class="modal__overlay" onclick="cerrarModal('modalVerIngreso')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalVerIngreso')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Detalles de Ingreso Pendiente</h3>
                </div>
                <div class="modal__body">
                    <table class="modal__data-table">
                        <tr>
                            <td class="modal__data-label">ID</td>
                            <td class="modal__data-value" id="ingresoId"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Fecha</td>
                            <td class="modal__data-value" id="ingresoFecha"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Conductor</td>
                            <td class="modal__data-value" id="ingresoConductor"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Monto Esperado</td>
                            <td class="modal__data-value" id="ingresoMontoEsperado"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Monto Ingresado</td>
                            <td class="modal__data-value" id="ingresoMontoIngresado"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Monto Pendiente</td>
                            <td class="modal__data-value" id="ingresoMontoPendiente"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Descripción</td>
                            <td class="modal__data-value" id="ingresoDescripcion"></td>
                        </tr>
                    </table>
                </div>
                <div class="modal__footer">
                    <button class="modal__action-btn" onclick="cerrarModal('modalVerIngreso')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        Cerrar
                    </button>
                    <button class="modal__action-btn modal__action-btn--primary" id="btnPagarIngresoModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v20M2 12h20"></path>
                        </svg>
                        Pagar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Modal para ver detalles de préstamo -->
        <div class="modal" id="modalVerPrestamo">
            <div class="modal__overlay" onclick="cerrarModal('modalVerPrestamo')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalVerPrestamo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Detalles de Préstamo</h3>
                </div>
                <div class="modal__body">
                    <table class="modal__data-table">
                        <tr>
                            <td class="modal__data-label">ID</td>
                            <td class="modal__data-value" id="prestamoId"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Fecha</td>
                            <td class="modal__data-value" id="prestamoFecha"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Conductor</td>
                            <td class="modal__data-value" id="prestamoConductor"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Monto</td>
                            <td class="modal__data-value" id="prestamoMonto"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Saldo Pendiente</td>
                            <td class="modal__data-value" id="prestamoSaldoPendiente"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Estado</td>
                            <td class="modal__data-value" id="prestamoEstado"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Descripción</td>
                            <td class="modal__data-value" id="prestamoDescripcion"></td>
                        </tr>
                    </table>
                    
                    <div class="historial-container">
                        <h4 class="historial-title">Historial de Pagos</h4>
                        <div class="historial-content" id="historialPagosPrestamo">
                            <!-- Aquí se cargará el historial de pagos mediante AJAX -->
                        </div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button class="modal__action-btn" onclick="cerrarModal('modalVerPrestamo')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        Cerrar
                    </button>
                    <button class="modal__action-btn modal__action-btn--primary" id="btnPagarPrestamoModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v20M2 12h20"></path>
                        </svg>
                        Pagar
                    </button>
                    <button class="modal__action-btn" id="btnEditarPrestamoModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Editar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Modal para ver detalles de amonestación -->
        <div class="modal" id="modalVerAmonestacion">
            <div class="modal__overlay" onclick="cerrarModal('modalVerAmonestacion')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalVerAmonestacion')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Detalles de Amonestación</h3>
                </div>
                <div class="modal__body">
                    <table class="modal__data-table">
                        <tr>
                            <td class="modal__data-label">ID</td>
                            <td class="modal__data-value" id="amonestacionId"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Fecha</td>
                            <td class="modal__data-value" id="amonestacionFecha"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Conductor</td>
                            <td class="modal__data-value" id="amonestacionConductor"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Monto</td>
                            <td class="modal__data-value" id="amonestacionMonto"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Estado</td>
                            <td class="modal__data-value" id="amonestacionEstado"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Descripción</td>
                            <td class="modal__data-value" id="amonestacionDescripcion"></td>
                        </tr>
                    </table>
                    
                    <div class="historial-container">
                        <h4 class="historial-title">Historial de Pagos</h4>
                        <div class="historial-content" id="historialPagosAmonestacion">
                            <!-- Aquí se cargará el historial de pagos mediante AJAX -->
                        </div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button class="modal__action-btn" onclick="cerrarModal('modalVerAmonestacion')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        Cerrar
                    </button>
                    <button class="modal__action-btn modal__action-btn--primary" id="btnPagarAmonestacionModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v20M2 12h20"></path>
                        </svg>
                        Pagar
                    </button>
                    <button class="modal__action-btn modal__action-btn--danger" id="btnAnularAmonestacionModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                        </svg>
                        Anular
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Modal para ver detalles de pago -->
        <div class="modal" id="modalVerPago">
            <div class="modal__overlay" onclick="cerrarModal('modalVerPago')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalVerPago')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Detalles de Pago</h3>
                </div>
                <div class="modal__body" id="modalVerPagoBody">
                    <!-- Aquí se cargarán los detalles del pago mediante AJAX -->
                </div>
                <div class="modal__footer">
                    <button class="modal__action-btn" onclick="cerrarModal('modalVerPago')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Modal para ver detalles de conductor -->
        <div class="modal" id="modalVerConductor">
            <div class="modal__overlay" onclick="cerrarModal('modalVerConductor')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalVerConductor')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Detalles del Conductor</h3>
                </div>
                <div class="modal__body">
                    <table class="modal__data-table">
                        <tr>
                            <td class="modal__data-label">ID</td>
                            <td class="modal__data-value" id="conductorId"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Nombre</td>
                            <td class="modal__data-value" id="conductorNombre"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Teléfono</td>
                            <td class="modal__data-value" id="conductorTelefono"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Vehículo</td>
                            <td class="modal__data-value" id="conductorVehiculo"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Salario Mensual</td>
                            <td class="modal__data-value" id="conductorSalario"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Días por Ciclo</td>
                            <td class="modal__data-value" id="conductorDiasCiclo"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Ciclos Completados</td>
                            <td class="modal__data-value" id="conductorCiclosCompletados"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Total Pendiente</td>
                            <td class="modal__data-value" id="conductorTotalPendiente"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Total Préstamos</td>
                            <td class="modal__data-value" id="conductorTotalPrestamos"></td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Total Amonestaciones</td>
                            <td class="modal__data-value" id="conductorTotalAmonestaciones"></td>
                        </tr>
                    </table>
                    
                    <div class="historial-container">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h4 class="historial-title">Historial de Pagos de Salario</h4>
                            <button class="btn btn-ver" onclick="imprimirNominaConductor()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                    <rect x="6" y="14" width="12" height="8"></rect>
                                </svg>
                                Imprimir Nómina
                            </button>
                        </div>
                        <div class="historial-content" id="historialPagosSalario">
                            <!-- Aquí se cargará el historial de pagos mediante AJAX -->
                        </div>
                    </div>
                </div>
                <div class="modal__footer">
                    <button class="modal__action-btn" onclick="cerrarModal('modalVerConductor')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        Cerrar
                    </button>
                    <button class="modal__action-btn modal__action-btn--primary" id="btnPagarSalarioConductor">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v20M2 12h20"></path>
                        </svg>
                        Pagar Salario
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Modal para pagar ingreso pendiente -->
        <div class="modal" id="modalPagoIngreso">
            <div class="modal__overlay" onclick="cerrarModal('modalPagoIngreso')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalPagoIngreso')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Registrar Pago de Ingreso</h3>
                </div>
                <form id="formPagoIngreso" method="post">
                    <div class="modal__body">
                        <div class="modal__form-group">
                            <label class="modal__form-label">Conductor</label>
                            <input type="text" id="pagoIngresoConductor" class="modal__form-input" readonly>
                        </div>
                        <div class="modal__form-row">
                            <div class="modal__form-group">
                                <label class="modal__form-label">Fecha</label>
                                <input type="date" name="fecha" class="modal__form-input" required>
                            </div>
                            <div class="modal__form-group">
                                <label class="modal__form-label">Monto</label>
                                <input type="number" name="monto" id="pagoIngresoMonto" class="modal__form-input" min="1" required>
                            </div>
                        </div>
                        <div class="modal__form-group">
                            <label class="modal__form-label">Descripción</label>
                            <textarea name="descripcion" class="modal__form-input" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="conductor_id" id="pagoIngresoConductorId">
                        <input type="hidden" name="accion" value="pagar_ingreso_pendiente">
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="modal__action-btn" onclick="cerrarModal('modalPagoIngreso')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cancelar
                        </button>
                        <button type="submit" class="modal__action-btn modal__action-btn--primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M2 12h20"></path>
                            </svg>
                            Registrar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal para pagar préstamo -->
        <div class="modal" id="modalPagoPrestamo">
            <div class="modal__overlay" onclick="cerrarModal('modalPagoPrestamo')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalPagoPrestamo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Registrar Pago de Préstamo</h3>
                </div>
                <form id="formPagoPrestamo" method="post">
                    <div class="modal__body">
                        <div class="modal__form-group">
                            <label class="modal__form-label">Conductor</label>
                            <input type="text" id="pagoPrestamoConductor" class="modal__form-input" readonly>
                        </div>
                        <div class="modal__form-row">
                            <div class="modal__form-group">
                                <label class="modal__form-label">Fecha</label>
                                <input type="date" name="fecha" class="modal__form-input" required>
                            </div>
                            <div class="modal__form-group">
                                <label class="modal__form-label">Monto</label>
                                <input type="number" name="monto" id="pagoPrestamoMonto" class="modal__form-input" min="1" required>
                            </div>
                        </div>
                        <div class="modal__form-group">
                            <label class="modal__form-label">Descripción</label>
                            <textarea name="descripcion" class="modal__form-input" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="prestamo_id" id="pagoPrestamoId">
                        <input type="hidden" name="accion" value="agregar_pago_prestamo">
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="modal__action-btn" onclick="cerrarModal('modalPagoPrestamo')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cancelar
                        </button>
                        <button type="submit" class="modal__action-btn modal__action-btn--primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M2 12h20"></path>
                            </svg>
                            Registrar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal para pagar amonestación -->
        <div class="modal" id="modalPagoAmonestacion">
            <div class="modal__overlay" onclick="cerrarModal('modalPagoAmonestacion')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalPagoAmonestacion')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Registrar Pago de Amonestación</h3>
                </div>
                <form id="formPagoAmonestacion" method="post">
                    <div class="modal__body">
                        <div class="modal__form-group">
                            <label class="modal__form-label">Conductor</label>
                            <input type="text" id="pagoAmonestacionConductor" class="modal__form-input" readonly>
                        </div>
                        <div class="modal__form-row">
                            <div class="modal__form-group">
                                <label class="modal__form-label">Fecha</label>
                                <input type="date" name="fecha" class="modal__form-input" required>
                            </div>
                            <div class="modal__form-group">
                                <label class="modal__form-label">Monto</label>
                                <input type="number" name="monto" id="pagoAmonestacionMonto" class="modal__form-input" min="1" required>
                            </div>
                        </div>
                        <div class="modal__form-group">
                            <label class="modal__form-label">Descripción</label>
                            <textarea name="descripcion" class="modal__form-input" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="amonestacion_id" id="pagoAmonestacionId">
                        <input type="hidden" name="accion" value="agregar_pago_amonestacion">
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="modal__action-btn" onclick="cerrarModal('modalPagoAmonestacion')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cancelar
                        </button>
                        <button type="submit" class="modal__action-btn modal__action-btn--primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M2 12h20"></path>
                            </svg>
                            Registrar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal para pagar salario -->
        <div class="modal" id="modalPagoSalario">
            <div class="modal__overlay" onclick="cerrarModal('modalPagoSalario')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalPagoSalario')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Registrar Pago de Salario</h3>
                </div>
                <form id="formPagoSalario" method="post">
                    <div class="modal__body">
                        <div class="modal__form-group">
                            <label class="modal__form-label">Conductor</label>
                            <input type="text" id="pagoSalarioConductor" class="modal__form-input" readonly>
                        </div>
                        <div class="modal__form-row">
                            <div class="modal__form-group">
                                <label class="modal__form-label">Fecha</label>
                                <input type="date" name="fecha" class="modal__form-input" required>
                            </div>
                            <div class="modal__form-group">
                                <label class="modal__form-label">Salario</label>
                                <input type="number" id="pagoSalarioMonto" class="modal__form-input" readonly>
                            </div>
                        </div>
                        <div class="modal__form-group">
                            <label class="modal__form-label">Descripción</label>
                            <textarea name="descripcion" class="modal__form-input" rows="2">Pago de salario</textarea>
                        </div>
                        
                        <div class="historial-container">
                            <h4 class="historial-title">Descuentos por Deudas</h4>
                            <div id="listaDeudasConductor">
                                <!-- Aquí se cargarán las deudas del conductor mediante AJAX -->
                            </div>
                            <div class="modal__form-row" style="margin-top: 15px;">
                                <div class="modal__form-group">
                                    <label class="modal__form-label">Total a Descontar</label>
                                    <input type="number" id="totalDescuentos" class="modal__form-input" readonly>
                                </div>
                                <div class="modal__form-group">
                                    <label class="modal__form-label">Total a Pagar</label>
                                    <input type="number" id="totalPagar" class="modal__form-input" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="conductor_id" id="pagoSalarioConductorId">
                        <input type="hidden" name="monto" id="pagoSalarioMontoReal">
                        <input type="hidden" name="total_pagado" id="pagoSalarioTotalPagado">
                        <input type="hidden" name="accion" value="pagar_salario">
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="modal__action-btn" onclick="cerrarModal('modalPagoSalario')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cancelar
                        </button>
                        <button type="submit" class="modal__action-btn modal__action-btn--primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M2 12h20"></path>
                            </svg>
                            Registrar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal para nuevo préstamo -->
        <div class="modal" id="modalNuevoPrestamo">
            <div class="modal__overlay" onclick="cerrarModal('modalNuevoPrestamo')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalNuevoPrestamo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Nuevo Préstamo</h3>
                </div>
                <form id="formNuevoPrestamo" method="post">
                    <div class="modal__body">
                        <div class="modal__form-group">
                            <label class="modal__form-label">Conductor</label>
                            <select name="conductor_id" class="modal__form-input" required>
                                <option value="">Seleccionar conductor</option>
                                <?php foreach ($conductores as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal__form-row">
                            <div class="modal__form-group">
                                <label class="modal__form-label">Fecha</label>
                                <input type="date" name="fecha" class="modal__form-input" required>
                            </div>
                            <div class="modal__form-group">
                                <label class="modal__form-label">Monto</label>
                                <input type="number" name="monto" class="modal__form-input" min="1" required>
                            </div>
                        </div>
                        <div class="modal__form-group">
                            <label class="modal__form-label">Descripción</label>
                            <textarea name="descripcion" class="modal__form-input" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="accion" value="agregar_prestamo">
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="modal__action-btn" onclick="cerrarModal('modalNuevoPrestamo')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cancelar
                        </button>
                        <button type="submit" class="modal__action-btn modal__action-btn--primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M2 12h20"></path>
                            </svg>
                            Registrar Préstamo
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal para editar préstamo -->
        <div class="modal" id="modalEditarPrestamo">
            <div class="modal__overlay" onclick="cerrarModal('modalEditarPrestamo')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalEditarPrestamo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Editar Préstamo</h3>
                </div>
                <form id="formEditarPrestamo" method="post">
                    <div class="modal__body">
                        <div class="modal__form-group">
                            <label class="modal__form-label">Conductor</label>
                            <select name="conductor_id" id="editarPrestamoConductor" class="modal__form-input" required>
                                <option value="">Seleccionar conductor</option>
                                <?php foreach ($conductores as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal__form-row">
                            <div class="modal__form-group">
                                <label class="modal__form-label">Fecha</label>
                                <input type="date" name="fecha" id="editarPrestamoFecha" class="modal__form-input" required>
                            </div>
                            <div class="modal__form-group">
                                <label class="modal__form-label">Monto</label>
                                <input type="number" name="monto" id="editarPrestamoMonto" class="modal__form-input" min="1" required>
                            </div>
                        </div>
                        <div class="modal__form-group">
                            <label class="modal__form-label">Descripción</label>
                            <textarea name="descripcion" id="editarPrestamoDescripcion" class="modal__form-input" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="id" id="editarPrestamoId">
                        <input type="hidden" name="accion" value="editar_prestamo">
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="modal__action-btn" onclick="cerrarModal('modalEditarPrestamo')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cancelar
                        </button>
                        <button type="submit" class="modal__action-btn modal__action-btn--primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M2 12h20"></path>
                            </svg>
                            Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal para nueva amonestación -->
        <div class="modal" id="modalNuevaAmonestacion">
            <div class="modal__overlay" onclick="cerrarModal('modalNuevaAmonestacion')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalNuevaAmonestacion')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Nueva Amonestación</h3>
                </div>
                <form id="formNuevaAmonestacion" method="post">
                    <div class="modal__body">
                        <div class="modal__form-group">
                            <label class="modal__form-label">Conductor</label>
                            <select name="conductor_id" class="modal__form-input" required>
                                <option value="">Seleccionar conductor</option>
                                <?php foreach ($conductores as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal__form-row">
                            <div class="modal__form-group">
                                <label class="modal__form-label">Fecha</label>
                                <input type="date" name="fecha" class="modal__form-input" required>
                            </div>
                            <div class="modal__form-group">
                                <label class="modal__form-label">Monto</label>
                                <input type="number" name="monto" class="modal__form-input" min="1" required>
                            </div>
                        </div>
                        <div class="modal__form-group">
                            <label class="modal__form-label">Descripción</label>
                            <textarea name="descripcion" class="modal__form-input" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="accion" value="agregar_amonestacion">
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="modal__action-btn" onclick="cerrarModal('modalNuevaAmonestacion')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cancelar
                        </button>
                        <button type="submit" class="modal__action-btn modal__action-btn--primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M2 12h20"></path>
                            </svg>
                            Registrar Amonestación
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal para cambiar estado de amonestación -->
        <div class="modal" id="modalCambiarEstadoAmonestacion">
            <div class="modal__overlay" onclick="cerrarModal('modalCambiarEstadoAmonestacion')"></div>
            <div class="modal__container">
                <div class="modal__close" onclick="cerrarModal('modalCambiarEstadoAmonestacion')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Cambiar Estado de Amonestación</h3>
                </div>
                <form id="formCambiarEstadoAmonestacion" method="post">
                    <div class="modal__body">
                        <div class="modal__form-group">
                            <label class="modal__form-label">Estado Actual</label>
                            <input type="text" id="amonestacionEstadoActual" class="modal__form-input" readonly>
                        </div>
                        <div class="modal__form-group">
                            <label class="modal__form-label">Nuevo Estado</label>
                            <select name="estado" class="modal__form-input" required>
                                <option value="activa">Activa</option>
                                <option value="pagada">Pagada</option>
                                <option value="anulado">Anulado</option>
                            </select>
                        </div>
                        <input type="hidden" name="id" id="amonestacionCambiarEstadoId">
                        <input type="hidden" name="accion" value="cambiar_estado_amonestacion">
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="modal__action-btn" onclick="cerrarModal('modalCambiarEstadoAmonestacion')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cancelar
                        </button>
                        <button type="submit" class="modal__action-btn modal__action-btn--primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2v20M2 12h20"></path>
                            </svg>
                            Cambiar Estado
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal para imprimir nómina -->
        <div class="modal" id="modalImprimirNomina">
            <div class="modal__overlay" onclick="cerrarModal('modalImprimirNomina')"></div>
            <div class="modal__container" style="max-width: 800px;">
                <div class="modal__close" onclick="cerrarModal('modalImprimirNomina')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </div>
                <div class="modal__header">
                    <h3 class="modal__title">Nómina de Pago</h3>
                </div>
                <div class="modal__body" id="contenidoNomina">
                    <!-- Aquí se cargará el contenido de la nómina mediante AJAX -->
                </div>
                <div class="modal__footer">
                    <button type="button" class="modal__action-btn" onclick="cerrarModal('modalImprimirNomina')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        Cerrar
                    </button>
                    <button type="button" class="modal__action-btn modal__action-btn--primary" onclick="imprimirContenido('contenidoNomina')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // ============ FUNCIONES GENERALES ============
    function abrirModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function cerrarModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    function imprimirContenido(elementId) {
        const contenido = document.getElementById(elementId).innerHTML;
        const ventana = window.open('', '', 'width=800,height=600');
        ventana.document.write('<html><head><title>Nómina de Pago</title>');
        ventana.document.write('<style>');
        ventana.document.write('body { font-family: Arial, sans-serif; margin: 20px; }');
        ventana.document.write('h1 { color: #004b87; text-align: center; }');
        ventana.document.write('table { width: 100%; border-collapse: collapse; margin: 20px 0; }');
        ventana.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        ventana.document.write('th { background-color: #f2f2f2; }');
        ventana.document.write('.firma { margin-top: 50px; border-top: 1px solid #000; width: 300px; text-align: center; }');
        ventana.document.write('</style>');
        ventana.document.write('</head><body>');
        ventana.document.write(contenido);
        ventana.document.write('</body></html>');
        ventana.document.close();
        ventana.focus();
        setTimeout(() => {
            ventana.print();
        }, 500);
    }
    
    // ============ FUNCIONES DE PESTAÑAS ============
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            // Desactivar todas las pestañas y contenidos
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Activar la pestaña y contenido seleccionados
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId + '-content').classList.add('active');
            
            // Mostrar u ocultar controles según la pestaña
            const filterEstado = document.getElementById('filterEstado');
            const openModalNuevo = document.getElementById('openModalNuevo');
            
            if (tabId === 'prestamos' || tabId === 'amonestaciones') {
                filterEstado.style.display = 'block';
                openModalNuevo.style.display = 'block';
            } else {
                filterEstado.style.display = 'none';
                openModalNuevo.style.display = 'none';
            }
        });
    });
    
    // ============ FILTROS Y BÚSQUEDA ============
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const activeTab = document.querySelector('.tab-content.active').id;
        const tableBody = document.getElementById(activeTab.replace('-content', '')).querySelector('tbody');
        
        Array.from(tableBody.rows).forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
    
    document.getElementById('filterConductor').addEventListener('change', function() {
        const conductorId = this.value;
        const activeTab = document.querySelector('.tab-content.active').id;
        const tableBody = document.getElementById(activeTab.replace('-content', '')).querySelector('tbody');
        
        Array.from(tableBody.rows).forEach(row => {
            if (conductorId === '') {
                row.style.display = '';
            } else {
                const rowConductor = row.getAttribute('data-conductor');
                row.style.display = rowConductor === conductorId ? '' : 'none';
            }
        });
    });
    
    document.getElementById('filterEstado').addEventListener('change', function() {
        const estado = this.value;
        const activeTab = document.querySelector('.tab-content.active').id;
        const tableBody = document.getElementById(activeTab.replace('-content', '')).querySelector('tbody');
        
        Array.from(tableBody.rows).forEach(row => {
            if (estado === '') {
                row.style.display = '';
            } else {
                const rowEstado = row.getAttribute('data-estado');
                row.style.display = rowEstado === estado ? '' : 'none';
            }
        });
    });
    
    // ============ FUNCIONES PARA MODALES ============
    function abrirModalNuevoPrestamo() {
        // Establecer la fecha actual por defecto
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('#formNuevoPrestamo input[name="fecha"]').value = today;
        abrirModal('modalNuevoPrestamo');
    }
    
    function abrirModalNuevaAmonestacion() {
        // Establecer la fecha actual por defecto
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('#formNuevaAmonestacion input[name="fecha"]').value = today;
        abrirModal('modalNuevaAmonestacion');
    }
    
    function abrirModalPagoIngreso(conductorId, montoPendiente, conductorNombre) {
        // Establecer valores en el modal
        document.getElementById('pagoIngresoConductor').value = conductorNombre;
        document.getElementById('pagoIngresoConductorId').value = conductorId;
        document.getElementById('pagoIngresoMonto').value = montoPendiente;
        
        // Establecer la fecha actual por defecto
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('#formPagoIngreso input[name="fecha"]').value = today;
        
        abrirModal('modalPagoIngreso');
    }
    
    function abrirModalPagoPrestamo(prestamoId, saldoPendiente, conductorNombre) {
        // Establecer valores en el modal
        document.getElementById('pagoPrestamoConductor').value = conductorNombre;
        document.getElementById('pagoPrestamoId').value = prestamoId;
        document.getElementById('pagoPrestamoMonto').value = saldoPendiente;
        
        // Establecer la fecha actual por defecto
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('#formPagoPrestamo input[name="fecha"]').value = today;
        
        abrirModal('modalPagoPrestamo');
    }
    
    function abrirModalPagoAmonestacion(amonestacionId, monto, conductorNombre) {
        // Establecer valores en el modal
        document.getElementById('pagoAmonestacionConductor').value = conductorNombre;
        document.getElementById('pagoAmonestacionId').value = amonestacionId;
        document.getElementById('pagoAmonestacionMonto').value = monto;
        
        // Establecer la fecha actual por defecto
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('#formPagoAmonestacion input[name="fecha"]').value = today;
        
        abrirModal('modalPagoAmonestacion');
    }
    
    function abrirModalPagoSalario(conductorId, conductorNombre, salarioMensual) {
        // Establecer valores en el modal
        document.getElementById('pagoSalarioConductor').value = conductorNombre;
        document.getElementById('pagoSalarioConductorId').value = conductorId;
        document.getElementById('pagoSalarioMonto').value = salarioMensual;
        document.getElementById('pagoSalarioMontoReal').value = salarioMensual;
        
        // Establecer la fecha actual por defecto
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('#formPagoSalario input[name="fecha"]').value = today;
        
        // Cargar deudas del conductor
        cargarDeudasConductor(conductorId, salarioMensual);
        
        abrirModal('modalPagoSalario');
    }
    
    function cargarDeudasConductor(conductorId, salarioMensual) {
        fetch(`deudas.php?action=obtener_deudas_conductor&conductor_id=${conductorId}`)
            .then(response => response.json())
            .then(data => {
                const listaDeudas = document.getElementById('listaDeudasConductor');
                listaDeudas.innerHTML = '';
                
                let totalDescuentos = 0;
                
                data.deudas.forEach(deuda => {
                    const deudaItem = document.createElement('div');
                    deudaItem.className = 'deuda-item';
                    
                    deudaItem.innerHTML = `
                        <label>${deuda.descripcion}: ${deuda.monto} XAF</label>
                        <input type="number" name="pago_deuda[${deuda.id}]" class="monto-deuda" 
                               data-tipo="${deuda.tipo}" min="0" max="${deuda.monto}" 
                               oninput="calcularTotalPago(${salarioMensual})">
                        <input type="hidden" name="tipo_deuda[${deuda.id}]" value="${deuda.tipo}">
                    `;
                    
                    listaDeudas.appendChild(deudaItem);
                });
                
                // Actualizar totales
                calcularTotalPago(salarioMensual);
            })
            .catch(error => console.error('Error al cargar deudas:', error));
    }
    
    function calcularTotalPago(salarioMensual) {
        const montosDeuda = document.querySelectorAll('.monto-deuda');
        let totalDescuentos = 0;
        
        montosDeuda.forEach(input => {
            const monto = parseFloat(input.value) || 0;
            totalDescuentos += monto;
        });
        
        document.getElementById('totalDescuentos').value = totalDescuentos;
        
        const totalPagar = salarioMensual - totalDescuentos;
        document.getElementById('totalPagar').value = totalPagar;
        document.getElementById('pagoSalarioTotalPagado').value = totalPagar;
    }
    
    // ============ FUNCIONES PARA VER DETALLES ============
    function verIngreso(ingresoId) {
        fetch(`deudas.php?action=obtener_detalle_pago&tipo=ingreso&id=${ingresoId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('ingresoId').textContent = data.id;
                document.getElementById('ingresoFecha').textContent = data.fecha;
                document.getElementById('ingresoConductor').textContent = data.conductor_nombre;
                document.getElementById('ingresoMontoEsperado').textContent = data.monto_esperado + ' XAF';
                document.getElementById('ingresoMontoIngresado').textContent = data.monto_ingresado + ' XAF';
                document.getElementById('ingresoMontoPendiente').textContent = data.monto_pendiente + ' XAF';
                document.getElementById('ingresoDescripcion').textContent = data.descripcion || 'N/A';
                
                // Configurar botón de pagar
                const btnPagar = document.getElementById('btnPagarIngresoModal');
                btnPagar.onclick = function() {
                    abrirModalPagoIngreso(data.conductor_id, data.monto_pendiente, data.conductor_nombre);
                    cerrarModal('modalVerIngreso');
                };
                
                abrirModal('modalVerIngreso');
            })
            .catch(error => console.error('Error al cargar ingreso:', error));
    }
    
    function verPrestamo(prestamoId) {
        fetch(`deudas.php?action=obtener_prestamo&id=${prestamoId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('prestamoId').textContent = data.id;
                document.getElementById('prestamoFecha').textContent = data.fecha;
                document.getElementById('prestamoConductor').textContent = data.conductor_nombre;
                document.getElementById('prestamoMonto').textContent = data.monto + ' XAF';
                document.getElementById('prestamoSaldoPendiente').textContent = data.saldo_pendiente + ' XAF';
                document.getElementById('prestamoEstado').textContent = data.estado;
                document.getElementById('prestamoDescripcion').textContent = data.descripcion || 'N/A';
                
                // Configurar botones
                document.getElementById('btnPagarPrestamoModal').onclick = function() {
                    abrirModalPagoPrestamo(data.id, data.saldo_pendiente, data.conductor_nombre);
                    cerrarModal('modalVerPrestamo');
                };
                
                document.getElementById('btnEditarPrestamoModal').onclick = function() {
                    editarPrestamo(data.id);
                    cerrarModal('modalVerPrestamo');
                };
                
                // Cargar historial de pagos
                cargarHistorialPagos('prestamo', prestamoId, 'historialPagosPrestamo');
                
                abrirModal('modalVerPrestamo');
            })
            .catch(error => console.error('Error al cargar préstamo:', error));
    }
    
    function verAmonestacion(amonestacionId) {
        fetch(`deudas.php?action=obtener_detalle_pago&tipo=amonestacion&id=${amonestacionId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('amonestacionId').textContent = data.id;
                document.getElementById('amonestacionFecha').textContent = data.fecha;
                document.getElementById('amonestacionConductor').textContent = data.conductor_nombre;
                document.getElementById('amonestacionMonto').textContent = data.monto + ' XAF';
                document.getElementById('amonestacionEstado').textContent = data.estado;
                document.getElementById('amonestacionDescripcion').textContent = data.descripcion || 'N/A';
                
                // Configurar botones
                document.getElementById('btnPagarAmonestacionModal').onclick = function() {
                    abrirModalPagoAmonestacion(data.id, data.monto, data.conductor_nombre);
                    cerrarModal('modalVerAmonestacion');
                };
                
                document.getElementById('btnAnularAmonestacionModal').onclick = function() {
                    cambiarEstadoAmonestacion(data.id, 'anulado');
                    cerrarModal('modalVerAmonestacion');
                };
                
                // Cargar historial de pagos
                cargarHistorialPagos('amonestacion', amonestacionId, 'historialPagosAmonestacion');
                
                abrirModal('modalVerAmonestacion');
            })
            .catch(error => console.error('Error al cargar amonestación:', error));
    }
    
    function verDetallePago(tipo, pagoId) {
        fetch(`deudas.php?action=obtener_detalle_pago&tipo=${tipo}&id=${pagoId}`)
            .then(response => response.json())
            .then(data => {
                const modalBody = document.getElementById('modalVerPagoBody');
                
                let html = `
                    <table class="modal__data-table">
                        <tr>
                            <td class="modal__data-label">ID</td>
                            <td class="modal__data-value">${data.id}</td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Fecha</td>
                            <td class="modal__data-value">${data.fecha}</td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Conductor</td>
                            <td class="modal__data-value">${data.conductor_nombre}</td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Monto</td>
                            <td class="modal__data-value">${data.monto} XAF</td>
                        </tr>
                        <tr>
                            <td class="modal__data-label">Descripción</td>
                            <td class="modal__data-value">${data.descripcion || 'N/A'}</td>
                        </tr>
                `;
                
                if (tipo === 'prestamo') {
                    html += `
                        <tr>
                            <td class="modal__data-label">Préstamo ID</td>
                            <td class="modal__data-value">${data.prestamo_id}</td>
                        </tr>
                    `;
                } else if (tipo === 'amonestacion') {
                    html += `
                        <tr>
                            <td class="modal__data-label">Amonestación ID</td>
                            <td class="modal__data-value">${data.amonestacion_id}</td>
                        </tr>
                    `;
                }
                
                html += `</table>`;
                modalBody.innerHTML = html;
                
                abrirModal('modalVerPago');
            })
            .catch(error => console.error('Error al cargar pago:', error));
    }
    
    function verDetalleConductor(conductorId) {
        fetch(`deudas.php?action=obtener_detalle_conductor&conductor_id=${conductorId}`)
            .then(response => response.json())
            .then(data => {
                const conductor = data.conductor;
                
                document.getElementById('conductorId').textContent = conductor.id;
                document.getElementById('conductorNombre').textContent = conductor.nombre;
                document.getElementById('conductorTelefono').textContent = conductor.telefono || 'N/A';
                document.getElementById('conductorVehiculo').textContent = 
                    conductor.vehiculo_placa ? `${conductor.vehiculo_placa} - ${conductor.vehiculo_modelo}` : 'N/A';
                document.getElementById('conductorSalario').textContent = conductor.salario_mensual + ' XAF';
                document.getElementById('conductorDiasCiclo').textContent = conductor.dias_por_ciclo;
                document.getElementById('conductorCiclosCompletados').textContent = conductor.ciclos_completados;
                document.getElementById('conductorTotalPendiente').textContent = conductor.total_pendiente + ' XAF';
                document.getElementById('conductorTotalPrestamos').textContent = conductor.total_prestamos + ' XAF';
                document.getElementById('conductorTotalAmonestaciones').textContent = conductor.total_amonestaciones + ' XAF';
                
                // Configurar botón de pagar salario
                document.getElementById('btnPagarSalarioConductor').onclick = function() {
                    abrirModalPagoSalario(conductor.id, conductor.nombre, conductor.salario_mensual);
                    cerrarModal('modalVerConductor');
                };
                
                // Cargar historial de pagos de salario
                const historialContainer = document.getElementById('historialPagosSalario');
                historialContainer.innerHTML = '';
                
                if (data.pagos_salario.length > 0) {
                    const table = document.createElement('table');
                    table.className = 'table';
                    table.style.width = '100%';
                    
                    const thead = document.createElement('thead');
                    thead.innerHTML = `
                        <tr>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Descripción</th>
                        </tr>
                    `;
                    
                    const tbody = document.createElement('tbody');
                    data.pagos_salario.forEach(pago => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${pago.fecha}</td>
                            <td>${pago.monto} XAF</td>
                            <td>${pago.descripcion || 'N/A'}</td>
                        `;
                        tbody.appendChild(row);
                    });
                    
                    table.appendChild(thead);
                    table.appendChild(tbody);
                    historialContainer.appendChild(table);
                } else {
                    historialContainer.innerHTML = '<p>No hay registros de pagos de salario.</p>';
                }
                
                abrirModal('modalVerConductor');
            })
            .catch(error => console.error('Error al cargar conductor:', error));
    }
    
    function cargarHistorialPagos(tipo, id, elementoId) {
        const action = tipo === 'prestamo' ? 'obtener_pagos_prestamo' : 'obtener_pagos_amonestacion';
        const param = tipo === 'prestamo' ? 'prestamo_id' : 'amonestacion_id';
        
        fetch(`deudas.php?action=${action}&${param}=${id}`)
            .then(response => response.json())
            .then(data => {
                const historialContainer = document.getElementById(elementoId);
                historialContainer.innerHTML = '';
                
                if (data.length > 0) {
                    const table = document.createElement('table');
                    table.className = 'table';
                    table.style.width = '100%';
                    
                    const thead = document.createElement('thead');
                    thead.innerHTML = `
                        <tr>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Descripción</th>
                        </tr>
                    `;
                    
                    const tbody = document.createElement('tbody');
                    data.forEach(pago => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${pago.fecha}</td>
                            <td>${pago.monto} XAF</td>
                            <td>${pago.descripcion || 'N/A'}</td>
                        `;
                        tbody.appendChild(row);
                    });
                    
                    table.appendChild(thead);
                    table.appendChild(tbody);
                    historialContainer.appendChild(table);
                } else {
                    historialContainer.innerHTML = '<p>No hay registros de pagos.</p>';
                }
            })
            .catch(error => console.error('Error al cargar historial de pagos:', error));
    }
    
    // ============ FUNCIONES DE EDICIÓN ============
    function editarPrestamo(prestamoId) {
        fetch(`deudas.php?action=obtener_prestamo&id=${prestamoId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('editarPrestamoId').value = data.id;
                document.getElementById('editarPrestamoConductor').value = data.conductor_id;
                document.getElementById('editarPrestamoFecha').value = data.fecha;
                document.getElementById('editarPrestamoMonto').value = data.monto;
                document.getElementById('editarPrestamoDescripcion').value = data.descripcion || '';
                
                abrirModal('modalEditarPrestamo');
            })
            .catch(error => console.error('Error al cargar préstamo para editar:', error));
    }
    
    function cambiarEstadoAmonestacion(amonestacionId, estado) {
        if (confirm(`¿Está seguro de cambiar el estado de esta amonestación a "${estado}"?`)) {
            const form = document.createElement('form');
            form.method = 'post';
            form.action = 'deudas.php';
            
            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = 'id';
            inputId.value = amonestacionId;
            
            const inputAccion = document.createElement('input');
            inputAccion.type = 'hidden';
            inputAccion.name = 'accion';
            inputAccion.value = 'cambiar_estado_amonestacion';
            
            const inputEstado = document.createElement('input');
            inputEstado.type = 'hidden';
            inputEstado.name = 'estado';
            inputEstado.value = estado;
            
            form.appendChild(inputId);
            form.appendChild(inputAccion);
            form.appendChild(inputEstado);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // ============ FUNCIÓN PARA IMPRIMIR NÓMINA ============
    function imprimirNomina(pagoId) {
        fetch(`deudas.php?action=obtener_detalle_pago&tipo=salario&id=${pagoId}`)
            .then(response => response.json())
            .then(data => {
                const contenido = document.getElementById('contenidoNomina');
                
                let html = `
                    <h1 style="text-align: center; color: #004b87;">NÓMINA DE PAGO</h1>
                    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px; width: 30%;"><strong>Conductor:</strong></td>
                            <td style="border: 1px solid #ddd; padding: 8px;">${data.conductor_nombre}</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px;"><strong>Fecha de Pago:</strong></td>
                            <td style="border: 1px solid #ddd; padding: 8px;">${data.fecha}</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px;"><strong>Vehículo Asignado:</strong></td>
                            <td style="border: 1px solid #ddd; padding: 8px;">${data.vehiculo_placa || 'N/A'} - ${data.vehiculo_modelo || 'N/A'}</td>
                        </tr>
                    </table>
                    
                    <h3 style="color: #004b87; margin-top: 30px;">Detalles del Pago</h3>
                    <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px; width: 30%;"><strong>Salario:</strong></td>
                            <td style="border: 1px solid #ddd; padding: 8px;">${data.monto} XAF</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px;"><strong>Descripción:</strong></td>
                            <td style="border: 1px solid #ddd; padding: 8px;">${data.descripcion || 'N/A'}</td>
                        </tr>
                    </table>
                    
                    <div style="margin-top: 50px; text-align: right;">
                        <div style="width: 300px; border-top: 1px solid #000; margin-left: auto; text-align: center; padding-top: 5px;">
                            Firma del Conductor
                        </div>
                    </div>
                `;
                
                contenido.innerHTML = html;
                abrirModal('modalImprimirNomina');
            })
            .catch(error => console.error('Error al cargar datos de nómina:', error));
    }
    
    function imprimirNominaConductor() {
        const conductorId = document.getElementById('conductorId').textContent;
        const conductorNombre = document.getElementById('conductorNombre').textContent;
        const vehiculo = document.getElementById('conductorVehiculo').textContent;
        const salario = document.getElementById('conductorSalario').textContent;
        
        const contenido = document.getElementById('contenidoNomina');
        
        let html = `
            <h1 style="text-align: center; color: #004b87;">NÓMINA DE PAGO</h1>
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; width: 30%;"><strong>Conductor:</strong></td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${conductorNombre}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;"><strong>Fecha:</strong></td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${new Date().toLocaleDateString()}</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px;"><strong>Vehículo Asignado:</strong></td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${vehiculo}</td>
                </tr>
            </table>
            
            <h3 style="color: #004b87; margin-top: 30px;">Detalles del Salario</h3>
            <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                <tr>
                    <td style="border: 1px solid #ddd; padding: 8px; width: 30%;"><strong>Salario Mensual:</strong></td>
                    <td style="border: 1px solid #ddd; padding: 8px;">${salario}</td>
                </tr>
            </table>
            
            <div style="margin-top: 50px; text-align: right;">
                <div style="width: 300px; border-top: 1px solid #000; margin-left: auto; text-align: center; padding-top: 5px;">
                    Firma del Conductor
                </div>
            </div>
        `;
        
        contenido.innerHTML = html;
        abrirModal('modalImprimirNomina');
    }
    
    // ============ INICIALIZACIÓN ============
    document.addEventListener('DOMContentLoaded', function() {
        // Establecer la fecha actual en todos los formularios que la requieran
        const today = new Date().toISOString().split('T')[0];
        document.querySelectorAll('input[type="date"]').forEach(input => {
            if (!input.value) {
                input.value = today;
            }
        });
    });
    </script>
</body>
</html>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("filtroTabla");
    const table = document.getElementById("tablaDeudas");
    const rows = table.getElementsByTagName("tr");

    searchInput.addEventListener("keyup", function() {
        const filter = searchInput.value.toLowerCase();
        for (let i = 1; i < rows.length; i++) {
            let rowText = rows[i].textContent.toLowerCase();
            rows[i].style.display = rowText.includes(filter) ? "" : "none";
        }
    });
});
</script>
