<?php
/**
 * System Alerts Model
 * Handles fetching and displaying system-wide alerts to users
 */

/**
 * Fetch all active, non-expired system alerts
 * @param mysqli $conn Database connection
 * @return array Array of alert objects
 */
function get_active_system_alerts($conn) {
  $alerts = [];
  $current_time = date('Y-m-d H:i:s');
  
  $query = "SELECT id, message, expiry_date, created_at 
            FROM system_alerts 
            WHERE active = 1 
            AND expiry_date > ? 
            ORDER BY created_at DESC";
  
  $stmt = mysqli_prepare($conn, $query);
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $current_time);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
      $alerts[] = $row;
    }
    
    mysqli_stmt_close($stmt);
  }
  
  return $alerts;
}

/**
 * Render system alerts as a carousel or single alert
 * @param array $alerts Array of alert objects
 * @return string HTML output for alerts
 */
function render_system_alerts($alerts) {
  if (empty($alerts)) {
    return '';
  }
  
  $count = count($alerts);
  $carousel_id = 'systemAlertsCarousel_' . uniqid();
  
  ob_start();
  ?>
  <div class="system-alerts-container mb-3">
    <?php if ($count === 1): ?>
      <!-- Single alert -->
      <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <?php echo htmlspecialchars($alerts[0]['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php else: ?>
      <!-- Multiple alerts carousel -->
      <div id="<?php echo $carousel_id; ?>" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
        <div class="carousel-indicators">
          <?php for ($i = 0; $i < $count; $i++): ?>
            <button type="button" data-bs-target="#<?php echo $carousel_id; ?>" data-bs-slide-to="<?php echo $i; ?>" 
                    <?php echo $i === 0 ? 'class="active" aria-current="true"' : ''; ?> 
                    aria-label="Alert <?php echo $i + 1; ?>"></button>
          <?php endfor; ?>
        </div>
        <div class="carousel-inner">
          <?php foreach ($alerts as $index => $alert): ?>
            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
              <div class="alert alert-info alert-dismissible fade show mb-0" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                <?php echo htmlspecialchars($alert['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo $carousel_id; ?>" data-bs-slide="prev">
          <span class="carousel-control-prev-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#<?php echo $carousel_id; ?>" data-bs-slide="next">
          <span class="carousel-control-next-icon" aria-hidden="true"></span>
          <span class="visually-hidden">Next</span>
        </button>
      </div>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}
?>
