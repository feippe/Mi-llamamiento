SET NAMES utf8mb4;

INSERT INTO units (id, type, name, parent_unit_id, created_at, updated_at) VALUES
  (1, 'stake', 'Estaca Demo', NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (2, 'ward', 'Barrio Prado', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (3, 'ward', 'Barrio Peñarol', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (4, 'ward', 'Barrio Brazo Oriental', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (5, 'ward', 'Barrio 7', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (6, 'ward', 'Barrio General Flores', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
  (7, 'branch', 'Rama Municipal', 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE name = VALUES(name), parent_unit_id = VALUES(parent_unit_id), updated_at = UTC_TIMESTAMP();

INSERT INTO organizations (id, scope_type, name, sort_order) VALUES
  (1, 'stake', 'Presidencia de Estaca', 1),
  (2, 'stake', 'Sumo Consejo', 2),
  (3, 'stake', 'Consejo de Estaca', 3),
  (4, 'stake', 'Sociedad de Socorro de Estaca', 4),
  (5, 'stake', 'Mujeres Jóvenes de Estaca', 5),
  (6, 'stake', 'Hombres Jóvenes de Estaca', 6),
  (7, 'stake', 'Primaria de Estaca', 7),
  (8, 'stake', 'Escuela Dominical de Estaca', 8),
  (9, 'stake', 'Templo e Historia Familiar de Estaca', 9),
  (10, 'stake', 'Seminario e Instituto de Estaca', 10),
  (11, 'stake', 'Bienestar y Autosuficiencia de Estaca', 11),
  (12, 'stake', 'Música de la Estaca', 12),
  (13, 'stake', 'Comité de Auditorías de Estaca', 13),
  (14, 'ward', 'Obispado', 1),
  (15, 'ward', 'Consejo de Barrio', 2),
  (16, 'ward', 'Cuórum de Élderes', 3),
  (17, 'ward', 'Sociedad de Socorro', 4),
  (18, 'ward', 'Mujeres Jóvenes', 5),
  (19, 'ward', 'Primaria', 6),
  (20, 'ward', 'Escuela Dominical', 7),
  (21, 'ward', 'Obra Misional', 8),
  (22, 'ward', 'Templo e Historia Familiar', 9),
  (23, 'branch', 'Presidencia de Rama', 1),
  (24, 'branch', 'Consejo de Rama', 2),
  (25, 'branch', 'Cuórum de Élderes', 3),
  (26, 'branch', 'Sociedad de Socorro', 4),
  (27, 'branch', 'Mujeres Jóvenes', 5),
  (28, 'branch', 'Primaria', 6),
  (29, 'branch', 'Escuela Dominical', 7),
  (30, 'branch', 'Obra Misional', 8),
  (31, 'branch', 'Templo e Historia Familiar', 9)
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), active = TRUE;

INSERT INTO organizations (id, scope_type, name, sort_order) VALUES
  (32, 'stake', 'Jóvenes Adultos Solteros de Estaca', 12),
  (33, 'stake', 'Adultos Solteros de Estaca', 13)
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), active = TRUE;

INSERT INTO callings (id, organization_id, name, authority_scope, sort_order) VALUES
  (1, 1, 'Presidente de Estaca', 'stake', 1),
  (2, 1, 'Primer Consejero de Estaca', 'stake', 2),
  (3, 1, 'Segundo Consejero de Estaca', 'stake', 3),
  (4, 1, 'Secretario de Estaca', 'stake', 4),
  (5, 1, 'Secretario Ejecutivo de Estaca', 'stake', 5),
  (6, 2, 'Miembro del Sumo Consejo', 'none', 1),
  (7, 4, 'Presidenta', 'area', 1),
  (8, 4, 'Primera Consejera', 'none', 2),
  (9, 4, 'Segunda Consejera', 'none', 3),
  (10, 4, 'Secretaria', 'none', 4),
  (11, 5, 'Presidenta', 'area', 1),
  (12, 5, 'Primera Consejera', 'none', 2),
  (13, 5, 'Segunda Consejera', 'none', 3),
  (14, 5, 'Secretaria', 'none', 4),
  (15, 6, 'Presidente', 'area', 1),
  (16, 6, 'Primer Consejero', 'none', 2),
  (17, 6, 'Segundo Consejero', 'none', 3),
  (18, 6, 'Secretario', 'none', 4),
  (19, 7, 'Presidenta', 'area', 1),
  (20, 7, 'Primera Consejera', 'none', 2),
  (21, 7, 'Segunda Consejera', 'none', 3),
  (22, 7, 'Secretaria', 'none', 4),
  (23, 8, 'Presidente', 'area', 1),
  (24, 8, 'Primer Consejero', 'none', 2),
  (25, 8, 'Segundo Consejero', 'none', 3),
  (26, 8, 'Secretario', 'none', 4),
  (27, 14, 'Obispo', 'unit', 1),
  (28, 14, 'Primer Consejero', 'unit', 2),
  (29, 14, 'Segundo Consejero', 'unit', 3),
  (30, 14, 'Secretario de Barrio', 'unit', 4),
  (31, 14, 'Secretario Ejecutivo de Barrio', 'unit', 5),
  (32, 16, 'Presidente', 'area', 1),
  (33, 16, 'Primer Consejero', 'none', 2),
  (34, 16, 'Segundo Consejero', 'none', 3),
  (35, 16, 'Secretario', 'none', 4),
  (36, 17, 'Presidenta', 'area', 1),
  (37, 17, 'Primera Consejera', 'none', 2),
  (38, 17, 'Segunda Consejera', 'none', 3),
  (39, 17, 'Secretaria', 'none', 4),
  (40, 18, 'Presidenta', 'area', 1),
  (41, 18, 'Primera Consejera', 'none', 2),
  (42, 18, 'Segunda Consejera', 'none', 3),
  (43, 18, 'Secretaria', 'none', 4),
  (44, 19, 'Presidenta', 'area', 1),
  (45, 19, 'Primera Consejera', 'none', 2),
  (46, 19, 'Segunda Consejera', 'none', 3),
  (47, 19, 'Secretaria', 'none', 4),
  (48, 20, 'Presidente', 'area', 1),
  (49, 20, 'Primer Consejero', 'none', 2),
  (50, 20, 'Segundo Consejero', 'none', 3),
  (51, 20, 'Secretario', 'none', 4),
  (52, 23, 'Presidente de Rama', 'unit', 1),
  (53, 23, 'Primer Consejero', 'unit', 2),
  (54, 23, 'Segundo Consejero', 'unit', 3),
  (55, 23, 'Secretario de Rama', 'unit', 4),
  (56, 23, 'Secretario Ejecutivo de Rama', 'unit', 5)
ON DUPLICATE KEY UPDATE authority_scope = VALUES(authority_scope), sort_order = VALUES(sort_order), active = TRUE;

INSERT INTO callings (id, organization_id, name, authority_scope, sort_order) VALUES
  (57, 9, 'Presidente', 'area', 1),
  (58, 9, 'Primer Consejero', 'none', 2),
  (59, 9, 'Segundo Consejero', 'none', 3),
  (60, 9, 'Secretario', 'none', 4),
  (61, 10, 'Coordinador', 'area', 1),
  (62, 10, 'Maestro', 'none', 2),
  (63, 10, 'Maestra', 'none', 3),
  (64, 10, 'Secretario', 'none', 4),
  (65, 11, 'Presidente', 'area', 1),
  (66, 11, 'Primer Consejero', 'none', 2),
  (67, 11, 'Segundo Consejero', 'none', 3),
  (68, 11, 'Secretario', 'none', 4),
  (69, 12, 'Director de Música', 'area', 1),
  (70, 12, 'Pianista', 'none', 2),
  (71, 13, 'Presidente del Comité', 'area', 1),
  (72, 13, 'Miembro del Comité', 'none', 2),
  (73, 32, 'Presidente', 'area', 1),
  (74, 32, 'Primer Consejero', 'none', 2),
  (75, 32, 'Segundo Consejero', 'none', 3),
  (76, 32, 'Secretario', 'none', 4),
  (77, 33, 'Presidente', 'area', 1),
  (78, 33, 'Primer Consejero', 'none', 2),
  (79, 33, 'Segundo Consejero', 'none', 3),
  (80, 33, 'Secretario', 'none', 4),
  (81, 21, 'Líder Misional de Barrio', 'area', 1),
  (82, 21, 'Misionero de Barrio', 'none', 2),
  (83, 21, 'Misionera de Barrio', 'none', 3),
  (84, 22, 'Líder de Templo e Historia Familiar', 'area', 1),
  (85, 22, 'Consultor', 'none', 2),
  (86, 22, 'Consultora', 'none', 3),
  (87, 30, 'Líder Misional de Rama', 'area', 1),
  (88, 30, 'Misionero de Rama', 'none', 2),
  (89, 30, 'Misionera de Rama', 'none', 3),
  (90, 31, 'Líder de Templo e Historia Familiar', 'area', 1),
  (91, 31, 'Consultor', 'none', 2),
  (92, 31, 'Consultora', 'none', 3),
  (93, 25, 'Presidente', 'area', 1),
  (94, 25, 'Primer Consejero', 'none', 2),
  (95, 25, 'Segundo Consejero', 'none', 3),
  (96, 25, 'Secretario', 'none', 4),
  (97, 26, 'Presidenta', 'area', 1),
  (98, 26, 'Primera Consejera', 'none', 2),
  (99, 26, 'Segunda Consejera', 'none', 3),
  (100, 26, 'Secretaria', 'none', 4),
  (101, 27, 'Presidenta', 'area', 1),
  (102, 27, 'Primera Consejera', 'none', 2),
  (103, 27, 'Segunda Consejera', 'none', 3),
  (104, 27, 'Secretaria', 'none', 4),
  (105, 28, 'Presidenta', 'area', 1),
  (106, 28, 'Primera Consejera', 'none', 2),
  (107, 28, 'Segunda Consejera', 'none', 3),
  (108, 28, 'Secretaria', 'none', 4),
  (109, 29, 'Presidente', 'area', 1),
  (110, 29, 'Primer Consejero', 'none', 2),
  (111, 29, 'Segundo Consejero', 'none', 3),
  (112, 29, 'Secretario', 'none', 4)
ON DUPLICATE KEY UPDATE authority_scope = VALUES(authority_scope), sort_order = VALUES(sort_order), active = TRUE;

INSERT INTO work_area_templates (id, scope_type, organization_id, name, is_personal, sort_order) VALUES
  (1, 'personal', NULL, 'Personal', TRUE, 0),
  (2, 'stake', 1, 'Presidencia de Estaca', FALSE, 1),
  (3, 'stake', 2, 'Sumo Consejo', FALSE, 2),
  (4, 'stake', 3, 'Consejo de Estaca', FALSE, 3),
  (5, 'stake', 4, 'Sociedad de Socorro de Estaca', FALSE, 4),
  (6, 'stake', 5, 'Mujeres Jóvenes de Estaca', FALSE, 5),
  (7, 'stake', 6, 'Hombres Jóvenes de Estaca', FALSE, 6),
  (8, 'stake', 7, 'Primaria de Estaca', FALSE, 7),
  (9, 'stake', 8, 'Escuela Dominical de Estaca', FALSE, 8),
  (10, 'ward', 14, 'Obispado', FALSE, 1),
  (11, 'ward', 15, 'Consejo de Barrio', FALSE, 2),
  (12, 'ward', 16, 'Cuórum de Élderes', FALSE, 3),
  (13, 'ward', 17, 'Sociedad de Socorro', FALSE, 4),
  (14, 'ward', 18, 'Mujeres Jóvenes', FALSE, 5),
  (15, 'ward', 19, 'Primaria', FALSE, 6),
  (16, 'ward', 20, 'Escuela Dominical', FALSE, 7),
  (17, 'branch', 23, 'Presidencia de Rama', FALSE, 1),
  (18, 'branch', 24, 'Consejo de Rama', FALSE, 2),
  (19, 'branch', 25, 'Cuórum de Élderes', FALSE, 3),
  (20, 'branch', 26, 'Sociedad de Socorro', FALSE, 4),
  (21, 'branch', 27, 'Mujeres Jóvenes', FALSE, 5),
  (22, 'branch', 28, 'Primaria', FALSE, 6),
  (23, 'branch', 29, 'Escuela Dominical', FALSE, 7)
ON DUPLICATE KEY UPDATE active = TRUE;

INSERT INTO work_area_templates (id, scope_type, organization_id, name, is_personal, sort_order) VALUES
  (24, 'stake', 9, 'Templo e Historia Familiar de Estaca', FALSE, 9),
  (25, 'stake', 10, 'Seminario e Instituto de Estaca', FALSE, 10),
  (26, 'stake', 11, 'Bienestar y Autosuficiencia de Estaca', FALSE, 11),
  (27, 'stake', 32, 'Jóvenes Adultos Solteros de Estaca', FALSE, 12),
  (28, 'stake', 33, 'Adultos Solteros de Estaca', FALSE, 13),
  (29, 'stake', 12, 'Música de la Estaca', FALSE, 14),
  (30, 'stake', 13, 'Comité de Auditorías de Estaca', FALSE, 15),
  (31, 'ward', 21, 'Obra Misional', FALSE, 8),
  (32, 'ward', 22, 'Templo e Historia Familiar', FALSE, 9),
  (33, 'branch', 30, 'Obra Misional', FALSE, 8),
  (34, 'branch', 31, 'Templo e Historia Familiar', FALSE, 9)
ON DUPLICATE KEY UPDATE active = TRUE;

INSERT IGNORE INTO calling_work_area_rules (calling_id, work_area_template_id, access_role) VALUES
  (1,2,'manager'),(1,3,'manager'),(1,4,'manager'),(1,5,'manager'),(1,6,'manager'),(1,7,'manager'),(1,8,'manager'),(1,9,'manager'),
  (2,2,'manager'),(2,3,'manager'),(2,4,'manager'),(2,5,'manager'),(2,6,'manager'),(2,7,'manager'),(2,8,'manager'),(2,9,'manager'),
  (3,2,'manager'),(3,3,'manager'),(3,4,'manager'),(3,5,'manager'),(3,6,'manager'),(3,7,'manager'),(3,8,'manager'),(3,9,'manager'),
  (4,2,'manager'),(4,4,'manager'),(5,2,'manager'),(5,4,'manager'),
  (6,3,'member'),(6,4,'member'),
  (7,5,'manager'),(7,4,'member'),(8,5,'member'),(9,5,'member'),(10,5,'member'),
  (11,6,'manager'),(11,4,'member'),(12,6,'member'),(13,6,'member'),(14,6,'member'),
  (15,7,'manager'),(15,4,'member'),(16,7,'member'),(17,7,'member'),(18,7,'member'),
  (19,8,'manager'),(19,4,'member'),(20,8,'member'),(21,8,'member'),(22,8,'member'),
  (23,9,'manager'),(23,4,'member'),(24,9,'member'),(25,9,'member'),(26,9,'member'),
  (27,10,'manager'),(27,11,'manager'),(27,13,'manager'),(27,14,'manager'),(27,15,'manager'),(27,16,'manager'),
  (28,10,'manager'),(28,11,'manager'),(28,14,'manager'),(28,15,'manager'),(28,16,'manager'),
  (29,10,'manager'),(29,11,'manager'),(29,14,'manager'),(29,15,'manager'),(29,16,'manager'),
  (30,10,'manager'),(31,10,'manager'),
  (32,12,'manager'),(32,11,'member'),(33,12,'member'),(34,12,'member'),(35,12,'member'),
  (36,13,'manager'),(36,11,'member'),(37,13,'member'),(38,13,'member'),(39,13,'member'),
  (40,14,'manager'),(40,11,'member'),(41,14,'member'),(42,14,'member'),(43,14,'member'),
  (44,15,'manager'),(44,11,'member'),(45,15,'member'),(46,15,'member'),(47,15,'member'),
  (48,16,'manager'),(48,11,'member'),(49,16,'member'),(50,16,'member'),(51,16,'member'),
  (52,17,'manager'),(52,18,'manager'),(52,20,'manager'),(52,21,'manager'),(52,22,'manager'),(52,23,'manager'),
  (53,17,'manager'),(53,18,'manager'),(53,21,'manager'),(53,22,'manager'),(53,23,'manager'),
  (54,17,'manager'),(54,18,'manager'),(54,21,'manager'),(54,22,'manager'),(54,23,'manager'),
  (55,17,'manager'),(56,17,'manager');

INSERT IGNORE INTO calling_work_area_rules (calling_id, work_area_template_id, access_role) VALUES
  (1,24,'manager'),(1,25,'manager'),(1,26,'manager'),(1,27,'manager'),(1,28,'manager'),(1,29,'manager'),(1,30,'manager'),
  (2,24,'manager'),(2,25,'manager'),(2,26,'manager'),(2,27,'manager'),(2,28,'manager'),(2,29,'manager'),(2,30,'manager'),
  (3,24,'manager'),(3,25,'manager'),(3,26,'manager'),(3,27,'manager'),(3,28,'manager'),(3,29,'manager'),(3,30,'manager'),
  (57,24,'manager'),(57,4,'member'),(58,24,'member'),(59,24,'member'),(60,24,'member'),
  (61,25,'manager'),(62,25,'member'),(63,25,'member'),(64,25,'member'),
  (65,26,'manager'),(65,4,'member'),(66,26,'member'),(67,26,'member'),(68,26,'member'),
  (69,29,'manager'),(70,29,'member'),
  (71,30,'manager'),(72,30,'member'),
  (73,27,'manager'),(73,4,'member'),(74,27,'member'),(75,27,'member'),(76,27,'member'),
  (77,28,'manager'),(77,4,'member'),(78,28,'member'),(79,28,'member'),(80,28,'member'),
  (27,12,'manager'),(27,31,'manager'),(27,32,'manager'),
  (28,12,'manager'),(28,31,'manager'),(28,32,'manager'),
  (29,12,'manager'),(29,31,'manager'),(29,32,'manager'),
  (81,31,'manager'),(81,11,'member'),(82,31,'member'),(83,31,'member'),
  (84,32,'manager'),(84,11,'member'),(85,32,'member'),(86,32,'member'),
  (52,19,'manager'),(52,33,'manager'),(52,34,'manager'),
  (53,19,'manager'),(53,33,'manager'),(53,34,'manager'),
  (54,19,'manager'),(54,33,'manager'),(54,34,'manager'),
  (87,33,'manager'),(87,18,'member'),(88,33,'member'),(89,33,'member'),
  (90,34,'manager'),(90,18,'member'),(91,34,'member'),(92,34,'member'),
  (93,19,'manager'),(93,18,'member'),(94,19,'member'),(95,19,'member'),(96,19,'member'),
  (97,20,'manager'),(97,18,'member'),(98,20,'member'),(99,20,'member'),(100,20,'member'),
  (101,21,'manager'),(101,18,'member'),(102,21,'member'),(103,21,'member'),(104,21,'member'),
  (105,22,'manager'),(105,18,'member'),(106,22,'member'),(107,22,'member'),(108,22,'member'),
  (109,23,'manager'),(109,18,'member'),(110,23,'member'),(111,23,'member'),(112,23,'member');

-- Mark callings that can conduct interviews (presidents, counselors, bishop)
UPDATE callings SET can_interview = 1 WHERE
  name LIKE '%Presidente%' OR
  name LIKE '%Presidenta%' OR
  name LIKE '%Consejero%' OR
  name LIKE '%Consejera%' OR
  name LIKE '%Obispo%';

-- Activate has_interviews for presidencies that conduct calling interviews
-- Excludes: Sumo Consejo (3), Consejo de Estaca (4), Escuela Dominical, Templo, etc.
UPDATE work_area_templates SET has_interviews = 1 WHERE id IN (
  2,   -- Presidencia de Estaca
  5,   -- Sociedad de Socorro de Estaca
  6,   -- Mujeres Jóvenes de Estaca
  7,   -- Hombres Jóvenes de Estaca
  8,   -- Primaria de Estaca
  10,  -- Obispado
  12,  -- Cuórum de Élderes (barrio)
  13,  -- Sociedad de Socorro (barrio)
  14,  -- Mujeres Jóvenes (barrio)
  15,  -- Primaria (barrio)
  17,  -- Presidencia de Rama
  19,  -- Cuórum de Élderes (rama)
  20,  -- Sociedad de Socorro (rama)
  21,  -- Mujeres Jóvenes (rama)
  22   -- Primaria (rama)
);

-- Activate has_ministering for Cuórum de Élderes and Sociedad de Socorro
UPDATE work_area_templates SET has_ministering = 1 WHERE id IN (12, 13, 19, 20);
