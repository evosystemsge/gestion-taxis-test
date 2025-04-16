<?php
include '../../layout/header.php';
require '../../config/database.php'; // Usamos la conexión PDO

// Verificar si el ID está presente en la URL
if (!isset($_GET['id'])) {
    echo "ID de vehículo no proporcionado.";
    exit;
}

$vehiculo_id = $_GET['id'];

// Obtener detalles del vehículo y del conductor asignado
$stmt = $pdo->prepare("SELECT v.*, c.nombre AS conductor_nombre, c.telefono AS conductor_telefono, c.dip AS conductor_dip
                       FROM vehiculos v 
                       LEFT JOIN conductores c ON v.id = c.vehiculo_id 
                       WHERE v.id = ?");
$stmt->execute([$vehiculo_id]);
$vehiculo = $stmt->fetch();

if (!$vehiculo) {
    echo "Vehículo no encontrado.";
    exit;
}
?>

<style>
/* Estilos para el detalle del vehículo */
.container {
    max-width: 900px;
    margin: 20px auto;
    padding: 20px;
    background: white;
    border-radius: 5px;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
}
h2 {
    text-align: center;
    color: #004b87;
    margin-bottom: 20px;
}
.detail-table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    padding: 12px;
    text-align: left;
    border: 1px solid #ddd;
}
th {
    background-color: #004b87;
    color: white;
}
</style>

<div class="container">
    <h2>Detalles del Vehículo</h2>
    <table class="detail-table">
        <tr>
            <th>ID</th>
            <td><?= $vehiculo['id'] ?></td>
        </tr>
        <tr>
            <th>Marca</th>
            <td><?= $vehiculo['marca'] ?></td>
        </tr>
        <tr>
            <th>Modelo</th>
            <td><?= $vehiculo['modelo'] ?></td>
        </tr>
        <tr>
            <th>Matricula</th>
            <td><?= $vehiculo['matricula'] ?></td>
        </tr>
        <tr>
            <th>Número</th>
            <td><?= $vehiculo['numero'] ?></td>
        </tr>
        <tr>
            <th>Conductor Asignado</th>
            <td>
                <?php 
                    if ($vehiculo['conductor_nombre']) {
                        echo $vehiculo['conductor_nombre'];
                    } else {
                        echo "No asignado";
                    }
                ?>
            </td>
        </tr>
        <?php if ($vehiculo['conductor_nombre']) { ?>
        <tr>
            <th>Teléfono del Conductor</th>
            <td><?= $vehiculo['conductor_telefono'] ?></td>
        </tr>
        <tr>
            <th>DIP del Conductor</th>
            <td><?= $vehiculo['conductor_dip'] ?></td>
        </tr>
        <?php } ?>
    </table>

    <div style="text-align: center; margin-top: 20px;">
        <a href="vehiculos.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            Volver a la lista de vehículos
        </a>
    </div>
</div>

<?php include '../../layout/footer.php'; ?>
