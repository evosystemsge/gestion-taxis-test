<?php
require '../../config/database.php';
header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;
$prestamo = $pdo->query("SELECT p.*, c.nombre as conductor 
                        FROM prestamos p
                        JOIN conductores c ON p.conductor_id = c.id
                        WHERE p.id = $id")->fetch();

echo json_encode($prestamo ?: ['error' => 'Préstamo no encontrado']);
?>