<?php
require '../../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['mantenimiento_id'])) {
    $mantenimientoId = $_GET['mantenimiento_id'];
    
    $stmt = $pdo->prepare("
        SELECT mp.*, p.nombre 
        FROM mantenimiento_productos mp
        JOIN productos p ON mp.producto_id = p.id
        WHERE mp.mantenimiento_id = ?
    ");
    $stmt->execute([$mantenimientoId]);
    $productos = $stmt->fetchAll();
    
    echo json_encode($productos);
} else {
    echo json_encode([]);
}
?>