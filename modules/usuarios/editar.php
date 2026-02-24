<?php
session_start();
require_once '../../config/conexion.php';

// SEGURIDAD: Solo Administrador
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: ../../index.php');
    exit;
}

$id_usuario = $_GET['id'] ?? null;
$mensaje = '';

if (!$id_usuario) {
    header('Location: index.php');
    exit;
}

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute(['id' => $id_usuario]);
$usuario = $stmt->fetch();

if (!$usuario) {
    die("Usuario no encontrado.");
}

// Obtener roles
$roles = $pdo->query("SELECT * FROM roles")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']);
    $user_login = trim($_POST['usuario']);
    $password = $_POST['password']; // Puede estar vacío
    $rol_id   = $_POST['rol_id'];
    $activo   = $_POST['activo'];

    if (empty($nombre) || empty($user_login)) {
        $mensaje = '<div class="alert alert-danger shadow-sm border-0"><i class="bi bi-x-circle-fill me-2"></i> Nombre y Usuario son obligatorios.</div>';
    } else {
        try {
            // Lógica para contraseña: ¿La cambiaron?
            if (!empty($password)) {
                // Si escribió algo, actualizamos TODO incluyendo password
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET usuario = :user, password = :pass, nombre_completo = :nom, id_rol = :rol, activo = :act WHERE id = :id";
                $params = [
                    'user' => $user_login,
                    'pass' => $hash,
                    'nom'  => $nombre,
                    'rol'  => $rol_id,
                    'act'  => $activo,
                    'id'   => $id_usuario
                ];
            } else {
                // Si NO escribió password, actualizamos todo MENOS password
                $sql = "UPDATE usuarios SET usuario = :user, nombre_completo = :nom, id_rol = :rol, activo = :act WHERE id = :id";
                $params = [
                    'user' => $user_login,
                    'nom'  => $nombre,
                    'rol'  => $rol_id,
                    'act'  => $activo,
                    'id'   => $id_usuario
                ];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $mensaje = '<div class="alert alert-success shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i> Usuario actualizado correctamente.</div>';
            
            // Recargar datos para ver cambios en el form
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
            $stmt->execute(['id' => $id_usuario]);
            $usuario = $stmt->fetch();

        } catch (PDOException $e) {
            $mensaje = '<div class="alert alert-danger shadow-sm border-0">Error: ' . $e->getMessage() . '</div>';
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
                <div class="card-header bg-warning bg-opacity-10 text-dark py-3 border-bottom-0">
                    <h4 class="card-title mb-0 fw-bold">
                        <i class="bi bi-pencil-fill me-2 text-warning"></i> Editar Usuario
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
                            <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($usuario['nombre_completo']) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Nombre de Usuario (Login)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" name="usuario" class="form-control" required value="<?= htmlspecialchars($usuario['usuario']) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para no cambiar" autocomplete="new-password">
                            </div>
                            <div class="form-text small text-muted">Solo escribe aquí si quieres restablecer la clave del usuario.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-muted">Perfil / Rol</label>
                                <select name="rol_id" class="form-select" required>
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?= $rol['id'] ?>" <?= $rol['id'] == $usuario['id_rol'] ? 'selected' : '' ?>>
                                            <?= $rol['nombre'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-muted">Estado</label>
                                <select name="activo" class="form-select">
                                    <option value="1" <?= $usuario['activo'] == 1 ? 'selected' : '' ?>>Activo</option>
                                    <option value="0" <?= $usuario['activo'] == 0 ? 'selected' : '' ?>>Inactivo (Bloqueado)</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-grid gap-2 pt-2">
                            <button type="submit" class="btn btn-warning btn-lg shadow-sm">
                                <i class="bi bi-save"></i> Actualizar Datos
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