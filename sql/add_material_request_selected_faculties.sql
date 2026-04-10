ALTER TABLE `material_requests`
  MODIFY COLUMN `scope` ENUM('school','faculty','selected_faculties','selected_departments','my_department') NOT NULL DEFAULT 'my_department',
  ADD COLUMN `target_faculty_ids_json` LONGTEXT DEFAULT NULL AFTER `target_department_id`;