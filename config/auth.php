<?php
/**
 * Autenticación y Autorización Centralizada
 *
 * Incluir al inicio de cada módulo en lugar de repetir el boilerplate de sesión.
 *
 * Uso básico:
 *   require_once '../../config/auth.php';
 *   require_login();                         // Solo requiere estar logueado
 *   require_role(['Administrador']);          // Requiere rol específico
 */

// Configurar opciones seguras de cookie ANTES de iniciar la sesión.
// Solo aplica si la sesión aún no fue iniciada (evita warnings en archivos
// que ya llaman session_start() por su cuenta).
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

    session_set_cookie_params([
        'lifetime' => 0,           // Sesión de navegador (expira al cerrar)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,   // Solo enviar por HTTPS cuando esté disponible
        'httponly' => true,        // Inaccesible desde JavaScript
        'samesite' => 'Lax',       // Protección CSRF adicional a nivel de cookie
    ]);

    session_start();
}

/**
 * Verifica que haya un usuario autenticado.
 * Si no lo hay, redirige al login.
 */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        // Determinar la ruta al login relativa al archivo que llama esta función
        // Usamos una ruta absoluta desde la raíz del servidor para mayor robustez
        header('Location: /comercio/login.php');
        exit;
    }
}

/**
 * Verifica que el usuario tenga uno de los roles permitidos.
 * Llama internamente a require_login(), así que no es necesario llamar ambas.
 *
 * @param string[] $roles  Ej: ['Administrador'], ['Administrador', 'Supervisor']
 */
function require_role(array $roles): void
{
    require_login();

    if (!in_array($_SESSION['rol'] ?? '', $roles, true)) {
        http_response_code(403);
        // Redirigir al inicio con un error discreto en lugar de mostrar mensaje técnico
        header('Location: /comercio/index.php?error=acceso_denegado');
        exit;
    }
}
