# Base de datos

## Crear base

```sql
CREATE DATABASE mi_llamamiento CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Importar estructura y datos iniciales

```bash
mysql -u root -p mi_llamamiento < database/schema.sql
mysql -u root -p mi_llamamiento < database/seed.sql
```

## Configuracion local

```bash
cp app/config/local.example.php app/config/local.php
```

Edita `app/config/local.php` con host, nombre de base, usuario y password reales.

## Bootstrap del primer aprobador

La app aprueba automaticamente la primera solicitud de un llamamiento de `Presidencia de Estaca` con autoridad de estaca cuando todavia no existe ningun miembro activo en esa presidencia.

Si ya habias creado esa solicitud antes de implementar la aprobacion automatica, puedes activarla manualmente una sola vez con este SQL, cambiando el correo:

```sql
SET @email = 'presidente@example.com';

UPDATE user_callings uc
JOIN users u ON u.id = uc.user_id
JOIN callings c ON c.id = uc.calling_id
JOIN organizations o ON o.id = c.organization_id
SET uc.status = 'active',
    uc.resolved_by = uc.user_id,
    uc.resolved_at = UTC_TIMESTAMP()
WHERE u.email = @email
  AND o.name = 'Presidencia de Estaca'
  AND c.authority_scope = 'stake';

UPDATE work_area_members wam
JOIN user_callings uc ON uc.id = wam.user_calling_id
JOIN users u ON u.id = uc.user_id
SET wam.status = 'active',
    wam.updated_at = UTC_TIMESTAMP()
WHERE u.email = @email
  AND uc.status = 'active';

UPDATE access_requests ar
JOIN user_callings uc ON uc.id = ar.user_calling_id
JOIN users u ON u.id = uc.user_id
SET ar.status = 'approved',
    ar.resolved_by = uc.user_id,
    ar.resolved_at = UTC_TIMESTAMP()
WHERE u.email = @email
  AND uc.status = 'active';
```
