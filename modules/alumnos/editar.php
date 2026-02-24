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

$id_alumno = (int)$_GET['id'];
$mensaje = '';
$tipo_alerta = '';

// 3. Procesar Formulario (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction(); // Iniciar transacción por seguridad

        // --- A. DATOS DEL ALUMNO ---
        $dni_alumno = trim($_POST['dni']);
        $nombre_alumno = trim($_POST['nombre']);
        $apellido_alumno = trim($_POST['apellido']);
        $celular_alumno = trim($_POST['celular']);
        // ... otros campos del alumno ...

        // --- B. LÓGICA DEL TUTOR ---
        $dni_tutor = trim($_POST['dni_tutor']);
        $id_tutor_final = null;

        if (!empty($dni_tutor)) {
            // 1. Buscar si el tutor ya existe por DNI
            $sql_check_tutor = "SELECT id FROM tutores WHERE dni = :dni LIMIT 1";
            $stmt_check = $pdo->prepare($sql_check_tutor);
            $stmt_check->execute(['dni' => $dni_tutor]);
            $tutor_existente = $stmt_check->fetch(PDO::FETCH_ASSOC);

            // Datos del formulario del tutor
            $nombre_tutor = trim($_POST['nombre_tutor']);
            $apellido_tutor = trim($_POST['apellido_tutor']);
            $celular_tutor = trim($_POST['celular_tutor']);
            $direccion_tutor = trim($_POST['direccion_tutor']);

            if ($tutor_existente) {
                // SI EXISTE: Actualizamos sus datos (opcional, pero recomendado) y obtenemos su ID
                $id_tutor_final = $tutor_existente['id'];
                $sql_update_tutor = "UPDATE tutores SET nombre=:nom, apellido=:ape, celular=:cel, direccion=:dir WHERE id=:id";
                $stmt_up_tutor = $pdo->prepare($sql_update_tutor);
                $stmt_up_tutor->execute([
                    'nom' => $nombre_tutor,
                    'ape' => $apellido_tutor,
                    'cel' => $celular_tutor,
                    'dir' => $direccion_tutor,
                    'id'  => $id_tutor_final
                ]);
            } else {
                // NO EXISTE: Lo creamos nuevo
                $sql_new_tutor = "INSERT INTO tutores (dni, nombre, apellido, celular, direccion) VALUES (:dni, :nom, :ape, :cel, :dir)";
                $stmt_new_tutor = $pdo->prepare($sql_new_tutor);
                $stmt_new_tutor->execute([
                    'dni' => $dni_tutor,
                    'nom' => $nombre_tutor,
                    'ape' => $apellido_tutor,
                    'cel' => $celular_tutor,
                    'dir' => $direccion_tutor
                ]);
                $id_tutor_final = $pdo->lastInsertId();
            }
        }

        // --- C. ACTUALIZAR ALUMNO ---
        $sql_update_alumno = "UPDATE alumnos SET 
                                dni = :dni, 
                                nombre = :nombre, 
                                apellido = :apellido, 
                                celular = :celular,
                                id_tutor = :id_tutor
                              WHERE id = :id";
        
        $stmt_alumno = $pdo->prepare($sql_update_alumno);
        $stmt_alumno->execute([
            'dni' => $dni_alumno,
            'nombre' => $nombre_alumno,
            'apellido' => $apellido_alumno,
            'celular' => $celular_alumno,
            'id_tutor' => $id_tutor_final,
            'id' => $id_alumno
        ]);

        $pdo->commit(); // Confirmar cambios
        $mensaje = "Alumno y Tutor actualizados correctamente.";
        $tipo_alerta = "success";

    } catch (Exception $e) {
        $pdo->rollBack(); // Deshacer si hubo error
        $mensaje = "Error al guardar: " . $e->getMessage();
        $tipo_alerta = "danger";
    }
}

// 4. Obtener datos actuales (JOIN con tutor)
$sql_get = "SELECT a.*, 
            t.dni as dni_tutor, t.nombre as nombre_tutor, t.apellido as apellido_tutor, 
            t.celular as celular_tutor, t.direccion as direccion_tutor
            FROM alumnos a 
            LEFT JOIN tutores t ON a.id_tutor = t.id 
            WHERE a.id = :id";
$stmt_get = $pdo->prepare($sql_get);
$stmt_get->execute(['id' => $id_alumno]);
$alumno = $stmt_get->fetch(PDO::FETCH_ASSOC);

if (!$alumno) die("Alumno no encontrado");

// --- HEADER ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mt-4">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Editar Alumno</h4>
        </div>
        <div class="card-body">
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipo_alerta ?>"><?= $mensaje ?></div>
            <?php endif; ?>

            <form method="POST" id="formEditarAlumno">
                
                <!-- DATOS DEL ALUMNO -->
                <h5 class="text-secondary border-bottom pb-2 mb-3">Datos del Alumno</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">DNI Alumno</label>
                        <input type="text" name="dni" class="form-control" value="<?= htmlspecialchars($alumno['dni']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Apellido</label>
                        <input type="text" name="apellido" class="form-control" value="<?= htmlspecialchars($alumno['apellido']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($alumno['nombre']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Celular Alumno (Opcional)</label>
                        <input type="text" name="celular" class="form-control" value="<?= htmlspecialchars($alumno['celular']) ?>">
                    </div>
                </div>

                <!-- DATOS DEL TUTOR -->
                <h5 class="text-secondary border-bottom pb-2 mb-3 d-flex justify-content-between">
                    <span>Datos del Tutor / Responsable</span>
                    <small class="text-muted fst-italic" style="font-size: 0.8rem;">
                        <i class="bi bi-info-circle"></i> Ingrese DNI para buscar automáticamente
                    </small>
                </h5>
                
                <div class="p-3 bg-light rounded border">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">DNI Tutor</label>
                            <div class="input-group">
                                <input type="text" id="dni_tutor" name="dni_tutor" class="form-control border-primary" 
                                       value="<?= htmlspecialchars($alumno['dni_tutor'] ?? '') ?>" placeholder="Ingrese DNI...">
                                <button type="button" id="btnBuscarTutor" class="btn btn-outline-primary"><i class="bi bi-search"></i></button>
                            </div>
                            <div id="feedbackTutor" class="form-text text-primary" style="display:none;">Buscando...</div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Apellido Tutor</label>
                            <input type="text" id="apellido_tutor" name="apellido_tutor" class="form-control" 
                                   value="<?= htmlspecialchars($alumno['apellido_tutor'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nombre Tutor</label>
                            <input type="text" id="nombre_tutor" name="nombre_tutor" class="form-control" 
                                   value="<?= htmlspecialchars($alumno['nombre_tutor'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Celular Tutor</label>
                            <input type="text" id="celular_tutor" name="celular_tutor" class="form-control" 
                                   value="<?= htmlspecialchars($alumno['celular_tutor'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Dirección / Domicilio</label>
                            <input type="text" id="direccion_tutor" name="direccion_tutor" class="form-control" 
                                   value="<?= htmlspecialchars($alumno['direccion_tutor'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar Todo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPT JAVASCRIPT PARA BÚSQUEDA AUTOMÁTICA -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputDni = document.getElementById('dni_tutor');
    const btnBuscar = document.getElementById('btnBuscarTutor');
    const feedback = document.getElementById('feedbackTutor');

    // Función que realiza la búsqueda
    function buscarTutor() {
        const dni = inputDni.value.trim();
        if (dni.length < 5) return; // Evitar búsquedas con DNI muy cortos

        feedback.style.display = 'block';
        feedback.textContent = 'Buscando tutor...';

        // Petición AJAX al archivo buscar_tutor.php
        fetch('buscar_tutor.php?dni=' + dni)
            .then(response => response.json())
            .then(data => {
                if (data.found) {
                    feedback.textContent = '¡Tutor encontrado!';
                    feedback.className = 'form-text text-success';
                    
                    // Rellenar campos
                    document.getElementById('apellido_tutor').value = data.data.apellido || '';
                    document.getElementById('nombre_tutor').value = data.data.nombre || '';
                    document.getElementById('celular_tutor').value = data.data.celular || '';
                    document.getElementById('direccion_tutor').value = data.data.direccion || '';
                } else {
                    feedback.textContent = 'Tutor no registrado (se creará uno nuevo al guardar).';
                    feedback.className = 'form-text text-muted';
                    
                    // Opcional: Limpiar campos si no se encuentra
                    // document.getElementById('apellido_tutor').value = '';
                    // ...
                }
            })
            .catch(err => {
                console.error('Error:', err);
                feedback.textContent = 'Error al buscar.';
                feedback.className = 'form-text text-danger';
            });
    }

    // Evento al perder el foco (salir del campo DNI)
    inputDni.addEventListener('blur', buscarTutor);
    
    // Evento al presionar el botón de la lupa
    btnBuscar.addEventListener('click', buscarTutor);
    
    // Opcional: Evento al presionar Enter en el campo DNI
    inputDni.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // Evitar que se envíe el formulario
            buscarTutor();
        }
    });
});
</script>

<?php include $base_path . 'includes/footer.php'; ?>