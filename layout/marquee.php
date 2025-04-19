<?php
// layout/marquee.php
include __DIR__ . '/../config/database.php';

$vehiculos_mantenimiento = $pdo->query("
    SELECT id, marca, modelo, matricula, numero, km_actual, km_aceite,
           CASE 
               WHEN km_actual >= km_aceite THEN 0
               ELSE km_aceite - km_actual
           END AS diferencia_km
    FROM vehiculos 
    WHERE km_actual >= km_aceite OR (km_aceite - km_actual <= 500)
    ORDER BY diferencia_km ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Vehículos</title>
    <style>
    /* Estilos del marquee que deben estar en todas las páginas */
    .marquee-container {
        background-color: #004b87;
        color: white;
        padding: 10px 0;
        margin-bottom: 0;
        border-radius: 0;
        overflow: hidden;
    }
    
    .marquee {
        display: flex;
        animation: marquee 30s linear infinite;
        white-space: nowrap;
    }
    
    .marquee-item {
        margin-right: 40px;
        display: flex;
        align-items: center;
    }
    
    .marquee-item.alerta {
        color: #ffcc00;
        font-weight: bold;
    }
    
    .marquee-item.urgente {
        color: #ff3333;
        font-weight: bold;
    }
    
    @keyframes marquee {
        0% { transform: translateX(100%); }
        100% { transform: translateX(-100%); }
    }
    </style>
</head>
<body>
<div class="marquee-container">
    <div class="marquee">
        <?php if (count($vehiculos_mantenimiento) > 0): ?>
            <?php foreach ($vehiculos_mantenimiento as $vehiculo): 
                $diferencia = $vehiculo['diferencia_km'];
                $clase = ($diferencia == 0) ? 'urgente' : (($diferencia <= 500) ? 'alerta' : '');
                $mensaje = ($diferencia == 0) ? '⚠️ MANTENIMIENTO URGENTE' : '⚠️ PRÓXIMO MANTENIMIENTO (Faltan '.number_format($diferencia, 0, ',', '.').' km)';
            ?>
                <div class="marquee-item <?= $clase ?>">
                    <?= htmlspecialchars($vehiculo['marca']) ?> <?= htmlspecialchars($vehiculo['modelo']) ?> 
                    (<?= htmlspecialchars($vehiculo['matricula']) ?>) - 
                    Km Actual: <?= number_format($vehiculo['km_actual'], 0, ',', '.') ?> | 
                    Próx. Mant.: <?= number_format($vehiculo['km_aceite'], 0, ',', '.') ?> 
                    <?= $mensaje ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="marquee-item">No hay vehículos próximos a mantenimiento</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>