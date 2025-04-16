<?php
include '../../layout/header.php';
require '../../config/database.php';

// ==============================

// Obtener la caja predeterminada al inicio del script
$caja_predeterminada = $pdo->query("SELECT id FROM cajas WHERE predeterminada = 1 LIMIT 1")->fetch();
if (!$caja_predeterminada) {
    die("No se ha configurado una caja predeterminada");
}
$caja_id = $caja_predeterminada['id'];

// LÓGICA DE ACCIONES: Agregar / Editar / Eliminar
// ==============================
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // Agregar préstamo
    if ($accion == 'agregar_prestamo') {
        $fecha = $_POST['fecha'];
        $conductor_id = $_POST['conductor_id'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];

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
                "Préstamo ID: $prestamo_id - " . substr($descripcion, 0, 245),
                $fecha,
                $prestamo_id
            ]);
            
            // Actualizar saldo de la caja predeterminada
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
        $id = $_POST['id'];
        $fecha = $_POST['fecha'];
        $conductor_id = $_POST['conductor_id'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];
        
        $pdo->beginTransaction();
        try {
            // Obtener el préstamo actual para calcular diferencias
            $stmt = $pdo->prepare("SELECT monto, saldo_pendiente FROM prestamos WHERE id = ?");
            $stmt->execute([$id]);
            $prestamo_actual = $stmt->fetch();
            
            if (!$prestamo_actual) {
                throw new Exception("Préstamo no encontrado");
            }
            
            $monto_actual = $prestamo_actual['monto'];
            $saldo_actual = $prestamo_actual['saldo_pendiente'];
            
            // Calcular diferencia de montos
            $diferencia = $monto - $monto_actual;
            
            // Actualizar préstamo
            $stmt = $pdo->prepare("
                UPDATE prestamos 
                SET fecha = ?, conductor_id = ?, monto = ?, descripcion = ?, saldo_pendiente = saldo_pendiente + ?
                WHERE id = ?
            ");
            $stmt->execute([$fecha, $conductor_id, $monto, $descripcion, $diferencia, $id]);
            
            if ($diferencia != 0) {
                // Registrar movimiento en caja si hay diferencia
                $tipo_movimiento = $diferencia > 0 ? 'egreso' : 'ingreso';
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
                    $tipo_movimiento,
                    $abs_diferencia,
                    "Ajuste préstamo ID: $id - " . substr($descripcion, 0, 245),
                    $fecha,
                    $id
                ]);
                
                // Actualizar saldo de la caja
                $stmt = $pdo->prepare("
                    UPDATE cajas 
                    SET saldo_actual = saldo_actual " . ($diferencia > 0 ? '-' : '+') . " ?
                    WHERE id = ?
                ");
                $stmt->execute([$abs_diferencia, $caja_id]);
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
        $prestamo_id = $_POST['prestamo_id'];
        $fecha = $_POST['fecha'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];
    
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
                pago_prestamo_id  -- Usamos la nueva columna específica
            ) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $caja_id,
            'ingreso',
            $monto,
            "Pago préstamo ID: $prestamo_id",
            $fecha,
            $pago_id  // ID de pagos_prestamos
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
        $fecha = $_POST['fecha'];
        $conductor_id = $_POST['conductor_id'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];

        $stmt = $pdo->prepare("
            INSERT INTO amonestaciones (fecha, conductor_id, monto, descripcion, estado)
            VALUES (?, ?, ?, ?, 'pendiente')
        ");
        $stmt->execute([$fecha, $conductor_id, $monto, $descripcion]);
        
        header("Location: deudas.php");
        exit;
    }

    // Agregar pago de amonestación
    elseif ($accion == 'agregar_pago_amonestacion') {
        $amonestacion_id = $_POST['amonestacion_id'];
        $fecha = $_POST['fecha'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];
    
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
        $conductor_id = $_POST['conductor_id'];
        $fecha = $_POST['fecha'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];
    
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
        $conductor_id = $_POST['conductor_id'];
        $fecha = $_POST['fecha'];
        $monto_salario = $_POST['monto_salario'];
        $descripcion = $_POST['descripcion'];
        $amonestaciones = isset($_POST['amonestaciones']) ? $_POST['amonestaciones'] : [];
        $ingresos = isset($_POST['ingresos']) ? $_POST['ingresos'] : [];
        $prestamos = isset($_POST['prestamos']) ? $_POST['prestamos'] : [];
        
        $pdo->beginTransaction();
        try {
            // 1. Registrar pago de salario
            $stmt = $pdo->prepare("
                INSERT INTO pagos_salarios (conductor_id, fecha, monto, descripcion)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$conductor_id, $fecha, $monto_salario, $descripcion]);
            $pago_salario_id = $pdo->lastInsertId();
            
            // 2. Registrar movimiento en caja (egreso)
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
                $monto_salario,
                "Pago salario conductor ID: $conductor_id",
                $fecha,
                $pago_salario_id
            ]);
            
            // 3. Actualizar saldo de la caja
            $stmt = $pdo->prepare("
                UPDATE cajas 
                SET saldo_actual = saldo_actual - ?
                WHERE id = ?
            ");
            $stmt->execute([$monto_salario, $caja_id]);
            
            // 4. Procesar amonestaciones
            foreach ($amonestaciones as $amonestacion_id => $monto) {
                if ($monto > 0) {
                    // Registrar pago de amonestación
                    $stmt = $pdo->prepare("
                        INSERT INTO pagos_amonestaciones (amonestacion_id, fecha, monto, descripcion, pago_salario_id)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $amonestacion_id,
                        $fecha,
                        $monto,
                        "Pago desde salario ID: $pago_salario_id",
                        $pago_salario_id
                    ]);
                    $pago_amonestacion_id = $pdo->lastInsertId();
                    
                    // Registrar movimiento en caja (ingreso)
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
                        "Pago amonestación ID: $amonestacion_id desde salario",
                        $fecha,
                        $pago_amonestacion_id
                    ]);
                    
                    // Actualizar saldo de la caja
                    $stmt = $pdo->prepare("
                        UPDATE cajas 
                        SET saldo_actual = saldo_actual + ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$monto, $caja_id]);
                    
                    // Verificar si la amonestación está completamente pagada
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
                }
            }
            
            // 5. Procesar ingresos pendientes
            foreach ($ingresos as $ingreso_id => $monto) {
                if ($monto > 0) {
                    // Registrar pago de ingreso pendiente
                    $stmt = $pdo->prepare("
                        INSERT INTO pagos_ingresos_pendientes (conductor_id, fecha, monto, descripcion, pago_salario_id)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $conductor_id,
                        $fecha,
                        $monto,
                        "Pago desde salario ID: $pago_salario_id",
                        $pago_salario_id
                    ]);
                    $pago_ingreso_id = $pdo->lastInsertId();
                    
                    // Registrar movimiento en caja (ingreso)
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
                        "Pago ingreso pendiente desde salario",
                        $fecha,
                        $pago_ingreso_id
                    ]);
                    
                    // Actualizar saldo de la caja
                    $stmt = $pdo->prepare("
                        UPDATE cajas 
                        SET saldo_actual = saldo_actual + ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$monto, $caja_id]);
                    
                    // Actualizar ingreso pendiente
                    $stmt = $pdo->prepare("
                        UPDATE ingresos 
                        SET monto_pendiente = monto_pendiente - ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$monto, $ingreso_id]);
                }
            }
            
            // 6. Procesar préstamos
            foreach ($prestamos as $prestamo_id => $monto) {
                if ($monto > 0) {
                    // Registrar pago de préstamo
                    $stmt = $pdo->prepare("
                        INSERT INTO pagos_prestamos (prestamo_id, fecha, monto, descripcion, pago_salario_id)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $prestamo_id,
                        $fecha,
                        $monto,
                        "Pago desde salario ID: $pago_salario_id",
                        $pago_salario_id
                    ]);
                    $pago_prestamo_id = $pdo->lastInsertId();
                    
                    // Actualizar saldo pendiente del préstamo
                    $stmt = $pdo->prepare("
                        UPDATE prestamos 
                        SET saldo_pendiente = saldo_pendiente - ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$monto, $prestamo_id]);
                    
                    // Registrar movimiento en caja (ingreso)
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
                        "Pago préstamo ID: $prestamo_id desde salario",
                        $fecha,
                        $pago_prestamo_id
                    ]);
                    
                    // Actualizar saldo de la caja
                    $stmt = $pdo->prepare("
                        UPDATE cajas 
                        SET saldo_actual = saldo_actual + ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$monto, $caja_id]);
                    
                    // Verificar si el préstamo está completamente pagado
                    $stmt = $pdo->prepare("SELECT saldo_pendiente FROM prestamos WHERE id = ?");
                    $stmt->execute([$prestamo_id]);
                    $saldo = $stmt->fetchColumn();
                    
                    if ($saldo <= 0) {
                        $stmt = $pdo->prepare("UPDATE prestamos SET estado = 'pagado' WHERE id = ?");
                        $stmt->execute([$prestamo_id]);
                    }
                }
            }
            
            // 7. Actualizar ciclo del conductor
            $stmt = $pdo->prepare("
                UPDATE ingresos 
                SET ciclo_completado = 1 
                WHERE conductor_id = ? AND ciclo = (SELECT MAX(ciclo) FROM ingresos WHERE conductor_id = ?)
            ");
            $stmt->execute([$conductor_id, $conductor_id]);
            
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
        $id = $_POST['id'];
        $estado = $_POST['estado'];

        $stmt = $pdo->prepare("UPDATE amonestaciones SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        
        header("Location: deudas.php");
        exit;
    }

    // Eliminar préstamo
    elseif ($accion == 'eliminar_prestamo') {
        $id = $_POST['id'];
        
        $pdo->beginTransaction();
        try {
            // Obtener el préstamo para verificar si hay saldo pendiente
            $stmt = $pdo->prepare("SELECT saldo_pendiente FROM prestamos WHERE id = ?");
            $stmt->execute([$id]);
            $prestamo = $stmt->fetch();
            
            if ($prestamo && $prestamo['saldo_pendiente'] > 0) {
                throw new Exception("No se puede eliminar un préstamo con saldo pendiente");
            }
            
            // Eliminar pagos asociados
            $stmt = $pdo->prepare("DELETE FROM pagos_prestamos WHERE prestamo_id = ?");
            $stmt->execute([$id]);
            
            // Eliminar movimientos de caja asociados
            $stmt = $pdo->prepare("DELETE FROM movimientos_caja WHERE prestamo_id = ?");
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
        $id = $_POST['id'];
        
        $pdo->beginTransaction();
        try {
            // Obtener la amonestación para verificar si hay pagos
            $stmt = $pdo->prepare("SELECT estado FROM amonestaciones WHERE id = ?");
            $stmt->execute([$id]);
            $amonestacion = $stmt->fetch();
            
            if ($amonestacion && $amonestacion['estado'] === 'pagada') {
                throw new Exception("No se puede eliminar una amonestación pagada");
            }
            
            // Eliminar pagos asociados
            $stmt = $pdo->prepare("DELETE FROM pagos_amonestaciones WHERE amonestacion_id = ?");
            $stmt->execute([$id]);
            
            // Eliminar movimientos de caja asociados
            $stmt = $pdo->prepare("DELETE FROM movimientos_caja WHERE pago_amonestacion_id IN (SELECT id FROM pagos_amonestaciones WHERE amonestacion_id = ?)");
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
$conductoresBase = $pdo->query("SELECT c.id, c.nombre, c.salario_mensual, c.dias_por_ciclo, v.placa as vehiculo_placa 
                               FROM conductores c
                               LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
                               ORDER BY c.nombre")->fetchAll();

foreach ($conductoresBase as $conductor) {
    $conductorId = $conductor['id'];
    
    // Ciclos completados
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ingresos WHERE conductor_id = ? AND tipo_ingreso = 'obligatorio' AND ciclo_completado = 1");
    $stmt->execute([$conductorId]);
    $ciclosCompletados = $stmt->fetchColumn();
    
    // Días trabajados en el último ciclo
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ingresos WHERE conductor_id = ? AND tipo_ingreso = 'obligatorio' AND ciclo = (SELECT MAX(ciclo) FROM ingresos WHERE conductor_id = ?)");
    $stmt->execute([$conductorId, $conductorId]);
    $diasTrabajados = $stmt->fetchColumn();
    
    // Deudas
    $stmt = $pdo->prepare("SELECT i.id, i.monto_pendiente FROM ingresos WHERE conductor_id = ? AND monto_pendiente > 0");
    $stmt->execute([$conductorId]);
    $ingresosPendientes = $stmt->fetchAll();
    $totalPendiente = array_sum(array_column($ingresosPendientes, 'monto_pendiente'));
    
    $stmt = $pdo->prepare("SELECT p.id, p.saldo_pendiente FROM prestamos WHERE conductor_id = ? AND estado = 'pendiente'");
    $stmt->execute([$conductorId]);
    $prestamosPendientes = $stmt->fetchAll();
    $totalPrestamos = array_sum(array_column($prestamosPendientes, 'saldo_pendiente'));
    
    $stmt = $pdo->prepare("SELECT a.id, a.monto FROM amonestaciones WHERE conductor_id = ? AND estado = 'pendiente'");
    $stmt->execute([$conductorId]);
    $amonestacionesPendientes = $stmt->fetchAll();
    $totalAmonestaciones = array_sum(array_column($amonestacionesPendientes, 'monto'));
    
    $conductoresCompletos[] = [
        'id' => $conductor['id'],
        'nombre' => $conductor['nombre'],
        'salario_mensual' => $conductor['salario_mensual'],
        'dias_por_ciclo' => $conductor['dias_por_ciclo'],
        'vehiculo_placa' => $conductor['vehiculo_placa'],
        'ciclos_completados' => $ciclosCompletados,
        'dias_trabajados_ultimo_ciclo' => $diasTrabajados,
        'total_pendiente' => $totalPendiente,
        'total_prestamos' => $totalPrestamos,
        'total_amonestaciones' => $totalAmonestaciones,
        'ingresos_pendientes' => $ingresosPendientes,
        'prestamos_pendientes' => $prestamosPendientes,
        'amonestaciones_pendientes' => $amonestacionesPendientes
    ];
}

// Obtener resumen de deudas por conductor
$resumenDeudas = $pdo->query("
    SELECT 
        c.id AS conductor_id,
        c.nombre AS conductor_nombre,
        COALESCE(SUM(i.monto_pendiente), 0) AS total_pendiente,
        COALESCE(SUM(p.saldo_pendiente), 0) AS total_prestamos,
        COALESCE(SUM(CASE WHEN a.estado = 'pendiente' THEN a.monto ELSE 0 END), 0) AS total_amonestaciones
    FROM conductores c
    LEFT JOIN ingresos i ON c.id = i.conductor_id AND i.monto_pendiente > 0
    LEFT JOIN prestamos p ON c.id = p.conductor_id AND p.estado = 'pendiente'
    LEFT JOIN amonestaciones a ON c.id = a.conductor_id AND a.estado = 'pendiente'
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

// Obtener salarios pagados
$salariosPagados = $pdo->query("
    SELECT ps.*, c.nombre AS conductor_nombre, 
           (SELECT MIN(fecha) FROM ingresos WHERE conductor_id = c.id AND ciclo = (SELECT MAX(ciclo) FROM ingresos WHERE conductor_id = c.id)) AS fecha_inicio_ciclo
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
    /* ============ ESTILOS PRINCIPALES (COPIADOS DE INGRESOS.PHP) ============ */
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
    
    /* ============ DEUDAS EN PAGO SALARIO ============ */
    .deuda-item {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
        border: 1px solid var(--color-borde);
    }
    
    .deuda-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
    }
    
    .deuda-title {
        font-weight: 600;
        color: var(--color-primario);
    }
    
    .deuda-monto {
        font-weight: 600;
    }
    
    .deuda-input-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .deuda-input-group label {
        font-size: 0.85rem;
    }
    
    .deuda-input-group input {
        flex: 1;
        max-width: 100px;
    }
    
    /* ============ HISTORIAL ============ */
    .historial-container {
        margin-top: 20px;
        border-top: 1px solid var(--color-borde);
        padding-top: 15px;
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
    
    .historial-item {
        padding: 10px;
        border-bottom: 1px solid var(--color-borde);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .historial-item:last-child {
        border-bottom: none;
    }
    
    .historial-item-info {
        flex: 1;
    }
    
    .historial-item-acciones {
        display: flex;
        gap: 5px;
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
                                        <button class="btn btn-ver" onclick="verIngreso(<?= htmlspecialchars(json_encode($ingreso), ENT_QUOTES, 'UTF-8') ?>)">
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
                                        <button class="btn btn-ver" onclick="verPrestamo(<?= htmlspecialchars(json_encode($prestamo), ENT_QUOTES, 'UTF-8') ?>)">
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
                                        <button class="btn btn-ver" onclick="verAmonestacion(<?= htmlspecialchars(json_encode($amonestacion), ENT_QUOTES, 'UTF-8') ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <button class="btn btn-nuevo" onclick="abrirModalPagoAmonestacion(<?= $amonestacion['id'] ?>, <?= $amonestacion['monto'] ?>, '<?= htmlspecialchars($amonestacion['conductor_nombre']) ?>')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 2v20M2 12h20"></path>
                                            </svg>
                                            Pagar
                                        </button>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('¿Eliminar esta amonestación?')">
                                            <input type="hidden" name="id" value="<?= $amonestacion['id'] ?>">
                                            <input type="hidden" name="accion" value="eliminar_amonestacion">
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
        
        <!-- Pestaña Historial de Pagos -->
        <div class="tab-content" id="historial-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Tipo</th>
                            <th>Monto</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyHistorial">
                        <?php foreach ($historialPagos as $pago): ?>
                            <tr data-conductor="<?= $pago['conductor_id'] ?>" data-tipo="<?= $pago['tipo'] ?>">
                                <td><?= $pago['id'] ?></td>
                                <td><?= $pago['fecha'] ?></td>
                                <td><?= htmlspecialchars($pago['conductor_nombre']) ?></td>
                                <td><?= ucfirst($pago['tipo']) ?></td>
                                <td><?= number_format($pago['monto'], 0, ',', '.') ?> XAF</td>
                                <td><?= htmlspecialchars($pago['descripcion']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verPago('<?= $pago['tipo'] ?>', <?= $pago['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pestaña Conductores -->
        <div class="tab-content" id="conductores-content">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Vehículo</th>
                            <th>Antigüedad</th>
                            <th>Salario</th>
                            <th>Deuda actual</th>
                            <th>Días trabajados</th>
                            <th>Pago pendiente</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyConductores">
                        <?php foreach ($conductoresCompletos as $conductor): 
                            $deudaTotal = $conductor['total_pendiente'] + $conductor['total_prestamos'] + $conductor['total_amonestaciones'];
                            $pagoPendiente = ($conductor['salario_mensual'] / $conductor['dias_por_ciclo']) * $conductor['dias_trabajados_ultimo_ciclo'];
                        ?>
                            <tr data-conductor="<?= $conductor['id'] ?>">
                                <td><?= $conductor['id'] ?></td>
                                <td><?= htmlspecialchars($conductor['nombre']) ?></td>
                                <td><?= $conductor['vehiculo_placa'] ? htmlspecialchars($conductor['vehiculo_placa']) : 'N/A' ?></td>
                                <td><?= $conductor['ciclos_completados'] ?> ciclos</td>
                                <td><?= number_format($conductor['salario_mensual'], 0, ',', '.') ?> XAF</td>
                                <td><?= number_format($deudaTotal, 0, ',', '.') ?> XAF</td>
                                <td><?= $conductor['dias_trabajados_ultimo_ciclo'] ?>/<?= $conductor['dias_por_ciclo'] ?></td>
                                <td><?= number_format($pagoPendiente, 0, ',', '.') ?> XAF</td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verConductor(<?= htmlspecialchars(json_encode($conductor), ENT_QUOTES, 'UTF-8') ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <button class="btn btn-nuevo" onclick="abrirModalPagoSalario(<?= $conductor['id'] ?>, <?= $pagoPendiente ?>, '<?= htmlspecialchars($conductor['nombre']) ?>')">
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
                            <th>Periodo</th>
                            <th>Descripción</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodySalarios">
                        <?php foreach ($salariosPagados as $salario): 
                            $fechaInicio = $salario['fecha_inicio_ciclo'] ? date('d/m/Y', strtotime($salario['fecha_inicio_ciclo'])) : 'N/A';
                            $fechaFin = $salario['fecha'] ? date('d/m/Y', strtotime($salario['fecha'])) : 'N/A';
                        ?>
                            <tr data-conductor="<?= $salario['conductor_id'] ?>">
                                <td><?= $salario['id'] ?></td>
                                <td><?= $fechaFin ?></td>
                                <td><?= htmlspecialchars($salario['conductor_nombre']) ?></td>
                                <td><?= number_format($salario['monto'], 0, ',', '.') ?> XAF</td>
                                <td><?= "$fechaInicio - $fechaFin" ?></td>
                                <td><?= htmlspecialchars($salario['descripcion']) ?></td>
                                <td>
                                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                        <button class="btn btn-ver" onclick="verSalario(<?= htmlspecialchars(json_encode($salario), ENT_QUOTES, 'UTF-8') ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </button>
                                        <button class="btn btn-ver" onclick="imprimirNomina(<?= $salario['id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                                <rect x="6" y="14" width="12" height="8"></rect>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Paginación -->
        <div class="pagination">
            <button class="btn" id="prevPage" disabled>Anterior</button>
            <span id="pageInfo" style="padding: 8px 15px;">Página 1</span>
            <button class="btn" id="nextPage">Siguiente</button>
        </div>
    </div>
    
    <!-- Botones flotantes -->
    <div class="action-buttons">
        <button class="action-button" id="btnScrollTop" title="Ir arriba">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 15l-6-6-6 6"></path>
            </svg>
        </button>
        <button class="action-button" id="btnAddNew" title="Nuevo registro" style="display: none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>
    </div>
    
    <!-- ============ MODALES ============ -->
    
    <!-- Modal Agregar Préstamo -->
    <div id="modalAgregarPrestamo" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalAgregarPrestamo')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalAgregarPrestamo')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Agregar Nuevo Préstamo</h3>
            </div>
            
            <div class="modal__body">
                <form method="post" class="modal__form" id="modalAgregarPrestamoForm">
                    <input type="hidden" name="accion" value="agregar_prestamo">
                    
                    <div class="modal__form-group">
                        <label for="prestamo_fecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="prestamo_fecha" class="modal__form-input" required>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="prestamo_conductor_id" class="modal__form-label">Conductor</label>
                        <select name="conductor_id" id="prestamo_conductor_id" class="modal__form-input" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($conductores as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal__form-row">
                        <div class="modal__form-group">
                            <label for="prestamo_monto" class="modal__form-label">Monto</label>
                            <input type="number" name="monto" id="prestamo_monto" class="modal__form-input" min="1" required>
                        </div>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="prestamo_descripcion" class="modal__form-label">Descripción</label>
                        <textarea name="descripcion" id="prestamo_descripcion" class="modal__form-input" rows="3"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalAgregarPrestamo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="modal__action-btn modal__action-btn--primary" form="modalAgregarPrestamoForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Guardar Préstamo
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Préstamo -->
    <div id="modalEditarPrestamo" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalEditarPrestamo')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalEditarPrestamo')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Editar Préstamo</h3>
            </div>
            
            <div class="modal__body">
                <form method="post" class="modal__form" id="modalEditarPrestamoForm">
                    <input type="hidden" name="accion" value="editar_prestamo">
                    <input type="hidden" name="id" id="editar_prestamo_id">
                    
                    <div class="modal__form-group">
                        <label for="editar_prestamo_fecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="editar_prestamo_fecha" class="modal__form-input" required>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="editar_prestamo_conductor_id" class="modal__form-label">Conductor</label>
                        <select name="conductor_id" id="editar_prestamo_conductor_id" class="modal__form-input" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($conductores as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal__form-row">
                        <div class="modal__form-group">
                            <label for="editar_prestamo_monto" class="modal__form-label">Monto</label>
                            <input type="number" name="monto" id="editar_prestamo_monto" class="modal__form-input" min="1" required>
                        </div>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="editar_prestamo_descripcion" class="modal__form-label">Descripción</label>
                        <textarea name="descripcion" id="editar_prestamo_descripcion" class="modal__form-input" rows="3"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalEditarPrestamo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="modal__action-btn modal__action-btn--primary" form="modalEditarPrestamoForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Guardar Cambios
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Pago de Préstamo -->
    <div id="modalPagoPrestamo" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalPagoPrestamo')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalPagoPrestamo')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Registrar Pago de Préstamo</h3>
                <h4 class="modal__title--highlight" id="prestamoConductorNombre"></h4>
            </div>
            
            <div class="modal__body">
                <form method="post" class="modal__form" id="formPagoPrestamo">
                    <input type="hidden" name="accion" value="agregar_pago_prestamo">
                    <input type="hidden" name="prestamo_id" id="pago_prestamo_id">
                    
                    <div class="modal__form-group">
                        <label for="pago_prestamo_fecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="pago_prestamo_fecha" class="modal__form-input" required>
                    </div>
                    
                    <div class="modal__form-row">
                        <div class="modal__form-group">
                            <label for="pago_prestamo_monto" class="modal__form-label">Monto</label>
                            <input type="number" name="monto" id="pago_prestamo_monto" class="modal__form-input" min="1" required>
                        </div>
                        <div class="modal__form-group">
                            <label for="pago_prestamo_saldo" class="modal__form-label">Saldo Pendiente</label>
                            <input type="number" id="pago_prestamo_saldo" class="modal__form-input" readonly>
                        </div>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="pago_prestamo_descripcion" class="modal__form-label">Descripción</label>
                        <textarea name="descripcion" id="pago_prestamo_descripcion" class="modal__form-input" rows="3"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalPagoPrestamo')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="modal__action-btn modal__action-btn--primary" form="formPagoPrestamo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Registrar Pago
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Préstamo -->
    <div id="modalVerPrestamo" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalVerPrestamo')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalVerPrestamo')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Detalles del Préstamo</h3>
                <h4 class="modal__title--highlight" id="verPrestamoConductorNombre"></h4>
            </div>
            
            <div class="modal__body">
                <div id="detallePrestamo" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
                
                <!-- Historial de pagos -->
                <div class="historial-container">
                    <h4 class="historial-title">Historial de Pagos</h4>
                    <div id="historialPagosPrestamo" class="historial-content">
                        <!-- Contenido dinámico aquí -->
                    </div>
                </div>
            </div>
            
            <div class="modal__footer">
                <button class="btn btn-nuevo" onclick="abrirModalPagoPrestamo(currentPrestamo.id, currentPrestamo.saldo_pendiente, currentPrestamo.conductor_nombre)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2v20M2 12h20"></path>
                    </svg>
                    Pagar
                </button>
                <button class="modal__action-btn modal__action-btn--primary" onclick="editarPrestamo()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Editar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Agregar Amonestación -->
    <div id="modalAgregarAmonestacion" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalAgregarAmonestacion')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalAgregarAmonestacion')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Agregar Nueva Amonestación</h3>
            </div>
            
            <div class="modal__body">
                <form method="post" class="modal__form" id="modalAgregarAmonestacionForm">
                    <input type="hidden" name="accion" value="agregar_amonestacion">
                    
                    <div class="modal__form-group">
                        <label for="amonestacion_fecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="amonestacion_fecha" class="modal__form-input" required>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="amonestacion_conductor_id" class="modal__form-label">Conductor</label>
                        <select name="conductor_id" id="amonestacion_conductor_id" class="modal__form-input" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($conductores as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal__form-row">
                        <div class="modal__form-group">
                            <label for="amonestacion_monto" class="modal__form-label">Monto</label>
                            <input type="number" name="monto" id="amonestacion_monto" class="modal__form-input" min="1" required>
                        </div>
                    </div>
                    
                    <div class="modal__form-group">
                        <label for="amonestacion_descripcion" class="modal__form-label">Descripción</label>
                        <textarea name="descripcion" id="amonestacion_descripcion" class="modal__form-input" rows="3"></textarea>
                    </div>
                </form>
            </div>
            
            <div class="modal__footer">
                <button type="button" class="modal__action-btn" onclick="cerrarModal('modalAgregarAmonestacion')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="modal__action-btn modal__action-btn--primary" form="modalAgregarAmonestacionForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Guardar Amonestación
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Pago de Amonestación -->
    <div id="modalPagoAmonestacion" class="modal">
        <div class="modal__overlay" onclick="cerrarModal('modalPagoAmonestacion')"></div>
        <div class="modal__container">
            <button class="modal__close" onclick="cerrarModal('modalPagoAmonestacion')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal__header">
                <h3 class="modal__title">Registrar Pago de Amonestación</h3>
                <h4 class="modal__title--highlight" id="amonestacionConductorNombre"></h4>
            </div>
            
            <div class="modal__body">
                <form method="post" class="modal__form" id="formPagoAmonestacion">
                    <input type="hidden" name="accion" value="agregar_pago_amonestacion">
                    <input type="hidden" name="amonestacion_id" id="pago_amonestacion_id">
                    
                    <div class="modal__form-group">
                        <label for="pago_amonestacion_fecha" class="modal__form-label">Fecha</label>
                        <input type="date" name="fecha" id="pago_amonestacion_fecha
                            </body>
                        <script>
// Variables globales para almacenar datos temporales
let currentPrestamo = {};
let currentAmonestacion = {};
let currentConductor = {};
let currentIngreso = {};

// Función para abrir modales
function abrirModal(id) {
    document.getElementById(id).style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// Función para cerrar modales
function cerrarModal(id) {
    document.getElementById(id).style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Función para ver detalles de un préstamo
function verPrestamo(prestamo) {
    currentPrestamo = prestamo;
    document.getElementById('verPrestamoConductorNombre').textContent = prestamo.conductor_nombre;
    
    // Mostrar detalles del préstamo
    const detalleHTML = `
        <table class="modal__data-table">
            <tr>
                <td class="modal__data-label">Fecha</td>
                <td class="modal__data-value">${prestamo.fecha}</td>
            </tr>
            <tr>
                <td class="modal__data-label">Monto</td>
                <td class="modal__data-value">${prestamo.monto.toLocaleString()} XAF</td>
            </tr>
            <tr>
                <td class="modal__data-label">Saldo Pendiente</td>
                <td class="modal__data-value">${prestamo.saldo_pendiente.toLocaleString()} XAF</td>
            </tr>
            <tr>
                <td class="modal__data-label">Estado</td>
                <td class="modal__data-value">
                    <span class="badge badge-${prestamo.estado}">${prestamo.estado.charAt(0).toUpperCase() + prestamo.estado.slice(1)}</span>
                </td>
            </tr>
            <tr>
                <td class="modal__data-label">Descripción</td>
                <td class="modal__data-value">${prestamo.descripcion || 'N/A'}</td>
            </tr>
        </table>
    `;
    document.getElementById('detallePrestamo').innerHTML = detalleHTML;
    
    // Cargar historial de pagos
    fetch(`../../api/get_pagos_prestamo.php?prestamo_id=${prestamo.id}`)
        .then(response => response.json())
        .then(data => {
            let historialHTML = '';
            if (data.length > 0) {
                data.forEach(pago => {
                    historialHTML += `
                        <div class="historial-item">
                            <div class="historial-item-info">
                                <strong>${pago.fecha}</strong> - ${pago.monto.toLocaleString()} XAF
                                <div>${pago.descripcion || ''}</div>
                            </div>
                        </div>
                    `;
                });
            } else {
                historialHTML = '<p>No hay pagos registrados</p>';
            }
            document.getElementById('historialPagosPrestamo').innerHTML = historialHTML;
        });
    
    abrirModal('modalVerPrestamo');
}

// Función para editar un préstamo
function editarPrestamo(id = null) {
    if (id) {
        fetch(`../../api/get_prestamo.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('editar_prestamo_id').value = data.id;
                document.getElementById('editar_prestamo_fecha').value = data.fecha;
                document.getElementById('editar_prestamo_conductor_id').value = data.conductor_id;
                document.getElementById('editar_prestamo_monto').value = data.monto;
                document.getElementById('editar_prestamo_descripcion').value = data.descripcion || '';
                
                abrirModal('modalEditarPrestamo');
            });
    } else {
        abrirModal('modalEditarPrestamo');
    }
}

// Función para abrir modal de pago de préstamo
function abrirModalPagoPrestamo(prestamoId, saldoPendiente, conductorNombre) {
    document.getElementById('pago_prestamo_id').value = prestamoId;
    document.getElementById('pago_prestamo_saldo').value = saldoPendiente;
    document.getElementById('pago_prestamo_monto').max = saldoPendiente;
    document.getElementById('pago_prestamo_monto').value = saldoPendiente;
    document.getElementById('prestamoConductorNombre').textContent = conductorNombre;
    document.getElementById('pago_prestamo_fecha').valueAsDate = new Date();
    
    abrirModal('modalPagoPrestamo');
}

// Función para ver detalles de una amonestación
function verAmonestacion(amonestacion) {
    currentAmonestacion = amonestacion;
    document.getElementById('verAmonestacionConductorNombre').textContent = amonestacion.conductor_nombre;
    
    // Mostrar detalles de la amonestación
    const detalleHTML = `
        <table class="modal__data-table">
            <tr>
                <td class="modal__data-label">Fecha</td>
                <td class="modal__data-value">${amonestacion.fecha}</td>
            </tr>
            <tr>
                <td class="modal__data-label">Monto</td>
                <td class="modal__data-value">${amonestacion.monto.toLocaleString()} XAF</td>
            </tr>
            <tr>
                <td class="modal__data-label">Estado</td>
                <td class="modal__data-value">
                    <span class="badge badge-${amonestacion.estado}">${amonestacion.estado.charAt(0).toUpperCase() + amonestacion.estado.slice(1)}</span>
                </td>
            </tr>
            <tr>
                <td class="modal__data-label">Descripción</td>
                <td class="modal__data-value">${amonestacion.descripcion || 'N/A'}</td>
            </tr>
        </table>
    `;
    document.getElementById('detalleAmonestacion').innerHTML = detalleHTML;
    
    // Cargar historial de pagos
    fetch(`../../api/get_pagos_amonestacion.php?amonestacion_id=${amonestacion.id}`)
        .then(response => response.json())
        .then(data => {
            let historialHTML = '';
            if (data.length > 0) {
                data.forEach(pago => {
                    historialHTML += `
                        <div class="historial-item">
                            <div class="historial-item-info">
                                <strong>${pago.fecha}</strong> - ${pago.monto.toLocaleString()} XAF
                                <div>${pago.descripcion || ''}</div>
                            </div>
                        </div>
                    `;
                });
            } else {
                historialHTML = '<p>No hay pagos registrados</p>';
            }
            document.getElementById('historialPagosAmonestacion').innerHTML = historialHTML;
        });
    
    abrirModal('modalVerAmonestacion');
}

// Función para cambiar estado de amonestación
function cambiarEstadoAmonestacion(estado) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '';
    
    const inputAccion = document.createElement('input');
    inputAccion.type = 'hidden';
    inputAccion.name = 'accion';
    inputAccion.value = 'cambiar_estado_amonestacion';
    
    const inputId = document.createElement('input');
    inputId.type = 'hidden';
    inputId.name = 'id';
    inputId.value = currentAmonestacion.id;
    
    const inputEstado = document.createElement('input');
    inputEstado.type = 'hidden';
    inputEstado.name = 'estado';
    inputEstado.value = estado;
    
    form.appendChild(inputAccion);
    form.appendChild(inputId);
    form.appendChild(inputEstado);
    
    document.body.appendChild(form);
    form.submit();
}

// Función para abrir modal de pago de amonestación
function abrirModalPagoAmonestacion(amonestacionId, monto, conductorNombre) {
    document.getElementById('pago_amonestacion_id').value = amonestacionId;
    document.getElementById('pago_amonestacion_monto').value = monto;
    document.getElementById('amonestacionConductorNombre').textContent = conductorNombre;
    document.getElementById('pago_amonestacion_fecha').valueAsDate = new Date();
    
    abrirModal('modalPagoAmonestacion');
}

// Función para ver detalles de un conductor
function verConductor(conductor) {
    currentConductor = conductor;
    document.getElementById('verConductorNombre').textContent = conductor.nombre;
    
    // Mostrar detalles del conductor
    const detalleHTML = `
        <table class="modal__data-table">
            <tr>
                <td class="modal__data-label">Vehículo</td>
                <td class="modal__data-value">${conductor.vehiculo_placa || 'N/A'}</td>
            </tr>
            <tr>
                <td class="modal__data-label">Salario Mensual</td>
                <td class="modal__data-value">${conductor.salario_mensual.toLocaleString()} XAF</td>
            </tr>
            <tr>
                <td class="modal__data-label">Días por ciclo</td>
                <td class="modal__data-value">${conductor.dias_por_ciclo}</td>
            </tr>
            <tr>
                <td class="modal__data-label">Días trabajados</td>
                <td class="modal__data-value">${conductor.dias_trabajados_ultimo_ciclo}</td>
            </tr>
            <tr>
                <td class="modal__data-label">Deuda total</td>
                <td class="modal__data-value">${(conductor.total_pendiente + conductor.total_prestamos + conductor.total_amonestaciones).toLocaleString()} XAF</td>
            </tr>
        </table>
    `;
    document.getElementById('detalleConductor').innerHTML = detalleHTML;
    
    // Cargar historial de salarios
    fetch(`../../api/get_salarios_conductor.php?conductor_id=${conductor.id}`)
        .then(response => response.json())
        .then(data => {
            let historialHTML = '';
            if (data.length > 0) {
                data.forEach(salario => {
                    historialHTML += `
                        <div class="historial-item">
                            <div class="historial-item-info">
                                <strong>${salario.fecha}</strong> - ${salario.monto.toLocaleString()} XAF
                                <div>${salario.descripcion || ''}</div>
                            </div>
                            <div class="historial-item-acciones">
                                <button class="btn btn-ver" onclick="verSalario(${JSON.stringify(salario)})">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                                <button class="btn btn-ver" onclick="imprimirNomina(${salario.id})">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <rect x="6" y="14" width="12" height="8"></rect>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    `;
                });
            } else {
                historialHTML = '<p>No hay salarios registrados</p>';
            }
            document.getElementById('historialSalariosConductor').innerHTML = historialHTML;
        });
    
    abrirModal('modalVerConductor');
}

// Función para abrir modal de pago de salario
function abrirModalPagoSalario(conductorId, montoSalario, conductorNombre) {
    currentConductor = { id: conductorId, nombre: conductorNombre };
    document.getElementById('pago_salario_conductor_id').value = conductorId;
    document.getElementById('pago_salario_monto').value = montoSalario;
    document.getElementById('salarioConductorNombre').textContent = conductorNombre;
    document.getElementById('pago_salario_fecha').valueAsDate = new Date();
    
    // Cargar deudas del conductor
    fetch(`../../api/get_deudas_conductor.php?conductor_id=${conductorId}`)
        .then(response => response.json())
        .then(data => {
            let deudasHTML = '';
            
            // Amonestaciones
            if (data.amonestaciones && data.amonestaciones.length > 0) {
                deudasHTML += '<h4>Amonestaciones Pendientes</h4>';
                data.amonestaciones.forEach(amonestacion => {
                    deudasHTML += `
                        <div class="deuda-item">
                            <div class="deuda-header">
                                <span class="deuda-title">Amonestación #${amonestacion.id}</span>
                                <span class="deuda-monto">${amonestacion.monto.toLocaleString()} XAF</span>
                            </div>
                            <div class="deuda-input-group">
                                <label>Cobrar:</label>
                                <input type="number" name="amonestaciones[${amonestacion.id}]" min="0" max="${amonestacion.monto}" value="${amonestacion.monto}" class="modal__form-input">
                            </div>
                        </div>
                    `;
                });
            }
            
            // Ingresos pendientes
            if (data.ingresos && data.ingresos.length > 0) {
                deudasHTML += '<h4>Ingresos Pendientes</h4>';
                data.ingresos.forEach(ingreso => {
                    deudasHTML += `
                        <div class="deuda-item">
                            <div class="deuda-header">
                                <span class="deuda-title">Ingreso #${ingreso.id} (${ingreso.fecha})</span>
                                <span class="deuda-monto">${ingreso.monto_pendiente.toLocaleString()} XAF</span>
                            </div>
                            <div class="deuda-input-group">
                                <label>Cobrar:</label>
                                <input type="number" name="ingresos[${ingreso.id}]" min="0" max="${ingreso.monto_pendiente}" value="${ingreso.monto_pendiente}" class="modal__form-input">
                            </div>
                        </div>
                    `;
                });
            }
            
            // Préstamos
            if (data.prestamos && data.prestamos.length > 0) {
                deudasHTML += '<h4>Préstamos Pendientes</h4>';
                data.prestamos.forEach(prestamo => {
                    deudasHTML += `
                        <div class="deuda-item">
                            <div class="deuda-header">
                                <span class="deuda-title">Préstamo #${prestamo.id}</span>
                                <span class="deuda-monto">${prestamo.saldo_pendiente.toLocaleString()} XAF</span>
                            </div>
                            <div class="deuda-input-group">
                                <label>Cobrar:</label>
                                <input type="number" name="prestamos[${prestamo.id}]" min="0" max="${prestamo.saldo_pendiente}" value="${prestamo.saldo_pendiente}" class="modal__form-input">
                            </div>
                        </div>
                    `;
                });
            }
            
            document.getElementById('deudasConductor').innerHTML = deudasHTML || '<p>No hay deudas pendientes</p>';
        });
    
    abrirModal('modalPagoSalario');
}

// Función para ver detalles de un salario
function verSalario(salario) {
    document.getElementById('verSalarioConductorNombre').textContent = salario.conductor_nombre;
    
    // Mostrar detalles del salario
    const detalleHTML = `
        <table class="modal__data-table">
            <tr>
                <td class="modal__data-label">Fecha</td>
                <td class="modal__data-value">${salario.fecha}</td>
            </tr>
            <tr>
                <td class="modal__data-label">Monto</td>
                <td class="modal__data-value">${salario.monto.toLocaleString()} XAF</td>
            </tr>
            <tr>
                <td class="modal__data-label">Descripción</td>
                <td class="modal__data-value">${salario.descripcion || 'N/A'}</td>
            </tr>
        </table>
    `;
    document.getElementById('detalleSalario').innerHTML = detalleHTML;
    
    abrirModal('modalVerSalario');
}

// Función para imprimir nómina
function imprimirNomina(salarioId) {
    window.open(`../../reportes/nomina.php?id=${salarioId}`, '_blank');
}

// Función para ver detalles de un ingreso pendiente
function verIngreso(ingreso) {
    currentIngreso = ingreso;
    document.getElementById('verIngresoConductorNombre').textContent = ingreso.conductor_nombre;
    
    // Mostrar detalles del ingreso
    const detalleHTML = `
        <table class="modal__data-table">
            <tr>
                <td class="modal__data-label">Fecha</td>
                <td class="modal__data-value">${ingreso.fecha}</td>
            </tr>
            <tr>
                <td class="modal__data-label">Monto Esperado</td>
                <td class="modal__data-value">${ingreso.monto_esperado.toLocaleString()} XAF</td>
            </tr>
            <tr>
                <td class="modal__data-label">Monto Ingresado</td>
                <td class="modal__data-value">${ingreso.monto_ingresado.toLocaleString()} XAF</td>
            </tr>
            <tr>
                <td class="modal__data-label">Pendiente</td>
                <td class="modal__data-value">${ingreso.monto_pendiente.toLocaleString()} XAF</td>
            </tr>
        </table>
    `;
    document.getElementById('detalleIngreso').innerHTML = detalleHTML;
    
    abrirModal('modalVerIngreso');
}

// Función para abrir modal de pago de ingreso pendiente
function abrirModalPagoIngreso(conductorId, montoPendiente, conductorNombre) {
    document.getElementById('pago_ingreso_conductor_id').value = conductorId;
    document.getElementById('pago_ingreso_monto').value = montoPendiente;
    document.getElementById('ingresoConductorNombre').textContent = conductorNombre;
    document.getElementById('pago_ingreso_fecha').valueAsDate = new Date();
    
    abrirModal('modalPagoIngreso');
}

// Manejar cambio de pestañas
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        // Ocultar todas las pestañas y contenidos
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Activar la pestaña seleccionada
        tab.classList.add('active');
        const tabId = tab.getAttribute('data-tab');
        document.getElementById(`${tabId}-content`).classList.add('active');
        
        // Mostrar/ocultar controles según la pestaña
        const filterEstado = document.getElementById('filterEstado');
        const openModalNuevo = document.getElementById('openModalNuevo');
        const btnAddNew = document.getElementById('btnAddNew');
        
        if (tabId === 'prestamos') {
            filterEstado.style.display = 'block';
            openModalNuevo.style.display = 'block';
            openModalNuevo.textContent = 'Nuevo Préstamo';
            openModalNuevo.onclick = () => abrirModal('modalAgregarPrestamo');
            btnAddNew.style.display = 'block';
            btnAddNew.onclick = () => abrirModal('modalAgregarPrestamo');
        } else if (tabId === 'amonestaciones') {
            filterEstado.style.display = 'block';
            openModalNuevo.style.display = 'block';
            openModalNuevo.textContent = 'Nueva Amonestación';
            openModalNuevo.onclick = () => abrirModal('modalAgregarAmonestacion');
            btnAddNew.style.display = 'block';
            btnAddNew.onclick = () => abrirModal('modalAgregarAmonestacion');
        } else {
            filterEstado.style.display = 'none';
            openModalNuevo.style.display = 'none';
            btnAddNew.style.display = 'none';
        }
    });
});

// Configurar fecha actual en los formularios
document.querySelectorAll('input[type="date"]').forEach(input => {
    if (!input.value) {
        input.valueAsDate = new Date();
    }
});

// Botón para ir arriba
document.getElementById('btnScrollTop').addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// Mostrar botón de ir arriba cuando se hace scroll
window.addEventListener('scroll', () => {
    const btnScrollTop = document.getElementById('btnScrollTop');
    if (window.pageYOffset > 300) {
        btnScrollTop.style.display = 'flex';
    } else {
        btnScrollTop.style.display = 'none';
    }
});

// Filtrado de tablas
document.getElementById('searchInput').addEventListener('input', function() {
    const searchValue = this.value.toLowerCase();
    const activeTable = document.querySelector('.tab-content.active table tbody');
    
    if (activeTable) {
        const rows = activeTable.querySelectorAll('tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchValue) ? '' : 'none';
        });
    }
});

document.getElementById('filterConductor').addEventListener('change', function() {
    const conductorId = this.value;
    const activeTable = document.querySelector('.tab-content.active table tbody');
    
    if (activeTable) {
        const rows = activeTable.querySelectorAll('tr');
        rows.forEach(row => {
            const rowConductor = row.getAttribute('data-conductor');
            row.style.display = (!conductorId || rowConductor === conductorId) ? '' : 'none';
        });
    }
});

document.getElementById('filterEstado').addEventListener('change', function() {
    const estado = this.value;
    const activeTable = document.querySelector('.tab-content.active table tbody');
    
    if (activeTable) {
        const rows = activeTable.querySelectorAll('tr');
        rows.forEach(row => {
            const rowEstado = row.getAttribute('data-estado');
            row.style.display = (!estado || rowEstado === estado) ? '' : 'none';
        });
    }
});
</script>
</body>
</html>