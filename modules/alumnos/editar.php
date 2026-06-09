<?php
require_once '../../config/auth.php';
require_once '../../config/conexion.php';
require_once '../../config/csrf.php';
require_once '../../config/logger.php';

require_login();

// Verificar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?mensaje=error_id');
    exit;
}

$id_alumno = (int)$_GET['id'];
$mensaje = '';
$tipo_alerta = '';

// Procesar Formulario (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    try {
        $pdo->beginTransaction();

        // --- A. DATOS DEL ALUMNO ---
        $dni_alumno        = trim($_POST['dni']              ?? '');
        $nombre_alumno     = trim($_POST['nombre']           ?? '');
        $apellido_alumno   = trim($_POST['apellido']         ?? '');
        $celular_alumno    = trim($_POST['celular']          ?? '');
        $nacimiento_alumno = trim($_POST['fecha_nacimiento'] ?? '');
        $direccion_alumno  = trim($_POST['direccion']        ?? '');
        $localidad_alumno  = trim($_POST['localidad']        ?? '');

        // --- B. LÓGICA DEL TUTOR ---
        $dni_tutor = trim($_POST['dni_tutor'] ?? '');

        // F2-FIX: si no se provee DNI de tutor, conservar el id_tutor actual
        // para no borrar la asociación existente de forma accidental.
        // Solo se desvincula si el usuario activa el checkbox "quitar_tutor".
        $quitar_tutor  = isset($_POST['quitar_tutor']);
        $id_tutor_final = null; // default: desvinculado

        if ($quitar_tutor) {
            // Desvinculación explícita
            $id_tutor_final = null;
            $parentesco_final = null;
        } elseif (!empty($dni_tutor)) {
            // Se proveyó DNI → buscar o crear tutor
            $nombre_tutor    = trim($_POST['nombre_tutor']    ?? '');
            $apellido_tutor  = trim($_POST['apellido_tutor']  ?? '');
            $celular_tutor   = trim($_POST['celular_tutor']   ?? '');
            $direccion_tutor = trim($_POST['direccion_tutor'] ?? '');
            $parentesco_final = strtoupper(trim($_POST['parentesco_tutor'] ?? '')) ?: null;

            $stmt_check = $pdo->prepare("SELECT id FROM tutores WHERE dni = :dni LIMIT 1");
            $stmt_check->execute(['dni' => $dni_tutor]);
            $tutor_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($tutor_existente) {
                $id_tutor_final = $tutor_existente['id'];
                $pdo->prepare("UPDATE tutores SET nombre=:nom, apellido=:ape, celular=:cel, direccion=:dir WHERE id=:id")
                    ->execute([
                        'nom' => strtoupper($nombre_tutor),
                        'ape' => strtoupper($apellido_tutor),
                        'cel' => $celular_tutor,
                        'dir' => $direccion_tutor,
                        'id'  => $id_tutor_final,
                    ]);
            } else {
                // Crear tutor nuevo
                $stmt_new = $pdo->prepare(
                    "INSERT INTO tutores (dni, nombre, apellido, celular, direccion)
                     VALUES (:dni, :nom, :ape, :cel, :dir)"
                );
                $stmt_new->execute([
                    'dni' => $dni_tutor,
                    'nom' => strtoupper($nombre_tutor),
                    'ape' => strtoupper($apellido_tutor),
                    'cel' => $celular_tutor,
                    'dir' => $direccion_tutor,
                ]);
                $id_tutor_final = $pdo->lastInsertId();
            }
        } else {
            // F2-FIX: DNI vacío sin checkbox → conservar id_tutor actual
            $stmt_actual = $pdo->prepare("SELECT id_tutor, parentesco_tutor FROM alumnos WHERE id = :id");
            $stmt_actual->execute(['id' => $id_alumno]);
            $actual = $stmt_actual->fetch();
            $id_tutor_final  = $actual['id_tutor']  ?? null;
            $parentesco_final = $actual['parentesco_tutor'] ?? null;
        }

        // --- C. ACTUALIZAR ALUMNO (F1-FIX: incluye todos los campos) ---
        $sql_update = "UPDATE alumnos SET
                           dni               = :dni,
                           nombre            = :nombre,
                           apellido          = :apellido,
                           celular           = :celular,
                           fecha_nacimiento  = :nacimiento,
                           direccion         = :direccion,
                           localidad         = :localidad,
                           id_tutor          = :id_tutor,
                           parentesco_tutor  = :parentesco
                       WHERE id = :id";

        $pdo->prepare($sql_update)->execute([
            'dni'        => $dni_alumno,
            'nombre'     => strtoupper($nombre_alumno),
            'apellido'   => strtoupper($apellido_alumno),
            'celular'    => $celular_alumno,
            'nacimiento' => $nacimiento_alumno ?: null,
            'direccion'  => $direccion_alumno,
            'localidad'  => $localidad_alumno,
            'id_tutor'   => $id_tutor_final,
            'parentesco' => $parentesco_final ?? null,
            'id'         => $id_alumno,
        ]);

        $pdo->commit();

        // F9-FIX: registrar auditoría de la edición
        audit_log($pdo, 'EDITAR_ALUMNO',
            "DNI: $dni_alumno | Alumno: $apellido_alumno, $nombre_alumno | ID: $id_alumno"
        );

        $mensaje = "Alumno actualizado correctamente.";
        $tipo_alerta = "success";

    } catch (\Exception $e) {
        $pdo->rollBack();
        log_error('EDITAR_ALUMNO', $e->getMessage());
        $mensaje = "Error al guardar los cambios. Por favor, intente nuevamente.";
        $tipo_alerta = "danger";
    }
}

// Obtener datos actuales del alumno (JOIN con tutor)
$sql_get = "SELECT a.*,
            t.dni as dni_tutor, t.nombre as nombre_tutor, t.apellido as apellido_tutor,
            t.celular as celular_tutor, t.direccion as direccion_tutor
            FROM alumnos a
            LEFT JOIN tutores t ON a.id_tutor = t.id
            WHERE a.id = :id";
$stmt_get = $pdo->prepare($sql_get);
$stmt_get->execute(['id' => $id_alumno]);
$alumno = $stmt_get->fetch(PDO::FETCH_ASSOC);

if (!$alumno) {
    header('Location: index.php?mensaje=error_id');
    exit;
}

// Localidades disponibles (mismas que en alta.php)
$localidades = [
    'San Miguel de Tucumán', 'Tafí Viejo', 'Alderetes', 'Banda del Río Salí',
    'Famaillá', 'Yerba Buena', 'Lules', 'Alberdi', 'Bella Vista', 'Otros',
];

$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mt-4 mb-5">

    <div class="mb-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al Listado
        </a>
    </div>

    <div class="card shadow border-0">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Editar Alumno</h4>
        </div>
        <div class="card-body p-4">

            <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show shadow-sm border-0">
                    <i class="bi bi-<?= $tipo_alerta === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" id="formEditarAlumno">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <!-- ======== DATOS DEL ALUMNO ======== -->
                <h5 class="text-secondary border-bottom pb-2 mb-3">1. Datos del Alumno</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">DNI *</label>
                        <input type="text" name="dni" class="form-control"
                               value="<?= htmlspecialchars($alumno['dni']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Apellido *</label>
                        <input type="text" name="apellido" class="form-control"
                               value="<?= htmlspecialchars($alumno['apellido']) ?>" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-muted">Nombre *</label>
                        <input type="text" name="nombre" class="form-control"
                               value="<?= htmlspecialchars($alumno['nombre']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold small text-muted">Fecha de Nacimiento</label>
                        <input type="date" name="fecha_nacimiento" class="form-control"
                               value="<?= htmlspecialchars($alumno['fecha_nacimiento'] ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-muted">Dirección</label>
                        <input type="text" name="direccion" class="form-control"
                               value="<?= htmlspecialchars($alumno['direccion'] ?? '') ?>"
                               placeholder="Calle y número">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Localidad</label>
                        <select name="localidad" class="form-select">
                            <?php foreach ($localidades as $loc): ?>
                                <option value="<?= $loc ?>"
                                    <?= ($alumno['localidad'] ?? '') === $loc ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Celular Alumno</label>
                        <input type="text" name="celular" class="form-control"
                               value="<?= htmlspecialchars($alumno['celular'] ?? '') ?>"
                               placeholder="Ej: 381...">
                    </div>
                </div>

                <!-- ======== DATOS DEL TUTOR ======== -->
                <h5 class="text-secondary border-bottom pb-2 mb-3 d-flex justify-content-between align-items-center">
                    <span>2. Tutor / Responsable</span>
                    <?php if ($alumno['id_tutor']): ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success small fw-normal">
                            <i class="bi bi-person-check-fill me-1"></i> Tutor asociado
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border small fw-normal">Sin tutor</span>
                    <?php endif; ?>
                </h5>

                <div class="p-3 bg-light rounded border mb-3">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">DNI Tutor</label>
                            <div class="input-group">
                                <input type="text" id="dni_tutor" name="dni_tutor" class="form-control border-primary"
                                       value="<?= htmlspecialchars($alumno['dni_tutor'] ?? '') ?>"
                                       placeholder="Ingrese DNI...">
                                <button type="button" id="btnBuscarTutor" class="btn btn-outline-primary">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <div id="feedbackTutor" class="form-text" style="display:none;"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Apellido Tutor</label>
                            <input type="text" id="apellido_tutor" name="apellido_tutor" class="form-control"
                                   value="<?= htmlspecialchars($alumno['apellido_tutor'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold small text-muted">Nombre Tutor</label>
                            <input type="text" id="nombre_tutor" name="nombre_tutor" class="form-control"
                                   value="<?= htmlspecialchars($alumno['nombre_tutor'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Celular Tutor</label>
                            <input type="text" id="celular_tutor" name="celular_tutor" class="form-control"
                                   value="<?= htmlspecialchars($alumno['celular_tutor'] ?? '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold small text-muted">Dirección Tutor</label>
                            <input type="text" id="direccion_tutor" name="direccion_tutor" class="form-control"
                                   value="<?= htmlspecialchars($alumno['direccion_tutor'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Parentesco</label>
                            <select name="parentesco_tutor" class="form-select">
                                <option value="">Seleccione...</option>
                                <?php
                                $parentescos = ['PADRE', 'MADRE', 'ABUELO/A', 'TIO/A', 'OTRO'];
                                foreach ($parentescos as $par):
                                    $sel = ($alumno['parentesco_tutor'] ?? '') === $par ? 'selected' : '';
                                ?>
                                    <option value="<?= $par ?>" <?= $sel ?>><?= ucfirst(strtolower($par)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- F2-FIX: checkbox explícito para desvincular tutor -->
                    <?php if ($alumno['id_tutor']): ?>
                        <div class="mt-3 border-top pt-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="quitar_tutor"
                                       id="quitar_tutor" value="1">
                                <label class="form-check-label text-danger small fw-bold" for="quitar_tutor">
                                    <i class="bi bi-person-x-fill me-1"></i>
                                    Quitar tutor asociado (desvincular)
                                </label>
                            </div>
                            <div class="form-text text-muted">
                                Marcar solo si querés eliminar la asociación con el tutor actual.
                                Si dejás el DNI en blanco sin marcar esto, el tutor existente se conserva.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="bi bi-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputDni  = document.getElementById('dni_tutor');
    const btnBuscar = document.getElementById('btnBuscarTutor');
    const feedback  = document.getElementById('feedbackTutor');

    function buscarTutor() {
        const dni = inputDni.value.trim();
        if (dni.length < 5) return;

        feedback.style.display = 'block';
        feedback.className = 'form-text text-primary';
        feedback.textContent = 'Buscando tutor...';

        fetch('buscar_tutor.php?dni=' + encodeURIComponent(dni))
            .then(r => r.json())
            .then(data => {
                if (data.found && data.data) {
                    feedback.className = 'form-text text-success';
                    feedback.textContent = '¡Tutor encontrado! Datos cargados.';
                    document.getElementById('apellido_tutor').value  = data.data.apellido  || '';
                    document.getElementById('nombre_tutor').value    = data.data.nombre    || '';
                    document.getElementById('celular_tutor').value   = data.data.celular   || '';
                    document.getElementById('direccion_tutor').value = data.data.direccion || '';
                } else {
                    feedback.className = 'form-text text-muted';
                    feedback.textContent = 'Tutor no encontrado (se creará al guardar si completás los datos).';
                }
            })
            .catch(() => {
                feedback.className = 'form-text text-danger';
                feedback.textContent = 'Error al buscar.';
            });
    }

    inputDni.addEventListener('blur', buscarTutor);
    btnBuscar.addEventListener('click', buscarTutor);
    inputDni.addEventListener('keypress', e => {
        if (e.key === 'Enter') { e.preventDefault(); buscarTutor(); }
    });
});
</script>

<?php include $base_path . 'includes/footer.php'; ?>
