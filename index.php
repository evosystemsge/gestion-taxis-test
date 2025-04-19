<?php
// Incluimos el encabezado y la conexión a la base de datos
include 'layout/header.php';
require 'config/database.php';

// Obtener datos para el dashboard
$mes_actual = date('m');
$anio_actual = date('Y');

/* 1. Vehículos próximos a mantenimiento
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

*/// 2. Bloques de totales
// TOTAL INGRESOS (mes actual)
$total_ingresos = $pdo->query("
    SELECT COALESCE(SUM(monto_ingresado), 0) as total 
    FROM ingresos 
    WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual
")->fetchColumn();

// TOTAL GASTOS (mes actual)
$total_gastos = $pdo->query("
    SELECT COALESCE(SUM(costo), 0) + COALESCE(SUM(total), 0) as total
    FROM (
        SELECT costo, 0 as total FROM mantenimientos WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual
        UNION ALL
        SELECT 0 as costo, total FROM gastos WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual
    ) as combined
")->fetchColumn();

// DEUDAS Y PENDIENTES (mes actual)
$total_deudas = $pdo->query("
    SELECT COALESCE(SUM(monto_pendiente), 0) + COALESCE(SUM(saldo_pendiente), 0) as total
    FROM (
        SELECT monto_pendiente, 0 as saldo_pendiente FROM ingresos WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual
        UNION ALL
        SELECT 0 as monto_pendiente, saldo_pendiente FROM prestamos WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual
    ) as combined
")->fetchColumn();

// SALDO CAJA
$saldo_caja = $pdo->query("SELECT saldo_actual FROM cajas WHERE id = 1")->fetchColumn();

// 3. Datos para gráficos comparativos
$previsiones = $pdo->query("
    SELECT tipo, COALESCE(SUM(monto_previsto), 0) as total 
    FROM previsiones 
    WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual
    GROUP BY tipo
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Real vs Previsiones
$real_vs_prevision = [
    'mantenimientos' => [
        'previsto' => $previsiones['mantenimientos'] ?? 0,
        'real' => $pdo->query("SELECT COALESCE(SUM(costo), 0) FROM mantenimientos WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual")->fetchColumn()
    ],
    'ingresos' => [
        'previsto' => $previsiones['ingresos'] ?? 0,
        'real' => $pdo->query("SELECT COALESCE(SUM(monto_ingresado), 0) FROM ingresos WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual")->fetchColumn()
    ],
    'gastos' => [
        'previsto' => $previsiones['gastos'] ?? 0,
        'real' => $pdo->query("SELECT COALESCE(SUM(total), 0) FROM gastos WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual")->fetchColumn()
    ],
    'prestamos' => [
        'previsto' => $previsiones['prestamos'] ?? 0,
        'real' => $pdo->query("SELECT COALESCE(SUM(saldo_pendiente), 0) FROM prestamos WHERE MONTH(fecha) = $mes_actual AND YEAR(fecha) = $anio_actual")->fetchColumn()
    ]
];

// 4. Últimos 10 días de movimientos (agrupados por día y tipo)
$movimientos_por_dia = $pdo->query("
    SELECT 
        fecha,
        'Ingreso' as tipo,
        SUM(monto_ingresado) as monto,
        1 as es_ingreso
    FROM ingresos 
    GROUP BY fecha
    ORDER BY fecha DESC
    LIMIT 10
")->fetchAll();

// Agregar gastos
$gastos_por_dia = $pdo->query("
    SELECT 
        fecha,
        'Gasto' as tipo,
        SUM(total) as monto,
        0 as es_ingreso
    FROM gastos 
    GROUP BY fecha
    ORDER BY fecha DESC
    LIMIT 10
")->fetchAll();

// Agregar mantenimientos
$mantenimientos_por_dia = $pdo->query("
    SELECT 
        fecha,
        'Mantenimiento' as tipo,
        SUM(costo) as monto,
        0 as es_ingreso
    FROM mantenimientos 
    GROUP BY fecha
    ORDER BY fecha DESC
    LIMIT 10
")->fetchAll();

// Agregar préstamos
$prestamos_por_dia = $pdo->query("
    SELECT 
        fecha,
        'Préstamo' as tipo,
        SUM(saldo_pendiente) as monto,
        0 as es_ingreso
    FROM prestamos 
    GROUP BY fecha
    ORDER BY fecha DESC
    LIMIT 10
")->fetchAll();

// Combinar todos los resultados
$ultimos_movimientos = array_merge(
    $movimientos_por_dia,
    $gastos_por_dia,
    $mantenimientos_por_dia,
    $prestamos_por_dia
);

// Ordenar por fecha descendente
usort($ultimos_movimientos, function($a, $b) {
    return strtotime($b['fecha']) - strtotime($a['fecha']);
});

// Limitar a 10 días distintos (mostrando todos los tipos para cada día)
$fechas_unicas = array_unique(array_column($ultimos_movimientos, 'fecha'));
$fechas_mostrar = array_slice($fechas_unicas, 0, 10);

// Filtrar solo los movimientos de las fechas que vamos a mostrar
$ultimos_movimientos = array_filter($ultimos_movimientos, function($mov) use ($fechas_mostrar) {
    return in_array($mov['fecha'], $fechas_mostrar);
});
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gestión de Flota</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    /* ============ ESTILOS PRINCIPALES ============ */
    :root {
        --color-primario: #004b87;
        --color-secundario: #003366;
        --color-texto: #333;
        --color-fondo: #f8f9fa;
        --color-borde: #e2e8f0;
        --color-exito: #28a745;
        --color-advertencia: #ffc107;
        --color-peligro: #dc3545;
        --color-info: #17a2b8;
        --color-pagar: #20c997;
        --color-negativo: #dc3545;
        --color-positivo: #28a745;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f5f5f5;
        color: var(--color-texto);
        line-height: 1.6;
        margin: 0;
        padding: 0;
    }
    
    .container {
        max-width: 1400px;
        margin: 0 auto; /* Cambiado para eliminar espacio superior */
        background: #fff;
        padding: 0 25px 25px 25px; /* Eliminado padding superior */
        border-radius: 0 0 10px 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    
    h2, h3 {
        color: var(--color-primario);
        margin-top: 0;
    }
    
    h2 {
        font-size: 1.5rem;
        margin-bottom: 15px;
        font-weight: 600;
        padding-top: 20px; /* Espacio después del marquee */
    }
    
    /* ============ BARRERA DE VEHÍCULOS ============ */
    .marquee-container {
        background-color: var(--color-primario);
        color: white;
        padding: 10px 0;
        margin-bottom: 0; /* Eliminado margen inferior */
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
    
    /* ============ BLOQUES DE TOTALES ============ */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .stat-title {
        font-size: 1rem;
        color: #64748b;
        margin-bottom: 10px;
        font-weight: 600;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .stat-positive {
        color: var(--color-positivo);
    }
    
    .stat-negative {
        color: var(--color-negativo);
    }
    
    .stat-info {
        font-size: 0.9rem;
        color: #64748b;
    }
    
    /* ============ GRÁFICOS ============ */
    .charts-container {
        display: grid;
        grid-template-columns: 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }
    
    .chart-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .chart-container {
        position: relative;
        height: 400px;
        width: 100%;
    }
    
    /* ============ TABLA DE MOVIMIENTOS ============ */
    .table-container {
        overflow-x: auto;
        margin-top: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .table th, .table td {
        padding: 14px 12px;
        text-align: left;
        border-bottom: 1px solid var(--color-borde);
    }
    
    .table th {
        background-color: var(--color-primario);
        color: white;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        position: sticky;
        top: 0;
    }
    
    .table td {
        background-color: #fff;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }
    
    .table tr:hover td {
        background-color: rgba(0, 75, 135, 0.05);
    }
    
    .negative {
        color: var(--color-negativo);
        font-weight: 500;
    }
    
    .positive {
        color: var(--color-positivo);
        font-weight: 500;
    }
    
    /* ============ RESPONSIVE ============ */
    @media (max-width: 1200px) {
        .stats-container {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .container {
            padding: 0 15px 15px 15px;
        }
        
        .stats-container {
            grid-template-columns: 1fr;
        }
        
        .chart-container {
            height: 300px;
        }
    }
    </style>
</head>
<body>
      
    <!-- Barra de vehículos (pegada al header)
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
    </div> -->
    
    <div class="container">
        <!-- 2. Bloques de totales -->
        <h2>Resumen Financiero - <?= date('F Y') ?></h2>
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-title">TOTAL INGRESOS</div>
                <div class="stat-value stat-positive"><?= number_format($total_ingresos, 0, ',', '.') ?> XAF</div>
                <div class="stat-info">Total de ingresos este mes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">TOTAL GASTOS</div>
                <div class="stat-value stat-negative"><?= number_format($total_gastos, 0, ',', '.') ?> XAF</div>
                <div class="stat-info">Total de gastos este mes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">DEUDAS Y PENDIENTES</div>
                <div class="stat-value stat-negative"><?= number_format($total_deudas, 0, ',', '.') ?> XAF</div>
                <div class="stat-info">Total pendiente por cobrar</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-title">SALDO CAJA</div>
                <div class="stat-value <?= $saldo_caja >= 0 ? 'stat-positive' : 'stat-negative' ?>">
                    <?= number_format($saldo_caja, 0, ',', '.') ?> XAF
                </div>
                <div class="stat-info">Saldo actual en caja principal</div>
            </div>
        </div>
        
        <!-- 3. Gráficos comparativos -->
        <h2>Comparativo Real vs Previsiones - <?= date('F Y') ?></h2>
        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-container">
                    <canvas id="comparisonChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- 4. Tabla de últimos movimientos -->
        <h2>Resumen Diario de Movimientos (Últimos 10 días)</h2>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Motivo</th>
                        <th>Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Agrupar por fecha para mejor visualización
                    $movimientos_agrupados = [];
                    foreach ($ultimos_movimientos as $mov) {
                        $movimientos_agrupados[$mov['fecha']][] = $mov;
                    }
                    
                    // Mostrar los últimos 10 días
                    $contador_dias = 0;
                    foreach ($movimientos_agrupados as $fecha => $movimientos_dia): 
                        if ($contador_dias >= 10) break;
                        $contador_dias++;
                        
                        // Ordenar los movimientos del día: ingresos primero
                        usort($movimientos_dia, function($a, $b) {
                            return $b['es_ingreso'] - $a['es_ingreso'];
                        });
                        
                        foreach ($movimientos_dia as $mov): 
                            // Saltar movimientos con monto cero
                            if ($mov['monto'] == 0) continue;
                    ?>
                        <tr>
                            <td><?= $mov['fecha'] ?></td>
                            <td><?= htmlspecialchars($mov['tipo']) ?></td>
                            <td class="<?= $mov['es_ingreso'] ? 'positive' : 'negative' ?>">
                                <?= number_format($mov['monto'], 0, ',', '.') ?> XAF
                            </td>
                        </tr>
                    <?php 
                        endforeach;
                    endforeach; 
                    
                    if (count($movimientos_agrupados) == 0): 
                    ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">No hay movimientos recientes</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    // Datos para el gráfico comparativo
    const chartData = {
        labels: ['Mantenimientos', 'Ingresos', 'Gastos', 'Préstamos'],
        datasets: [
            {
                label: 'Previsión',
                data: [
                    <?= $real_vs_prevision['mantenimientos']['previsto'] ?>,
                    <?= $real_vs_prevision['ingresos']['previsto'] ?>,
                    <?= $real_vs_prevision['gastos']['previsto'] ?>,
                    <?= $real_vs_prevision['prestamos']['previsto'] ?>
                ],
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            },
            {
                label: 'Real',
                data: [
                    <?= $real_vs_prevision['mantenimientos']['real'] ?>,
                    <?= $real_vs_prevision['ingresos']['real'] ?>,
                    <?= $real_vs_prevision['gastos']['real'] ?>,
                    <?= $real_vs_prevision['prestamos']['real'] ?>
                ],
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }
        ]
    };
    
    // Configuración del gráfico
    const chartConfig = {
        type: 'bar',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Monto (XAF)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Categoría'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw.toLocaleString() + ' XAF';
                        }
                    }
                },
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Comparación Real vs Previsiones Mensuales'
                }
            }
        }
    };
    
    // Crear el gráfico
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('comparisonChart').getContext('2d');
        new Chart(ctx, chartConfig);
        
        // Pausar el marquee al hacer hover
        const marquee = document.querySelector('.marquee');
        marquee.addEventListener('mouseenter', function() {
            this.style.animationPlayState = 'paused';
        });
        
        marquee.addEventListener('mouseleave', function() {
            this.style.animationPlayState = 'running';
        });
    });
    </script>
    
    <?php include 'layout/footer.php'; ?>
</body>
</html>