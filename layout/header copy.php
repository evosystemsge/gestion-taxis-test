<?php
    // Ruta del logo
    $logoPath = '/taxis/assets/logo.png';
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
                <a href="/taxis/modules/flota.php"><i class="fas fa-car-side"></i> Flota</a>
                <ul class="submenu">
                    <li><a href="/taxis/modules/vehiculos/vehiculos.php"><i class="fas fa-truck"></i> Vehículos</a></li>
                    <li><a href="/taxis/modules/conductores/conductores.php"><i class="fas fa-user-tie"></i> Conductores</a></li>
                </ul>
            </li>
            <li>
                <a href="#"><i class="fas fa-tools"></i> Taller</a>
                <ul class="submenu">
                    <li><a href="/taxis/modules/almacen/productos.php"><i class="fas fa-box"></i> Almacén</a></li>
                    <li><a href="#"><i class="fas fa-wrench"></i> Mantenimientos</a></li>
                    <li><a href="#"><i class="fas fa-exclamation-triangle"></i> Averías</a></li>
                </ul>
            </li>
            <li>
                <a href="#"><i class="fas fa-cash-register"></i> Caja</a>
                <ul class="submenu">
                    <li><a href="/taxis/modules/ingresos/ingresos.php"><i class="fas fa-arrow-down"></i> Ingresos</a></li>
                    <li><a href="#"><i class="fas fa-arrow-up"></i> Entradas</a></li>
                    <li><a href="#"><i class="fas fa-exchange"></i> Traspasos</a></li>
                    <li><a href="#"><i class="fas fa-file-invoice-dollar"></i> Gastos</a></li>
                    <li><a href="#"><i class="fas fa-bank"></i> Cuentas</a></li>
                </ul>
            </li>
            <li>
                <a href="#"><i class="fas fa-wallet"></i> Nóminas</a>
                <ul class="submenu">
                    <li><a href="#"><i class="fas fa-money-bill"></i> Salarios</a></li>
                    <li><a href="#"><i class="fas fa-hand-holding-usd"></i> Préstamos</a></li>
                </ul>
            </li>
            <li>
                <a href="#"><i class="fas fa-user-shield"></i> Administración</a>
                <ul class="submenu">
                    <li><a href="#"><i class="fas fa-user-cog"></i> Usuarios</a></li>
                    <li><a href="#"><i class="fas fa-print"></i> Impresos</a></li>
                </ul>
            </li>
        </ul>
    </nav>
</header>

</body>
</html>
