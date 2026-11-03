<?php
require_once '../../config/auth.php';
require_once '../../config/conexion.php';
require_once '../../config/csrf.php';

require_role(['Administrador', 'Supervisor']);

$mensaje = '';
$tipo_alerta = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $anio     = trim($_POST['anio']);      // Ej: "1ro", "3ro"
    $division = trim($_POST['division']);  // Ej: "A", "B"
    $turno    = $_POST['turno'];           // Mañana, Tarde, Noche

    if (empty($anio) || empty($division) || empty($turno)) {
        $mensaje = 'Todos los campos son obligatorios.';
        $tipo_alerta = 'danger';
    } else {
        try {
            // Verificar si ya existe ese curso exacto
            $sql_check = "SELECT id FROM cursos WHERE anio_curso = :anio AND division = :div AND turno = :turno";
            $stmt = $pdo->prepare($sql_check);
            $stmt->execute([
                'anio' => $anio,
                'div'  => strtoupper($division),
                'turno'=> $turno
            ]);

            if ($stmt->rowCount() > 0) {
                $mensaje = 'Ese curso ya existe en el sistema.';
                $tipo_alerta = 'warning';
            } else {
                // Crear el curso
                $sql_insert = "INSERT INTO cursos (anio_curso, division, turno) VALUES (:anio, :div, :turno)";
                $stmt = $pdo->prepare($sql_insert);
                $stmt->execute([
                    'anio' => $anio,
                    'div'  => strtoupper($division),
                    'turno'=> $turno
                ]);

                $mensaje = '¡Curso ' . $anio . ' "' . $division . '" creado con éxito!';
                $tipo_alerta = 'success';
            }
        } catch (PDOException $e) {
            log_error('CREAR_CURSO', $e->getMessage());
            $mensaje = 'Error interno. Por favor, intente nuevamente.';
            $tipo_alerta = 'danger';
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
            <i class="bi bi-arrow-left"></i> Volver al Listado
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <div class="card shadow border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h4 class="card-title mb-0 text-primary fw-bold">
                        <i class="bi bi-plus-circle-fill me-2"></i> Agregar Nuevo Curso
                    </h4>
                </div>
                <div class="card-body p-4">
                    
                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?= $tipo_alerta ?> shadow-sm border-0 mb-4">
                            <i class="bi bi-<?= $tipo_alerta === 'success' ? 'check-circle-fill' : ($tipo_alerta === 'warning' ? 'exclamation-triangle-fill' : 'x-circle-fill') ?> me-2"></i>
                            <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-muted">Año / Grado</label>
                                <select name="anio" class="form-select form-select-lg" required>
                                    <option value="">Seleccione...</option>
                                    <option value="1ro">1ro</option>
                                    <option value="2do">2do</option>
                                    <option value="3ro">3ro</option>
                                    <option value="4to">4to</option>
                                    <option value="5to">5to</option>
                                    <option value="6to">6to</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold small text-muted">División</label>
                                <input type="text" name="division" class="form-control form-control-lg" required placeholder="Ej: A" maxlength="5" style="text-transform: uppercase;">
                                <div class="form-text small">Letra o Identificador (Ej: A, B, Única).</div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Turno</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-clock"></i></span>
                                <select name="turno" class="form-select form-select-lg" required>
                                    <option value="Mañana">Mañana</option>
                                    <option value="Tarde">Tarde</option>
                                    <option value="Noche">Noche</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-grid gap-2 pt-2">
                            <button type="submit" class="btn btn-success btn-lg shadow-sm">
                                <i class="bi bi-save"></i> Guardar Curso
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