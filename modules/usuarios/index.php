<?php
session_start();
require_once '../../config/conexion.php';

// SEGURIDAD: Solo el Administrador puede ver este módulo
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    // Si no es admin, lo mandamos al inicio o mostramos error
    header('Location: ../../index.php');
    exit;
}

// Obtener usuarios y sus roles
$sql = "SELECT u.id, u.usuario, u.nombre_completo, u.activo, r.nombre as rol
        FROM usuarios u
        JOIN roles r ON u.id_rol = r.id
        ORDER BY u.nombre_completo ASC";
$stmt = $pdo->query($sql);
$usuarios = $stmt->fetchAll();

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container">
    
    <!-- Botón volver -->
    <div class="mb-4">
        <a href="../../index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al Inicio
        </a>
    </div>

    <!-- Encabezado y Botón Nuevo -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-shield-lock-fill text-danger me-2"></i> Usuarios del Sistema</h3>
            <p class="text-muted mb-0">Gestión de personal con acceso al sistema.</p>
        </div>
        <a href="crear.php" class="btn btn-danger shadow-sm">
            <i class="bi bi-person-plus-fill"></i> Nuevo Usuario
        </a>
    </div>

    <div class="card shadow border-0">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="mb-0 fw-bold text-dark">Lista de Usuarios Registrados</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Usuario (Login)</th>
                            <th>Nombre Completo</th>
                            <th>Rol / Perfil</th>
                            <th>Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $user): ?>
                            <tr>
                                <td class="ps-4 fw-bold font-monospace text-primary">
                                    <?= htmlspecialchars($user['usuario']) ?>
                                </td>
                                <td class="fw-medium">
                                    <?= htmlspecialchars($user['nombre_completo']) ?>
                                </td>
                                <td>
                                    <?php if ($user['rol'] === 'Administrador'): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">
                                            <i class="bi bi-shield-fill me-1"></i> Administrador
                                        </span>
                                    <?php elseif ($user['rol'] === 'Supervisor'): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning text-dark">
                                            <i class="bi bi-eye-fill me-1"></i> Supervisor
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border border-info text-dark">
                                            <i class="bi bi-person-badge-fill me-1"></i> Auxiliar
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['activo']): ?>
                                        <span class="badge bg-success rounded-pill px-3">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary rounded-pill px-3">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <!-- Evitar que se borre a sí mismo -->
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="editar.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar Usuario">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <!-- Aquí podrías agregar botón de eliminar o desactivar -->
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic me-2"><i class="bi bi-person-circle"></i> Tú</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-muted small">
            Total: <strong><?= count($usuarios) ?></strong> usuarios registrados.
        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>