-- Migration: Ensure recruitment_leads has all required columns for recruitment dashboard

ALTER TABLE `recruitment_leads`
  ADD COLUMN IF NOT EXISTS `agent_onboarding_status` TINYINT(1) DEFAULT 0 AFTER `source`,
  ADD COLUMN IF NOT EXISTS `onboarding_status` TINYINT(1) DEFAULT 0 AFTER `agent_onboarding_status`,
  ADD COLUMN IF NOT EXISTS `remarks` TEXT NULL AFTER `onboarding_status`,
  ADD COLUMN IF NOT EXISTS `pre_assessment` TINYINT(1) DEFAULT 0 AFTER `updated_at`,
  ADD COLUMN IF NOT EXISTS `accreditation` TINYINT(1) DEFAULT 0 AFTER `pre_assessment`,
  ADD COLUMN IF NOT EXISTS `assessment` TINYINT(1) DEFAULT 0 AFTER `accreditation`,
  ADD COLUMN IF NOT EXISTS `sales_training` TINYINT(1) DEFAULT 0 AFTER `assessment`,
  ADD COLUMN IF NOT EXISTS `site_tour` TINYINT(1) DEFAULT 0 AFTER `sales_training`,
  ADD COLUMN IF NOT EXISTS `onboarding` TINYINT(1) DEFAULT 0 AFTER `site_tour`,
  ADD COLUMN IF NOT EXISTS `habit_forming` TINYINT(1) DEFAULT 0 AFTER `onboarding`,
  ADD COLUMN IF NOT EXISTS `digital_training` TINYINT(1) DEFAULT 0 AFTER `habit_forming`,
  ADD COLUMN IF NOT EXISTS `sales_training_materials` TINYINT(1) DEFAULT 0 AFTER `digital_training`,
  ADD COLUMN IF NOT EXISTS `objection_handling` TINYINT(1) DEFAULT 0 AFTER `sales_training_materials`,
  ADD COLUMN IF NOT EXISTS `VAST` TINYINT(1) DEFAULT 0 AFTER `objection_handling`,
  ADD COLUMN IF NOT EXISTS `sales_monitoring` TINYINT(1) DEFAULT 0 AFTER `VAST`,
  ADD COLUMN IF NOT EXISTS `LMS` TINYINT(1) DEFAULT 0 AFTER `sales_monitoring`,
  ADD COLUMN IF NOT EXISTS `comm_structure` TINYINT(1) DEFAULT 0 AFTER `LMS`,
  ADD COLUMN IF NOT EXISTS `terminologies` TINYINT(1) DEFAULT 0 AFTER `comm_structure`,
  ADD COLUMN IF NOT EXISTS `focus_projects` TINYINT(1) DEFAULT 0 AFTER `terminologies`;

-- Helpful indexes
ALTER TABLE `recruitment_leads`
  ADD INDEX IF NOT EXISTS `idx_onboarding_status` (`onboarding_status`),
  ADD INDEX IF NOT EXISTS `idx_created_at` (`created_at`),
  ADD INDEX IF NOT EXISTS `idx_email` (`email`),
  ADD INDEX IF NOT EXISTS `idx_contact_number` (`contact_number`);

SELECT 'Migration applied: recruitment_leads columns ensured' AS message;
