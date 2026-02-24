<?php
session_start();
require_once '../../config/conexion.php';

// Verificar seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Búsqueda
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

// Consulta SQL optimizada
// Se agregó LEFT JOIN con 'tutores' y se seleccionan datos del tutor
$sql = "SELECT 
            a.id, a.dni, a.apellido, a.nombre, a.celular, a.id_tutor,
            t.nombre as nombre_tutor, t.apellido as apellido_tutor,
            c.anio_curso, c.division, c.turno,
            i.estado as condicion, i.id as id_inscripcion
        FROM alumnos a
        LEFT JOIN tutores t ON a.id_tutor = t.id
        LEFT JOIN inscripciones i ON a.id = i.id_alumno 
            AND i.id_ciclo_lectivo = (SELECT id FROM ciclos_lectivos WHERE activo = 1 LIMIT 1)
        LEFT JOIN cursos c ON i.id_curso = c.id
        WHERE 
            a.apellido LIKE :b1 OR 
            a.nombre LIKE :b2 OR 
            a.dni LIKE :b3
        ORDER BY a.apellido ASC, a.nombre ASC
        LIMIT 50"; 

$stmt = $pdo->prepare($sql);
$termino = "%$busqueda%";
$stmt->execute([
    'b1' => $termino, 
    'b2' => $termino, 
    'b3' => $termino
]);
$alumnos = $stmt->fetchAll();

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../'; // Ajustamos la ruta base para subir dos niveles
include $base_path . 'includes/header.php';
?>

<div class="container">
    
    <!-- Encabezado y Botón Nuevo -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-people-fill text-primary me-2"></i> Listado de Alumnos</h3>
        <a href="alta.php" class="btn btn-success shadow-sm">
            <i class="bi bi-person-plus-fill"></i> Nuevo Alumno
        </a>
    </div>

    <!-- Buscador -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" action="" class="row g-2">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Buscar por Apellido, Nombre o DNI..." value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Resultados -->
    <div class="card shadow border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">DNI</th>
                            <th>Apellido y Nombre</th>
                            <th>Situación (Ciclo Actual)</th>
                            <!-- Cambio: Se eliminó Celular y se agregó ¿Tiene Tutor? -->
                            <th class="text-center">¿Tiene Tutor?</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($alumnos) > 0): ?>
                            <?php foreach ($alumnos as $alu): ?>
                                <tr>
                                    <td class="ps-3 fw-medium"><?= $alu['dni'] ?></td>
                                    <td class="fw-bold text-dark">
                                        <?= $alu['apellido'] ?>, <?= $alu['nombre'] ?>
                                    </td>
                                    <td>
                                        <?php if ($alu['anio_curso']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                                <?= $alu['anio_curso'] ?> "<?= $alu['division'] ?>"
                                            </span>
                                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                                                <?= $alu['condicion'] ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">No inscrito</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Nueva Columna: ¿Tiene Tutor? -->
                                    <td class="text-center">
                                        <?php if (!empty($alu['id_tutor'])): ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary mb-1">Sí</span>
                                            <!-- Opcional: Mostrar nombre del tutor si se desea -->
                                            <?php if(isset($alu['apellido_tutor'])): ?>
                                                <small class="d-block text-muted" style="font-size: 0.7rem;">
                                                    <?= $alu['apellido_tutor'] ?> <?= substr($alu['nombre_tutor'],0,1) ?>.
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning">No</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end pe-3">
                                        <!-- Grupo de botones de acción -->
                                        <div class="btn-group" role="group">
                                            
                                            <!-- Botón Cobrar / Cooperadora -->
                                            <a href="../../modules/pagos/registrar.php?id_alumno=<?= $alu['id'] ?>" class="btn btn-sm btn-outline-success" title="Registrar Pago / Cooperadora">
                                                <i class="bi bi-currency-dollar"></i>
                                            </a>

                                            <!-- Botón Inscribir (Si no tiene curso) -->
                                            <?php if (!$alu['anio_curso']): ?>
                                                <a href="../inscripciones/nueva.php?id_alumno=<?= $alu['id'] ?>" class="btn btn-sm btn-outline-primary" title="Inscribir">
                                                    <i class="bi bi-journal-plus"></i>
                                                </a>
                                            <?php endif; ?>

                                            <!-- Botón Ver/Editar -->
                                            <a href="editar.php?id=<?= $alu['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar Datos">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            
                                            <!-- Botón Imprimir Ficha -->
                                            <a href="../inscripciones/imprimir_ficha.php?id_alumno=<?= $alu['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger" title="Imprimir Ficha">
                                                <i class="bi bi-file-pdf"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-search display-6 d-block mb-3 opacity-50"></i>
                                        <p class="mb-0">No se encontraron alumnos con ese criterio.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-muted small">
            Mostrando <?= count($alumnos) ?> resultados.
        </div>
    </div>

</div>

<?php 
// Incluir pie de página maestro
include $base_path . 'includes/footer.php'; 
?>