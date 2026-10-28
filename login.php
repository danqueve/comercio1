<?php
// Configurar cookie de sesión segura antes de iniciarla
$_is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $_is_https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
require_once 'config/conexion.php';
require_once 'config/logger.php';

$error = '';

// 1. Si el usuario ya está logueado, lo mandamos directo al panel
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// --- Anti-fuerza bruta: conteo de intentos fallidos en sesión por IP ---
// Límite: 5 intentos → bloqueo 15 minutos
const LOGIN_MAX_INTENTOS  = 5;
const LOGIN_BLOQUEO_SEG   = 900; // 15 minutos

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$clave_intentos = 'login_intentos_' . md5($ip);
$clave_tiempo   = 'login_bloqueo_hasta_' . md5($ip);

// ¿Está bloqueado?
$bloqueado_hasta = $_SESSION[$clave_tiempo] ?? 0;
if ($bloqueado_hasta > time()) {
    $minutos_restantes = ceil(($bloqueado_hasta - time()) / 60);
    $error = "Demasiados intentos fallidos. Por favor espere $minutos_restantes minuto(s) antes de intentar nuevamente.";
}

// 2. Procesar el formulario al enviar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $usuario = trim($_POST['usuario']);
    $password = $_POST['password'];

    if (empty($usuario) || empty($password)) {
        $error = "Por favor, complete usuario y contraseña.";
    } else {
        try {
            // Buscamos el usuario en la BD (que esté activo)
            $stmt = $pdo->prepare("SELECT u.id, u.usuario, u.password, u.nombre_completo, r.nombre as rol
                                   FROM usuarios u
                                   JOIN roles r ON u.id_rol = r.id
                                   WHERE u.usuario = :usuario AND u.activo = 1");
            $stmt->execute(['usuario' => $usuario]);
            $user = $stmt->fetch();

            // Verificamos contraseña
            if ($user && password_verify($password, $user['password'])) {
                // Prevenir session fixation: regenerar ID ANTES de escribir datos sensibles
                session_regenerate_id(true);

                // Resetear contador de intentos fallidos
                unset($_SESSION[$clave_intentos], $_SESSION[$clave_tiempo]);

                // Login Exitoso: Guardamos datos en sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nombre']  = $user['nombre_completo'];
                $_SESSION['usuario'] = $user['usuario'];
                $_SESSION['rol']     = $user['rol'];

                // Auditar acceso exitoso
                audit_log($pdo, 'LOGIN_OK', "Usuario: {$user['usuario']}");

                // Redirigir al Dashboard
                header('Location: index.php');
                exit;
            } else {
                // Incrementar contador de intentos fallidos
                $_SESSION[$clave_intentos] = ($_SESSION[$clave_intentos] ?? 0) + 1;

                if ($_SESSION[$clave_intentos] >= LOGIN_MAX_INTENTOS) {
                    $_SESSION[$clave_tiempo] = time() + LOGIN_BLOQUEO_SEG;
                    unset($_SESSION[$clave_intentos]);
                    $minutos = LOGIN_BLOQUEO_SEG / 60;
                    $error = "Demasiados intentos fallidos. Cuenta bloqueada por $minutos minutos.";
                } else {
                    $restantes = LOGIN_MAX_INTENTOS - $_SESSION[$clave_intentos];
                    $error = "Usuario o contraseña incorrectos. Intentos restantes: $restantes.";
                }

                $usuario_intento = htmlspecialchars(trim($_POST['usuario'] ?? ''));
                audit_log($pdo, 'LOGIN_FAIL', "Intento fallido con usuario: $usuario_intento — IP: $ip");
            }
        } catch (PDOException $e) {
            log_error('LOGIN', $e->getMessage());
            $error = "Error interno del sistema. Por favor, intente más tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#142a45">
    <title>Acceso · Escuela de Comercio N°1</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/tokens.css" rel="stylesheet">
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

    <div class="login-page">

        <!-- Panel institucional -->
        <aside class="login-aside">
            <div>
                <a class="app-brand" href="login.php">
                    <span class="app-brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                    <span>
                        <span class="app-brand-name">Comercio N°1</span>
                        <span class="app-brand-sub">Sistema de Gestión Escolar</span>
                    </span>
                </a>
                <p class="login-school">Escuela de Comercio N°1<br>«Gral. Manuel Belgrano»</p>
                <p class="login-tagline">Alumnos, cursos, pagos y vestimenta del ciclo lectivo en un solo lugar.</p>
            </div>
            <div>
                <div class="login-flag mb-3" aria-hidden="true"><span></span><span></span><span></span></div>
                <div class="login-aside-foot">&copy; <?= date('Y') ?> Escuela de Comercio N°1</div>
            </div>
        </aside>

        <!-- Formulario -->
        <main class="login-main">
            <div class="login-card">
                <h1>Iniciar sesión</h1>
                <p class="text-muted mb-4">Ingresá con tu usuario del sistema.</p>

                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
                        <div><?= $error ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <div class="mb-3">
                        <label class="form-label" for="usuario">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
                            <input type="text" class="form-control form-control-lg" id="usuario" name="usuario" required autofocus autocomplete="username" placeholder="Tu usuario">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                            <input type="password" class="form-control form-control-lg" id="password" name="password" required autocomplete="current-password" placeholder="Tu contraseña">
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            Ingresar <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>
                    </div>

                </form>

                <p class="small text-muted mt-4 mb-0">
                    <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
                    Por seguridad, el acceso se bloquea 15 minutos tras 5 intentos fallidos.
                </p>
            </div>
        </main>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
