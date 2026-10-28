<?php
// Asegurarnos de que $base_path esté definido para evitar errores en las rutas
if (!isset($base_path)) {
    $base_path = './';
}

// --- Sección activa del menú (según el script que se está ejecutando) ---
$_script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$_seccion_activa = 'inicio';
if (preg_match('#/modules/([a-z_]+)/#', $_script, $_m)) {
    $_seccion_activa = $_m[1];
}

// Menú principal: clave del módulo => [etiqueta, ícono, ruta]
$_menu_principal = [
    'inicio'     => ['Inicio',     'bi-house-door',   'index.php'],
    'alumnos'    => ['Alumnos',    'bi-people',       'modules/alumnos/index.php'],
    'tutores'    => ['Tutores',    'bi-person-vcard', 'modules/tutores/index.php'],
    'cursos'     => ['Cursos',     'bi-journal-bookmark', 'modules/cursos/index.php'],
    'pagos'      => ['Pagos',      'bi-cash-stack',   'modules/pagos/index.php'],
    'vestimenta' => ['Vestimenta', 'bi-tag',          'modules/vestimenta/index.php'],
];
$_menu_admin = [
    'usuarios' => ['Usuarios',        'bi-shield-lock',    'modules/usuarios/index.php'],
    'ciclos'   => ['Ciclos lectivos', 'bi-calendar-range', 'modules/ciclos/index.php'],
];
// Inscripciones se gestiona desde Alumnos: mantener resaltado "Alumnos"
if ($_seccion_activa === 'inscripciones') {
    $_seccion_activa = 'alumnos';
}

$_nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$_rol_usuario    = $_SESSION['rol'] ?? '';
$_iniciales = '';
foreach (preg_split('/\s+/', trim($_nombre_usuario)) as $_parte) {
    if ($_parte !== '' && mb_strlen($_iniciales) < 2) {
        $_iniciales .= mb_strtoupper(mb_substr($_parte, 0, 1));
    }
}

if (!function_exists('render_nav_link')) {
    function render_nav_link(string $clave, array $item, string $activa, string $base): void
    {
        [$etiqueta, $icono, $ruta] = $item;
        $es_activa = $clave === $activa;
        printf(
            '<a class="app-nav-link%s" href="%s"%s><i class="bi %s" aria-hidden="true"></i><span>%s</span></a>',
            $es_activa ? ' active' : '',
            htmlspecialchars($base . $ruta),
            $es_activa ? ' aria-current="page"' : '',
            $icono,
            htmlspecialchars($etiqueta)
        );
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#142a45">
    <title>Sistema de Gestión Escolar · Comercio N°1</title>

    <!-- Bootstrap 5 + íconos + fuentes: todo local -->
    <link href="<?= $base_path ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $base_path ?>assets/icons/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Design system: tokens (generado desde tokens.json) + tema -->
    <link href="<?= $base_path ?>assets/css/tokens.css" rel="stylesheet">
    <link href="<?= $base_path ?>assets/css/app.css" rel="stylesheet">
</head>

<body>
    <a class="visually-hidden-focusable btn btn-primary position-absolute m-2" style="z-index:2000" href="#contenido">Saltar al contenido</a>

    <!-- Barra superior: solo en pantallas chicas -->
    <header class="app-topbar">
        <button class="app-menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
            aria-controls="appSidebar" aria-label="Abrir menú">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <a class="app-brand" href="<?= $base_path ?>index.php">
            <span class="app-brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
            <span><span class="app-brand-name">Comercio N°1</span></span>
        </a>
    </header>

    <div class="app-shell">

        <!-- Barra lateral (offcanvas en móvil, fija en escritorio) -->
        <aside class="app-sidebar offcanvas offcanvas-start" tabindex="-1" id="appSidebar" aria-label="Menú principal">
            <div class="app-sidebar-head d-flex align-items-start">
                <a class="app-brand flex-grow-1" href="<?= $base_path ?>index.php">
                    <span class="app-brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                    <span>
                        <span class="app-brand-name">Comercio N°1</span>
                        <span class="app-brand-sub">Gral. Manuel Belgrano</span>
                    </span>
                </a>
                <button type="button" class="btn-close btn-close-white m-3" data-bs-dismiss="offcanvas" aria-label="Cerrar menú"></button>
            </div>

            <nav class="app-nav">
                <div class="app-nav-label">Gestión</div>
                <?php foreach ($_menu_principal as $_clave => $_item) {
                    render_nav_link($_clave, $_item, $_seccion_activa, $base_path);
                } ?>

                <?php if ($_rol_usuario === 'Administrador'): ?>
                    <div class="app-nav-label mt-3">Administración</div>
                    <?php foreach ($_menu_admin as $_clave => $_item) {
                        render_nav_link($_clave, $_item, $_seccion_activa, $base_path);
                    } ?>
                <?php endif; ?>
            </nav>

            <div class="app-user dropup">
                <button class="app-user-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="app-avatar" aria-hidden="true"><?= htmlspecialchars($_iniciales ?: 'U') ?></span>
                    <span class="flex-grow-1 overflow-hidden">
                        <span class="app-user-name"><?= htmlspecialchars($_nombre_usuario) ?></span>
                        <?php if ($_rol_usuario !== ''): ?>
                            <span class="app-user-role"><?= htmlspecialchars($_rol_usuario) ?></span>
                        <?php endif; ?>
                    </span>
                    <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu w-100">
                    <li><a class="dropdown-item" href="<?= $base_path ?>modules/usuarios/perfil.php"><i class="bi bi-gear me-2 text-muted"></i>Mi perfil</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= $base_path ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
                </ul>
            </div>
        </aside>

        <!-- Contenido principal (se cierra en footer.php) -->
        <main class="app-main" id="contenido">
