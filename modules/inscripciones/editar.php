<?php
require_once '../../config/auth.php';
require_once '../../config/conexion.php';
require_once '../../config/csrf.php';
require_once '../../config/logger.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$id_inscripcion = $_GET['id_inscripcion'] ?? null;
$volver_curso   = $_GET['volver'] ?? null; // id_curso para volver

if (!$id_inscripcion) {
    header('Location: ../../index.php');
    exit;
}

// Datos de la inscripción actual
$stmt = $pdo->prepare("
    SELECT i.id, i.id_curso, i.id_alumno, i.id_ciclo_lectivo,
           a.apellido, a.nombre, a.dni,
           c.anio_curso, c.division, c.turno,
           cl.anio as anio_ciclo
    FROM inscripciones i
    JOIN alumnos a ON i.id_alumno = a.id
    JOIN cursos c ON i.id_curso = c.id
    JOIN ciclos_lectivos cl ON i.id_ciclo_lectivo = cl.id
    WHERE i.id = :id
");
$stmt->execute(['id' => $id_inscripcion]);
$inscripcion = $stmt->fetch();

if (!$inscripcion) {
    header('Location: ../../index.php');
    exit;
}

// Obtener todos los cursos disponibles (del mismo ciclo)
$stmt_cursos = $pdo->prepare("
    SELECT id, anio_curso, division, turno
    FROM cursos
    ORDER BY anio_curso ASC, division ASC, turno ASC
");
$stmt_cursos->execute();
$cursos = $stmt_cursos->fetchAll();

$mensaje = '';
$tipo_alerta = 'success';

// Estados válidos para una inscripción
$estados_validos = ['Regular', 'Libre', 'Condicional', 'Promovido', 'Repitente'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $nuevo_curso_id = (int)$_POST['id_curso_nuevo'];
    $nuevo_estado   = in_array($_POST['nuevo_estado'] ?? '', $estados_validos, true)
                      ? $_POST['nuevo_estado']
                      : $inscripcion['estado']; // mantener el actual si inválido
    $id_ciclo = $inscripcion['id_ciclo_lectivo'];

    $cambio_curso  = $nuevo_curso_id !== (int)$inscripcion['id_curso'];
    $cambio_estado = $nuevo_estado   !== $inscripcion['estado'];

    if (!$cambio_curso && !$cambio_estado) {
        $mensaje = 'No se realizaron cambios. Seleccioná un curso diferente o modificá el estado.';
        $tipo_alerta = 'warning';
    } elseif ($cambio_curso) {
        // Verificar que no exista ya una inscripción en ese curso/ciclo
        $stmt_check = $pdo->prepare("
            SELECT id FROM inscripciones
            WHERE id_alumno = :alumno AND id_curso = :curso AND id_ciclo_lectivo = :ciclo AND id != :excluir
        ");
        $stmt_check->execute([
            'alumno'  => $inscripcion['id_alumno'],
            'curso'   => $nuevo_curso_id,
            'ciclo'   => $id_ciclo,
            'excluir' => $id_inscripcion,
        ]);

        if ($stmt_check->fetch()) {
            $mensaje = 'El alumno ya tiene una inscripción en ese curso para este ciclo lectivo.';
            $tipo_alerta = 'danger';
        } else {
            try {
                // F8-FIX: actualizar tanto curso como estado en un solo UPDATE
                $stmt_upd = $pdo->prepare(
                    "UPDATE inscripciones SET id_curso = :nuevo, estado = :estado WHERE id = :id"
                );
                $stmt_upd->execute([
                    'nuevo'  => $nuevo_curso_id,
                    'estado' => $nuevo_estado,
                    'id'     => $id_inscripcion,
                ]);

                $cur_info = $pdo->prepare("SELECT anio_curso, division, turno FROM cursos WHERE id = :id");
                $cur_info->execute(['id' => $nuevo_curso_id]);
                $cur = $cur_info->fetch();

                audit_log($pdo, 'EDITAR_INSCRIPCION',
                    "Alumno: {$inscripcion['apellido']}, {$inscripcion['nombre']} (DNI {$inscripcion['dni']}) "
                    . "movido a {$cur['anio_curso']} \"{$cur['division']}\" turno {$cur['turno']}"
                    . " — Estado: $nuevo_estado"
                );

                $destino = $volver_curso ?? $nuevo_curso_id;
                header("Location: ../cursos/ver_curso.php?id=$destino&msg=moved");
                exit;

            } catch (PDOException $e) {
                log_error('EDITAR_INSCRIPCION', $e->getMessage());
                $mensaje = 'Error al actualizar la inscripción. Intente nuevamente.';
                $tipo_alerta = 'danger';
            }
        }
    } else {
        // Solo cambió el estado (mismo curso)
        try {
            $pdo->prepare("UPDATE inscripciones SET estado = :estado WHERE id = :id")
                ->execute(['estado' => $nuevo_estado, 'id' => $id_inscripcion]);

            audit_log($pdo, 'EDITAR_INSCRIPCION',
                "Alumno: {$inscripcion['apellido']}, {$inscripcion['nombre']} — Estado cambiado a: $nuevo_estado"
            );

            $destino = $volver_curso ?? $inscripcion['id_curso'];
            header("Location: ../cursos/ver_curso.php?id=$destino&msg=moved");
            exit;

        } catch (PDOException $e) {
            log_error('EDITAR_INSCRIPCION', $e->getMessage());
            $mensaje = 'Error al actualizar el estado. Intente nuevamente.';
            $tipo_alerta = 'danger';
        }
    }
}

$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mb-5">

    <div class="mb-4">
        <?php $url_volver = $volver_curso ? "../cursos/ver_curso.php?id=$volver_curso" : '../../index.php'; ?>
        <a href="<?= $url_volver ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al Curso
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <!-- Info del Alumno -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;">
                        <i class="bi bi-person-fill text-primary fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0"><?= htmlspecialchars($inscripcion['apellido']) ?>, <?= htmlspecialchars($inscripcion['nombre']) ?></h5>
                        <div class="text-muted small">DNI <?= htmlspecialchars($inscripcion['dni']) ?> &bull; Ciclo <?= htmlspecialchars($inscripcion['anio_ciclo']) ?></div>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info mt-1 small">
                            Curso actual: <?= htmlspecialchars($inscripcion['anio_curso']) ?> "<?= htmlspecialchars($inscripcion['division']) ?>" — <?= htmlspecialchars($inscripcion['turno']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Formulario Cambio de Curso -->
            <div class="card shadow border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-arrow-left-right text-primary me-2"></i> Cambiar de Curso
                    </h5>
                </div>
                <div class="card-body p-4">

                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show border-0 shadow-sm" role="alert">
                            <i class="bi bi-<?= $tipo_alerta === 'success' ? 'check-circle-fill' : ($tipo_alerta === 'warning' ? 'exclamation-circle-fill' : 'exclamation-triangle-fill') ?> me-2"></i>
                            <?= htmlspecialchars($mensaje) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                        <!-- Cambiar Curso -->
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Nuevo Curso</label>
                            <select name="id_curso_nuevo" class="form-select form-select-lg">
                                <?php foreach ($cursos as $c): ?>
                                    <?php $es_actual = ($c['id'] == $inscripcion['id_curso']); ?>
                                    <option value="<?= $c['id'] ?>" <?= $es_actual ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['anio_curso']) ?> "<?= htmlspecialchars($c['division']) ?>" — Turno <?= htmlspecialchars($c['turno']) ?>
                                        <?= $es_actual ? ' (actual)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Mantené el mismo curso si solo querés cambiar el estado.</div>
                        </div>

                        <!-- F8-FIX: Cambiar Estado -->
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Estado de la Inscripción</label>
                            <select name="nuevo_estado" class="form-select form-select-lg">
                                <?php foreach ($estados_validos as $est): ?>
                                    <option value="<?= $est ?>"
                                        <?= $est === $inscripcion['estado'] ? 'selected' : '' ?>>
                                        <?= $est ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Regular · Libre · Condicional · Promovido · Repitente</div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                <i class="bi bi-save me-1"></i> Guardar Cambios
                            </button>
                            <a href="<?= $url_volver ?>" class="btn btn-link text-muted text-decoration-none">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>

