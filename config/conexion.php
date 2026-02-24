<?php
/**
 * Archivo de conexión a la Base de Datos usando PDO.
 * Proyecto: Sistema Escuela de Comercio N° 1
 */

// Configuración de credenciales
// NOTA: En un servidor real, estos datos deberían estar en variables de entorno (.env)
// Si usas XAMPP, el usuario suele ser 'root' y la contraseña vacía ''.
$host     = 'localhost';
$dbname   = 'c2721666_comer1';
$username = 'c2721666_comer1'; 
$password = 'waGU39zufa'; 
$charset  = 'utf8mb4'; // Crucial para ñ, acentos y caracteres especiales

// Data Source Name (DSN)
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// Opciones de PDO para mayor seguridad y facilidad de uso
$options = [
    // Lanza excepciones en caso de error (útil para try-catch)
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Devuelve los resultados como arrays asociativos (clave => valor)
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Desactiva la emulación de sentencias preparadas (mayor seguridad real)
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Intentamos crear la conexión
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Si llegamos aquí, la conexión fue exitosa. 
    // Comenta la línea de abajo en producción para no ensuciar la pantalla.
    // echo "Conexión exitosa a la base de datos."; 

} catch (PDOException $e) {
    // Si hay error, lo capturamos y detenemos todo para no exponer datos sensibles
    // En producción, podrías guardar $e->getMessage() en un archivo de log
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>