<?php
// Este archivo es solo para probar que todo funciona. 
// Guárdalo en la raíz (sistema_escuela/test_conexion.php) y ejecútalo en el navegador.

require_once 'config/conexion.php';

echo "<h1>Prueba de Sistema</h1>";

if (isset($pdo)) {
    echo "<p style='color:green; font-weight:bold;'>✅ Conexión a la base de datos exitosa.</p>";
    
    // Vamos a consultar los roles para ver si lee la base de datos
    $stmt = $pdo->query("SELECT * FROM roles");
    $roles = $stmt->fetchAll();

    echo "<h3>Roles disponibles en el sistema:</h3>";
    echo "<ul>";
    foreach ($roles as $rol) {
        echo "<li>ID: " . $rol['id'] . " - Rol: " . $rol['nombre'] . "</li>";
    }
    echo "</ul>";

} else {
    echo "<p style='color:red;'>❌ No se pudo establecer la conexión.</p>";
}
?>