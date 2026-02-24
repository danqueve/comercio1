<?php
session_start();
require_once '../../config/conexion.php';

// Verificación de seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// --- NUEVO: Lógica de Eliminación (Solo Admin) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador') {
        $id_borrar = $_POST['id_pago'];
        try {
            $stmt_del = $pdo->prepare("DELETE FROM pagos WHERE id = :id");
            $stmt_del->execute(['id' => $id_borrar]);
            
            // Redirigir para evitar reenvío del formulario y mantener filtros
            $desde = $_GET['desde'] ?? date('Y-m-01');
            $hasta = $_GET['hasta'] ?? date('Y-m-d');
            header("Location: index.php?desde=$desde&hasta=$hasta&msg=deleted");
            exit;
        } catch (PDOException $e) {
            $error_db = "Error al eliminar: " . $e->getMessage();
        }
    } else {
        $error_db = "Acceso denegado: Solo los administradores pueden eliminar pagos.";
    }
}
// --------------------------------------------------

// Configuración de Fechas para el Filtro
// Por defecto: Desde el primer día del mes actual hasta hoy
$fecha_inicio = $_GET['desde'] ?? date('Y-m-01');
$fecha_fin    = $_GET['hasta'] ?? date('Y-m-d');

// Ajustamos las horas para cubrir todo el día
$inicio_sql = $fecha_inicio . ' 00:00:00';
$fin_sql    = $fecha_fin . ' 23:59:59';

// Consulta SQL
$sql = "SELECT p.*, a.apellido, a.nombre, a.dni
        FROM pagos p
        JOIN alumnos a ON p.id_alumno = a.id
        WHERE p.fecha BETWEEN :inicio AND :fin
        ORDER BY p.fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'inicio' => $inicio_sql,
    'fin'    => $fin_sql
]);
$pagos = $stmt->fetchAll();

// Calcular Total Recaudado en el periodo
$total_recaudado = 0;
foreach ($pagos as $p) {
    $total_recaudado += $p['monto'];
}

// --- INCLUIR CABECERA MAESTRA ---
$base_path = '../../';
include $base_path . 'includes/header.php';
?>

<div class="container mb-5">
    
    <!-- Botón volver -->
    <div class="mb-4">
        <a href="../../index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver al Inicio
        </a>
    </div>

    <!-- Mensajes de alerta -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="bi bi-trash-fill me-2"></i> El pago ha sido eliminado correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_db)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error_db ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-cash-stack text-success me-2"></i> Reporte de Caja</h3>
            <p class="text-muted mb-0">Control de ingresos y pagos registrados.</p>
        </div>
        <!-- Botón para imprimir el reporte actual (Opcional, se puede implementar luego) -->
        <button class="btn btn-outline-dark btn-sm d-none" onclick="window.print()">
            <i class="bi bi-printer"></i> Imprimir Reporte
        </button>
    </div>

    <!-- FILTROS Y RESUMEN -->
    <div class="row g-4 mb-4">
        
        <!-- Tarjeta de Filtro -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small fw-bold mb-3">Filtrar por Fecha</h6>
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small">Desde</label>
                            <input type="date" name="desde" class="form-control" value="<?= $fecha_inicio ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Hasta</label>
                            <input type="date" name="hasta" class="form-control" value="<?= $fecha_fin ?>">
                        </div>
                        <div class="col-md-4 d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Total (Estilo Caja) -->
        <div class="col-md-4">
            <div class="card shadow border-0 h-100 bg-success text-white">
                <div class="card-body d-flex flex-column justify-content-center text-center">
                    <h6 class="text-white-50 text-uppercase small mb-1">Total Recaudado</h6>
                    <h2 class="display-5 fw-bold mb-0">
                        $ <?= number_format($total_recaudado, 2, ',', '.') ?>
                    </h2>
                    <small class="text-white-50 mt-2">
                        <?= count($pagos) ?> movimientos registrados
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE MOVIMIENTOS -->
    <div class="card shadow border-0">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="mb-0 fw-bold text-dark">Detalle de Movimientos</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Fecha</th>
                            <th>Recibo N°</th>
                            <th>Alumno</th>
                            <th>Concepto</th>
                            <th>Cobrado por</th>
                            <th class="text-end">Monto</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pagos) > 0): ?>
                            <?php foreach ($pagos as $p): ?>
                                <tr>
                                    <td class="ps-4 text-muted small">
                                        <?= date('d/m/Y', strtotime($p['fecha'])) ?><br>
                                        <?= date('H:i', strtotime($p['fecha'])) ?> hs
                                    </td>
                                    <td class="fw-bold text-primary">
                                        #<?= str_pad($p['id'], 6, '0', STR_PAD_LEFT) ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= $p['apellido'] ?>, <?= $p['nombre'] ?></div>
                                        <div class="small text-muted">DNI: <?= $p['dni'] ?></div>
                                    </td>
                                    <td><?= $p['concepto'] ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= $p['usuario_responsable'] ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold text-success fs-5">
                                        $ <?= number_format($p['monto'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <!-- Grupo de botones -->
                                        <div class="btn-group">
                                            <!-- Botones de Reimpresión (Para todos) -->
                                            <a href="imprimir.php?id=<?= $p['id'] ?>&tipo=a4" target="_blank" class="btn btn-sm btn-outline-secondary" title="Reimprimir A4">
                                                <i class="bi bi-file-pdf"></i>
                                            </a>
                                            <a href="imprimir.php?id=<?= $p['id'] ?>&tipo=matricial" target="_blank" class="btn btn-sm btn-outline-dark" title="Reimprimir Ticket">
                                                <i class="bi bi-printer"></i>
                                            </a>

                                            <!-- NUEVO: Botón Eliminar (Solo Admin) -->
                                            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador'): ?>
                                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este pago?\n\nEsta acción NO se puede deshacer y afectará el total de la caja.');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id_pago" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar Pago">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-wallet2 display-4 d-block mb-3 opacity-25"></i>
                                        <p class="mb-0">No hay pagos registrados en este periodo.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>