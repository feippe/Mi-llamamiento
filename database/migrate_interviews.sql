-- Migration: add interview support
-- Compatible con MySQL 5.7+

DROP PROCEDURE IF EXISTS migrate_interviews;

DELIMITER //
CREATE PROCEDURE migrate_interviews()
BEGIN
  -- callings.can_interview
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'callings' AND COLUMN_NAME = 'can_interview'
  ) THEN
    ALTER TABLE callings ADD COLUMN can_interview TINYINT(1) NOT NULL DEFAULT 0;
  END IF;

  -- work_area_templates.has_interviews
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_area_templates' AND COLUMN_NAME = 'has_interviews'
  ) THEN
    ALTER TABLE work_area_templates ADD COLUMN has_interviews TINYINT(1) NOT NULL DEFAULT 0;
  END IF;

  -- work_area_templates.has_ministering
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_area_templates' AND COLUMN_NAME = 'has_ministering'
  ) THEN
    ALTER TABLE work_area_templates ADD COLUMN has_ministering TINYINT(1) NOT NULL DEFAULT 0;
  END IF;
END //
DELIMITER ;

CALL migrate_interviews();
DROP PROCEDURE IF EXISTS migrate_interviews;

-- Nuevas tablas

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

-- Activar flags según catálogo

UPDATE callings SET can_interview = 1 WHERE
  name LIKE '%Presidente%' OR name LIKE '%Presidenta%' OR
  name LIKE '%Consejero%'  OR name LIKE '%Consejera%'  OR
  name LIKE '%Obispo%';

UPDATE work_area_templates SET has_interviews = 1 WHERE id IN
  (2, 5, 6, 7, 8, 10, 12, 13, 14, 15, 17, 19, 20, 21, 22);

UPDATE work_area_templates SET has_ministering = 1 WHERE id IN (12, 13, 19, 20);
