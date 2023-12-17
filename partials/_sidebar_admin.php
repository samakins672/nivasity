<nav class="sidebar sidebar-offcanvas border-start border-2 border-secondary" id="sidebar">
  <ul class="nav">
    <li class="nav-item active">
      <a class="nav-link bg-primary" href="store.html">
        <i class="mdi mdi-store menu-icon text-white"></i>
        <span class="menu-title text-white fw-bold">Store</span>
      </a>
    </li>
    <!-- <li class="nav-item nav-category">Dashboard</li> -->
    <li class="nav-item">
      <a class="nav-link" href="orders.html">
        <i class="mdi mdi-package menu-icon"></i>
        <span class="menu-title">Orders</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="vouchers.html">
        <i class="mdi mdi-ticket-outline menu-icon"></i>
        <span class="menu-title">Vouchers</span>
      </a>
    </li>

    <li class="nav-item nav-category">My Settings</li>
    <li class="nav-item">
      <a class="nav-link" href="user.html">
        <i class="mdi mdi-account-star menu-icon"></i>
        <span class="menu-title">Profile Details</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="user.html?security">
        <i class="mdi mdi-security menu-icon"></i>
        <span class="menu-title">Security Settings</span>
      </a>
    </li>

    <?php if($admin_role):?>
    <li class="nav-item nav-category">Host Panel</li>
    <li class="nav-item">
      <a class="nav-link" href="user.html">
        <i class="mdi mdi-grid-large menu-icon"></i>
        <span class="menu-title">Back to Dashboard</span>
      </a>
    </li>
    <?php endif;?>

    <li class="nav-item">
      <a class="nav-link" href="signin.html?logout">
        <i class="menu-icon mdi mdi-power"></i>
        <span class="menu-title">Sign Out</span>
      </a>
    </li>
  </ul>
</nav>