<?php
if (!isset($bulk_payment_manual_options) || !is_array($bulk_payment_manual_options)) {
  $bulk_payment_manual_options = [];
  $bulkPaymentRole = (string) ($_SESSION['nivas_userRole'] ?? '');
  if (in_array($bulkPaymentRole, ['student', 'hoc'], true)) {
    $bulkUserDeptInt = (int) ($user_dept ?? 0);
    $bulkSchoolIdInt = (int) ($school_id ?? ($_SESSION['nivas_userSch'] ?? 0));
    $bulkLegacyVisibilityWhere = '1 = 0';
    $bulkVisibilityWhere = '1 = 0';

    if ($bulkUserDeptInt > 0) {
      $bulkLegacyVisibilityWhere = "m.dept = $bulkUserDeptInt";
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

      if ($deptsHasFacultyId && $manualsHasFaculty && $bulkUserDeptInt > 0) {
        $userFacultyId = 0;
        $userDeptMetaQ = mysqli_query($conn, "SELECT faculty_id FROM depts WHERE id = $bulkUserDeptInt AND school_id = $bulkSchoolIdInt LIMIT 1");
        if ($userDeptMetaQ && mysqli_num_rows($userDeptMetaQ) > 0) {
          $userDeptMeta = mysqli_fetch_assoc($userDeptMetaQ);
          $userFacultyId = isset($userDeptMeta['faculty_id']) ? (int) $userDeptMeta['faculty_id'] : 0;
        }

        if ($userFacultyId > 0) {
          $bulkLegacyVisibilityWhere .= " OR (m.dept = 0 AND m.faculty = $userFacultyId)";
        }
      }

      if ($manualsHasDepts && $bulkUserDeptInt > 0) {
        $normalizedDeptsExpr = "REPLACE(REPLACE(REPLACE(REPLACE(m.depts, '[', ''), ']', ''), '\"', ''), ' ', '')";
        $bulkVisibilityWhere = "(m.depts IS NOT NULL AND FIND_IN_SET($bulkUserDeptInt, $normalizedDeptsExpr) > 0) OR (m.depts IS NULL AND ($bulkLegacyVisibilityWhere))";
      } else {
        $bulkVisibilityWhere = $bulkLegacyVisibilityWhere;
      }
    } catch (Throwable $e) {
      error_log('[bulk_payment_modal] visibility fallback: ' . $e->getMessage());
      $bulkVisibilityWhere = $bulkLegacyVisibilityWhere;
    }

    $bulkManualQuerySql = "SELECT * FROM manuals AS m WHERE ($bulkVisibilityWhere) AND m.status = 'open' AND m.school_id = $bulkSchoolIdInt ORDER BY m.id DESC";
    $bulkPaymentManualQuery = mysqli_query($conn, $bulkManualQuerySql);
    if ($bulkPaymentManualQuery) {
      $todayYmd = date('Y-m-d');
      while ($bulkPaymentManual = mysqli_fetch_assoc($bulkPaymentManualQuery)) {
        $bulkDueDate = trim((string) ($bulkPaymentManual['due_date'] ?? ''));
        $bulkDueDateYmd = $bulkDueDate !== '' ? date('Y-m-d', strtotime($bulkDueDate)) : '';
        if ($bulkDueDateYmd !== '' && $todayYmd > $bulkDueDateYmd) {
          continue;
        }

        $bulkTitle = trim((string) ($bulkPaymentManual['title'] ?? 'Selected Material'));
        $bulkCourseCode = trim((string) ($bulkPaymentManual['course_code'] ?? ''));
        $bulkPrice = (int) round((float) ($bulkPaymentManual['price'] ?? 0));
        $bulkLabel = $bulkTitle;
        if ($bulkCourseCode !== '') {
          $bulkLabel .= ' - ' . $bulkCourseCode;
        }
        $bulkLabel .= ' (₦ ' . number_format($bulkPrice) . ')';

        $bulk_payment_manual_options[] = [
          'id' => (int) ($bulkPaymentManual['id'] ?? 0),
          'label' => $bulkLabel,
        ];
      }
    }
  }
}
?>
<div class="modal fade" id="bulkPaymentManualPickerModal" tabindex="-1" aria-labelledby="bulkPaymentManualPickerLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="get" action="<?php echo htmlspecialchars(nivasity_app_url('bulk_material_payment.php'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="modal-header">
          <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="bulkPaymentManualPickerLabel"><i class="mdi mdi-cash-multiple"></i><span>Bulk Payment</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted mb-3">Select the material you want to pay for in bulk.</p>
          <?php if (!empty($bulk_payment_manual_options)): ?>
            <div class="mb-0">
              <label for="bulkPaymentManualSelect" class="form-label fw-bold">Material</label>
              <select class="form-select" id="bulkPaymentManualSelect" name="manual_id" required>
                <option value="" selected disabled>Select a material</option>
                <?php foreach ($bulk_payment_manual_options as $bulk_payment_manual_option): ?>
                  <option value="<?php echo (int) ($bulk_payment_manual_option['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($bulk_payment_manual_option['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">This list includes materials that are still open for your department, even if you already bought your own copy.</div>
            </div>
          <?php else: ?>
            <div class="alert alert-warning mb-0">No materials are currently available for bulk payment.</div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <?php if (!empty($bulk_payment_manual_options)): ?>
            <button type="submit" class="btn btn-primary fw-bold">Proceed to Bulk Payment</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  (function () {
    function initBulkPaymentPicker() {
      if (!window.jQuery) {
        return;
      }

      var $select = $('#bulkPaymentManualSelect');
      if (!$select.length) {
        return;
      }

      if ($.fn.select2 && !$select.hasClass('select2-hidden-accessible')) {
        $select.select2({
          theme: 'bootstrap',
          width: '100%',
          placeholder: 'Select a material',
          dropdownParent: $('#bulkPaymentManualPickerModal'),
          minimumResultsForSearch: 0
        });
      }
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initBulkPaymentPicker);
    } else {
      initBulkPaymentPicker();
    }
  })();
</script>