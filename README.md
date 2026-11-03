# Sistema de Gestion Escolar — Escuela de Comercio N°1 "Gral. Manuel Belgrano"

Sistema web de administracion escolar desarrollado en **PHP 8.3 + MySQL**, disenado para gestionar el ciclo de vida completo de alumnos, inscripciones, pagos, cursos, tutores y vestimenta institucional.

---

## Tabla de Contenidos

- [Tecnologias](#tecnologias)
- [Arquitectura](#arquitectura)
- [Modulos del Sistema](#modulos-del-sistema)
- [Roles y Permisos](#roles-y-permisos)
- [Seguridad](#seguridad)
- [Estructura de Directorios](#estructura-de-directorios)
- [Instalacion Local (WAMP)](#instalacion-local-wamp)
- [Configuracion de Produccion](#configuracion-de-produccion)
- [Base de Datos](#base-de-datos)
- [Funcionalidades Destacadas](#funcionalidades-destacadas)

---

## Tecnologias

| Capa | Tecnologia | Version |
|------|-----------|---------|
| Servidor | Apache | 2.4+ (mod_rewrite, mod_headers) |
| Backend | PHP | 8.3 |
| Base de datos | MySQL (PDO) | 5.7+ / 8.0 |
| Frontend | Bootstrap + Bootstrap Icons | 5.3 / 1.11 |
| Graficos | Chart.js | 4.x |
| PDF | FPDF | 1.86 (vendored) |

Todos los assets de frontend (Bootstrap CSS/JS, Chart.js) se sirven de forma **local** desde `assets/` — sin dependencias de CDN externas, excepto Bootstrap Icons que se carga desde jsDelivr.

---

## Arquitectura

El sistema sigue una arquitectura **PHP procedural modular** sin framework:

```
[Navegador] → [Apache + .htaccess] → [PHP]
                                        ├── config/auth.php    → Autenticacion/Autorizacion
                                        ├── config/conexion.php → PDO (MySQL)
                                        ├── config/csrf.php    → Proteccion CSRF
                                        ├── config/logger.php  → Auditoria + Log de errores
                                        └── modules/           → Logica de negocio por modulo
```

**Patron de cada modulo:**

Cada modulo vive en `modules/<nombre>/` y contiene:
- `index.php` — Listado principal con busqueda, filtros y paginacion
- `crear.php` o `alta.php` — Formulario de alta con validacion server-side
- `editar.php` — Formulario de edicion con carga de datos existentes
- `eliminar.php` — Accion POST protegida con CSRF y validacion de rol
- `exportar.php` — Exportacion a CSV/PDF segun el modulo
- `imprimir_*.php` — Vistas de impresion optimizadas para A4 y matricial

**Flujo de una peticion tipica:**
1. Apache aplica `.htaccess` (headers de seguridad, bloqueo de archivos sensibles)
2. PHP carga `config/auth.php` → verifica sesion y rol
3. Se valida el token CSRF en operaciones POST
4. Se ejecuta la logica con PDO prepared statements
5. Se renderiza el HTML usando `includes/header.php` y `includes/footer.php`
6. Se registra la accion en `logs_actividad` (auditoria)

---

## Modulos del Sistema

### Dashboard (`/index.php`)
Panel principal con estadisticas en tiempo real del ciclo lectivo activo:
- Total de alumnos registrados (historico)
- Alumnos inscritos en el ciclo activo (estado Regular)
- Cursos con mas alumnos (Top 4)
- Graficos interactivos con Chart.js
- Cache de sesion de 5 minutos para optimizar consultas

### Alumnos (`/modules/alumnos/`)
| Archivo | Funcion |
|---------|---------|
| `index.php` | Listado con busqueda por DNI/nombre, filtro por curso/turno, paginacion |
| `alta.php` | Alta de alumno con datos personales + tutor opcional (autocompletado AJAX) |
| `editar.php` | Edicion completa de datos, localidad con selector |
| `eliminar.php` | Eliminacion protegida (solo Administrador/Supervisor) |
| `exportar.php` | Exportacion CSV/PDF de listados filtrados |
| `imprimir_lista_curso.php` | Lista imprimible por curso |
| `buscar_tutor.php` | Endpoint AJAX para autocompletar datos del tutor por DNI |

### Tutores (`/modules/tutores/`)
| Archivo | Funcion |
|---------|---------|
| `index.php` | Listado con busqueda y paginacion |
| `editar.php` | Edicion de datos + vista de alumnos vinculados |
| `eliminar.php` | Eliminacion protegida (solo Administrador/Supervisor) |
| `ver.php` | Ficha detallada del tutor con alumnos asociados |
| `exportar.php` | Exportacion CSV |
| `imprimir_lista.php` | Lista imprimible |

### Cursos (`/modules/cursos/`)
| Archivo | Funcion |
|---------|---------|
| `index.php` | Listado de cursos con cantidad de inscriptos por ciclo |
| `crear.php` | Crear curso (anio/division/turno) con validacion de duplicados |
| `editar.php` | Edicion de datos del curso |
| `eliminar.php` | Eliminacion protegida (solo Administrador/Supervisor) |
| `ver_curso.php` | Vista detallada con lista de alumnos inscriptos |
| `imprimir_lista.php` | Lista de alumnos por curso imprimible |

### Inscripciones (`/modules/inscripciones/`)
| Archivo | Funcion |
|---------|---------|
| `nueva.php` | Inscribir alumno a curso/ciclo lectivo |
| `editar.php` | Cambiar estado de inscripcion (Regular, Libre, etc.) |
| `eliminar.php` | Eliminacion protegida (solo Administrador/Supervisor) |
| `imprimir_ficha.php` | Ficha de inscripcion imprimible |

### Pagos (`/modules/pagos/`)
| Archivo | Funcion |
|---------|---------|
| `index.php` | Listado de pagos con filtros por fecha, alumno y concepto |
| `registrar.php` | Registro de nuevo pago con seleccion de alumno |
| `exito.php` | Confirmacion de pago exitoso |
| `imprimir.php` | Recibo de pago (formato A4) |
| `imprimir_reporte.php` | Reporte imprimible de recaudacion |
| `exportar.php` | Exportacion CSV de movimientos |

### Vestimenta (`/modules/vestimenta/`)
| Archivo | Funcion |
|---------|---------|
| `index.php` | Inventario de productos con stock deposito/administracion |
| `crear.php` | Agregar nuevo producto al inventario |
| `editar.php` | Editar producto (descripcion, talle, precio, stock) |
| `eliminar.php` | Eliminacion protegida (solo Administrador/Supervisor) |
| `nueva_venta.php` | Formulario de venta con seleccion de productos |
| `procesar_venta.php` | Procesamiento de venta con descuento de stock |
| `ver_ventas.php` | Historial de ventas con filtros |
| `detalle_venta.php` | Detalle de una venta individual |
| `eliminar_venta.php` | Anulacion de venta (solo Administrador) |
| `exportar_ventas.php` | Exportacion CSV de ventas |
| `imprimir_balance.php` | Balance de vestimenta imprimible |
| `imprimir_inventario.php` | Inventario imprimible |
| `imprimir_precios.php` | Lista de precios imprimible |
| `imprimir_ventas.php` | Reporte de ventas imprimible |

### Ciclos Lectivos (`/modules/ciclos/`)
| Archivo | Funcion |
|---------|---------|
| `index.php` | Gestion de ciclos lectivos, activar/desactivar ciclo |

### Usuarios (`/modules/usuarios/`)
| Archivo | Funcion |
|---------|---------|
| `index.php` | Listado de usuarios del sistema |
| `crear.php` | Registrar nuevo usuario con rol asignado |
| `editar.php` | Editar usuario, cambiar rol/estado/contrasena |
| `perfil.php` | Perfil del usuario autenticado |
| `logs.php` | Visualizacion del log de auditoria |

---

## Roles y Permisos

El sistema implementa tres niveles de acceso via `require_role()`:

| Rol | Acceso | Descripcion |
|-----|--------|-------------|
| **Administrador** | Total | Gestiona usuarios, elimina registros, anula ventas, accede a logs |
| **Supervisor** | Alto | Directivos — ve todo, edita datos, elimina registros (excepto ventas) |
| **Auxiliar** | Basico | Preceptores — inscribe alumnos, ve listas, registra pagos y asistencia |

**Matriz de permisos por operacion:**

| Operacion | Admin | Supervisor | Auxiliar |
|-----------|:-----:|:----------:|:--------:|
| Ver listados y dashboard | Si | Si | Si |
| Alta/edicion de alumnos | Si | Si | Si |
| Inscribir alumnos | Si | Si | Si |
| Registrar pagos | Si | Si | Si |
| Eliminar alumnos/cursos/tutores | Si | Si | No |
| Eliminar inscripciones | Si | Si | No |
| Ventas de vestimenta | Si | Si | No |
| Anular ventas | Si | No | No |
| Crear/editar usuarios | Si | No | No |
| Ver logs de auditoria | Si | No | No |
| Crear cursos | Si | Si | No |

---

## Seguridad

### Autenticacion
- Sesiones PHP con cookies seguras: `HttpOnly`, `SameSite=Lax`, `Secure` (automatico en HTTPS)
- `session_regenerate_id()` al hacer login para prevenir session fixation
- Proteccion anti-fuerza bruta: 5 intentos fallidos bloquean por 15 minutos (por IP)
- Contrasenas hasheadas con `password_hash(PASSWORD_DEFAULT)` (bcrypt)

### Autorizacion
- Funciones centralizadas en `config/auth.php`:
  - `require_login()` — cualquier usuario autenticado
  - `require_role(['Administrador', 'Supervisor'])` — roles especificos
- Operaciones destructivas (eliminar) restringidas a Administrador/Supervisor
- Operaciones financieras (ventas) restringidas a Administrador/Supervisor

### Proteccion contra XSS
- Todas las salidas HTML escapadas con `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`
- Patron de mensajes: texto plano en `$mensaje` + clase CSS en `$tipo_alerta`, escapado en el template

### Proteccion contra SQL Injection
- 100% de consultas via PDO prepared statements
- `ATTR_EMULATE_PREPARES = false` (prepared statements nativos de MySQL)

### Proteccion CSRF
- Token generado con `random_bytes(32)` almacenado en sesion
- Verificacion con `hash_equals()` en toda operacion POST
- Input hidden `csrf_token` en todos los formularios

### Headers HTTP de Seguridad (`.htaccess`)
```apache
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'
```

### Proteccion de Archivos
- `.env`, `.env.example`, `.gitignore` bloqueados via `<FilesMatch>` en `.htaccess`
- `config/` protegido con `Require all denied` (`.htaccess` propio)
- `logs/` y `fpdf/tutorial/` bloqueados al acceso HTTP
- `Options -Indexes` previene listado de directorios
- `.env` buscado primero fuera del document root (`../../.env`)

### Auditoria
- Log de errores en archivo: `logs/errores.log`
- Log de actividad en BD: tabla `logs_actividad` con usuario, accion, detalle, IP y timestamp
- Acciones auditadas: alta/baja de alumnos, inscripciones, pagos, login, gestion de usuarios

---

## Estructura de Directorios

```
comercio/
├── assets/                          # Frontend (local, sin CDN)
│   ├── css/                         #   Bootstrap 5.3 CSS
│   └── js/                          #   Bootstrap 5.3 JS + Chart.js
├── config/                          # Configuracion del sistema
│   ├── auth.php                     #   Autenticacion y autorizacion centralizada
│   ├── conexion.php                 #   Conexion PDO a MySQL + lectura de .env
│   ├── csrf.php                     #   Generacion y verificacion de tokens CSRF
│   ├── logger.php                   #   Log de errores (archivo) + auditoria (BD)
│   ├── crear_log_tabla.sql          #   DDL para tabla logs_actividad
│   ├── indices.sql                  #   Indices de rendimiento para MySQL
│   └── .htaccess                    #   Require all denied (bloquea acceso HTTP)
├── fpdf/                            # Libreria FPDF 1.86 (vendored)
├── includes/                        # Layout compartido
│   ├── header.php                   #   Navbar + head HTML + estilos
│   └── footer.php                   #   Scripts + cierre HTML
├── logs/                            # Logs del sistema (no versionados)
│   └── errores.log                  #   Errores capturados por log_error()
├── modules/                         # Modulos funcionales
│   ├── alumnos/                     #   CRUD + exportacion + busqueda AJAX
│   ├── ciclos/                      #   Gestion de ciclos lectivos
│   ├── cursos/                      #   CRUD + vista detallada + impresion
│   ├── inscripciones/               #   Alta + edicion de estado + ficha
│   ├── pagos/                       #   Registro + recibos + reportes
│   ├── tutores/                     #   CRUD + vinculacion con alumnos
│   ├── usuarios/                    #   Gestion de usuarios + logs
│   └── vestimenta/                  #   Inventario + ventas + balance
├── .env                             # Credenciales DB (NO versionado)
├── .htaccess                        # Seguridad global + headers HTTP + CSP
├── index.php                        # Dashboard principal
├── login.php                        # Login con anti-fuerza bruta
├── logout.php                       # Cierre de sesion seguro
└── README.md
```

---

## Instalacion Local (WAMP)

### Requisitos previos

- [WAMP Server](https://www.wampserver.com/) con Apache 2.4, PHP 8.3 y MySQL 5.7+
- Modulos Apache habilitados: `mod_rewrite` y `mod_headers`

### Paso a paso

**1. Clonar el repositorio:**
```bash
cd C:\wamp64\www
git clone https://github.com/danqueve/comercio1.git comercio
```

**2. Crear la base de datos:**
```sql
CREATE DATABASE comercio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Importar el schema SQL (solicitar al administrador del proyecto). Luego ejecutar los scripts de soporte:
```sql
-- Tabla de auditoria
SOURCE config/crear_log_tabla.sql;

-- Indices de rendimiento
SOURCE config/indices.sql;
```

**3. Crear el archivo `.env`** en la raiz del proyecto (o idealmente un nivel arriba):
```env
DB_HOST=localhost
DB_NAME=comercio
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

> **Nota de seguridad:** El sistema busca `.env` primero un nivel arriba del proyecto (`../../.env` desde `config/`). Si lo colocas fuera del document root, queda inaccesible desde el navegador incluso sin `.htaccess`.

**4. Verificar Apache** — en `httpd.conf`:
```apache
<Directory "c:/wamp64/www/">
    AllowOverride All
</Directory>
```
Asegurarse de que `mod_rewrite` y `mod_headers` esten habilitados (sin `#` al inicio de sus lineas `LoadModule`).

**5. Acceder al sistema:**
```
http://localhost/comercio
```

**6. Crear el primer usuario administrador** directamente en MySQL:
```sql
INSERT INTO usuarios (usuario, password, nombre_completo, id_rol, activo)
VALUES ('admin', '$2y$10$...hash...', 'Administrador', 1, 1);
```
Para generar el hash: `php -r "echo password_hash('tu_clave', PASSWORD_DEFAULT);"` desde la terminal.

---

## Configuracion de Produccion

### Lista de verificacion

- [ ] Mover `.env` **fuera** del document root (un directorio arriba)
- [ ] Configurar credenciales de produccion en `.env`
- [ ] Servir el sitio por **HTTPS** (las cookies `Secure` se activan automaticamente)
- [ ] Verificar permisos de escritura en `logs/` (`chmod 755`)
- [ ] Verificar que `.htaccess` funciona correctamente (`AllowOverride All`)
- [ ] Confirmar que `config/`, `logs/`, `.env` no son accesibles via HTTP
- [ ] Cambiar la contrasena del usuario administrador por defecto
- [ ] Configurar backups automaticos de la base de datos

### Recomendaciones adicionales

- Habilitar HTTPS con certificado SSL (Let's Encrypt es gratuito)
- Configurar rotacion de logs en `logs/errores.log`
- Monitorear la tabla `logs_actividad` periodicamente
- Mantener PHP y MySQL actualizados con parches de seguridad

---

## Base de Datos

### Tablas principales

| Tabla | Descripcion |
|-------|-------------|
| `alumnos` | Datos personales de alumnos (DNI, nombre, nacimiento, tutor) |
| `tutores` | Datos de tutores/responsables |
| `cursos` | Cursos por anio/division/turno |
| `inscripciones` | Relacion alumno-curso-ciclo con estado |
| `ciclos_lectivos` | Ciclos lectivos con indicador de activo |
| `pagos` | Registro de pagos por alumno y concepto |
| `usuarios` | Usuarios del sistema con rol y estado |
| `roles` | Roles disponibles (Administrador, Supervisor, Auxiliar) |
| `vestimenta_productos` | Inventario de productos con stock doble (deposito/administracion) |
| `vestimenta_ventas` | Cabecera de ventas |
| `vestimenta_detalle_venta` | Items de cada venta |
| `logs_actividad` | Auditoria de acciones del sistema |

### Indices de rendimiento

El archivo `config/indices.sql` crea indices optimizados para:
- `alumnos`: busqueda por DNI, apellido, nombre
- `pagos`: filtro por fecha y alumno
- `inscripciones`: filtro por alumno, ciclo, curso y estado
- `usuarios`: login por nombre de usuario

---

## Funcionalidades Destacadas

### Dashboard con cache inteligente
El panel principal cachea las estadisticas en sesion por 5 minutos, evitando consultas repetidas. La cache se invalida automaticamente tras operaciones de alta/baja/inscripcion via `invalidar_cache_dashboard()`.

### Autocompletado de tutores (AJAX)
Al registrar un alumno, el campo DNI del tutor busca automaticamente en la base de datos via `buscar_tutor.php`. Si el tutor ya existe, autocompleta sus datos evitando duplicados.

### Sistema dual de impresion
Los modulos de pagos soportan impresion en formato A4 (laser/inkjet) y formato matricial (impresoras de punto), adaptando el layout con CSS `@media print`.

### Gestion de stock doble (Vestimenta)
El inventario de vestimenta maneja dos ubicaciones de stock independientes: **deposito** y **administracion**, permitiendo control granular del flujo de productos.

### Exportaciones multiformato
Todos los listados principales soportan exportacion a **CSV** (compatible con Excel) y **PDF** (via FPDF), aplicando los mismos filtros activos en pantalla.

---

## Autores

Desarrollado para la **Escuela de Comercio N°1 "Gral. Manuel Belgrano"**.
