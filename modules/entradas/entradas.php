<?php
include '../../layout/header.php';
require '../../config/database.php';

// Restaurar la pestaña activa si existe en parámetro GET
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'ingresos';

// Lista de pestañas disponibles
$available_tabs = ['ingresos', 'pendientes', 'traspasos', 'cuentas', 'gastos'];

// Validar que la pestaña solicitada exista
if (!in_array($active_tab, $available_tabs)) {
    $active_tab = 'ingresos';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Entradas</title>
    <style>
    /* ============ ESTILOS PRINCIPALES ============ */
    :root {
        --color-primario: #004b87;
        --color-secundario: #003366;
        --color-texto: #333;
        --color-fondo: #f8f9fa;
        --color-borde: #e2e8f0;
        --color-ingresos: #3498db;
        --color-pendientes: #2ecc71;
        --color-traspasos: #e67e22;
        --color-cuentas: #9b59b6;
        --color-gastos: #e74c3c;
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
        padding: 8px 15px;
        cursor: pointer;
        font-weight: 500;
        color: var(--color-texto);
        border-bottom: 3px solid transparent;
        transition: all 0.3s ease;
        font-size: 0.9rem;
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
    
    /* Estilos específicos para cada pestaña */
    .tab[onclick*="ingresos"]:hover {
        color: var(--color-ingresos);
    }
    
    .tab[onclick*="pendientes"]:hover {
        color: var(--color-pendientes);
    }
    
    .tab[onclick*="traspasos"]:hover {
        color: var(--color-traspasos);
    }
    
    .tab[onclick*="cuentas"]:hover {
        color: var(--color-cuentas);
    }
    
    .tab[onclick*="gastos"]:hover {
        color: var(--color-gastos);
    }
    
    .tab-content {
        display: none;
        padding: 15px 0;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
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
        <!--<h1>Gestión de entradas</h1>-->
        
        <!-- Pestañas -->
        <div class="tabs">
            <div class="tab <?= $active_tab == 'ingresos' ? 'active' : '' ?>" 
                 onclick="window.location.href='entradas.php?tab=ingresos'">
                 <i class="fas fa-taxi" style="margin-right: 8px;"></i>Ingresos</div>
            <div class="tab <?= $active_tab == 'pendientes' ? 'active' : '' ?>" 
                 onclick="window.location.href='entradas.php?tab=pendientes'">
                 <i class="fa fa-user" style="margin-right: 8px;"></i>Pendientes</div>
            <div class="tab <?= $active_tab == 'traspasos' ? 'active' : '' ?>" 
                 onclick="window.location.href='entradas.php?tab=traspasos'">
                 <i class="fa fa-warehouse" style="margin-right: 8px;"></i>Traspasos</div>
            <div class="tab <?= $active_tab == 'cuentas' ? 'active' : '' ?>" 
                 onclick="window.location.href='entradas.php?tab=cuentas'">
                 <i class="fas fa-tools" style="margin-right: 8px;"></i>Cuentas</div>
            <!--<div class="tab <?= $active_tab == 'gastos' ? 'active' : '' ?>" 
                 onclick="window.location.href='entradas.php?tab=gastos'">Gastos</div>-->
        </div>
        
        <!-- Contenido de las pestañas -->
        <div class="tab-content <?= $active_tab == 'ingresos' ? 'active' : '' ?>" id="ingresos-content">
            <?php if ($active_tab == 'ingresos'): ?>
                <?php include 'ingresos.php'; ?>
            <?php endif; ?>
        </div>
        
        <div class="tab-content <?= $active_tab == 'pendientes' ? 'active' : '' ?>" id="pendientes-content">
            <?php if ($active_tab == 'pendientes'): ?>
                <?php include 'pendientes.php'; ?>
            <?php endif; ?>
        </div>
        
        <div class="tab-content <?= $active_tab == 'traspasos' ? 'active' : '' ?>" id="traspasos-content">
            <?php if ($active_tab == 'traspasos'): ?>
                <?php include 'traspasos.php'; ?>
            <?php endif; ?>
        </div>
        
        <div class="tab-content <?= $active_tab == 'cuentas' ? 'active' : '' ?>" id="cuentas-content">
            <?php if ($active_tab == 'cuentas'): ?>
                <?php include 'cuentas.php'; ?>
            <?php endif; ?>
        </div>
        
        <div class="tab-content <?= $active_tab == 'gastos' ? 'active' : '' ?>" id="gastos-content">
            <?php if ($active_tab == 'gastos'): ?>
                <?php include 'gastos.php'; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../layout/footer.php'; ?>
</body>
</html>