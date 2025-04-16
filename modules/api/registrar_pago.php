<?php
require '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// Validaciones
if (empty($data['conductor_id']) || empty($data['fecha']) || empty($data['monto']) || 
    empty($data['fecha_inicio']) || empty($data['fecha_fin'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Formatear el ciclo como "dd/mm/yyyy - dd/mm/yyyy"
    $fechaInicio = DateTime::createFromFormat('Y-m-d', $data['fecha_inicio']);
    $fechaFin = DateTime::createFromFormat('Y-m-d', $data['fecha_fin']);
    
    if (!$fechaInicio || !$fechaFin) {
        throw new Exception("Formato de fecha inválido");
    }
    
    $ciclo = $fechaInicio->format('d/m/Y') . ' - ' . $fechaFin->format('d/m/Y');
    
    // 1. Registrar el pago
    $stmt = $pdo->prepare("INSERT INTO pagos_salarios 
                          (conductor_id, fecha, fecha_inicio, fecha_fin, monto, ciclo, observaciones)
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['conductor_id'],
        $data['fecha'],
        $data['fecha_inicio'],
        $data['fecha_fin'],
        $data['monto'],
        $ciclo,
        $data['observaciones'] ?? null
    ]);
    
    // 2. Actualizar la caja
    $stmt = $pdo->prepare("UPDATE cajas SET saldo_actual = saldo_actual - ? WHERE id = 1");
    $stmt->execute([$data['monto']]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}