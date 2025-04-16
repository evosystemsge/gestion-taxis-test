<?php
require '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_GET['conductor_id'])) {
    echo json_encode(['error' => 'ID de conductor no proporcionado']);
    exit;
}

$conductor_id = intval($_GET['conductor_id']);

try {
    $stmt = $pdo->prepare("
        SELECT fecha, fecha_inicio, fecha_fin, monto, ciclo, observaciones 
        FROM pagos_salarios 
        WHERE conductor_id = ? 
        ORDER BY fecha DESC
        LIMIT 50
    ");
    $stmt->execute([$conductor_id]);
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear los datos para la vista
    $resultado = array_map(function($pago) {
        return [
            'fecha' => $pago['fecha'],
            'periodo' => $pago['ciclo'], // Ya está formateado como "dd/mm/yyyy - dd/mm/yyyy"
            'monto' => $pago['monto'],
            'observaciones' => $pago['observaciones']
        ];
    }, $pagos);
    
    echo json_encode($resultado);
    
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}