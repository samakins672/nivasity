<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$manual_query = 0;

$link_to = "signin.html";

if (isset($_SESSION['nivas_userId'])) {
  $link_to = "store.php";
  if ($is_admin_role) {
    $link_to = "admin/";
  }
  // Simulate adding/removing the product to/from the cart
  if (!isset($_SESSION["nivas_cart$user_id"])) {
    $_SESSION["nivas_cart$user_id"] = array();
  }
  if (!isset($_SESSION["nivas_cart_event$user_id"])) {
    $_SESSION["nivas_cart_event$user_id"] = array();
  }
  $total_cart_items = count($_SESSION["nivas_cart$user_id"]) + count($_SESSION["nivas_cart_event$user_id"]);

  $manual_query = mysqli_query($conn, "SELECT * FROM manuals WHERE dept = $user_dept AND status = 'open' AND school_id = $school_id ORDER BY `id` DESC");
}

$event_query = mysqli_query($conn, "SELECT * FROM events WHERE status = 'open' ORDER BY `event_date` DESC LIMIT 7");
$event_query2 = mysqli_query($conn, "SELECT * FROM events WHERE status = 'open' ORDER BY `id` DESC LIMIT 3");

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Nivasity Web Services</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Favicons -->
  <link href="favicon.ico" rel="icon">
  <link href="logo.png" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Inter:wght@100;200;300;400;500;600;700;800;900&family=Nunito:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
    rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/css/mdb.css" rel="stylesheet" />
  <link href="assets/vendors/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendors/aos/aos.css" rel="stylesheet">
  <link href="assets/vendors/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendors/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="assets/vendors/select2/select2.min.css" rel="stylesheet" />

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-30QJ6DSHBN"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());

    gtag('config', 'G-30QJ6DSHBN');
  </script>
  
  
</head>

<body class="index-page">

  <header id="header" class="header d-flex flex-column align-items-center fixed-top">
    <div class="container-fluid position-relative d-flex align-items-center justify-content-around">

      <a href="/" class="logo d-flex align-items-center">
        <img src="assets/images/nivasity-main.png" alt="">
      </a>

      <nav id="navmenu" class="navmenu">
        <ul>
          <li class="me-md-4 mx-3 mb-md-0 mb-3 d-md-block d-none">
            <div class="input-group input-group-lg search_input">
              <input type="text" class="form-control rounded-pill" name="q"
                placeholder="Search for materials, or events...">
            </div>
          </li>
          <li><a href="#hero" class="active">Home</a></li>
          <li><a href="#manuals">Browse Materials</a></li>
          <li><a href="#events">Find Events</a></li>
          <li><a href="<?php echo $link_to ?>">Sell on Nivasity</a></li>
          <li class="dropdown">
            <a href="javascript:;"><span>More</span>
              <i class="bi bi-chevron-down toggle-dropdown"></i>
            </a>
            <ul>
              <li><a href="#partners">Our Partners</a></li>
              <li><a href="#faq">FAQs</a></li>
            </ul>
          </li>
        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
      </nav>

      <a class="btn-getstarted" href="<?php echo $link_to ?>">Get Started</a>

    </div>
    <div class="container-fluid d-md-none d-flex align-items-center pt-3">
      <div class="input-group input-group-lg search_input">
        <input type="text" class="form-control rounded-pill" name="q" placeholder="Search for materials, or events...">
      </div>
    </div>
  </header>

  <main class="main">

    <!-- Hero Section -->
    <section id="hero" class="hero section flex-column">
      <div class="hero-bg">
        <img src="assets/images/background.jpg" alt="">
      </div>
      <div class="container text-center">
        <div id="carouselExampleIndicators" class="carousel slide w-100" data-bs-ride="carousel">
          <div class="carousel-indicators">
            <button class="btn btn-sm me-2 p-2 rounded-circle active" data-bs-target="#carouselExampleIndicators"
              data-bs-slide-to="0" aria-current="true" aria-label="Slide 1"></button>
            <!-- <button class="btn btn-sm me-2 p-2 rounded-circle" data-bs-target="#carouselExampleIndicators"
              data-bs-slide-to="1" aria-label="Slide 2"></button>
            <button class="btn btn-sm me-2 p-2 rounded-circle" data-bs-target="#carouselExampleIndicators"
              data-bs-slide-to="2" aria-label="Slide 3"></button> -->
          </div>
          <div class="carousel-inner">
            <div class="carousel-item active">
            <?php
            // while ($event = mysqli_fetch_array($event_query2)) {
            //     $event_id = $event['id'];
            //     $seller_id = $event['user_id'];
            ?>
              <img src="assets/images/banner-1.jpg" class="d-block rounded-7 w-100" alt="...">
            </div>
            <!-- <div class="carousel-item">
              <img src="assets/images/events/image.png" class="d-block rounded-7 w-100" alt="...">
            </div>
            <div class="carousel-item">
              <img src="assets/images/events/image.png" class="d-block rounded-7 w-100" alt="...">
            </div> -->
            <?php 
          // } ?>
          </div>
          <button class="carousel-control-prev fs-2 text-primary" type="button"
            data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
            <i class="bi bi-arrow-left-circle" aria-hidden="true"></i>
          </button>
          <button class="carousel-control-next fs-2 text-primary" type="button"
            data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
            <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
          </button>
        </div>
      </div>

    </section><!-- /Hero Section -->

    <!-- Featured Services Section -->
    <section id="featured-services" class="featured-services section light-background">

      <div class="container">

        <div class="row gy-4">

          <div class="col-xl-4 col-lg-6" data-aos="fade-up" data-aos-delay="100">
            <div class="service-item d-flex">
              <div class="icon rounded-circle flex-shrink-0"><i class="bi bi-person-rolodex"></i></div>
              <div>
                <h4 class="title"><a class="stretched-link">Are you a Student?</a></h4>
                <p class="description">Discover and purchase study materials tailored to your school and department.
                  Simplify your academic journey with resources created by your peers.</p>
              </div>
            </div>
          </div>
          <!-- End Service Item -->

          <div class="col-xl-4 col-lg-6" data-aos="fade-up" data-aos-delay="200">
            <div class="service-item d-flex">
              <div class="icon rounded-circle flex-shrink-0"><i class="bi bi-person-badge-fill"></i></div>
              <div>
                <h4 class="title"><a class="stretched-link">Are you an HOC or Lecturer?</a></h4>
                <p class="description">Easily upload and sell your course materials to help fellow students. Reach more
                  learners and earn as you share valuable knowledge.</p>
              </div>
            </div>
          </div>
          <!-- End Service Item -->

          <div class="col-xl-4 col-lg-6" data-aos="fade-up" data-aos-delay="300">
            <div class="service-item d-flex">
              <div class="icon rounded-circle flex-shrink-0"><i class="bi bi-person-vcard-fill"></i></div>
              <div>
                <h4 class="title"><a class="stretched-link">Planning an Event?</a></h4>
                <p class="description">Host your next event on Nivasity! Reach students with ease and make ticketing
                  simple for both organizers and attendees.</p>
              </div>
            </div>
          </div>
          <!-- End Service Item -->

        </div>

      </div>

    </section><!-- /Featured Services Section -->

    <!-- Manual Section -->
    <section id="manuals" class="manuals section pb-2">
      <!-- Section Title -->
      <div class="container section-title" data-aos="fade-up">
        <h2 class="pb-2">Course Materials/Manuals</h2>
      </div><!-- End Section Title -->

      <div class="container mb-3">
        <div class="row gy-4 justify-content-between features-item">
          <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
            <img src="assets/images/dashboard/banner-1.png" class="img-fluid"
              alt="Students studying with course materials">
          </div>

          <div class="col-lg-6 d-flex align-items-center" data-aos="fade-up" data-aos-delay="200">
            <div class="content">
              <h3>Get the Right Course Materials at Your Fingertips</h3>
              <p>
                Nivasity connects students with essential course materials curated by HOC or Lecturers, making studying easier and
                more targeted for every school and department.
              </p>
              <ul>
                <li><i class="bi bi-book-half"></i> <strong>Wide Selection:</strong> Access a variety of course
                  materials across different subjects and courses.</li>
                <li><i class="bi bi-search"></i> <strong>Easy to Find:</strong> Materials are filtered by school,
                  department, or course to find what you need instantly.</li>
                <li><i class="bi bi-cash-coin"></i> <strong>Affordable and Reliable:</strong> Quality materials from
                  trusted HOC or Lecturers, priced for student budgets.</li>
              </ul>
            </div>
          </div>
        </div><!-- Features Item -->
      </div>


      <div class="container pt-5">
        <h4 class="fw-bold">Recently Posted Materials</h4>
        <div class="d-flex overflow-auto py-3">
          <?php
          if ($manual_query !== 0) {
          if (mysqli_num_rows($manual_query) > 0) {
            $count_row = mysqli_num_rows($manual_query);

            while ($manual = mysqli_fetch_array($manual_query)) {
              $manual_id = $manual['id'];
              $seller_id = $manual['user_id'];

              // Check if the manual has been bought by the current user
              $is_bought_query = mysqli_query($conn, "SELECT COUNT(*) AS count FROM manuals_bought WHERE manual_id = $manual_id AND buyer = $user_id AND school_id = $school_id");
              $is_bought_result = mysqli_fetch_assoc($is_bought_query);

              // If the manual has been bought, skip it
              if ($is_bought_result['count'] > 0) {
                $count_row = $count_row - 1;
                continue;
              }

              $seller_q = mysqli_fetch_array(mysqli_query($conn, "SELECT first_name, last_name FROM users WHERE id = $seller_id"));
              $seller_fn = $seller_q['first_name'];
              $seller_ln = $seller_q['last_name'];

              // Retrieve and format the due date
              $due_date = date('j M, Y', strtotime($manual['due_date']));
              $due_date2 = date('Y-m-d', strtotime($manual['due_date']));
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

              ?>
                    <div class="card card-rounded border border-1 border-secondary shadow-sm m-2 w-md-25 min-w-75">
                      <div class="card-body">
                        <h6 class="card-title"><?php echo $manual['title'] ?> <span class="text-secondary">- <?php echo $manual['course_code'] ?></span></h6>
                        <div class="d-flex">
                          <i class="bi bi-journal-bookmark-fill fs-1 text-secondary d-flex align-self-start me-3"></i>
                          <div class="media-body">
                            <h4 class="fw-bold price">₦ <?php echo number_format($manual['price']) ?></h4>
                            <p class="card-text">
                              Due date:<span class="fw-bold text-<?php echo $status_c ?> due_date"> <?php echo $due_date ?></span><br>
                              <span class="text-secondary"><?php echo $seller_fn . ' ' . $seller_ln ?> (HOC or Lecturer)</span>
                            </p>
                          </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                          <a href="javascript:;">
                            <i class="bi bi-share-fill fs-3 text-muted share_button" data-title="<?php echo $manual['title']; ?>" data-product_id="<?php echo $manual['id']; ?>" data-type="product"></i>
                          </a>
                          <button class="btn btn-outline-primary m-0 cart-button" data-product-id="<?php echo $manual_id ?>"
                            data-mdb-ripple-duration="0"> Buy now
                          </button>
                        </div>
                      </div>
                    </div>

                  <?php
            } ?>

            <?php 
            if ($count_row == 0) { ?>
              <div class="col-12">
                <div class="card card-rounded shadow-sm">
                  <div class="card-body">
                    <h5 class="card-title">All manuals have been bought</h5>
                    <p class="card-text">Check back later when your HOC or Lecturer uploads a new manual.</p>
                  </div>
                </div>
              </div>
            <?php } else { ?>

            <a href="<?php echo $link_to ?>" class="card card-rounded border border-1 border-secondary shadow-sm m-2 w-md-25 min-w-75">
              <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <i class="fs-4 text-secondary fw-bold bi bi-box-arrow-up-right"></i>
                <h5 class="fw-bold text-secondary">See More</h5>
              </div>
            </a>

            <?php } } else{ ?>
              <div class="col-12">
                <div class="card card-rounded shadow-sm">
                  <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <h5 class="card-title fw-bold">Opps!</h5>
                    <p class="card-text">Only students can view available manuals!</p>
                  </div>
                </div>
              </div>
            <?php }} else { ?>
              <div class="col-12">
                <div class="card card-rounded shadow-sm">
                  <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <h5 class="card-title fw-bold">Let's make it personal</h5>
                    <p class="card-text">Log in and we'll find manuals available for you!</p>
                    <a class="btn btn-primary" href="signin.html">Get Started</a>
                  </div>
                </div>
              </div>
            <?php } ?>

          <!-- Add more cards as needed -->
        </div>
      </div>

    </section>
    <!-- /Manual Section -->

    <!-- About Section -->
    <section id="events" class="about section mb-5">
      <!-- Section Title -->
      <div class="container section-title" data-aos="fade-up">
        <h2 class="pb-2">Events Booking</h2>
      </div>
      <!-- End Section Title -->

      <div class="container mb-3">
        <div class="row gy-4 justify-content-between features-item">
          <div class="col-lg-6 d-flex align-items-center" data-aos="fade-up" data-aos-delay="200">
            <div class="content">
              <h3>Discover Events and Get Your Tickets Hassle-Free</h3>
              <p>
                Nivasity connects you with campus events and beyond, making it easy to find, book, and enjoy events
                relevant to you.
              </p>
              <ul>
                <li><i class="bi bi-calendar-event flex-shrink-0"></i> <strong>Wide Range of Events:</strong> From
                  social gatherings to educational workshops, find events that matter.</li>
                <li><i class="bi bi-cart-check flex-shrink-0"></i> <strong>Seamless Booking:</strong> Secure your spot
                  in a few clicks with student-friendly prices.</li>
                <li><i class="bi bi-person-lines-fill flex-shrink-0"></i> <strong>Personalized Experience:</strong> See
                  events tailored to your school, interests, and preferences.</li>
              </ul>
            </div>
          </div>

          <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
            <img src="assets/images/dashboard/banner-2.png" class="img-fluid rounded-6"
              alt="Event tickets available on Nivasity">
          </div>
        </div><!-- Features Item -->
      </div>

      <div class="container pt-5">
        <h4 class="fw-bold">Upcoming Events</h4>
        <div class="row flex-grow g-3 sortables mt-1">
          <?php
            if (mysqli_num_rows($event_query) > 0) {
              $count_row = mysqli_num_rows($event_query);

              while ($event = mysqli_fetch_array($event_query)) {
                $event_id = $event['id'];
                $seller_id = $event['user_id'];

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

                $event_price = number_format($event['price']);
                $event_price = $event_price > 0 ? "₦ $event_price" : 'FREE';

                ?>
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3 grid-margin px-2 stretch-card text-dark">
                      <div class="card card-rounded border border-1 border-secondary shadow-sm">
                        <div class="card-body p-0">
                          <img src="assets/images/events/<?php echo $event['event_banner'] ?>" class="img-fluid rounded-top w-100" style="max-height: 140px; object-fit: cover;">
                          <div class="p-3">
                            <p class="fw-bold text-secondary mb-1"><i class="bi bi-geo-alt-fill"></i> <?php echo $location ?></p>
                            <h6 class="fw-bold text-uppercase"><?php echo $event['title'] ?></h6>
                            <small class="fw-bold"><?php echo $event_date ?> • <?php echo $event_time ?></small><br>
                            <small class="badge badge-success fw-bold text-uppercase mt-2"><?php echo $event_price ?></small>
                            <p>Host: <span class="fw-bold text-secondary"><?php echo $organisation['business_name'] ?></span></p>
                            <hr>
                            <div class="d-flex justify-content-between">
                              <a href="javascript:;">
                                <i class="bi bi-share-fill fs-3 text-muted share_button" data-title="<?php echo $event['title']; ?>" data-product_id="<?php echo $event['id']; ?>" data-type="event"></i>
                              </a>
                              <button class="btn btn-outline-primary m-0 cart-event-button" data-event-id="<?php echo $event['id'] ?>" data-mdb-ripple-duration="0">Get Ticket</button>
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
                  <?php } else { ?>

          <a href="<?php echo $link_to ?>" class="col-12 col-md-6 col-lg-4 col-xl-3 grid-margin px-2 stretch-card text-dark">
            <div class="card card-rounded border border-1 border-secondary shadow-sm h-100">
              <div class="card-body d-flex flex-md-column align-items-center justify-content-center p-2">
                <i class="fs-4 text-secondary fw-bold bi bi-box-arrow-up-right me-3"></i>
                <h5 class="fw-bold text-secondary m-0">See More</h5>
              </div>
            </div>
          </a>
            <?php } } else {
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

    </section>
    <!-- /About Section -->

    <!-- partners Section -->
    <section id="partners" class="partners section light-background">
      <!-- Section Title -->
      <div class="container section-title" data-aos="fade-up">
        <h2 class="pb-2">Our Partners</h2>
      </div>
      <!-- End Section Title -->

      <div class="container" data-aos="fade-up">

        <div class="row gy-4">

          <a href="https://flutterwave.com" target="_blank" class="col-md-2 col-4 client-logo text-center">
            <img src="assets/images/partners/Flutterwave_Logo.png" class="img-fluid w-25" alt="">
          </a><!-- End Client Item -->

          <a href="https://sannex.ng" target="_blank" class="col-md-2 col-4 client-logo text-center">
            <img src="assets/images/partners/logo.png" class="img-fluid w-25" alt="">
          </a><!-- End Client Item -->

          <a href="https://moniepoint.com" target="_blank" class="col-md-2 col-4 client-logo text-center">
            <img src="assets/images/partners/Moniepoint_logo.jpg" class="img-fluid w-25" alt="">
          </a><!-- End Client Item -->

          <a href="https://paystack.com" target="_blank" class="col-md-2 col-4 client-logo text-center">
            <img src="assets/images/partners/Paystack_Logo.png" class="img-fluid w-25" alt="">
          </a><!-- End Client Item -->

          <a href="https://drive.google.com" target="_blank" class="col-md-2 col-4 client-logo text-center">
            <img src="assets/images/partners/google_drive.png" class="img-fluid w-25" alt="">
          </a><!-- End Client Item -->

          <a href="https://goldelitedeals.com" target="_blank" class="col-md-2 col-4 client-logo text-center">
            <img src="assets/images/partners/gold_elite.png" class="img-fluid w-25" alt="">
          </a><!-- End Client Item -->

        </div>

      </div>

    </section>
    <!-- /partners Section -->

    <!-- Faq Section -->
    <section id="faq" class="faq section">

      <!-- Section Title -->
      <div class="container section-title" data-aos="fade-up">
        <h2>Frequently Asked Questions</h2>
      </div><!-- End Section Title -->

      <div class="container">

        <div class="row justify-content-center">

          <div class="col-lg-10" data-aos="fade-up" data-aos-delay="100">
            <div class="faq-container">

              <div class="faq-item faq-active">
                <h3>What is Nivasity?</h3>
                <div class="faq-content">
                  <p>Nivasity is an online platform that allows students to buy academic materials from Heads of Courses
                    (HOC) or Lecturer and purchase event tickets from various organizers.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div><!-- End Faq item-->

              <div class="faq-item">
                <h3>How do I create an account on Nivasity?</h3>
                <div class="faq-content">
                  <p>To create an account, click on the "Sign Up" button on the homepage and fill in the required
                    information, including your email, password, and other relevant details.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div><!-- End Faq item-->

              <div class="faq-item">
                <h3>Can I access course materials without logging in?</h3>
                <div class="faq-content">
                  <p>No, you need to log in or create an account to access course materials filtered by your school and
                    department.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div><!-- End Faq item-->

              <div class="faq-item">
                <h3>What types of course materials are available on the platform?</h3>
                <div class="faq-content">
                  <p>We offer a wide range of course materials covering different subjects and courses, all provided by
                    trusted
                    Heads of Courses (HOC) or Lecturers.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div><!-- End Faq item-->

              <div class="faq-item">
                <h3>How can I purchase a course material?</h3>
                <div class="faq-content">
                  <p>Once you find the course material you need, simply click on the "Add to Cart" button, proceed to
                    checkout,
                    and follow the payment instructions.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div><!-- End Faq item-->

              <div class="faq-item">
                <h3>Are the course materials affordable?</h3>
                <div class="faq-content">
                  <p>Yes, we aim to provide quality materials at student-friendly prices, making it easier for you to
                    access necessary resources.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div><!-- End Faq item-->

              <div class="faq-item">
                <h3>What types of events can I find tickets for?</h3>
                <div class="faq-content">
                  <p>You can find tickets for school events, online events, and public events, all listed on our
                    platform for easy access.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div><!-- End Faq item-->

              <div class="faq-item">
                <h3>How do I purchase an event ticket?</h3>
                <div class="faq-content">
                  <p>Navigate to the event listing, click on the "Buy Ticket" button, and follow the checkout process to
                    secure your ticket.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div><!-- End Faq item-->

              <div class="faq-item">
                <h3>Can I get a refund if I can't attend an event?</h3>
                <div class="faq-content">
                  <p>Refund policies vary by event organizer. Please check the specific event details or contact
                    customer support for assistance regarding refunds.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div><!-- End Faq item-->

              <div class="faq-item">
                <h3>How can I contact customer support?</h3>
                <div class="faq-content">
                  <p>If you have any questions or issues, you can reach our customer support team through the "Contact
                    Us" page on our website, and we will assist you as soon as possible.</p>
                </div>
                <i class="faq-toggle bi bi-chevron-right"></i>
              </div><!-- End Faq item-->

            </div>


          </div><!-- End Faq Column-->

        </div>

      </div>

    </section>
    <!-- /Faq Section -->

  </main>

  <footer id="footer" class="footer position-relative light-background">

    <div class="container footer-top">
      <div class="row gy-4">
        <div class="col-lg-4 col-md-6 footer-about">
          <a href="" class="logo d-flex align-items-center">
            <img src="assets/images/nivasity-main.png" alt="nivasity">
          </a>
          <div class="footer-contact">
            <p>The leading secure and convenient platform for purchasing</p>
            <p>Course materials, school event tickets, and much more</p>
          </div>

        </div>

        <div class="col-md-3 footer-links">
          <h4>Useful Links</h4>
          <ul>
            <li><a href="t&c.html">Terms and Conditions</a></li>
            <li><a href="p&p.html">Privacy and Policy</a></li>
            <li><a href="sitemap.xml">Sitemap</a></li>
          </ul>
        </div>
        
        <div class="col-md-3 footer-links">
          <h4>Our Services</h4>
          <ul>
            <li><a>Course Materials</a></li>
            <li><a>Event Tickets</a></li>
            <li><a>Cloud Storage</a></li>
          </ul>
        </div>

        <div class="col-md-2 footer-links">
          <h4>Contact Us</h4>
          <ul>
            <li>
              <a href="https://x.com/nivasity" target="_blank"><i class="bi bi-twitter-x fs-4 pe-3"></i></a>
              <a href="https://facebook.com/nivasity" target="_blank"><i class="bi bi-facebook fs-4 pe-3"></i></a>
              <a href="https://instagram.com/nivasity" target="_blank"><i class="bi bi-instagram fs-4 pe-3"></i></a>
              <a href="https://linkedin.com/company/nivasity" target="_blank"><i class="bi bi-linkedin fs-4"></i></a>
            </li>
            <li class="d-block">
              <span><strong>Phone:</strong> +234 814 891 9310</span> <br>
               <a href="mailto:support@nivasity.com" class="text-dark d-block"><strong>Email:</strong> support@nivasity.com</a>
            </li>
          </ul>
        </div>

        <!-- <div class="col-lg-3 col-md-12 footer-newsletter">
          <h4>Our Newsletter</h4>
          <p>Subscribe to our newsletter and receive the latest news about our products and services!</p>
          <form action="forms/newsletter.php" method="post" class="php-email-form">
            <div class="newsletter-form"><input type="email" name="email"><input type="submit" value="Subscribe"></div>
            <div class="loading">Loading</div>
            <div class="error-message"></div>
            <div class="sent-message">Your subscription request has been sent. Thank you!</div>
          </form>
        </div> -->

      </div>
    </div>

    <div class="container copyright text-center mt-4">
      <p>© <span>Copyright <?php echo date('Y')?> </span> <strong class="px-1 sitename">Nivasity</strong><span>All Rights Reserved</span>
      </p>
    </div>

  </footer>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i
      class="bi bi-arrow-up-short"></i></a>

  <!-- Preloader -->
  <div id="preloader"></div>

  <!-- Vendor JS Files -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="assets/vendors/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendors/glightbox/js/glightbox.min.js"></script>
  <script src="assets/vendors/aos/aos.js"></script>
  <script src="assets/vendors/swiper/swiper-bundle.min.js"></script>
  <script src="assets/vendors/select2/select2.min.js"></script>

  <!-- Main JS File -->
  <script src="assets/js/home-main.js"></script>
  <!--Start of Tawk.to Script-->
  <script type="text/javascript">
    var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
    (function(){
    var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
    s1.async=true;
    s1.src='https://embed.tawk.to/6722bbbb4304e3196adae0cd/1ibfqqm4s';
    s1.charset='UTF-8';
    s1.setAttribute('crossorigin','*');
    s0.parentNode.insertBefore(s1,s0);
    })();
  </script>
  <!--End of Tawk.to Script-->
  <script>
    $(document).ready(function () {
      $('.searchable-select').select2();
      
      $(document).on('click', '.share_button', function (e) {
        var button = $(this);
        var product_id = button.data('product_id');
        var type = button.data('type');
        var title = button.data('title');
        var shareText = 'Check out '+title+' on nivasity and order now!';
        var shareUrl = "https://nivasity.com/model/cart_guest.php?share=1&action=1&type="+type+"&product_id="+product_id;

        // Check if the Web Share API is available
        if (navigator.share) {
          navigator.share({
            title: document.title,
            text: shareText,
            url: shareUrl,
          })
            .then(() => console.log('Shared successfully'))
            .catch((error) => console.error('Error sharing:', error));
        } else {
          // Fallback for platforms that do not support Web Share API
          // You can add specific share URLs for each platform here
          alert('Web Share API not supported. You can manually share the link.');
        }
      });
      
      // Add to Cart button click event
      $('.cart-button').on('click', function () {
        var button = $(this);
        var product_id = button.data('product-id');

        // Make AJAX request to PHP file
        $.ajax({
          type: 'GET',
          url: 'model/cart_guest.php', // Replace with your PHP file handling the cart logic
          data: { type: 'product', product_id: product_id, action: 1 },
          success: function (data) {
            window.location.href = "store.php?cart=1";
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

        // Make AJAX request to PHP file
        $.ajax({
          type: 'GET',
          url: 'model/cart_guest.php', // Replace with your PHP file handling the cart logic
          data: { type: 'event', product_id: event_id, action: 1 },
          success: function (data) {
            if (data.active = 1) {
              window.location.href = "store.php?cart=1";
            } else {
              window.location.href = "signin.html";
            }
          },
          error: function () {
            // Handle error
            console.error('Error in AJAX request');
          }
        });
      });

    });
  </script>

</body>

</html>