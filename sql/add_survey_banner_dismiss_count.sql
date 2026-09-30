-- ================================================================
-- Migration: Add dismiss_count to survey_banner_dismissals
-- Tracks the number of times a user has dismissed a survey banner.
-- Dismissals persist permanently once dismiss_count reaches 5,
-- or immediately if the user attends to/completes the survey.
-- ================================================================

ALTER TABLE `survey_banner_dismissals`
  ADD COLUMN IF NOT EXISTS `dismiss_count` INT(11) NOT NULL DEFAULT 1
    COMMENT 'Number of times banner was dismissed. Persists after 5th dismissal unless survey already answered'
    AFTER `user_id`;
