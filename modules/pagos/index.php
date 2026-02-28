<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../config/csrf.php';
require_once '../../config/logger.php';

// Verificación de seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// --- NUEVO: Lógica de Eliminación (Solo Admin) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    csrf_verify(); // Verificar token CSRF
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador') {
        $id_borrar = $_POST['id_pago'];
        try {
            $stmt_del = $pdo->prepare("DELETE FROM pagos WHERE id = :id");
            $stmt_del->execute(['id' => $id_borrar]);

            audit_log($pdo, 'ELIMINAR_PAGO', "ID pago: $id_borrar");

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

// Item 17: Recordar filtros en sesión
if (isset($_GET['desde']) || isset($_GET['hasta']) || isset($_GET['q'])) {
    $_SESSION['pagos_filtros'] = [
        'desde' => $_GET['desde'] ?? date('Y-m-01'),
        'hasta' => $_GET['hasta'] ?? date('Y-m-d'),
        'q' => $_GET['q'] ?? '',
    ];
} elseif (isset($_SESSION['pagos_filtros']) && !isset($_GET['reset'])) {
    $_GET = array_merge($_GET, $_SESSION['pagos_filtros']);
}

// Configuración de Fechas para el Filtro
$fecha_inicio = $_GET['desde'] ?? date('Y-m-01');
$fecha_fin = $_GET['hasta'] ?? date('Y-m-d');
$busqueda_pago = trim($_GET['q'] ?? '');

// Paginación
$pagina_actual_p = isset($_GET['pag']) && is_numeric($_GET['pag']) ? (int) $_GET['pag'] : 1;
$por_pagina_p = 20;
$offset_p = ($pagina_actual_p - 1) * $por_pagina_p;

// Ajustamos las horas para cubrir todo el día
$inicio_sql = $fecha_inicio . ' 00:00:00';
$fin_sql = $fecha_fin . ' 23:59:59';

// Condición de búsqueda reutilizable
$where_busqueda = '';
$termino_pago = "%$busqueda_pago%";
if (!empty($busqueda_pago)) {
    $where_busqueda = " AND (a.apellido LIKE :q OR a.nombre LIKE :q2 OR a.dni LIKE :q3)";
}

// Total monetario y conteo (para el resumen y paginación) — en SQL, sin cargar todas las filas
$sql_total = "SELECT SUM(p.monto) as total_monto, COUNT(p.id) as total_filas
              FROM pagos p
              JOIN alumnos a ON p.id_alumno = a.id
              WHERE p.fecha BETWEEN :inicio AND :fin" . $where_busqueda;
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->bindValue(':inicio', $inicio_sql);
$stmt_total->bindValue(':fin', $fin_sql);
if (!empty($busqueda_pago)) {
    $stmt_total->bindValue(':q', $termino_pago);
    $stmt_total->bindValue(':q2', $termino_pago);
    $stmt_total->bindValue(':q3', $termino_pago);
}
$stmt_total->execute();
$row_total = $stmt_total->fetch();
$total_recaudado = $row_total['total_monto'] ?? 0;
$total_filas_pago = $row_total['total_filas'] ?? 0;
$total_paginas_p = (int) ceil($total_filas_pago / $por_pagina_p);

// --- NUEVO: Total Vestimenta para el mismo rango ---
$sql_vest = "SELECT SUM(total) as total FROM vestimenta_ventas WHERE fecha BETWEEN :inicio AND :fin";
$stmt_vest = $pdo->prepare($sql_vest);
$stmt_vest->execute(['inicio' => $inicio_sql, 'fin' => $fin_sql]);
$total_vestimenta = $stmt_vest->fetch()['total'] ?? 0;

// Consulta paginada
$sql = "SELECT p.*, a.apellido, a.nombre, a.dni
        FROM pagos p
        JOIN alumnos a ON p.id_alumno = a.id
        WHERE p.fecha BETWEEN :inicio AND :fin" . $where_busqueda . "
        ORDER BY p.fecha DESC
        LIMIT :limite OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':inicio', $inicio_sql);
$stmt->bindValue(':fin', $fin_sql);
if (!empty($busqueda_pago)) {
    $stmt->bindValue(':q', $termino_pago);
    $stmt->bindValue(':q2', $termino_pago);
    $stmt->bindValue(':q3', $termino_pago);
}
$stmt->bindValue(':limite', $por_pagina_p, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset_p, PDO::PARAM_INT);
$stmt->execute();
$pagos = $stmt->fetchAll();

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
        <div class="d-flex gap-2">
            <a href="imprimir_reporte.php?desde=<?= urlencode($fecha_inicio) ?>&hasta=<?= urlencode($fecha_fin) ?>&q=<?= urlencode($busqueda_pago) ?>"
                target="_blank" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
            </a>
            <a href="exportar.php?desde=<?= urlencode($fecha_inicio) ?>&hasta=<?= urlencode($fecha_fin) ?>&q=<?= urlencode($busqueda_pago) ?>"
                class="btn btn-outline-success btn-sm shadow-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
            </a>
            <?php if (isset($_SESSION['pagos_filtros'])): ?>
                <a href="?reset=1" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros guardados">
                    <i class="bi bi-x-circle"></i> Limpiar filtros
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTROS Y RESUMEN -->
    <div class="row g-4 mb-5">

        <!-- Tarjeta de Filtro Modernizada -->
        <div class="col-xl-7">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="text-secondary text-uppercase small fw-bold mb-0">
                        <i class="bi bi-filter-right me-2"></i> Parámetros de Búsqueda
                    </h6>
                </div>
                <div class="card-body bg-light bg-opacity-50">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-12">
                            <div class="input-group input-group-lg shadow-sm">
                                <span class="input-group-text bg-white border-end-0 border-0 rounded-start-3"><i
                                        class="bi bi-search text-muted"></i></span>
                                <input type="text" name="q" class="form-control border-0 rounded-end-3"
                                    placeholder="Nombre, Apellido o DNI del alumno..."
                                    value="<?= htmlspecialchars($busqueda_pago) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted fw-semibold">Desde</label>
                            <input type="date" name="desde" class="form-control rounded-3 border-0 shadow-sm"
                                value="<?= $fecha_inicio ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted fw-semibold">Hasta</label>
                            <input type="date" name="hasta" class="form-control rounded-3 border-0 shadow-sm"
                                value="<?= $fecha_fin ?>">
                        </div>
                        <div class="col-md-4 d-grid align-items-end">
                            <button type="submit" class="btn btn-primary rounded-3 shadow-sm py-2">
                                <i class="bi bi-funnel-fill me-2"></i> Aplicar Filtros
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tarjetas de Totales Verticales y Premium -->
        <div class="col-xl-5">
            <div class="row g-3">
                <!-- Card Cuotas -->
                <div class="col-md-6 col-xl-12">
                    <div class="card border-0 rounded-4 bg-success bg-gradient text-white shadow-sm overflow-hidden">
                        <div class="card-body d-flex align-items-center p-3">
                            <div class="flex-shrink-0 bg-white bg-opacity-25 rounded-3 p-3 me-3">
                                <i class="bi bi-person-check fs-3"></i>
                            </div>
                            <div>
                                <h6 class="text-white-50 small mb-0 fw-semibold text-uppercase">Cobro Cuotas</h6>
                                <h4 class="fw-bold mb-0">$ <?= number_format($total_recaudado, 2, ',', '.') ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Card Vestimenta -->
                <div class="col-md-6 col-xl-12">
                    <div class="card border-0 rounded-4 bg-info bg-gradient text-white shadow-sm overflow-hidden">
                        <div class="card-body d-flex align-items-center p-3">
                            <div class="flex-shrink-0 bg-white bg-opacity-25 rounded-3 p-3 me-3">
                                <i class="bi bi-bag-check fs-3"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="text-white-50 small mb-0 fw-semibold text-uppercase">Ventas Vestimenta</h6>
                                <h4 class="fw-bold mb-0">$ <?= number_format($total_vestimenta, 2, ',', '.') ?></h4>
                            </div>
                            <a href="../vestimenta/imprimir_balance.php?desde=<?= $fecha_inicio ?>&hasta=<?= $fecha_fin ?>"
                                target="_blank"
                                class="btn btn-sm btn-light bg-opacity-25 border-0 text-white rounded-pill ms-2"
                                title="Imprimir Balance">
                                <i class="bi bi-printer-fill"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <!-- Total Global -->
                <div class="col-12">
                    <div class="card border-0 rounded-4 bg-white shadow-sm border-start border-primary border-5">
                        <div class="card-body d-flex justify-content-between align-items-center py-2 px-4 text-primary">
                            <div class="fw-bold text-uppercase small">Total General Neto</div>
                            <div class="h4 fw-bold mb-0">$
                                <?= number_format($total_recaudado + $total_vestimenta, 2, ',', '.') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE MOVIMIENTOS -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div
            class="card-header bg-white py-4 px-4 d-flex justify-content-between align-items-center border-bottom border-light">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-columns-reverse text-primary me-2"></i> Detalle de
                Movimientos</h5>
            <span class="badge bg-light text-primary border rounded-pill px-3 py-2 fw-normal">
                <?= $total_filas_pago ?> Registros en el período
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light bg-opacity-75">
                        <tr>
                            <th class="ps-4 text-muted small fw-bold">FECHA</th>
                            <th class="text-muted small fw-bold">RECIBO</th>
                            <th class="text-muted small fw-bold">ALUMNO / DNI</th>
                            <th class="text-muted small fw-bold">CONCEPTO</th>
                            <th class="text-muted small fw-bold">RESPONSABLE</th>
                            <th class="text-end text-muted small fw-bold">MONTO</th>
                            <th class="text-end pe-4 text-muted small fw-bold">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php if (count($pagos) > 0): ?>
                            <?php foreach ($pagos as $p): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= date('d/m/Y', strtotime($p['fecha'])) ?></div>
                                        <div class="text-muted small"><?= date('H:i', strtotime($p['fecha'])) ?> hs</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold">
                                            #<?= str_pad($p['id'], 6, '0', STR_PAD_LEFT) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($p['apellido']) ?>,
                                            <?= htmlspecialchars($p['nombre']) ?>
                                        </div>
                                        <div class="small text-muted font-monospace"><?= htmlspecialchars($p['dni']) ?></div>
                                    </td>
                                    <td>
                                        <div class="text-dark small"><?= htmlspecialchars($p['concepto']) ?></div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2 bg-light rounded-circle text-center"
                                                style="width: 24px; height: 24px; line-height: 24px;">
                                                <i class="bi bi-person text-secondary small"></i>
                                            </div>
                                            <span
                                                class="text-muted small"><?= htmlspecialchars($p['usuario_responsable']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        $ <?= number_format($p['monto'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group shadow-sm rounded-3 overflow-hidden">
                                            <a href="imprimir.php?id=<?= $p['id'] ?>&tipo=a4" target="_blank"
                                                class="btn btn-sm btn-white text-secondary border-end" title="PDF A4">
                                                <i class="bi bi-filetype-pdf"></i>
                                            </a>
                                            <a href="imprimir.php?id=<?= $p['id'] ?>&tipo=matricial" target="_blank"
                                                class="btn btn-sm btn-white text-secondary border-end" title="Ticket">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador'): ?>
                                                <form method="POST" action="" class="d-inline"
                                                    onsubmit="return confirm('¿Eliminar definitivamente este pago?');">
                                                    <input type="hidden" name="accion" value="eliminar">
                                                    <input type="hidden" name="id_pago" value="<?= $p['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    <button type="submit" class="btn btn-sm btn-white text-danger" title="Eliminar">
                                                        <i class="bi bi-trash3"></i>
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
        <!-- Paginación con estilo mejorado -->
        <div
            class="card-footer bg-white py-3 px-4 d-flex justify-content-between align-items-center border-top border-light">
            <span class="text-muted small">
                Página <?= $pagina_actual_p ?> de <?= max(1, $total_paginas_p) ?>
            </span>
            <?php if ($total_paginas_p > 1): ?>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $pagina_actual_p <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link border-0 shadow-none rounded-circle me-1"
                                href="?desde=<?= urlencode($fecha_inicio) ?>&hasta=<?= urlencode($fecha_fin) ?>&q=<?= urlencode($busqueda_pago) ?>&pag=<?= $pagina_actual_p - 1 ?>"><i
                                    class="bi bi-chevron-left"></i></a>
                        </li>
                        <?php
                        $ini_p = max(1, $pagina_actual_p - 2);
                        $fin_p = min($total_paginas_p, $pagina_actual_p + 2);
                        for ($i = $ini_p; $i <= $fin_p; $i++):
                            ?>
                            <li class="page-item <?= $i == $pagina_actual_p ? 'active' : '' ?>">
                                <a class="page-link border-0 shadow-none rounded-circle mx-1"
                                    href="?desde=<?= urlencode($fecha_inicio) ?>&hasta=<?= urlencode($fecha_fin) ?>&q=<?= urlencode($busqueda_pago) ?>&pag=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $pagina_actual_p >= $total_paginas_p ? 'disabled' : '' ?>">
                            <a class="page-link border-0 shadow-none rounded-circle ms-1"
                                href="?desde=<?= urlencode($fecha_inicio) ?>&hasta=<?= urlencode($fecha_fin) ?>&q=<?= urlencode($busqueda_pago) ?>&pag=<?= $pagina_actual_p + 1 ?>"><i
                                    class="bi bi-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Estilos adicionales para mejorar la visual */
    .pagination .page-item .page-link {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6c757d;
    }

    .pagination .page-item.active .page-link {
        background-color: var(--bs-primary);
        color: white;
    }

    .btn-white {
        background-color: white;
        border: 1px solid #f8f9fa;
    }

    .btn-white:hover {
        background-color: #f8f9fa;
    }

    .card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    /* .card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.1)!important; } */
</style>

<?php include $base_path . 'includes/footer.php'; ?>