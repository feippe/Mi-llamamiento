SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS stakes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('estaca','distrito') NOT NULL DEFAULT 'estaca',
  nombre VARCHAR(255) NOT NULL,
  created_by CHAR(36) NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  stake_id INT NOT NULL,
  tipo ENUM('barrio','rama') NOT NULL DEFAULT 'barrio',
  nombre VARCHAR(255) NOT NULL,
  created_by CHAR(36) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_units_stake (stake_id),
  CONSTRAINT fk_units_stake FOREIGN KEY (stake_id) REFERENCES stakes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) PRIMARY KEY,
  google_id VARCHAR(255) UNIQUE NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  nombre VARCHAR(255) NOT NULL,
  foto_url VARCHAR(512) NULL,
  stake_id INT NOT NULL,
  unit_id INT NOT NULL,
  timezone VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_users_unit (stake_id, unit_id),
  CONSTRAINT fk_users_stake FOREIGN KEY (stake_id) REFERENCES stakes(id),
  CONSTRAINT fk_users_unit FOREIGN KEY (unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  token VARCHAR(255) UNIQUE NOT NULL,
  device_info VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  INDEX idx_sessions_user (user_id),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nivel ENUM('estaca','barrio','rama') NOT NULL,
  nombre VARCHAR(255) NOT NULL,
  es_solo_solapamiento BOOLEAN NOT NULL DEFAULT FALSE,
  orden INT NOT NULL DEFAULT 0,
  INDEX idx_service_areas_nivel (nivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS callings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  service_area_id INT NOT NULL,
  nombre VARCHAR(255) NOT NULL,
  es_presidente BOOLEAN NOT NULL DEFAULT FALSE,
  es_aprobador_unidad BOOLEAN NOT NULL DEFAULT FALSE,
  created_by CHAR(36) NULL,
  created_at DATETIME NULL,
  INDEX idx_callings_area (service_area_id),
  CONSTRAINT fk_callings_area FOREIGN KEY (service_area_id) REFERENCES service_areas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calling_overlaps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  calling_id INT NOT NULL,
  service_area_id INT NOT NULL,
  UNIQUE KEY uq_calling_overlap (calling_id, service_area_id),
  CONSTRAINT fk_overlap_calling FOREIGN KEY (calling_id) REFERENCES callings(id),
  CONSTRAINT fk_overlap_area FOREIGN KEY (service_area_id) REFERENCES service_areas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calling_approvers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  calling_id INT NOT NULL,
  approver_calling_id INT NOT NULL,
  UNIQUE KEY uq_calling_approver (calling_id, approver_calling_id),
  CONSTRAINT fk_calling_approvers_calling FOREIGN KEY (calling_id) REFERENCES callings(id),
  CONSTRAINT fk_calling_approvers_approver FOREIGN KEY (approver_calling_id) REFERENCES callings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_area_instances (
  id CHAR(36) PRIMARY KEY,
  service_area_id INT NULL,
  unit_id INT NULL,
  stake_id INT NULL,
  owner_user_id CHAR(36) NULL,
  es_personal BOOLEAN NOT NULL DEFAULT FALSE,
  nombre VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_area_instance_unit (service_area_id, unit_id),
  INDEX idx_area_instance_owner (owner_user_id),
  CONSTRAINT fk_area_instance_area FOREIGN KEY (service_area_id) REFERENCES service_areas(id),
  CONSTRAINT fk_area_instance_unit FOREIGN KEY (unit_id) REFERENCES units(id),
  CONSTRAINT fk_area_instance_stake FOREIGN KEY (stake_id) REFERENCES stakes(id),
  CONSTRAINT fk_area_instance_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_callings (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  calling_id INT NOT NULL,
  unit_id INT NULL,
  stake_id INT NULL,
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_user_callings_user (user_id),
  CONSTRAINT fk_user_callings_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_user_callings_calling FOREIGN KEY (calling_id) REFERENCES callings(id),
  CONSTRAINT fk_user_callings_unit FOREIGN KEY (unit_id) REFERENCES units(id),
  CONSTRAINT fk_user_callings_stake FOREIGN KEY (stake_id) REFERENCES stakes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS area_members (
  id CHAR(36) PRIMARY KEY,
  area_instance_id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  rol ENUM('miembro','propietario') NOT NULL DEFAULT 'miembro',
  estado ENUM('pendiente','activo','rechazado') NOT NULL DEFAULT 'pendiente',
  origen ENUM('llamamiento','solapamiento','invitacion') NOT NULL,
  user_calling_id CHAR(36) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_area_member_user (area_instance_id, user_id, user_calling_id),
  INDEX idx_area_members_user (user_id),
  INDEX idx_area_members_state (area_instance_id, estado),
  CONSTRAINT fk_area_members_area FOREIGN KEY (area_instance_id) REFERENCES service_area_instances(id),
  CONSTRAINT fk_area_members_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_area_members_calling FOREIGN KEY (user_calling_id) REFERENCES user_callings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_access_requests (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  area_instance_id CHAR(36) NOT NULL,
  user_calling_id CHAR(36) NOT NULL,
  estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  resolved_by CHAR(36) NULL,
  created_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  INDEX idx_access_requests_area (area_instance_id, estado),
  CONSTRAINT fk_access_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_access_area FOREIGN KEY (area_instance_id) REFERENCES service_area_instances(id),
  CONSTRAINT fk_access_calling FOREIGN KEY (user_calling_id) REFERENCES user_callings(id),
  CONSTRAINT fk_access_resolver FOREIGN KEY (resolved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
  id CHAR(36) PRIMARY KEY,
  area_instance_id CHAR(36) NOT NULL,
  creador_id CHAR(36) NOT NULL,
  responsable_id CHAR(36) NULL,
  titulo VARCHAR(255) NOT NULL,
  descripcion TEXT NULL,
  fecha_inicio DATE NULL,
  fecha_vencimiento DATE NULL,
  estado ENUM('pendiente','en_progreso','completada') NOT NULL DEFAULT 'pendiente',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_tasks_area (area_instance_id, deleted_at),
  INDEX idx_tasks_responsable (responsable_id),
  CONSTRAINT fk_tasks_area FOREIGN KEY (area_instance_id) REFERENCES service_area_instances(id),
  CONSTRAINT fk_tasks_creator FOREIGN KEY (creador_id) REFERENCES users(id),
  CONSTRAINT fk_tasks_responsable FOREIGN KEY (responsable_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subtasks (
  id CHAR(36) PRIMARY KEY,
  task_id CHAR(36) NOT NULL,
  titulo VARCHAR(255) NOT NULL,
  descripcion TEXT NULL,
  fecha_inicio DATE NULL,
  fecha_fin DATE NULL,
  estado ENUM('pendiente','en_progreso','completada') NOT NULL DEFAULT 'pendiente',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_subtasks_task (task_id),
  CONSTRAINT fk_subtasks_task FOREIGN KEY (task_id) REFERENCES tasks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ministering_pairs (
  id CHAR(36) PRIMARY KEY,
  area_instance_id CHAR(36) NOT NULL,
  integrante_1 VARCHAR(255) NOT NULL,
  integrante_2 VARCHAR(255) NOT NULL,
  responsable_id CHAR(36) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_pairs_area (area_instance_id),
  INDEX idx_pairs_responsable (responsable_id),
  CONSTRAINT fk_pairs_area FOREIGN KEY (area_instance_id) REFERENCES service_area_instances(id),
  CONSTRAINT fk_pairs_responsable FOREIGN KEY (responsable_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ministering_interviews (
  id CHAR(36) PRIMARY KEY,
  pair_id CHAR(36) NOT NULL,
  estado ENUM('agendada','realizada') NOT NULL,
  agendada_para DATETIME NULL,
  agendada_por CHAR(36) NULL,
  fecha_realizada DATE NULL,
  trimestre CHAR(7) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_interviews_pair_quarter (pair_id, trimestre),
  INDEX idx_interviews_state (estado),
  CONSTRAINT fk_interviews_pair FOREIGN KEY (pair_id) REFERENCES ministering_pairs(id),
  CONSTRAINT fk_interviews_scheduler FOREIGN KEY (agendada_por) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leadership_interviews (
  id CHAR(36) PRIMARY KEY,
  area_instance_id CHAR(36) NOT NULL,
  creador_id CHAR(36) NOT NULL,
  persona VARCHAR(255) NOT NULL,
  motivo VARCHAR(255) NULL,
  fecha_objetivo DATETIME NULL,
  estado ENUM('pendiente','realizada') NOT NULL DEFAULT 'pendiente',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_leadership_area_state (area_instance_id, estado),
  CONSTRAINT fk_leadership_area FOREIGN KEY (area_instance_id) REFERENCES service_area_instances(id),
  CONSTRAINT fk_leadership_creator FOREIGN KEY (creador_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invitations (
  id CHAR(36) PRIMARY KEY,
  area_instance_id CHAR(36) NOT NULL,
  invitado_por CHAR(36) NOT NULL,
  email VARCHAR(255) NOT NULL,
  invited_user_id CHAR(36) NULL,
  token VARCHAR(255) UNIQUE NULL,
  estado ENUM('pendiente','aceptada','rechazada','expirada') NOT NULL DEFAULT 'pendiente',
  expira_en DATETIME NULL,
  created_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_invitations_area FOREIGN KEY (area_instance_id) REFERENCES service_area_instances(id),
  CONSTRAINT fk_invitations_sender FOREIGN KEY (invitado_por) REFERENCES users(id),
  CONSTRAINT fk_invitations_user FOREIGN KEY (invited_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  tipo VARCHAR(64) NOT NULL,
  titulo VARCHAR(255) NOT NULL,
  cuerpo TEXT NULL,
  data JSON NULL,
  leida BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME NOT NULL,
  INDEX idx_notifications_user (user_id, leida),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  endpoint VARCHAR(512) NOT NULL,
  p256dh VARCHAR(255) NOT NULL,
  auth VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sync_queue (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  entidad VARCHAR(64) NOT NULL,
  entidad_id CHAR(36) NOT NULL,
  operacion ENUM('crear','editar','eliminar') NOT NULL,
  payload JSON NULL,
  client_timestamp DATETIME NOT NULL,
  server_timestamp DATETIME NULL,
  estado ENUM('pendiente','aplicado','conflicto') NOT NULL DEFAULT 'pendiente',
  CONSTRAINT fk_sync_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NULL,
  accion VARCHAR(128) NOT NULL,
  entidad VARCHAR(64) NULL,
  entidad_id CHAR(36) NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_audit_user (user_id),
  INDEX idx_audit_entity (entidad, entidad_id),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO stakes (id, tipo, nombre, created_at)
VALUES (1, 'estaca', 'Estaca Demo', UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

INSERT INTO units (id, stake_id, tipo, nombre, created_at)
VALUES (1, 1, 'barrio', 'Barrio Demo', UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);
