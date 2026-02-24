<?php
session_start();
require_once '../../config/conexion.php';

// 1. Verificar seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// 2. Verificar ID válido en la URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?mensaje=error_id');
    exit;
}

$id = (int)$_GET['id'];
$mensaje = '';
$tipo_alerta = '';

// 3. Procesar el Formulario al Guardar (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir y limpiar datos (Se eliminaron email y ocupacion)
    $dni = trim($_POST['dni']);
    $apellido = trim($_POST['apellido']);
    $nombre = trim($_POST['nombre']);
    $celular = trim($_POST['celular']);
    $direccion = trim($_POST['direccion']);

    // Validar campos obligatorios
    if (empty($dni) || empty($apellido) || empty($nombre)) {
        $mensaje = "Los campos DNI, Apellido y Nombre son obligatorios.";
        $tipo_alerta = "danger";
    } else {
        try {
            // Actualizar datos
            $sql_update = "UPDATE tutores SET 
                            dni = :dni,
                            apellido = :apellido,
                            nombre = :nombre,
                            celular = :celular,
                            direccion = :direccion
                           WHERE id = :id";
            
            $stmt = $pdo->prepare($sql_update);
            $stmt->execute([
                'dni' => $dni,
                'apellido' => $apellido,
                'nombre' => $nombre,
                'celular' => $celular,
                'direccion' => $direccion,
                'id' => $id
            ]);

            $mensaje = "Datos del tutor actualizados correctamente.";
            $tipo_alerta = "success";

        } catch (PDOException $e) {
            // Manejo de error (ej: DNI duplicado)
            if ($e->getCode() == 23000) { 
                $mensaje = "Error: El DNI ingresado ya pertenece a otro tutor.";
            } else {
                $mensaje = "Error al actualizar: " . $e->getMessage();
            }
            $tipo_alerta = "danger";
        }
    }
}

// 4. Obtener datos actuales del tutor
try {
    $sql_get = "SELECT * FROM tutores WHERE id = :id";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute(['id' => $id]);
    $tutor = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if (!$tutor) {
        header('Location: index.php?mensaje=error_no_encontrado');
        exit;
    }

    // 5. NUEVO: Obtener alumnos vinculados a este tutor
    $sql_alumnos = "SELECT 
                        a.id, a.dni, a.apellido, a.nombre,
                        c.anio_curso, c.division
                    FROM alumnos a
                    LEFT JOIN inscripciones i ON a.id = i.id_alumno 
                        AND i.id_ciclo_lectivo = (SELECT id FROM ciclos_lectivos WHERE activo = 1 LIMIT 1)
                    LEFT JOIN cursos c ON i.id_curso = c.id
                    WHERE a.id_tutor = :id_tutor
                    ORDER BY a.apellido, a.nombre";
    
    $stmt_alumnos = $pdo->prepare($sql_alumnos);
    $stmt_alumnos->execute(['id_tutor' => $id]);
    $alumnos_vinculados = $stmt_alumnos->fetchAll();

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

// --- INCLUIR CABECERA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            
            <!-- TARJETA EDICIÓN TUTOR -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Editar Tutor</h4>
                </div>
                <div class="card-body">
                    
                    <!-- Mensajes de Alerta -->
                    <?php if (!empty($mensaje)): ?>
                        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
                            <?= $mensaje ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        
                        <!-- Datos Personales -->
                        <h6 class="text-muted border-bottom pb-2 mb-3">Datos Personales</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="dni" class="form-label">DNI <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="dni" name="dni" 
                                       value="<?= htmlspecialchars($tutor['dni']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="apellido" class="form-label">Apellido <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="apellido" name="apellido" 
                                       value="<?= htmlspecialchars($tutor['apellido']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?= htmlspecialchars($tutor['nombre']) ?>" required>
                            </div>
                        </div>

                        <!-- Contacto (Simplificado) -->
                        <h6 class="text-muted border-bottom pb-2 mb-3">Contacto y Domicilio</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label for="celular" class="form-label">Celular / Teléfono</label>
                                <input type="text" class="form-control" id="celular" name="celular" 
                                       value="<?= htmlspecialchars($tutor['celular']) ?>" placeholder="Ej: 381...">
                            </div>
                            <div class="col-md-7">
                                <label for="direccion" class="form-label">Dirección / Domicilio</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" 
                                       value="<?= htmlspecialchars($tutor['direccion']) ?>">
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4 pt-3 border-top">
                            <a href="index.php" class="btn btn-secondary me-md-2">
                                <i class="bi bi-x-lg"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Guardar Cambios
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- TARJETA ALUMNOS VINCULADOS -->
            <div class="card shadow">
                <div class="card-header bg-light">
                    <h5 class="mb-0 text-secondary"><i class="bi bi-people me-2"></i>Alumnos a Cargo</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">DNI</th>
                                    <th>Alumno</th>
                                    <th>Curso Actual</th>
                                    <th class="text-end pe-3">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($alumnos_vinculados) > 0): ?>
                                    <?php foreach ($alumnos_vinculados as $alu): ?>
                                        <tr>
                                            <td class="ps-3"><?= $alu['dni'] ?></td>
                                            <td class="fw-bold text-dark">
                                                <?= $alu['apellido'] ?>, <?= $alu['nombre'] ?>
                                            </td>
                                            <td>
                                                <?php if ($alu['anio_curso']): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success border border-success">
                                                        <?= $alu['anio_curso'] ?> "<?= $alu['division'] ?>"
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted small">No inscripto</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-3">
                                                <a href="../alumnos/editar.php?id=<?= $alu['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Ver ficha del alumno">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            No hay alumnos vinculados a este tutor.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php 
include $base_path . 'includes/footer.php'; 
?>