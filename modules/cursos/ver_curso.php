<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../config/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$id_curso = $_GET['id'] ?? null;

if (!$id_curso) {
    header('Location: index.php');
    exit;
}

// 1. Datos del Curso
$stmt_curso = $pdo->prepare("SELECT * FROM cursos WHERE id = :id");
$stmt_curso->execute(['id' => $id_curso]);
$curso = $stmt_curso->fetch();

// 2. Alumnos inscritos en este curso (Ciclo Activo)
// Traemos 'i.id as id_inscripcion' para poder eliminar la relación
$sql_alumnos = "SELECT a.id, a.dni, a.apellido, a.nombre, a.celular, i.estado, i.id as id_inscripcion
                FROM inscripciones i
                JOIN alumnos a ON i.id_alumno = a.id
                JOIN ciclos_lectivos cl ON i.id_ciclo_lectivo = cl.id
                WHERE i.id_curso = :curso AND cl.activo = 1
                ORDER BY a.apellido ASC, a.nombre ASC";

$stmt = $pdo->prepare($sql_alumnos);
$stmt->execute(['curso' => $id_curso]);
$alumnos = $stmt->fetchAll();

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container">
    
    <!-- Botón volver -->
    <div class="mb-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver a Cursos
        </a>
    </div>

    <!-- Mensaje de confirmación si se eliminó alguien -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="bi bi-trash-fill me-2"></i> La inscripción del alumno ha sido eliminada correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'moved'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="bi bi-arrow-left-right me-2"></i> El alumno fue movido a un nuevo curso correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0 text-dark fw-bold">
                    <span class="text-primary"><?= htmlspecialchars($curso['anio_curso']) ?> "<?= htmlspecialchars($curso['division']) ?>"</span>
                </h4>
                <div class="text-muted small mt-1">
                    <i class="bi bi-clock me-1"></i> Turno <?= htmlspecialchars($curso['turno']) ?> &bull; Listado de alumnos regulares
                </div>
            </div>
            <div>
                <!-- Botón para imprimir lista en PDF -->
                <a href="imprimir_lista.php?id=<?= $curso['id'] ?>" target="_blank" class="btn btn-secondary btn-sm shadow-sm">
                    <i class="bi bi-printer-fill me-2"></i> Imprimir Lista
                </a>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 50px;">#</th>
                            <th>Apellido y Nombre</th>
                            <th>DNI</th>
                            <th>Celular</th>
                            <th>Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($alumnos) > 0): ?>
                            <?php $contador = 1; ?>
                            <?php foreach ($alumnos as $alu): ?>
                                <tr>
                                    <td class="ps-4 text-muted"><?= $contador++ ?></td>
                                    <td class="fw-bold text-dark">
                                        <?= htmlspecialchars($alu['apellido']) ?>, <?= htmlspecialchars($alu['nombre']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($alu['dni']) ?></td>
                                    <td><?= $alu['celular'] ? htmlspecialchars($alu['celular']) : '<span class="text-muted small">-</span>' ?></td>
                                    <td>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                            <?= htmlspecialchars($alu['estado']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group" role="group">
                                            <!-- Ver / Editar -->
                                            <a href="../alumnos/editar.php?id=<?= $alu['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Ver/Editar Datos">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <!-- Ficha Individual PDF -->
                                            <a href="../inscripciones/imprimir_ficha.php?id_alumno=<?= $alu['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Ficha de Matrícula">
                                                <i class="bi bi-file-pdf"></i>
                                            </a>
                                            
                                            <!-- BOTÓN CAMBIAR CURSO -->
                                            <a href="../inscripciones/editar.php?id_inscripcion=<?= $alu['id_inscripcion'] ?>&volver=<?= $curso['id'] ?>" class="btn btn-sm btn-outline-primary" title="Cambiar de Curso">
                                                <i class="bi bi-arrow-left-right"></i>
                                            </a>
                                            <!-- Usamos un formulario para enviar POST seguro -->
                                            <form method="POST" action="../inscripciones/eliminar.php" class="d-inline" onsubmit="return confirm('¿Estás seguro de quitar a <?= $alu['nombre'] ?> de este curso?\n\nEsta acción NO elimina al alumno del sistema, solo borra su inscripción en este curso.');">
                                                <input type="hidden" name="id_inscripcion" value="<?= $alu['id_inscripcion'] ?>">
                                                <input type="hidden" name="id_curso" value="<?= $curso['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-dark text-danger border-start-0" title="Eliminar del curso">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-people display-6 d-block mb-3 opacity-50"></i>
                                        <p class="mb-0">No hay alumnos inscritos en este curso aún.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-muted small">
            Total: <strong><?= count($alumnos) ?></strong> alumnos registrados.
        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>