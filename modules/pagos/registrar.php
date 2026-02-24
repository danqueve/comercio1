<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$id_alumno = $_GET['id_alumno'] ?? null;

if (!$id_alumno) {
    die("Error: Faltan datos del alumno.");
}

// Obtener datos del alumno
$stmt = $pdo->prepare("SELECT * FROM alumnos WHERE id = :id");
$stmt->execute(['id' => $id_alumno]);
$alumno = $stmt->fetch();

if (!$alumno) {
    die("Error: Alumno no encontrado.");
}

// Obtener ciclo activo
$stmt_c = $pdo->query("SELECT * FROM ciclos_lectivos WHERE activo = 1 LIMIT 1");
$ciclo = $stmt_c->fetch();
$anio_actual = $ciclo ? $ciclo['anio'] : date('Y');
$id_ciclo = $ciclo ? $ciclo['id'] : 0;

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto = $_POST['monto'];
    $concepto = $_POST['concepto'];
    
    if ($monto > 0 && !empty($concepto)) {
        try {
            $sql = "INSERT INTO pagos (id_alumno, id_ciclo_lectivo, monto, concepto, usuario_responsable) 
                    VALUES (:id_alu, :id_ciclo, :monto, :con, :user)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'id_alu' => $id_alumno,
                'id_ciclo' => $id_ciclo,
                'monto' => $monto,
                'con' => $concepto,
                'user' => $_SESSION['nombre']
            ]);
            
            $id_pago = $pdo->lastInsertId();
            
            // Redirigir a la pantalla de éxito con opciones de impresión
            header("Location: exito.php?id_pago=$id_pago");
            exit;
            
        } catch (PDOException $e) {
            $mensaje = "Error al guardar: " . $e->getMessage();
        }
    } else {
        $mensaje = "El monto debe ser mayor a 0 y el concepto no puede estar vacío.";
    }
}

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mb-5">
    
    <!-- Botón volver -->
    <div class="mb-4">
        <a href="../../modules/alumnos/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver a Alumnos
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <div class="card shadow border-0">
                <div class="card-header bg-success text-white py-3 border-bottom-0">
                    <h4 class="card-title mb-0 fw-bold">
                        <i class="bi bi-cash-coin me-2"></i> Registrar Cobro
                    </h4>
                </div>
                <div class="card-body p-4">
                    
                    <?php if($mensaje): ?>
                        <div class="alert alert-danger shadow-sm border-0 mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $mensaje ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-light border border-success border-opacity-25 shadow-sm mb-4">
                        <h6 class="text-success fw-bold text-uppercase small mb-1">Alumno</h6>
                        <div class="fs-5 text-dark fw-bold mb-1">
                            <?= $alumno['apellido'] ?>, <?= $alumno['nombre'] ?>
                        </div>
                        <div class="small text-muted">
                            <i class="bi bi-person-vcard"></i> DNI: <?= $alumno['dni'] ?>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Concepto</label>
                            <input type="text" name="concepto" class="form-control form-control-lg" value="Bono Contribución Cooperadora <?= $anio_actual ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">Monto ($)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white text-success fw-bold">$</span>
                                <input type="number" name="monto" class="form-control fw-bold text-success" placeholder="0.00" step="100" required autofocus>
                            </div>
                        </div>

                        <div class="d-grid gap-2 pt-2">
                            <button type="submit" class="btn btn-success btn-lg shadow-sm">
                                <i class="bi bi-check-lg"></i> Confirmar Cobro
                            </button>
                            <a href="../../modules/alumnos/index.php" class="btn btn-link text-muted text-decoration-none">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>