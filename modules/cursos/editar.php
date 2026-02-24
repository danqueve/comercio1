<?php
session_start();
require_once '../../config/conexion.php';

// 1. Verificar seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// 2. Verificar ID válido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?mensaje=error_id');
    exit;
}

$id = (int)$_GET['id'];
$mensaje = '';
$tipo_alerta = '';

// 3. Procesar el Formulario (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $anio = trim($_POST['anio_curso']);
    $division = trim($_POST['division']);
    $turno = trim($_POST['turno']);

    if (empty($anio) || empty($division) || empty($turno)) {
        $mensaje = "Todos los campos son obligatorios.";
        $tipo_alerta = "danger";
    } else {
        try {
            $sql_update = "UPDATE cursos SET anio_curso = :anio, division = :division, turno = :turno WHERE id = :id";
            $stmt = $pdo->prepare($sql_update);
            $stmt->execute([
                'anio' => $anio,
                'division' => $division,
                'turno' => $turno,
                'id' => $id
            ]);

            $mensaje = "Curso actualizado correctamente.";
            $tipo_alerta = "success";
            
            // Opcional: Redirigir después de guardar
            // header('Location: index.php?mensaje=modificado_ok');
            // exit;
        } catch (PDOException $e) {
            $mensaje = "Error al actualizar: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

// 4. Obtener datos actuales del curso (Para rellenar el formulario)
// Esto se hace al cargar la página o después de un POST para mostrar los datos actualizados
try {
    $sql_get = "SELECT * FROM cursos WHERE id = :id";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute(['id' => $id]);
    $curso = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if (!$curso) {
        header('Location: index.php?mensaje=error_no_encontrado');
        exit;
    }
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

// --- INCLUIR CABECERA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Curso</h4>
                </div>
                <div class="card-body">
                    
                    <!-- Alertas -->
                    <?php if (!empty($mensaje)): ?>
                        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
                            <?= $mensaje ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        
                        <div class="mb-3">
                            <label for="anio_curso" class="form-label">Año</label>
                            <input type="number" class="form-control" id="anio_curso" name="anio_curso" 
                                   value="<?= htmlspecialchars($curso['anio_curso']) ?>" required min="1" max="7">
                            <div class="form-text">Ej: 1, 2, 3...</div>
                        </div>

                        <div class="mb-3">
                            <label for="division" class="form-label">División</label>
                            <input type="text" class="form-control" id="division" name="division" 
                                   value="<?= htmlspecialchars($curso['division']) ?>" required maxlength="5">
                            <div class="form-text">Ej: A, B, 1ra, 2da...</div>
                        </div>

                        <div class="mb-3">
                            <label for="turno" class="form-label">Turno</label>
                            <select class="form-select" id="turno" name="turno" required>
                                <option value="">Seleccione...</option>
                                <option value="Mañana" <?= ($curso['turno'] == 'Mañana') ? 'selected' : '' ?>>Mañana</option>
                                <option value="Tarde" <?= ($curso['turno'] == 'Tarde') ? 'selected' : '' ?>>Tarde</option>
                                <option value="Vespertino" <?= ($curso['turno'] == 'Vespertino') ? 'selected' : '' ?>>Vespertino</option>
                                <option value="Noche" <?= ($curso['turno'] == 'Noche') ? 'selected' : '' ?>>Noche</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="index.php" class="btn btn-secondary me-md-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Guardar Cambios
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include $base_path . 'includes/footer.php'; 
?>