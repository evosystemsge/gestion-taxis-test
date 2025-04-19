<?php
require '../../config/database.php';
header('Content-Type: application/json');

$accion = $_POST['accion'] ?? '';

// 1. REGISTRAR PAGO
if ($accion == 'pagar' && !empty($_POST['id'])) {
    $id = $_POST['id'];
    
    try {
        // Obtener préstamo
        $prestamo = $pdo->query("SELECT * FROM prestamos WHERE id = $id")->fetch();
        
        if (!$prestamo) {
            throw new Exception("Préstamo no existe");
        }

        // Registrar pago (simplificado)
        $pdo->exec("UPDATE prestamos SET saldo_pendiente = 0 WHERE id = $id");
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
else {
    echo json_encode(['error' => 'Acción no válida']);
}
?>