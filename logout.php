<?php
session_start();

// F14-FIX: Instruir al navegador a eliminar la cookie de sesión inmediatamente
// (no solo destruir los datos en el servidor)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
header('Location: login.php');
exit;
