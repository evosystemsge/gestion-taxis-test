<?php
include '../../layout/header.php';
require '../../config/database.php'; // Usamos la conexión PDO

// Verificar si el ID está presente en la URL
if (!isset($_GET['id'])) {
    echo "ID de conductor no proporcionado.";
    exit;
}

$conductor_id = $_GET['id'];

// Obtener detalles del conductor y del vehículo asignado
$stmt = $pdo->prepare("SELECT c.*, v.marca AS vehiculo_marca, v.modelo AS vehiculo_modelo, v.matricula AS vehiculo_matricula
                       FROM conductores c 
                       LEFT JOIN vehiculos v ON c.vehiculo_id = v.id 
                       WHERE c.id = ?");
$stmt->execute([$conductor_id]);
$conductor = $stmt->fetch();

if (!$conductor) {
    echo "Conductor no encontrado.";
    exit;
}
?>

<style>
/* Estilos para el detalle del conductor */
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
    <h2>Detalles del Conductor</h2>
    <table class="detail-table">
        <tr>
            <th>ID</th>
            <td><?= $conductor['id'] ?></td>
        </tr>
        <tr>
            <th>Nombre</th>
            <td><?= $conductor['nombre'] ?></td>
        </tr>
        <tr>
            <th>Teléfono</th>
            <td><?= $conductor['telefono'] ?></td>
        </tr>
        <tr>
            <th>DIP</th>
            <td><?= $conductor['dip'] ?></td>
        </tr>
        <tr>
            <th>Vehículo Asignado</th>
            <td>
                <?php 
                    if ($conductor['vehiculo_marca']) {
                        echo $conductor['vehiculo_marca'] . " " . $conductor['vehiculo_modelo'] . " - " . $conductor['vehiculo_matricula'];
                    } else {
                        echo "No asignado";
                    }
                ?>
            </td>
        </tr>
    </table>

    <div style="text-align: center; margin-top: 20px;">
        <a href="conductores.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            Volver a la lista de conductores
        </a>
    </div>
</div>

<?php include '../../layout/footer.php'; ?>
