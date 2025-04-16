<?php
// Incluimos el encabezado y la conexión a la base de datos
include '../../layout/header.php';
require '../../config/database.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Flota</title>
    <style>
        :root {
            --color-primario: #004b87;
            --color-secundario: #003366;
            --color-texto: #333;
            --color-fondo: #f8f9fa;
            --color-borde: #e2e8f0;
        }



        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            margin-right: 5px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tab:hover {
            background-color: rgba(0, 75, 135, 0.05);
        }

        .tab.active {
            border-color: var(--color-borde);
            border-bottom-color: #fff;
            background: #fff;
            color: var(--color-primario);
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
        .tabs {

        margin: 0px auto;
        background: #fff;
        padding: 5px;
 
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            display: flex;
            border-bottom: 1px solid var(--color-borde);
            margin-bottom: px;
        }
    
    </style>
</head>
<body>

        <!-- Pestañas -->
        <div class="tabs">
            <div class="tab active" data-tab="conductores">Conductores</div>
            <div class="tab" data-tab="vehiculos">Vehículos</div>
        </div>
        
        <!-- Contenido de las pestañas -->
        <div class="tab-content active" id="conductores-content">
            <?php include '../conductores/conductores.php'; ?>
        </div>
        
        <div class="tab-content" id="vehiculos-content">
            <?php include '../vehiculos/vehiculos.php'; ?>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Manejar el cambio de pestañas
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remover la clase 'active' de todas las pestañas y contenidos
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    
                    // Agregar 'active' a la pestaña y contenido seleccionados
                    this.classList.add('active');
                    document.getElementById(`${tabId}-content`).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>