<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flota</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> <!-- Para iconos -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet" /> <!-- Estilos Bootstrap -->
    <style>
        /* Aquí puedes colocar tus estilos personalizados o usar Bootstrap */
        body {
            font-family: Arial, sans-serif;
        }
        .container {
            width: 80%;
            margin: auto;
            padding-top: 20px;
        }
        .tabs {
            display: flex;
            border-bottom: 2px solid #333;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid #ccc;
            border-bottom: none;
            background: #f4f4f4;
        }
        .tab.active {
            background: white;
            font-weight: bold;
            border-top: 2px solid #333;
        }
        .content {
            border: 1px solid #ccc;
            padding: 20px;
            background: white;
        }
    </style>
</head>
<body>
    <?php include '../layout/header.php'; ?>
    
    <div class="container">
        <?php 
            $activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'vehiculos';
        ?>
        <div class="tabs">
            <a href="?tab=vehiculos" class="tab <?php echo ($activeTab == 'vehiculos') ? 'active' : ''; ?>">Vehículos</a>
            <a href="?tab=conductores" class="tab <?php echo ($activeTab == 'conductores') ? 'active' : ''; ?>">Conductores</a>
        </div>
        <div class="content">
            <?php 
                if ($activeTab == 'vehiculos') {
                    include '/taxis/modules/vehiculos/vehiculos.php';
                } else {
                    include '/taxis/modules/conductores/conductores.php';
                }
            ?>
        </div>
    </div>

    <?php include '../layout/footer.php'; ?>
</body>
</html>
