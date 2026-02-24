<?php
session_start();
require_once '../../config/conexion.php';

// 1. Verificar seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// 2. Búsqueda
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

// 3. Consulta SQL
// Seleccionamos tutores y contamos sus alumnos asociados
// Se eliminaron campos innecesarios para esta vista (email, ocupacion)
$sql = "SELECT 
            t.id, t.dni, t.apellido, t.nombre, t.direccion, t.celular,
            (SELECT COUNT(*) FROM alumnos a WHERE a.id_tutor = t.id) as cantidad_alumnos
        FROM tutores t
        WHERE 
            t.apellido LIKE :b1 OR 
            t.nombre LIKE :b2 OR 
            t.dni LIKE :b3
        ORDER BY t.apellido ASC, t.nombre ASC
        LIMIT 50";

$stmt = $pdo->prepare($sql);
$termino = "%$busqueda%";
$stmt->execute([
    'b1' => $termino, 
    'b2' => $termino, 
    'b3' => $termino
]);
$tutores = $stmt->fetchAll();

// --- INCLUIR CABECERA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container">
    
    <!-- Encabezado y Botón Nuevo -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-person-video3 text-primary me-2"></i> Gestión de Tutores</h3>
        <a href="alta.php" class="btn btn-success shadow-sm">
            <i class="bi bi-person-plus-fill"></i> Nuevo Tutor
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
                            <th>Dirección</th>
                            <th>Celular</th>
                            <th class="text-center">Alumnos a Cargo</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tutores) > 0): ?>
                            <?php foreach ($tutores as $tutor): ?>
                                <tr>
                                    <!-- DNI -->
                                    <td class="ps-3 fw-medium"><?= $tutor['dni'] ?></td>
                                    
                                    <!-- Apellido y Nombre -->
                                    <td class="fw-bold text-dark">
                                        <?= $tutor['apellido'] ?>, <?= $tutor['nombre'] ?>
                                    </td>
                                    
                                    <!-- Dirección -->
                                    <td>
                                        <span class="text-truncate d-block" style="max-width: 200px;" title="<?= $tutor['direccion'] ?>">
                                            <?= $tutor['direccion'] ?: '<span class="text-muted small">-</span>' ?>
                                        </span>
                                    </td>

                                    <!-- Celular -->
                                    <td>
                                        <?php if ($tutor['celular']): ?>
                                            <span><i class="bi bi-whatsapp text-success me-1"></i> <?= $tutor['celular'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Alumnos a Cargo (Contador) -->
                                    <td class="text-center">
                                        <?php if ($tutor['cantidad_alumnos'] > 0): ?>
                                            <span class="badge bg-info text-dark rounded-pill">
                                                <?= $tutor['cantidad_alumnos'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-25 text-secondary rounded-pill">0</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Acciones -->
                                    <td class="text-end pe-3">
                                        <div class="btn-group" role="group">
                                            <!-- Ver Ficha (Apunta a ver.php) -->
                                            <a href="ver.php?id=<?= $tutor['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver Ficha y Alumnos">
                                                <i class="bi bi-eye"></i> Ver Alumnos
                                            </a>
                                            
                                            <!-- Editar -->
                                            <a href="editar.php?id=<?= $tutor['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar Datos">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            
                                            <!-- Eliminar -->
                                            <a href="eliminar.php?id=<?= $tutor['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Está seguro de eliminar este tutor?');" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-person-x display-6 d-block mb-3 opacity-50"></i>
                                        <p class="mb-0">No se encontraron tutores con ese criterio.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-muted small">
            Mostrando <?= count($tutores) ?> registros.
        </div>
    </div>

</div>

<?php 
include $base_path . 'includes/footer.php'; 
?>