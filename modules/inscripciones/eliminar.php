<?php
session_start();
require_once '../../config/conexion.php';

// Verificación de seguridad
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_inscripcion = $_POST['id_inscripcion'] ?? null;
    $id_curso       = $_POST['id_curso'] ?? null; // Para saber a dónde volver después de borrar

    if ($id_inscripcion) {
        try {
            // Eliminar solo la inscripción (el alumno sigue existiendo en el sistema)
            $stmt = $pdo->prepare("DELETE FROM inscripciones WHERE id = :id");
            $stmt->execute(['id' => $id_inscripcion]);
            
            // Redirigir al curso con mensaje de éxito
            if($id_curso) {
                header("Location: ../cursos/ver_curso.php?id=$id_curso&msg=deleted");
            } else {
                header("Location: ../../index.php");
            }
            exit;

        } catch (PDOException $e) {
            die("Error al eliminar inscripción: " . $e->getMessage());
        }
    }
}

// Si llegan aquí por error (ej: escribiendo la URL directamente), volver al inicio
header('Location: ../../index.php');
exit;
?>