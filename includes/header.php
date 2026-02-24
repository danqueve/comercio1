<?php
// Asegurarnos de que $base_path esté definido para evitar errores en las rutas
if (!isset($base_path)) {
    $base_path = './';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión Escolar</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Estilos Personalizados Mejorados -->
    <style>
        body {
            background-color: #f0f2f5; /* Gris muy suave, más moderno */
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Navbar con degradado elegante */
        .navbar-custom {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); /* Azul profundo profesional */
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }

        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
            font-size: 1.4rem;
        }

        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 0.3rem;
            transition: all 0.2s ease;
        }

        /* Efecto Hover en enlaces */
        .navbar-dark .navbar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: #fff;
            transform: translateY(-1px);
        }

        /* Enlace activo */
        .navbar-dark .navbar-nav .nav-link.active {
            background-color: rgba(255, 255, 255, 0.25);
            color: #fff;
            font-weight: 600;
        }

        .card {
            border-radius: 0.8rem;
            border: none;
        }
    </style>
</head>
<body>

<!-- Barra de Navegación Personalizada -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 sticky-top">
    <div class="container">
        <!-- Logo / Marca -->
        <a class="navbar-brand" href="<?= $base_path ?>index.php">
            <i class="bi bi-mortarboard-fill me-2"></i>Gestión Escolar
        </a>
        
        <!-- Botón Hamburguesa para Móvil -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Contenido del Menú -->
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                
                <!-- Inicio -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_path ?>index.php">Inicio</a>
                </li>

                <!-- Módulo Alumnos -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_path ?>modules/alumnos/index.php">Alumnos</a>
                </li>
                
                <!-- NUEVO: Módulo Tutores -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_path ?>modules/tutores/index.php">Tutores</a>
                </li>

                <!-- Módulo Cursos -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_path ?>modules/cursos/index.php">Cursos</a>
                </li>

                <!-- Módulo Inscripciones (ELIMINADO) -->
                
                <!-- Módulo Pagos -->
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_path ?>modules/pagos/index.php">Pagos</a>
                </li>

            </ul>

            <!-- Menú de Usuario a la derecha -->
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <span><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item py-2" href="<?= $base_path ?>modules/usuarios/perfil.php"><i class="bi bi-gear me-2 text-muted"></i> Mi Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger py-2" href="<?= $base_path ?>logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Inicio del contenido principal -->
<!-- (El cierre </body> y </html> va en el footer.php) -->