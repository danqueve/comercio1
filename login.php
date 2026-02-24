<?php
session_start();
require_once 'config/conexion.php';

$error = '';

// 1. Si el usuario ya está logueado, lo mandamos directo al panel
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// 2. Procesar el formulario al enviar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];

    if (empty($usuario) || empty($password)) {
        $error = "Por favor, complete usuario y contraseña.";
    } else {
        try {
            // Buscamos el usuario en la BD (que esté activo)
            $stmt = $pdo->prepare("SELECT u.id, u.usuario, u.password, u.nombre_completo, r.nombre as rol 
                                   FROM usuarios u 
                                   JOIN roles r ON u.id_rol = r.id 
                                   WHERE u.usuario = :usuario AND u.activo = 1");
            $stmt->execute(['usuario' => $usuario]);
            $user = $stmt->fetch();

            // Verificamos contraseña
            if ($user && password_verify($password, $user['password'])) {
                // Login Exitoso: Guardamos datos en sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nombre']  = $user['nombre_completo'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['rol']     = $user['rol'];

                // Redirigir al Dashboard
                header('Location: index.php');
                exit;
            } else {
                $error = "Usuario o contraseña incorrectos.";
            }
        } catch (PDOException $e) {
            $error = "Error de conexión: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - Escuela Comercio N° 1</title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .card-login {
            width: 100%;
            max-width: 400px;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            background: white;
        }
        .login-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 2rem 1rem;
            text-align: center;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }
        .form-control {
            border-left: none;
        }
    </style>
</head>
<body>

    <div class="container p-3">
        <div class="card shadow-lg card-login mx-auto">
            
            <!-- Encabezado Azul -->
            <div class="login-header">
                <i class="bi bi-mortarboard-fill display-4 mb-2"></i>
                <h4 class="fw-bold mb-0">Sistema Escolar</h4>
                <small class="opacity-75">Escuela de Comercio N° 1</small>
            </div>

            <div class="card-body p-4 pt-5">
                
                <h5 class="text-center text-secondary mb-4">Iniciar Sesión</h5>

                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center small" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <div><?= $error ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text text-muted"><i class="bi bi-person-fill"></i></span>
                            <input type="text" class="form-control form-control-lg fs-6" id="usuario" name="usuario" required autofocus placeholder="Ingrese su usuario">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text text-muted"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control form-control-lg fs-6" id="password" name="password" required placeholder="Ingrese su clave">
                        </div>
                    </div>

                    <div class="d-grid gap-2 mb-3">
                        <button type="submit" class="btn btn-primary btn-lg shadow-sm fw-bold">
                            Ingresar al Sistema
                        </button>
                    </div>

                </form>
            </div>
            
            <div class="card-footer bg-light text-center py-3 border-top-0">
                <small class="text-muted" style="font-size: 0.8rem;">
                    &copy; <?= date('Y') ?> Gestión Escolar &bull; Gral. M. Belgrano
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>