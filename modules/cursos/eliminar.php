<?php
session_start();
require_once '../../config/conexion.php';

// 1. Verificar seguridad: El usuario debe estar logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// 2. Verificar que recibimos un ID válido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?mensaje=error_id');
    exit;
}

$id_curso = (int)$_GET['id'];

try {
    // 3. Validación de Integridad: Verificar si hay alumnos inscriptos
    // Antes de borrar, contamos cuántas inscripciones tiene este curso
    $sql_check = "SELECT COUNT(*) FROM inscripciones WHERE id_curso = :id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute(['id' => $id_curso]);
    $cantidad_inscriptos = $stmt_check->fetchColumn();

    if ($cantidad_inscriptos > 0) {
        // Si hay alumnos inscriptos, NO eliminamos y redirigimos con un error
        header('Location: index.php?mensaje=error_dependencia&cant=' . $cantidad_inscriptos);
        exit;
    }

    // 4. Si no hay alumnos, procedemos a eliminar
    $sql_delete = "DELETE FROM cursos WHERE id = :id";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute(['id' => $id_curso]);

    // Verificar si se eliminó alguna fila
    if ($stmt_delete->rowCount() > 0) {
        header('Location: index.php?mensaje=eliminado_ok');
    } else {
        header('Location: index.php?mensaje=error_no_encontrado');
    }

} catch (PDOException $e) {
    // Capturar errores de base de datos (por ejemplo, otras claves foráneas)
    // Se recomienda registrar el error en un log en lugar de mostrarlo al usuario
    // error_log($e->getMessage()); 
    header('Location: index.php?mensaje=error_bd');
}
exit;
?>