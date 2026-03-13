ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `mobile_experience_prompt_visits` INT(11) NOT NULL DEFAULT 0 AFTER `last_login`;

UPDATE `users` u
INNER JOIN `mobile_experience_feedback` mef ON mef.`user_id` = u.`id`
SET u.`mobile_experience_prompt_visits` = GREATEST(COALESCE(u.`mobile_experience_prompt_visits`, 0), 5);
