# Mi Llamamiento

PWA mobile-first para gestionar tareas por áreas de trabajo asignadas según llamamientos.

## Stack

- PHP 8.3 sin librerías externas.
- MySQL.
- MVC propio.
- PWA en `public_html`.

## Estructura

```text
app/            lógica PHP fuera de public_html
database/       schema y seed
public_html/    raíz pública del servidor
storage/        archivos internos
```

## Instalación

1. Crea la base:

```sql
CREATE DATABASE mi_llamamiento CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importa schema y seed:

```bash
mysql -u root -p mi_llamamiento < database/schema.sql
mysql -u root -p mi_llamamiento < database/seed.sql
```

3. Configura credenciales locales:

```bash
cp app/config/local.example.php app/config/local.php
```

Edita `app/config/local.php` con tus credenciales reales de MySQL.

4. Desarrollo local:

```bash
php -S localhost:8080 -t public_html public_html/index.php
```

5. En hosting:

- Copia el proyecto completo al servidor.
- Configura la raíz web del dominio apuntando a `public_html`.
- Mantén `app/`, `database/` y `storage/` fuera del acceso público.

## MVP incluido

- Registro, login y logout.
- Contraseñas con `password_hash` / `password_verify`.
- Sesión con cookie `HttpOnly` y `SameSite=Lax`.
- Protección CSRF en acciones mutadoras.
- Área personal automática.
- Selección de llamamiento.
- Accesos pendientes por matriz de llamamiento-área.
- Aprobación/rechazo de solicitudes.
- CRUD de tareas por área de trabajo.
- Responsable opcional en tareas.

## Nota de bootstrap

La primera solicitud de un llamamiento de `Presidencia de Estaca` con autoridad de estaca se aprueba automaticamente si todavia no existe ningun miembro activo en esa presidencia. Para solicitudes creadas antes de este cambio, hay un SQL manual en `database/README.md`.
