<?php
// Incluimos el encabezado y la conexión a la base de datos
include '../../layout/header.php';
require '../../config/database.php';

// Configuración de imágenes
$uploadDir = '../../imagenes/vehiculos/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// ==============================
// LÓGICA DE ACCIONES: Agregar / Editar / Eliminar
// ==============================
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion == 'agregar') {
        // Agregar nuevo vehículo
        $stmt = $pdo->prepare("INSERT INTO vehiculos (marca, modelo, matricula, numero, km_inicial, km_actual, km_aceite) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['marca'], $_POST['modelo'], $_POST['matricula'],
            $_POST['numero'], $_POST['km_inicial'], $_POST['km_actual'], $_POST['km_aceite']
        ]);
        
        // Obtener el ID del vehículo recién insertado
        $vehiculoId = $pdo->lastInsertId();
        
        // Procesar cada imagen
        $updateFields = [];
        $updateValues = [];
        
        for ($i = 1; $i <= 4; $i++) {
            $fieldName = "imagen$i";
            if (!empty($_FILES[$fieldName]['name'])) {
                $fileName = uniqid() . '_' . basename($_FILES[$fieldName]['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetPath)) {
                    $updateFields[] = "imagen$i = ?";
                    $updateValues[] = $fileName;
                }
            }
        }
        
        // Actualizar el vehículo con las rutas de las imágenes
        if (!empty($updateFields)) {
            $sql = "UPDATE vehiculos SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $updateValues[] = $vehiculoId;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateValues);
        }
        
        header("Location: vehiculos.php");
        exit;

    } elseif ($accion == 'eliminar') {
        // Eliminar vehículo si no está asignado a un conductor
        $id = $_POST['id'];
        
        // Primero eliminar las imágenes asociadas
        $stmt = $pdo->prepare("SELECT imagen1, imagen2, imagen3, imagen4 FROM vehiculos WHERE id = ?");
        $stmt->execute([$id]);
        $vehiculo = $stmt->fetch();
        
        for ($i = 1; $i <= 4; $i++) {
            $imgField = "imagen$i";
            if (!empty($vehiculo[$imgField])) {
                $filePath = $uploadDir . $vehiculo[$imgField];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }
        
        // Verificar si está asignado a un conductor
        $stmt = $pdo->prepare("SELECT * FROM conductores WHERE vehiculo_id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            echo "<script>alert('Este vehículo está asignado a un conductor y no se puede eliminar hasta desvincularlo.'); window.location.href='vehiculos.php';</script>";
        } else {
            $stmt = $pdo->prepare("DELETE FROM vehiculos WHERE id = ?");
            $stmt->execute([$id]);
            echo "<script>alert('Vehículo eliminado correctamente.'); window.location.href='vehiculos.php';</script>";
        }

    } elseif ($accion == 'editar') {
        $vehiculoId = $_POST['id'];
        
        // Procesar cada imagen
        $updateFields = [];
        $updateValues = [];
        
        for ($i = 1; $i <= 4; $i++) {
            $fieldName = "editar_imagen$i";
            $currentImageField = "imagen_actual$i";
            
            if (!empty($_FILES[$fieldName]['name'])) {
                // Eliminar imagen anterior si existe
                if (!empty($_POST[$currentImageField])) {
                    $oldImagePath = $uploadDir . $_POST[$currentImageField];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                
                // Subir nueva imagen
                $fileName = uniqid() . '_' . basename($_FILES[$fieldName]['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetPath)) {
                    $updateFields[] = "imagen$i = ?";
                    $updateValues[] = $fileName;
                }
            } elseif (!empty($_POST[$currentImageField])) {
                // Mantener la imagen actual
                $updateFields[] = "imagen$i = ?";
                $updateValues[] = $_POST[$currentImageField];
            } else {
                // No hay imagen
                $updateFields[] = "imagen$i = NULL";
            }
        }
        
        // Actualizar campos básicos
        $stmt = $pdo->prepare("UPDATE vehiculos SET marca=?, modelo=?, matricula=?, numero=?, km_inicial=?, km_actual=?, km_aceite=?" . 
                             (!empty($updateFields) ? ", " . implode(', ', $updateFields) : "") . 
                             " WHERE id=?");
        
        $params = [
            $_POST['marca'], $_POST['modelo'], $_POST['matricula'], $_POST['numero'],
            $_POST['km_inicial'], $_POST['km_actual'], $_POST['km_aceite']
        ];
        
        if (!empty($updateValues)) {
            $params = array_merge($params, $updateValues);
        }
        
        $params[] = $vehiculoId;
        
        $stmt->execute($params);
        header("Location: vehiculos.php");
        exit;
    }
}

// Obtener todos los vehículos con conductor asignado
$vehiculos = $pdo->query("
    SELECT v.*, c.nombre AS conductor_nombre 
    FROM vehiculos v 
    LEFT JOIN conductores c ON v.id = c.vehiculo_id
")->fetchAll();

// Obtener marcas para filtro
$marcas = $pdo->query("SELECT DISTINCT marca FROM vehiculos ORDER BY marca")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Vehículos</title>
    <style>
    /* ============ ESTILOS PRINCIPALES (COPIADOS DE CONDUCTORES.PHP) ============ */
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
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f5f5f5;
        color: var(--color-texto);
        line-height: 1.6;
    }
    
    .container {
        width: 1250px;
        margin: 20px auto;
        background: #fff;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    
    h2 {
        margin-top:0px;
        color: var(--color-primario);
        font-size: 1.8rem;
        margin-bottom: 5px;
        text-align: center;
        font-weight: 600;
    }
    
    /* ============ TABLA ============ */
    .table-container {
        overflow-x: auto;
        margin-top: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
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
    
    /* Estilo para vehículos que necesitan mantenimiento */
    .alerta-mantenimiento {
        background-color: #ffebee !important; /* Fondo rojo claro */
        font-weight: bold;
        color:rgb(235, 13, 46)
    }
    
    /* ============ BOTONES ============ */
    .btn {
        padding: 8px 8px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-weight: 500;
    }
    
    .btn-nuevo {
        background-color: var(--color-exito);
        color: white;
    }
    
    .btn-nuevo:hover {
        background-color: #218838;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-editar {
        background-color: var(--color-advertencia);
        color: #212529;
    }
    
    .btn-editar:hover {
        background-color: #e0a800;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-ver {
        background-color: var(--color-info);
        color: white;
    }
    
    .btn-ver:hover {
        background-color: #138496;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-eliminar {
        background-color: var(--color-peligro);
        color: white;
    }
    
    .btn-eliminar:hover {
        background-color: #c82333;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .btn-filtro {
        background-color: white;
        border: 1px solid var(--color-borde);
        color: var(--color-texto);
    }
    
    .btn-filtro:hover {
        background-color: #f1f5f9;
    }
    
    /* ============ CONTROLES DE TABLA ============ */
    .table-controls {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .table-controls input, 
    .table-controls select {
        flex: 1;
        min-width: 200px;
        max-width: 300px;
        padding: 10px 12px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.95rem;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 25px;
        gap: 8px;
        align-items: center;
    }
    
    /* ============ MODALES ============ */
    .modal-vehicle {
        display: none;
        position: fixed;
        z-index: 9999;
        inset: 0;
        font-family: 'Inter', sans-serif;
    }
    
    .modal-vehicle__overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
    }
    
    .modal-vehicle__container {
        position: relative;
        background: white;
        width: 800px;
        max-width: 95%;
        max-height: 95vh;
        margin: auto;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        animation: modalFadeIn 0.3s ease-out;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .modal-vehicle__close {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: white;
        border: 1px solid var(--color-borde);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        z-index: 10;
    }
    
    .modal-vehicle__close:hover {
        background: #f1f5f9;
        transform: rotate(90deg);
    }
    
    .modal-vehicle__close svg {
        width: 18px;
        height: 18px;
        color: #64748b;
    }
    
    .modal-vehicle__header {
        padding: 24px 24px 16px;
        border-bottom: 1px solid var(--color-borde);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .modal-vehicle__header-content {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .modal-vehicle__badge {
        margin-left: auto;
    }
    
    .modal-vehicle__badge span {
        display: inline-block;
        padding: 4px 20px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        background: #10b981;
        color: white;
    }
    
    .modal-vehicle__title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
    }
    
    .modal-vehicle__title--highlight {
        color: var(--color-primario);
        font-weight: 600;
    }
    
    .modal-vehicle__body {
        padding: 0 24px;
        overflow-y: auto;
        flex-grow: 1;
    }
    
    .modal-vehicle__form {
        display: flex;
        flex-direction: column;
        gap: 16px;
        padding: 16px 0;
    }
    
    .modal-vehicle__form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .modal-vehicle__form-row {
        display: flex;
        gap: 16px;
    }
    
    .modal-vehicle__form-row .modal-vehicle__form-group {
        flex: 1;
    }
    
    .modal-vehicle__form-label {
        font-size: 0.85rem;
        color: #64748b;
        font-weight: 600;
    }
    
    .modal-vehicle__form-input {
        padding: 10px 12px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.95rem;
        transition: border-color 0.2s ease;
        width: 100%;
    }
    
    .modal-vehicle__form-input:focus {
        outline: none;
        border-color: var(--color-primario);
        box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.1);
    }
    
    .modal-vehicle__file-input {
        padding: 8px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.9rem;
        width: 100%;
    }
    
    .modal-vehicle__image-preview {
        margin-top: 8px;
        min-height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 5px;
    }
    
    .modal-vehicle__preview-image {
        max-width: 100%;
        max-height: 100px;
        border-radius: 4px;
        border: 1px solid var(--color-borde);
    }
    
    .modal-vehicle__footer {
        padding: 16px 24px;
        border-top: 1px solid var(--color-borde);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .modal-vehicle__action-btn {
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 0.875rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        background: white;
        border: 1px solid var(--color-borde);
        color: #1e293b;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .modal-vehicle__action-btn:hover {
        background: #f1f5f9;
        transform: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .modal-vehicle__action-btn svg {
        width: 14px;
        height: 14px;
    }
    
    .modal-vehicle__action-btn--primary {
        background: var(--color-primario);
        border-color: var(--color-primario);
        color: white;
    }
    
    .modal-vehicle__action-btn--primary:hover {
        background: var(--color-secundario);
    }
    
    /* ============ TABLA DE DETALLES ============ */
    .modal-vehicle__data-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--color-borde);
        border-radius: 8px;
        overflow: hidden;
    }
    
    .modal-vehicle__data-table tr:not(:last-child) {
        border-bottom: 1px solid var(--color-borde);
    }
    
    .modal-vehicle__data-label {
        padding: 12px 16px;
        font-size: 0.85rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        background-color: #f8fafc;
        text-align: left;
        border-right: 1px solid var(--color-borde);
        width: 40%;
    }
    
    .modal-vehicle__data-value {
        padding: 12px 16px;
        font-size: 1rem;
        color: #1e293b;
        font-weight: 500;
        background-color: white;
    }
    
    /* ============ CARRUSEL DE IMÁGENES ============ */
    .vehicle-carousel {
        position: relative;
        width: 100%;
        height: 200px;
        overflow: hidden;
        margin-bottom: 20px;
        border-radius: 8px;
        background: #f1f5f9;
    }
    
    .vehicle-carousel__images {
        display: flex;
        height: 100%;
        transition: transform 0.3s ease;
    }
    
    .vehicle-carousel__image {
        min-width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .vehicle-carousel__nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 100%;
        display: flex;
        justify-content: space-between;
        padding: 0 10px;
        z-index: 2;
    }
    
    .vehicle-carousel__btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.8);
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }
    
    .vehicle-carousel__btn svg {
        width: 20px;
        height: 20px;
        color: #1e293b;
    }
    
    .vehicle-carousel__btn:hover {
        background: white;
    }
    
    .vehicle-carousel__counter {
        position: absolute;
        bottom: 10px;
        right: 10px;
        background: rgba(0, 0, 0, 0.5);
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        z-index: 2;
    }
    
    /* ============ BOTONES FLOTANTES ============ */
    .action-buttons {
        position: fixed;
        bottom: 20px;
        right: 20px;
        display: flex;
        gap: 10px;
        z-index: 100;
    }
    
    .action-button {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--color-primario);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        cursor: pointer;
        transition: all 0.3s;
        border: none;
    }
    
    .action-button:hover {
        transform: scale(1.1);
        background: var(--color-secundario);
    }
    
    /* ============ RESPONSIVE ============ */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }
        
        .modal-vehicle__container {
            max-width: 98%;
            max-height: 98vh;
        }
        
        .modal-vehicle__form-row {
            flex-direction: column;
            gap: 16px;
        }
        
        .table-controls input, 
        .table-controls select {
            max-width: 100%;
        }
        
        .modal-vehicle__footer {
            flex-direction: column;
        }
        
        .modal-vehicle__action-btn {
            width: 100%;
            justify-content: center;
        }
        
        .vehicle-carousel {
            height: 150px;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <h2>Lista de Vehículos</h2>
        
        <!-- Controles de tabla -->
        <div class="table-controls">
            <input type="text" id="searchInput" placeholder="Buscar vehículo..." class="modal-vehicle__form-input">
            <select id="filterMarca" class="modal-vehicle__form-input">
                <option value="">Todas las marcas</option>
                <?php foreach ($marcas as $marca): ?>
                    <option value="<?= htmlspecialchars($marca['marca']) ?>"><?= htmlspecialchars($marca['marca']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterMantenimiento" class="modal-vehicle__form-input">
                <option value="">Todos los estados</option>
                <option value="proximo">Próximo a mantenimiento</option>
                <option value="urgente">Mantenimiento urgente</option>
                <option value="ok">Mantenimiento al día</option>
            </select>
            <button id="openModalAgregar" class="btn btn-nuevo">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nuevo Vehículo
            </button>
        </div>
        
        <!-- Tabla de vehículos -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Marca</th>
                        <th>Modelo</th>
                        <th>Matrícula</th>
                        <th>Número</th>
                        <!--<th>Km Inicial</th>-->
                        <th>Km Actual</th>
                        <th>Próx. Mantenimiento</th>
                        <th>Conductor</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($vehiculos as $vehiculo): ?>
                        <?php
                            $diferencia_km = $vehiculo['km_aceite'] - $vehiculo['km_actual'];
                            $clase_alerta = ($diferencia_km <= 500) ? 'alerta-mantenimiento' : '';
                        ?>
                        <tr class="<?= $clase_alerta ?>" 
                            data-marca="<?= htmlspecialchars($vehiculo['marca']) ?>"
                            data-mantenimiento="<?= $diferencia_km <= 0 ? 'urgente' : ($diferencia_km <= 500 ? 'proximo' : 'ok') ?>">
                            <td><?= $vehiculo['id'] ?></td>
                            <td><?= htmlspecialchars($vehiculo['marca']) ?></td>
                            <td><?= htmlspecialchars($vehiculo['modelo']) ?></td>
                            <td><?= htmlspecialchars($vehiculo['matricula']) ?></td>
                            <td><?= htmlspecialchars($vehiculo['numero']) ?></td>
                            <!--<td><?= number_format($vehiculo['km_inicial'], 0, ',', '.') ?> km</td>-->
                            <td><?= number_format($vehiculo['km_actual'], 0, ',', '.') ?> km</td>
                            <td><?= number_format($vehiculo['km_aceite'], 0, ',', '.') ?> km</td>
                            <td><?= $vehiculo['conductor_nombre'] ?? 'No asignado' ?></td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <button class="btn btn-ver" onclick="verVehiculo(<?= htmlspecialchars(json_encode($vehiculo), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    <button class="btn btn-editar" onclick="editarVehiculo(<?= htmlspecialchars(json_encode($vehiculo), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>
                                    <form method="post" style="display:inline;" onsubmit="return confirmarEliminacion();">
                                        <input type="hidden" name="id" value="<?= $vehiculo['id'] ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <button type="submit" class="btn btn-eliminar">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <div class="pagination">
            <button class="btn" id="prevPage" disabled>Anterior</button>
            <span id="pageInfo" style="padding: 8px 15px;">Página 1</span>
            <button class="btn" id="nextPage">Siguiente</button>
        </div>
    </div>
    
    <!-- Botones flotantes -->
    <div class="action-buttons">
        <button class="action-button" id="btnScrollTop" title="Ir arriba">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 15l-6-6-6 6"></path>
            </svg>
        </button>
        <button class="action-button" id="btnAddNew" title="Nuevo vehículo">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>
    </div>
    
    <!-- ============ MODALES ============ -->
    
    <!-- Modal Agregar Vehículo -->
    <div id="modalAgregar" class="modal-vehicle">
        <div class="modal-vehicle__overlay" onclick="cerrarModal('modalAgregar')"></div>
        <div class="modal-vehicle__container">
            <button class="modal-vehicle__close" onclick="cerrarModal('modalAgregar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal-vehicle__header">
                <h3 class="modal-vehicle__title">Agregar Nuevo Vehículo</h3>
            </div>
            
            <div class="modal-vehicle__body">
                <form method="post" enctype="multipart/form-data" class="modal-vehicle__form" id="modalAgregarForm">
                    <div class="modal-vehicle__form-group">
                        <label for="marca" class="modal-vehicle__form-label">Marca</label>
                        <input type="text" name="marca" class="modal-vehicle__form-input" placeholder="Marca" required>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="modelo" class="modal-vehicle__form-label">Modelo</label>
                        <input type="text" name="modelo" class="modal-vehicle__form-input" placeholder="Modelo" required>
                    </div>
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="matricula" class="modal-vehicle__form-label">Matrícula</label>
                            <input type="text" name="matricula" class="modal-vehicle__form-input" placeholder="Matrícula" required>
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="numero" class="modal-vehicle__form-label">Número</label>
                            <input type="text" name="numero" class="modal-vehicle__form-input" placeholder="Número" required>
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="km_inicial" class="modal-vehicle__form-label">Km Inicial</label>
                            <input type="number" name="km_inicial" class="modal-vehicle__form-input" placeholder="Km Inicial" required>
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="km_actual" class="modal-vehicle__form-label">Km Actual</label>
                            <input type="number" name="km_actual" class="modal-vehicle__form-input" placeholder="Km Actual" required>
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="km_aceite" class="modal-vehicle__form-label">Km Próximo Mantenimiento</label>
                        <input type="number" name="km_aceite" class="modal-vehicle__form-input" placeholder="Km Mantenimiento" required>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label class="modal-vehicle__form-label">Imágenes del Vehículo</label>
                        <div class="modal-vehicle__image-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="modal-vehicle__image-container">
                                    <label for="imagen<?= $i ?>" class="modal-vehicle__form-label">Imagen <?= $i ?></label>
                                    <input type="file" name="imagen<?= $i ?>" id="imagen<?= $i ?>" class="modal-vehicle__file-input" accept="image/*">
                                    <div class="modal-vehicle__image-preview" id="preview<?= $i ?>" style="display: none;">
                                        <img id="previewImg<?= $i ?>" src="#" alt="Vista previa" class="modal-vehicle__preview-image">
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-vehicle__footer">
                <button type="button" class="modal-vehicle__action-btn" onclick="cerrarModal('modalAgregar')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" name="accion" value="agregar" class="modal-vehicle__action-btn modal-vehicle__action-btn--primary" form="modalAgregarForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Guardar Vehículo
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Vehículo -->
    <div id="modalVer" class="modal-vehicle">
        <div class="modal-vehicle__overlay" onclick="cerrarModal('modalVer')"></div>
        <div class="modal-vehicle__container">
            <button class="modal-vehicle__close" onclick="cerrarModal('modalVer')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal-vehicle__header">
                <div class="vehicle-carousel">
                    <!-- Contenido del carrusel de imágenes se insertará aquí -->
                </div>
                
                <div class="modal-vehicle__header-content">
                    <h3 class="modal-vehicle__title">CONDUCTOR ASIGNADO: <span id="nombreConductor" class="modal-vehicle__title--highlight">No asignado</span></h3>
                    <div class="modal-vehicle__badge">
                        <span id="vehicleStatusBadge">Activo</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-vehicle__body">
                <div id="detalleVehiculo" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
            </div>
            
            <div class="modal-vehicle__footer">
                <button class="modal-vehicle__action-btn" onclick="mostrarHistorial('mantenimiento')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Historial de Mantenimiento
                </button>
                <button class="modal-vehicle__action-btn modal-vehicle__action-btn--primary" onclick="editarVehiculo()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Editar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Vehículo -->
    <div id="modalEditar" class="modal-vehicle">
        <div class="modal-vehicle__overlay" onclick="cerrarModal('modalEditar')"></div>
        <div class="modal-vehicle__container">
            <button class="modal-vehicle__close" onclick="cerrarModal('modalEditar')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            
            <div class="modal-vehicle__header">
                <h3 class="modal-vehicle__title">Editar Vehículo</h3>
            </div>
            
            <div class="modal-vehicle__body">
                <form method="post" enctype="multipart/form-data" class="modal-vehicle__form" id="modalEditarForm">
                    <input type="hidden" id="editar_id" name="id">
                    
                    <div class="modal-vehicle__form-group">
                        <label for="editar_marca" class="modal-vehicle__form-label">Marca</label>
                        <input type="text" id="editar_marca" name="marca" class="modal-vehicle__form-input" required>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="editar_modelo" class="modal-vehicle__form-label">Modelo</label>
                        <input type="text" id="editar_modelo" name="modelo" class="modal-vehicle__form-input" required>
                    </div>
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="editar_matricula" class="modal-vehicle__form-label">Matrícula</label>
                            <input type="text" id="editar_matricula" name="matricula" class="modal-vehicle__form-input" required>
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="editar_numero" class="modal-vehicle__form-label">Número</label>
                            <input type="text" id="editar_numero" name="numero" class="modal-vehicle__form-input" required>
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="editar_km_inicial" class="modal-vehicle__form-label">Km Inicial</label>
                            <input type="number" id="editar_km_inicial" name="km_inicial" class="modal-vehicle__form-input" required>
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="editar_km_actual" class="modal-vehicle__form-label">Km Actual</label>
                            <input type="number" id="editar_km_actual" name="km_actual" class="modal-vehicle__form-input" required>
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="editar_km_aceite" class="modal-vehicle__form-label">Km Próximo Mantenimiento</label>
                        <input type="number" id="editar_km_aceite" name="km_aceite" class="modal-vehicle__form-input" required>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label class="modal-vehicle__form-label">Imágenes del Vehículo</label>
                        <div class="modal-vehicle__image-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="modal-vehicle__image-container">
                                    <label for="editar_imagen<?= $i ?>" class="modal-vehicle__form-label">Imagen <?= $i ?></label>
                                    <input type="file" name="editar_imagen<?= $i ?>" id="editar_imagen<?= $i ?>" class="modal-vehicle__file-input" accept="image/*">
                                    <input type="hidden" name="imagen_actual<?= $i ?>" id="imagen_actual<?= $i ?>">
                                    <div class="modal-vehicle__image-preview" id="editar_preview<?= $i ?>">
                                        <img id="editar_previewImg<?= $i ?>" src="#" alt="Vista previa" class="modal-vehicle__preview-image" style="display: none;">
                                        <div id="current_image<?= $i ?>"></div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-vehicle__footer">
                <button type="button" class="modal-vehicle__action-btn" onclick="cerrarModal('modalEditar')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Cancelar
                </button>
                <button type="submit" name="accion" value="editar" class="modal-vehicle__action-btn modal-vehicle__action-btn--primary" form="modalEditarForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Actualizar Vehículo
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Variables globales
    let currentVehiculo = null;
    let currentPage = 1;
    const rowsPerPage = 10;
    let filteredData = [];
    let currentImageIndex = 0;
    let totalImages = 0;

    // Inicialización
    document.addEventListener('DOMContentLoaded', function() {
        // Configurar eventos
        initEventListeners();
        // Configurar paginación
        setupPagination();
        // Mostrar primera página
        updateTable();
    });
    
    // Configurar eventos
    function initEventListeners() {
        // Botón agregar
        document.getElementById('openModalAgregar').addEventListener('click', function() {
            abrirModal('modalAgregar');
        });
        
        // Botón flotante agregar
        document.getElementById('btnAddNew').addEventListener('click', function() {
            abrirModal('modalAgregar');
        });
        
        // Botón flotante ir arriba
        document.getElementById('btnScrollTop').addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Búsqueda
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                updateTable();
            }, 300);
        });
        
        // Filtros
        document.getElementById('filterMarca').addEventListener('change', function() {
            currentPage = 1;
            updateTable();
        });
        
        document.getElementById('filterMantenimiento').addEventListener('change', function() {
            currentPage = 1;
            updateTable();
        });
        
        // Paginación
        document.getElementById('prevPage').addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                updateTable();
            }
        });
        
        document.getElementById('nextPage').addEventListener('click', function() {
            const totalPages = Math.ceil(filteredData.length / rowsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                updateTable();
            }
        });
        
        // Previsualización de imágenes (agregar)
        for (let i = 1; i <= 4; i++) {
            document.getElementById(`imagen${i}`)?.addEventListener('change', function(e) {
                const preview = document.getElementById(`preview${i}`);
                const previewImg = document.getElementById(`previewImg${i}`);
                
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        preview.style.display = 'block';
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
            
            // Previsualización de imágenes (editar)
            document.getElementById(`editar_imagen${i}`)?.addEventListener('change', function(e) {
                const preview = document.getElementById(`editar_previewImg${i}`);
                const currentImage = document.getElementById(`current_image${i}`);
                
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        currentImage.innerHTML = '';
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    }
    
    // Configurar paginación
    function setupPagination() {
        const totalRows = document.querySelectorAll('#tableBody tr').length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage === totalPages;
        document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${totalPages}`;
    }
    
    // Actualizar tabla con filtros y paginación
    function updateTable() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const marcaFilter = document.getElementById('filterMarca').value;
        const mantenimientoFilter = document.getElementById('filterMantenimiento').value;
        
        const rows = document.querySelectorAll('#tableBody tr');
        filteredData = [];
        
        rows.forEach(row => {
            const marca = row.getAttribute('data-marca');
            const mantenimiento = row.getAttribute('data-mantenimiento');
            const texto = row.textContent.toLowerCase();
            
            const matchesSearch = texto.includes(searchTerm);
            const matchesMarca = !marcaFilter || marca === marcaFilter;
            const matchesMantenimiento = !mantenimientoFilter || mantenimiento === mantenimientoFilter;
            
            if (matchesSearch && matchesMarca && matchesMantenimiento) {
                filteredData.push(row);
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        // Paginación
        const startIdx = (currentPage - 1) * rowsPerPage;
        const endIdx = startIdx + rowsPerPage;
        const paginatedData = filteredData.slice(startIdx, endIdx);
        
        // Mostrar solo las filas de la página actual
        rows.forEach(row => {
            if (paginatedData.includes(row)) {
                row.style.display = '';
            } else if (filteredData.includes(row)) {
                row.style.display = 'none';
            }
        });
        
        // Actualizar controles de paginación
        const totalPages = Math.ceil(filteredData.length / rowsPerPage);
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage === totalPages || totalPages === 0;
        document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${totalPages}`;
    }
    
    // Función para abrir modal
    function abrirModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
        // Enfocar el primer input
        setTimeout(() => {
            const firstInput = document.querySelector(`#${modalId} .modal-vehicle__form-input`);
            if (firstInput) firstInput.focus();
        }, 100);
    }
    
    // Función para cerrar modal
    function cerrarModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        // Reiniciar el carrusel al cerrar el modal
        currentImageIndex = 0;
        totalImages = 0;
    }
    
    // Función para ver vehículo
    function verVehiculo(vehiculo) {
        currentVehiculo = vehiculo;
        
        // Configurar conductor asignado
        document.getElementById('nombreConductor').textContent = vehiculo.conductor_nombre || 'No asignado';
        
        // Configurar estado del badge
        const diferenciaKm = vehiculo.km_aceite - vehiculo.km_actual;
        let estado = 'Activo';
        let badgeColor = '#10b981'; // Verde
        
        if (diferenciaKm <= 0) {
            estado = 'Mantenimiento urgente';
            badgeColor = '#ef4444'; // Rojo
        } else if (diferenciaKm <= 500) {
            estado = 'Próximo a mantenimiento';
            badgeColor = '#f59e0b'; // Amarillo
        }
        
        document.getElementById('vehicleStatusBadge').textContent = estado;
        document.getElementById('vehicleStatusBadge').style.background = badgeColor;
        
        // Generar HTML para el carrusel de imágenes
        let carouselHTML = `
            <div class="vehicle-carousel__images" id="vehicleCarouselImages">
        `;
        
        let hasImages = false;
        for (let i = 1; i <= 4; i++) {
            const imgField = `imagen${i}`;
            if (vehiculo[imgField]) {
                hasImages = true;
                carouselHTML += `
                    <img class="vehicle-carousel__image" 
                         src="../../imagenes/vehiculos/${vehiculo[imgField]}" 
                         alt="Vehículo ${i}">
                `;
            }
        }
        
        if (!hasImages) {
            carouselHTML += `
                <div style="display: flex; justify-content: center; align-items: center; width: 100%; height: 100%; color: #64748b;">
                    No hay imágenes disponibles
                </div>
            `;
        }
        
        carouselHTML += `
            </div>
            ${hasImages ? `
            <div class="vehicle-carousel__nav">
                <button class="vehicle-carousel__btn" onclick="prevImage()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 18l-6-6 6-6"></path>
                    </svg>
                </button>
                <button class="vehicle-carousel__btn" onclick="nextImage()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 18l6-6-6-6"></path>
                    </svg>
                </button>
            </div>
            <div class="vehicle-carousel__counter" id="imageCounter">1/${hasImages ? document.querySelectorAll('.vehicle-carousel__image').length : '0'}</div>
            ` : ''}
        `;
        
        // Insertar el carrusel en el header
        const carouselContainer = document.querySelector('.modal-vehicle__header .vehicle-carousel');
        carouselContainer.innerHTML = carouselHTML;
        
        // Datos para las columnas
        const columna1 = [
            { label: 'MARCA', value: vehiculo.marca || 'N/A' },
            { label: 'MODELO', value: vehiculo.modelo || 'N/A' },
            { label: 'MATRÍCULA', value: vehiculo.matricula || 'N/A' },
            { label: 'NÚMERO', value: vehiculo.numero || 'N/A' },
            { label: 'KILOMETRAJE INICIAL', value: (vehiculo.km_inicial || '0') + ' km' }
        ];
        
        const columna2 = [
            { label: 'KILOMETRAJE ACTUAL', value: (vehiculo.km_actual || '0') + ' km' },
            { label: 'PRÓXIMO MANTENIMIENTO', value: (vehiculo.km_aceite || '0') + ' km' },
            { label: 'ESTADO', value: estado },
            { label: 'CONDUCTOR ASIGNADO', value: vehiculo.conductor_nombre || 'No asignado' },
            { label: 'OBSERVACIONES', value: vehiculo.observaciones || 'Ninguna' }
        ];
        
        // Generar HTML para las tablas
        const html = `
            <div style="flex: 1; min-width: 300px;">
                <table class="modal-vehicle__data-table">
                    ${columna1.map(item => `
                        <tr>
                            <td class="modal-vehicle__data-label">${item.label}</td>
                            <td class="modal-vehicle__data-value">${item.value}</td>
                        </tr>
                    `).join('')}
                </table>
            </div>
            <div style="flex: 1; min-width: 300px;">
                <table class="modal-vehicle__data-table">
                    ${columna2.map(item => `
                        <tr>
                            <td class="modal-vehicle__data-label">${item.label}</td>
                            <td class="modal-vehicle__data-value">${item.value}</td>
                        </tr>
                    `).join('')}
                </table>
            </div>
        `;
        
        // Insertar todo el contenido
        document.getElementById('detalleVehiculo').innerHTML = html;
        
        // Inicializar el carrusel si hay imágenes
        if (hasImages) {
            initCarousel();
        }
        
        // Mostrar modal
        abrirModal('modalVer');
    }
    
    // Funciones para el carrusel
    function initCarousel() {
        const images = document.querySelectorAll('.vehicle-carousel__image');
        totalImages = images.length;
        updateCounter();
    }
    
    function prevImage() {
        if (currentImageIndex > 0) {
            currentImageIndex--;
        } else {
            currentImageIndex = totalImages - 1;
        }
        updateCarousel();
    }
    
    function nextImage() {
        if (currentImageIndex < totalImages - 1) {
            currentImageIndex++;
        } else {
            currentImageIndex = 0;
        }
        updateCarousel();
    }
    
    function updateCarousel() {
        const carousel = document.getElementById('vehicleCarouselImages');
        carousel.style.transform = `translateX(-${currentImageIndex * 100}%)`;
        updateCounter();
    }
    
    function updateCounter() {
        const counter = document.getElementById('imageCounter');
        if (counter) {
            counter.textContent = `${currentImageIndex + 1}/${totalImages}`;
        }
    }
    
    // Función para editar vehículo
    function editarVehiculo(vehiculo = null) {
        if (!vehiculo && currentVehiculo) {
            vehiculo = currentVehiculo;
        }
        
        if (vehiculo) {
            document.getElementById('editar_id').value = vehiculo.id;
            document.getElementById('editar_marca').value = vehiculo.marca;
            document.getElementById('editar_modelo').value = vehiculo.modelo;
            document.getElementById('editar_matricula').value = vehiculo.matricula;
            document.getElementById('editar_numero').value = vehiculo.numero;
            document.getElementById('editar_km_inicial').value = vehiculo.km_inicial;
            document.getElementById('editar_km_actual').value = vehiculo.km_actual;
            document.getElementById('editar_km_aceite').value = vehiculo.km_aceite;
            
            // Mostrar imágenes actuales
            for (let i = 1; i <= 4; i++) {
                const imgField = `imagen${i}`;
                const previewDiv = document.getElementById(`current_image${i}`);
                const hiddenInput = document.getElementById(`imagen_actual${i}`);
                
                if (vehiculo[imgField]) {
                    hiddenInput.value = vehiculo[imgField];
                    previewDiv.innerHTML = `
                        <img src="../../imagenes/vehiculos/${vehiculo[imgField]}" 
                             class="modal-vehicle__preview-image"
                             style="max-width: 100px; max-height: 80px;">
                        <div style="font-size: 12px; color: #666; text-align: center; margin-top: 5px;">Imagen actual</div>
                    `;
                } else {
                    previewDiv.innerHTML = '<div style="color: #999; font-size: 12px; text-align: center;">No hay imagen</div>';
                }
            }
            
            cerrarModal('modalVer');
            abrirModal('modalEditar');
        } else {
            // Si no hay vehículo, abrir modal de agregar
            cerrarModal('modalVer');
            abrirModal('modalAgregar');
        }
    }
    
    // Función para mostrar historial
    function mostrarHistorial(tipo) {
        if (!currentVehiculo) return;
        
        alert(`Mostrar historial de ${tipo} para el vehículo ${currentVehiculo.matricula}`);
        // Aquí puedes implementar la lógica para cargar y mostrar el historial
    }
    
    // Confirmar eliminación
    function confirmarEliminacion() {
        return confirm('¿Estás seguro de que deseas eliminar este vehículo?');
    }
    
    // Cerrar modal al hacer clic fuera del contenido
    window.onclick = function(event) {
        if (event.target.className === 'modal-vehicle__overlay') {
            const modalId = event.target.parentElement.id;
            cerrarModal(modalId);
        }
    };
    </script>
    
    <?php include '../../layout/footer.php'; ?>
</body>
</html>