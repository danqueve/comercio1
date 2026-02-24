<?php
session_start();
require_once '../../config/conexion.php';

// SEGURIDAD: Solo Administrador puede tocar esto
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: ../../index.php');
    exit;
}

$mensaje = '';

// --- ACCIÓN 1: CREAR NUEVO CICLO ---
if (isset($_POST['btn_crear'])) {
    $nuevo_anio = (int) $_POST['anio'];
    
    if ($nuevo_anio < 2000 || $nuevo_anio > 2100) {
        $mensaje = '<div class="alert alert-danger shadow-sm border-0"><i class="bi bi-x-circle-fill me-2"></i> Año inválido.</div>';
    } else {
        try {
            // Verificar si ya existe
            $stmt = $pdo->prepare("SELECT id FROM ciclos_lectivos WHERE anio = :anio");
            $stmt->execute(['anio' => $nuevo_anio]);
            
            if ($stmt->rowCount() > 0) {
                $mensaje = '<div class="alert alert-warning shadow-sm border-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> El ciclo ' . $nuevo_anio . ' ya existe.</div>';
            } else {
                // Insertar (por defecto inactivo)
                $stmt = $pdo->prepare("INSERT INTO ciclos_lectivos (anio, activo) VALUES (:anio, 0)");
                $stmt->execute(['anio' => $nuevo_anio]);
                $mensaje = '<div class="alert alert-success shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i> Ciclo ' . $nuevo_anio . ' creado correctamente.</div>';
            }
        } catch (PDOException $e) {
            $mensaje = '<div class="alert alert-danger shadow-sm border-0">Error: ' . $e->getMessage() . '</div>';
        }
    }
}

// --- ACCIÓN 2: ACTIVAR UN CICLO ---
if (isset($_POST['btn_activar'])) {
    $id_ciclo = $_POST['id_ciclo'];
    
    try {
        $pdo->beginTransaction();
        
        // 1. Desactivar TODOS
        $pdo->exec("UPDATE ciclos_lectivos SET activo = 0");
        
        // 2. Activar el SELECCIONADO
        $stmt = $pdo->prepare("UPDATE ciclos_lectivos SET activo = 1 WHERE id = :id");
        $stmt->execute(['id' => $id_ciclo]);
        
        $pdo->commit();
        $mensaje = '<div class="alert alert-success shadow-sm border-0"><i class="bi bi-check-circle-fill me-2"></i> ¡Cambio de ciclo realizado con éxito!</div>';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensaje = '<div class="alert alert-danger shadow-sm border-0">Error al cambiar ciclo: ' . $e->getMessage() . '</div>';
    }
}

// Obtener listado
$ciclos = $pdo->query("SELECT * FROM ciclos_lectivos ORDER BY anio DESC")->fetchAll();

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

    <div class="row">
        
        <!-- COLUMNA IZQUIERDA: CREAR -->
        <div class="col-md-4 mb-4">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white py-3 border-bottom-0 rounded-top">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-plus-circle"></i> Nuevo Ciclo</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Año</label>
                            <input type="number" name="anio" class="form-control form-control-lg" required placeholder="Ej: 2026" min="2020" max="2100" value="<?= date('Y')+1 ?>">
                            <div class="form-text text-muted small">Crear el año para comenzar las inscripciones.</div>
                        </div>
                        <div class="d-grid pt-2">
                            <button type="submit" name="btn_crear" class="btn btn-success shadow-sm">
                                <i class="bi bi-save"></i> Agregar Año
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="mt-3">
                <?= $mensaje ?>
            </div>
        </div>

        <!-- COLUMNA DERECHA: LISTADO Y ACTIVACIÓN -->
        <div class="col-md-8">
            <div class="card shadow border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history text-primary"></i> Historial de Ciclos Lectivos</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Año</th>
                                    <th>Estado</th>
                                    <th class="text-end pe-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ciclos as $c): ?>
                                    <tr class="<?= $c['activo'] ? 'bg-success bg-opacity-10' : '' ?>">
                                        <td class="ps-4 fs-5"><strong><?= $c['anio'] ?></strong></td>
                                        <td>
                                            <?php if ($c['activo']): ?>
                                                <span class="badge bg-success shadow-sm px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> ACTIVO (Vigente)</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary bg-opacity-25 text-secondary border">Cerrado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if (!$c['activo']): ?>
                                                <form method="POST" action="" onsubmit="return confirm('¿Estás seguro? Al activar <?= $c['anio'] ?> se cerrará el ciclo actual y las nuevas inscripciones irán a este año.');">
                                                    <input type="hidden" name="id_ciclo" value="<?= $c['id'] ?>">
                                                    <button type="submit" name="btn_activar" class="btn btn-sm btn-outline-primary shadow-sm">
                                                        <i class="bi bi-toggle-on"></i> Activar
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-success small fw-bold"><i class="bi bi-lock-fill"></i> En uso</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-light border border-info mt-3 small shadow-sm text-muted">
                <div class="d-flex">
                    <i class="bi bi-info-circle-fill text-info fs-4 me-3"></i>
                    <div>
                        <strong>Nota Importante:</strong><br>
                        Solo puede haber un ciclo activo a la vez. Al activar uno nuevo (ej: 2026), el sistema cerrará automáticamente el anterior (ej: 2025) y todas las nuevas inscripciones y pagos quedarán registrados en el nuevo año.
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>