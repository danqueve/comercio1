<?php
session_start();
require_once '../../config/conexion.php';
require_once '../../config/logger.php';

// Solo Administradores pueden ver el log de auditoría
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'Administrador') {
    header('Location: ../../index.php');
    exit;
}

// Filtros
$filtro_accion = trim($_GET['accion'] ?? '');
$filtro_desde  = $_GET['desde'] ?? date('Y-m-01');
$filtro_hasta  = $_GET['hasta'] ?? date('Y-m-d');
$pagina_log    = isset($_GET['pag']) && is_numeric($_GET['pag']) ? (int)$_GET['pag'] : 1;
$por_pag_log   = 30;
$offset_log    = ($pagina_log - 1) * $por_pag_log;

$inicio_sql = $filtro_desde . ' 00:00:00';
$fin_sql    = $filtro_hasta . ' 23:59:59';

$where_accion = '';
if (!empty($filtro_accion)) {
    $where_accion = " AND accion = :accion";
}

// Contar total
$sql_count = "SELECT COUNT(*) FROM logs_actividad WHERE fecha BETWEEN :ini AND :fin" . $where_accion;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->bindValue(':ini', $inicio_sql);
$stmt_count->bindValue(':fin', $fin_sql);
if (!empty($filtro_accion)) $stmt_count->bindValue(':accion', $filtro_accion);
$stmt_count->execute();
$total_logs  = $stmt_count->fetchColumn();
$total_pags_log = ceil($total_logs / $por_pag_log);

// Registros
$sql_log = "SELECT * FROM logs_actividad
            WHERE fecha BETWEEN :ini AND :fin" . $where_accion . "
            ORDER BY fecha DESC
            LIMIT :lim OFFSET :off";
$stmt_log = $pdo->prepare($sql_log);
$stmt_log->bindValue(':ini', $inicio_sql);
$stmt_log->bindValue(':fin', $fin_sql);
if (!empty($filtro_accion)) $stmt_log->bindValue(':accion', $filtro_accion);
$stmt_log->bindValue(':lim', $por_pag_log, PDO::PARAM_INT);
$stmt_log->bindValue(':off', $offset_log, PDO::PARAM_INT);
$stmt_log->execute();
$logs = $stmt_log->fetchAll();

// Acciones disponibles para el filtro
$acciones_disponibles = $pdo->query("SELECT DISTINCT accion FROM logs_actividad ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN);

$base_path = '../../';
include $base_path . 'includes/header.php';

// Colores de badge por tipo de acción
function badge_accion(string $accion): string {
    if (str_starts_with($accion, 'ALTA'))     return 'success';
    if (str_starts_with($accion, 'EDITAR'))   return 'primary';
    if (str_starts_with($accion, 'ELIMINAR')) return 'danger';
    if (str_starts_with($accion, 'LOGIN'))    return $accion === 'LOGIN_FAIL' ? 'warning' : 'info';
    return 'secondary';
}
?>

<div class="container mb-5">

    <div class="mb-4">
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Volver a Usuarios
        </a>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-0"><i class="bi bi-journal-text text-primary me-2"></i> Log de Auditoría</h3>
            <p class="text-muted mb-0">Registro de todas las acciones realizadas en el sistema.</p>
        </div>
        <span class="badge bg-primary rounded-pill fs-6"><?= number_format($total_logs) ?> registros</span>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Desde</label>
                    <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($filtro_desde) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Hasta</label>
                    <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($filtro_hasta) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Tipo de Acción</label>
                    <select name="accion" class="form-select">
                        <option value="">— Todas —</option>
                        <?php foreach ($acciones_disponibles as $ac): ?>
                            <option value="<?= htmlspecialchars($ac) ?>" <?= $filtro_accion === $ac ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ac) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card shadow border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-muted small fw-bold">FECHA</th>
                            <th class="py-3 text-muted small fw-bold">USUARIO</th>
                            <th class="py-3 text-muted small fw-bold">ACCIÓN</th>
                            <th class="py-3 text-muted small fw-bold">DETALLE</th>
                            <th class="py-3 text-muted small fw-bold">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?= date('d/m/Y H:i', strtotime($log['fecha'])) ?></td>
                                <td class="fw-medium"><?= htmlspecialchars($log['nombre_usuario']) ?></td>
                                <td>
                                    <span class="badge bg-<?= badge_accion($log['accion']) ?> bg-opacity-10 text-<?= badge_accion($log['accion']) ?> border border-<?= badge_accion($log['accion']) ?>">
                                        <?= htmlspecialchars($log['accion']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($log['detalle'] ?? '') ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($log['ip'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-journal-x fs-3 d-block mb-2"></i>
                                No hay registros para el período seleccionado
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($total_pags_log > 1): ?>
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-top bg-light">
                <div class="text-muted small">
                    Mostrando <?= min($offset_log + 1, $total_logs) ?>–<?= min($offset_log + $por_pag_log, $total_logs) ?> de <?= $total_logs ?>
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = 1; $p <= $total_pags_log; $p++): ?>
                            <li class="page-item <?= $p === $pagina_log ? 'active' : '' ?>">
                                <a class="page-link" href="?desde=<?= urlencode($filtro_desde) ?>&hasta=<?= urlencode($filtro_hasta) ?>&accion=<?= urlencode($filtro_accion) ?>&pag=<?= $p ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include $base_path . 'includes/footer.php'; ?>
