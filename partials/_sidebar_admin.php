<nav class="sidebar sidebar-offcanvas border-start border-2 border-secondary" id="sidebar">
  <ul class="nav">
    <li class="nav-item active">
      <a class="nav-link bg-primary" href="javascript:;" data-bs-toggle="modal" data-bs-target="#addManual">
        <i class="mdi mdi-plus menu-icon text-white"></i>
        <span class="menu-title text-white fw-bold">New Manual</span>
      </a>
    </li>
    <li class="nav-item nav-category">Dashboard</li>
    <li class="nav-item">
      <a class="nav-link" href="/admin">
        <i class="mdi mdi-grid-large menu-icon"></i>
        <span class="menu-title">Overview</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="user.php">
        <i class="mdi mdi-account-outline menu-icon"></i>
        <span class="menu-title">Profile Settings</span>
      </a>
    </li>

    <li class="nav-item nav-category">Support</li>
    <li class="nav-item">
      <a class="nav-link" href="support.php">
        <i class="mdi mdi-comment-outline menu-icon"></i>
        <span class="menu-title">Support Tickets</span>
      </a>
    </li>

    <?php if ($admin_role): ?>
      <li class="nav-item nav-category">Student Panel</li>
      <li class="nav-item">
        <a class="nav-link" href="../store.php">
          <i class="mdi mdi-grid-large menu-icon"></i>
          <span class="menu-title">Go to Store</span>
        </a>
      </li>
    <?php endif; ?>

    <li class="nav-item nav-category">Sign Out</li>
    <li class="nav-item">
      <a class="nav-link" href="../signin.html?logout">
        <i class="menu-icon mdi mdi-power"></i>
        <span class="menu-title">Sign Out</span>
      </a>
    </li>
  </ul>
</nav>