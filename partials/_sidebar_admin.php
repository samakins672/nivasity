<nav class="sidebar sidebar-offcanvas border-start border-2 border-secondary" id="sidebar">
  <ul class="nav">
    <li class="nav-item d-md-none d-block">
      <div class="dropdown-header text-center">
        <img class="img-sm img-fluid rounded rounded-7" src="https://funaab.nivasity.com/assets/images/users/<?php echo $user_image?>" alt="Profile image">
        <span class="mb-1 mt-1 fw-bold d-block">
          <?php echo $user_name?><br>
          <?php if ($_SESSION['nivas_userRole'] == 'hoc'): ?>
            <small class="text-secondary">(HOC/Lecturer)</small>
          <?php else: ?>
            <small class="text-secondary">(Event Host)</small>
          <?php endif; ?>
        </span>
      </div>
    </li>
    <?php if($url == 'index.php'):?>
    <li class="nav-item active">
      <?php if (mysqli_num_rows($settlement_query) > 0): ?>
        <?php if ($_SESSION['nivas_userRole'] == 'hoc'): ?>
        <a class="nav-link bg-primary" href="javascript:;" data-bs-toggle="modal" data-bs-target="#<?php echo $manual_modal = ($user_status == 'verified') ? 'addManual' : 'verificationManual' ?>">
          <i class="mdi mdi-plus menu-icon text-white"></i>
          <span class="menu-title text-white fw-bold">
            New Material
        <?php else: ?>
          <a class="nav-link bg-primary" href="javascript:;" data-bs-toggle="modal" data-bs-target="#<?php echo $manual_modal = ($user_status == 'verified') ? 'addEvent' : 'verificationManual' ?>">
            <i class="mdi mdi-plus menu-icon text-white"></i>
            <span class="menu-title text-white fw-bold">
              New Event
        <?php endif; ?>
        </span>
      <?php else: ?> 
        <a class="nav-link bg-primary" href="javascript:;" data-bs-toggle="modal" data-bs-target="#addSettlement">
          <i class="mdi mdi-plus menu-icon text-white"></i>
          <span class="menu-title text-white fw-bold">
            Add Settlement
        </span>
      <?php endif; ?> 
      </a>
    </li>
    <?php endif;?>
    <li class="nav-item nav-category">Dashboard</li>
    <li class="nav-item">
      <a class="nav-link" href="/admin">
        <i class="mdi mdi-view-dashboard-outline menu-icon"></i>
        <span class="menu-title">Home</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="transaction.php">
        <i class="mdi mdi-receipt menu-icon"></i>
        <span class="menu-title">Transactions</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="user.php">
        <i class="mdi mdi-account-outline menu-icon"></i>
        <span class="menu-title">Profile Settings</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="support.php">
        <i class="mdi mdi-comment-outline menu-icon"></i>
        <span class="menu-title">Support Tickets</span>
      </a>
    </li>

    <?php if ($is_admin_role): ?>
      <li class="nav-item nav-category">Change Role</li>
      <li class="nav-item">
        <a class="nav-link" href="/">
          <i class="mdi mdi-store menu-icon"></i>
          <span class="menu-title">Marketplace</span>
        </a>
      </li>
    <?php endif; ?>

    <li class="nav-item nav-category d-block d-md-none">Sign Out</li>
    <li class="nav-item d-block d-md-none">
      <a class="nav-link g_id_signout" href="../signin.html?logout=1">
        <i class="menu-icon mdi mdi-power"></i>
        <span class="menu-title">Sign Out</span>
      </a>
    </li>
  </ul>
</nav>