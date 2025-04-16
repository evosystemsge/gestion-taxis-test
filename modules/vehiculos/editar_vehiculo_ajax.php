<?php
require '../../config/database.php';

$id = $_POST['id'];
$marca = $_POST['marca'];
$modelo = $_POST['modelo'];
$matricula = $_POST['matricula'];
$numero = $_POST['numero'];

$sql = "UPDATE vehiculos SET marca = ?, modelo = ?, matricula = ?, numero = ? WHERE id = ?";
$stmt = $pdo->prepare($sql);
if ($stmt->execute([$marca, $modelo, $matricula, $numero, $id])) {
    echo "Vehículo actualizado correctamente";
} else {
    echo "Error al actualizar vehículo";
}
?>
