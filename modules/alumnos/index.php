<?php
session_start();
require_once '../../config/conexion.php';

// Verificar seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Búsqueda y paginación
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$pagina_actual = isset($_GET['pag']) && is_numeric($_GET['pag']) ? (int)$_GET['pag'] : 1;
$por_pagina = 15;
$offset = ($pagina_actual - 1) * $por_pagina;
$termino = "%$busqueda%";

// Consulta de conteo total (para calcular páginas)
$sql_count = "SELECT COUNT(*) as total FROM alumnos a
              WHERE a.apellido LIKE :b1 OR a.nombre LIKE :b2 OR a.dni LIKE :b3";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute(['b1' => $termino, 'b2' => $termino, 'b3' => $termino]);
$total_registros = $stmt_count->fetch()['total'];
$total_paginas = (int)ceil($total_registros / $por_pagina);
if ($pagina_actual > $total_paginas && $total_paginas > 0) $pagina_actual = $total_paginas;

// Consulta SQL optimizada con paginación
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
        LIMIT :limite OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':b1', $termino);
$stmt->bindValue(':b2', $termino);
$stmt->bindValue(':b3', $termino);
$stmt->bindValue(':limite', $por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$alumnos = $stmt->fetchAll();

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../'; // Ajustamos la ruta base para subir dos niveles
include $base_path . 'includes/header.php';
?>

<div class="container">
    
    <!-- Encabezado y Botón Nuevo -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-people-fill text-primary me-2"></i> Listado de Alumnos</h3>
        <div class="d-flex gap-2">
            <a href="exportar.php?q=<?= urlencode($busqueda) ?>" class="btn btn-outline-success btn-sm shadow-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
            </a>
            <a href="alta.php" class="btn btn-success shadow-sm">
                <i class="bi bi-person-plus-fill"></i> Nuevo Alumno
            </a>
        </div>
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
                                    <td class="ps-3 fw-medium"><?= htmlspecialchars($alu['dni']) ?></td>
                                    <td class="fw-bold text-dark">
                                        <?= htmlspecialchars($alu['apellido']) ?>, <?= htmlspecialchars($alu['nombre']) ?>
                                    </td>
                                    <td>
                                        <?php if ($alu['anio_curso']): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                                <?= htmlspecialchars($alu['anio_curso']) ?> "<?= htmlspecialchars($alu['division']) ?>"
                                            </span>
                                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                                                <?= htmlspecialchars($alu['condicion']) ?>
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
                                                    <?= htmlspecialchars($alu['apellido_tutor']) ?> <?= htmlspecialchars(substr($alu['nombre_tutor'],0,1)) ?>.
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
        <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small">
                <?= $total_registros ?> alumno<?= $total_registros != 1 ? 's' : '' ?> encontrado<?= $total_registros != 1 ? 's' : '' ?> • Mostrando página <?= $pagina_actual ?> de <?= max(1, $total_paginas) ?>
            </span>
            <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de alumnos">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $pagina_actual <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&pag=<?= $pagina_actual - 1 ?>">&laquo;</a>
                    </li>
                    <?php
                    $inicio = max(1, $pagina_actual - 2);
                    $fin    = min($total_paginas, $pagina_actual + 2);
                    for ($i = $inicio; $i <= $fin; $i++):
                    ?>
                    <li class="page-item <?= $i == $pagina_actual ? 'active' : '' ?>">
                        <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&pag=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pagina_actual >= $total_paginas ? 'disabled' : '' ?>">
                        <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&pag=<?= $pagina_actual + 1 ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php 
// Incluir pie de página maestro
include $base_path . 'includes/footer.php'; 
?>