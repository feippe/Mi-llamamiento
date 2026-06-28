SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_prt_token (token_hash),
  INDEX idx_prt_user (user_id),
  CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('stake','ward','branch') NOT NULL,
  name VARCHAR(160) NOT NULL,
  parent_unit_id INT NULL,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_units_parent (parent_unit_id),
  CONSTRAINT fk_units_parent FOREIGN KEY (parent_unit_id) REFERENCES units(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS organizations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scope_type ENUM('stake','ward','branch') NOT NULL,
  name VARCHAR(160) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE KEY uq_org_scope_name (scope_type, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS callings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  organization_id INT NOT NULL,
  name VARCHAR(160) NOT NULL,
  authority_scope ENUM('none','area','unit','stake') NOT NULL DEFAULT 'none',
  can_interview TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE KEY uq_calling_org_name (organization_id, name),
  CONSTRAINT fk_callings_org FOREIGN KEY (organization_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_area_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scope_type ENUM('personal','stake','ward','branch') NOT NULL,
  organization_id INT NULL,
  name VARCHAR(160) NOT NULL,
  is_personal BOOLEAN NOT NULL DEFAULT FALSE,
  has_interviews TINYINT(1) NOT NULL DEFAULT 0,
  has_ministering TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  active BOOLEAN NOT NULL DEFAULT TRUE,
  CONSTRAINT fk_area_template_org FOREIGN KEY (organization_id) REFERENCES organizations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calling_work_area_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  calling_id INT NOT NULL,
  work_area_template_id INT NOT NULL,
  access_role ENUM('member','manager') NOT NULL DEFAULT 'member',
  UNIQUE KEY uq_calling_area_rule (calling_id, work_area_template_id),
  CONSTRAINT fk_rule_calling FOREIGN KEY (calling_id) REFERENCES callings(id),
  CONSTRAINT fk_rule_area_template FOREIGN KEY (work_area_template_id) REFERENCES work_area_templates(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_area_instances (
  id CHAR(36) PRIMARY KEY,
  template_id INT NOT NULL,
  unit_id INT NULL,
  owner_user_id CHAR(36) NULL,
  name VARCHAR(160) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_area_instance (template_id, unit_id, owner_user_id),
  INDEX idx_area_instances_unit (unit_id),
  CONSTRAINT fk_area_instance_template FOREIGN KEY (template_id) REFERENCES work_area_templates(id),
  CONSTRAINT fk_area_instance_unit FOREIGN KEY (unit_id) REFERENCES units(id),
  CONSTRAINT fk_area_instance_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_callings (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  calling_id INT NOT NULL,
  unit_id INT NOT NULL,
  status ENUM('pending','active','rejected','removed') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL,
  resolved_by CHAR(36) NULL,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_user_calling_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_user_calling_calling FOREIGN KEY (calling_id) REFERENCES callings(id),
  CONSTRAINT fk_user_calling_unit FOREIGN KEY (unit_id) REFERENCES units(id),
  CONSTRAINT fk_user_calling_resolver FOREIGN KEY (resolved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_area_members (
  id CHAR(36) PRIMARY KEY,
  work_area_id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  user_calling_id CHAR(36) NULL,
  role ENUM('member','manager','owner') NOT NULL DEFAULT 'member',
  status ENUM('pending','active','rejected','removed') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_area_member_calling (work_area_id, user_id, user_calling_id),
  INDEX idx_area_members_user (user_id),
  CONSTRAINT fk_area_member_area FOREIGN KEY (work_area_id) REFERENCES work_area_instances(id),
  CONSTRAINT fk_area_member_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_area_member_calling FOREIGN KEY (user_calling_id) REFERENCES user_callings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_requests (
  id CHAR(36) PRIMARY KEY,
  user_calling_id CHAR(36) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL,
  resolved_by CHAR(36) NULL,
  resolved_at DATETIME NULL,
  CONSTRAINT fk_access_request_calling FOREIGN KEY (user_calling_id) REFERENCES user_callings(id),
  CONSTRAINT fk_access_request_resolver FOREIGN KEY (resolved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
  id CHAR(36) PRIMARY KEY,
  work_area_id CHAR(36) NOT NULL,
  title VARCHAR(220) NOT NULL,
  description TEXT NULL,
  start_date DATE NULL,
  due_date DATE NULL,
  status ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending',
  assigned_to CHAR(36) NULL,
  created_by CHAR(36) NOT NULL,
  updated_by CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_tasks_area (work_area_id, deleted_at),
  CONSTRAINT fk_tasks_area FOREIGN KEY (work_area_id) REFERENCES work_area_instances(id),
  CONSTRAINT fk_tasks_assigned FOREIGN KEY (assigned_to) REFERENCES users(id),
  CONSTRAINT fk_tasks_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_tasks_updater FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS interviews (
  id CHAR(36) NOT NULL PRIMARY KEY,
  work_area_id CHAR(36) NOT NULL,
  interviewee VARCHAR(220) NOT NULL,
  scheduled_date DATE NULL,
  scheduled_time TIME NULL,
  interviewer_id CHAR(36) NULL,
  notes TEXT NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  created_by CHAR(36) NOT NULL,
  updated_by CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_interviews_area (work_area_id, deleted_at),
  CONSTRAINT fk_interviews_area FOREIGN KEY (work_area_id) REFERENCES work_area_instances(id),
  CONSTRAINT fk_interviews_interviewer FOREIGN KEY (interviewer_id) REFERENCES users(id),
  CONSTRAINT fk_interviews_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_interviews_updater FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ministering_pairs (
  id CHAR(36) NOT NULL PRIMARY KEY,
  work_area_id CHAR(36) NOT NULL,
  minister1 VARCHAR(220) NOT NULL,
  minister2 VARCHAR(220) NULL,
  assigned_to VARCHAR(220) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_min_pairs_area (work_area_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ministering_interviews (
  id CHAR(36) NOT NULL PRIMARY KEY,
  work_area_id CHAR(36) NOT NULL,
  pair_id CHAR(36) NOT NULL,
  quarter CHAR(7) NOT NULL,
  scheduled_date DATE NULL,
  scheduled_time TIME NULL,
  interviewer_id CHAR(36) NULL,
  notes TEXT NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  created_by CHAR(36) NOT NULL,
  updated_by CHAR(36) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX idx_min_interviews_pair (pair_id, quarter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
