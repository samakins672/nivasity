<?php
/**
 * Material Management Configuration (Example)
 * 
 * Copy this file to material_management.php in the same directory.
 * This file controls whether HOC/Admin users can manage materials in the admin panel.
 * 
 * IMPORTANT: material_management.php is ignored by git to allow per-environment configuration.
 */

// ============================================================================
// MATERIAL MANAGEMENT SETTINGS
// ============================================================================

/**
 * Enable or disable material management for HOC/Admin users
 * Set to true to allow HOC/Admin to manage materials (default behavior)
 * Set to false to disable material management at the HOC/Admin level
 * 
 * When set to false:
 * - The action column on the materials table will be removed
 * - The "Add new material" button will be disabled
 * - Clicking the disabled button shows a message directing users to contact faculty managers
 */
define('MATERIAL_MANAGEMENT_ENABLED', true);

/**
 * Custom message to display when material management is disabled
 * This message is shown when users try to click the disabled "Add new material" button
 * If empty, a default message will be used
 */
define('MATERIAL_MANAGEMENT_DISABLED_MESSAGE', 'The School Management has disabled material management at this level. Please reach out to your faculty managers to make enquiry.');
