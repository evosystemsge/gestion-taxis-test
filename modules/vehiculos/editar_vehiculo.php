<?php
include '../../layout/header.php';
require '../../config/database.php'; // Usamos la conexión PDO

// Verificar si el ID está presente en la URL
if (!isset($_GET['id'])) {
    echo "ID de vehículo no proporcionado.";
    exit;
}

$vehiculo_id = $_GET['id'];

// Obtener detalles del vehículo
$stmt = $pdo->prepare("SELECT * FROM vehiculos WHERE id = ?");
$stmt->execute([$vehiculo_id]);
$vehiculo = $stmt->fetch();

if (!$vehiculo) {
    echo "Vehículo no encontrado.";
    exit;
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $marca = $_POST['marca'];
    $modelo = $_POST['modelo'];
    $matricula = $_POST['matricula'];
    $numero = $_POST['numero'];

    // Validación simple
    if (empty($marca) || empty($modelo) || empty($matricula) || empty($numero)) {
        echo "Todos los campos son obligatorios.";
    } else {
        // Actualizar los detalles del vehículo en la base de datos
        $stmt = $pdo->prepare("UPDATE vehiculos SET marca = ?, modelo = ?, matricula = ?, numero = ? WHERE id = ?");
        $stmt->execute([$marca, $modelo, $matricula, $numero, $vehiculo_id]);

        // Redirigir con un mensaje de confirmación
        echo "<script>
                alert('Vehículo actualizado con éxito.');
                window.location.href = 'vehiculos.php';
              </script>";
        exit;
    }
}
?>

<style>
/* Estilos para el formulario de edición */
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
form {
    display: grid;
    gap: 15px;
}
label {
    font-weight: bold;
}
input[type="text"] {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
}
button {
    padding: 10px 20px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
button:hover {
    background: #0056b3;
}
</style>

<div class="container">
    <h2>Editar Vehículo</h2>
    <form method="POST" action="">
        <label for="marca">Marca</label>
        <input type="text" id="marca" name="marca" value="<?= htmlspecialchars($vehiculo['marca']) ?>" required>

        <label for="modelo">Modelo</label>
        <input type="text" id="modelo" name="modelo" value="<?= htmlspecialchars($vehiculo['modelo']) ?>" required>

        <label for="matricula">Matrícula</label>
        <input type="text" id="matricula" name="matricula" value="<?= htmlspecialchars($vehiculo['matricula']) ?>" required>

        <label for="numero">Número</label>
        <input type="text" id="numero" name="numero" value="<?= htmlspecialchars($vehiculo['numero']) ?>" required>

        <button type="submit">Actualizar Vehículo</button>
    </form>

    <div style="text-align: center; margin-top: 20px;">
        <a href="vehiculos.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            Volver a la lista de vehículos
        </a>
    </div>
</div>

<?php include '../../layout/footer.php'; ?>
