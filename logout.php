<?php
session_start();
session_destroy(); // Destruye toda la información registrada de una sesión
header('Location: login.php');
exit;
?>