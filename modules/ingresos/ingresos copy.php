<?php
include '../../layout/header.php';
require '../../config/database.php';

// Restaurar la pestaña activa si existe en parámetro GET
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'ingresos';
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
        margin-top: -15px;
        padding-bottom: 0px;
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
        .container {
            padding: 10px;
        }
    }
    </style>
</head>
<body>
    <div class="container" style="margin-top: 0; padding-top: 0;">
        <h1>Gestión de Entradas</h1>
        
        <!-- Pestañas -->
        <div class="tabs">
            <div class="tab <?= $active_tab == 'ingresos' ? 'active' : '' ?>" 
                 onclick="location.href='entradas.php?tab=ingresos'">Ingresos</div>
            <div class="tab <?= $active_tab == 'pendientes' ? 'active' : '' ?>" 
                 onclick="location.href='entradas.php?tab=pendientes'">Pendientes</div>
            <div class="tab <?= $active_tab == 'entradas' ? 'active' : '' ?>" 
                 onclick="location.href='entradas.php?tab=entradas'">Entradas</div>
            <div class="tab <?= $active_tab == 'traspasos' ? 'active' : '' ?>" 
                 onclick="location.href='entradas.php?tab=traspasos'">Traspasos</div>
            <div class="tab <?= $active_tab == 'cuentas' ? 'active' : '' ?>" 
                 onclick="location.href='entradas.php?tab=cuentas'">Cuentas</div>
        </div>
        
        <!-- Contenido de las pestañas -->
        <div class="tab-content <?= $active_tab == 'ingresos' ? 'active' : '' ?>" id="ingresos-content">
            <?php 
            if ($active_tab == 'ingresos') {
                include 'registros.php';
            }
            ?>
        </div>
        
        <div class="tab-content <?= $active_tab == 'pendientes' ? 'active' : '' ?>" id="pendientes-content">
            <?php 
            if ($active_tab == 'pendientes') {
                include 'pendientes.php';
            }
            ?>
        </div>
        
        <div class="tab-content <?= $active_tab == 'entradas' ? 'active' : '' ?>" id="entradas-content">
            <?php 
            if ($active_tab == 'entradas') {
                include 'entradas_tab.php';
            }
            ?>
        </div>
        
        <div class="tab-content <?= $active_tab == 'traspasos' ? 'active' : '' ?>" id="traspasos-content">
            <?php 
            if ($active_tab == 'traspasos') {
                include 'traspasos.php';
            }
            ?>
        </div>
        
        <div class="tab-content <?= $active_tab == 'cuentas' ? 'active' : '' ?>" id="cuentas-content">
            <?php 
            if ($active_tab == 'cuentas') {
                include 'cuentas.php';
            }
            ?>
        </div>
    </div>
    
    <?php include '../../layout/footer.php'; ?>
</body>
</html>