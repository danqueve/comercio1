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
        'q'     => $_GET['q'] ?? '',
    ];
} elseif (isset($_SESSION['pagos_filtros']) && !isset($_GET['reset'])) {
    $_GET = array_merge($_GET, $_SESSION['pagos_filtros']);
}

// Configuración de Fechas para el Filtro
$fecha_inicio = $_GET['desde'] ?? date('Y-m-01');
$fecha_fin    = $_GET['hasta'] ?? date('Y-m-d');
$busqueda_pago = trim($_GET['q'] ?? '');

// Paginación
$pagina_actual_p = isset($_GET['pag']) && is_numeric($_GET['pag']) ? (int)$_GET['pag'] : 1;
$por_pagina_p = 20;
$offset_p = ($pagina_actual_p - 1) * $por_pagina_p;

// Ajustamos las horas para cubrir todo el día
$inicio_sql = $fecha_inicio . ' 00:00:00';
$fin_sql    = $fecha_fin . ' 23:59:59';

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
    $stmt_total->bindValue(':q',  $termino_pago);
    $stmt_total->bindValue(':q2', $termino_pago);
    $stmt_total->bindValue(':q3', $termino_pago);
}
$stmt_total->execute();
$row_total = $stmt_total->fetch();
$total_recaudado = $row_total['total_monto'] ?? 0;
$total_filas_pago = $row_total['total_filas'] ?? 0;
$total_paginas_p = (int)ceil($total_filas_pago / $por_pagina_p);

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
    $stmt->bindValue(':q',  $termino_pago);
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
            <a href="exportar.php?desde=<?= urlencode($fecha_inicio) ?>&hasta=<?= urlencode($fecha_fin) ?>&q=<?= urlencode($busqueda_pago) ?>" class="btn btn-outline-success btn-sm shadow-sm">
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
    <div class="row g-4 mb-4">
        
        <!-- Tarjeta de Filtro -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase small fw-bold mb-3">Filtrar por Fecha y Alumno</h6>
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-12">
                            <label class="form-label small">Buscar alumno</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" name="q" class="form-control border-start-0" placeholder="Nombre, Apellido o DNI..." value="<?= htmlspecialchars($busqueda_pago) ?>">
                            </div>
                        </div>
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
                        <?= $total_filas_pago ?> movimiento<?= $total_filas_pago != 1 ? 's' : '' ?> registrado<?= $total_filas_pago != 1 ? 's' : '' ?>
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
                                        <div class="fw-bold"><?= htmlspecialchars($p['apellido']) ?>, <?= htmlspecialchars($p['nombre']) ?></div>
                                        <div class="small text-muted">DNI: <?= htmlspecialchars($p['dni']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($p['concepto']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= htmlspecialchars($p['usuario_responsable']) ?>
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
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
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
        <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted small">
                <?= $total_filas_pago ?> registro<?= $total_filas_pago != 1 ? 's' : '' ?> en el período &bull; Página <?= $pagina_actual_p ?> de <?= max(1, $total_paginas_p) ?>
            </span>
            <?php if ($total_paginas_p > 1): ?>
            <nav aria-label="Paginación de pagos">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $pagina_actual_p <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?desde=<?= urlencode($fecha_inicio) ?>&hasta=<?= urlencode($fecha_fin) ?>&q=<?= urlencode($busqueda_pago) ?>&pag=<?= $pagina_actual_p - 1 ?>">&laquo;</a>
                    </li>
                    <?php
                    $ini_p = max(1, $pagina_actual_p - 2);
                    $fin_p = min($total_paginas_p, $pagina_actual_p + 2);
                    for ($i = $ini_p; $i <= $fin_p; $i++):
                    ?>
                    <li class="page-item <?= $i == $pagina_actual_p ? 'active' : '' ?>">
                        <a class="page-link" href="?desde=<?= urlencode($fecha_inicio) ?>&hasta=<?= urlencode($fecha_fin) ?>&q=<?= urlencode($busqueda_pago) ?>&pag=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $pagina_actual_p >= $total_paginas_p ? 'disabled' : '' ?>">
                        <a class="page-link" href="?desde=<?= urlencode($fecha_inicio) ?>&hasta=<?= urlencode($fecha_fin) ?>&q=<?= urlencode($busqueda_pago) ?>&pag=<?= $pagina_actual_p + 1 ?>">&raquo;</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include $base_path . 'includes/footer.php'; ?>