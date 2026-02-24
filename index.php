<?php
session_start();
require_once 'config/conexion.php';

// Seguridad: Si no hay usuario logueado, ir al login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// --- LÓGICA DE DATOS (Estadísticas) — con caché de sesión de 5 min ---
$cache_key   = 'dashboard_stats';
$cache_ttl   = 300; // 5 minutos
$cache_valid = isset($_SESSION[$cache_key . '_time']) && (time() - $_SESSION[$cache_key . '_time']) < $cache_ttl;

if ($cache_valid) {
    // Leer desde caché
    $anio_actual    = $_SESSION[$cache_key]['anio_actual'];
    $id_ciclo       = $_SESSION[$cache_key]['id_ciclo'];
    $total_alumnos  = $_SESSION[$cache_key]['total_alumnos'];
    $inscritos_ciclo = $_SESSION[$cache_key]['inscritos_ciclo'];
    $total_cursos   = $_SESSION[$cache_key]['total_cursos'];
    $top_cursos     = $_SESSION[$cache_key]['top_cursos'];
} else {
    // 1. Ciclo Lectivo Activo
    $stmt = $pdo->query("SELECT * FROM ciclos_lectivos WHERE activo = 1 LIMIT 1");
    $ciclo = $stmt->fetch();
    $anio_actual = $ciclo ? $ciclo['anio'] : '---';
    $id_ciclo    = $ciclo ? $ciclo['id'] : 0;

    // 2. Total de Alumnos registrados (Histórico)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM alumnos");
    $total_alumnos = $stmt->fetch()['total'];

    // 3. Alumnos inscritos en el ciclo ACTIVO (Regulares)
    $inscritos_ciclo = 0;
    $total_cursos    = 0;
    $top_cursos      = [];
    if ($id_ciclo) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM inscripciones WHERE id_ciclo_lectivo = :ciclo AND estado = 'Regular'");
        $stmt->execute(['ciclo' => $id_ciclo]);
        $inscritos_ciclo = $stmt->fetch()['total'];

        // 4. Cursos con más alumnos (Top 4 para el panel lateral)
        $stmt_tc = $pdo->prepare("SELECT COUNT(DISTINCT id_curso) as total FROM inscripciones WHERE id_ciclo_lectivo = :ciclo AND estado = 'Regular'");
        $stmt_tc->execute(['ciclo' => $id_ciclo]);
        $total_cursos = $stmt_tc->fetch()['total'];

        $sql_top = "SELECT c.anio_curso, c.division, c.turno, COUNT(i.id) as cantidad
                    FROM cursos c
                    LEFT JOIN inscripciones i ON c.id = i.id_curso AND i.id_ciclo_lectivo = :ciclo AND i.estado = 'Regular'
                    GROUP BY c.id
                    ORDER BY cantidad DESC
                    LIMIT 4";
        $stmt = $pdo->prepare($sql_top);
        $stmt->execute(['ciclo' => $id_ciclo]);
        $top_cursos = $stmt->fetchAll();
    }

    // Guardar en caché de sesión
    $_SESSION[$cache_key] = compact('anio_actual', 'id_ciclo', 'total_alumnos', 'inscritos_ciclo', 'total_cursos', 'top_cursos');
    $_SESSION[$cache_key . '_time'] = time();
}

// --- INCLUIR CABECERA MAESTRA ---
// Definimos la ruta base como './' porque estamos en la raíz
$base_path = './'; 
include 'includes/header.php';
?>

<div class="container">
    
    <!-- Encabezado de Bienvenida y Fecha -->
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold text-dark">Panel de Control</h2>
            <p class="text-muted mb-0">Resumen del Ciclo Lectivo <strong><?= $anio_actual ?></strong></p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge bg-primary rounded-pill px-3 py-2 shadow-sm">
                <i class="bi bi-calendar-event"></i> Hoy: <?= date('d/m/Y') ?>
            </span>
        </div>
    </div>

    <!-- Tarjetas de Estadísticas (Responsive mejorado) -->
    <div class="row g-4 mb-5">
        <!-- Tarjeta 1: Total Alumnos -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-primary bg-opacity-10 p-3 rounded-3 text-primary">
                            <i class="bi bi-people-fill fs-4"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted text-uppercase small mb-1">Total Histórico</h6>
                            <h3 class="fw-bold mb-0"><?= $total_alumnos ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta 2: Inscritos este año -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-success bg-opacity-10 p-3 rounded-3 text-success">
                            <i class="bi bi-person-check-fill fs-4"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted text-uppercase small mb-1">Inscritos <?= $anio_actual ?></h6>
                            <h3 class="fw-bold mb-0"><?= $inscritos_ciclo ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta 3: Cursos Activos -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-info bg-opacity-10 p-3 rounded-3 text-info">
                            <i class="bi bi-grid-fill fs-4"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted text-uppercase small mb-1">Cursos Activos</h6>
                            <h3 class="fw-bold mb-0"><?= $total_cursos ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta 4: Accesos (Placeholder visual) -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm bg-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-warning bg-opacity-10 p-3 rounded-3 text-warning">
                            <i class="bi bi-clock-history fs-4"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted text-uppercase small mb-1">Ciclo Lectivo</h6>
                            <h3 class="fw-bold mb-0"><?= $anio_actual ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Columna Izquierda: Accesos Rápidos -->
        <div class="col-lg-8">
            <h5 class="fw-bold mb-3 text-secondary">Accesos Rápidos</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <a href="modules/alumnos/index.php" class="text-decoration-none">
                        <div class="card h-100 bg-white border-0 shadow-sm card-hover">
                            <div class="card-body d-flex align-items-center p-4">
                                <div class="bg-primary text-white rounded-circle p-3 me-3 shadow-sm">
                                    <i class="bi bi-backpack4 fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="text-dark fw-bold mb-1">Alumnos</h5>
                                    <p class="text-muted small mb-0">Inscripciones, Fichas y Pagos.</p>
                                </div>
                                <i class="bi bi-chevron-right ms-auto text-muted"></i>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="modules/cursos/index.php" class="text-decoration-none">
                        <div class="card h-100 bg-white border-0 shadow-sm card-hover">
                            <div class="card-body d-flex align-items-center p-4">
                                <div class="bg-success text-white rounded-circle p-3 me-3 shadow-sm">
                                    <i class="bi bi-journal-bookmark fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="text-dark fw-bold mb-1">Cursos</h5>
                                    <p class="text-muted small mb-0">Listas de asistencia y reportes.</p>
                                </div>
                                <i class="bi bi-chevron-right ms-auto text-muted"></i>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Sección Administración (Solo Admin) -->
            <?php if ($_SESSION['rol'] === 'Administrador'): ?>
            <h5 class="fw-bold mb-3 mt-4 text-secondary">Administración</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <a href="modules/usuarios/index.php" class="text-decoration-none">
                        <div class="card h-100 bg-white border-0 shadow-sm border-start border-danger border-4">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-shield-lock text-danger fs-3 me-3"></i>
                                    <div>
                                        <h6 class="text-dark fw-bold mb-0">Usuarios del Sistema</h6>
                                        <small class="text-muted">Crear o editar accesos</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="modules/ciclos/index.php" class="text-decoration-none">
                        <div class="card h-100 bg-white border-0 shadow-sm border-start border-danger border-4">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-calendar-range text-danger fs-3 me-3"></i>
                                    <div>
                                        <h6 class="text-dark fw-bold mb-0">Ciclos Lectivos</h6>
                                        <small class="text-muted">Abrir o cerrar años</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Columna Derecha: Panel Lateral -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold py-3 border-bottom">
                    <i class="bi bi-graph-up text-primary me-2"></i> Cursos más poblados
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (count($top_cursos) > 0): ?>
                        <?php foreach ($top_cursos as $top): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-4 py-3">
                                <div>
                                    <span class="fw-bold"><?= $top['anio_curso'] ?> "<?= $top['division'] ?>"</span>
                                    <br>
                                    <small class="text-muted">Turno <?= $top['turno'] ?></small>
                                </div>
                                <span class="badge bg-light text-primary border border-primary rounded-pill">
                                    <?= $top['cantidad'] ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted py-4">
                            <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                            Sin inscripciones activas
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php 
// Query de recaudación mensual para el gráfico
$stmt_chart = $pdo->query(
    "SELECT MONTH(fecha) as mes, SUM(monto) as total
     FROM pagos
     WHERE YEAR(fecha) = YEAR(CURDATE())
     GROUP BY MONTH(fecha)
     ORDER BY mes ASC"
);
$pagos_por_mes = $stmt_chart->fetchAll(PDO::FETCH_KEY_PAIR); // mes => total

$meses_es = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$labels  = json_encode($meses_es);
$data    = json_encode(array_map(
    fn($m) => round((float)($pagos_por_mes[$m] ?? 0), 2),
    range(1, 12)
));
?>

<div class="container mb-5">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-bold py-3 border-bottom d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-bar-chart-fill text-success me-2"></i> Recaudación Mensual <?= date('Y') ?>
            </div>
            <a href="modules/pagos/index.php" class="btn btn-sm btn-outline-success">
                <i class="bi bi-cash-stack"></i> Ver Reporte Completo
            </a>
        </div>
        <div class="card-body py-4">
            <canvas id="chartPagosMensuales" height="90"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const ctx = document.getElementById('chartPagosMensuales');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= $labels ?>,
            datasets: [{
                label: 'Total cobrado ($)',
                data: <?= $data ?>,
                backgroundColor: 'rgba(25, 135, 84, 0.15)',
                borderColor: 'rgba(25, 135, 84, 0.85)',
                borderWidth: 2,
                borderRadius: 6,
                hoverBackgroundColor: 'rgba(25, 135, 84, 0.3)',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => '$ ' + ctx.parsed.y.toLocaleString('es-AR', {minimumFractionDigits: 2})
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => '$ ' + Number(v).toLocaleString('es-AR')
                    }
                }
            }
        }
    });
})();
</script>

<?php 
// Incluir pie de página maestro
include 'includes/footer.php'; 
?>