<?php
// No necesitamos sesión start aquí si header.php ya lo hace, pero por seguridad lo dejamos o validamos
// En este diseño, header.php suele manejar la sesión, pero validamos el ID antes.
require_once '../../config/conexion.php';

$id_pago = $_GET['id_pago'] ?? null;

// Si no hay ID de pago, no tiene sentido estar aquí
if (!$id_pago) {
    header('Location: ../../index.php');
    exit;
}

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            
            <div class="card shadow border-0 text-center">
                <div class="card-body p-5">
                    
                    <div class="mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success rounded-circle" style="width: 80px; height: 80px;">
                            <i class="bi bi-check-lg display-4"></i>
                        </div>
                    </div>

                    <h2 class="fw-bold text-dark mb-3">¡Cobro Registrado!</h2>
                    <p class="text-muted mb-4">La operación se realizó con éxito. Seleccione el formato de comprobante que desea imprimir:</p>
                    
                    <div class="d-grid gap-3 mb-4">
                        <!-- OPCIÓN 1: MATRICIAL (Epson LX-350) -->
                        <a href="imprimir.php?id=<?= $id_pago ?>&tipo=matricial" target="_blank" class="btn btn-outline-dark btn-lg text-start p-3 shadow-sm card-hover">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-printer-fill fs-3 me-3"></i>
                                <div>
                                    <div class="fw-bold">Impresora Matricial (LX-350)</div>
                                    <div class="small text-muted" style="font-size: 0.8rem;">Formato simple, rápido y económico (Texto plano).</div>
                                </div>
                            </div>
                        </a>

                        <!-- OPCIÓN 2: LÁSER / INKJET (A4) -->
                        <a href="imprimir.php?id=<?= $id_pago ?>&tipo=a4" target="_blank" class="btn btn-outline-primary btn-lg text-start p-3 shadow-sm card-hover">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-earmark-pdf-fill fs-3 me-3"></i>
                                <div>
                                    <div class="fw-bold">Impresora Común (A4)</div>
                                    <div class="small text-muted" style="font-size: 0.8rem;">Diseño gráfico completo, logos y recuadros.</div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div class="border-top pt-4">
                        <a href="../../modules/alumnos/index.php" class="btn btn-link text-secondary text-decoration-none">
                            <i class="bi bi-arrow-left"></i> Volver al listado de Alumnos
                        </a>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>