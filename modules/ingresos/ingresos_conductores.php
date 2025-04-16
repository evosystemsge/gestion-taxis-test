
<?php
include("conexion.php");

// Agregar ingreso
if (isset($_POST['agregar'])) {
    $conductor_id = $_POST['conductor_id'];
    $vehiculo_id = $_POST['vehiculo_id'];
    $fecha = $_POST['fecha'];
    $monto = $_POST['monto'];
    $tipo = $_POST['tipo'];
    $km_recorrido = $_POST['km_recorrido'];

    // Obtener conductor
    $conductor = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM conductores WHERE id = $conductor_id"));
    $dias_por_ciclo = $conductor['dias_por_ciclo'];
    $monto_dia = ($tipo == 'obligatorio') ? $conductor['ingreso_obligatorio'] : $conductor['ingreso_libre'];

    // Obtener último ingreso
    $ultimo = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM ingresos_conductores WHERE conductor_id = $conductor_id ORDER BY id DESC LIMIT 1"));
    $pendiente_anterior = $ultimo ? $ultimo['saldo_pendiente'] : 0;
    $dia = ($tipo == 'obligatorio') ? (($ultimo ? $ultimo['numero'] : 0) + 1) : null;
    $ciclo = ($tipo == 'obligatorio') ? floor(($dia - 1) / $dias_por_ciclo) + 1 : ($ultimo ? $ultimo['ciclo'] : 1);

    if ($tipo != 'obligatorio') $dia = null;

    // Cálculo de pendiente
    $nuevo_pendiente = $pendiente_anterior + ($monto - $monto_dia);

    $sql = "INSERT INTO ingresos_conductores (conductor_id, vehiculo_id, fecha, monto, tipo, numero, ciclo, saldo_pendiente, km_recorrido)
            VALUES ($conductor_id, $vehiculo_id, '$fecha', $monto, '$tipo', ".($dia ? $dia : "NULL").", $ciclo, $nuevo_pendiente, $km_recorrido)";
    mysqli_query($conn, $sql);

    // Actualizar km_actual solo si es el último
    $last = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MAX(id) as maxid FROM ingresos_conductores WHERE vehiculo_id = $vehiculo_id"));
    if ($last && $last['maxid']) {
        $ing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM ingresos_conductores WHERE id = {$last['maxid']}"));
        if ($ing) {
            mysqli_query($conn, "UPDATE vehiculos SET km_actual = $km_recorrido WHERE id = $vehiculo_id");
        }
    }

    header("Location: ingresos_conductores.php");
    exit();
}

// Eliminar ingreso
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    mysqli_query($conn, "DELETE FROM ingresos_conductores WHERE id = $id");
    header("Location: ingresos_conductores.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Ingresos de Conductores</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="container mt-4">

<h2>Ingresos de Conductores</h2>

<button class="btn btn-primary mb-3" data-toggle="modal" data-target="#agregarModal">Agregar Ingreso</button>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Ciclo</th>
            <th>N° Día</th>
            <th>Conductor</th>
            <th>Vehículo</th>
            <th>Monto</th>
            <th>Saldo Pendiente</th>
            <th>Km</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $res = mysqli_query($conn, "SELECT i.*, c.nombre as conductor, v.matricula as vehiculo FROM ingresos_conductores i
                                    LEFT JOIN conductores c ON i.conductor_id = c.id
                                    LEFT JOIN vehiculos v ON i.vehiculo_id = v.id
                                    ORDER BY i.fecha DESC, i.id DESC");
        while ($row = mysqli_fetch_assoc($res)) {
            echo "<tr>
                    <td>{$row['fecha']}</td>
                    <td>{$row['ciclo']}</td>
                    <td>".($row['numero'] ?? '-')."</td>
                    <td>{$row['conductor']}</td>
                    <td>{$row['vehiculo']}</td>
                    <td>{$row['monto']}</td>
                    <td>{$row['saldo_pendiente']}</td>
                    <td>{$row['km_recorrido']}</td>
                    <td>
                        <a href='?eliminar={$row['id']}' class='btn btn-danger btn-sm' onclick='return confirm("¿Eliminar ingreso?")'>Eliminar</a>
                    </td>
                </tr>"
        }
        ?>
    </tbody>
</table>

<!-- Modal Agregar -->
<div class="modal fade" id="agregarModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <form method="post" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Agregar Ingreso</h5></div>
      <div class="modal-body">
        <div class="form-group">
            <label>Fecha</label>
            <input type="date" name="fecha" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Conductor</label>
            <select name="conductor_id" class="form-control" required>
                <?php
                $conductores = mysqli_query($conn, "SELECT id, nombre FROM conductores");
                while ($c = mysqli_fetch_assoc($conductores)) {
                    echo "<option value='{$c['id']}'>{$c['nombre']}</option>";
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label>Vehículo</label>
            <select name="vehiculo_id" class="form-control" required>
                <?php
                $vehiculos = mysqli_query($conn, "SELECT id, matricula FROM vehiculos");
                while ($v = mysqli_fetch_assoc($vehiculos)) {
                    echo "<option value='{$v['id']}'>{$v['matricula']}</option>";
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label>Monto</label>
            <input type="number" name="monto" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Tipo</label>
            <select name="tipo" class="form-control" required>
                <option value="obligatorio">Obligatorio</option>
                <option value="libre">Libre</option>
            </select>
        </div>
        <div class="form-group">
            <label>Kilometraje Recorrido</label>
            <input type="number" name="km_recorrido" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="agregar" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
