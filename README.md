# Mi Llamamiento

PWA de gestión de tareas, áreas de servicio, llamamientos y entrevistas según `spec-taskmanager-lds.md`.

## Stack

- Frontend: HTML, CSS y JavaScript modular.
- Offline-first: Service Worker, Cache Storage, IndexedDB y cola local `sync_queue`.
- Backend: PHP puro con MVC, API REST JSON y PDO.
- Base de datos: MySQL.

## Instalación

1. Crea la base:

```sql
CREATE DATABASE mi_llamamiento CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importa el esquema y el catálogo:

```bash
mysql -u root -p mi_llamamiento < database/schema.sql
mysql -u root -p mi_llamamiento < seed-catalogo.sql
```

3. Configura variables de entorno si no usas los valores por defecto:

```bash
APP_ENV=development
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mi_llamamiento
DB_USERNAME=root
DB_PASSWORD=
```

4. Levanta el servidor:

```bash
php -S localhost:8080 router.php
```

5. Abre `http://localhost:8080`.

## Estado funcional

Implementado:

- PWA instalable con cache de app shell.
- UI responsive: barra inferior en celular y panel lateral en tablet/escritorio.
- Login de desarrollo y endpoint preparado para reemplazar por Google OAuth real.
- Perfil, catálogos, áreas, llamamientos y creación automática de accesos por llamamiento.
- Tareas y subtareas con permisos básicos de miembro/propietario.
- Parejas ministrantes, agenda, registro de entrevistas y semáforo trimestral.
- Entrevistas de liderazgo.
- Notificaciones in-app y sincronización offline last-write-wins para entidades editables.

Pendiente de credenciales/decisiones externas:

- Google OAuth real: requiere `GOOGLE_CLIENT_ID` y validación server-side del token contra Google.
- Web Push real: requiere claves VAPID y una librería o implementación de envío push desde PHP.
- Email de invitaciones: falta definir proveedor SMTP/API.
- Reglas finas de aprobación eclesiástica: el backend implementa propietario/aprobador presente y autoaprobación si no hay autoridad; las reglas especiales de cada llamamiento pueden ampliarse en `calling_approvers`.

## Incongruencias detectadas

- La especificación exige login Google y Web Push, pero no incluye credenciales ni proveedor de envío. Por eso se dejó modo desarrollo y configuración preparada.
- `seed-catalogo.sql` no contiene datos para `calling_approvers`; el documento dice que todos los casos quedan cubiertos por presidente/aprobador de unidad, pero hay reglas especiales como maestros de Seminario/Instituto que conviene modelar explícitamente.
- El SQL seed usa nombres sin acentos por compatibilidad, mientras el `.md` contiene acentos. La UI funciona con los datos que estén en la base.
