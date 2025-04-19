<?php
include '../../layout/header.php';
require '../../config/database.php';

// Restaurar la pestaña activa si existe en parámetro GET
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'gastos';

// Lista de pestañas disponibles
$available_tabs = ['gastos', 'nominas', 'salarios'];

// Validar que la pestaña solicitada exista
if (!in_array($active_tab, $available_tabs)) {
    $active_tab = 'gastos';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Salidas</title>
    <style>
    /* ============ ESTILOS PRINCIPALES ============ */
    :root {
        --color-primario: #004b87;
        --color-secundario: #003366;
        --color-texto: #333;
        --color-fondo: #f8f9fa;
        --color-borde: #e2e8f0;
        --color-gastos: #e74c3c;
        --color-nominas: #3498db;
        --color-salarios: #2ecc71;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f5f5f5;
        color: var(--color-texto);
        line-height: 1.6;
        margin: 0;
    }

    .container {
        max-width: 1400px;
        margin: 0 auto;
        background: #fff;
        padding: 0 25px 25px 25px;
        border-radius: 0 0 10px 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    
    h1 {
        color: var(--color-primario);
        margin-top: 0;
        padding: 15px 0;
        border-bottom: 1px solid #eee;
        font-size: 1.6rem;
        margin-bottom: 5px;
        text-align: center;
        font-weight: 600;
    }
    
    /* ============ PESTAÑAS ============ */
    .tabs {
        display: flex;
        border-bottom: 1px solid var(--color-borde);
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .tab {
        padding: 10px 20px;
        cursor: pointer;
        font-weight: 500;
        color: var(--color-texto);
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
        font-size: 0.95rem;
        margin-right: 5px;
    }
    
    .tab:hover {
        background-color: rgba(0, 75, 135, 0.05);
    }
    
    .tab.active {
        color: var(--color-primario);
        border-bottom: 3px solid var(--color-primario);
        font-weight: 600;
    }
    
    /* Colores específicos para cada pestaña */
    .tab[onclick*="gastos"]:hover {
        color: var(--color-gastos);
    }
    
    .tab[onclick*="nominas"]:hover {
        color: var(--color-nominas);
    }
    
    .tab[onclick*="salarios"]:hover {
        color: var(--color-salarios);
    }
    
    .tab-content {
        display: none;
        padding: 20px 0;
        animation: fadeIn 0.4s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .tab-content.active {
        display: block;
    }
    
    /* ============ RESPONSIVE ============ */
    @media (max-width: 768px) {
        .tabs {
            flex-direction: column;
            border-bottom: none;
        }
        
        .tab {
            border-bottom: none;
            border-left: 3px solid transparent;
            margin-bottom: 5px;
            padding: 8px 15px;
        }
        
        .tab.active {
            border-bottom: none;
            border-left: 3px solid var(--color-primario);
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <!--<h1>Gestión de Salidas</h1>-->
        
        <!-- Pestañas -->
        <div class="tabs">
            <div class="tab <?= $active_tab == 'gastos' ? 'active' : '' ?>" 
                 onclick="window.location.href='salidas.php?tab=gastos'">
                <i class="fas fa-money-bill-wave" style="margin-right: 8px;"></i> Gastos
            </div>
            <div class="tab <?= $active_tab == 'nominas' ? 'active' : '' ?>" 
                 onclick="window.location.href='salidas.php?tab=nominas'">
                <i class="fas fa-file-invoice-dollar" style="margin-right: 8px;"></i> Nóminas
            </div>
            <div class="tab <?= $active_tab == 'salarios' ? 'active' : '' ?>" 
                 onclick="window.location.href='salidas.php?tab=salarios'">
                <i class="fas fa-hand-holding-usd" style="margin-right: 8px;"></i> Salarios
            </div>
        </div>
        
        <!-- Contenido de las pestañas -->
        <div class="tab-content <?= $active_tab == 'gastos' ? 'active' : '' ?>" id="gastos-content">
            <?php if ($active_tab == 'gastos'): ?>
                <?php include 'gastos.php'; ?>
            <?php endif; ?>
        </div>
        
        <div class="tab-content <?= $active_tab == 'nominas' ? 'active' : '' ?>" id="nominas-content">
            <?php if ($active_tab == 'nominas'): ?>
                <?php include 'nominas.php'; ?>
            <?php endif; ?>
        </div>
        
        <div class="tab-content <?= $active_tab == 'salarios' ? 'active' : '' ?>" id="salarios-content">
            <?php if ($active_tab == 'salarios'): ?>
                <?php include 'salarios.php'; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <?php include '../../layout/footer.php'; ?>
</body>
</html>