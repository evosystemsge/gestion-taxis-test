<?php
include '../../layout/header.php';
require '../../config/database.php'; // Usamos la conexión PDO

// Verificar si el ID está presente en la URL
if (!isset($_GET['id'])) {
    echo "ID de conductor no proporcionado.";
    exit;
}

$conductor_id = $_GET['id'];

// Obtener detalles del conductor
$stmt = $pdo->prepare("SELECT c.*, v.marca, v.modelo, v.matricula, v.numero, v.id AS vehiculo_id
                       FROM conductores c 
                       LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
                       WHERE c.id = ?");
$stmt->execute([$conductor_id]);
$conductor = $stmt->fetch();

if (!$conductor) {
    echo "Conductor no encontrado.";
    exit;
}

// Procesar el formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $dip = $_POST['dip'];
    
    // Verificar si se seleccionó un vehículo
    $vehiculo_id = $_POST['vehiculo_id'];
    if (empty($vehiculo_id)) {
        $vehiculo_id = null; // Si no se selecciona vehículo, asignar NULL
    }

    // Validación simple
    if (empty($nombre) || empty($telefono) || empty($dip)) {
        echo "Todos los campos son obligatorios.";
    } else {
        // Actualizar los detalles del conductor en la base de datos
        $stmt = $pdo->prepare("UPDATE conductores SET nombre = ?, telefono = ?, dip = ?, vehiculo_id = ? WHERE id = ?");
        $stmt->execute([$nombre, $telefono, $dip, $vehiculo_id, $conductor_id]);

        // Redirigir con un mensaje de confirmación
        echo "<script>
                alert('Conductor actualizado con éxito.');
                window.location.href = 'conductores.php';
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
input[type="text"], select {
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
    <h2>Editar Conductor</h2>
    <form method="POST" action="">
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($conductor['nombre']) ?>" required>

        <label for="telefono">Teléfono</label>
        <input type="text" id="telefono" name="telefono" value="<?= htmlspecialchars($conductor['telefono']) ?>" required>

        <label for="dip">DIP</label>
        <input type="text" id="dip" name="dip" value="<?= htmlspecialchars($conductor['dip']) ?>" required>

        <label for="vehiculo_id">Vehículo Asignado</label>
        <select id="vehiculo_id" name="vehiculo_id">
            <option value="">Seleccionar Vehículo</option>
            <?php
            // Obtener lista de vehículos para asignar uno
            $stmt = $pdo->query("SELECT id, marca, modelo, matricula, numero FROM vehiculos");
            $vehiculos = $stmt->fetchAll();

            foreach ($vehiculos as $vehiculo) {
                echo '<option value="' . $vehiculo['id'] . '"';
                if ($vehiculo['id'] == $conductor['vehiculo_id']) {
                    echo ' selected';
                }
                echo '>' . $vehiculo['marca'] . ' ' . $vehiculo['modelo'] . ' - ' . $vehiculo['matricula'] . ' - ' . $vehiculo['numero'] . '</option>';
            }
            ?>
        </select>

        <button type="submit">Actualizar Conductor</button>
    </form>

    <div style="text-align: center; margin-top: 20px;">
        <a href="conductores.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            Volver a la lista de conductores
        </a>
    </div>
</div>

<?php include '../../layout/footer.php'; ?>
