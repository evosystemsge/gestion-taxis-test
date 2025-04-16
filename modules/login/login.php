<?php
session_start();
require '../../config/database.php';

// Verificar si el usuario ya está logueado
if (isset($_SESSION['usuario_id'])) {
    header("Location: ../../index.php");
    exit;
}

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Validar credenciales
    $stmt = $pdo->prepare("SELECT id, nombre, email, password, rol FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario && password_verify($password, $usuario['password'])) {
        // Credenciales válidas
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_rol'] = $usuario['rol'];
        
        // Generar nuevo token CSRF para la sesión
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        header("Location: ../../index.php");
        exit;
    } else {
        $error = "Credenciales inválidas. Por favor intenta nuevamente.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Taxis</title>
    <style>
    /* Estilos base consistentes con el sistema */
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
        background-color: var(--color-fondo);
        color: var(--color-texto);
        line-height: 1.6;
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
    }
    
    .login-container {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        width: 100%;
        max-width: 400px;
        padding: 40px;
        margin: 20px;
    }
    
    .login-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .login-header h2 {
        color: var(--color-primario);
        margin: 0 0 10px;
        font-size: 1.8rem;
    }
    
    .login-header p {
        color: #64748b;
        margin: 0;
    }
    
    .login-form .form-group {
        margin-bottom: 20px;
    }
    
    .login-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #1e293b;
        font-size: 0.9rem;
    }
    
    .login-form input {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--color-borde);
        border-radius: 6px;
        font-size: 0.95rem;
        transition: all 0.2s ease;
    }
    
    .login-form input:focus {
        outline: none;
        border-color: var(--color-primario);
        box-shadow: 0 0 0 3px rgba(0, 75, 135, 0.1);
    }
    
    .login-btn {
        width: 100%;
        padding: 12px;
        background-color: var(--color-primario);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .login-btn:hover {
        background-color: var(--color-secundario);
    }
    
    .login-footer {
        text-align: center;
        margin-top: 20px;
        font-size: 0.9rem;
        color: #64748b;
    }
    
    .login-footer a {
        color: var(--color-primario);
        text-decoration: none;
        font-weight: 600;
    }
    
    .login-footer a:hover {
        text-decoration: underline;
    }
    
    .error-message {
        color: var(--color-peligro);
        background-color: rgba(220, 53, 69, 0.1);
        padding: 10px 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        text-align: center;
    }
    
    .logo {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .logo img {
        max-width: 150px;
        height: auto;
    }
    
    @media (max-width: 480px) {
        .login-container {
            padding: 30px 20px;
        }
    }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <!-- Reemplaza con tu logo o nombre del sistema -->
            <h2 style="color: var(--color-primario); margin: 0;">Sistema de Taxis</h2>
        </div>
        
        <div class="login-header">
            <h2>Iniciar Sesión</h2>
            <p>Ingresa tus credenciales para acceder al sistema</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form class="login-form" method="POST" action="">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-btn">Iniciar Sesión</button>
        </form>
        
        <div class="login-footer">
            <p>¿No tienes una cuenta? <a href="registro.php">Contacta al administrador</a></p>
            <p><a href="recuperar.php">¿Olvidaste tu contraseña?</a></p>
        </div>
    </div>
</body>
</html>