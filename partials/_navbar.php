<nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
  <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
    <div class="ms-md-0 ms-2">
      <a class="navbar-brand brand-logo" href="https://funaab.nivasity.com">
        <img src="https://funaab.nivasity.com/assets/images/nivasity-main.png" alt="logo" />
      </a>
      <a class="navbar-brand brand-logo-mini" href="https://funaab.nivasity.com">
        <img src="https://funaab.nivasity.com/assets/images/nivasity-logo-tr.png" alt="logo" />
      </a>
    </div>
  </div>
  <div class="navbar-menu-wrapper d-flex align-items-top">
    <ul class="navbar-nav">
      <li class="nav-item font-weight-semibold d-none d-lg-block ms-0">
        <?php
        $currentHour = date('G');

        if ($currentHour >= 5 && $currentHour < 12) {
          $greeting = 'Good morning';
        } elseif ($currentHour >= 12 && $currentHour < 18) {
          $greeting = 'Good afternoon';
        } else {
          $greeting = 'Good evening';
        }
        ?>
        <h1 class="welcome-text"><?php echo $greeting ?>, <span class="text-black fw-bold"><?php echo $f_name ?></span></h1>
      </li>
    </ul>
    <ul class="navbar-nav ms-auto">
      <li class="nav-item">
        <form class="search-form" action="#">
          <i class="icon-search"></i>
          <input type="search" class="form-control" placeholder="Search Here" title="Search here">
        </form>
      </li>
      <?php if($url == 'store.php'):?>
      <li class="nav-item dropdown d-md-none d-inline">
        <a class="nav-link count-indicator mt-2 go-to-cart-button" data-bs-toggle="tab" href="javascript:;">
          <i class="mdi mdi-cart-outline"></i>
          <span class="count"></span>
        </a>
      </li>
      <?php endif;?>
      <li class="nav-item dropdown d-none d-lg-block user-dropdown">
        <a class="nav-link" id="UserDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
          <img class="img-xs rounded rounded-7" src="https://funaab.nivasity.com/assets/images/users/<?php echo $user_image?>" alt="Profile image"> </a>
        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown">
          <div class="dropdown-header text-center">
            <img class="img-sm img-fluid rounded rounded-7" src="https://funaab.nivasity.com/assets/images/users/<?php echo $user_image?>" alt="Profile image">
            <span class="mb-1 mt-1 fw-bold d-block">
              <?php echo $user_name?><br>
              <?php if ($_SESSION['nivas_userRole'] == 'student'): ?>
                <small class="text-secondary">(Student)</small>
              <?php elseif ($_SESSION['nivas_userRole'] == 'hoc'): ?>
                <small class="text-secondary">(HOC)</small>
              <?php elseif ($_SESSION['nivas_userRole'] == 'org_admin'): ?>
                <small class="text-secondary">(Event Host)</small>
              <?php else: ?>
                <small class="text-secondary">(Public User)</small>
              <?php endif; ?>
            </span>
          </div>
          <!-- <a class="dropdown-item" href="https://funaab.nivasity.com/user.php"><i
              class="dropdown-item-icon mdi mdi-account-outline text-primary me-2"></i> My
            Profile</a>
          <a class="dropdown-item" href="https://funaab.nivasity.com/faq.html"><i
              class="dropdown-item-icon mdi mdi-help-circle-outline text-primary me-2"></i>
            FAQ</a> -->
          <a class="dropdown-item" href="https://funaab.nivasity.com/signin.html?logout=1"><i
              class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>Sign Out</a>
        </div>
      </li>
    </ul>
    <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center mt-2" type="button"
      data-bs-toggle="offcanvas">
      <span class="mdi mdi-menu"></span>
    </button>
  </div>
</nav>