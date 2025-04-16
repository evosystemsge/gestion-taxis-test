<?php
include '../../layout/header.php';
require '../../config/database.php';

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
        $id = $_POST['id'];
        $fecha = $_POST['fecha'];
        $conductor_id = $_POST['conductor_id'];
        $monto = $_POST['monto'];
        $descripcion = $_POST['descripcion'];
        
        $pdo->beginTransaction();
        try {
            // Obtener el préstamo actual para calcular la diferencia
            $stmt = $pdo->prepare("SELECT monto FROM prestamos WHERE id = ?");
            $stmt->execute([$id]);
            $prestamo_actual = $stmt->fetch();
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

    // Resto de las acciones (pagos, amonestaciones, etc.) permanecen igual...

    /////////////////////////////////////////////////////
    /////////////////////////////////////////////////////

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
            VALUES (?, ?, ?, ?, 'activa')
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
        $id = $_POST['id'];
        
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
// Obtener conductores para Listar y pagar salarios
// En la sección donde obtienes los datos, añade esta consulta:
// Primero obtener todos los conductores básicos
$conductores = $pdo->query("SELECT id, nombre, salario_mensual, dias_por_ciclo FROM conductores ORDER BY nombre")->fetchAll();

// Luego para cada conductor, obtener los datos adicionales
$conductoresCompletos = [];
foreach ($conductores as $conductor) {
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
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto_pendiente), 0) FROM ingresos WHERE conductor_id = ? AND monto_pendiente > 0");
    $stmt->execute([$conductorId]);
    $totalPendiente = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(saldo_pendiente), 0) FROM prestamos WHERE conductor_id = ? AND estado = 'pendiente'");
    $stmt->execute([$conductorId]);
    $totalPrestamos = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM amonestaciones WHERE conductor_id = ? AND estado = 'activa'");
    $stmt->execute([$conductorId]);
    $totalAmonestaciones = $stmt->fetchColumn();
    
    $conductoresCompletos[] = [
        'id' => $conductor['id'],
        'nombre' => $conductor['nombre'],
        'salario_mensual' => $conductor['salario_mensual'],
        'dias_por_ciclo' => $conductor['dias_por_ciclo'],
        'ciclos_completados' => $ciclosCompletados,
        'dias_trabajados_ultimo_ciclo' => $diasTrabajados,
        'total_pendiente' => $totalPendiente,
        'total_prestamos' => $totalPrestamos,
        'total_amonestaciones' => $totalAmonestaciones
    ];
}

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
    
    ORDER BY fecha DESC
")->fetchAll();
    
// Obtener datos para mostrar en las tablas (conductores, préstamos, amonestaciones, etc.)
// ... (el resto del código PHP permanece igual)
?>