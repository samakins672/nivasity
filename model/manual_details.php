<?php
session_start();
include('config.php');

header('Content-Type: text/html; charset=utf-8');

$manual_id = 0;
if (isset($_GET['manual_id'])) {
    $manual_id = intval($_GET['manual_id']);
} elseif (isset($_POST['manual_id'])) {
    $manual_id = intval($_POST['manual_id']);
}

if ($manual_id <= 0) {
    http_response_code(400);
    echo '<div class="p-4"><h5 class="mb-1">Invalid request</h5><p class="text-muted mb-0">No material specified.</p></div>';
    exit();
}

$manual_q = mysqli_query($conn, "SELECT * FROM manuals WHERE id = $manual_id LIMIT 1");
if (!$manual_q || mysqli_num_rows($manual_q) === 0) {
    http_response_code(404);
    echo '<div class="p-4"><h5 class="mb-1">Material not found</h5><p class="text-muted mb-0">This material may have been removed or is unavailable.</p></div>';
    exit();
}

$manual = mysqli_fetch_array($manual_q);
$price = number_format($manual['price']);
$due_date = date('j M, Y', strtotime($manual['due_date']));
$due_date2 = date('Y-m-d', strtotime($manual['due_date']));
$status = $manual['status'];
$material_scope_label = ((int)$manual['dept'] === 0 && (int)$manual['faculty'] > 0) ? 'Faculty' : 'Department';
$is_overdue = (date('Y-m-d') > $due_date2) || ($status === 'closed');

// Determine cart status
$in_cart = false;
if (isset($_SESSION['nivas_userId'])) {
    $uid = $_SESSION['nivas_userId'];
    $cart_key = "nivas_cart$uid";
    if (isset($_SESSION[$cart_key]) && is_array($_SESSION[$cart_key])) {
        $in_cart = in_array($manual_id, $_SESSION[$cart_key]);
    }
} elseif (isset($_SESSION['nivas_cart']) && is_array($_SESSION['nivas_cart'])) {
    $in_cart = in_array($manual_id, $_SESSION['nivas_cart']);
}

$btn_disabled = '';
if ($is_overdue) {
    // Treat overdue or explicitly closed materials as closed/unavailable
    $btn_text = 'Closed';
    $btn_class = 'btn-secondary disabled';
    $btn_disabled = 'disabled';
} else {
    $btn_text = $in_cart ? 'Remove' : 'Add to Cart';
    $btn_class = $in_cart ? 'btn-primary' : 'btn-outline-primary';
}
$is_public = isset($_GET['public']) || isset($_POST['public']);

?>
<div class="modal-header">
  <h5 class="modal-title">Material Details</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
  </div>
<div class="modal-body">
  <div class="d-flex align-items-start">
    <i class="mdi mdi-book icon-lg text-secondary d-flex align-self-start me-3"></i>
    <div>
      <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($manual['title']); ?> <span class="text-secondary">- <?php echo htmlspecialchars($manual['course_code']); ?></span></h5>
      <p class="mb-2"><span class="fw-bold">Price:</span> &#8358; <span class="fw-bold"><?php echo $price; ?></span></p>
      <p class="mb-2"><span class="fw-bold">Due date:</span> <span class="fw-bold <?php echo $is_overdue ? 'text-danger' : 'text-success'; ?>"><?php echo $due_date; ?></span></p>
      <p class="mb-0 text-secondary">By: <?php echo htmlspecialchars($material_scope_label); ?></p>
    </div>
  </div>
  <?php if ($is_overdue): ?>
    <div class="alert alert-danger rounded-3 mt-3" role="alert">
      This material is closed and not available for purchase.
    </div>
  <?php endif; ?>
</div>
<div class="modal-footer">
  <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?php echo $is_public ? 'Cancel' : 'Close'; ?></button>
  <button type="button" class="btn <?php echo $btn_class; ?> cart-button" <?php echo $btn_disabled; ?> data-product-id="<?php echo $manual_id; ?>"><?php echo $btn_text; ?></button>
</div>
