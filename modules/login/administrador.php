<?php
require '../../config/database.php';

$nombre = 'Administrador';
$email = 'admin@taxis.com';
$password = password_hash('Admin123', PASSWORD_DEFAULT);
$rol = 'admin';

$stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
$stmt->execute([$nombre, $email, $password, $rol]);

echo "Usuario administrador creado con éxito.";
?>