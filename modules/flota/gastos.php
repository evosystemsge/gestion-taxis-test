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

// Configuración de imágenes para gastos
$uploadDir = '../../imagenes/gastos/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Lógica para agregar/editar/eliminar gastos
if (isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion == 'agregar') {
        try {
            $pdo->beginTransaction();
            
            // Procesar imagen del gasto
            $imagen = null;
            if (!empty($_FILES['imagen']['name'])) {
                $fileName = uniqid() . '_' . basename($_FILES['imagen']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $targetPath)) {
                    $imagen = $fileName;
                }
            }
            
            // Insertar gasto principal
            $stmt = $pdo->prepare("INSERT INTO gastos 
                (fecha, caja_id, tipo, descripcion, total, vehiculo_id, imagen) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                validarEntrada($_POST['fecha']),
                1, // Caja predeterminada
                validarEntrada($_POST['tipo']),
                validarEntrada($_POST['descripcion']),
                validarEntrada($_POST['total']),
                $_POST['vehiculo_id'] ?: null,
                $imagen
            ]);
            
            $gastoId = $pdo->lastInsertId();
            
            // Procesar líneas de productos si existen
            if (isset($_POST['productos'])) {
                foreach ($_POST['productos'] as $producto) {
                    $stmt = $pdo->prepare("INSERT INTO compras_productos 
                        (gasto_id, producto_id, cantidad, precio_unitario) 
                        VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $gastoId,
                        $producto['id'],
                        $producto['cantidad'],
                        $producto['precio']
                    ]);
                    
                    // Actualizar stock del producto (en la parte de agregar gasto)
$stmt = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
$stmt->execute([
    $producto['stock'], // Usamos el valor de stock en lugar de cantidad
    $producto['id']
]);
                }
            }
            
            // Registrar movimiento en caja
            $stmt = $pdo->prepare("INSERT INTO movimientos_caja 
                (caja_id, gasto_id, tipo, monto, descripcion, fecha) 
                VALUES (?, ?, 'egreso', ?, ?, ?)");
            $stmt->execute([
                1, // Caja predeterminada
                $gastoId,
                validarEntrada($_POST['total']),
                "Gasto: " . validarEntrada($_POST['descripcion']),
                validarEntrada($_POST['fecha'])
            ]);
            
            // Actualizar saldo de caja
            $stmt = $pdo->prepare("UPDATE cajas SET saldo_actual = saldo_actual - ? WHERE id = 1");
            $stmt->execute([validarEntrada($_POST['total'])]);
            
            $pdo->commit();
            
            header("Location: gastos.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error al registrar el gasto: " . $e->getMessage());
        }

    } elseif ($accion == 'eliminar') {
        $id = $_POST['id'];
        
        try {
            $pdo->beginTransaction();
            
            // Obtener datos del gasto
            $stmt = $pdo->prepare("SELECT total, imagen FROM gastos WHERE id = ?");
            $stmt->execute([$id]);
            $gasto = $stmt->fetch();
            
            if (!$gasto) {
                die("Gasto no encontrado");
            }
            
            // Revertir compras de productos (si existen)
            $stmt = $pdo->prepare("SELECT producto_id, cantidad FROM compras_productos WHERE gasto_id = ?");
            $stmt->execute([$id]);
            $compras = $stmt->fetchAll();
            
            foreach ($compras as $compra) {
                $stmt = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$compra['cantidad'], $compra['producto_id']]);
            }
            
            // Eliminar compras asociadas
            $stmt = $pdo->prepare("DELETE FROM compras_productos WHERE gasto_id = ?");
            $stmt->execute([$id]);
            
            // Eliminar movimiento de caja
            $stmt = $pdo->prepare("DELETE FROM movimientos_caja WHERE gasto_id = ?");
            $stmt->execute([$id]);
            
            // Revertir saldo en caja
            $stmt = $pdo->prepare("UPDATE cajas SET saldo_actual = saldo_actual + ? WHERE id = 1");
            $stmt->execute([$gasto['total']]);
            
            // Eliminar imagen si existe
            if (!empty($gasto['imagen'])) {
                $filePath = $uploadDir . $gasto['imagen'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Eliminar gasto principal
            $stmt = $pdo->prepare("DELETE FROM gastos WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            
            echo "<script>alert('Gasto eliminado correctamente.'); window.location.href='gastos.php';</script>";
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error al eliminar el gasto: " . $e->getMessage());
        }
    } elseif ($accion == 'editar') {
        $gastoId = $_POST['id'];
        
        try {
            $pdo->beginTransaction();
            
            // Obtener datos actuales del gasto
            $stmt = $pdo->prepare("SELECT total, imagen FROM gastos WHERE id = ?");
            $stmt->execute([$gastoId]);
            $gastoActual = $stmt->fetch();
            
            // Procesar imagen
            $imagen = $gastoActual['imagen'];
            if (!empty($_FILES['editar_imagen']['name'])) {
                // Eliminar imagen anterior si existe
                if (!empty($gastoActual['imagen'])) {
                    $oldImagePath = $uploadDir . $gastoActual['imagen'];
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
            }
            
            // Actualizar gasto principal
            $stmt = $pdo->prepare("UPDATE gastos SET 
                fecha = ?, tipo = ?, descripcion = ?, total = ?, vehiculo_id = ?, imagen = ? 
                WHERE id = ?");
            $stmt->execute([
                validarEntrada($_POST['fecha']),
                validarEntrada($_POST['tipo']),
                validarEntrada($_POST['descripcion']),
                validarEntrada($_POST['total']),
                $_POST['vehiculo_id'] ?: null,
                $imagen,
                $gastoId
            ]);
            
            // Calcular diferencia para actualizar caja
            $diferencia = $gastoActual['total'] - $_POST['total'];
            
            if ($diferencia != 0) {
                // Actualizar movimiento en caja
                $stmt = $pdo->prepare("UPDATE movimientos_caja SET 
                    monto = monto - ?, descripcion = ?, fecha = ? 
                    WHERE gasto_id = ?");
                $stmt->execute([
                    $diferencia,
                    "Gasto: " . validarEntrada($_POST['descripcion']),
                    validarEntrada($_POST['fecha']),
                    $gastoId
                ]);
                
                // Actualizar saldo de caja
                $stmt = $pdo->prepare("UPDATE cajas SET saldo_actual = saldo_actual + ? WHERE id = 1");
                $stmt->execute([$diferencia]);
            }
            
            // Procesar líneas de productos (eliminar las antiguas y crear nuevas)
            // Primero revertir stock de productos antiguos
            $stmt = $pdo->prepare("SELECT producto_id, cantidad FROM compras_productos WHERE gasto_id = ?");
            $stmt->execute([$gastoId]);
            $comprasAntiguas = $stmt->fetchAll();
            
            foreach ($comprasAntiguas as $compra) {
                $stmt = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$compra['cantidad'], $compra['producto_id']]);
            }
            
            // Eliminar compras antiguas
            $stmt = $pdo->prepare("DELETE FROM compras_productos WHERE gasto_id = ?");
            $stmt->execute([$gastoId]);
            
            // Agregar nuevas compras si existen
            if (isset($_POST['productos'])) {
                foreach ($_POST['productos'] as $producto) {
                    $stmt = $pdo->prepare("INSERT INTO compras_productos 
                        (gasto_id, producto_id, cantidad, precio_unitario) 
                        VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $gastoId,
                        $producto['id'],
                        $producto['cantidad'],
                        $producto['precio']
                    ]);
                    
                    // Actualizar stock del producto
                    $stmt = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                    $stmt->execute([
                        $producto['cantidad'],
                        $producto['id']
                    ]);
                }
            }
            
            $pdo->commit();
            
            header("Location: gastos.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error al actualizar el gasto: " . $e->getMessage());
        }
    }
}

// Obtener todos los gastos con información relacionada
$gastos = $pdo->query("
    SELECT g.*, v.marca AS vehiculo_marca, v.modelo AS vehiculo_modelo, 
           v.matricula AS vehiculo_matricula, v.numero AS vehiculo_numero,
           c.saldo_actual
    FROM gastos g
    LEFT JOIN vehiculos v ON g.vehiculo_id = v.id
    LEFT JOIN cajas c ON g.caja_id = c.id
    ORDER BY g.fecha DESC
")->fetchAll();

// Obtener vehículos para filtros y formularios
$vehiculos = $pdo->query("SELECT * FROM vehiculos ORDER BY marca, modelo")->fetchAll();

// Obtener productos para formularios
$productos = $pdo->query("SELECT * FROM productos ORDER BY nombre")->fetchAll();

// Obtener tipos de gastos únicos para filtros
$tiposGastos = $pdo->query("SELECT DISTINCT tipo FROM gastos ORDER BY tipo")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Gastos</title>
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
    
    /* En la sección de estilos */
.productos-table th:nth-child(5),
.productos-table td:nth-child(5) {
    width: 120px;
}

.productos-table input[type="number"] {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid var(--color-borde);
    border-radius: 4px;
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
    
    /* Estilo para gastos importantes */
    .gasto-importante {
        background-color: #ffebee !important; /* Fondo rojo claro */
        font-weight: bold;
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
    
    /* ============ TABLA DE PRODUCTOS ============ */
    .productos-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    .productos-table th {
        background-color: #f1f5f9;
        padding: 8px 12px;
        text-align: left;
        font-size: 0.8rem;
        color: #64748b;
    }
    
    .productos-table td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--color-borde);
    }
    
    .productos-table input {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid var(--color-borde);
        border-radius: 4px;
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
    }
    
    /* ============ ESTILOS ESPECÍFICOS PARA GASTOS ============ */
    .total-display {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--color-primario);
        text-align: right;
        margin-top: 15px;
        padding: 10px;
        background-color: #f8fafc;
        border-radius: 6px;
    }
    
    .add-product-btn {
        background-color: var(--color-exito);
        color: white;
        padding: 6px 12px;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        margin-top: 10px;
    }
    
    .remove-product-btn {
        background-color: var(--color-peligro);
        color: white;
        border: none;
        border-radius: 4px;
        width: 25px;
        height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    
    .product-row {
        transition: all 0.2s ease;
    }
    
    .product-row:hover {
        background-color: rgba(0, 75, 135, 0.05);
    }
    
    /* ============ IMAGEN DEL GASTO ============ */
    .gasto-image-container {
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
    
    .gasto-image {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    </style>
</head>
<body>
    <div class="container">
        <h2>Lista de Gastos</h2>
        
        <!-- Controles de tabla -->
        <div class="table-controls">
            <input type="text" id="searchInput" placeholder="Buscar gasto..." class="modal-vehicle__form-input">
            
            <select id="filterTipo" class="modal-vehicle__form-input">
                <option value="">Todos los tipos</option>
                <?php foreach ($tiposGastos as $tipo): ?>
                    <option value="<?= htmlspecialchars($tipo['tipo']) ?>"><?= htmlspecialchars($tipo['tipo']) ?></option>
                <?php endforeach; ?>
            </select>
            
            <select id="filterVehiculo" class="modal-vehicle__form-input">
                <option value="">Todos los vehículos</option>
                <?php foreach ($vehiculos as $vehiculo): ?>
                    <option value="<?= $vehiculo['id'] ?>">
                        <?= htmlspecialchars($vehiculo['marca']) ?> <?= htmlspecialchars($vehiculo['modelo']) ?> - <?= htmlspecialchars($vehiculo['matricula']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" id="filterFecha" class="modal-vehicle__form-input">
            
            <button id="openModalAgregar" class="btn btn-nuevo">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Nuevo Gasto
            </button>
        </div>
        
        <!-- Tabla de gastos -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Vehículo</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php foreach ($gastos as $gasto): ?>
                        <tr class="<?= $gasto['total'] > 50000 ? 'gasto-importante' : '' ?>" 
                            data-tipo="<?= htmlspecialchars($gasto['tipo']) ?>"
                            data-vehiculo="<?= $gasto['vehiculo_id'] ?: '' ?>"
                            data-fecha="<?= $gasto['fecha'] ?>">
                            <td><?= $gasto['id'] ?></td>
                            <td><?= htmlspecialchars($gasto['fecha']) ?></td>
                            <td><?= htmlspecialchars($gasto['tipo']) ?></td>
                            <td><?= htmlspecialchars($gasto['descripcion']) ?></td>
                            <td>
                                <?php if ($gasto['vehiculo_id']): ?>
                                    <?= htmlspecialchars($gasto['vehiculo_marca']) ?> <?= htmlspecialchars($gasto['vehiculo_modelo']) ?>
                                <?php else: ?>
                                    General
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($gasto['total'], 2, ',', '.') ?> XAF</td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <button class="btn btn-ver" onclick="verGasto(<?= htmlspecialchars(json_encode($gasto), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    <button class="btn btn-editar" onclick="editarGasto(<?= htmlspecialchars(json_encode($gasto), ENT_QUOTES, 'UTF-8') ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>
                                    <form method="post" style="display:inline;" onsubmit="return confirmarEliminacion();">
                                        <input type="hidden" name="id" value="<?= $gasto['id'] ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
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
        <button class="action-button" id="btnAddNew" title="Nuevo gasto">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
        </button>
    </div>
    
    <!-- ============ MODALES ============ -->
    
    <!-- Modal Agregar Gasto -->
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
                <h3 class="modal-vehicle__title">Registrar Nuevo Gasto</h3>
            </div>
            
            <div class="modal-vehicle__body">
                <form method="post" enctype="multipart/form-data" class="modal-vehicle__form" id="modalAgregarForm">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="agregar">
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="fecha" class="modal-vehicle__form-label">Fecha</label>
                            <input type="date" name="fecha" id="fecha" class="modal-vehicle__form-input" required value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="tipo" class="modal-vehicle__form-label">Tipo de Gasto</label>
                            <select name="tipo" id="tipo" class="modal-vehicle__form-input" required>
                                <option value="">Seleccione un tipo</option>
                                <option value="Avería">Avería</option>
                                <option value="Incidencia">Incidencia</option>
                                <option value="Gasto General">Gasto General</option>
                                <option value="Mantenimiento">Mantenimiento</option>
                                <option value="Compra">Compra</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="descripcion" class="modal-vehicle__form-label">Descripción</label>
                        <input type="text" name="descripcion" id="descripcion" class="modal-vehicle__form-input" placeholder="Descripción detallada del gasto" required>
                    </div>
                    <!-- Agrega esto en el formulario de agregar gasto (modalAgregar) -->
<div class="modal-vehicle__form-row">
    <div class="modal-vehicle__form-group">
        <label for="monto_directo" class="modal-vehicle__form-label">Monto Directo (para gastos sin productos)</label>
        <input type="number" name="monto_directo" id="monto_directo" class="modal-vehicle__form-input" step="0.01" min="0" value="0">
    </div>
</div>
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="vehiculo_id" class="modal-vehicle__form-label">Vehículo (opcional)</label>
                            <select name="vehiculo_id" id="vehiculo_id" class="modal-vehicle__form-input">
                                <option value="">Seleccione un vehículo</option>
                                <?php foreach ($vehiculos as $vehiculo): ?>
                                    <option value="<?= $vehiculo['id'] ?>">
                                        <?= htmlspecialchars($vehiculo['marca']) ?> <?= htmlspecialchars($vehiculo['modelo']) ?> - <?= htmlspecialchars($vehiculo['matricula']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="imagen" class="modal-vehicle__form-label">Comprobante (opcional)</label>
                            <input type="file" name="imagen" id="imagen" class="modal-vehicle__file-input" accept="image/*">
                            <div class="modal-vehicle__image-preview" id="preview" style="display: none;">
                                <img id="previewImg" src="#" alt="Vista previa" class="modal-vehicle__preview-image">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabla de productos -->
                    <div class="modal-vehicle__form-group">
                        <label class="modal-vehicle__form-label">Productos/Conceptos</label>
                        <table class="productos-table" id="productosTable">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Agregar al Stock</th>
                                    <th>Precio</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="productosBody">
                                <!-- Filas de productos se agregarán aquí -->
                            </tbody>
                        </table>
                        <button type="button" class="add-product-btn" id="addProductBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Agregar Producto/Concepto
                        </button>
                    </div>
                    
                    <div class="total-display">
                        Total: <span id="totalDisplay">0.00</span> XAF
                        <input type="hidden" name="total" id="total" value="0">
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
                    Registrar Gasto
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Gasto -->
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
                <div id="gastoImageContainer" class="gasto-image-container">
                    <!-- Imagen del gasto se insertará aquí -->
                </div>
                
                <div class="modal-vehicle__header-content">
                    <h3 class="modal-vehicle__title">VEHÍCULO: <span id="vehiculoAsignado" class="modal-vehicle__title--highlight">No asignado</span></h3>
                    <div class="modal-vehicle__badge">
                        <span id="gastoStatusBadge">Registrado</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-vehicle__body">
                <div id="detalleGasto" style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <!-- Contenido dinámico aquí -->
                </div>
                
                <!-- Sección de productos -->
                <div id="productosContent" style="margin-top: 20px; display: none;">
                    <h4 style="color: var(--color-primario); margin-bottom: 15px;">Productos/Conceptos</h4>
                    <table class="productos-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="productosDetalle">
                            <!-- Contenido dinámico de productos -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="modal-vehicle__footer">
                <button class="modal-vehicle__action-btn modal-vehicle__action-btn--primary" onclick="editarGasto()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    Editar
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Gasto -->
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
                <h3 class="modal-vehicle__title">Editar Gasto</h3>
            </div>
            
            <div class="modal-vehicle__body">
                <form method="post" enctype="multipart/form-data" class="modal-vehicle__form" id="modalEditarForm">
                    <input type="hidden" id="editar_id" name="id">
                    <input type="hidden" id="imagen_actual" name="imagen_actual">
                    <input type="hidden" name="csrf_token" value="<?= generarTokenCSRF() ?>">
                    <input type="hidden" name="accion" value="editar">
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="editar_fecha" class="modal-vehicle__form-label">Fecha</label>
                            <input type="date" id="editar_fecha" name="fecha" class="modal-vehicle__form-input" required>
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="editar_tipo" class="modal-vehicle__form-label">Tipo de Gasto</label>
                            <select name="tipo" id="editar_tipo" class="modal-vehicle__form-input" required>
                                <option value="">Seleccione un tipo</option>
                                <option value="Avería">Avería</option>
                                <option value="Incidencia">Incidencia</option>
                                <option value="Gasto General">Gasto General</option>
                                <option value="Mantenimiento">Mantenimiento</option>
                                <option value="Compra">Compra</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-vehicle__form-group">
                        <label for="editar_descripcion" class="modal-vehicle__form-label">Descripción</label>
                        <input type="text" id="editar_descripcion" name="descripcion" class="modal-vehicle__form-input" required>
                    </div>
                    
                    <div class="modal-vehicle__form-row">
                        <div class="modal-vehicle__form-group">
                            <label for="editar_vehiculo_id" class="modal-vehicle__form-label">Vehículo (opcional)</label>
                            <select name="vehiculo_id" id="editar_vehiculo_id" class="modal-vehicle__form-input">
                                <option value="">Seleccione un vehículo</option>
                                <?php foreach ($vehiculos as $vehiculo): ?>
                                    <option value="<?= $vehiculo['id'] ?>">
                                        <?= htmlspecialchars($vehiculo['marca']) ?> <?= htmlspecialchars($vehiculo['modelo']) ?> - <?= htmlspecialchars($vehiculo['matricula']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="modal-vehicle__form-group">
                            <label for="editar_imagen" class="modal-vehicle__form-label">Comprobante (opcional)</label>
                            <input type="file" name="editar_imagen" id="editar_imagen" class="modal-vehicle__file-input" accept="image/*">
                            <div class="modal-vehicle__image-preview" id="editar_preview">
                                <img id="editar_previewImg" src="#" alt="Vista previa" class="modal-vehicle__preview-image" style="display: none;">
                                <div id="current_image"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabla de productos -->
                    <div class="modal-vehicle__form-group">
                        <label class="modal-vehicle__form-label">Productos/Conceptos</label>
                        <table class="productos-table" id="editarProductosTable">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unitario</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="editarProductosBody">
                                <!-- Filas de productos se agregarán aquí -->
                            </tbody>
                        </table>
                        <button type="button" class="add-product-btn" id="editarAddProductBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            Agregar Producto/Concepto
                        </button>
                    </div>
                    
                    <div class="total-display">
                        Total: <span id="editarTotalDisplay">0.00</span> XAF
                        <input type="hidden" name="total" id="editarTotal" value="0">
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
                    Actualizar Gasto
                </button>
            </div>
        </div>
    </div>
    
    <script>
    // Variables globales
    let currentGasto = null;
    let currentPage = 1;
    const rowsPerPage = 10;
    let filteredData = [];
    let productos = <?php echo json_encode($productos); ?>;
    let nextProductId = 0;
    
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
            // Agregar primera fila de producto vacía
            agregarFilaProducto('productosBody');
        });
        
        // Botón flotante agregar
        document.getElementById('btnAddNew').addEventListener('click', function() {
            abrirModal('modalAgregar');
            // Agregar primera fila de producto vacía
            agregarFilaProducto('productosBody');
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
        document.getElementById('filterTipo').addEventListener('change', function() {
            currentPage = 1;
            updateTable();
        });
        
        document.getElementById('filterVehiculo').addEventListener('change', function() {
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
        
        // Agregar fila de producto
        document.getElementById('addProductBtn')?.addEventListener('click', function() {
            agregarFilaProducto('productosBody');
        });
        
        // Agregar fila de producto en edición
        document.getElementById('editarAddProductBtn')?.addEventListener('click', function() {
            agregarFilaProducto('editarProductosBody');
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
    
    // Actualizar tabla con filtros y paginación
    function updateTable() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const tipoFilter = document.getElementById('filterTipo').value;
        const vehiculoFilter = document.getElementById('filterVehiculo').value;
        const fechaFilter = document.getElementById('filterFecha').value;
        
        const rows = document.querySelectorAll('#tableBody tr');
        filteredData = [];
        
        rows.forEach(row => {
            const descripcion = row.cells[3].textContent.toLowerCase();
            const tipo = row.getAttribute('data-tipo');
            const vehiculo = row.getAttribute('data-vehiculo');
            const fecha = row.getAttribute('data-fecha');
            
            const matchesSearch = descripcion.includes(searchTerm);
            const matchesTipo = !tipoFilter || tipo === tipoFilter;
            const matchesVehiculo = !vehiculoFilter || vehiculo === vehiculoFilter;
            const matchesFecha = !fechaFilter || fecha === fechaFilter;
            
            if (matchesSearch && matchesTipo && matchesVehiculo && matchesFecha) {
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
    
    // Función para ver gasto
    function verGasto(gasto) {
        currentGasto = gasto;
        
        // Configurar vehículo asignado
        document.getElementById('vehiculoAsignado').textContent = 
            gasto.vehiculo_id ? 
            `${gasto.vehiculo_marca} ${gasto.vehiculo_modelo} - ${gasto.vehiculo_matricula}` : 
            'No asignado';
        
        // Configurar imagen del gasto
        const imageContainer = document.getElementById('gastoImageContainer');
        imageContainer.innerHTML = '';
        
        if (gasto.imagen) {
            imageContainer.innerHTML = `
                <img src="../../imagenes/gastos/${gasto.imagen}" 
                     class="gasto-image" 
                     alt="Comprobante del gasto"
                     loading="lazy">
            `;
        } else {
            imageContainer.innerHTML = `
                <div style="color: #64748b; text-align: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                    <p>No hay comprobante disponible</p>
                </div>
            `;
        }
        
        // Datos para mostrar
        const columna1 = [
            { label: 'FECHA', value: gasto.fecha || 'N/A' },
            { label: 'TIPO', value: gasto.tipo || 'N/A' },
            { label: 'DESCRIPCIÓN', value: gasto.descripcion || 'N/A' }
        ];
        
        const columna2 = [
            { label: 'TOTAL', value: formatNumber(gasto.total) + ' XAF' },
            { label: 'CAJA', value: 'Caja Principal (' + formatNumber(gasto.saldo_actual) + ' XAF)' },
            { label: 'REGISTRADO POR', value: 'Administrador' }
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
        document.getElementById('detalleGasto').innerHTML = html;
        
        // Obtener y mostrar productos asociados
        fetch(`../../api/get_productos_gasto.php?id=${gasto.id}`)
            .then(response => response.json())
            .then(data => {
                const productosDetalle = document.getElementById('productosDetalle');
                productosDetalle.innerHTML = '';
                
                if (data.length > 0) {
                    document.getElementById('productosContent').style.display = 'block';
                    
                    data.forEach(producto => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${producto.nombre || 'Concepto'}</td>
                            <td>${producto.cantidad || '-'}</td>
                            <td>${formatNumber(producto.precio_unitario)} XAF</td>
                            <td>${formatNumber(producto.cantidad * producto.precio_unitario)} XAF</td>
                        `;
                        productosDetalle.appendChild(row);
                    });
                } else {
                    document.getElementById('productosContent').style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error al obtener productos:', error);
                document.getElementById('productosContent').style.display = 'none';
            });
        
        // Mostrar modal
        abrirModal('modalVer');
    }
    
    // Función para editar gasto
    function editarGasto(gasto = null) {
        if (!gasto && currentGasto) {
            gasto = currentGasto;
        }
        
        if (gasto) {
            // Llenar campos básicos
            document.getElementById('editar_id').value = gasto.id;
            document.getElementById('editar_fecha').value = gasto.fecha;
            document.getElementById('editar_tipo').value = gasto.tipo;
            document.getElementById('editar_descripcion').value = gasto.descripcion;
            document.getElementById('editar_vehiculo_id').value = gasto.vehiculo_id || '';
            document.getElementById('imagen_actual').value = gasto.imagen || '';
            document.getElementById('editarTotal').value = gasto.total;
            document.getElementById('editarTotalDisplay').textContent = formatNumber(gasto.total);
            
            // Mostrar imagen actual si existe
            const previewDiv = document.getElementById('current_image');
            if (gasto.imagen) {
                previewDiv.innerHTML = `
                    <img src="../../imagenes/gastos/${gasto.imagen}" 
                         class="modal-vehicle__preview-image"
                         style="max-width: 100px; max-height: 80px;"
                         loading="lazy">
                    <div style="font-size: 12px; color: #666; text-align: center; margin-top: 5px;">Imagen actual</div>
                `;
            } else {
                previewDiv.innerHTML = '<div style="color: #999; font-size: 12px; text-align: center;">No hay imagen</div>';
            }
            
            // Limpiar tabla de productos antes de agregar nuevos
            document.getElementById('editarProductosBody').innerHTML = '';
            
            // Obtener productos asociados a este gasto
            fetch(`../../api/get_productos_gasto.php?id=${gasto.id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        data.forEach(producto => {
                            agregarFilaProducto('editarProductosBody', {
                                id: producto.producto_id,
                                cantidad: producto.cantidad,
                                precio: producto.precio_unitario,
                                nombre: producto.nombre
                            });
                        });
                        calcularTotal('editar');
                    }
                })
                .catch(error => {
                    console.error('Error al obtener productos:', error);
                });
            
            // Mostrar modal de edición
            abrirModal('modalEditar');
        }
    }
    
    // Función para agregar fila de producto
    function agregarFilaProducto(tbodyId, producto = null) {
    const tbody = document.getElementById(tbodyId);
    const rowId = `producto-${nextProductId++}`;
    const tipo = tbodyId === 'productosBody' ? 'agregar' : 'editar';
    
    const row = document.createElement('tr');
    row.id = rowId;
    row.className = 'product-row';
    
    // Celda de selección de producto
    const productoCell = document.createElement('td');
    const productoSelect = document.createElement('select');
    productoSelect.name = 'productos[' + rowId + '][id]';
    productoSelect.className = 'modal-vehicle__form-input';
    productoSelect.required = true;
    
    // Opción vacía
    const emptyOption = document.createElement('option');
    emptyOption.value = '';
    emptyOption.textContent = 'Seleccione un producto';
    productoSelect.appendChild(emptyOption);
    
    // Opciones de productos
    productos.forEach(prod => {
        const option = document.createElement('option');
        option.value = prod.id;
        option.textContent = prod.nombre;
        option.dataset.precio = prod.precio || '0';
        
        if (producto && producto.id == prod.id) {
            option.selected = true;
        }
        
        productoSelect.appendChild(option);
    });
    
    productoCell.appendChild(productoSelect);
    row.appendChild(productoCell);
    
    // Celda de cantidad comprada
    const cantidadCell = document.createElement('td');
    const cantidadInput = document.createElement('input');
    cantidadInput.type = 'number';
    cantidadInput.name = 'productos[' + rowId + '][cantidad]';
    cantidadInput.className = 'modal-vehicle__form-input';
    cantidadInput.min = '1';
    cantidadInput.value = producto?.cantidad || '1';
    cantidadInput.required = true;
    cantidadCell.appendChild(cantidadInput);
    row.appendChild(cantidadCell);
    
    // Celda de cantidad a agregar a stock
    const stockCell = document.createElement('td');
    const stockInput = document.createElement('input');
    stockInput.type = 'number';
    stockInput.name = 'productos[' + rowId + '][stock]';
    stockInput.className = 'modal-vehicle__form-input';
    stockInput.min = '0';
    stockInput.value = producto?.stock || '0';
    stockInput.required = true;
    stockCell.appendChild(stockInput);
    row.appendChild(stockCell);
    
    // Celda de precio unitario
    const precioCell = document.createElement('td');
    const precioInput = document.createElement('input');
    precioInput.type = 'number';
    precioInput.name = 'productos[' + rowId + '][precio]';
    precioInput.className = 'modal-vehicle__form-input';
    precioInput.step = '0.01';
    precioInput.min = '0';
    precioInput.value = producto?.precio || '0';
    precioInput.required = true;
    precioCell.appendChild(precioInput);
    row.appendChild(precioCell);
    
    // Celda de subtotal
    const subtotalCell = document.createElement('td');
    subtotalCell.className = 'subtotal-cell';
    subtotalCell.textContent = '0.00';
    row.appendChild(subtotalCell);
    
    // Celda de acciones (eliminar)
    const accionesCell = document.createElement('td');
    const eliminarBtn = document.createElement('button');
    eliminarBtn.type = 'button';
    eliminarBtn.className = 'remove-product-btn';
    eliminarBtn.innerHTML = '&times;';
    eliminarBtn.addEventListener('click', () => {
        row.remove();
        calcularTotal(tipo);
    });
    accionesCell.appendChild(eliminarBtn);
    row.appendChild(accionesCell);
    
    // Eventos
    cantidadInput.addEventListener('input', () => {
        calcularSubtotal(row);
        calcularTotal(tipo);
    });
    
    stockInput.addEventListener('input', () => {
        // Validar que stock no sea mayor que cantidad
        if (parseFloat(stockInput.value) > parseFloat(cantidadInput.value)) {
            stockInput.value = cantidadInput.value;
        }
    });
    
    precioInput.addEventListener('input', () => {
        calcularSubtotal(row);
        calcularTotal(tipo);
    });
    
    productoSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value && selectedOption.dataset.precio) {
            precioInput.value = selectedOption.dataset.precio;
            calcularSubtotal(row);
            calcularTotal(tipo);
        }
    });
    
    tbody.appendChild(row);
    
    // Calcular subtotal inicial si hay producto
    if (producto) {
        calcularSubtotal(row);
    }
}
    
    // Función para calcular subtotal de una fila de producto
    function calcularSubtotal(row) {
        const cantidad = parseFloat(row.querySelector('input[name*="[cantidad]"]').value) || 0;
        const precio = parseFloat(row.querySelector('input[name*="[precio]"]').value) || 0;
        const subtotal = cantidad * precio;
        
        const subtotalCell = row.querySelector('.subtotal-cell');
        subtotalCell.textContent = formatNumber(subtotal);
        
        // Determinar en qué modal estamos y calcular el total correspondiente
        const modalId = row.closest('.modal-vehicle__container').id;
        if (modalId === 'modalAgregar') {
            calcularTotal('agregar');
        } else if (modalId === 'modalEditar') {
            calcularTotal('editar');
        }
    }
    
    // Función para calcular el total del gasto
    // Modifica la función calcularTotal
function calcularTotal(tipo) {
    let total = 0;
    const tbodyId = tipo === 'agregar' ? 'productosBody' : 'editarProductosBody';
    const rows = document.querySelectorAll(`#${tbodyId} tr`);
    
    rows.forEach(row => {
        const subtotalText = row.querySelector('.subtotal-cell').textContent;
        const subtotal = parseFloat(subtotalText.replace('.', '').replace(',', '.')) || 0;
        total += subtotal;
    });
    
    // Sumar el monto directo si existe
    const montoDirecto = parseFloat(document.getElementById('monto_directo').value) || 0;
    total += montoDirecto;
    
    if (tipo === 'agregar') {
        document.getElementById('total').value = total;
        document.getElementById('totalDisplay').textContent = formatNumber(total);
    } else {
        document.getElementById('editarTotal').value = total;
        document.getElementById('editarTotalDisplay').textContent = formatNumber(total);
    }
}

// Agrega evento al monto directo para recalcular
document.getElementById('monto_directo')?.addEventListener('input', () => {
    calcularTotal('agregar');
});
    
    // Función para formatear números
    function formatNumber(number) {
        return new Intl.NumberFormat('es-ES', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(number);
    }
    
    // Confirmar eliminación
    function confirmarEliminacion() {
        return confirm('¿Estás seguro de que deseas eliminar este gasto? Esta acción no se puede deshacer.');
    }
    
    // Cerrar modal al hacer clic fuera del contenido
    window.onclick = function(event) {
        if (event.target.className === 'modal-vehicle__overlay') {
            const modalId = event.target.parentElement.id;
            if (modalId !== 'modalAgregar') {
                cerrarModal(modalId);
            }
        }
    };
    </script>
</body>
</html>