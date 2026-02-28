<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: ver_ventas.php');
    exit;
}

// Obtener cabecera de venta
$stmt = $pdo->prepare("SELECT v.*, u.nombre_completo as usuario_nombre 
                        FROM vestimenta_ventas v
                        LEFT JOIN usuarios u ON v.id_usuario = u.id
                        WHERE v.id = ?");
$stmt->execute([$id]);
$venta = $stmt->fetch();

if (!$venta) {
    header('Location: ver_ventas.php');
    exit;
}

// Obtener detalles de venta
$sql_d = "SELECT vd.*, p.descripcion, p.talle 
          FROM vestimenta_venta_detalles vd
          JOIN vestimenta_productos p ON vd.id_producto = p.id
          WHERE vd.id_venta = ?";
$stmt_d = $pdo->prepare($sql_d);
$stmt_d->execute([$id]);
$detalles = $stmt_d->fetchAll();

$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container pb-5">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h3><i class="bi bi-receipt text-primary me-2"></i> Detalle de Venta #
            <?= $id ?>
        </h3>
        <a href="ver_ventas.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Volver al Historial
        </a>
    </div>

    <div class="row g-4">
        <!-- Resumen de Venta -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white fw-bold py-3"><i class="bi bi-info-circle me-2 text-primary"></i>
                    Información General</div>
                <div class="card-body">
                    <p class="mb-2"><strong>Fecha:</strong>
                        <?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?>
                    </p>
                    <p class="mb-2"><strong>Usuario:</strong>
                        <?= htmlspecialchars($venta['usuario_nombre']) ?>
                    </p>
                    <hr>
                    <div class="text-center py-3">
                        <h6 class="text-muted text-uppercase small mb-1">Total de la Venta</h6>
                        <h2 class="fw-bold text-success mb-0">$
                            <?= number_format($venta['total'], 2, ',', '.') ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de Items -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3"><i class="bi bi-box-seam me-2 text-primary"></i> Items
                    Vendidos</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Producto</th>
                                    <th>Origen</th>
                                    <th class="text-center">Cant.</th>
                                    <th class="text-end">Precio Unit.</th>
                                    <th class="text-end pe-3">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detalles as $det): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <span class="fw-bold d-block">
                                                <?= htmlspecialchars($det['descripcion']) ?>
                                            </span>
                                            <small class="text-muted">Talle:
                                                <?= htmlspecialchars($det['talle']) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= ucfirst($det['origen_stock']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?= $det['cantidad'] ?>
                                        </td>
                                        <td class="text-end">$
                                            <?= number_format($det['precio_unitario'], 2, ',', '.') ?>
                                        </td>
                                        <td class="text-end pe-3 fw-bold">$
                                            <?= number_format($det['precio_unitario'] * $det['cantidad'], 2, ',', '.') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>