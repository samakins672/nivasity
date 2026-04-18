ALTER TABLE `users`
  ADD COLUMN `nivasity_2_intro_seen_at` datetime NULL DEFAULT NULL AFTER `last_login`;
