<?php
include '../../layout/header.php';
require '../../config/database.php';

// Restaurar la pestaña activa si existe en parámetro GET
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'prestamos';

// Obtener conductores con deudas pendientes
$conductores_con_deudas = $pdo->query("
    SELECT 
        c.id,
        c.nombre,
        (SELECT COALESCE(SUM(p.saldo_pendiente), 0) FROM prestamos p WHERE p.conductor_id = c.id AND p.estado = 'pendiente') as prestamos_pendientes,
        (SELECT COALESCE(SUM(a.saldo_pendiente), 0) FROM amonestaciones a WHERE a.conductor_id = c.id AND a.estado = 'pendiente') as amonestaciones_pendientes,
        (SELECT COALESCE(SUM(i.monto_pendiente), 0) FROM ingresos i WHERE i.conductor_id = c.id) as ingresos_pendientes
    FROM conductores c
    WHERE 
        (SELECT COALESCE(SUM(p.saldo_pendiente), 0) FROM prestamos p WHERE p.conductor_id = c.id AND p.estado = 'pendiente') > 0
        OR (SELECT COALESCE(SUM(a.saldo_pendiente), 0) FROM amonestaciones a WHERE a.conductor_id = c.id AND a.estado = 'pendiente') > 0
        OR (SELECT COALESCE(SUM(i.monto_pendiente), 0) FROM ingresos i WHERE i.conductor_id = c.id) > 0
    ORDER BY c.nombre
")->fetchAll();

// Dividir conductores en grupos de 10 (2 filas de 5)
$grupos_conductores = array_chunk($conductores_con_deudas, 10);
$total_grupos = count($grupos_conductores);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Deudas</title>
    <style>
    /* ============ ESTILOS PRINCIPALES ============ */
    :root {
        --color-primario: #004b87;
        --color-secundario: #003366;
        --color-texto: #333;
        --color-fondo: #f8f9fa;
        --color-borde: #e2e8f0;
        --color-prestamos: #3498db;
        --color-amonestaciones: #e74c3c;
        --color-ingresos: #2ecc71;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f5f5f5;
        color: var(--color-texto);
        line-height: 1.6;
    }
  /*  
    .container {
        max-width: 1250px;
        margin: 20px auto;
        background: #fff;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }*/
    .container {
        max-width: 1400px;
        margin: 0 auto; /* Cambiado para eliminar espacio superior */
        background: #fff;
        padding: 0 25px 25px 25px; /* Eliminado padding superior */
        border-radius: 0 0 10px 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    h1 {
        color: var(--color-primario);
        margin-top: -15px;
        padding-bottom: 0px;
        border-bottom: 1px solid #eee;
        font-size: 1.6rem;
        margin-bottom: 5px;
        text-align: center;
        font-weight: 600;
    }
    
    /* ============ SLIDER DE DEUDAS ============ */
    .deudas-resumen {
        margin-bottom: 15px;
        position: relative;
    }
    
    .deudas-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    
    .deudas-title {
        font-size: 1.1rem;
        color: var(--color-primario);
        margin: 0;
    }
    
    .slider-controls {
        display: flex;
        gap: 5px;
    }
    
    .slider-btn {
        background: var(--color-primario);
        color: white;
        border: none;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 12px;
    }
    
    .slider-container {
        width: 100%;
        overflow: hidden;
        position: relative;
    }
    
    .slider-track {
        display: flex;
        transition: transform 0.3s ease;
    }
    
    .slider-group {
        min-width: 100%;
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        grid-template-rows: repeat(2, auto);
        gap: 5px;
    }
    
    .conductor-card {
        background: white;
        border-radius: 4px;
        padding: 6px;
        box-shadow: 3px 3px 3px rgba(0,0,0,0.1);
        border-left: 2px solid var(--color-primario);
        border-right: 2px solid var(--color-primario);
        border-top: 2px solid var(--color-primario);
        border-bottom: 2px solid var(--color-primario);
        font-size: 1rem;
    }
    
    .conductor-nombre {
        font-weight: 600;
        margin-bottom: 4px;
        color: var(--color-primario);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 1rem;
    }
    
    .deuda-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2px;
        line-height: 1.2;
    }
    
    .deuda-prestamos {
        color: var(--color-prestamos);
    }
    
    .deuda-amonestaciones {
        color: var(--color-amonestaciones);
    }
    
    .deuda-ingresos {
        color: var(--color-ingresos);
    }
    
    .deuda-total {
        margin-top: 3px;
        padding-top: 3px;
        border-top: 1px dashed #eee;
        font-weight: 600;
        font-size: 1rem;
    }
    
    /* ============ PESTAÑAS ============ */
    .tabs {
        display: flex;
        border-bottom: 1px solid var(--color-borde);
        margin-bottom: 15px;
    }
    
    .tab {
        padding: 8px 15px;
        cursor: pointer;
        font-weight: 500;
        color: var(--color-primario);
        border-bottom: 2px solid transparent;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }
    
    .tab:hover {
        background-color: rgba(0, 75, 135, 0.05);
    }
    
    .tab.active {
        color: var(--color-primario);
        border-bottom: 2px solid var(--color-primario);
        font-weight: 600;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* ============ RESPONSIVE ============ */
    @media (max-width: 768px) {
        .slider-group {
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(3, auto);
        }
        
        .tabs {
            flex-direction: column;
        }
        
        .tab {
            border-bottom: none;
            border-left: 2px solid transparent;
        }
        
        .tab.active {
            border-bottom: none;
            border-left: 2px solid var(--color-primario);
        }
    }
    
    @media (max-width: 480px) {
        .slider-group {
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(4, auto);
        }
        
        .container {
            padding: 10px;
        }
    }
    </style>
</head>
<body>
<div class="container" style="margin-top: 0; padding-top: 0;">
        <h1>Gestión de Deudas</h1>
        
        <!-- Resumen de deudas por conductor -->
        <div class="deudas-resumen">
            <div class="deudas-header">
                <h2 class="deudas-title">Conductores con Deudas</h2>
                <?php if ($total_grupos > 1): ?>
                <div class="slider-controls">
                    <button class="slider-btn prev-btn">&lt;</button>
                    <button class="slider-btn next-btn">&gt;</button>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($conductores_con_deudas)): ?>
                <p style="text-align: center; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 0.8rem;">
                    No hay conductores con deudas pendientes
                </p>
            <?php else: ?>
                <div class="slider-container">
                    <div class="slider-track">
                        <?php foreach ($grupos_conductores as $index => $grupo): ?>
                            <div class="slider-group" data-group="<?= $index ?>">
                                <?php foreach ($grupo as $conductor): ?>
                                    <div class="conductor-card">
                                        <div class="conductor-nombre" title="<?= htmlspecialchars($conductor['nombre']) ?>">
                                            <?= htmlspecialchars(mb_substr($conductor['nombre'], 0, 15)) . (mb_strlen($conductor['nombre']) > 15 ? '...' : '') ?>
                                        </div>
                                        
                                        <?php if ($conductor['prestamos_pendientes'] > 0): ?>
                                            <div class="deuda-item deuda-prestamos">
                                                <span>Préstamo:</span>
                                                <span><?= number_format($conductor['prestamos_pendientes'], 0, ',', '.') ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($conductor['amonestaciones_pendientes'] > 0): ?>
                                            <div class="deuda-item deuda-amonestaciones">
                                                <span>Amonestación:</span>
                                                <span><?= number_format($conductor['amonestaciones_pendientes'], 0, ',', '.') ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($conductor['ingresos_pendientes'] > 0): ?>
                                            <div class="deuda-item deuda-ingresos">
                                                <span>Ingreso Pendiente:</span>
                                                <span><?= number_format($conductor['ingresos_pendientes'], 0, ',', '.') ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="deuda-item deuda-total">
                                            <span>Total:</span>
                                            <span><?= number_format($conductor['prestamos_pendientes'] + $conductor['amonestaciones_pendientes'] + $conductor['ingresos_pendientes'], 0, ',', '.') ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pestañas -->
        <div class="tabs">
            <div class="tab <?= $active_tab == 'prestamos' ? 'active' : '' ?>" 
                 onclick="location.href='deudas.php?tab=prestamos'">
                 <i class="fas fa-clock" style="margin-right: 8px;"></i>Préstamos</div>
            <div class="tab <?= $active_tab == 'historial' ? 'active' : '' ?>" 
                 onclick="location.href='deudas.php?tab=historial'">
                 <i class="fas fa-history" style="margin-right: 8px;"></i>Historial de Pagos</div>
        </div>
        
        <!-- Contenido de las pestañas -->
        <div class="tab-content <?= $active_tab == 'prestamos' ? 'active' : '' ?>" id="prestamos-content">
            <?php 
            if ($active_tab == 'prestamos') {
                include 'prestamos.php';
            }
            ?>
        </div>
        
        <div class="tab-content <?= $active_tab == 'historial' ? 'active' : '' ?>" id="historial-content">
            <?php 
            if ($active_tab == 'historial') {
                include 'historial_pagos.php';
            }
            ?>
        </div>
    </div>

    <script>
    // Slider de conductores
    document.addEventListener('DOMContentLoaded', function() {
        const sliderTrack = document.querySelector('.slider-track');
        const prevBtn = document.querySelector('.prev-btn');
        const nextBtn = document.querySelector('.next-btn');
        const sliderGroups = document.querySelectorAll('.slider-group');
        const totalGroups = <?= $total_grupos ?>;
        let currentGroup = 0;
        
        if (totalGroups <= 1) return;
        
        function updateSlider() {
            sliderTrack.style.transform = `translateX(-${currentGroup * 100}%)`;
            
            // Deshabilitar botones en extremos
            prevBtn.disabled = currentGroup === 0;
            nextBtn.disabled = currentGroup === totalGroups - 1;
        }
        
        if (prevBtn && nextBtn) {
            prevBtn.addEventListener('click', function() {
                if (currentGroup > 0) {
                    currentGroup--;
                    updateSlider();
                }
            });
            
            nextBtn.addEventListener('click', function() {
                if (currentGroup < totalGroups - 1) {
                    currentGroup++;
                    updateSlider();
                }
            });
        }
        
        // Inicializar
        updateSlider();
    });
    </script>
    
    <?php include '../../layout/footer.php'; ?>
</body>
</html>