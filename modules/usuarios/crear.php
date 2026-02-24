<?php
session_start();
require_once '../../config/conexion.php';

// SEGURIDAD: Solo Administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: ../../index.php');
    exit;
}

$mensaje = '';

// Obtener roles para el select
$roles = $pdo->query("SELECT * FROM roles")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']);
    $usuario  = trim($_POST['usuario']);
    $password = $_POST['password'];
    $rol_id   = $_POST['rol_id'];

    if (empty($nombre) || empty($usuario) || empty($password)) {
        $mensaje = '<div class="alert alert-danger shadow-sm border-0"><i class="bi bi-x-circle-fill me-2"></i> Todos los campos son obligatorios.</div>';
    } else {
        // Verificar si el usuario ya existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = :user");
        $stmt->execute(['user' => $usuario]);
        
        if ($stmt->rowCount() > 0) {
            $mensaje = '<div class="alert alert-warning shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> El nombre de usuario ya existe. Elija otro.</div>';
        } else {
            // ENCRIPTAR CLAVE
            $hash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $sql = "INSERT INTO usuarios (usuario, password, nombre_completo, id_rol, activo) 
                        VALUES (:user, :pass, :nom, :rol, 1)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'user' => $usuario,
                    'pass' => $hash,
                    'nom'  => $nombre,
                    'rol'  => $rol_id
                ]);
                
                $mensaje = '<div class="alert alert-success shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i> ¡Usuario creado correctamente!</div>';
            } catch (PDOException $e) {
                $mensaje = '<div class="alert alert-danger shadow-sm border-0">Error: ' . $e->getMessage() . '</div>';
            }
        }
    }
}

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mb-5">
    
    <!-- Botón volver -->
    <div class="mb-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver a Usuarios
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <div class="card shadow border-0">
                <div class="card-header bg-danger text-white py-3 border-bottom-0">
                    <h4 class="card-title mb-0 fw-bold">
                        <i class="bi bi-person-plus-fill me-2"></i> Registrar Nuevo Usuario
                    </h4>
                </div>
                <div class="card-body p-4">
                    
                    <?php if ($mensaje): ?>
                        <div class="mb-4">
                            <?= $mensaje ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Nombre Completo</label>
                            <input type="text" name="nombre" class="form-control" required placeholder="Ej: Juan Pérez">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Nombre de Usuario (Login)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" name="usuario" class="form-control" required placeholder="Ej: jperez" autocomplete="off">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" name="password" class="form-control" required placeholder="******" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Perfil / Rol</label>
                            <select name="rol_id" class="form-select" required>
                                <?php foreach ($roles as $rol): ?>
                                    <option value="<?= $rol['id'] ?>"><?= $rol['nombre'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="alert alert-light border mt-2 small text-muted">
                                <ul class="mb-0 ps-3">
                                    <li><strong>Administrador:</strong> Acceso total al sistema.</li>
                                    <li><strong>Supervisor:</strong> Directivos (Ver todo, editar datos).</li>
                                    <li><strong>Auxiliar:</strong> Preceptores (Inscribir, ver listas).</li>
                                </ul>
                            </div>
                        </div>

                        <div class="d-grid gap-2 pt-2">
                            <button type="submit" class="btn btn-danger btn-lg shadow-sm">
                                <i class="bi bi-save"></i> Guardar Usuario
                            </button>
                            <a href="index.php" class="btn btn-link text-muted text-decoration-none">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>