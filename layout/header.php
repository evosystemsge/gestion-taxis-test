<?php

define('BASE_URL', '/taxis'); //constante que almecena el directorio principal del proyecto en el servidor

//######################### CODIGO JACK #################################

session_start();
// Incluir archivo de conexión
//include '../config/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location:" . BASE_URL . "/modules/login/login.php");
    exit;
}


//######################## FIN CODIGO JACK ##############################



// Incluir archivo de conexión
include __DIR__ . '/../config/database.php';
$logoPath = '/taxis/assets/logo.png';

// Consultar el saldo de la caja predeterminada
$query = "SELECT saldo_actual FROM cajas WHERE predeterminada = 1 LIMIT 1";
$stmt = $pdo->prepare($query);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Si encontramos el saldo, lo asignamos a una variable
$saldoCaja = $result ? $result['saldo_actual'] : 0; // Si no hay saldo, asignamos 0
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flota Taxis</title>
    <link rel="stylesheet" href="/taxis/styles/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body>

    <header class="header">
        <div class="logo-container">
            <img src="<?php echo $logoPath; ?>" alt="Logo Flota Taxis" class="logo">
            <span class="title">FLOTA TAXIS</span>
        </div>

        <nav class="navbar">
            <ul class="menu">
                <li>
                    <a href="/taxis/index.php"><i class="fas fa-home"></i> Inicio</a>
                </li>
                <li>
                    <a href="/taxis/modules/flota/flota.php"><i class="fas fa-car-side"></i> Flota</a>
                    <!--<ul class="submenu">
                    <li><a href="/taxis/modules/vehiculos/vehiculos.php"><i class="fas fa-truck"></i> Vehículos</a></li>
                    <li><a href="/taxis/modules/conductores/conductores.php"><i class="fas fa-user-tie"></i> Conductores</a></li>
                </ul>-->
                </li>
                <!--<li>
                <a href="#"><i class="fas fa-tools"></i> Taller</a>
                <ul class="submenu">
                    <li><a href="/taxis/modules/almacen/productos.php"><i class="fas fa-box"></i> Repuestos</a></li>
                    <li><a href="/taxis/modules/mantenimientos/mantenimientos.php"><i class="fas fa-wrench"></i> Mantenimientos</a></li>
                </ul>
            </li>-->
                <li>
                    <a href="/taxis/modules/entradas/entradas.php"><i class="fas fa-arrow-up"></i> Ingresos</a>
                    <!--<ul class="submenu">
                    <li><a href="#"><i class="fas fa-arrow-up"></i> Entradas</a></li>
                    <li><a href="#"><i class="fas fa-exchange"></i> Traspasos</a></li>                    
                </ul>-->
                </li>
                <li>
                    <a href="/taxis/modules/deudas/deudas.php"><i class="fas fa-file-invoice-dollar"></i> Deudas</a>
                    <!--ul class="submenu">
                    <li><a href="#"><i class="fas fa-money-bill"></i> Salarios</a></li>
                    <li><a href="/taxis/modules/deudas/deudas.php"><i class="fas fa-hand-holding-usd"></i> Deudas</a></li>
                </ul>-->
                </li>
                <li>
                    <a href="/taxis/modules/salidas/salidas.php"><i class="fas fa-arrow-down"></i> Gastos</a>
                    <!--<ul class="submenu">
                    <li><a href="/taxis/modules/deudas/ingresos-pendientes.php"><i class="fas fa-money-bill"></i> Ingresos Pendientes</a></li>
                    <li><a href="/taxis/modules/deudas/prestamos.php"><i class="fas fa-money-bill"></i> Prestamos</a></li>
                    <li><a href="/taxis/modules/deudas/deudas.php"><i class="fas fa-hand-holding-usd"></i> Amonestaciones</a></li>
                </ul>-->
                </li>
                <li>
                    <a href="#" onclick="abrirModalImpresos()"><i class="fas fa-print"></i> Impresos</a>
                </li>
                <!-- Agregamos el ícono de monedero y el saldo de la caja predeterminada -->
                <li>
                    <a href="#"><i class="fas fa-wallet"></i> Caja: <strong><?php echo   number_format($saldoCaja, 0, '.', '.') . '  FCFA'; ?></strong></a>
                    <ul class="submenu">
                        <li><a href="#"><i class="fas fa-bank"></i> Cuentas</a></li>
                    </ul>
                </li>

                <li style="display: flex; justify-content: center; align-items: center;">
                    <button onclick="cerrarSesion()" style="background-color: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 16px;">
                        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                    </button>
                </li>
            </ul>
        </nav>
    </header>
    <!-- Modal Impresos -->
    <div id="modalImpresos" class="modal-impresos" style="display: none;">
        <div class="modal-impresos-overlay" onclick="cerrarModalImpresos()"></div>
        <div class="modal-impresos-container">
            <button class="modal-impresos-close" onclick="cerrarModalImpresos()">
                <i class="fas fa-times"></i>
            </button>

            <div class="modal-impresos-header">
                <h3>Generar Impreso</h3>
            </div>

            <div class="modal-impresos-body">
                <div class="form-group">
                    <label>Tipo de Impreso:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="tipo_impreso" value="ingreso" checked onchange="cambiarTipoImpreso('ingreso')">
                            Ingresos Diarios
                        </label>
                        <label>
                            <input type="radio" name="tipo_impreso" value="gasto" onchange="cambiarTipoImpreso('gasto')">
                            Gastos
                        </label>
                        <label>
                            <input type="radio" name="tipo_impreso" value="contrato" onchange="cambiarTipoImpreso('contrato')">
                            Contrato
                        </label>
                    </div>
                </div>

                <div id="conductorSection" class="form-group">
                    <label for="conductor_id">Conductor:</label>
                    <select id="conductor_id" class="form-control">
                        <option value="">Seleccione un conductor</option>
                        <?php
                        $query = "SELECT id, nombre FROM conductores ORDER BY nombre";
                        $stmt = $pdo->query($query);
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='{$row['id']}'>{$row['nombre']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="modal-impresos-footer">
                <button class="btn btn-cancelar" onclick="cerrarModalImpresos()">Cancelar</button>
                <button class="btn btn-generar" onclick="generarImpreso()">Generar</button>
            </div>
        </div>
    </div>

    <style>
        /*.modal-impresos {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}*/
        .modal-impresos {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none;
            z-index: 1000;
        }

        /*
.modal-impresos-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}*/
        .modal-impresos-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        /*
.modal-impresos-container {
    position: relative;
    background: white;
    width: 500px;
    max-width: 90%;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    z-index: 1001;
    padding: 20px;
}*/

        .modal-impresos-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #666;
        }

        .modal-impresos-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            width: 500px;
            max-width: 90%;
            max-height: 90vh;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            padding: 20px;
            overflow-y: auto;
        }

        .modal-impresos-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .modal-impresos-header h3 {
            margin: 0;
            color: #333;
        }

        .modal-impresos-body {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }

        .modal-impresos-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-cancelar {
            background-color: #f1f1f1;
            color: #333;
        }

        .btn-generar {
            background-color: #004b87;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }
    </style>
    <script>
        function abrirModalImpresos() {
            document.getElementById('modalImpresos').style.display = 'block';
        }

        function cerrarModalImpresos() {
            document.getElementById('modalImpresos').style.display = 'none';
        }

        function cambiarTipoImpreso(tipo) {
            const conductorSection = document.getElementById('conductorSection');
            if (tipo === 'gasto') {
                conductorSection.style.display = 'none';
            } else {
                conductorSection.style.display = 'block';
                // Aquí podrías cargar dinámicamente los conductores si es necesario
            }
        }

        function generarImpreso() {
            const tipo = document.querySelector('input[name="tipo_impreso"]:checked').value;
            const conductorId = tipo !== 'gasto' ? document.getElementById('conductor_id').value : null;

            if (tipo !== 'gasto' && !conductorId) {
                alert('Por favor seleccione un conductor');
                return;
            }

            // Aquí iría la lógica para generar el PDF
            if (tipo === 'ingreso') {
                generarPDFIngreso(conductorId);
            } else if (tipo === 'contrato') {
                generarPDFContrato(conductorId);
            } else {
                generarPDFGasto();
            }

            cerrarModalImpresos();
        }

        function generarPDFIngreso(conductorId) {
            // Aquí iría la lógica para generar el PDF de ingresos
            // Usarías conductorId para obtener los datos del conductor y su vehículo
            console.log('Generando PDF de ingresos para conductor ID:', conductorId);

            // Ejemplo de cómo abrir una nueva ventana con el PDF
            window.open(`/taxis/modules/impresos/generar_ingreso.php?conductor_id=${conductorId}`, '_blank');
        }

        function generarPDFContrato(conductorId) {
            console.log('Generando contrato para conductor ID:', conductorId);
            window.open(`/taxis/modules/impresos/generar_contrato.php?conductor_id=${conductorId}`, '_blank');
        }

        function generarPDFGasto() {
            console.log('Generando PDF de gastos');
            window.open('/taxis/modules/impresos/generar_gasto.php', '_blank');
        }

        function cerrarSesion(base) {
            // Redirige a la URL de logout
            window.location.href = "<?php echo BASE_URL; ?>" + '/modules/login/logout.php';
        }
    </script>
    <?php include 'marquee.php'; ?>
</body>

</html>