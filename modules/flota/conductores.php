<?php 
require '../../config/database.php';

// ===== MEJORAS DE SEGURIDAD ===== //


function generarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarEntrada($dato) {
    return htmlspecialchars(strip_tags(trim($dato)));
}

if (isset($_POST['accion'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Token CSRF inválido");
    }
}
// ===== FIN MEJORAS DE SEGURIDAD ===== //

// Configuración de imágenes para conductores
$uploadDir = '../../imagenes/conductores/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Lógica para agregar/editar/eliminar conductores
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion == 'agregar') {
        // Procesar imagen del conductor
        $imagen = null;
        if (!empty($_FILES['imagen']['name'])) {
            $fileName = uniqid() . '_' . basename($_FILES['imagen']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $targetPath)) {
                $imagen = $fileName;
            }
        }
        
        // Insertar conductor con datos validados
        $stmt = $pdo->prepare("INSERT INTO conductores 
            (fecha, nombre, direccion, telefono, dip, vehiculo_id, dias_por_ciclo, 
             ingreso_obligatorio, ingreso_libre, imagen, salario_mensual) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            validarEntrada($_POST['fecha']),
            validarEntrada($_POST['nombre']),
            validarEntrada($_POST['direccion']),
            validarEntrada($_POST['telefono']),
            validarEntrada($_POST['dip']),
            $_POST['vehiculo_id'] ?: null,
            validarEntrada($_POST['dias_por_ciclo']),
            validarEntrada($_POST['ingreso_obligatorio']),
            validarEntrada($_POST['ingreso_libre']),
            $imagen,
            validarEntrada($_POST['salario_mensual'])
        ]);
        
        header("Location: conductores.php");
        exit;

    } elseif ($accion == 'eliminar') {
        $id = $_POST['id'];
    
        // Verificar si tiene vehículo asignado
        $stmt = $pdo->prepare("SELECT vehiculo_id, imagen FROM conductores WHERE id = ?");
        $stmt->execute([$id]);
        $conductor = $stmt->fetch();
    
        // Verificar si tiene ingresos vinculados
        $verificarIngresos = $pdo->prepare("SELECT COUNT(*) FROM ingresos WHERE conductor_id = ?");
        $verificarIngresos->execute([$id]);
        $tieneIngresos = $verificarIngresos->fetchColumn();
    
        if ($tieneIngresos > 0) {
            echo "<script>alert('No se puede eliminar este conductor porque tiene ingresos registrados.'); window.location.href='conductores.php';</script>";
            exit;
        }
    
        if ($conductor && $conductor['vehiculo_id']) {
            echo "<script>alert('No se puede eliminar este conductor. Primero desvincúlelo de un vehículo.'); window.location.href='conductores.php';</script>";
        } else {
            // Eliminar imagen si existe
            if (!empty($conductor['imagen'])) {
                $filePath = $uploadDir . $conductor['imagen'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
    
            $stmt = $pdo->prepare("DELETE FROM conductores WHERE id = ?");
            $stmt->execute([$id]);
            echo "<script>alert('Conductor eliminado correctamente.'); window.location.href='conductores.php';</script>";
        }
    }
    elseif ($accion == 'editar') {
        $id = $_POST['id'];
        $imagen = null;
        
        // Procesar imagen
        if (!empty($_FILES['editar_imagen']['name'])) {
            // Eliminar imagen anterior si existe
            $stmt = $pdo->prepare("SELECT imagen FROM conductores WHERE id = ?");
            $stmt->execute([$id]);
            $conductor = $stmt->fetch();
            
            if ($conductor['imagen']) {
                $oldImagePath = $uploadDir . $conductor['imagen'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            // Subir nueva imagen
            $fileName = uniqid() . '_' . basename($_FILES['editar_imagen']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['editar_imagen']['tmp_name'], $targetPath)) {
                $imagen = $fileName;
            }
        } elseif (!empty($_POST['imagen_actual'])) {
            $imagen = $_POST['imagen_actual'];
        }
        
        // Actualizar conductor con datos validados
        $sql = "UPDATE conductores SET 
            fecha = ?, nombre = ?, direccion = ?, telefono = ?, dip = ?, 
            vehiculo_id = ?, dias_por_ciclo = ?, ingreso_obligatorio = ?, 
            ingreso_libre = ?, salario_mensual = ?" . 
            ($imagen !== null ? ", imagen = ?" : "") . 
            " WHERE id = ?";
        
        $params = [
            validarEntrada($_POST['fecha']),
            validarEntrada($_POST['nombre']),
            validarEntrada($_POST['direccion']),
            validarEntrada($_POST['telefono']),
            validarEntrada($_POST['dip']),
            $_POST['vehiculo_id'] ?: null,
            validarEntrada($_POST['dias_por_ciclo']),
            validarEntrada($_POST['ingreso_obligatorio']),
            validarEntrada($_POST['ingreso_libre']),
            validarEntrada($_POST['salario_mensual'])
        ];
        
        if ($imagen !== null) {
            $params[] = $imagen;
        }
        
        $params[] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        header("Location: conductores.php");
        exit;
    }
}

// Consultas mejoradas
$sqlConductores = "SELECT c.*, v.marca AS vehiculo_marca, v.modelo AS vehiculo_modelo, 
                  v.matricula AS vehiculo_matricula, v.numero AS vehiculo_numero
                  FROM conductores c
                  LEFT JOIN vehiculos v ON c.vehiculo_id = v.id
                  ORDER BY c.fecha DESC";
$conductores = $pdo->query($sqlConductores)->fetchAll();

$sqlVehiculos = "SELECT * FROM vehiculos 
                 WHERE id NOT IN (SELECT vehiculo_id FROM conductores WHERE vehiculo_id IS NOT NULL)";
$vehiculosDisponibles = $pdo->query($sqlVehiculos)->fetchAll();

$marcasUnicas = $pdo->query("SELECT DISTINCT marca FROM vehiculos")->fetchAll();
$modelosUnicos = $pdo->query("SELECT DISTINCT modelo FROM vehiculos")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Conductores</title>
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
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }
    */
    .container {
        max-width: 1400px;
        margin: 0 auto; /* Cambiado para eliminar espacio superior */
        background: #fff;
        padding: 0 25px 25px 25px; /* Eliminado padding superior */
        border-radius: 0 0 10px 10px;
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
        min-width: 240px;
        max-width: 240px;
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
    
    /* ============ FOTO DEL CONDUCTOR ============ */
    .conductor-photo-container {
        width: 100%;
        height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f5f9;
        border-radius: 8px;
        margin-bottom: 20px;
        overflow: hidden;
    }
    
    .conductor-photo {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    /* ============ HISTORIAL ============ */
    .historial-container {
        width: 100%;
        margin-top: 20px;
        border-top: 1px solid var(--color-borde);
        padding-top: 20px;
    }
    
    .historial-title {
        color: var(--color-primario);
        margin-bottom: 15px;
        font-size: 1.1rem;
        font-weight: 600;
    }
    
    .historial-content {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        max-height: 300px;
        overflow-y: auto;
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
    
    /* ===== ESTILOS MEJORADOS ===== */
    .loading-spinner {
        border: 4px solid rgba(0, 0, 0, 0.1);
        border-radius: 50%;
        border-top: 4px solid var(--color-primario);
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 20px auto;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .fixed-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 5px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        opacity: 1;
        transition: opacity 0.5s;
    }
    
    .fade-out { opacity: 0; }
    .alert-success { background-color: var(--color-exito); color: white; }
    .alert-error { background-color: var(--color-peligro); color: white; }
    
    .sortable { cursor: pointer; position: relative; padding-right: 25px !important; }
    .sortable::after { content: "↕"; position: absolute; right: 8px; opacity: 0.5; }
    .sort-asc::after { content: "↑"; opacity: 1; }
    .sort-desc::after { content: "↓"; opacity: 1; }
    
    [data-tooltip] {
        position: relative;
    }
    
    [data-tooltip]::before {
        content: attr(data-tooltip);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: #fff;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    [data-tooltip]:hover::before {
        opacity: 1;
    }
    
    .btn-exportar {
        background-color: #6c757d;
        color: white;
    }
    
    .btn-exportar:hover {
        background-color: #5a6268;
    }
    
    .form-hint {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    /* ===== FIN ESTILOS MEJORADOS ===== */
    
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
    }
    </style>
</head>
<body>
    <!--<div class="container">
        <h2>Lista de Conductores</h2>-->
        
        <!-- Controles de tabla mejorados -->
        <div class="table-controls">
            <div class="filter-group">
                <input type="text" id="searchInput" placeholder="Buscar conductor..." class="modal-vehicle__form-input" autocomplete="off">
                
                <select id="filterMarca" class="modal-vehicle__form-input">
                    <option value="">Todas las marcas</option>
                    <?php foreach ($marcasUnicas as $marca): ?>
                        <option value="<?= validarEntrada($marca['marca']) ?>">
                            <?= validarEntrada($marca['marca']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select id="filterModelo" class="modal-vehicle__form-input">
                    <option value="">Todos los modelos</option>
                    <?php foreach ($modelosUnicos as $modelo): ?>
                        <option value="<?= validarEntrada($modelo['modelo']) ?>">
                            <?= validarEntrada($modelo['modelo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="date" id="filterFecha" class="modal-vehicle__form-input">
                
                <button id="btnExportar" class="btn btn-exportar" data-tooltip="Exportar a Excel">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Exportar
                </button>
                
                <button id="openModalAgregar" class="btn btn-nuevo" data-tooltip="Agregar nuevo conductor">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Nuevo
                </button>
            </div>
        </div>
        
        <!-- Tabla de conductores -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>DIP</th>
                        <th>Vehículo Asignado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($conductores as $conductor): ?>
                        <tr data-vehiculo="<?= $conductor['vehiculo_id'] ?: '' ?>" 
                            data-marca="<?= htmlspecialchars($conductor['vehiculo_marca'] ?? '') ?>" 
                            data-modelo="<?= htmlspecialchars($conductor['vehiculo_modelo'] ?? '') ?>"
                            data-fecha="<?= $conductor['fecha'] ?>">
                            <td><?= $conductor['id'] ?></td>
                            <td><?= htmlspecialchars($conductor['fecha']) ?></td>
                            <td><?= htmlspecialchars($conductor['nombre']) ?></td>
                            <td><?= htmlspecialchars($conductor['telefono']) ?></td>
                            <td><?= htmlspecialchars($conductor['dip']) ?></td>
                            <td>
                                <?php if ($conductor['vehiculo_id']): ?>
                                    <?= htmlspecialchars($conductor['vehiculo_matricula']) ?> (<?= htmlspecialchars($conductor['vehiculo_numero']) ?>)
                                <?php else: ?>
                                    No asignado
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <button class="btn btn-ver" onclick="verConductor(<?= htmlspecialchars(json_encode($conductor), ENT_QUOTES, 'UTF-8') ?>)" data-tooltip="Ver detalles">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    <button class="btn btn-editar" onclick="editarConductor(<?= htmlspecialchars(json_encode($conductor), ENT_QUOTES, 'UTF-8') ?>)" data-tooltip="Editar">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>
                                    <form method="post" style="display:inline;" onsubmit="return confirmarEliminacion()">
                                        <input type="hidden" name="id" value="<?= $conductor['id'] ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                                        <button type="submit" class="btn btn-eliminar" data-tooltip="Eliminar">
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
        <button class="action-button" id="btnAddNew" title="Nuevo conductor">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>
    </div>
    
    <!-- Modales -->
    
    <!-- Modal Agregar Conductor -->
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
                <h3 class="modal-vehicle__title">Agregar Nuevo Conductor</h3>
            </div>
            
            <div class="modal-vehicle__body">
                <form method="post" enctype="multipart/form-data" class="modal-vehicle__form" id="modalAgregarForm">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="agregar">
                    
                    <div class="modal-vehicle__form-group">
                        <label for="fecha" class="modal-vehicle__form-label">Fecha</label>
                        <input type="date" name="fecha" id="fecha" class="modal-vehicle__form-input" placeholder="Fecha de registro" required>
                    </div>
                    <div class="modal-vehicle__form-group">
                        <label for="nombre" class="modal-vehicle__form-label">Nombre</label>
                        <input type="text" name="nombre" id="nombre" class="modal-vehicle__form-input" placeholder="Nombre completo" required>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="direccion" class="modal-vehicle__form-label">Dirección</label>
                        <input type="text" name="direccion" id="direccion" class="modal-vehicle__form-input" placeholder="Dirección completa">
                    </div>
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="telefono" class="modal-vehicle__form-label">Teléfono</label>
                            <input type="text" name="telefono" id="telefono" class="modal-vehicle__form-input" placeholder="Ej: 2222333444" required>
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="dip" class="modal-vehicle__form-label">DIP</label>
                            <input type="text" name="dip" id="dip" class="modal-vehicle__form-input" placeholder="Número DIP" required>
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="ingreso_obligatorio" class="modal-vehicle__form-label">Ingreso Obligatorio (XAF)</label>
                            <input type="number" name="ingreso_obligatorio" id="ingreso_obligatorio" class="modal-vehicle__form-input" placeholder="Ej: 14000" required step="1" min="0">
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="ingreso_libre" class="modal-vehicle__form-label">Ingreso Libre (XAF)</label>
                            <input type="number" name="ingreso_libre" id="ingreso_libre" class="modal-vehicle__form-input" placeholder="Ej: 10000" required step="1" min="0">
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="dias_por_ciclo" class="modal-vehicle__form-label">Días de trabajo</label>
                            <input type="number" name="dias_por_ciclo" id="dias_por_ciclo" class="modal-vehicle__form-input" placeholder="Ej: 30" required>
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="salario_mensual" class="modal-vehicle__form-label">Salario Mensual (XAF)</label>
                            <input type="number" name="salario_mensual" id="salario_mensual" class="modal-vehicle__form-input" placeholder="Ej: 250000" required step="1" min="0">
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="vehiculo_id" class="modal-vehicle__form-label">Vehículo Asignado</label>
                        <select name="vehiculo_id" id="vehiculo_id" class="modal-vehicle__form-input">
                            <option value="">Seleccione un vehículo</option>
                            <?php foreach ($vehiculosDisponibles as $vehiculo): ?>
                                <option value="<?= $vehiculo['id'] ?>">
                                    <?= htmlspecialchars($vehiculo['marca']) ?> <?= htmlspecialchars($vehiculo['modelo']) ?> - <?= htmlspecialchars($vehiculo['matricula']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="imagen" class="modal-vehicle__form-label">Foto del Conductor</label>
                        <input type="file" name="imagen" id="imagen" class="modal-vehicle__file-input" accept="image/*">
                        <div class="modal-vehicle__image-preview" id="preview" style="display: none;">
                            <img id="previewImg" src="#" alt="Vista previa" class="modal-vehicle__preview-image">
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
                <button type="submit" class="modal-vehicle__action-btn modal-vehicle__action-btn--primary" form="modalAgregarForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Guardar Conductor
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Conductor -->
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
                <div class="conductor-photo-container" id="conductorFotoContainer">
                    <!-- Imagen del conductor se insertará aquí -->
                </div>
                
                <div class="modal-vehicle__header-content">
                    <h3 class="modal-vehicle__title">VEHÍCULO ASIGNADO: <span id="vehiculoAsignado" class="modal-vehicle__title--highlight">No asignado</span></h3>
                    <div class="modal-vehicle__badge">
                        <span id="conductorStatusBadge">Activo</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-vehicle__body">
                <div id="detalleConductor" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
                
                <!-- Sección de historial -->
                <div id="historialContent" class="historial-container" style="display: none;">
                    <h4 class="historial-title" id="historialTitle"></h4>
                    <div id="historialData" class="historial-content">
                        <!-- Contenido dinámico del historial -->
                    </div>
                </div>
            </div>
            
            <div class="modal-vehicle__footer">
                <button class="modal-vehicle__action-btn" onclick="mostrarHistorial('deudas')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2v4"></path>
                        <path d="M5 10v4a7 7 0 0 0 14 0v-4"></path>
                        <line x1="12" y1="19" x2="12" y2="22"></line>
                    </svg>
                    Historial de Deudas
                </button>
                <button class="modal-vehicle__action-btn" onclick="mostrarHistorial('ingresos')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Historial de Ingresos
                </button>
                <button class="modal-vehicle__action-btn" onclick="mostrarHistorial('pagos')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"></path>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    Historial de Pagos
                </button>
                <button class="modal-vehicle__action-btn modal-vehicle__action-btn--primary" onclick="editarConductor()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Editar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Conductor -->
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
                <h3 class="modal-vehicle__title">Editar Conductor</h3>
            </div>
            
            <div class="modal-vehicle__body">
                <form method="post" enctype="multipart/form-data" class="modal-vehicle__form" id="modalEditarForm">
                    <input type="hidden" id="editar_id" name="id">
                    <input type="hidden" id="imagen_actual" name="imagen_actual">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="editar">
                    
                    <div class="modal-vehicle__form-group">
                        <label for="editar_fecha" class="modal-vehicle__form-label">Fecha</label>
                        <input type="date" id="editar_fecha" name="fecha" class="modal-vehicle__form-input" required>
                    </div>
                    <div class="modal-vehicle__form-group">
                        <label for="editar_nombre" class="modal-vehicle__form-label">Nombre</label>
                        <input type="text" id="editar_nombre" name="nombre" class="modal-vehicle__form-input" required>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="editar_direccion" class="modal-vehicle__form-label">Dirección</label>
                        <input type="text" id="editar_direccion" name="direccion" class="modal-vehicle__form-input">
                    </div>
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="editar_telefono" class="modal-vehicle__form-label">Teléfono</label>
                            <input type="text" id="editar_telefono" name="telefono" class="modal-vehicle__form-input" required>
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="editar_dip" class="modal-vehicle__form-label">DIP</label>
                            <input type="text" id="editar_dip" name="dip" class="modal-vehicle__form-input" required>
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="editar_ingreso_obligatorio" class="modal-vehicle__form-label">Ingreso Obligatorio (XAF)</label>
                            <input type="number" id="editar_ingreso_obligatorio" name="ingreso_obligatorio" class="modal-vehicle__form-input" required step="1" min="0">
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="editar_ingreso_libre" class="modal-vehicle__form-label">Ingreso Libre (XAF)</label>
                            <input type="number" id="editar_ingreso_libre" name="ingreso_libre" class="modal-vehicle__form-input" required step="1" min="0">
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="editar_dias_por_ciclo" class="modal-vehicle__form-label">Días de trabajo</label>
                            <input type="number" id="editar_dias_por_ciclo" name="dias_por_ciclo" class="modal-vehicle__form-input" required>
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="editar_salario_mensual" class="modal-vehicle__form-label">Salario Mensual (XAF)</label>
                            <input type="number" id="editar_salario_mensual" name="salario_mensual" class="modal-vehicle__form-input" required step="1" min="0">
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="editar_vehiculo_id" class="modal-vehicle__form-label">Vehículo Asignado</label>
                        <select name="vehiculo_id" id="editar_vehiculo_id" class="modal-vehicle__form-input">
    <option value="">Seleccione un vehículo</option>
    <!-- Las opciones se llenarán dinámicamente con JavaScript -->
</select>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="editar_imagen" class="modal-vehicle__form-label">Foto del Conductor</label>
                        <input type="file" name="editar_imagen" id="editar_imagen" class="modal-vehicle__file-input" accept="image/*">
                        <div class="modal-vehicle__image-preview" id="editar_preview">
                            <img id="editar_previewImg" src="#" alt="Vista previa" class="modal-vehicle__preview-image" style="display: none;">
                            <div id="current_image"></div>
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
                <button type="submit" class="modal-vehicle__action-btn modal-vehicle__action-btn--primary" form="modalEditarForm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Actualizar Conductor
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Variables globales
    let currentConductor = null;
    let currentPage = 1;
    const rowsPerPage = 10;
    let filteredData = [];
    
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
            // Establecer fecha actual por defecto
            document.getElementById('fecha').valueAsDate = new Date();
        });
        
        // Botón flotante agregar
        document.getElementById('btnAddNew').addEventListener('click', function() {
            abrirModal('modalAgregar');
            // Establecer fecha actual por defecto
            document.getElementById('fecha').valueAsDate = new Date();
        });
        
        // Botón flotante ir arriba
        document.getElementById('btnScrollTop').addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Evento para exportar
        document.getElementById('btnExportar').addEventListener('click', exportarDatos);
        
        // Hacer cabeceras ordenables
        document.querySelectorAll('.table th').forEach((header, index) => {
            if (index < 6) { // Excluir columna de acciones
                header.classList.add('sortable');
                header.addEventListener('click', () => {
                    const isAsc = header.classList.contains('sort-asc');
                    ordenarTabla(index, isAsc ? 'desc' : 'asc');
                });
            }
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
        
        document.getElementById('filterModelo').addEventListener('change', function() {
            currentPage = 1;
            updateTable();
        });
        
        document.getElementById('filterFecha').addEventListener('change', function() {
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
        document.getElementById('imagen')?.addEventListener('change', function(e) {
            const preview = document.getElementById('preview');
            const previewImg = document.getElementById('previewImg');
            
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
        document.getElementById('editar_imagen')?.addEventListener('change', function(e) {
            const preview = document.getElementById('editar_previewImg');
            const currentImage = document.getElementById('current_image');
            
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
        
        // Validación de teléfono y DIP
        document.getElementById('telefono')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if(this.value.length > 9) this.value = this.value.slice(0, 9);
        });
        
        document.getElementById('dip')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        document.getElementById('editar_telefono')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if(this.value.length > 9) this.value = this.value.slice(0, 9);
        });
        
        document.getElementById('editar_dip')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
    
    // Configurar paginación
    function setupPagination() {
        const totalRows = document.querySelectorAll('#tableBody tr').length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage === totalPages;
        document.getElementById('pageInfo').textContent = `Página ${currentPage} de ${totalPages}`;
    }
    
    // Función para exportar datos
// Función para exportar datos a PDF
function exportarDatos() {
    const btn = document.getElementById('btnExportar');
    const originalHtml = btn.innerHTML;
    
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generando PDF...';
    btn.disabled = true;
    
    // Usar html2pdf para generar el PDF
    const element = document.querySelector('.table-container');
    const opt = {
        margin: 10,
        filename: 'conductores.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };
    
    // Cargar la librería html2pdf si no está cargada
    if (typeof html2pdf !== 'undefined') {
        html2pdf().from(element).set(opt).save().then(() => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            mostrarNotificacion('success', 'PDF generado con éxito');
        });
    } else {
        // Cargar la librería dinámicamente
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        script.onload = () => {
            html2pdf().from(element).set(opt).save().then(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                mostrarNotificacion('success', 'PDF generado con éxito');
            });
        };
        script.onerror = () => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            mostrarNotificacion('error', 'Error al cargar la librería PDF');
        };
        document.head.appendChild(script);
    }
}
    
    // Función para mostrar notificaciones
    function mostrarNotificacion(tipo, mensaje) {
        const notificacion = document.createElement('div');
        notificacion.className = `fixed-notification alert-${tipo}`;
        notificacion.textContent = mensaje;
        document.body.appendChild(notificacion);
        
        setTimeout(() => {
            notificacion.classList.add('fade-out');
            setTimeout(() => notificacion.remove(), 500);
        }, 3000);
    }
    
    // Función para ordenar tabla
    function ordenarTabla(columna, direccion) {
        const tabla = document.getElementById('tableBody');
        const filas = Array.from(tabla.querySelectorAll('tr'));
        
        filas.sort((a, b) => {
            const valorA = a.cells[columna].textContent.trim();
            const valorB = b.cells[columna].textContent.trim();
            
            if (!isNaN(valorA)) {
                return direccion === 'asc' ? valorA - valorB : valorB - valorA;
            }
            else if (Date.parse(valorA)) {
                return direccion === 'asc' ? 
                    new Date(valorA) - new Date(valorB) : 
                    new Date(valorB) - new Date(valorA);
            }
            else {
                return direccion === 'asc' ? 
                    valorA.localeCompare(valorB) : 
                    valorB.localeCompare(valorA);
            }
        });
        
        filas.forEach(fila => tabla.appendChild(fila));
        
        document.querySelectorAll('.sortable').forEach(header => {
            header.classList.remove('sort-asc', 'sort-desc');
        });
        const header = document.querySelector(`th:nth-child(${columna + 1})`);
        header.classList.add(`sort-${direccion}`);
    }
    
    // Actualizar tabla con filtros y paginación
    function updateTable() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const marcaFilter = document.getElementById('filterMarca').value;
        const modeloFilter = document.getElementById('filterModelo').value;
        const fechaFilter = document.getElementById('filterFecha').value;
        
        const rows = document.querySelectorAll('#tableBody tr');
        filteredData = [];
        
        rows.forEach(row => {
            const nombre = row.cells[2].textContent.toLowerCase();
            const telefono = row.cells[3].textContent.toLowerCase();
            const marca = row.getAttribute('data-marca');
            const modelo = row.getAttribute('data-modelo');
            const fecha = row.getAttribute('data-fecha');
            
            const matchesSearch = nombre.includes(searchTerm) || telefono.includes(searchTerm);
            const matchesMarca = !marcaFilter || marca === marcaFilter;
            const matchesModelo = !modeloFilter || modelo === modeloFilter;
            const matchesFecha = !fechaFilter || fecha === fechaFilter;
            
            if (matchesSearch && matchesMarca && matchesModelo && matchesFecha) {
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
    }
    
    // Función para ver conductor
// Función para ver conductor - Versión corregida
function verConductor(conductor) {
    currentConductor = conductor;
    abrirModal('modalVer');
    
    // Configurar foto del conductor
    const fotoContainer = document.getElementById('conductorFotoContainer');
    fotoContainer.innerHTML = '';
    
    if (conductor.imagen) {
        fotoContainer.innerHTML = `
            <img src="../../imagenes/conductores/${conductor.imagen}" 
                 class="conductor-photo" 
                 alt="Foto del conductor"
                 loading="lazy">
        `;
    } else {
        fotoContainer.innerHTML = `
            <div style="color: #64748b; text-align: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5.52 19c.64-2.2 1.84-3 3.22-3h6.52c1.38 0 2.58.8 3.22 3"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                    <circle cx="12" cy="12" r="10"></circle>
                </svg>
                <p>No hay foto disponible</p>
            </div>
        `;
    }
    
    // Configurar vehículo asignado
    document.getElementById('vehiculoAsignado').textContent = 
        conductor.vehiculo_id ? 
        `${conductor.vehiculo_marca} ${conductor.vehiculo_modelo} - ${conductor.vehiculo_matricula}` : 
        'No asignado';
    
    // Configurar estado
    const estado = 'Activo';
    document.getElementById('conductorStatusBadge').textContent = estado;
    document.getElementById('conductorStatusBadge').style.background = '#10b981';
    
    // Calcular antigüedad
    const fechaRegistro = new Date(conductor.fecha);
    const hoy = new Date();
    const diffTime = Math.abs(hoy - fechaRegistro);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    const diffMonths = hoy.getMonth() - fechaRegistro.getMonth() + (12 * (hoy.getFullYear() - fechaRegistro.getFullYear()));
    const diffYears = hoy.getFullYear() - fechaRegistro.getFullYear();
    
    let antiguedad = '';
    if (diffYears > 0) {
        antiguedad = `${diffYears} año${diffYears > 1 ? 's' : ''}`;
        if (diffMonths % 12 > 0) {
            antiguedad += ` y ${diffMonths % 12} mes${diffMonths % 12 > 1 ? 'es' : ''}`;
        }
    } else if (diffMonths > 0) {
        antiguedad = `${diffMonths} mes${diffMonths > 1 ? 'es' : ''}`;
        if (diffDays % 30 > 0) {
            antiguedad += ` y ${diffDays % 30} día${diffDays % 30 > 1 ? 's' : ''}`;
        }
    } else {
        antiguedad = `${diffDays} día${diffDays > 1 ? 's' : ''}`;
    }
    
    // Formatear números sin decimales
// Función para formatear números sin decimales
// Función para formatear números sin decimales
const formatNumber = num => {
    // Convertir a entero (por si acaso viene con decimales de la base de datos)
    const numeroEntero = Math.round(Number(num) || 0);
    // Formatear con separadores de miles
    return numeroEntero.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
};
    
    // Datos para mostrar
    const columna1 = [
        { label: 'NOMBRE', value: conductor.nombre || 'N/A' },
        { label: 'DIRECCIÓN', value: conductor.direccion || 'N/A' },
        { label: 'TELÉFONO', value: conductor.telefono || 'N/A' },
        { label: 'DIP', value: conductor.dip || 'N/A' },
        { label: 'FECHA REGISTRO', value: conductor.fecha || 'N/A' }
    ];
    
    const columna2 = [
        { label: 'INGRESO', value: formatNumber(conductor.ingreso_obligatorio) + ' XAF' },
        { label: 'DIAS LIBRES', value: formatNumber(conductor.ingreso_libre) + ' XAF' },
        { label: 'SALARIO', value: formatNumber(conductor.salario_mensual) + ' XAF' },
        { label: 'DÍAS DE TRABAJO', value: conductor.dias_por_ciclo || 'N/A' },
        { label: 'ANTIGÜEDAD', value: antiguedad }
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
    
    // Insertar contenido
    document.getElementById('detalleConductor').innerHTML = html;
    
    // Ocultar sección de historial
    document.getElementById('historialContent').style.display = 'none';
}
    
    // Función para editar conductor
    // Función para editar conductor - Versión corregida
function editarConductor(conductor = null) {
    if (!conductor && currentConductor) {
        conductor = currentConductor;
    }
    
    if (conductor) {
        // Llenar los campos del formulario
        document.getElementById('editar_id').value = conductor.id;
        document.getElementById('editar_fecha').value = conductor.fecha;
        document.getElementById('editar_nombre').value = conductor.nombre;
        document.getElementById('editar_direccion').value = conductor.direccion || '';
        document.getElementById('editar_telefono').value = conductor.telefono;
        document.getElementById('editar_dip').value = conductor.dip;
        document.getElementById('editar_ingreso_obligatorio').value = conductor.ingreso_obligatorio;
        document.getElementById('editar_ingreso_libre').value = conductor.ingreso_libre;
        document.getElementById('editar_dias_por_ciclo').value = conductor.dias_por_ciclo;
        document.getElementById('editar_salario_mensual').value = conductor.salario_mensual;
        document.getElementById('imagen_actual').value = conductor.imagen || '';
        
        // Configurar vehículo asignado
        const vehiculoSelect = document.getElementById('editar_vehiculo_id');
        
        // Guardar el vehículo actualmente seleccionado
        const vehiculoActual = conductor.vehiculo_id || '';
        
        // Reconstruir el select con todas las opciones
        vehiculoSelect.innerHTML = '<option value="">Seleccione un vehículo</option>';
        
        // Obtener vehículos disponibles desde PHP
        const vehiculosDisponibles = <?php echo json_encode($vehiculosDisponibles); ?>;
        
        // Si el conductor tiene un vehículo asignado, agregarlo a las opciones aunque no esté disponible
        if (conductor.vehiculo_id) {
            vehiculosDisponibles.push({
                id: conductor.vehiculo_id,
                marca: conductor.vehiculo_marca,
                modelo: conductor.vehiculo_modelo,
                matricula: conductor.vehiculo_matricula
            });
        }
        
        // Llenar el select con las opciones
        vehiculosDisponibles.forEach(vehiculo => {
            const option = document.createElement('option');
            option.value = vehiculo.id;
            option.textContent = `${vehiculo.marca} ${vehiculo.modelo} - ${vehiculo.matricula}`;
            if (vehiculoActual && vehiculo.id == vehiculoActual) {
                option.selected = true;
            }
            vehiculoSelect.appendChild(option);
        });
        
        // Mostrar imagen actual
        const previewDiv = document.getElementById('current_image');
        if (conductor.imagen) {
            previewDiv.innerHTML = `
                <img src="../../imagenes/conductores/${conductor.imagen}" 
                     class="modal-vehicle__preview-image"
                     style="max-width: 100px; max-height: 80px;"
                     loading="lazy">
                <div style="font-size: 12px; color: #666; text-align: center; margin-top: 5px;">Foto actual</div>
            `;
        } else {
            previewDiv.innerHTML = '<div style="color: #999; font-size: 12px; text-align: center;">No hay foto</div>';
        }
        
        cerrarModal('modalVer');
        abrirModal('modalEditar');
    } else {
        // Si no hay conductor, abrir modal de agregar
        cerrarModal('modalVer');
        abrirModal('modalAgregar');
    }
}
    
    // Función para mostrar historial
    function mostrarHistorial(tipo) {
        if (!currentConductor) return;
        
        const historialContent = document.getElementById('historialContent');
        const historialTitle = document.getElementById('historialTitle');
        const historialData = document.getElementById('historialData');
        
        // Configurar título según el tipo de historial
        let titulo = '';
        let datosEjemplo = '';
        
        switch(tipo) {
            case 'ingresos':
                titulo = 'Historial de Ingresos';
                datosEjemplo = `
                    <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><strong>Fecha:</strong> 15/06/2023</span>
                            <span><strong>Monto:</strong> 14,000 XAF</span>
                        </div>
                        <div style="margin-top: 5px;"><strong>Descripción:</strong> Ingreso día laboral</div>
                    </div>
                    <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><strong>Fecha:</strong> 14/06/2023</span>
                            <span><strong>Monto:</strong> 14,000 XAF</span>
                        </div>
                        <div style="margin-top: 5px;"><strong>Descripción:</strong> Ingreso día laboral</div>
                    </div>
                `;
                break;
                
            case 'pagos':
                titulo = 'Historial de Pagos';
                datosEjemplo = `
                    <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><strong>Fecha:</strong> 31/05/2023</span>
                            <span><strong>Monto:</strong> 250,000 XAF</span>
                        </div>
                        <div style="margin-top: 5px;"><strong>Descripción:</strong> Pago de salario mensual</div>
                    </div>
                    <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><strong>Fecha:</strong> 30/04/2023</span>
                            <span><strong>Monto:</strong> 250,000 XAF</span>
                        </div>
                        <div style="margin-top: 5px;"><strong>Descripción:</strong> Pago de salario mensual</div>
                    </div>
                `;
                break;
                
            case 'deudas':
                titulo = 'Historial de Deudas';
                datosEjemplo = `
                    <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><strong>Fecha:</strong> 10/06/2023</span>
                            <span><strong>Monto:</strong> 50,000 XAF</span>
                        </div>
                        <div style="margin-top: 5px;"><strong>Descripción:</strong> Adelanto de salario</div>
                        <div style="margin-top: 5px;"><strong>Estado:</strong> Pendiente</div>
                    </div>
                    <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between;">
                            <span><strong>Fecha:</strong> 15/05/2023</span>
                            <span><strong>Monto:</strong> 30,000 XAF</span>
                        </div>
                        <div style="margin-top: 5px;"><strong>Descripción:</strong> Reparación vehículo</div>
                        <div style="margin-top: 5px;"><strong>Estado:</strong> Pagado</div>
                    </div>
                `;
                break;
        }
        
        historialTitle.textContent = titulo;
        historialData.innerHTML = datosEjemplo;
        historialContent.style.display = 'block';
        
        // Desplazarse a la sección de historial
        setTimeout(() => {
            historialContent.scrollIntoView({ behavior: 'smooth' });
        }, 100);
    }
    document.querySelectorAll('.no-decimal').forEach(input => {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
});
    // Confirmar eliminación
    function confirmarEliminacion() {
        return confirm('¿Estás seguro de que deseas eliminar este conductor?');
    }
    
    // Cerrar modal al hacer clic fuera del contenido
    window.onclick = function(event) {
        if (event.target.className === 'modal-vehicle__overlay') {
            const modalId = event.target.parentElement.id;
            cerrarModal(modalId);
        }
    };
    </script>
  </body>
</html>