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

// ==============================
// CONSULTA: Obtener lista de vehículos y conductor asignado
// ==============================
$vehiculos = $pdo->query("
    SELECT v.*, c.nombre AS conductor_nombre 
    FROM vehiculos v 
    LEFT JOIN conductores c ON v.id = c.vehiculo_id
")->fetchAll();
?>

<!-- ==============================
    ESTILOS CSS
============================== -->
<style>
/* Estilos generales */
.container {
    max-width: 80%;
    margin: 20px auto;
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
}

h2 {
    color: #004b87;
    font-size: 1.8rem;
    margin-bottom: 20px;
    text-align: center;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.table th, .table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.table th {
    background-color: #004b87;
    color: white;
}

.table td {
    background-color: #f9f9f9;
}

.table tr:hover {
    background-color: #f1f1f1;
}

.btn, .btn-nuevo, .btn-editar, .btn-ver {
    padding: 8px 15px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn {
    background-color: #e74c3c;
    color: white;
}

.btn-nuevo {
    background-color: #2ecc71;
    color: white;
}

.btn-editar {
    background-color: #f39c12;
    color: white;
}

.btn-ver {
    background-color: #3498db;
    color: white;
}

.modal {
    display: none;
    position: fixed;
    z-index: 999;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
    padding: 20px;
}

.modal-content {
    background-color: #fff;
    border-radius: 8px;
    width: 450px;
    max-width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    position: relative;
}

.modal-content h3 {
    margin-bottom: 20px;
    color: #004b87;
}

.modal-content input, .modal-content select, .modal-content textarea {
    width: 100%;
    padding: 10px;
    margin: 8px 0;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 1rem;
}

.modal-content button {
    width: 100%;
    padding: 12px;
    background-color: #2ecc71;
    border: none;
    color: white;
    border-radius: 6px;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 10px;
}

.modal-content button:hover {
    background-color: #27ae60;
}

.close {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 20px;
    color: #e74c3c;
    cursor: pointer;
}

.alerta-mantenimiento {
    background-color: #ffebee;
    font-weight: bold;
}

.header-table {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.label {
    font-weight: bold;
    margin-bottom: 5px;
}

/* Estilos para imágenes */
.image-preview img {
    max-width: 100px;
    max-height: 80px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

input[type="file"] {
    width: 100%;
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f9f9f9;
}

.image-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 10px;
}

.image-container {
    text-align: center;
}

/* ESTILOS PARA EL MODAL DE VER VEHÍCULO */
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
    max-width: 90%;
    max-height: 90vh;
    margin: auto;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    animation: modalFadeIn 0.3s ease-out;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-vehicle__close {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: white;
    border: 1px solid #e2e8f0;
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
    position: relative;
    padding: 24px 24px 0;
    border-bottom: 1px solid #e2e8f0;
}

.modal-vehicle__title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    margin-bottom: 16px;
}

.modal-vehicle__title--highlight {
    color: #4f46e5;
    font-weight: 600;
}

.modal-vehicle__badge span {
    display: inline-block;
    padding: 4px 20px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-right: 50px;
    background: #10b981;
    color: white;
}

.modal-vehicle__details-table {
    padding: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    overflow-y: auto;
}

.modal-vehicle__table-column {
    flex: 1;
    min-width: 250px;
}

.modal-vehicle__data-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}

.modal-vehicle__data-table tr:not(:last-child) {
    border-bottom: 1px solid #e2e8f0;
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
    border-right: 1px solid #e2e8f0;
    width: 40%;
}

.modal-vehicle__data-value {
    padding: 12px 16px;
    font-size: 1rem;
    color: #1e293b;
    font-weight: 500;
    background-color: white;
}

.modal-vehicle__footer {
    padding: 16px 24px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
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
    border: 1px solid #e2e8f0;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.2s ease;
}

.modal-vehicle__action-btn:hover {
    background: #f1f5f9;
}

.modal-vehicle__action-btn svg {
    width: 14px;
    height: 14px;
}

.modal-vehicle__action-btn--primary {
    background: #4f46e5;
    border-color: #4f46e5;
    color: white;
}

.modal-vehicle__action-btn--primary:hover {
    background: #6366f1;
}

/* Estilos para el carrusel de imágenes */
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

@media (max-width: 640px) {
    .modal-vehicle__container {
        max-width: 95%;
    }
    
    .modal-vehicle__details-table {
        flex-direction: column;
        gap: 0;
    }
    
    .modal-vehicle__table-column {
        width: 100%;
    }
    
    .modal-vehicle__header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .modal-vehicle__footer {
        flex-direction: column;
    }
    
    .modal-vehicle__action-btn {
        justify-content: center;
    }
    
    .image-grid {
        grid-template-columns: 1fr;
    }
    
    .vehicle-carousel {
        height: 150px;
    }
}
</style>

<!-- ==============================
    INTERFAZ PRINCIPAL
============================== -->
<div class="container">
    <h2>Lista de Vehículos</h2>
    <div class="header-table">
        <button id="openModalAgregar" class="btn btn-nuevo">+ Nuevo Vehículo</button>
    </div>

    <table class="table">
        <tr>
 <!--           <th>ID</th>-->
            <th>Marca</th><th>Modelo</th><th>Matrícula</th><th>Número</th>
            <th>Km Inicial</th><th>Km Actual</th><th>Próx. Mantenimiento</th>
            <th>Conductor</th><th>Acciones</th>
        </tr>
        <?php foreach ($vehiculos as $vehiculo): ?>
            <?php
                $diferencia_km = $vehiculo['km_aceite'] - $vehiculo['km_actual'];
                $clase_alerta = ($diferencia_km <= 500) ? 'alerta-mantenimiento' : '';
            ?>
            <tr class="<?= $clase_alerta ?>">
 <!--               <td><?= $vehiculo['id'] ?></td>-->
                <td><?= $vehiculo['marca'] ?></td>
                <td><?= $vehiculo['modelo'] ?></td>
                <td><?= $vehiculo['matricula'] ?></td>
                <td><?= $vehiculo['numero'] ?></td>
                <td><?= number_format($vehiculo['km_inicial'], 0, ',', '.') ?> km</td>
                <td><?= number_format($vehiculo['km_actual'], 0, ',', '.') ?> km</td>
                <td><?= number_format($vehiculo['km_aceite'], 0, ',', '.') ?> km</td>
                <td><?= $vehiculo['conductor_nombre'] ?? 'No asignado' ?></td>
                <td>
                    <button class="btn-ver" onclick="verVehiculo(<?= htmlspecialchars(json_encode($vehiculo), ENT_QUOTES, 'UTF-8') ?>)">Ver</button>
                    <button class="btn-editar" onclick="editarVehiculo(<?= htmlspecialchars(json_encode($vehiculo), ENT_QUOTES, 'UTF-8') ?>)">Editar</button>
                    <form method="post" style="display:inline;" onsubmit="return confirmarEliminacion();">
                        <input type="hidden" name="id" value="<?= $vehiculo['id'] ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <button type="submit" class="btn">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- ==============================
    MODAL: AGREGAR VEHÍCULO
============================== -->
<div id="modalAgregar" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal('modalAgregar')">&times;</span>
        <h3>Agregar Vehículo</h3>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="marca" class="label">Marca</label>
                <input type="text" name="marca" placeholder="Marca" required>
            </div>
            <div class="form-group">
                <label for="modelo" class="label">Modelo</label>
                <input type="text" name="modelo" placeholder="Modelo" required>
            </div>
            <div class="form-group">
                <label for="matricula" class="label">Matrícula</label>
                <input type="text" name="matricula" placeholder="Matrícula" required>
            </div>
            <div class="form-group">
                <label for="numero" class="label">Número</label>
                <input type="text" name="numero" placeholder="Número" required>
            </div>
            <div class="form-group">
                <label for="km_inicial" class="label">Km Inicial</label>
                <input type="number" name="km_inicial" placeholder="Km Inicial" required>
            </div>
            <div class="form-group">
                <label for="km_actual" class="label">Km Actual</label>
                <input type="number" name="km_actual" placeholder="Km Actual" required>
            </div>
            <div class="form-group">
                <label for="km_aceite" class="label">Km Próximo Mantenimiento</label>
                <input type="number" name="km_aceite" placeholder="Km Mantenimiento" required>
            </div>
            
            <!-- Sección para subir imágenes -->
            <div class="form-group">
                <label class="label">Imágenes del Vehículo</label>
                <div class="image-grid">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="image-container">
                            <label for="imagen<?= $i ?>" class="label">Imagen <?= $i ?></label>
                            <input type="file" name="imagen<?= $i ?>" id="imagen<?= $i ?>" accept="image/*">
                            <div class="image-preview" id="preview<?= $i ?>" style="margin-top: 5px; display: none;">
                                <img id="previewImg<?= $i ?>" src="#" alt="Vista previa">
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <button type="submit" name="accion" value="agregar">Guardar Vehículo</button>
        </form>
    </div>
</div>

<!-- ==============================
    MODAL: VER VEHÍCULO
============================== -->
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
            <h3 class="modal-vehicle__title">CONDUCTOR ASIGNADO: <span id="nombreConductor" class="modal-vehicle__title--highlight">No asignado</span></h3>
            <div class="modal-vehicle__badge">
                <span id="vehicleStatusBadge">Activo</span>
            </div>
        </div>
        
        <div id="detalleVehiculo" class="modal-vehicle__details-table">
            <!-- Contenido dinámico aquí -->
        </div>
        
        <div class="modal-vehicle__footer">
            <button class="modal-vehicle__action-btn" onclick="mostrarHistorial()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                Historial
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

<!-- ==============================
    MODAL: EDITAR VEHÍCULO
============================== -->
<div id="modalEditar" class="modal">
    <div class="modal-content">
        <span class="close" onclick="cerrarModal('modalEditar')">&times;</span>
        <h3>Editar Vehículo</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" id="editar_id" name="id">
            <div class="form-group">
                <label for="editar_marca" class="label">Marca</label>
                <input type="text" id="editar_marca" name="marca" required>
            </div>
            <div class="form-group">
                <label for="editar_modelo" class="label">Modelo</label>
                <input type="text" id="editar_modelo" name="modelo" required>
            </div>
            <div class="form-group">
                <label for="editar_matricula" class="label">Matrícula</label>
                <input type="text" id="editar_matricula" name="matricula" required>
            </div>
            <div class="form-group">
                <label for="editar_numero" class="label">Número</label>
                <input type="text" id="editar_numero" name="numero" required>
            </div>
            <div class="form-group">
                <label for="editar_km_inicial" class="label">Km Inicial</label>
                <input type="number" id="editar_km_inicial" name="km_inicial" required>
            </div>
            <div class="form-group">
                <label for="editar_km_actual" class="label">Km Actual</label>
                <input type="number" id="editar_km_actual" name="km_actual" required>
            </div>
            <div class="form-group">
                <label for="editar_km_aceite" class="label">Km Próximo Mantenimiento</label>
                <input type="number" id="editar_km_aceite" name="km_aceite" required>
            </div>
            
            <!-- Sección para editar imágenes -->
            <div class="form-group">
                <label class="label">Imágenes del Vehículo</label>
                <div class="image-grid">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                        <div class="image-container">
                            <label for="editar_imagen<?= $i ?>" class="label">Imagen <?= $i ?></label>
                            <input type="file" name="editar_imagen<?= $i ?>" id="editar_imagen<?= $i ?>" accept="image/*">
                            <input type="hidden" name="imagen_actual<?= $i ?>" id="imagen_actual<?= $i ?>">
                            <div class="image-preview" id="editar_preview<?= $i ?>" style="margin-top: 5px;">
                                <img id="editar_previewImg<?= $i ?>" src="#" alt="Vista previa" style="display: none;">
                                <div id="current_image<?= $i ?>"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <button type="submit" name="accion" value="editar">Actualizar Vehículo</button>
        </form>
    </div>
</div>

<!-- ==============================
    SCRIPTS JS
============================== -->
<script>
// Variables globales para el carrusel
let currentImageIndex = 0;
let totalImages = 0;

// Función para abrir el modal de agregar vehículo
document.getElementById('openModalAgregar').onclick = function() {
    document.getElementById('modalAgregar').style.display = 'flex';
};

// Función para cerrar el modal
function cerrarModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    // Reiniciar el carrusel al cerrar el modal
    currentImageIndex = 0;
    totalImages = 0;
}

// Función para editar vehículo
function editarVehiculo(vehiculo) {
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
                <div style="margin-top: 5px;">
                    <img src="../../imagenes/vehiculos/${vehiculo[imgField]}" 
                         style="max-width: 100px; max-height: 80px; border: 1px solid #ddd;">
                    <div style="font-size: 12px; color: #666;">Imagen actual</div>
                </div>
            `;
        } else {
            previewDiv.innerHTML = '<div style="color: #999; font-size: 12px;">No hay imagen</div>';
        }
    }
    
    document.getElementById('modalEditar').style.display = 'flex';
}

// Función para mostrar detalles del vehículo
function verVehiculo(vehiculo) {
    // Datos para la primera columna
    const columna1 = [
        { label: 'MARCA', value: vehiculo.marca || 'N/A' },
        { label: 'MODELO', value: vehiculo.modelo || 'N/A' },
        { label: 'MATRÍCULA', value: vehiculo.matricula || 'N/A' },
        { label: 'NÚMERO', value: vehiculo.numero || 'N/A' },
        { label: 'KILOMETRAJE ACTUAL', value: (vehiculo.km_actual || '0') + ' km' },
        { label: 'PRÓXIMO MANTENIMIENTO', value: (vehiculo.km_aceite || '0') + ' km' }
    ];
    
    // Datos para la segunda columna
    const columna2 = [
        { label: 'KM INICIAL', value: (vehiculo.km_inicial || '0') + ' km' },
        { label: 'ESTADO', value: vehiculo.estado || 'Activo' },
        { label: 'FECHA REGISTRO', value: vehiculo.fecha_registro || 'N/A' },
        { label: 'ÚLTIMO MANTENIMIENTO', value: vehiculo.ultimo_mantenimiento || 'N/A' },
        { label: 'OBSERVACIONES', value: vehiculo.observaciones || 'Ninguna' }
    ];
    
    // Actualizar conductor en el header
    document.getElementById('nombreConductor').textContent = vehiculo.conductor_nombre || 'No asignado';
    
    // Configurar el estado del badge
    const estado = vehiculo.estado || 'Activo';
    let badgeColor = '#10b981'; // Verde por defecto (Activo)
    
    if (estado.toLowerCase() === 'mantenimiento') {
        badgeColor = '#f59e0b'; // Amarillo
    } else if (estado.toLowerCase() === 'inactivo') {
        badgeColor = '#ef4444'; // Rojo
    }
    
    document.getElementById('vehicleStatusBadge').textContent = estado;
    document.getElementById('vehicleStatusBadge').style.background = badgeColor;
    
    // Generar HTML para el carrusel de imágenes
    let carouselHTML = `
        <div class="vehicle-carousel">
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
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                </button>
                <button class="vehicle-carousel__btn" onclick="nextImage()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </button>
            </div>
            <div class="vehicle-carousel__counter" id="imageCounter">1/${hasImages ? document.querySelectorAll('.vehicle-carousel__image').length : '0'}</div>
            ` : ''}
        </div>
    `;
    
    // Insertar el carrusel en el header
    const header = document.querySelector('.modal-vehicle__header');
    header.insertAdjacentHTML('afterbegin', carouselHTML);
    
    // Generar HTML para las tablas
    const html = `
        <div class="modal-vehicle__table-column">
            <table class="modal-vehicle__data-table">
                ${columna1.map(item => `
                    <tr>
                        <td class="modal-vehicle__data-label">${item.label}</td>
                        <td class="modal-vehicle__data-value">${item.value}</td>
                    </tr>
                `).join('')}
            </table>
        </div>
        <div class="modal-vehicle__table-column">
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
    document.getElementById('modalVer').style.display = 'block';
    
    // Inicializar el carrusel si hay imágenes
    if (hasImages) {
        initCarousel();
    }
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

function mostrarHistorial() {
    alert('Mostrar historial del vehículo');
}

// Cerrar modal al hacer clic fuera del contenido
window.onclick = function(event) {
    if (event.target.className === 'modal' || event.target.className === 'modal-vehicle__overlay') {
        const modalId = event.target === document.querySelector('.modal-vehicle__overlay') ? 'modalVer' : event.target.id;
        cerrarModal(modalId);
    }
};

function confirmarEliminacion() {
    return confirm('¿Estás seguro de que deseas eliminar este vehículo?');
}

// Previsualización de imágenes al seleccionarlas (para agregar)
for (let i = 1; i <= 4; i++) {
    document.getElementById(`imagen${i}`).addEventListener('change', function(e) {
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
}

// Previsualización de imágenes al seleccionarlas (para editar)
for (let i = 1; i <= 4; i++) {
    document.getElementById(`editar_imagen${i}`).addEventListener('change', function(e) {
        const preview = document.getElementById(`editar_previewImg${i}`);
        
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });
}
</script>

<?php include '../../layout/footer.php'; ?>