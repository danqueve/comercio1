# Sistema de Gestión — Escuela de Comercio N°1 "Gral. Manuel Belgrano"

Sistema web de administración escolar desarrollado en PHP + MySQL, diseñado para gestionar alumnos, inscripciones, pagos, cursos y vestimenta de la institución.

---

## Tecnologías

| Capa | Tecnología |
|------|-----------|
| Servidor | Apache 2.4 (WAMP) |
| Backend | PHP 8.3 |
| Base de datos | MySQL (PDO) |
| Frontend | Bootstrap 5 + Bootstrap Icons + tema propio (`assets/css/app.css`) |
| Gráficos | Chart.js |
| PDF | FPDF |

Todos los assets de frontend (Bootstrap, Bootstrap Icons, Chart.js y las fuentes) se sirven de forma **local** desde `assets/` — sin dependencias de CDN externas.

---

## Módulos

| Módulo | Ruta | Descripción |
|--------|------|-------------|
| Dashboard | `/index.php` | Estadísticas del ciclo lectivo activo con gráficos |
| Alumnos | `/modules/alumnos/` | Alta, edición, búsqueda, exportación CSV/PDF y eliminación |
| Tutores | `/modules/tutores/` | Gestión de tutores y asociación con alumnos |
| Cursos | `/modules/cursos/` | Creación y gestión de cursos por año/división/turno |
| Inscripciones | `/modules/inscripciones/` | Inscripción de alumnos a cursos, cambio de estado |
| Pagos | `/modules/pagos/` | Registro de pagos, impresión de recibos (A4 y matricial) |
| Vestimenta | `/modules/vestimenta/` | Inventario de productos, registro de ventas, balance |
| Ciclos Lectivos | `/modules/ciclos/` | Gestión del ciclo lectivo activo |
| Usuarios | `/modules/usuarios/` | Gestión de usuarios del sistema y log de actividad |

---

## Diseño de la interfaz

La interfaz usa un design system propio, «Comercio N°1», construido sobre Bootstrap 5.1:

| Archivo | Qué contiene |
|---------|--------------|
| `assets/css/tokens.css` | Colores, tipografías, espacios, radios y sombras como variables CSS (`--brand`, `--surface`, `--ink`…). Generado desde el `tokens.json` del design system: no editar a mano. |
| `assets/css/app.css` | Tema: mapea Bootstrap a los tokens (botones, tablas, badges, alertas, formularios) y define el layout (barra lateral, panel, login). |
| `assets/fonts/` | Public Sans (interfaz) y Source Serif 4 (títulos), variables, licencia OFL. |
| `assets/icons/` | Bootstrap Icons 1.11.3 local. |

Reglas rápidas:

- Paleta institucional: azul Belgrano (`--brand`), celeste de la bandera (`--celeste`) y Sol de Mayo (`--sol`) como acento.
- Los estados (Regular, pagado, sin tutor, error) siempre llevan palabra o ícono, nunca solo color.
- Las páginas nuevas incluyen `includes/header.php` y `includes/footer.php` y envuelven su contenido en `<div class="container">`; el menú lateral marca solo la sección activa.
- Clases propias disponibles: `page-head`, `page-title`, `stat-card`, `quick-link`, `section-title`, `empty-state`, `total-card`.

---

## Instalación local (WAMP)

### Requisitos

- WAMP con Apache 2.4, PHP 8.3, MySQL 5.7+
- `mod_rewrite` y `mod_headers` habilitados en Apache

### Pasos

1. Clonar el repositorio en `C:\wamp64\www\comercio`

2. Crear la base de datos e importar el schema:
   ```sql
   CREATE DATABASE comercio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   Luego importar el archivo SQL del schema (solicitarlo al administrador del proyecto).

3. Crear el archivo de configuración `.env` en la raíz del proyecto:
   ```env
   DB_HOST=localhost
   DB_NAME=comercio
   DB_USER=root
   DB_PASS=
   DB_CHARSET=utf8mb4
   ```

4. Verificar que `AllowOverride All` esté habilitado en `httpd.conf` para el directorio `www`:
   ```apache
   <Directory "c:/wamp64/www/">
       AllowOverride All
   </Directory>
   ```

5. Acceder a `http://localhost/comercio`

---

## Estructura de directorios

```
comercio/
├── assets/               # CSS (Bootstrap + tokens + tema), JS, fuentes e íconos locales
├── config/               # Conexión DB, autenticación, CSRF, logger
├── fpdf/                 # Librería FPDF para generación de PDF
├── includes/             # Header y footer maestros (layout)
├── logs/                 # Logs de errores y actividad (no versionados)
├── modules/
│   ├── alumnos/
│   ├── ciclos/
│   ├── cursos/
│   ├── inscripciones/
│   ├── pagos/
│   ├── tutores/
│   ├── usuarios/
│   └── vestimenta/
├── .env                  # Credenciales locales (NO versionado)
├── .htaccess             # Seguridad y cabeceras HTTP
├── index.php             # Dashboard principal
├── login.php
└── logout.php
```

---

## Seguridad implementada

- Autenticación centralizada en `config/auth.php` con `require_login()` / `require_role()`
- Protección CSRF en todos los formularios de escritura
- Cookies de sesión con flags `HttpOnly`, `SameSite=Lax` y `Secure` (en HTTPS)
- `session_regenerate_id()` en login para prevenir session fixation
- Límite de intentos de login (5 intentos → bloqueo de 15 minutos)
- Todas las salidas a HTML escapadas con `htmlspecialchars()`
- Consultas SQL con PDO prepared statements (sin SQL injection)
- Acceso HTTP bloqueado a `config/`, `logs/` y `fpdf/tutorial/` vía `.htaccess`
- Cabeceras HTTP de seguridad: `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`
- Auditoría de acciones en tabla `logs_actividad`

---

## Configuración de producción

- Reemplazar el `.env` con las credenciales del servidor de producción
- Asegurarse de que el servidor sirva el sitio por HTTPS (las cookies `Secure` se activan automáticamente)
- Verificar permisos de escritura en `logs/`
- No exponer el directorio `config/` ni `.env` al público

---

## Autores

Desarrollado para la **Escuela de Comercio N°1 "Gral. Manuel Belgrano"**.
