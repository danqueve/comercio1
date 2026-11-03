<?php
session_start();
require_once 'config/conexion.php';

// Seguridad: Si no hay usuario logueado, ir al login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// --- LÓGICA DE DATOS (Estadísticas) — con caché de sesión de 5 min ---
$cache_key = 'dashboard_stats';
$cache_ttl = 300; // 5 minutos
$cache_valid = isset($_SESSION[$cache_key . '_time']) && (time() - $_SESSION[$cache_key . '_time']) < $cache_ttl;

if ($cache_valid) {
    // Leer desde caché
    $anio_actual = $_SESSION[$cache_key]['anio_actual'];
    $id_ciclo = $_SESSION[$cache_key]['id_ciclo'];
    $total_alumnos = $_SESSION[$cache_key]['total_alumnos'];
    $inscritos_ciclo = $_SESSION[$cache_key]['inscritos_ciclo'];
    $total_cursos = $_SESSION[$cache_key]['total_cursos'];
    $top_cursos = $_SESSION[$cache_key]['top_cursos'];
} else {
    // 1. Ciclo Lectivo Activo
    $stmt = $pdo->query("SELECT * FROM ciclos_lectivos WHERE activo = 1 LIMIT 1");
    $ciclo = $stmt->fetch();
    $anio_actual = $ciclo ? $ciclo['anio'] : '---';
    $id_ciclo = $ciclo ? $ciclo['id'] : 0;

    // 2. Total de Alumnos registrados (Histórico)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM alumnos");
    $total_alumnos = $stmt->fetch()['total'];

    // 3. Alumnos inscritos en el ciclo ACTIVO (Regulares)
    $inscritos_ciclo = 0;
    $total_cursos = 0;
    $top_cursos = [];
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

// --- Recaudación mensual (gráfico + tarjeta del mes) ---
$stmt_chart = $pdo->query(
    "SELECT MONTH(fecha) as mes, SUM(monto) as total
     FROM pagos
     WHERE YEAR(fecha) = YEAR(CURDATE())
     GROUP BY MONTH(fecha)
     ORDER BY mes ASC"
);
$pagos_por_mes = $stmt_chart->fetchAll(PDO::FETCH_KEY_PAIR); // mes => total

$stmt_vest = $pdo->query(
    "SELECT MONTH(fecha) as mes, SUM(total) as total
     FROM vestimenta_ventas
     WHERE YEAR(fecha) = YEAR(CURDATE())
     GROUP BY MONTH(fecha)
     ORDER BY mes ASC"
);
$vestimenta_por_mes = $stmt_vest->fetchAll(PDO::FETCH_KEY_PAIR);

$meses_es = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$meses_largos = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$dias_es = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$labels = json_encode($meses_es);
$data_pagos = json_encode(array_map(
    fn($m) => round((float) ($pagos_por_mes[$m] ?? 0), 2),
    range(1, 12)
));
$data_vestimenta = json_encode(array_map(
    fn($m) => round((float) ($vestimenta_por_mes[$m] ?? 0), 2),
    range(1, 12)
));

$mes_actual = (int) date('n');
$recaudado_mes = (float) ($pagos_por_mes[$mes_actual] ?? 0) + (float) ($vestimenta_por_mes[$mes_actual] ?? 0);
$fecha_larga = $dias_es[(int) date('w')] . ' ' . date('j') . ' de ' . $meses_largos[$mes_actual - 1];
$max_top = 0;
foreach ($top_cursos as $tc) {
    $max_top = max($max_top, (int) $tc['cantidad']);
}
$nombre_corto = explode(' ', trim($_SESSION['nombre'] ?? ''))[0] ?? '';

// --- INCLUIR CABECERA MAESTRA ---
// Definimos la ruta base como './' porque estamos en la raíz
$base_path = './';
include 'includes/header.php';
?>

<div class="container">

    <!-- Encabezado -->
    <div class="page-head">
        <div>
            <div class="page-eyebrow">Ciclo lectivo <?= htmlspecialchars((string) $anio_actual) ?></div>
            <h1 class="page-title">Panel de control</h1>
            <p class="page-sub">
                <?= $nombre_corto !== '' ? 'Hola, ' . htmlspecialchars($nombre_corto) . ' · ' : '' ?>hoy es <?= $fecha_larga ?>.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="modules/alumnos/alta.php" class="btn btn-outline-secondary"><i class="bi bi-person-plus"></i> Nuevo alumno</a>
            <a href="modules/alumnos/index.php" class="btn btn-primary"><i class="bi bi-search"></i> Buscar alumno</a>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card stat-card">
                <div class="stat-top">
                    <span class="stat-label">Inscriptos <?= htmlspecialchars((string) $anio_actual) ?></span>
                    <span class="stat-icon is-success"><i class="bi bi-person-check"></i></span>
                </div>
                <div class="stat-value"><?= number_format((int) $inscritos_ciclo, 0, ',', '.') ?></div>
                <div class="stat-foot">Alumnos regulares del ciclo activo</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card">
                <div class="stat-top">
                    <span class="stat-label">Cursos activos</span>
                    <span class="stat-icon"><i class="bi bi-grid-3x3-gap"></i></span>
                </div>
                <div class="stat-value"><?= number_format((int) $total_cursos, 0, ',', '.') ?></div>
                <div class="stat-foot">Con al menos un alumno regular</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card">
                <div class="stat-top">
                    <span class="stat-label">Recaudado en <?= $meses_largos[$mes_actual - 1] ?></span>
                    <span class="stat-icon is-sol"><i class="bi bi-cash-stack"></i></span>
                </div>
                <div class="stat-value">$ <?= number_format($recaudado_mes, 0, ',', '.') ?></div>
                <div class="stat-foot">Cuotas y vestimenta</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card">
                <div class="stat-top">
                    <span class="stat-label">Total histórico</span>
                    <span class="stat-icon"><i class="bi bi-people"></i></span>
                </div>
                <div class="stat-value"><?= number_format((int) $total_alumnos, 0, ',', '.') ?></div>
                <div class="stat-foot">Alumnos registrados en el sistema</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Accesos rápidos -->
        <div class="col-lg-8">
            <h2 class="section-title">Accesos rápidos</h2>
            <div class="row g-3">
                <?php
                $accesos = [
                    ['modules/alumnos/index.php',    'bi-backpack4',        'Alumnos',    'Fichas, inscripciones y pagos'],
                    ['modules/cursos/index.php',     'bi-journal-bookmark', 'Cursos',     'Listas por año, división y turno'],
                    ['modules/pagos/index.php',      'bi-receipt',          'Pagos',      'Recibos y reportes de cobro'],
                    ['modules/tutores/index.php',    'bi-person-vcard',     'Tutores',    'Contactos y alumnos a cargo'],
                    ['modules/vestimenta/index.php', 'bi-tag',              'Vestimenta', 'Stock y ventas de uniformes'],
                    ['modules/vestimenta/nueva_venta.php', 'bi-bag-plus',   'Nueva venta', 'Vender prendas del uniforme'],
                ];
                foreach ($accesos as [$href, $icono, $titulo, $desc]): ?>
                    <div class="col-sm-6 col-md-4">
                        <a href="<?= $href ?>" class="quick-link">
                            <span class="quick-link-icon"><i class="bi <?= $icono ?>"></i></span>
                            <span>
                                <span class="quick-link-title"><?= $titulo ?></span>
                                <span class="quick-link-desc"><?= $desc ?></span>
                            </span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (($_SESSION['rol'] ?? '') === 'Administrador'): ?>
                <h2 class="section-title mt-4">Administración</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <a href="modules/usuarios/index.php" class="quick-link is-admin">
                            <span class="quick-link-icon"><i class="bi bi-shield-lock"></i></span>
                            <span>
                                <span class="quick-link-title">Usuarios del sistema</span>
                                <span class="quick-link-desc">Crear o editar accesos</span>
                            </span>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="modules/ciclos/index.php" class="quick-link is-admin">
                            <span class="quick-link-icon"><i class="bi bi-calendar-range"></i></span>
                            <span>
                                <span class="quick-link-title">Ciclos lectivos</span>
                                <span class="quick-link-desc">Abrir o cerrar años</span>
                            </span>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cursos más poblados -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Cursos más poblados</span>
                    <a href="modules/cursos/index.php" class="small fw-semibold text-decoration-none">Ver todos</a>
                </div>
                <?php if (count($top_cursos) > 0): ?>
                    <ul class="rank-list">
                        <?php foreach ($top_cursos as $top):
                            $pct = $max_top > 0 ? round(((int) $top['cantidad'] / $max_top) * 100) : 0; ?>
                            <li class="rank-item">
                                <div class="rank-row">
                                    <span>
                                        <span class="rank-name"><?= htmlspecialchars($top['anio_curso']) ?> «<?= htmlspecialchars($top['division']) ?>»</span>
                                        <span class="rank-meta">· Turno <?= htmlspecialchars($top['turno']) ?></span>
                                    </span>
                                    <span class="rank-count"><?= (int) $top['cantidad'] ?></span>
                                </div>
                                <div class="rank-bar" aria-hidden="true"><span style="width: <?= $pct ?>%"></span></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        Sin inscripciones activas
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recaudación mensual -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Recaudación mensual <?= date('Y') ?></span>
            <a href="modules/pagos/index.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-cash-stack"></i> Ver reporte completo
            </a>
        </div>
        <div class="card-body">
            <canvas id="chartPagosMensuales" height="90" aria-label="Gráfico de recaudación mensual por cuotas y vestimenta" role="img"></canvas>
        </div>
    </div>
</div>

<script src="assets/js/chart.umd.min.js"></script>
<script>
    (function () {
        const css = getComputedStyle(document.documentElement);
        const tok = name => css.getPropertyValue(name).trim();
        Chart.defaults.font.family = tok('--font-sans');
        Chart.defaults.color = tok('--ink-muted');
        Chart.defaults.borderColor = tok('--line');

        const ctx = document.getElementById('chartPagosMensuales');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= $labels ?>,
                datasets: [
                    {
                        label: 'Cuotas / Pagos',
                        data: <?= $data_pagos ?>,
                        backgroundColor: tok('--brand'),
                        borderRadius: 4,
                        maxBarThickness: 28,
                    },
                    {
                        label: 'Vestimenta',
                        data: <?= $data_vestimenta ?>,
                        backgroundColor: tok('--celeste'),
                        borderRadius: 4,
                        maxBarThickness: 28,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true, position: 'top', align: 'end', labels: { boxWidth: 12, boxHeight: 12, useBorderRadius: true, borderRadius: 3 } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.dataset.label + ': $ ' + ctx.parsed.y.toLocaleString('es-AR', { minimumFractionDigits: 2 })
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
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
