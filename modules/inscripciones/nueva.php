<?php
session_start();
require_once '../../config/conexion.php';

// Verificación de seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$id_alumno = $_GET['id_alumno'] ?? null;
$mensaje = '';
$tipo_alerta = '';

// Si no llega un ID de alumno, redirigir (o mostrar error)
if (!$id_alumno) {
    header('Location: ../../index.php');
    exit;
}

// 1. Obtener datos del ALUMNO
$stmt = $pdo->prepare("SELECT * FROM alumnos WHERE id = :id");
$stmt->execute(['id' => $id_alumno]);
$alumno = $stmt->fetch();

if (!$alumno) {
    die("Error: El alumno no existe.");
}

// 2. Obtener el CICLO LECTIVO ACTIVO (Ej: 2025)
$stmt_ciclo = $pdo->query("SELECT * FROM ciclos_lectivos WHERE activo = 1 LIMIT 1");
$ciclo = $stmt_ciclo->fetch();

if (!$ciclo) {
    die("Error: No hay un ciclo lectivo activo configurado en el sistema.");
}

// 3. Obtener los CURSOS disponibles
$stmt_cursos = $pdo->query("SELECT * FROM cursos ORDER BY anio_curso, division");
$cursos = $stmt_cursos->fetchAll();

// --- PROCESAR LA INSCRIPCIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_curso = $_POST['id_curso'];
    $observaciones = trim($_POST['observaciones']);
    $condicion = $_POST['condicion']; // Regular, Libre, etc.

    try {
        // Insertamos la inscripción
        // La restricción UNIQUE en la BD evitará duplicados para el mismo año
        $sql = "INSERT INTO inscripciones (id_alumno, id_curso, id_ciclo_lectivo, estado, observaciones) 
                VALUES (:alumno, :curso, :ciclo, :estado, :obs)";
        
        $stmt_ins = $pdo->prepare($sql);
        $stmt_ins->execute([
            'alumno' => $id_alumno,
            'curso'  => $id_curso,
            'ciclo'  => $ciclo['id'],
            'estado' => $condicion,
            'obs'    => $observaciones
        ]);

        $mensaje = "¡Inscripción exitosa para el ciclo " . $ciclo['anio'] . "!";
        $tipo_alerta = "success";

    } catch (PDOException $e) {
        // Código 23000 es violación de integridad (Duplicado)
        if ($e->getCode() == 23000) {
            $mensaje = "El alumno ya se encuentra inscrito en este ciclo lectivo.";
            $tipo_alerta = "warning";
        } else {
            $mensaje = "Error al inscribir: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mb-5">

    <!-- Botón volver -->
    <div class="mb-4 d-flex justify-content-between">
        <a href="../../modules/alumnos/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver a Alumnos
        </a>
        <a href="../alumnos/alta.php" class="btn btn-outline-success btn-sm">
            <i class="bi bi-person-plus"></i> Nuevo Alumno
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h4 class="card-title text-primary mb-0 fw-bold">
                        <i class="bi bi-journal-plus me-2"></i> Confirmar Inscripción
                    </h4>
                </div>
                <div class="card-body p-4">
                    
                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show shadow-sm border-0 mb-4">
                            <?php if($tipo_alerta == 'success'): ?>
                                <i class="bi bi-check-circle-fill me-2"></i>
                            <?php else: ?>
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php endif; ?>
                            <?= $mensaje ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Datos Informativos (Solo lectura) -->
                    <div class="alert alert-light border border-primary border-opacity-25 shadow-sm d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Estudiante</small>
                            <h5 class="mb-0 fw-bold text-dark">
                                <?= htmlspecialchars($alumno['apellido'] . ', ' . $alumno['nombre']) ?>
                            </h5>
                            <small class="text-muted"><i class="bi bi-person-vcard"></i> DNI: <?= htmlspecialchars($alumno['dni']) ?></small>
                        </div>
                        <div class="text-end border-start ps-3">
                            <small class="text-uppercase text-muted fw-bold" style="font-size: 0.7rem;">Ciclo Lectivo</small>
                            <h4 class="mb-0 fw-bold text-success">
                                <?= $ciclo['anio'] ?>
                            </h4>
                            <small class="text-success"><i class="bi bi-check-circle-fill"></i> Activo</small>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="id_curso" class="form-label fw-bold small text-muted">Seleccione el Curso *</label>
                            <select name="id_curso" id="id_curso" class="form-select form-select-lg" required>
                                <option value="">-- Elegir Curso --</option>
                                <?php foreach ($cursos as $c): ?>
                                    <option value="<?= $c['id'] ?>">
                                        <?= $c['anio_curso'] ?> "<?= $c['division'] ?>" - Turno <?= $c['turno'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="condicion" class="form-label fw-bold small text-muted">Condición de Inscripción</label>
                            <select name="condicion" class="form-select">
                                <option value="Regular">Alumno Regular</option>
                                <option value="Libre">Libre (Solo rinde examen)</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="observaciones" class="form-label fw-bold small text-muted">Observaciones (Opcional)</label>
                            <textarea name="observaciones" class="form-control" rows="2" placeholder="Ej: Adeuda documentación..."></textarea>
                        </div>

                        <div class="d-grid gap-2 pt-2">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                <i class="bi bi-save"></i> Confirmar Inscripción
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>