<?php
// F3-FIX: Requiere sesión autenticada
require_once '../../config/auth.php';
require_once '../../config/conexion.php';

require_login();

$id_pago = isset($_GET['id_pago']) ? (int) $_GET['id_pago'] : 0;

if (!$id_pago) {
    header('Location: ../../index.php');
    exit;
}

// F11-FIX: Consultar datos del pago para mostrarlos en pantalla
$stmt = $pdo->prepare(
    "SELECT p.id, p.monto, p.concepto, p.fecha, a.apellido, a.nombre, a.dni
     FROM pagos p
     JOIN alumnos a ON p.id_alumno = a.id
     WHERE p.id = :id"
);
$stmt->execute(['id' => $id_pago]);
$pago = $stmt->fetch();

if (!$pago) {
    header('Location: ../../index.php');
    exit;
}

$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <div class="card shadow border-0">
                <div class="card-body p-5">

                    <!-- Ícono de éxito -->
                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle" style="width: 80px; height: 80px;">
                            <i class="bi bi-check-lg display-4"></i>
                        </div>
                        <h2 class="fw-bold text-dark mt-3 mb-1">¡Cobro Registrado!</h2>
                        <p class="text-muted mb-0">Recibo <strong>#<?= str_pad($pago['id'], 6, '0', STR_PAD_LEFT) ?></strong></p>
                    </div>

                    <!-- F11-FIX: Detalle del pago recién registrado -->
                    <div class="card bg-light border-0 rounded-3 mb-4">
                        <div class="card-body px-4 py-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small fw-semibold text-uppercase">Alumno</span>
                            </div>
                            <p class="fw-bold text-dark mb-1 fs-5">
                                <?= htmlspecialchars($pago['apellido']) ?>, <?= htmlspecialchars($pago['nombre']) ?>
                            </p>
                            <p class="text-muted small mb-3">DNI: <?= htmlspecialchars($pago['dni']) ?></p>

                            <div class="d-flex justify-content-between align-items-start border-top pt-3">
                                <div>
                                    <p class="text-muted small fw-semibold text-uppercase mb-1">Concepto</p>
                                    <p class="mb-0"><?= htmlspecialchars($pago['concepto']) ?></p>
                                </div>
                                <div class="text-end">
                                    <p class="text-muted small fw-semibold text-uppercase mb-1">Monto</p>
                                    <p class="fs-4 fw-bold text-success mb-0">
                                        $ <?= number_format($pago['monto'], 2, ',', '.') ?>
                                    </p>
                                </div>
                            </div>
                            <p class="text-muted small mt-2 mb-0">
                                <i class="bi bi-calendar3 me-1"></i>
                                <?= date('d/m/Y H:i', strtotime($pago['fecha'])) ?> hs
                            </p>
                        </div>
                    </div>

                    <!-- Opciones de impresión -->
                    <p class="text-muted text-center mb-3">Seleccione el formato de comprobante:</p>
                    <div class="d-grid gap-3 mb-4">
                        <!-- Matricial (Epson LX-350) -->
                        <a href="imprimir.php?id=<?= $id_pago ?>&tipo=matricial" target="_blank"
                           class="btn btn-outline-dark btn-lg text-start p-3 shadow-sm">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-printer-fill fs-3 me-3"></i>
                                <div>
                                    <div class="fw-bold">Impresora Matricial (LX-350)</div>
                                    <div class="small text-muted" style="font-size: 0.8rem;">Formato simple, rápido y económico.</div>
                                </div>
                            </div>
                        </a>

                        <!-- A4 -->
                        <a href="imprimir.php?id=<?= $id_pago ?>&tipo=a4" target="_blank"
                           class="btn btn-outline-primary btn-lg text-start p-3 shadow-sm">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-earmark-pdf-fill fs-3 me-3"></i>
                                <div>
                                    <div class="fw-bold">Impresora Común (A4)</div>
                                    <div class="small text-muted" style="font-size: 0.8rem;">Diseño completo con original y duplicado.</div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="border-top pt-4 text-center">
                        <a href="../../modules/alumnos/index.php" class="btn btn-link text-secondary text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i> Volver al listado de Alumnos
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>
