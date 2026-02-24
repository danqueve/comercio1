<?php
/**
 * Logger interno — Guarda errores en un archivo de log.
 * Nunca expone mensajes técnicos al usuario final.
 */

function log_error(string $context, string $message): void
{
    $log_dir  = __DIR__ . '/../logs';
    $log_file = $log_dir . '/errores.log';

    // Crear la carpeta /logs si no existe
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0750, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $user_id   = $_SESSION['user_id'] ?? 'no-auth';
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $linea     = "[$timestamp] [USER:$user_id] [IP:$ip] [$context] $message" . PHP_EOL;

    // Append al archivo de log
    file_put_contents($log_file, $linea, FILE_APPEND | LOCK_EX);
}

/**
 * Auditoría — Registra acciones de usuario en la tabla logs_actividad.
 * Requiere que $pdo esté disponible globalmente.
 *
 * @param PDO    $pdo     Instancia de la conexión PDO
 * @param string $accion  Ej: 'ALTA_ALUMNO', 'ELIMINAR_PAGO'
 * @param string $detalle Descripción legible del evento
 */
function audit_log(PDO $pdo, string $accion, string $detalle = ''): void
{
    try {
        $id_usuario     = $_SESSION['user_id'] ?? 0;
        $nombre_usuario = $_SESSION['nombre']  ?? 'Desconocido';
        $ip             = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $stmt = $pdo->prepare(
            "INSERT INTO logs_actividad (id_usuario, nombre_usuario, accion, detalle, ip)
             VALUES (:uid, :nom, :accion, :detalle, :ip)"
        );
        $stmt->execute([
            'uid'     => $id_usuario,
            'nom'     => $nombre_usuario,
            'accion'  => $accion,
            'detalle' => $detalle,
            'ip'      => $ip,
        ]);
    } catch (Throwable $e) {
        // Si la tabla no existe aún, loguea en archivo en lugar de romper la app
        log_error('AUDIT_LOG_FAIL', $e->getMessage());
    }
}
