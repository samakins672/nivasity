<?php

if (!function_exists('bulk_material_payment_has_column')) {
  function bulk_material_payment_has_column(mysqli $conn, string $table, string $column): bool
  {
    static $cache = [];

    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) {
      return $cache[$key];
    }

    $safeTable = mysqli_real_escape_string($conn, $table);
    $safeColumn = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $cache[$key] = $result && mysqli_num_rows($result) > 0;

    return $cache[$key];
  }
}

if (!function_exists('bulk_material_payment_has_table')) {
  function bulk_material_payment_has_table(mysqli $conn, string $table): bool
  {
    static $cache = [];

    $key = strtolower($table);
    if (array_key_exists($key, $cache)) {
      return $cache[$key];
    }

    $safeTable = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '{$safeTable}'");
    $cache[$key] = $result && mysqli_num_rows($result) > 0;

    return $cache[$key];
  }
}

if (!function_exists('bulk_material_payment_ensure_schema')) {
  function bulk_material_payment_ensure_schema(mysqli $conn): bool
  {
    static $schemaReady = false;

    if ($schemaReady) {
      return true;
    }

    $createBatchesSql = "CREATE TABLE IF NOT EXISTS `manual_bulk_payment_batches` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ref_id` varchar(64) NOT NULL,
      `manual_id` int(11) NOT NULL,
      `school_id` int(11) NOT NULL DEFAULT 1,
      `payer_user_id` int(11) NOT NULL,
      `payer_dept_id` int(11) NOT NULL DEFAULT 0,
      `manual_seller_id` int(11) DEFAULT NULL,
      `student_count` int(11) NOT NULL DEFAULT 0,
      `subtotal` int(11) NOT NULL DEFAULT 0,
      `fee_percent` decimal(5,2) NOT NULL DEFAULT 5.00,
      `fee_amount` int(11) NOT NULL DEFAULT 0,
      `total_amount` int(11) NOT NULL DEFAULT 0,
      `payment_status` varchar(32) NOT NULL DEFAULT 'pending',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `paid_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_manual_bulk_payment_ref` (`ref_id`),
      KEY `idx_manual_bulk_payment_payer_created` (`payer_user_id`, `created_at`),
      KEY `idx_manual_bulk_payment_manual_status` (`manual_id`, `payment_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($conn, $createBatchesSql);

    $createStudentsSql = "CREATE TABLE IF NOT EXISTS `manual_bulk_payment_students` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `batch_id` int(11) NOT NULL,
      `ref_id` varchar(64) NOT NULL,
      `manual_id` int(11) NOT NULL,
      `school_id` int(11) NOT NULL DEFAULT 1,
      `payer_user_id` int(11) NOT NULL,
      `payer_dept_id` int(11) NOT NULL DEFAULT 0,
      `placeholder_user_id` int(11) DEFAULT NULL,
      `matched_user_id` int(11) DEFAULT NULL,
      `manuals_bought_id` int(11) DEFAULT NULL,
      `first_name` varchar(255) NOT NULL,
      `last_name` varchar(255) NOT NULL,
      `normalized_first_name` varchar(255) NOT NULL,
      `normalized_last_name` varchar(255) NOT NULL,
      `raw_matric_no` varchar(100) NOT NULL,
      `normalized_matric_no` varchar(100) NOT NULL,
      `pending_lookup_matric_no` varchar(100) NOT NULL,
      `claim_status` varchar(32) NOT NULL DEFAULT 'pending',
      `claimed_at` datetime DEFAULT NULL,
      `confirmed_at` datetime DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_manual_bulk_payment_student` (`manual_id`, `school_id`, `normalized_matric_no`, `normalized_first_name`, `normalized_last_name`, `claim_status`),
      KEY `idx_manual_bulk_payment_ref` (`ref_id`),
      KEY `idx_manual_bulk_payment_batch` (`batch_id`),
      KEY `idx_manual_bulk_payment_match` (`school_id`, `payer_dept_id`, `normalized_matric_no`, `normalized_first_name`, `normalized_last_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($conn, $createStudentsSql);

    if (!bulk_material_payment_has_column($conn, 'manuals_bought', 'payer_user_id')) {
      mysqli_query($conn, "ALTER TABLE `manuals_bought` ADD COLUMN `payer_user_id` int(11) DEFAULT NULL AFTER `buyer`");
    }

    $schemaReady = bulk_material_payment_has_table($conn, 'manual_bulk_payment_batches')
      && bulk_material_payment_has_table($conn, 'manual_bulk_payment_students')
      && bulk_material_payment_has_column($conn, 'manuals_bought', 'payer_user_id');

    return $schemaReady;
  }
}

if (!function_exists('bulk_material_payment_normalize_text')) {
  function bulk_material_payment_normalize_text(string $value): string
  {
    $value = trim($value);
    if ($value === '') {
      return '';
    }

    $value = preg_replace('/\s+/', ' ', $value);
    return strtolower(trim((string) $value));
  }
}

if (!function_exists('bulk_material_payment_pending_lookup_matric')) {
  function bulk_material_payment_pending_lookup_matric(string $matricNo): string
  {
    $normalized = bulk_material_payment_normalize_text($matricNo);
    if ($normalized === '') {
      return '';
    }

    return $normalized . '_pend_';
  }
}

if (!function_exists('bulk_material_payment_fee_breakdown')) {
  function bulk_material_payment_fee_breakdown(int $subtotal, float $feePercent = 5.0): array
  {
    $subtotal = max(0, $subtotal);
    $feePercent = max(0, $feePercent);
    $feeAmount = (int) round($subtotal * ($feePercent / 100));

    return [
      'subtotal' => $subtotal,
      'fee_percent' => $feePercent,
      'fee_amount' => $feeAmount,
      'total_amount' => $subtotal + $feeAmount,
    ];
  }
}

if (!function_exists('bulk_material_payment_format_csv_header')) {
  function bulk_material_payment_format_csv_header(): array
  {
    return ['first_name', 'last_name', 'matric_no'];
  }
}

if (!function_exists('bulk_material_payment_placeholder_status')) {
  function bulk_material_payment_placeholder_status(): string
  {
    return 'pending_bulk_claim';
  }
}

if (!function_exists('bulk_material_payment_claim_status_pending')) {
  function bulk_material_payment_claim_status_pending(): string
  {
    return 'pending';
  }
}

?>