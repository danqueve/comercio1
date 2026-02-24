<?php
/**
 * Conexión a la Base de Datos usando PDO.
 * Las credenciales se leen desde el archivo .env (nunca hardcodeadas aquí).
 */

require_once __DIR__ . '/logger.php';

// --- Leer .env manualmente (sin dependencias externas) ---
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Ignorar comentarios
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// --- Credenciales desde variables de entorno ---
$host    = $_ENV['DB_HOST']    ?? 'localhost';
$dbname  = $_ENV['DB_NAME']    ?? '';
$username = $_ENV['DB_USER']   ?? '';
$password = $_ENV['DB_PASS']   ?? '';
$charset  = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

// Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// Opciones de PDO para mayor seguridad y facilidad de uso
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Loguear el error real internamente
    log_error('CONEXION_BD', $e->getMessage());
    // Mostrar mensaje genérico al usuario
    die("Error: No se pudo conectar a la base de datos. Contacte al administrador del sistema.");
}
?>