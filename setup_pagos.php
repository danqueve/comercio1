<?php
require_once 'config/conexion.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS pagos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_alumno INT NOT NULL,
        id_ciclo_lectivo INT NOT NULL,
        monto DECIMAL(10,2) NOT NULL,
        concepto VARCHAR(255) NOT NULL, -- Ej: 'Cooperadora 2025'
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        usuario_responsable VARCHAR(100), -- Quién cobró
        FOREIGN KEY (id_alumno) REFERENCES alumnos(id)
    ) ENGINE=InnoDB;";

    $pdo->exec($sql);
    echo "<h1>¡Tabla 'pagos' creada correctamente!</h1>";
    echo "<p>Ya puedes borrar este archivo y empezar a registrar cobros.</p>";
    echo "<a href='index.php'>Ir al inicio</a>";

} catch (PDOException $e) {
    die("Error al crear tabla: " . $e->getMessage());
}
?>