<?php
/**
 * CSRF Protection Helper
 * Genera y verifica tokens CSRF para formularios sensibles.
 */

/**
 * Genera (o reutiliza) un token CSRF en la sesión.
 * Úsalo en el form: <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica el token CSRF enviado en el POST.
 * Si no es válido, termina la ejecución con error 403.
 */
function csrf_verify(): void
{
    $token_post    = $_POST['csrf_token']   ?? '';
    $token_session = $_SESSION['csrf_token'] ?? '';

    if (empty($token_post) || empty($token_session) || !hash_equals($token_session, $token_post)) {
        http_response_code(403);
        die('Error de seguridad: token CSRF inválido. Por favor, recargue la página e intente de nuevo.');
    }
}
