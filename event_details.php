<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$manual_query = 0;

$link_to = "signin.html";

if (isset($_SESSION['nivas_userId'])) {
  $link_to = "/";
  if ($is_admin_role) {
    $link_to = "admin/";
  }
}

// Check if event_id is passed in the URL
if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
  // Redirect to $link_to if event_id is not found
  header("Location: $link_to");
  exit();
} else {
  // Sanitize and retrieve the event_id
  $event_id = mysqli_real_escape_string($conn, $_GET['event_id']);
}

$event_query = mysqli_query($conn, "SELECT * FROM events WHERE id = $event_id");
$event_query2 = mysqli_query($conn, "SELECT * FROM events WHERE status = 'open' ORDER BY `id` DESC LIMIT 5");

// Fetch event details to populate Open Graph meta tags
if ($event_query && mysqli_num_rows($event_query) > 0) {
  $event = mysqli_fetch_array($event_query);
  $event_title = htmlspecialchars($event['title']);
  $event_description = htmlspecialchars($event['description'] ?: "Join us for an exciting event filled with opportunities to connect, learn, and explore. Don't miss out on this experience—more details coming soon!");
  $event_description = substr($event_description, 0, 150) . (strlen($event_description) > 150 ? '...' : ''); // Limit to 150 characters
  $event_image = "https://funaab.nivasity.com/assets/images/events/" . urlencode($event['event_banner']);
  $event_url = "https://funaab.nivasity.com/event_details.php?event_id=" . urlencode($event_id);
} else {
  // Redirect to $link_to if event is not found
  header("Location: $link_to");
  exit();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title><?php echo $event_title; ?> | Nivasity</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Open Graph Meta Tags -->
  <meta property="og:title" content="<?php echo $event_title; ?> | Nivasity">
  <meta property="og:description" content="<?php echo $event_description; ?>">
  <meta property="og:image" content="<?php echo $event_image; ?>">
  <meta property="og:url" content="<?php echo $event_url; ?>">
  <meta property="og:type" content="website">

  <!-- Twitter Meta Tags -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo $event_title; ?>">
  <meta name="twitter:description" content="<?php echo $event_description; ?>">
  <meta name="twitter:image" content="<?php echo $event_image; ?>">

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

  <!-- Copy Toast -->
  <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 11000;">
    <div id="copyToast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">Link copied to clipboard</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  </div>
  
  
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
      
      <div class="container my-3">
        <div class="row gy-4 justify-content-between features-item mb-md-5">
          <div class="col-lg-8 px-3" data-aos="fade-up" data-aos-delay="100">
            <?php 
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
                $location = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM schools WHERE id = ".$event['school']))['name'];
              } elseif ($event['event_type'] == 'public') {
                $location = $event['location'];
              } else {
                $location = "Online Event";
              }

              $event_price = number_format($event['price']);
              $event_price = $event_price > 0 ? "₦ $event_price" : 'FREE';
              
              $description = $event['description'] !== null 
                ? nl2br($event['description']) 
                : "Join us for an exciting event filled with opportunities to connect, learn, and explore. Don't miss out on this experience—more details coming soon!";
            ?>
            <img src="assets/images/events/<?php echo $event['event_banner'] ?>" class="img-fluid w-100 mb-4" style="max-height: 400px; object-fit: cover;"
              alt="<?php echo $event['title'] ?>">
            <p class="fw-bold text-secondary mb-1"><?php echo $event_date ?></p>
            <h1 class="fw-bold text-uppercase"><?php echo $event['title'] ?></h1>
            <p class="fw-bold text-muted mt-2 mb-4">
              <?php echo $description ?>
            </p>
            <h5 class="fw-bold">Date and Time</h5>
            <p class="fw-bold text-secondary mb-4"><i class="bi bi-calendar-check"></i> <?php echo $event_date ?> • <?php echo $event_time ?></p>
            <h5 class="fw-bold">Location</h5>
            <p class="fw-bold text-secondary mb-4"><i class="bi bi-geo-alt-fill"></i> <?php echo $location ?></p>
            
            <h5 class="fw-bold">Organised by</h5>
            <div class="light-background mb-md-5 d-flex align-items-center justify-content-start p-3 rounded-6">
              <img src="assets/images/users/<?php echo $seller_q['profile_pic'] ?>" class="img-fluid border border-3 border-primary rounded rounded-circle" width="50px"
                alt="nivasity_user_<?php echo $seller_fn ?>">
              <div class="ms-3 d-flex flex-column">
                <h6 class="fw-bold mb-0"><?php echo $organisation['business_name'] ?></h6>
                <small class="fw-bold text-primary"><i class="bi bi-patch-check-fill"></i> Verified</small>
              </div>
            </div>

          </div>

          <div class="col-lg-4 mb-md-5 p-md-0 p-3 mt-0" data-aos="fade-up" data-aos-delay="200">
            <div class="content">
              <div class="fs-5 text-center mb-3">
                Price: <small class="fw-bold text-uppercase"><?php echo $event_price ?></small>
              </div>
              
              <a href="/model/cart_guest.php?share=1&action=1&type=event&product_id=<?php echo $event_id ?>" class="btn btn-primary w-100">Get Ticket</a>
              <a class="btn btn-light w-100 mt-2 share_button" data-title="<?php echo $event['title']; ?>" data-product_id="<?php echo $event_id ?>">
                <i class="bi bi-share-fill pe-2"></i> Share Event
              </a>
            </div>
          </div>
        </div><!-- Features Item -->
      </div>

    </section>
    <!-- /Manual Section -->

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
      var myDiv = $(".content");
      var offsetTop = myDiv.offset().top; // The initial top offset of the div

      function toggleFixedPosition() {
        if ($(window).width() >= 768) {
          // For desktop/tablet view
          $(window).on("scroll", function () {
            if ($(this).scrollTop() > offsetTop) {
              // Apply fixed position and retain original width
              myDiv.css({
                position: "fixed",
                top: "130px",
                left: myDiv.offset().left + "px", // Retain original horizontal position
                width: myDiv.outerWidth() + "px", // Retain original width
              });
            } else {
              // Revert to static positioning
              myDiv.css({
                position: "static",
                width: "auto",
              });
            }
          });
        } else {
          // For mobile view
          $(window).off("scroll"); // Remove scroll event
          myDiv.css({
            position: "static",
            width: "auto",
          });
        }
      }

      // Adjust on page load and window resize
      toggleFixedPosition();
      $(window).resize(function () {
        // Recalculate the offset in case of layout changes
        offsetTop = myDiv.offset().top;
        toggleFixedPosition();
      });
      

      $('.searchable-select').select2();
      
      function showToast(message) {
        var el = document.getElementById('copyToast');
        if (!el || typeof bootstrap === 'undefined' || !bootstrap.Toast) { alert(message); return; }
        el.querySelector('.toast-body').textContent = message;
        var t = new bootstrap.Toast(el);
        t.show();
      }

      function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(function(){
            showToast('Link copied to clipboard');
          }, function(){
            var temp = $('<input>');
            $('body').append(temp);
            temp.val(text).select();
            document.execCommand('copy');
            temp.remove();
            showToast('Link copied to clipboard');
          });
        } else {
          var temp = $('<input>');
          $('body').append(temp);
          temp.val(text).select();
          document.execCommand('copy');
          temp.remove();
          showToast('Link copied to clipboard');
        }
      }

      function isMobileDevice() {
        return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
      }

      $(document).on('click', '.share_button', function (e) {
        var button = $(this);
        var product_id = button.data('product_id');
        var title = button.data('title');
        var shareText = 'Check out '+title+' on nivasity and order now!';
        var shareUrl = "https://funaab.nivasity.com/event_details.php?event_id="+product_id;

        if (isMobileDevice() && navigator.share) {
          navigator.share({ title: document.title, text: shareText, url: shareUrl })
            .catch(function(){ copyToClipboard(shareUrl); });
        } else { copyToClipboard(shareUrl); }
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
            window.location.href = "/?cart=1";
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
              window.location.href = "/?cart=1";
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
