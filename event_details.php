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

$event_query = mysqli_query($conn, "SELECT * FROM events WHERE id = 9");
$event_query2 = mysqli_query($conn, "SELECT * FROM events WHERE status = 'open' ORDER BY `id` DESC LIMIT 5");

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
          <li><a href="/#hero" class="active">Home</a></li>
          <li><a href="/#manuals">Browse Materials</a></li>
          <li><a href="/#events">Find Events</a></li>
          <li><a href="/<?php echo $link_to ?>">Sell on Nivasity</a></li>
          <li class="dropdown">
            <a href="javascript:;"><span>More</span>
              <i class="bi bi-chevron-down toggle-dropdown"></i>
            </a>
            <ul>
              <li><a href="/#partners">Our Partners</a></li>
              <li><a href="/#faq">FAQs</a></li>
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

  <main class="main pt-5">

    <!-- Manual Section -->
    <section id="manuals" class="manuals section pb-2">
      
      <div class="container mb-3">
        <div class="row gy-4 justify-content-between features-item">
          <div class="col-lg-8 px-5" data-aos="fade-up" data-aos-delay="100">
            <?php 
              $event = mysqli_fetch_array($event_query);
              $event_id = $event['id'];
              $seller_id = $event['user_id'];

              $seller_q = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $seller_id"));
              $organisation = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM organisation WHERE user_id = $seller_id"));
              $seller_fn = $seller_q['first_name'];
              $seller_ln = $seller_q['last_name'];

              // Retrieve and format the event_date and time
              $event_date = date('l, j F', strtotime($event['event_date']));
              $event_date2 = date('Y-m-d', strtotime($event['event_date']));
                    
              $event_time = date('g:i A', strtotime($event['event_time']));
              $event_time2 = date('H:i', strtotime($event['event_time']));

              // Retrieve the status
              $status = $event['status'];
              $status_c = 'success';

              if ($date > $event_date2) {
                $status = 'disabled';
                $status_c = 'danger';
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
            <img src="assets/images/events/<?php echo $event['event_banner'] ?>" class="img-fluid w-100 mb-4"
              alt="<?php echo $event['title'] ?>">
            <p class="fw-bold text-secondary mb-1"><?php echo $event_date ?></p>
            <h1 class="fw-bold text-uppercase"><?php echo $event['title'] ?></h1>
            <p class="fw-bold text-muted mt-2 mb-4">
              In a whimsical yet perilous kingdom, the tyrannical Evil Queen Janice and her daughters - Lazy Susan and the twins, Sam 'n Ella - have decreed that only baked beans are fit for consumption. Her iron-fisted rule has smothered the kingdom's culinary spirit, leaving its citizens craving a taste of freedom.
              <br><br>
              Enter Sorted Food and their brave culinary crew, who have hatched a daring plan to topple the Queen's tasteless regime. Their mission: infiltrate Janice's castle, navigate through a series of treacherous and thrilling cooking challenges, and reclaim the kingdom's gastronomic glory. Each chamber presents a new test, from devouring chocolate-dipped scorpions to mastering the art of elusive flavours.
              <br><br>
              But the team's journey is fraught with danger. Unbeknownst to them, Queen Janice has embedded a devious traitor within their ranks. This saboteur will stop at nothing to ensure their failure, undermining their efforts from within, all whilst trying to avoid detection by the team and on-looking community.
            </p>
            <h5 class="fw-bold">Date and Time</h5>
            <p class="fw-bold text-secondary mb-4"><i class="bi bi-calendar-check"></i> <?php echo $event_date ?> • <?php echo $event_time ?></p>
            <h5 class="fw-bold">Location</h5>
            <p class="fw-bold text-secondary mb-4"><i class="bi bi-geo-alt-fill"></i> <?php echo $location ?></p>
            
            <h5 class="fw-bold">Organised by</h5>
            <div class="light-background d-flex align-items-center justify-content-start p-3 rounded-6">
              <img src="assets/images/users/<?php echo $seller_q['profile_pic'] ?>" class="img-fluid rounded rounded-7" width="50px"
                alt="nivasity_user_<?php echo $seller_fn ?>">
              <div class="ms-3 d-flex lex-column align-items-center">
                <h6 class="fw-bold mb-0"><?php echo $organisation['business_name'] ?></h6>
                <small class="fw-bold text-muted">Verified</small>
              </div>
            </div>

          </div>

          <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
            <div class="content">
              <h3>Get the Right Course Materials at Your Fingertips</h3>
              <p>
                Nivasity connects students with essential course materials curated by HOC or Lecturers, making studying easier and
                more targeted for every school and department.
              </p>
              
              <buttton class="btn btn-primary w-100">Get Ticket</buttton>
              <buttton class="btn btn-light w-100 mt-2">Add to Cart</buttton>
            </div>
          </div>
        </div><!-- Features Item -->
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

      <div class="container pt-5">
        <h4 class="fw-bold">Upcoming Events</h4>
        <div class="row flex-grow g-3 sortables mt-1">
          <?php
            if (mysqli_num_rows($event_query2) > 0) {
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