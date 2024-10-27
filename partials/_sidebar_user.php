<nav class="sidebar sidebar-offcanvas border-start border-2 border-secondary" id="sidebar">
  <ul class="nav">
    <li class="nav-item active">
      <a class="nav-link bg-primary" href="store.php">
        <i class="mdi mdi-store menu-icon text-white"></i>
        <span class="menu-title text-white fw-bold">Store</span>
      </a>
    </li>
    <!-- <li class="nav-item nav-category">Dashboard</li> -->
    <?php if ($_SESSION['nivas_userRole'] !== 'org_admin'): ?>
    <li class="nav-item">
      <a class="nav-link" href="orders.php">
        <i class="mdi mdi-package menu-icon"></i>
        <span class="menu-title">Orders</span>
      </a>
    </li>
    <?php endif; ?>
    <li class="nav-item">
      <a class="nav-link" href="tickets.php">
        <i class="mdi mdi-ticket menu-icon"></i>
        <span class="menu-title">Event Tickets</span>
      </a>
    </li>

    <?php if(!$is_admin_role):?>      
    <li class="nav-item nav-category">My Settings</li>
    <li class="nav-item">
      <a class="nav-link" href="user.php">
        <i class="mdi mdi-account-star menu-icon"></i>
        <span class="menu-title">Profile Details</span>
      </a>
    </li>
    <?php endif;?>

    <?php if($is_admin_role):?>
    <li class="nav-item nav-category">Change role</li>
    <li class="nav-item">
      <a class="nav-link" href="admin/">
        <i class="mdi mdi-view-dashboard-outline menu-icon"></i>
        <?php if ($_SESSION['nivas_userRole'] !== 'org_admin'): ?>
          <span class="menu-title">Manage Manuals</span>
        <?php else: ?>
          <span class="menu-title">Manage Events</span>
        <?php endif; ?>
      </a>
    </li>
    <?php endif;?>

    <li class="nav-item nav-category d-block d-md-none">Sign Out</li>
    <li class="nav-item d-block d-md-none">
      <a class="nav-link g_id_signout" href="signin.html?logout=1">
        <i class="menu-icon mdi mdi-power"></i>
        <span class="menu-title">Sign Out</span>
      </a>
    </li>
  </ul>
</nav>