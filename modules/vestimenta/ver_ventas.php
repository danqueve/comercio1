<?php
session_start();
require_once '../../config/conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Filtros de fecha
$fecha_inicio = $_GET['desde'] ?? date('Y-m-01');
$fecha_fin = $_GET['hasta'] ?? date('Y-m-d');
$inicio_sql = $fecha_inicio . ' 00:00:00';
$fin_sql = $fecha_fin . ' 23:59:59';

// Ventas registradas en el período
$sql = "SELECT v.*, u.nombre_completo as usuario_nombre 
        FROM vestimenta_ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.id
        WHERE v.fecha BETWEEN :inicio AND :fin
        ORDER BY v.fecha DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['inicio' => $inicio_sql, 'fin' => $fin_sql]);
$ventas = $stmt->fetchAll();
$total_periodo = array_sum(array_column($ventas, 'total'));

$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h3><i class="bi bi-list-check text-primary me-2"></i> Historial de Ventas — Vestimenta</h3>
        <div class="d-flex gap-2 flex-wrap">
            <a href="imprimir_ventas.php?desde=<?= urlencode($fecha_inicio) ?>&hasta=<?= urlencode($fecha_fin) ?>"
                target="_blank" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
            </a>
            <a href="exportar_ventas.php?desde=<?= urlencode($fecha_inicio) ?>&hasta=<?= urlencode($fecha_fin) ?>"
                class="btn btn-outline-success btn-sm shadow-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
            </a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver al Inventario
            </a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'venta_ok'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> ¡Venta registrada correctamente! El stock ha sido actualizado.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'venta_eliminada'): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-trash-fill me-2"></i> Venta eliminada y stock restaurado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'sin_permiso'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-shield-x me-2"></i> Acción no permitida. Solo los administradores pueden eliminar ventas.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif ($_GET['msg'] === 'error_eliminar'): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Error al eliminar la venta. Intentá nuevamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Filtro de fechas -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted fw-semibold">Desde</label>
                    <input type="date" name="desde" class="form-control" value="<?= $fecha_inicio ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted fw-semibold">Hasta</label>
                    <input type="date" name="hasta" class="form-control" value="<?= $fecha_fin ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel-fill me-1"></i> Filtrar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="?desde=<?= date('Y-m-01') ?>&hasta=<?= date('Y-m-d') ?>"
                        class="btn btn-outline-secondary w-100">
                        <i class="bi bi-calendar-month me-1"></i> Este mes
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h6 class="m-0 fw-bold text-dark">
                Ventas del <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?>
            </h6>
            <span class="badge bg-success rounded-pill px-3 py-2">
                Total: $ <?= number_format($total_periodo, 0, ',', '.') ?>
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ID Venta</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th class="text-end">Total</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ventas) > 0): ?>
                            <?php foreach ($ventas as $v): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">#<?= $v['id'] ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></td>
                                    <td><?= htmlspecialchars($v['usuario_nombre'] ?? 'Desconocido') ?></td>
                                    <td class="text-end fw-bold text-success">$ <?= number_format($v['total'], 2, ',', '.') ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group">
                                            <a href="detalle_venta.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Ver Detalle
                                            </a>
                                            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador'): ?>
                                                <a href="eliminar_venta.php?id=<?= $v['id'] ?>"
                                                   class="btn btn-sm btn-outline-danger"
                                                   title="Eliminar venta (restaura stock)"
                                                   onclick="return confirm('Eliminar Venta #<?= str_pad($v["id"], 5, "0", STR_PAD_LEFT) ?> por $<?= number_format($v["total"], 0, ",", ".") ?>?\nSe restaurara el stock de todos los productos.')">
                                                    <i class="bi bi-trash3"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-receipt display-6 d-block mb-3 opacity-50"></i>
                                        <p class="mb-0">No se encontraron ventas en el período seleccionado.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-muted small d-flex justify-content-between">
            <span><?= count($ventas) ?> venta<?= count($ventas) != 1 ? 's' : '' ?> en el período</span>
            <span class="fw-bold text-dark">Total: $ <?= number_format($total_periodo, 0, ',', '.') ?></span>
        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>