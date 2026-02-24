<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../config/csrf.php';
require_once '../../config/logger.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$mensaje = '';
$tipo_alerta = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $pass_actual  = $_POST['password_actual'] ?? '';
    $pass_nueva   = $_POST['password_nueva'] ?? '';
    $pass_confirm = $_POST['password_confirm'] ?? '';

    if (empty($pass_actual) || empty($pass_nueva) || empty($pass_confirm)) {
        $mensaje = 'Todos los campos son obligatorios.';
        $tipo_alerta = 'danger';
    } elseif (strlen($pass_nueva) < 6) {
        $mensaje = 'La nueva contraseña debe tener al menos 6 caracteres.';
        $tipo_alerta = 'danger';
    } elseif ($pass_nueva !== $pass_confirm) {
        $mensaje = 'La nueva contraseña y la confirmación no coinciden.';
        $tipo_alerta = 'danger';
    } else {
        try {
            // Verificar contraseña actual
            $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = :id");
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($pass_actual, $usuario['password'])) {
                $nuevo_hash = password_hash($pass_nueva, PASSWORD_DEFAULT);
                $stmt2 = $pdo->prepare("UPDATE usuarios SET password = :pass WHERE id = :id");
                $stmt2->execute(['pass' => $nuevo_hash, 'id' => $_SESSION['user_id']]);
                $mensaje = '¡Contraseña actualizada correctamente!';
            } else {
                $mensaje = 'La contraseña actual es incorrecta.';
                $tipo_alerta = 'danger';
            }
        } catch (PDOException $e) {
            log_error('PERFIL_CAMBIO_PASS', $e->getMessage());
            $mensaje = 'Error interno. Por favor, intente más tarde.';
            $tipo_alerta = 'danger';
        }
    }
}

// Obtener datos del usuario actual
$stmt = $pdo->prepare("SELECT u.nombre_completo, u.usuario, r.nombre as rol FROM usuarios u JOIN roles r ON u.id_rol = r.id WHERE u.id = :id");
$stmt->execute(['id' => $_SESSION['user_id']]);
$perfil = $stmt->fetch();

$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mb-5">

    <div class="mb-4">
        <a href="../../index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al Inicio
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">

            <!-- Tarjeta info del usuario -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body d-flex align-items-center gap-3 py-4">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:60px;height:60px;">
                        <i class="bi bi-person-fill text-primary fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= htmlspecialchars($perfil['nombre_completo']) ?></h5>
                        <div class="text-muted small">@<?= htmlspecialchars($perfil['usuario']) ?></div>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary mt-1"><?= htmlspecialchars($perfil['rol']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Formulario cambio de contraseña -->
            <div class="card shadow border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-key-fill text-warning me-2"></i> Cambiar Contraseña
                    </h5>
                </div>
                <div class="card-body p-4">

                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show border-0 shadow-sm" role="alert">
                            <i class="bi bi-<?= $tipo_alerta === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
                            <?= htmlspecialchars($mensaje) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Contraseña Actual</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-lock text-muted"></i></span>
                                <input type="password" name="password_actual" class="form-control" required autocomplete="current-password" placeholder="Tu contraseña actual">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Nueva Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-key text-muted"></i></span>
                                <input type="password" name="password_nueva" class="form-control" required minlength="6" autocomplete="new-password" placeholder="Mínimo 6 caracteres">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Confirmar Nueva Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-check-circle text-muted"></i></span>
                                <input type="password" name="password_confirm" class="form-control" required autocomplete="new-password" placeholder="Repetí la nueva contraseña">
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                <i class="bi bi-save me-1"></i> Guardar Contraseña
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>
