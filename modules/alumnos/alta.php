<?php
session_start();
// Ajustamos la ruta porque estamos dos carpetas adentro (modules/alumnos)
require_once '../../config/conexion.php';
require_once '../../config/logger.php';

// Verificar permisos (Solo Admin y Auxiliares pueden inscribir)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$mensaje = '';
$tipo_alerta = '';

// --- PROCESAR FORMULARIO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Recolectar datos del TUTOR (Ahora pueden venir vacíos)
    $tutor_dni      = trim($_POST['tutor_dni'] ?? '');
    $tutor_apellido = trim($_POST['tutor_apellido'] ?? '');
    $tutor_nombre   = trim($_POST['tutor_nombre'] ?? '');
    $tutor_dir      = trim($_POST['tutor_direccion'] ?? '');
    $tutor_cel      = trim($_POST['tutor_celular'] ?? '');
    $parentesco     = trim($_POST['parentesco'] ?? '');

    // Recolectar datos del ALUMNO
    $alu_dni        = trim($_POST['alumno_dni']);
    $alu_apellido   = trim($_POST['alumno_apellido']);
    $alu_nombre     = trim($_POST['alumno_nombre']);
    $alu_nacimiento = $_POST['alumno_nacimiento'];
    $alu_dir        = trim($_POST['alumno_direccion']);
    $alu_loc        = trim($_POST['alumno_localidad']);
    $alu_cel        = trim($_POST['alumno_celular']);

    try {
        // INICIO DE TRANSACCIÓN (Para asegurar integridad de datos)
        $pdo->beginTransaction();

        $id_tutor = null; // Por defecto no hay tutor

        // 1. Lógica del TUTOR (Solo procesamos si se ingresó un DNI)
        if (!empty($tutor_dni)) {
            
            // Validar que si puso DNI, complete lo básico
            if (empty($tutor_apellido) || empty($tutor_nombre) || empty($parentesco)) {
                throw new Exception("Si ingresa un Tutor, debe completar obligatoriamente Apellido, Nombre y Parentesco.");
            }

            // Buscamos si ya existe el tutor por DNI
            $stmt = $pdo->prepare("SELECT id FROM tutores WHERE dni = :dni");
            $stmt->execute(['dni' => $tutor_dni]);
            $tutor_existente = $stmt->fetch();

            if ($tutor_existente) {
                // Si ya existe, usamos su ID
                $id_tutor = $tutor_existente['id'];
            } else {
                // Si no existe, lo creamos
                $sql_tutor = "INSERT INTO tutores (dni, apellido, nombre, direccion, celular) 
                              VALUES (:dni, :ape, :nom, :dir, :cel)";
                $stmt_t = $pdo->prepare($sql_tutor);
                $stmt_t->execute([
                    'dni' => $tutor_dni,
                    'ape' => strtoupper($tutor_apellido),
                    'nom' => strtoupper($tutor_nombre),
                    'dir' => $tutor_dir,
                    'cel' => $tutor_cel
                ]);
                $id_tutor = $pdo->lastInsertId();
            }
        }

        // 2. Lógica del ALUMNO
        // Verificamos que el alumno no exista ya
        $stmt_check = $pdo->prepare("SELECT id FROM alumnos WHERE dni = :dni");
        $stmt_check->execute(['dni' => $alu_dni]);
        
        if ($stmt_check->rowCount() > 0) {
            throw new Exception("El alumno con DNI $alu_dni ya está registrado en el sistema.");
        }

        $sql_alumno = "INSERT INTO alumnos (dni, apellido, nombre, fecha_nacimiento, direccion, localidad, celular, id_tutor, parentesco_tutor) 
                       VALUES (:dni, :ape, :nom, :nac, :dir, :loc, :cel, :id_tutor, :parentesco)";
        
        $stmt_a = $pdo->prepare($sql_alumno);
        $stmt_a->execute([
            'dni' => $alu_dni,
            'ape' => strtoupper($alu_apellido),
            'nom' => strtoupper($alu_nombre),
            'nac' => $alu_nacimiento,
            'dir' => $alu_dir,
            'loc' => $alu_loc,
            'cel' => $alu_cel,
            'id_tutor'   => $id_tutor, // Puede ser NULL
            'parentesco' => !empty($parentesco) ? strtoupper($parentesco) : null // Puede ser NULL
        ]);

        // Recuperamos el ID del alumno recién creado para poder inscribirlo
        $nuevo_alumno_id = $pdo->lastInsertId();

        // Si todo salió bien, confirmamos los cambios
        $pdo->commit();

        // Registrar en el log de auditoría
        audit_log($pdo, 'ALTA_ALUMNO', "DNI: $alu_dni | Nombre: $alu_apellido $alu_nombre");
        
        // Mensaje con botón para inscribir inmediatamente
        $mensaje = "¡Alumno registrado correctamente! <br> 
                    <a href='../inscripciones/nueva.php?id_alumno=$nuevo_alumno_id' class='btn btn-light btn-sm mt-2 shadow-sm border'>
                        <i class='bi bi-journal-check'></i> Inscribir al Ciclo Lectivo &rarr;
                    </a>";
        $tipo_alerta = "success";

    } catch (Exception $e) {
        // Si algo falló, deshacemos todo
        $pdo->rollBack();
        $mensaje = "Error: " . $e->getMessage();
        $tipo_alerta = "danger";
    }
}

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mb-5">
    
    <!-- Botón volver -->
    <div class="mb-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al Listado
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="card shadow border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h4 class="card-title mb-0 text-primary fw-bold">
                        <i class="bi bi-person-plus-fill"></i> Inscripción: Datos Personales
                    </h4>
                </div>
                <div class="card-body p-4">

                    <?php if ($mensaje): ?>
                        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show shadow-sm">
                            <?php if($tipo_alerta == 'success'): ?>
                                <i class="bi bi-check-circle-fill me-2"></i>
                            <?php else: ?>
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php endif; ?>
                            <?= $mensaje ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        
                        <!-- SECCIÓN TUTOR -->
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <h5 class="mb-0 text-secondary">1. Datos del Tutor / Responsable</h5>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border">Opcional</span>
                        </div>
                        
                        <!-- Aviso de Autocompletado -->
                        <div id="aviso_tutor" class="alert alert-info py-1 px-3 small mb-3 d-none">
                            <i class="bi bi-info-circle me-2"></i> Tutor encontrado en la base de datos. Se usarán sus datos existentes.
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted">DNI Tutor</label>
                                <!-- Quitamos 'required' -->
                                <input type="number" class="form-control" name="tutor_dni" id="tutor_dni" placeholder="Solo números">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Apellido</label>
                                <input type="text" class="form-control" name="tutor_apellido" id="tutor_apellido">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold small text-muted">Nombre</label>
                                <input type="text" class="form-control" name="tutor_nombre" id="tutor_nombre">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Dirección</label>
                                <input type="text" class="form-control" name="tutor_direccion" id="tutor_direccion">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted">Celular</label>
                                <input type="text" class="form-control" name="tutor_celular" id="tutor_celular" placeholder="Ej: 381...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted">Parentesco</label>
                                <!-- Quitamos 'required' y agregamos opción vacía -->
                                <select class="form-select" name="parentesco">
                                    <option value="">Seleccione...</option>
                                    <option value="PADRE">Padre</option>
                                    <option value="MADRE">Madre</option>
                                    <option value="ABUELO/A">Abuelo/a</option>
                                    <option value="TIO/A">Tío/a</option>
                                    <option value="OTRO">Otro</option>
                                </select>
                            </div>
                        </div>

                        <!-- SECCIÓN ALUMNO -->
                        <h5 class="mb-3 text-secondary border-bottom pb-2">2. Datos del Alumno</h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted">DNI Alumno *</label>
                                <input type="number" class="form-control" name="alumno_dni" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Apellido *</label>
                                <input type="text" class="form-control" name="alumno_apellido" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold small text-muted">Nombre *</label>
                                <input type="text" class="form-control" name="alumno_nombre" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted">Fecha Nacimiento *</label>
                                <input type="date" class="form-control" name="alumno_nacimiento" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold small text-muted">Dirección (Si es diferente)</label>
                                <input type="text" class="form-control" name="alumno_direccion">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Localidad</label>
                                <select class="form-select" name="alumno_localidad" required>
                                    <option value="San Miguel de Tucumán">San Miguel de Tucumán</option>
                                    <option value="Tafí Viejo">Tafí Viejo</option>
                                    <option value="Alderetes">Alderetes</option>
                                    <option value="Banda del Río Salí">Banda del Río Salí</option>
                                    <option value="Famaillá">Famaillá</option>
                                    <option value="Yerba Buena">Yerba Buena</option>
                                    <option value="Lules">Lules</option>
                                    <option value="Alberdi">Alberdi</option>
                                    <option value="Bella Vista">Bella Vista</option>
                                    <option value="Otros">Otros</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">Celular Alumno</label>
                                <input type="text" class="form-control" name="alumno_celular">
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-5">
                            <button type="submit" class="btn btn-success btn-lg shadow-sm">
                                <i class="bi bi-check-lg"></i> Guardar Alumno
                            </button>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- SCRIPT PARA BUSCAR TUTOR AUTOMÁTICAMENTE -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const inputDni = document.getElementById('tutor_dni');
    const aviso = document.getElementById('aviso_tutor');
    
    // Inputs a rellenar
    const inputApellido = document.getElementById('tutor_apellido');
    const inputNombre = document.getElementById('tutor_nombre');
    const inputDireccion = document.getElementById('tutor_direccion');
    const inputCelular = document.getElementById('tutor_celular');

    if(inputDni) {
        inputDni.addEventListener('blur', function() {
            let dni = this.value;
            
            if (dni.length > 5) {
                // Llamada AJAX a la API que creamos
                fetch(`../../modules/tutores/buscar.php?dni=${dni}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Si encuentra al tutor, rellena y bloquea campos visualmente
                            inputApellido.value = data.tutor.apellido;
                            inputNombre.value = data.tutor.nombre;
                            inputDireccion.value = data.tutor.direccion;
                            inputCelular.value = data.tutor.celular;
                            
                            // Estilo visual
                            inputApellido.classList.add('bg-light');
                            inputNombre.classList.add('bg-light');
                            aviso.classList.remove('d-none');
                        } else {
                            // Si no lo encuentra, limpia (opcional) o deja editar
                            // No limpiamos para no borrar si el usuario estaba escribiendo uno nuevo
                            inputApellido.classList.remove('bg-light');
                            inputNombre.classList.remove('bg-light');
                            aviso.classList.add('d-none');
                        }
                    })
                    .catch(error => console.error('Error al buscar tutor:', error));
            }
        });
    }
});
</script>

<?php include $base_path . 'includes/footer.php'; ?>