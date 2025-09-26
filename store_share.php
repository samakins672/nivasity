<?php
session_start();
include('model/config.php');

$manual_id = isset($_GET['manual_id']) ? intval($_GET['manual_id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Material | Nivasity</title>

  <!-- Vendor CSS -->
  <link href="assets/vendors/mdi/css/materialdesignicons.min.css" rel="stylesheet">
  <link href="assets/vendors/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/mdb.css" rel="stylesheet" />
  <link href="assets/css/main.css" rel="stylesheet">
</head>
<body>
  <div class="container py-5">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <a href="/" class="d-flex align-items-center text-decoration-none">
        <img src="assets/images/nivasity-main.png" alt="Nivasity" style="height:36px" class="me-2">
      </a>
      <div>
        <a href="/" class="btn btn-sm btn-light">Home</a>
        <a href="/signin.html" class="btn btn-sm btn-primary">Sign in</a>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-1">Loading material...</h5>
        <p class="text-muted mb-0">Please wait while we fetch details.</p>
      </div>
    </div>
  </div>

  <!-- Modal container -->
  <div class="modal fade" id="manualModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <!-- dynamic content loads here via AJAX -->
      </div>
    </div>
  </div>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="assets/vendors/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script>
    $(function() {
      var manualId = <?php echo json_encode($manual_id); ?>;
      if (!manualId || manualId <= 0) {
        alert('Invalid material link.');
        return;
      }

      $.ajax({
        type: 'GET',
        url: 'model/manual_details.php',
        data: { manual_id: manualId, public: 1 },
        success: function (html) {
          $('#manualModal .modal-content').html(html);
          var modalEl = document.getElementById('manualModal');
          var modal = new bootstrap.Modal(modalEl);
          // Redirect to home if modal is closed (Cancel)
          modalEl.addEventListener('hidden.bs.modal', function(){
            window.location.href = '/';
          });
          modal.show();
        },
        error: function () {
          alert('Failed to load material details.');
        }
      });

      // Add to cart from modal (guest-friendly)
      $(document).on('click', '.cart-button', function (e) {
        var $btn = $(this);
        if ($btn.prop('disabled') || $btn.hasClass('disabled')) {
          e.preventDefault();
          return;
        }
        var product_id = $btn.data('product-id');
        if (!product_id) return;
        window.location.href = 'model/cart_guest.php?share=1&action=1&type=product&product_id=' + product_id;
      });
    });
  </script>
</body>
</html>
