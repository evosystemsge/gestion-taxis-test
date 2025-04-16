<?php
require '../../config/database.php';

// Obtener la caja predeterminada
$caja_predeterminada = $pdo->query("SELECT id FROM cajas WHERE predeterminada = 1 LIMIT 1")->fetch();
if (!$caja_predeterminada) {
    die(json_encode(['success' => false, 'error' => 'No hay caja predeterminada configurada']));
}
$caja_id = $caja_predeterminada['id'];

$conductor_id = $_POST['conductor_id'] ?? 0;
$ingreso_id = $_POST['ingreso_id'] ?? 0;
$fecha = $_POST['fecha'] ?? '';
$monto = $_POST['monto'] ?? 0;
$descripcion = $_POST['descripcion'] ?? '';

if ($conductor_id <= 0 || $ingreso_id <= 0 || empty($fecha) || $monto <= 0) {
    die(json_encode(['success' => false, 'error' => 'Datos incompletos o inválidos']));
}

$pdo->beginTransaction();
try {
    // 1. Actualizar el ingreso pendiente
    $stmt = $pdo->prepare("
        UPDATE ingresos 
        SET monto_ingresado = monto_ingresado + ?, 
            monto_pendiente = monto_pendiente - ?
        WHERE id = ? AND conductor_id = ?
    ");
    $stmt->execute([$monto, $monto, $ingreso_id, $conductor_id]);
    
    // 2. Registrar movimiento en caja
    $stmt = $pdo->prepare("
        INSERT INTO movimientos_caja (
            caja_id, 
            tipo, 
            monto, 
            descripcion, 
            fecha,
            ingreso_id
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $caja_id,
        'ingreso',
        $monto,
        "Pago ingreso pendiente ID: $ingreso_id - " . substr($descripcion, 0, 245),
        $fecha,
        $ingreso_id
    ]);
    
    // 3. Actualizar saldo de la caja predeterminada
    $stmt = $pdo->prepare("
        UPDATE cajas 
        SET saldo_actual = saldo_actual + ?
        WHERE id = ?
    ");
    $stmt->execute([$monto, $caja_id]);
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}