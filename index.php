<?php
session_start();
include('model/config.php');
include('model/page_config.php');
include('model/system_alerts.php');
include('model/payment_freeze.php');
require_once 'model/internal_wallet_service.php';
require_once 'model/material_copy_status.php';
require_once 'model/bulk_material_payment_service.php';

// Fetch active system alerts
$system_alerts = get_active_system_alerts($conn);

// Check payment freeze status
$gateway_payment_freeze_info = get_payment_freeze_info('gateway');
$wallet_payment_freeze_info = get_payment_freeze_info('wallet');
$free_payment_freeze_info = get_payment_freeze_info('free');
$play_store_url = 'https://play.google.com/store/apps/details?id=com.nivasity.app';
$mobile_prompt_captured = !empty($mobile_experience_prompt_state['captured']);
$mobile_prompt_should_show = !empty($mobile_experience_prompt_state['should_show']);
$wallet_pin_configured = function_exists('nivasityUserHasWalletPin') ? nivasityUserHasWalletPin($conn, (int)$user_id) : false;
$pending_bulk_claims = [];
if ((string) ($user_status ?? '') === 'verified' && in_array((string) ($_SESSION['nivas_userRole'] ?? ''), ['student', 'hoc'], true)) {
  $pending_bulk_claims = bulk_material_payment_get_pending_claims_for_user($conn, [
    'id' => $user_id,
    'school' => $school_id,
    'dept' => $user_dept,
    'matric_no' => $user_matric_no,
    'first_name' => $f_name,
    'last_name' => $l_name,
  ], 5);
}

// Simulate adding/removing the product to/from the cart
if (!isset($_SESSION["nivas_cart$user_id"])) {
  $_SESSION["nivas_cart$user_id"] = array();
}
if (!isset($_SESSION["nivas_cart_event$user_id"])) {
  $_SESSION["nivas_cart_event$user_id"] = array();
}
$total_cart_items = count($_SESSION["nivas_cart$user_id"]) + count($_SESSION["nivas_cart_event$user_id"]);
$total_cart_price = 0;
$store_level_options = [];
$bulk_payment_manual_options = [];
$manual_query_sql = '';

$user_dept_int = (int) $user_dept;
$school_id_int = (int) $school_id;
$legacy_manual_visibility_where = "1 = 0";
$manual_visibility_where = "1 = 0";

if ($user_dept_int > 0) {
  $legacy_manual_visibility_where = "m.dept = $user_dept_int";
}

try {
  $deptsHasFacultyId = false;
  $manualsHasFaculty = false;
  $manualsHasDepts = false;
  $deptsFacultyColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM depts LIKE 'faculty_id'");
  if ($deptsFacultyColumnRes && mysqli_num_rows($deptsFacultyColumnRes) > 0) {
    $deptsHasFacultyId = true;
  }
  $manualsFacultyColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM manuals LIKE 'faculty'");
  if ($manualsFacultyColumnRes && mysqli_num_rows($manualsFacultyColumnRes) > 0) {
    $manualsHasFaculty = true;
  }
  $manualsDeptsColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM manuals LIKE 'depts'");
  if ($manualsDeptsColumnRes && mysqli_num_rows($manualsDeptsColumnRes) > 0) {
    $manualsHasDepts = true;
  }

  if ($deptsHasFacultyId && $manualsHasFaculty && $user_dept_int > 0) {
    $user_faculty_id = 0;
    $user_dept_meta_q = mysqli_query($conn, "SELECT faculty_id FROM depts WHERE id = $user_dept_int AND school_id = $school_id_int LIMIT 1");
    if ($user_dept_meta_q && mysqli_num_rows($user_dept_meta_q) > 0) {
      $user_dept_meta = mysqli_fetch_assoc($user_dept_meta_q);
      $user_faculty_id = isset($user_dept_meta['faculty_id']) ? (int) $user_dept_meta['faculty_id'] : 0;
    }

    if ($user_faculty_id > 0) {
      $legacy_manual_visibility_where .= " OR (m.dept = 0 AND m.faculty = $user_faculty_id)";
    }
  }

  if ($manualsHasDepts && $user_dept_int > 0) {
    $normalized_depts_expr = "REPLACE(REPLACE(REPLACE(REPLACE(m.depts, '[', ''), ']', ''), '\"', ''), ' ', '')";
    $manual_visibility_where = "(m.depts IS NOT NULL AND FIND_IN_SET($user_dept_int, $normalized_depts_expr) > 0) OR (m.depts IS NULL AND ($legacy_manual_visibility_where))";
  } else {
    $manual_visibility_where = $legacy_manual_visibility_where;
  }
} catch (Throwable $e) {
  error_log('[index] store visibility fallback: ' . $e->getMessage());
  $manual_visibility_where = $legacy_manual_visibility_where;
}

try {
  $t_manuals = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(m.id) FROM manuals AS m WHERE ($manual_visibility_where) AND m.status = 'open' AND m.school_id = $school_id_int"))[0];
  $manual_query_sql = "SELECT * FROM manuals AS m WHERE ($manual_visibility_where) AND m.status = 'open' AND m.school_id = $school_id_int ORDER BY m.id DESC";
  $manual_query = mysqli_query($conn, $manual_query_sql);

  $level_query = mysqli_query($conn, "SELECT DISTINCT m.level FROM manuals AS m WHERE ($manual_visibility_where) AND m.status = 'open' AND m.school_id = $school_id_int AND m.level IS NOT NULL AND TRIM(m.level) <> '' ORDER BY m.level ASC");
  if ($level_query) {
    while ($level_row = mysqli_fetch_assoc($level_query)) {
      $level_value = trim((string) ($level_row['level'] ?? ''));
      if ($level_value !== '') {
        $store_level_options[] = $level_value;
      }
    }
    $store_level_options = array_values(array_unique($store_level_options));
  }
} catch (Throwable $e) {
  error_log('[index] manual query failed, falling back: ' . $e->getMessage());
  $t_manuals = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(id) FROM manuals WHERE dept = $user_dept_int AND status = 'open' AND school_id = $school_id_int"))[0];
  $manual_query_sql = "SELECT * FROM manuals WHERE dept = $user_dept_int AND status = 'open' AND school_id = $school_id_int ORDER BY id DESC";
  $manual_query = mysqli_query($conn, $manual_query_sql);
}

$can_open_bulk_payment_picker = in_array((string) ($_SESSION['nivas_userRole'] ?? ''), ['student', 'hoc'], true);
if ($can_open_bulk_payment_picker && $manual_query_sql !== '') {
  $bulk_payment_manual_query = mysqli_query($conn, $manual_query_sql);
  if ($bulk_payment_manual_query) {
    $todayYmd = date('Y-m-d');
    while ($bulk_payment_manual = mysqli_fetch_assoc($bulk_payment_manual_query)) {
      $bulk_due_date = trim((string) ($bulk_payment_manual['due_date'] ?? ''));
      $bulk_due_date_ymd = $bulk_due_date !== '' ? date('Y-m-d', strtotime($bulk_due_date)) : '';
      if ($bulk_due_date_ymd !== '' && $todayYmd > $bulk_due_date_ymd) {
        continue;
      }

      $bulk_title = trim((string) ($bulk_payment_manual['title'] ?? 'Selected Material'));
      $bulk_course_code = trim((string) ($bulk_payment_manual['course_code'] ?? ''));
      $bulk_price = (int) round((float) ($bulk_payment_manual['price'] ?? 0));
      $bulk_label = $bulk_title;
      if ($bulk_course_code !== '') {
        $bulk_label .= ' - ' . $bulk_course_code;
      }
      $bulk_label .= ' (₦ ' . number_format($bulk_price) . ')';

      $bulk_payment_manual_options[] = [
        'id' => (int) ($bulk_payment_manual['id'] ?? 0),
        'label' => $bulk_label,
      ];
    }
  }
}

$event_query = mysqli_query($conn, "SELECT * FROM events WHERE status = 'open' ORDER BY `id` DESC");

// Determine if the Store tab should be shown
$show_store = (isset($_SESSION['nivas_userRole']) && $_SESSION['nivas_userRole'] !== 'org_admin' && $_SESSION['nivas_userRole'] !== 'visitor');
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Store - Nivasity</title>

  <?php include('partials/_head.php') ?>
  <link rel="stylesheet" href="assets/vendors/select2/select2.min.css">
  <link rel="stylesheet" href="assets/vendors/select2-bootstrap-theme/select2-bootstrap.min.css">
  <style>
    .wallet-pin-input {
      height: 4.5rem;
      border-radius: 0.85rem;
      font-size: 1.55rem;
      font-weight: 700;
      letter-spacing: 0.24em;
      text-align: center;
      padding: 0.75rem 1rem;
    }

    .wallet-pin-input::placeholder {
      letter-spacing: 0.08em;
      font-size: 0.9rem;
      font-weight: 600;
    }

    .wallet-pin-field {
      max-width: 21rem;
      margin: 0 auto;
      text-align: center;
    }

    .wallet-pin-field .form-label {
      display: block;
      text-align: center;
    }

    .cart-payment-summary {
      background: linear-gradient(180deg, #fffdfa 0%, #ffffff 100%);
      border: 1px solid rgba(255, 145, 0, 0.12);
      border-radius: 1.35rem;
      overflow: hidden;
    }

    .cart-summary-heading {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.9rem;
      flex-wrap: wrap;
    }

    .cart-payment-summary .summary-muted {
      color: #7c6f67;
      font-size: 0.92rem;
    }

    .cart-summary-breakdown {
      margin-top: 1.15rem;
      padding: 1rem 1.05rem;
      border: 1px solid rgba(255, 145, 0, 0.14);
      border-radius: 1.1rem;
      background: linear-gradient(180deg, rgba(255, 248, 238, 0.98) 0%, rgba(255, 255, 255, 1) 100%);
    }

    .cart-payment-summary .summary-divider {
      border-top: 1px solid rgba(28, 24, 20, 0.08);
      margin: 1rem 0 1.25rem;
    }

    .cart-payment-summary .summary-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 0.8rem;
    }

    .cart-payment-summary .summary-row:last-child {
      margin-bottom: 0;
    }

    .cart-payment-summary .summary-row.is-total {
      padding-top: 0.85rem;
      margin-top: 0.85rem;
      border-top: 1px dashed rgba(28, 24, 20, 0.12);
    }

    .cart-payment-summary .summary-row-label {
      color: #3b312a;
      font-weight: 700;
      margin: 0;
      min-width: 0;
    }

    .cart-payment-summary .summary-row-caption {
      display: block;
      margin-top: 0.25rem;
      color: #1f8f4d;
      font-size: 0.82rem;
      font-weight: 700;
    }

    .cart-payment-summary .summary-row-value {
      color: #1d1b1a;
      font-weight: 800;
      margin: 0;
      text-align: right;
      flex-shrink: 0;
      white-space: nowrap;
    }

    .cart-payment-summary .summary-row-value-group {
      display: inline-flex;
      align-items: baseline;
      justify-content: flex-end;
      gap: 0.55rem;
      flex-wrap: wrap;
    }

    .cart-payment-summary .summary-row-standard-value {
      color: #c46600;
      font-size: 0.82rem;
      font-weight: 700;
      text-decoration: line-through;
      white-space: nowrap;
    }

    .payment-options-heading {
      display: grid;
      gap: 0.35rem;
    }

    .payment-options-title {
      margin: 0;
      color: #1d1b1a;
      font-size: 1rem;
      font-weight: 800;
    }

    .cart-payment-summary .wallet-savings-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      background: rgba(255, 145, 0, 0.12);
      color: #c46600;
      border-radius: 999px;
      padding: 0.45rem 0.85rem;
      font-size: 0.82rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .cart-payment-options {
      display: grid;
      gap: 0.9rem;
      margin-top: 1.15rem;
    }

    .cart-payment-option {
      border: 1px solid rgba(28, 24, 20, 0.08);
      border-radius: 1.1rem;
      padding: 1rem;
      background: #fff;
      box-shadow: 0 12px 30px rgba(45, 28, 8, 0.05);
    }

    .cart-payment-option.is-wallet {
      border-color: rgba(255, 145, 0, 0.28);
      background: linear-gradient(180deg, rgba(255, 244, 229, 0.96) 0%, rgba(255, 255, 255, 1) 100%);
      box-shadow: 0 18px 40px rgba(255, 145, 0, 0.12);
    }

    .cart-payment-option.is-wallet.is-disabled {
      background: linear-gradient(180deg, rgba(248, 244, 239, 0.9) 0%, rgba(255, 255, 255, 1) 100%);
      border-color: rgba(130, 122, 116, 0.18);
      box-shadow: none;
    }

    .cart-payment-option-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 1rem;
      margin-bottom: 0.85rem;
    }

    .cart-payment-option-title {
      margin: 0;
      color: #1f1f1f;
      font-size: 1rem;
      font-weight: 800;
    }

    .cart-payment-option-subtitle {
      display: block;
      margin-top: 0.2rem;
      color: #7c6f67;
      font-size: 0.84rem;
      line-height: 1.45;
    }

    .cart-payment-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      padding: 0.35rem 0.7rem;
      font-size: 0.72rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      white-space: nowrap;
    }

    .cart-payment-badge.is-wallet {
      background: #ff9100;
      color: #fff;
    }

    .cart-payment-badge.is-neutral {
      background: rgba(43, 36, 31, 0.08);
      color: #675950;
    }

    .cart-payment-total {
      display: flex;
      align-items: baseline;
      gap: 0.45rem;
      margin-bottom: 0.8rem;
    }

    .cart-payment-total-label {
      color: #7c6f67;
      font-size: 0.82rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .cart-payment-total-amount {
      color: #1f1f1f;
      font-size: 1.6rem;
      font-weight: 800;
      line-height: 1;
    }

    .cart-payment-meta {
      display: grid;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }

    .cart-payment-meta-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      color: #5a4b41;
      font-size: 0.9rem;
    }

    .cart-payment-meta-row span:first-child {
      min-width: 0;
    }

    .cart-payment-meta-row strong {
      color: #211d1a;
      font-weight: 800;
      flex-shrink: 0;
      white-space: nowrap;
    }

    .cart-payment-meta-row.is-final-total {
      padding-top: 0.65rem;
      border-top: 1px dashed rgba(28, 24, 20, 0.12);
      margin-top: 0.15rem;
    }

    .cart-payment-meta-row.is-final-total strong {
      font-size: 1.05rem;
    }

    .cart-payment-meta-row .fee-strike {
      color: #9a8f87;
      font-weight: 700;
      font-size: 0.82rem;
      text-decoration: line-through;
      margin-left: 0.45rem;
    }

    .cart-payment-meta-row.is-saving strong,
    .cart-payment-meta-row.is-saving span:last-child {
      color: #c46600;
    }

    .cart-payment-note {
      margin: 0 0 0.95rem;
      color: #6d625a;
      font-size: 0.88rem;
      line-height: 1.5;
    }

    .cart-payment-action {
      border-radius: 0.9rem;
      font-size: 0.96rem;
      font-weight: 800;
      letter-spacing: 0.01em;
      min-height: 3.35rem;
    }

    .cart-payment-action.wallet-primary {
      background: linear-gradient(135deg, #ff9a1f 0%, #ff8400 100%);
      border: none;
      color: #fff;
      box-shadow: 0 16px 30px rgba(255, 145, 0, 0.22);
    }

    .cart-payment-action.wallet-primary:hover,
    .cart-payment-action.wallet-primary:focus {
      color: #fff;
      background: linear-gradient(135deg, #ff9210 0%, #f47800 100%);
    }

    .cart-payment-action.gateway-secondary {
      border: 1px solid rgba(255, 145, 0, 0.55);
      color: #d67400;
      background: #fff;
    }

    .cart-payment-action.gateway-secondary:hover,
    .cart-payment-action.gateway-secondary:focus {
      color: #b86100;
      border-color: rgba(216, 116, 0, 0.75);
      background: rgba(255, 145, 0, 0.04);
    }

    .cart-payment-action.wallet-disabled {
      background: #ebe7e2;
      border: 1px solid #dfd8d1;
      color: #8f847b;
      box-shadow: none;
    }

    .cart-wallet-helper-link {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      color: #c46600;
      font-weight: 700;
      text-decoration: none;
    }

    .cart-wallet-helper-link:hover,
    .cart-wallet-helper-link:focus {
      color: #a85600;
      text-decoration: underline;
    }

    @media (max-width: 575.98px) {
      .cart-summary-heading,
      .cart-payment-option-header,
      .cart-payment-total,
      .cart-payment-meta-row,
      .cart-payment-summary .summary-row {
        gap: 0.75rem;
      }

      .cart-summary-heading,
      .cart-payment-option-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .cart-payment-summary .summary-row,
      .cart-payment-meta-row {
        align-items: center;
      }

      .cart-payment-summary .summary-row-value {
        text-align: right;
      }

      .cart-payment-summary .summary-row-value-group {
        justify-content: flex-end;
      }

      .cart-payment-meta-row strong {
        text-align: right;
      }

      .cart-payment-total {
        flex-direction: column;
        align-items: flex-start;
      }

      .cart-payment-total-amount {
        font-size: 1.4rem;
      }
    }
  </style>
</head>

<body>
  <div class="container-scroller sidebar-fixed">
    <!-- partial:partials/_navbar.html -->
    <?php include('partials/_navbar.php') ?>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_sidebar_user.php -->
      <?php include('partials/_sidebar_user.php') ?>
      <!-- partial -->
      <div class="main-panel">

        <div class="content-wrapper">
          <?php 
          // Display system alerts at the top of the page
          if (!empty($system_alerts)) {
            echo render_system_alerts($system_alerts);
          }
          ?>
          <div class="row">
            <div class="col-sm-12 px-2">
              <div class="home-tab">
                <div class="d-flex align-items-center justify-content-between border-bottom">
                  <ul class="nav nav-tabs d-flex" role="tablist">
                    <?php if ($show_store): ?>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold active" id="store-tab" data-bs-toggle="tab" href="#store"
                        role="tab" aria-controls="store" aria-selected="true">Store</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold<?php echo $show_store ? '' : ' active'; ?>" id="events-tab" data-bs-toggle="tab" href="#events"
                        role="tab" aria-controls="events" aria-selected="<?php echo $show_store ? 'false' : 'true'; ?>">Events</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold" id="cart-tab" data-bs-toggle="tab" href="#cart" role="tab"
                        aria-selected="false">Cart (<span id="cart-count"><?php echo $total_cart_items; ?></span>)</a>
                    </li>
                  </ul>
                </div>
                <div class="tab-content tab-content-basic">
                  <?php if ($show_store): ?>
                  <div class="tab-pane fade show active" id="store" role="tabpanel" aria-labelledby="store">
                    <div class="row">
                      <div class="col-5 col-md-3 offset-md-9 form-group me-2">
                        <p class="text-muted">Level:</p>
                        <select class="form-control w-100" name="store-level-filter" id="store-level-filter">
                          <option value="">All Levels</option>
                          <?php foreach ($store_level_options as $level_option): ?>
                            <option value="<?php echo htmlspecialchars($level_option); ?>">
                              <?php echo htmlspecialchars($level_option); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-lg-12 d-flex flex-column">
                        <div class="row flex-grow sortables">
                          <?php
                          if (mysqli_num_rows($manual_query) > 0) {
                            $count_row = mysqli_num_rows($manual_query);

                            while ($manual = mysqli_fetch_array($manual_query)) {
                              $manual_id = $manual['id'];

                              // Check if the manual has been bought by the current user
                              $non_lost_condition = material_copy_non_lost_condition($conn, 'mb');
                              $is_bought_query = mysqli_query($conn, "SELECT COUNT(*) AS count FROM manuals_bought AS mb WHERE mb.manual_id = $manual_id AND mb.buyer = $user_id AND mb.school_id = $school_id AND {$non_lost_condition}");
                              $is_bought_result = mysqli_fetch_assoc($is_bought_query);

                              // If the manual has been bought, skip it
                              if ($is_bought_result['count'] > 0) {
                                $count_row = $count_row - 1;
                                continue;
                              }

                              // Retrieve and format the due date
                              $due_date = date('j M, Y', strtotime($manual['due_date']));
                              $due_date2 = date('Y-m-d', strtotime($manual['due_date']));
                              $material_scope_label = ((int)$manual['dept'] === 0 && (int)$manual['faculty'] > 0) ? 'Faculty' : 'Department';
                              // Retrieve the status
                              $status = $manual['status'];
                              $status_c = 'success';

                              if ($date > $due_date2) {
                                $status = 'disabled';
                                $status_c = 'danger';
                                if (abs(strtotime($date) - strtotime($due_date2)) > 10 * 24 * 60 * 60) {
                                  $count_row = $count_row - 1;
                                  continue;
                                }
                              }

                              // Check if the manual is already in the cart
                              $is_in_cart = in_array($manual_id, $_SESSION["nivas_cart$user_id"]);

                              // Update the Add to Cart button based on cart status
                              $button_text = $is_in_cart ? 'Remove' : 'Add to Cart';
                              $button_class = $is_in_cart ? 'btn-primary' : 'btn-outline-primary';

                              ?>
                                  <div class="col-12 col-md-6 col-lg-4 col-xl-3 grid-margin px-2 stretch-card sortable-card" data-level="<?php echo htmlspecialchars(strtolower(trim((string)($manual['level'] ?? '')))); ?>">
                                    <div class="card card-rounded shadow-sm h-100">
                                      <div class="card-body d-flex flex-column h-100">
                                        <h4 class="card-title"><?php echo $manual['title'] ?> <span class="text-secondary">- <?php echo $manual['course_code'] ?></span></h4>
                                        <div class="media">
                                          <i class="mdi mdi-book icon-lg text-secondary d-flex align-self-start me-3"></i>
                                          <div class="media-body">
                                            <h3 class="fw-bold price">₦ <?php echo number_format($manual['price']) ?></h3>
                                            <p class="card-text">
                                              <br>
                                              <span class="text-secondary">By: <?php echo $material_scope_label; ?></span>
                                            </p>
                                          </div>
                                        </div>
                                        <div class="mt-auto pt-2">
                                          <hr class="my-2">
                                          <div class="d-flex justify-content-between align-items-center">
                                            <?php if ($status != 'disabled'): ?>
                                                  <a href="javascript:;" title="Copy share link">
                                                    <i class="mdi mdi-share-variant icon-md text-muted share_button" data-title="<?php echo $manual['title']; ?>" data-product_id="<?php echo $manual['id']; ?>" data-type="product"></i>
                                                  </a>
                                                  <button class="btn <?php echo $button_class; ?> btn-lg m-0 cart-button" data-product-id="<?php echo $manual['id']; ?>">
                                                    <?php echo $button_text; ?>
                                                  </button>
                                            <?php else: ?>
                                                  <h4 class="fw-bold text-danger mb-0">Overdue !</h4>
                                            <?php endif; ?>
                                            </div>
                                          </div>
                                        </div>
                                      </div>
                                    </div>

                                  <?php
                            }
                            if ($count_row == 0) { ?>
                                      <div class="col-12">
                                          <div class="card card-rounded shadow-sm">
                                            <div class="card-body">
                                              <h5 class="card-title">All materials have been bought</h5>
                                              <p class="card-text">Check back later when your HOC/Lecturer uploads a new manual.</p>
                                            </div>
                                          </div>
                                      </div>
                                <?php }
                          } else {
                            // Display a message when no materials are found
                            ?>
                                  <div class="col-12">
                                      <div class="card card-rounded shadow-sm">
                                        <div class="card-body">
                                          <h5 class="card-title text-center">No material available.</h5>
                                          <p class="card-text text-center">Check back later when your HOC/Lecturer uploads a new manual.</p>
                                        </div>
                                      </div>
                                  </div>
                              <?php } ?>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php endif; ?>
                  <div class="tab-pane fade <?php echo $show_store ? 'hide' : 'show active'; ?>" id="events" role="tabpanel" aria-labelledby="events">
                    <div class="row">
                      <div class="col-5 col-md-3 offset-md-9 form-group me-2">
                        <p class="text-muted">Sort By:</p>
                        <select class="form-control w-100" name="sort-by" id="sort-by">
                          <option value="1">Event Date</option>
                          <option value="2">Price: Low to High</option>
                          <option value="3">Price: High to Low</option>
                        </select>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-lg-12 d-flex flex-column">
                        <div class="row flex-grow sortables">
                          <?php
                          if (mysqli_num_rows($event_query) > 0) {
                            $count_row = mysqli_num_rows($event_query);

                            while ($event = mysqli_fetch_array($event_query)) {
                              $event_id = $event['id'];
                              $seller_id = $event['user_id'];

                              // Check if the event has been bought by the current user
                              $is_bought_query = mysqli_query($conn, "SELECT COUNT(*) AS count FROM event_tickets WHERE event_id = $event_id AND buyer = $user_id");
                              $is_bought_result = mysqli_fetch_assoc($is_bought_query);

                              // If the event has been bought, skip it
                              if ($is_bought_result['count'] > 0) {
                                $count_row = $count_row - 1;
                                continue;
                              }

                              $seller_q = mysqli_fetch_array(mysqli_query($conn, "SELECT first_name, last_name FROM users WHERE id = $seller_id"));
                              $organisation = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM organisation WHERE user_id = $seller_id"));
                              $seller_fn = $seller_q['first_name'];
                              $seller_ln = $seller_q['last_name'];

                              // Retrieve and format the event_date and time
                              $event_date = date('j M', strtotime($event['event_date']));
                              $event_date2 = date('Y-m-d', strtotime($event['event_date']));
                                    
                              $event_time = date('g:i A', strtotime($event['event_time']));
                              $event_time2 = date('H:i', strtotime($event['event_time']));

                              // Retrieve the status
                              $status = $event['status'];
                              $status_c = 'success';

                              if ($date > $event_date2) {
                                $status = 'disabled';
                                $status_c = 'danger';
                                if (abs(strtotime($date) - strtotime($event_date2)) > 10 * 24 * 60 * 60) {
                                  $count_row = $count_row - 1;
                                  continue;
                                }
                              }

                              if ($event['event_type'] == 'school') {
                                $location = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM schools WHERE id = ".$event['school']))['code'];
                              } elseif ($event['event_type'] == 'public') {
                                $location = $event['location'];
                              } else {
                                $location = "Online Event";
                              }

                              // Check if the event is already in the cart
                              $is_in_cart = in_array($event_id, $_SESSION["nivas_cart_event$user_id"]);

                              // Update the Add to Cart button based on cart status
                              $button_text = $is_in_cart ? 'Remove' : 'Get Ticket';
                              $button_class = $is_in_cart ? 'btn-primary' : 'btn-outline-primary';
                              $event_price = number_format($event['price']);
                              $event_price = $event_price > 0 ? "₦ $event_price" : 'FREE';

                              ?>
                                  <div class="col-12 col-md-6 col-lg-4 col-xl-3 grid-margin px-2 stretch-card">
                                    <div class="card card-rounded shadow-sm">
                                      <div class="card-body p-0">
                                        <img src="assets/images/events/<?php echo $event['event_banner'] ?>" class="img-fluid rounded-top w-100" style="max-height: 140px; object-fit: cover;">
                                        <div class="p-3">
                                          <p class="fw-bold text-secondary"><i class="mdi mdi-map-marker menu-icon"></i> <?php echo $location ?></p>
                                          <h4 class="fw-bold text-uppercase"><?php echo $event['title'] ?></h4>
                                          <small class="fw-bold"><?php echo $event_date ?> • <?php echo $event_time ?></small><br>
                                          <small class="badge badge-success fw-bold text-uppercase mt-2"><?php echo $event_price ?></small>
                                          <p>Host: <span class="fw-bold text-secondary"><?php echo $organisation['business_name'] ?></span></p>
                                          <hr>
                                          <div class="d-flex justify-content-between">
                                            <a href="javascript:;">
                                              <i class="mdi mdi-share-variant icon-md text-muted share_button" title="Copy share link" data-title="<?php echo $event['title']; ?>" data-product_id="<?php echo $event['id']; ?>" data-type="event"></i>
                                            </a>
                                            <button class="btn <?php echo $button_class; ?>  btn-lg m-0 cart-event-button" data-event-id="<?php echo $event['id'] ?>" data-mdb-ripple-duration="0ms"><?php echo $button_text; ?></button>
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                  </div>

                                  <?php
                            }
                            if ($count_row == 0) { ?>
                                      <div class="col-12">
                                          <div class="card card-rounded shadow-sm">
                                            <div class="card-body">
                                              <h5 class="card-title">All events have been bought</h5>
                                              <p class="card-text">Check back later when a new event is uploaded.</p>
                                            </div>
                                          </div>
                                      </div>
                                <?php }
                          } else {
                            // Display a message when no events are found
                            ?>
                                  <div class="col-12">
                                      <div class="card card-rounded shadow-sm">
                                        <div class="card-body">
                                          <h5 class="card-title text-center">No event available.</h5>
                                          <p class="card-text text-center">Check back later when a new event is uploaded.</p>
                                        </div>
                                      </div>
                                  </div>
                              <?php } ?>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade hide" id="cart" role="tabpanel" aria-labelledby="cart">
                    
                  </div>
                  

                  <!-- User verifyTransaction Modal -->
                  <div class="modal fade" id="verifyTransaction" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="verifyTransactionLabel"
                    aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h4 class="modal-title fw-bold" id="verifyTransactionLabel">Verifying transaction...</h4>
                        </div>
                        <div class="modal-body">
                          <h4 class="text-center">
                            <div class="spinner-grow text-secondary spinner-1 me-1 mb-3" role="status">
                              <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="spinner-grow text-secondary spinner-2 me-1 mb-3" role="status">
                              <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="spinner-grow text-secondary spinner-3 mb-3" role="status">
                              <span class="visually-hidden">Loading...</span>
                            </div>
                            <br>
                            Please hang on in just a few seconds so we can verify your payment...
                          </h4>
                        </div>
                      </div>
                    </div>
                  </div>

                </div>

              </div>
            </div>
          </div>

        </div>
        <!-- content-wrapper ends -->
        <?php
          $nivasity_intro_mark_seen_url = 'model/user.php';
          $nivasity_intro_cta_label = 'Continue to Nivasity';
          include('partials/_nivasity_2_modal.php');
        ?>
        <!-- partial:partials/_footer.php -->
        <?php include('partials/_footer.php') ?>
        <!-- partial -->
      </div>

      <!-- Bootstrap alert container -->
      <div id="alertBanner"
        class="alert alert-success text-center alert-dismissible end-2 top-2 fade show position-fixed w-auto p-2 px-4"
        role="alert" style="z-index: 5000; display: none;">
        An error occurred during the AJAX request.
      </div>
      <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->

  <!-- plugins:js -->
  <script src="assets/vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.1/mdb.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <script src="assets/vendors/select2/select2.min.js"></script>
  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="assets/js/js/off-canvas.js"></script>
  <script src="assets/js/js/hoverable-collapse.js"></script>
  <script src="assets/js/js/template.js"></script>
  <script src="assets/js/js/settings.js"></script>
  <script src="assets/js/js/data-table.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="assets/js/script.js"></script>
  <script src="assets/js/main.js"></script>
  <script>
    const playStoreUrl = <?php echo json_encode($play_store_url); ?>;
    const mobileAppPromptCaptured = <?php echo $mobile_prompt_captured ? 'true' : 'false'; ?>;
    const mobileAppPromptShouldShow = <?php echo $mobile_prompt_should_show ? 'true' : 'false'; ?>;

    const urlParams = new URLSearchParams(window.location.search);
    // Get the logout parameter from the URL
    const cart = urlParams.get('cart');
    const manualId = urlParams.get('manual_id') || urlParams.get('product_id');

    // Check if the verify parameter is present
    if (cart) {
      $('.nav-link').removeClass('active');
      $('#cart-tab').addClass('active');

      // Show the corresponding tab content
      $('.tab-pane').removeClass('show active');
      $('#cart').addClass('show active');
    }
    
    // If a manual is specified in the URL, open Store and show modal
    if (manualId) {
      $('.nav-link').removeClass('active');
      $('#store-tab').addClass('active');

      $('.tab-pane').removeClass('show active');
      $('#store').addClass('show active');

      // Load and show the manual details modal
      $.ajax({
        type: 'GET',
        url: 'model/manual_details.php',
        data: { manual_id: manualId },
        success: function (html) {
          $('#manualModal .modal-content').html(html);
          $('#manualModal').modal('show');
        },
        error: function () {
          console.error('Failed to load material details');
        }
      });
    }

    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0ms');

      if ($.fn.select2 && $('#bulkPaymentManualSelect').length && !$('#bulkPaymentManualSelect').hasClass('select2-hidden-accessible')) {
        $('#bulkPaymentManualSelect').select2({
          theme: 'bootstrap',
          width: '100%',
          placeholder: 'Select a material',
          dropdownParent: $('#bulkPaymentManualPickerModal'),
          minimumResultsForSearch: 0
        });
      }

      function initMobileAppPromptModal() {
        var modalEl = document.getElementById('mobileAppPromoModal');
        if (!modalEl || !window.bootstrap || !bootstrap.Modal) return;
        if (mobileAppPromptCaptured) return;
        if (!mobileAppPromptShouldShow) return;

        var titleEl = document.getElementById('mobileAppPromoTitle');
        var bodyEl = document.getElementById('mobileAppPromoBody');
        var actionsEl = document.getElementById('mobileAppPromoActions');
        if (!titleEl || !bodyEl || !actionsEl) return;

        var modalInstance;
        if (bootstrap.Modal && typeof bootstrap.Modal.getOrCreateInstance === 'function') {
          modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        } else {
          modalInstance = new bootstrap.Modal(modalEl);
        }
        var selectedDevice = '';
        var selectedComfort = '';

        function submitComfortSurvey(deviceChoice, comfortLevel) {
          if (!deviceChoice || !comfortLevel) return;
          $.ajax({
            type: 'POST',
            url: 'model/mobile_experience_feedback.php',
            dataType: 'json',
            data: {
              device_choice: deviceChoice,
              comfort_level: comfortLevel,
              source_page: 'store'
            }
          }).fail(function () {
            console.warn('Unable to save mobile experience survey response.');
          });
        }

        function getComfortIntroMessage() {
          if (selectedComfort === 'love_it') {
            return "Thank you for the 💛. We're glad Nivasity is working great for you 😉.";
          }
          if (selectedComfort === 'its_cool') {
            return "Thanks for sharing. We're happy things feel cool so far 🙂.";
          }
          if (selectedComfort === 'its_okay') {
            return "Thanks for your honest feedback 👍. We know there's room to improve and we're working on it.";
          }
          if (selectedComfort === 'kinda_stressful') {
            return "Thanks for telling us. We're sorry it has felt stressful 🥺, and we're improving the experience.";
          }
          if (selectedComfort === 'not_good_experience') {
            return "We appreciate your honesty. We're sorry your experience has not been good 😣, and our team is prioritizing fixes.";
          }
          return "Thanks for being part of Nivasity. We're constantly improving your experience.";
        }

        function renderStep(step) {
          if (step === 'select') {
            titleEl.textContent = 'Nivasity Mobile App';
            bodyEl.textContent = 'Which mobile device you actively use?';
            actionsEl.innerHTML = ''
              + '<button type="button" class="btn btn-primary" data-app-device="android">Android</button>'
              + '<button type="button" class="btn btn-outline-primary" data-app-device="iphone">iPhone</button>';
            return;
          }

          if (step === 'survey') {
            titleEl.textContent = 'Nivasity Quick Survey (Optional)';
            bodyEl.textContent = 'How comfortable are you using Nivasity?';
            actionsEl.innerHTML = ''
              + '<div class="d-flex flex-column gap-2 w-100">'
              + '<button type="button" class="btn btn-outline-primary w-100 text-start" data-app-comfort="love_it">Love it 🔥</button>'
              + '<button type="button" class="btn btn-outline-primary w-100 text-start" data-app-comfort="its_cool">It\'s cool 🙂</button>'
              + '<button type="button" class="btn btn-outline-primary w-100 text-start" data-app-comfort="its_okay">It\'s okay 😐</button>'
              + '<button type="button" class="btn btn-outline-primary w-100 text-start" data-app-comfort="kinda_stressful">Kinda stressful 😕</button>'
              + '<button type="button" class="btn btn-outline-primary w-100 text-start" data-app-comfort="not_good_experience">Not a good experience 😣</button>'
              + '<button type="button" class="btn btn-light w-100 mt-1" data-app-action="skip-survey">Skip</button>'
              + '</div>';
            return;
          }

          if (step === 'iphone') {
            titleEl.textContent = 'Nivasity iOS App Coming Soon 🎊';
            bodyEl.innerHTML = ''
              + '<p class="mb-2">' + getComfortIntroMessage() + '</p><br>'
              + "<p class=\"mb-0\">We're excited to announce that our team is <strong>actively building</strong> the Nivasity iOS app and working to launch by <strong>March 2026</strong>, to give all students a <strong>smoother experience</strong>.</p>";
            actionsEl.innerHTML = '<button type="button" class="btn btn-primary" data-app-action="cancel">Cancel</button>';
            return;
          }

          titleEl.textContent = 'Nivasity Android App Is Live 🎊';
          bodyEl.innerHTML = ''
            + '<p class="mb-2">' + getComfortIntroMessage() + '</p><br>'
            + "<p class=\"mb-0\">We're happy to announce that our team has <strong>launched</strong> the Nivasity app on <strong>Google Play Store</strong>.</p>";
          actionsEl.innerHTML = ''
            + '<button type="button" class="btn btn-light" data-app-action="cancel">Cancel</button>'
            + '<a href="' + playStoreUrl + '" target="_blank" rel="noopener noreferrer" class="btn btn-primary" data-app-action="install">Install now</a>';
        }

        modalEl.addEventListener('click', function (e) {
          var deviceChoice = e.target.getAttribute('data-app-device');
          if (deviceChoice === 'android' || deviceChoice === 'iphone') {
            selectedDevice = deviceChoice;
            renderStep('survey');
            return;
          }

          var comfort = e.target.getAttribute('data-app-comfort');
          if (comfort) {
            selectedComfort = comfort;
            submitComfortSurvey(selectedDevice, comfort);
            renderStep(selectedDevice || 'android');
            return;
          }

          var action = e.target.getAttribute('data-app-action');
          if (action === 'skip-survey') {
            renderStep(selectedDevice || 'android');
            return;
          }

          if (action === 'cancel' || action === 'install') {
            modalInstance.hide();
          }
        });

        renderStep('select');
        modalInstance.show();
      }

      initMobileAppPromptModal();

      function initBulkMaterialClaimModal() {
        var pendingClaims = <?php echo json_encode(array_values($pending_bulk_claims), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        if (!Array.isArray(pendingClaims) || pendingClaims.length < 1) return;

        var modalEl = document.getElementById('bulkMaterialClaimModal');
        if (!modalEl || !window.bootstrap || !bootstrap.Modal) return;

        var titleEl = document.getElementById('bulkMaterialClaimTitle');
        var metaEl = document.getElementById('bulkMaterialClaimMeta');
        var messageEl = document.getElementById('bulkMaterialClaimMessage');
        var errorEl = document.getElementById('bulkMaterialClaimError');
        var confirmBtn = document.getElementById('bulkMaterialClaimConfirm');
        var rejectBtn = document.getElementById('bulkMaterialClaimReject');
        if (!titleEl || !metaEl || !messageEl || !errorEl || !confirmBtn || !rejectBtn) return;

        var modalInstance;
        if (typeof bootstrap.Modal.getOrCreateInstance === 'function') {
          modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        } else {
          modalInstance = new bootstrap.Modal(modalEl);
        }
        var isSubmitting = false;

        function setButtonsDisabled(disabled) {
          confirmBtn.disabled = disabled;
          rejectBtn.disabled = disabled;
        }

        function renderCurrentClaim() {
          var claim = pendingClaims[0];
          if (!claim) {
            modalInstance.hide();
            return;
          }

          titleEl.textContent = claim.title + (claim.course_code ? ' - ' + claim.course_code : '');
          metaEl.textContent = 'Paid by ' + (claim.payer_name || 'another student') + (claim.paid_at ? ' on ' + claim.paid_at : '');
          messageEl.innerHTML = 'We found a pending bulk material payment for <strong>'
            + (claim.student_name || 'this student')
            + '</strong> with matric number <strong>'
            + (claim.student_matric_no || 'N/A')
            + '</strong>. Confirm only if this payment was truly intended for you.';
          errorEl.classList.add('d-none');
          errorEl.textContent = '';
          setButtonsDisabled(false);
        }

        function handleClaimAction(action) {
          if (isSubmitting || pendingClaims.length < 1) return;
          var claim = pendingClaims[0];
          isSubmitting = true;
          setButtonsDisabled(true);
          errorEl.classList.add('d-none');
          errorEl.textContent = '';

          $.ajax({
            url: 'model/bulk_material_payment_claim.php',
            type: 'POST',
            dataType: 'json',
            data: {
              student_row_id: claim.id,
              action: action
            }
          }).done(function(response) {
            if (response && response.status === 'success') {
              pendingClaims = response.data && Array.isArray(response.data.remaining_claims) ? response.data.remaining_claims : [];
              if (pendingClaims.length < 1) {
                modalInstance.hide();
                if (action === 'confirm') {
                  window.location.reload();
                }
                return;
              }
              renderCurrentClaim();
              return;
            }
            errorEl.textContent = (response && response.message) ? response.message : 'Unable to update this bulk claim right now.';
            errorEl.classList.remove('d-none');
          }).fail(function(xhr) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Unable to update this bulk claim right now.';
            errorEl.textContent = message;
            errorEl.classList.remove('d-none');
          }).always(function() {
            isSubmitting = false;
            setButtonsDisabled(false);
          });
        }

        confirmBtn.addEventListener('click', function() {
          handleClaimAction('confirm');
        });
        rejectBtn.addEventListener('click', function() {
          handleClaimAction('reject');
        });

        renderCurrentClaim();
        modalInstance.show();
      }

      initBulkMaterialClaimModal();

      $(document).on('change', '#store-level-filter', function () {
        applyStoreLevelFilter();
      });

      function applyStoreLevelFilter() {
        var selectedLevel = ($('#store-level-filter').val() || '').toString().trim().toLowerCase();
        var $cards = $('#store .sortables .sortable-card');
        var visibleCount = 0;

        $cards.each(function () {
          var cardLevel = ($(this).attr('data-level') || '').toString().trim().toLowerCase();
          var shouldShow = !selectedLevel || selectedLevel === cardLevel;
          $(this).toggle(shouldShow);
          if (shouldShow) {
            visibleCount++;
          }
        });

        var $empty = $('#store-level-empty-state');
        if (!$empty.length) {
          $('#store .sortables').append(
            '<div class="col-12" id="store-level-empty-state" style="display:none;">'
              + '<div class="card card-rounded shadow-sm">'
                + '<div class="card-body">'
                  + '<h5 class="card-title text-center">No material available for this level.</h5>'
                  + '<p class="card-text text-center">Try another level.</p>'
                + '</div>'
              + '</div>'
            + '</div>'
          );
          $empty = $('#store-level-empty-state');
        }

        $empty.toggle(visibleCount === 0);
      }

      applyStoreLevelFilter();

      $('.go-to-cart-button').on('click', function () {
          $('#cart-tab').tab('show');
      });

      function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(function(){
            $('#alertBanner').removeClass('alert-info alert-danger').addClass('alert-success');
            $('#alertBanner').html('Link copied to clipboard');
            if (typeof showAlert === 'function') { showAlert(); }
          }, function(){
            // Fallback
            var temp = $('<input>');
            $('body').append(temp);
            temp.val(text).select();
            document.execCommand('copy');
            temp.remove();
            $('#alertBanner').removeClass('alert-info alert-danger').addClass('alert-success');
            $('#alertBanner').html('Link copied to clipboard');
            if (typeof showAlert === 'function') { showAlert(); }
          });
        } else {
          var temp = $('<input>');
          $('body').append(temp);
          temp.val(text).select();
          document.execCommand('copy');
          temp.remove();
          $('#alertBanner').removeClass('alert-info alert-danger').addClass('alert-success');
          $('#alertBanner').html('Link copied to clipboard');
          if (typeof showAlert === 'function') { showAlert(); }
        }
      }

      function isMobileDevice() {
        return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
      }

      $(document).on('click', '.share_button', function (e) {
        var button = $(this);
        var product_id = button.data('product_id');
        var type = button.data('type');
        var title = button.data('title');
        var shareText = 'Check out '+title+' on nivasity and order now!';

        if (type == 'product') {
          var shareUrl = <?php echo json_encode(nivasity_app_url('store_share.php')); ?>+"?manual_id="+product_id;
        } else {
          var shareUrl = <?php echo json_encode(nivasity_app_url('event_details.php')); ?>+"?event_id="+product_id;
        }

        // Mobile uses native share; desktop copies link
        if (isMobileDevice() && navigator.share) {
          navigator.share({ title: document.title, text: shareText, url: shareUrl })
            .catch(function(){ copyToClipboard(shareUrl); });
        } else {
          copyToClipboard(shareUrl);
        }
      });

      reloadCartTable()

      // Add to Cart button click event
      $(document).on('click', '.remove-cart', function (e) {
        var button = $(this);
        var type = button.data('type');
        var product_id = button.data('cart_id');

        // Make AJAX request to PHP file
        $.ajax({
          type: 'POST',
          url: 'model/cart.php', // Replace with your PHP file handling the cart logic
          data: { product_id: product_id, action: 0, type: type },
          success: function (data) {
            // Update the total number of carted products
            $('#cart-count').text(data.total);

            // Reload the cart table
            reloadCartTable();

            // Change the button text of the tag with data-product-id as the removed product ID
            btn_text = 'Add to Cart';
            if (type == 'event') {
              btn_text = 'Get Ticket';
            }
            $('button[data-'+type+'-id="' + product_id + '"]').toggleClass('btn-outline-primary btn-primary').text(btn_text);
          },
          error: function () {
            // Handle error
            console.error('Error in AJAX request');
          }
        });
      });

      // Add to Cart button click event (delegated for dynamic content)
      $(document).on('click', '.cart-button', function () {
        var button = $(this);
        if (button.prop('disabled') || button.hasClass('disabled')) {
          return;
        }
        var product_id = button.data('product-id');

        // Toggle button appearance and text
        if (button.hasClass('btn-outline-primary')) {
          button.toggleClass('btn-outline-primary btn-primary').text('Remove');
          action = 1;
        } else {
          button.toggleClass('btn-outline-primary btn-primary').text('Add to Cart');
          action = 0;
        }

        // Make AJAX request to PHP file
        $.ajax({
          type: 'POST',
          url: 'model/cart_manual.php', // Replace with your PHP file handling the cart logic
          data: { product_id: product_id, action: action },
          success: function (data) {
            // Update the total number of carted products
            $('#cart-count').text(data.total);

            // Reload the cart table
            reloadCartTable();
          },
          error: function () {
            // Handle error
            console.error('Error in AJAX request');
          }
        });
      });

      // Add to cart-event-button click event
      $('.cart-event-button').on('click', function () {
        var button = $(this);
        var event_id = button.data('event-id');

        // Toggle button appearance and text
        if (button.hasClass('btn-outline-primary')) {
          button.toggleClass('btn-outline-primary btn-primary').text('Remove');
          action = 1;
        } else {
          button.toggleClass('btn-outline-primary btn-primary').text('Get Ticket');
          action = 0;
        }

        // Make AJAX request to PHP file
        $.ajax({
          type: 'POST',
          url: 'model/cart_event.php', // Replace with your PHP file handling the cart logic
          data: { event_id: event_id, action: action },
          success: function (data) {
            // Update the total number of carted products
            $('#cart-count').text(data.total);

            // Reload the cart table
            reloadCartTable();
          },
          error: function () {
            // Handle error
            console.error('Error in AJAX request');
          }
        });
      });

      // Function to reload the cart table
      function reloadCartTable() {
        $.ajax({
          type: 'POST',
          url: 'model/cart.php',
          data: { reload_cart: 'reload_cart' },
          success: function (html) {
            $('#cart').html(html);
          },
          error: function () {
            // Handle error
            console.error('Error in reloading cart table');
          }
        });
      }

      // Pending payments: cancel
      $('#cart').on('click', '.pending-cancel', function() {
        var btn = $(this);
        var ref = btn.data('ref_id');
        btn.prop('disabled', true);
        $.ajax({
          type: 'POST',
          url: 'model/verify-pending-payment.php',
          dataType: 'json',
          data: { ref_id: ref, action: 'cancel' },
          success: function (res) {
            var $ab = $('#alertBanner');
            if ($ab.length) {
              $ab.removeClass('alert-info alert-danger alert-success alert-warning');
              $ab.addClass('alert-success');
              $ab.text('Pending payment cancelled.');
            }
            if (typeof showAlert === 'function') { showAlert(); }
            reloadCartTable();
          },
          complete: function () {
            btn.prop('disabled', false);
          },
          error: function () {
            console.error('Error cancelling pending payment');
          }
        });
      });

      // Pending payments: verify via Flutterwave
      $('#cart').on('click', '.pending-verify', function() {
        var btn = $(this);
        var ref = btn.data('ref_id');
        var orig = btn.text();
        btn.prop('disabled', true).text('Checking...');
        $.ajax({
          type: 'POST',
          url: 'model/verify-pending-payment.php',
          dataType: 'json',
          data: { ref_id: ref, action: 'verify' },
          success: function (res) {
            if (res.status === 'success') {
              location.reload();
            } else {
              var $ab = $('#alertBanner');
              if ($ab.length) {
                $ab.removeClass('alert-info alert-danger alert-success alert-warning');
                $ab.addClass('alert-warning');
                $ab.text(res.message || 'Payment not found yet. If you paid, please try again later.');
              }
              if (typeof showAlert === 'function') { showAlert(); }
              reloadCartTable();
            }
          },
          complete: function () {
            btn.prop('disabled', false).text(orig);
          },
          error: function () {
            console.error('Error verifying payment');
          }
        });
      });

      // Add to Cart button click event
      $('#cart').on('click', '.checkout-cart', function() {
        var triggerElement = this; // Store reference to the button that opened the modal
        
        // Check if payments are frozen
        <?php if ($gateway_payment_freeze_info): ?>
          // Show payment freeze modal
          $('#paymentFreezeMessage').text(<?php echo json_encode($gateway_payment_freeze_info['message'] ?? 'Gateway payments are currently paused. Only wallet payments are allowed right now.'); ?>);
          var modalElement = document.getElementById('paymentFreezeModal');
          var freezeModal = new bootstrap.Modal(modalElement);
          
          // Return focus to trigger element when modal is hidden
          modalElement.addEventListener('hidden.bs.modal', function handleHidden() {
            triggerElement.focus();
            modalElement.removeEventListener('hidden.bs.modal', handleHidden);
          });
          
          freezeModal.show();
          return; // Stop checkout process
        <?php endif; ?>

        email = "<?php echo $user_email ?>";
        phone = "<?php echo $user_phone ?>";
        u_name = "<?php echo $user_name ?>";
        transfer_amount = $(this).data('transfer_amount');
            
        // Retrieve and parse session data safely
        sessionData = $(this).data('session_data');
        // Check if sessionData is an object or a string
        let parsedSessionData;
        if (typeof sessionData === "string") {
            try {
                parsedSessionData = JSON.parse(sessionData); // Parse if it's a string
            } catch (error) {
                console.error("Error parsing session data:", error);
                return; // Exit if parsing fails
            }
        } else {
            parsedSessionData = sessionData; // Use as is if it's already an object
        }
                
        // Convert parsedSessionData to an array if it's an object
        if (typeof parsedSessionData === "object" && !Array.isArray(parsedSessionData)) {
          parsedSessionData = Object.values(parsedSessionData);
        }

        // Check the type and log the parsed session data for debugging
        console.log('parsedSessionData:', parsedSessionData);
        console.log('Type of parsedSessionData:', typeof parsedSessionData);

        function generateUniqueID() {
            const currentDate = new Date();
            const uniqueID = `nivas_<?php echo $user_id ?>_${currentDate.getTime()}`;
            return uniqueID;
        }

        const myUniqueID = generateUniqueID();

        // Get payment gateway keys first to determine active gateway
        $.ajax({
          url: 'model/getKey.php',
          type: 'POST',
          data: { getKey: 'get-Key'},
          success: function (data) {
            // Check if payment is frozen (server-side double-check)
            if (data.payment_frozen || data.error) {
              $('#paymentFreezeMessage').text(data.message || 'Payments are currently paused.');
              var modalElement = document.getElementById('paymentFreezeModal');
              var freezeModal = new bootstrap.Modal(modalElement);
              
              // Return focus to trigger element when modal is hidden
              modalElement.addEventListener('hidden.bs.modal', function handleHidden() {
                triggerElement.focus();
                modalElement.removeEventListener('hidden.bs.modal', handleHidden);
              });
              
              freezeModal.show();
              return;
            }

            var activeGateway = data.active_gateway || 'flutterwave';
            var flw_pk = data.flw_pk;
            var ps_pk = data.paystack_pk;
            
            console.log('Active Gateway:', activeGateway);

            // Save cart with gateway information and fetch adjusted payout shares.
            $.ajax({
              url: 'model/saveCart.php',
              type: 'POST',
              contentType: 'application/json',
              dataType: 'json',
              data: JSON.stringify({
                ref_id: myUniqueID,
                user_id: "<?php echo $user_id; ?>",
                gateway: activeGateway,
                items: parsedSessionData.map(item => ({
                  item_id: item.product_id,
                  type: item.type
                }))
              }),
              success: function(response) {
                if (!response || !response.success) {
                  console.error("Error saving cart:", response && response.message ? response.message : 'Unknown error');
                  alert('Unable to prepare checkout. Please try again.');
                  return;
                }

                console.log("Cart saved with gateway:", activeGateway, response.message);
                console.log("Refund reservation:", {
                  reserved: response.refund_reserved || 0,
                  school_share_before: response.school_share_before || 0,
                  school_share_after: response.school_share_after || 0
                });

                // Route to the appropriate payment gateway
                if (activeGateway === 'flutterwave') {
                  FlutterwaveCheckout({
                    public_key: flw_pk,
                    tx_ref: myUniqueID,
                    amount: transfer_amount,
                    currency: "NGN",
                    payment_options: "card, banktransfer, ussd",
                    callback: function(payment) {
                      console.log(payment);
                      verifyTransactionOnBackend(payment.transaction_id, payment.tx_ref);
                    },
                    onclose: function(status) {
                      if (!status) {
                        console.log(status);
                        $('#verifyTransaction').modal({
                          backdrop: 'static',
                          keyboard: false
                        }).modal('show');
                        
                        $('.spinner-grow').hide();
                        setTimeout(function() { $('.spinner-1').show(); }, 100);
                        setTimeout(function() { $('.spinner-2').show(); }, 300);
                        setTimeout(function() { $('.spinner-3').show(); }, 600);
                      }
                    },
                    customer: {
                        email: email,
                        phone_number: phone,
                        name: u_name,
                    },
                  });
                } else if (activeGateway === 'paystack') {
                  const amountKobo = Math.round(transfer_amount * 100);
                  function launchPaystack() {
                    var options = {
                      key: ps_pk,
                      email: email,
                      amount: amountKobo,
                      ref: myUniqueID,
                      callback: function(response) {
                        console.log(response);
                        verifyTransactionOnBackend(null, response.reference);
                      },
                      onClose: function() {
                        console.log('Payment window closed');
                        $('#verifyTransaction').modal({
                          backdrop: 'static',
                          keyboard: false
                        }).modal('show');
                      }
                    };
                    var handler = PaystackPop.setup(options);
                    handler.openIframe();
                  }
                  launchPaystack();
                } else if (activeGateway === 'interswitch') {
                  console.log('Interswitch payment - server-side initialization required');
                  window.location.href = 'model/handle-isw-init.php?ref=' + myUniqueID + '&amount=' + transfer_amount;
                } else {
                  alert('Unknown payment gateway: ' + activeGateway);
                }
              },
              error: function(xhr) {
                console.error("Error saving cart:", xhr);
                alert('Unable to prepare checkout. Please try again.');
              }
            });
          }
        });
      });

      $('#cart').on('click', '.wallet-cart-checkout', function() {
        var triggerElement = this;

        <?php if ($wallet_payment_freeze_info): ?>
          $('#paymentFreezeMessage').text(<?php echo json_encode($wallet_payment_freeze_info['message'] ?? 'Payments are currently paused.'); ?>);
          var walletFreezeModalElement = document.getElementById('paymentFreezeModal');
          var walletFreezeModal = new bootstrap.Modal(walletFreezeModalElement);
          walletFreezeModalElement.addEventListener('hidden.bs.modal', function handleHidden() {
            triggerElement.focus();
            walletFreezeModalElement.removeEventListener('hidden.bs.modal', handleHidden);
          });
          walletFreezeModal.show();
          return;
        <?php endif; ?>

        var sessionData = $(this).data('session_data');
        var parsedSessionData;
        if (typeof sessionData === 'string') {
          try {
            parsedSessionData = JSON.parse(sessionData);
          } catch (error) {
            console.error('Error parsing wallet checkout session data:', error);
            return;
          }
        } else {
          parsedSessionData = sessionData;
        }

        if (typeof parsedSessionData === 'object' && !Array.isArray(parsedSessionData)) {
          parsedSessionData = Object.values(parsedSessionData);
        }

        function generateUniqueID() {
          const currentDate = new Date();
          return `nivas_<?php echo $user_id ?>_${currentDate.getTime()}`;
        }

        function executeWalletCheckout(walletPin) {
          const walletRef = generateUniqueID();

          $.ajax({
            url: 'model/saveCart.php',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
              ref_id: walletRef,
              user_id: "<?php echo $user_id; ?>",
              gateway: 'NIVASITY',
              payment_channel: 'wallet',
              items: parsedSessionData.map(item => ({
                item_id: item.product_id,
                type: item.type
              }))
            }),
            success: function(response) {
              if (!response || !response.success) {
                alert(response && response.message ? response.message : 'Unable to prepare wallet checkout.');
                return;
              }

              $.ajax({
                url: 'model/wallet-checkout.php',
                type: 'POST',
                dataType: 'json',
                data: { ref_id: walletRef, wallet_pin: walletPin },
                success: function(walletResponse) {
                  if (walletResponse.status === 'success') {
                    location.reload();
                    return;
                  }
                  $('#walletCheckoutPinError').removeClass('d-none').text(walletResponse.message || 'Wallet checkout failed.');
                },
                error: function(xhr) {
                  console.error('Wallet checkout error', xhr);
                  var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Wallet checkout failed. Please try again.';
                  $('#walletCheckoutPinError').removeClass('d-none').text(message);
                },
                complete: function() {
                  $('#confirm-wallet-pin-btn').prop('disabled', false).text('Confirm & Pay');
                }
              });
            },
            error: function(xhr) {
              console.error('Wallet saveCart error', xhr);
              $('#walletCheckoutPinError').removeClass('d-none').text('Unable to prepare wallet checkout. Please try again.');
              $('#confirm-wallet-pin-btn').prop('disabled', false).text('Confirm & Pay');
            }
          });
        }

        var walletCheckoutModalElement = document.getElementById('walletPinCheckoutModal');
        var walletCheckoutModal = walletCheckoutModalElement ? new bootstrap.Modal(walletCheckoutModalElement) : null;
        $('#walletCheckoutPinError').addClass('d-none').text('');
        $('#walletCheckoutPin').val('');

        if (<?php echo $wallet_pin_configured ? 'true' : 'false'; ?>) {
          $('#walletPinCheckoutForm').removeClass('d-none');
          $('#walletPinCheckoutMissing').addClass('d-none');
          $('#confirm-wallet-pin-btn').removeClass('d-none').prop('disabled', false).text('Confirm & Pay');
        } else {
          $('#walletPinCheckoutForm').addClass('d-none');
          $('#walletPinCheckoutMissing').removeClass('d-none');
          $('#confirm-wallet-pin-btn').addClass('d-none');
        }

        $('#confirm-wallet-pin-btn').off('click').on('click', function() {
          var walletPin = $('#walletCheckoutPin').val().trim();
          if (!/^\d{4}$/.test(walletPin)) {
            $('#walletCheckoutPinError').removeClass('d-none').text('Enter your 4-digit Wallet PIN to continue.');
            return;
          }

          $('#walletCheckoutPinError').addClass('d-none').text('');
          $(this).prop('disabled', true).text('Processing...');
          executeWalletCheckout(walletPin);
        });

        if (walletCheckoutModal) {
          walletCheckoutModal.show();
        }
      });

      // free checkout button click event
      $('#cart').on('click', '.free-cart-checkout', function() {
        var triggerElement = this; // Store reference to the button that opened the modal
        
        // Check if payments are frozen
        <?php if ($free_payment_freeze_info): ?>
          // Show payment freeze modal
          $('#paymentFreezeMessage').text(<?php echo json_encode($free_payment_freeze_info['message'] ?? 'Payments are currently paused.'); ?>);
          var modalElement = document.getElementById('paymentFreezeModal');
          var freezeModal = new bootstrap.Modal(modalElement);
          
          // Return focus to trigger element when modal is hidden
          modalElement.addEventListener('hidden.bs.modal', function handleHidden() {
            triggerElement.focus();
            modalElement.removeEventListener('hidden.bs.modal', handleHidden);
          });
          
          freezeModal.show();
          return; // Stop checkout process
        <?php endif; ?>

        // Define event button
        var button = $(this);
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border text-white" style="width: 1.5rem; height: 1.5rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        function generateUniqueID() {
          const currentDate = new Date();
          const uniqueID = `nivas_<?php echo $user_id ?>_${currentDate.getTime()}`;
          return uniqueID;
        }

        const tx_ref = generateUniqueID();

        // Now make the Flutterwave API call
        $.ajax({
          url: 'model/handle-free-payment.php',
          type: 'GET',
          data: { tx_ref: tx_ref},
          success: function (response) {
            if (response.status === 'success') {
              location.reload();
            }

            // AJAX call successful, stop the spinner and update button text
            button.html(originalText);
            button.prop("disabled", false);
          },
          error: function () {
            // Handle error
            console.error('Error checking out!');
          }
        });
      });

      function verifyTransactionOnBackend(transaction_id, tx_ref) {
        // Use unified payment handler for all gateways
        var params = { tx_ref: tx_ref, callback: 1 };
        if (transaction_id) {
          params.transaction_id = transaction_id;
        } else {
          // For Paystack, use reference parameter
          params.reference = tx_ref;
        }
        
        $.ajax({
          url: 'model/handle-payment.php',
          type: 'GET',
          data: params,
          success: function (response) {
            if (response.status === 'success') {
              location.reload();
            }
          },
          error: function () {
            // Handle error
            console.error('Error verifying payment!');
          }
        });
      }

    });
  </script>
  <!-- End custom js for this page-->

  <!-- Manual Details Modal -->
  <div class="modal fade" id="manualModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <!-- dynamic content loads here via AJAX -->
      </div>
    </div>
  </div>

  <?php include('partials/_bulk_payment_modal.php') ?>

  <!-- Mobile App Promo Modal -->
  <div class="modal fade" id="mobileAppPromoModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="mobileAppPromoTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="mobileAppPromoTitle">Nivasity Mobile App</h5>
        </div>
        <div class="modal-body">
          <p class="mb-0" id="mobileAppPromoBody"></p>
        </div>
        <div class="modal-footer" id="mobileAppPromoActions">
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="bulkMaterialClaimModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="bulkMaterialClaimHeading" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="bulkMaterialClaimHeading">Confirm Bulk Material Payment</h5>
        </div>
        <div class="modal-body">
          <h6 class="fw-bold mb-1" id="bulkMaterialClaimTitle"></h6>
          <p class="text-muted mb-3" id="bulkMaterialClaimMeta"></p>
          <div class="alert alert-danger d-none" id="bulkMaterialClaimError"></div>
          <p class="mb-0" id="bulkMaterialClaimMessage"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" id="bulkMaterialClaimReject">No, this is not mine</button>
          <button type="button" class="btn btn-primary fw-bold" id="bulkMaterialClaimConfirm">Yes, this is mine</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Payment Freeze Modal -->
  <div class="modal fade" id="paymentFreezeModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="paymentFreezeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header bg-warning text-dark">
          <h4 class="modal-title fw-bold" id="paymentFreezeLabel">
            <i class="mdi mdi-alert-circle me-2"></i>Payment Update
          </h4>
        </div>
        <div class="modal-body">
          <p id="paymentFreezeMessage" class="mb-0"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Okay, I Understand</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="walletPinCheckoutModal" tabindex="-1" aria-labelledby="walletPinCheckoutLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="walletPinCheckoutLabel">Confirm Wallet Payment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted text-center mb-3">Enter your 4-digit Wallet PIN to authorize this payment.</p>
          <div class="alert alert-danger d-none" id="walletCheckoutPinError"></div>
          <div id="walletPinCheckoutForm" class="wallet-pin-field">
            <label for="walletCheckoutPin" class="form-label fw-bold">Wallet PIN</label>
            <input type="password" class="form-control wallet-pin-input" id="walletCheckoutPin" maxlength="4" inputmode="numeric" placeholder="4-DIGIT PIN">
          </div>
          <div class="alert alert-warning d-none mb-0" id="walletPinCheckoutMissing">
            Set up your Wallet PIN first on your wallet page before paying with wallet.
            <div class="mt-3">
              <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(nivasity_app_url('wallet.php'), ENT_QUOTES, 'UTF-8'); ?>">Go to Wallet Page</a>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary fw-bold" id="confirm-wallet-pin-btn">Confirm & Pay</button>
        </div>
      </div>
    </div>
  </div>
</body>

</html>

