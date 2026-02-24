<?php
session_start();
require_once '../../config/conexion.php';

// Verificar seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Búsqueda (Opcional, si quieres filtrar por año o división)
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

// Consulta SQL optimizada para Listado
// Incluye subconsulta para contar alumnos activos en cada curso
$sql = "SELECT 
            c.id, c.anio_curso, c.division, c.turno,
            (SELECT COUNT(*) 
             FROM inscripciones i 
             WHERE i.id_curso = c.id 
             AND i.id_ciclo_lectivo = (SELECT id FROM ciclos_lectivos WHERE activo = 1 LIMIT 1)) as total_alumnos
        FROM cursos c
        WHERE 
            c.anio_curso LIKE :b1 OR 
            c.division LIKE :b2
        ORDER BY c.anio_curso ASC, c.division ASC";

$stmt = $pdo->prepare($sql);
$termino = "%$busqueda%";
$stmt->execute(['b1' => $termino, 'b2' => $termino]);
$cursos = $stmt->fetchAll();

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container">
    
    <!-- Encabezado y Botón Nuevo -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-mortarboard-fill text-primary me-2"></i> Gestión de Cursos</h3>
        <a href="alta.php" class="btn btn-success shadow-sm">
            <i class="bi bi-plus-lg"></i> Nuevo Curso
        </a>
    </div>

    <!-- Buscador (Opcional) -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form method="GET" action="" class="row g-2">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Buscar curso (ej: 1, A, Tarde)..." value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Resultados (Reemplaza a las Tarjetas) -->
    <div class="card shadow border-0">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary">Listado de Cursos Activos</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Año y División</th>
                            <th class="text-center">Turno</th>
                            <th class="text-center">Alumnos Inscriptos</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($cursos) > 0): ?>
                            <?php foreach ($cursos as $curso): ?>
                                <tr>
                                    <!-- Columna Nombre del Curso -->
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initials bg-light text-primary rounded-circle me-3 d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                                                <?= $curso['anio_curso'] ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold text-dark">
                                                    <?= $curso['anio_curso'] ?>º Año "<?= $curso['division'] ?>"
                                                </h6>
                                                <small class="text-muted">Ciclo Lectivo Actual</small>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Columna Turno con Badges -->
                                    <td class="text-center">
                                        <?php 
                                            $badgeClass = 'bg-secondary';
                                            $icon = 'bi-clock';
                                            if (stripos($curso['turno'], 'Mañana') !== false) {
                                                $badgeClass = 'bg-warning text-dark bg-opacity-25 border border-warning';
                                                $icon = 'bi-sun-fill';
                                            } elseif (stripos($curso['turno'], 'Tarde') !== false) {
                                                $badgeClass = 'bg-info text-dark bg-opacity-25 border border-info';
                                                $icon = 'bi-sunset-fill';
                                            } elseif (stripos($curso['turno'], 'Vespertino') !== false || stripos($curso['turno'], 'Noche') !== false) {
                                                $badgeClass = 'bg-dark text-light border border-dark';
                                                $icon = 'bi-moon-stars-fill';
                                            }
                                        ?>
                                        <span class="badge <?= $badgeClass ?> px-3 py-2 rounded-pill fw-normal">
                                            <i class="bi <?= $icon ?> me-1"></i> <?= $curso['turno'] ?>
                                        </span>
                                    </td>

                                    <!-- Columna Cantidad Alumnos -->
                                    <td class="text-center">
                                        <?php if ($curso['total_alumnos'] > 0): ?>
                                            <span class="badge bg-success rounded-pill px-3">
                                                <?= $curso['total_alumnos'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin inscriptos</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Columna Acciones -->
                                    <td class="text-end pe-4">
                                        <div class="btn-group" role="group">
                                            <!-- Ver Alumnos del curso -->
                                            <a href="../alumnos/index.php?q=<?= urlencode($curso['anio_curso'] . ' ' . $curso['division']) ?>" class="btn btn-sm btn-outline-primary" title="Ver Alumnos">
                                                <i class="bi bi-people"></i>
                                            </a>
                                            <!-- Editar Curso -->
                                            <a href="editar.php?id=<?= $curso['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Modificar Curso">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <!-- Eliminar (con confirmación) -->
                                            <a href="eliminar.php?id=<?= $curso['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Está seguro de eliminar este curso?');" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-inboxes display-6 d-block mb-3 opacity-50"></i>
                                        <p class="mb-0">No hay cursos registrados o no coinciden con la búsqueda.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-muted small">
            Total de cursos: <?= count($cursos) ?>
        </div>
    </div>

</div>

<?php 
include $base_path . 'includes/footer.php'; 
?>