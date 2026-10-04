-- Academic session (year) on top of semester tagging (run after add_semester_tagging.sql).
--   schools.current_session  : e.g. '2026/2027'; with current_semester this is the school's period
--   manuals.session          : the session a material is sold in; with manuals.semester its period
--   academic_periods         : history of each school's periods; started_at is when purchases start
--                              counting for class rep exports (older unclaimed purchases never appear)
-- The student API only shows materials whose session AND semester match the school's current period.
-- Safe to run more than once (MariaDB 10.x: ADD COLUMN IF NOT EXISTS / CREATE TABLE IF NOT EXISTS).
-- manuals is MyISAM: run off-peak (table lock).

ALTER TABLE `schools` ADD COLUMN IF NOT EXISTS `current_session` VARCHAR(9) DEFAULT NULL
  COMMENT 'Current academic session, e.g. 2026/2027' AFTER `current_semester`;

ALTER TABLE `manuals` ADD COLUMN IF NOT EXISTS `session` VARCHAR(9) DEFAULT NULL
  COMMENT 'Academic session the material is sold in, e.g. 2026/2027. NULL = not tagged (hidden from students)';

CREATE TABLE IF NOT EXISTS `academic_periods` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `school_id` INT(11) NOT NULL,
  `session` VARCHAR(9) NOT NULL,
  `semester` TINYINT(1) NOT NULL,
  `started_at` DATETIME NOT NULL,
  `started_by` INT(11) DEFAULT NULL COMMENT 'admins.id; NULL = migration',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_school_started` (`school_id`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── One-off data tagging (only touches rows not tagged with a session yet) ──
-- Admin materials posted in the past 2 months: First Semester 2026/2027
UPDATE `manuals`
SET `session` = '2026/2027', `semester` = 1
WHERE `session` IS NULL AND `user_id` = 0 AND `created_at` >= DATE_SUB(NOW(), INTERVAL 2 MONTH);

-- Everything older (and materials created by students/HOCs): Second Semester 2025/2026
UPDATE `manuals`
SET `session` = '2025/2026', `semester` = 2
WHERE `session` IS NULL;

-- Every school is now in First Semester 2026/2027; it started 2 months ago, so purchases made
-- since then count as this period's.
UPDATE `schools`
SET `current_session` = '2026/2027', `current_semester` = 1,
    `current_semester_updated_at` = DATE_SUB(NOW(), INTERVAL 2 MONTH)
WHERE `current_session` IS NULL;

INSERT INTO `academic_periods` (`school_id`, `session`, `semester`, `started_at`, `started_by`)
SELECT s.`id`, '2026/2027', 1, s.`current_semester_updated_at`, NULL
FROM `schools` AS s
WHERE s.`current_session` = '2026/2027' AND s.`current_semester` = 1
  AND NOT EXISTS (SELECT 1 FROM `academic_periods` AS p WHERE p.`school_id` = s.`id`);
