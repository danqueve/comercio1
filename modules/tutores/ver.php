<?php
session_start();
require_once '../../config/conexion.php';

// 1. Verificar seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// 2. Verificar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?mensaje=error_id');
    exit;
}

$id_tutor = (int)$_GET['id'];

try {
    // A. Obtener datos del Tutor
    $sql_tutor = "SELECT * FROM tutores WHERE id = :id";
    $stmt_tutor = $pdo->prepare($sql_tutor);
    $stmt_tutor->execute(['id' => $id_tutor]);
    $tutor = $stmt_tutor->fetch(PDO::FETCH_ASSOC);

    if (!$tutor) {
        die("Tutor no encontrado.");
    }

    // B. Obtener Alumnos a cargo y su curso actual
    // Hacemos JOIN con inscripciones y cursos para traer el año y división del ciclo activo
    $sql_alumnos = "SELECT 
                        a.id, a.dni, a.apellido, a.nombre, a.celular,
                        c.anio_curso, c.division, c.turno,
                        i.id as id_inscripcion
                    FROM alumnos a
                    LEFT JOIN inscripciones i ON a.id = i.id_alumno 
                        AND i.id_ciclo_lectivo = (SELECT id FROM ciclos_lectivos WHERE activo = 1 LIMIT 1)
                    LEFT JOIN cursos c ON i.id_curso = c.id
                    WHERE a.id_tutor = :id_tutor
                    ORDER BY a.apellido, a.nombre";

    $stmt_alumnos = $pdo->prepare($sql_alumnos);
    $stmt_alumnos->execute(['id_tutor' => $id_tutor]);
    $alumnos = $stmt_alumnos->fetchAll();

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

// --- HEADER ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mt-4">
    
    <!-- Navegación -->
    <div class="mb-3">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al Listado
        </a>
    </div>

    <!-- TARJETA PRINCIPAL: FICHA DEL TUTOR -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-person-vcard me-2"></i>Ficha del Tutor</h4>
            <span class="badge bg-white text-primary">ID: <?= $tutor['id'] ?></span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 border-end">
                    <h5 class="text-primary mb-3"><?= $tutor['apellido'] ?>, <?= $tutor['nombre'] ?></h5>
                    <p class="mb-1"><strong>DNI:</strong> <?= $tutor['dni'] ?></p>
                    <p class="mb-1"><strong>Dirección:</strong> <?= $tutor['direccion'] ?: '<span class="text-muted">No registrada</span>' ?></p>
                    
                </div>
                <div class="col-md-6 ps-md-4">
                    <h6 class="text-muted text-uppercase small mt-2">Información de Contacto</h6>
                    <p class="mb-2 fs-5">
                        <i class="bi bi-whatsapp text-success me-2"></i> 
                        <?= $tutor['celular'] ?: '<span class="text-muted fs-6">No registrado</span>' ?>
                    </p>
                    <?php if (!empty($tutor['email'])): ?>
                        <p class="mb-1"><i class="bi bi-envelope me-2"></i> <?= $tutor['email'] ?></p>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <a href="editar.php?id=<?= $tutor['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i> Editar Datos Tutor
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TARJETA SECUNDARIA: ALUMNOS VINCULADOS -->
    <div class="card shadow">
        <div class="card-header bg-light">
            <h5 class="mb-0 text-secondary"><i class="bi bi-people-fill me-2"></i>Alumnos Vinculados</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">DNI</th>
                            <th>Alumno/a</th>
                            <th>Curso Actual (Ciclo Lectivo)</th>
                            
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($alumnos) > 0): ?>
                            <?php foreach ($alumnos as $alu): ?>
                                <tr>
                                    <td class="ps-4 fw-medium"><?= $alu['dni'] ?></td>
                                    
                                    <td class="fw-bold text-dark">
                                        <?= $alu['apellido'] ?>, <?= $alu['nombre'] ?>
                                    </td>
                                    
                                    <td>
                                        <?php if ($alu['anio_curso']): ?>
                                            <span class="badge bg-success bg-opacity-75 text-white fs-6 fw-normal px-3 py-2">
                                                <?= $alu['anio_curso'] ?>º "<?= $alu['division'] ?>"
                                            </span>
                                            <small class="text-muted d-block mt-1">
                                                <?= $alu['turno'] ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-white">No Inscripto</span>
                                        <?php endif; ?>
                                    </td>

                                    

                                    <td class="text-end pe-4">
                                        <a href="../alumnos/editar.php?id=<?= $alu['id'] ?>" class="btn btn-sm btn-primary" title="Ver ficha completa del alumno">
                                            <i class="bi bi-file-person"></i> Ver Alumno
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-emoji-neutral display-6 d-block mb-3"></i>
                                    No tiene alumnos asignados actualmente.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
include $base_path . 'includes/footer.php'; 
?>