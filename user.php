<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$academic_school_name = '';
$academic_departments = [];

if ($_SESSION['nivas_userRole'] !== 'org_admin' && $_SESSION['nivas_userRole'] !== 'visitor') {
  $school_query = mysqli_query($conn, "SELECT name FROM schools WHERE id = $school_id LIMIT 1");
  if ($school_query && mysqli_num_rows($school_query) === 1) {
    $academic_school_name = mysqli_fetch_assoc($school_query)['name'];
  }

  $departments_query = mysqli_query($conn, "SELECT id, name FROM depts WHERE school_id = $school_id AND status = 'active' ORDER BY name ASC");
  if ($departments_query) {
    while ($department = mysqli_fetch_assoc($departments_query)) {
      $academic_departments[] = $department;
    }
  }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>My Profile - Nivasity</title>
  
  <?php include('partials/_head.php') ?>
  <link rel="stylesheet" href="assets/vendors/select2/select2.min.css">
  <link rel="stylesheet" href="assets/vendors/select2-bootstrap-theme/select2-bootstrap.min.css">
  <style>
    .academic-select-wrap {
      margin-bottom: 1.5rem;
    }

    .select2-container--bootstrap {
      width: 100% !important;
    }

    .select2-container--bootstrap .select2-selection--single {
      min-height: calc(3rem + 2px);
      padding: 0.85rem 1rem;
      border: 1px solid #c9cdd4;
      border-radius: 0.5rem;
      display: flex;
      align-items: center;
    }

    .select2-container--bootstrap.select2-container--focus .select2-selection,
    .select2-container--bootstrap.select2-container--open .select2-selection {
      border-color: #ff9100;
      box-shadow: 0 0 0 0.2rem rgba(255, 145, 0, 0.2);
    }

    .select2-container--bootstrap .select2-selection__rendered {
      color: #212529;
      line-height: 1.5 !important;
      padding-left: 0 !important;
    }

    .select2-container--bootstrap .select2-selection__arrow {
      height: 100% !important;
      right: 0.9rem !important;
    }

    .select2-container--bootstrap .select2-results__option--highlighted[aria-selected],
    .select2-container--bootstrap .select2-results__option[aria-selected="true"] {
      background-color: #ff9100;
      color: #fff;
    }

    .select2-container--bootstrap .select2-dropdown {
      border-color: #ff9100;
    }

    .select2-container--bootstrap .select2-search--dropdown .select2-search__field:focus {
      border-color: #ff9100;
      box-shadow: 0 0 0 0.2rem rgba(255, 145, 0, 0.15);
      outline: 0;
    }
  </style>
</head>

<body>
  <div class="container-scroller">
    <!-- partial:partials/_navbar.php -->
    <?php include('partials/_navbar.php') ?>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_sidebar_user.php -->
      <?php include('partials/_sidebar_user.php') ?>
      <!-- partial -->
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-sm-12 px-2">
              <div class="home-tab">
                <div class="d-sm-flex align-items-center justify-content-start border-bottom">
                  <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                      <a class="nav-link px-3 active ps-0 fw-bold" id="home-tab" data-bs-toggle="tab" href="#account" role="tab"
                        aria-controls="account" aria-selected="true">Account</a>
                    </li>
                    <?php if ($_SESSION['nivas_userRole'] !== 'org_admin' && $_SESSION['nivas_userRole'] !== 'visitor'): ?>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold" id="contact-tab" data-bs-toggle="tab" href="#academics" role="tab"
                        aria-selected="false">Academic Info</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold" id="profile-tab" data-bs-toggle="tab" href="#security" role="tab"
                        aria-selected="false">Security</a>
                    </li>
                  </ul>
                </div>
                <div class="tab-content tab-content-basic">
                  <div class="tab-pane fade show active" id="account" role="tabpanel" aria-labelledby="account">
                    <div class="row">
                      <div class="col-12 mb-4">
                        <div class="card card-rounded p-3 px-2 shadow-sm">
                          <form id="profile-form" enctype="multipart/form-data">
                            <div class="card-header">
                              <h4 class="fw-bold">Profile Details</4>
                                <div class="d-sm-flex justify-content-start">
                                  <div class="square-img rounded rounded-10 shadow-sm my-3" style="width: 150px;">
                                    <img src="assets/images/users/<?php echo $user_image ?>" class="square-img-content" alt="Avatar" />
                                  </div>
                                  <div class="my-auto ms-3 d-inline">
                                    <input type="file" id="upload" name="upload" class="account-file-input" hidden=""
                                      accept="image/png, image/jpeg">
                                    <label for="upload" class="btn btn-primary fw-bold btn-lg btn-block">
                                      <span class="d-none d-md-block">Upload new photo</span>
                                      <i class="icon-upload d-md-none mx-2"></i>
                                    </label>
                                    <p class="text-muted mb-0">Allowed JPG or PNG. Max size of 800K</p>
                                  </div>
                                </div>
                            </div>
                            <div class="card-body">
                              <input type="hidden" name="edit_profile" value="1"/>
                              <div class="row">
                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="text" name="firstname" class="form-control form-control-lg w-100" value="<?php echo $f_name ?>"/>
                                    <label class="form-label" for="firstname">First Name</label>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="text" name="lastname" class="form-control form-control-lg w-100"
                                      value="<?php echo $l_name ?>"/>
                                    <label class="form-label" for="lastname">Last Name</label>
                                  </div>
                                </div>

                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="email" name="email" class="form-control form-control-lg bg-light w-100"
                                      value="<?php echo $user_email ?>" readonly />
                                    <label class="form-label" for="email">Email address</label>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  <div class="form-outline mb-3">
                                    <input type="number" name="phone" class="form-control form-control-lg w-100" value="<?php echo $user_phone ?>"
                                      required />
                                    <label class="form-label" for="phone">Phone Number</label>
                                  </div>
                                </div>

                              </div>
                              <!-- Save button -->
                              <button id="profile_submit" type="submit"
                                class="btn btn-primary fw-bold btn-lg btn-block mt-2">Save
                                Changes</button>

                            </div>
                          </form>
                        </div>
                      </div>
                      
                    </div>
                  </div>
                  <div class="tab-pane fade hide" id="security" role="tabpanel" aria-labelledby="security">
                    <div class="row">
                      <div class="col-12 mb-4">
                        <div class="card card-rounded p-3 px-2 shadow-sm">
                          <div class="card-header">
                            <h4 class="fw-bold">Change Password</4>
                          </div>
                          <div class="card-body">
                            <form id="password-form">
                              <input type="hidden" name="change_password" value="1"/>
                              <div class="row">
                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="password" name="curr_password" class="form-control form-control-lg w-100 passwords" required />
                                    <label class="form-label" for="curr_password">Curent Password</label>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                </div>

                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input id="password" type="password" name="new_password"
                                      class="form-control form-control-lg w-100 passwords"
                                      onkeyup="checkPasswordStrength()" required />
                                    <label class="form-label" for="new_password">New Password</label>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="password" name="new_password2"
                                      class="form-control form-control-lg w-100 passwords" 
                                      onkeyup="checkPasswordMatch()" required />
                                    <label class="form-label" for="new_password2">Confirm New Password</label>
                                  </div>
                                </div>
                              </div>
                              <div id="password-strength-status"></div>

                              <div class="form-group mb-3">
                                <div class="form-check">
                                  <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input toogle-password">Show
                                    Passwords</label>
                                </div>
                              </div>
                              <!-- Save button -->
                              <button id="password_submit" type="submit"
                                class="btn btn-primary fw-bold btn-lg btn-block mt-2" disabled>Save Changes</button>

                            </form>
                          </div>
                        </div>
                      </div>
                      <!-- <div class="col-12 mb-4">
                        <div class="card card-rounded p-3 px-2 shadow-sm">
                          <h4 class="card-header fw-bold pb-3">Two-steps Verification</h4>
                          <div class="card-body">
                            <h5 class="mb-3">Two factor authentication is not enabled yet.</h5>
                            <p>Two-factor authentication adds an additional layer of security to your
                              account by requiring more
                              than just a password to log in.
                              <a href="javascript:void(0);">Learn more.</a>
                            </p>
                            <button class="btn btn-primary btn-lg fw-bold mt-3"
                              data-bs-toggle="modal" data-bs-target="#enableOTP">Enable two-factor
                              authentication</button>
                          </div>
                        </div>
                      </div> -->
                      <div class="col-12">
                        <div class="card card-rounded p-3 px-2 shadow-sm">
                          <h4 class="card-header fw-bold pb-3">Delete Account</h4>
                          <div class="card-body">
                            <div class="mb-3 col-12 mb-0">
                              <div class="alert alert-danger">
                                <h6 class="alert-heading fw-medium mb-1">Are you sure you want to delete your account?
                                </h6>
                                <p class="mb-0">Once you delete your account, there is no going back. Please be certain.
                                </p>
                              </div>
                            </div>
                            <form id="acct_deactivation-form">
                              <input type="hidden" name="deactivate_acct" value="1"/>
                              <div class="form-outline mb-4">
                                <input type="password" name="password" class="form-control form-control-lg w-100" required />
                                <label class="form-label" for="password">Password</label>
                              </div>
                              <div class="form-group mb-3">
                                <div class="form-check">
                                  <label class="form-check-label">
                                    <input type="checkbox" class="form-check-input" required>I confirm my account
                                    deactivation</label>
                                </div>
                              </div>

                              <button type="submit" class="btn btn-danger deactivate-account btn-lg">Deactivate Account</button>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php if ($_SESSION['nivas_userRole'] !== 'org_admin' && $_SESSION['nivas_userRole'] !== 'visitor'): ?>
                  <div class="tab-pane fade hide" id="academics" role="tabpanel" aria-labelledby="academics">
                    <div class="row">
                      <div class="col-12">
                        <div class="card card-rounded p-3 px-2 shadow-sm">
                          <div class="card-header">
                            <h4 class="fw-bold">Academic Information</4>
                          </div>
                          <div class="card-body">
                            <form id="academic-info-form">
                              <input type="hidden" name="update_academic_info" value="1" />
                              <div class="row">
                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="text" id="new_institution"
                                      class="form-control form-control-lg w-100"
                                      value="<?php echo htmlspecialchars($academic_school_name) ?>" readonly />
                                    <label class="form-label" for="new_institution">Institution Name</label>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="text" id="new_matric_no" name="matric_no"
                                      class="form-control form-control-lg w-100" maxlength="25" value="<?php echo htmlspecialchars($user_matric_no) ?>" required />
                                    <label class="form-label" for="new_matric_no">Matric Number</label>
                                  </div>
                                </div>

                                <div class="col-md-6 academic-select-wrap">
                                  <label class="form-label" for="new_adm_year">Admission Year</label>
                                  <select id="new_adm_year" name="adm_year" class="form-control form-control-lg w-100 academic-select" required>
                                    <option value="" disabled <?php echo empty($user_adm_year) ? 'selected' : ''; ?>>Select admission year</option>
                                    <?php
                                    $admission_year_options = [];
                                    for ($year = ((int) date('Y')) + 1; $year >= 2019; $year--) {
                                      $value = ($year - 1) . '/' . $year;
                                      $admission_year_options[] = $value;
                                    }
                                    if (!empty($user_adm_year) && !in_array($user_adm_year, $admission_year_options, true)) {
                                      array_unshift($admission_year_options, $user_adm_year);
                                    }
                                    foreach ($admission_year_options as $admission_year_option):
                                    ?>
                                      <option value="<?php echo htmlspecialchars($admission_year_option); ?>" <?php echo $user_adm_year === $admission_year_option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($admission_year_option); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>

                                <div class="col-md-6 academic-select-wrap">
                                  <label class="form-label" for="new_department">Department</label>
                                  <select id="new_department" name="dept" class="form-control form-control-lg w-100 academic-select" required>
                                    <option value="" disabled <?php echo empty($user_dept) ? 'selected' : ''; ?>>Select department</option>
                                    <?php foreach ($academic_departments as $department): ?>
                                      <option value="<?php echo (int) $department['id']; ?>" <?php echo ((int) $user_dept === (int) $department['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['name']); ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                </div>
                              </div>
                              <button id="academic_info_submit" type="submit"
                                class="btn btn-primary fw-bold btn-lg btn-block mt-2">Save Changes</button>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php endif; ?>
                </div>
                
                <div class="modal fade" id="matricDuplicateModal" tabindex="-1" aria-labelledby="matricDuplicateLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="matricDuplicateLabel">Matric Number Already In Use</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <p id="matricDuplicateMessage" class="mb-0">
                          Another verified user already has this matric number. Update it before saving, or chat with Bella if the matric number belongs to you.
                        </p>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Close</button>
                        <a id="bellaMatricHelpLink" href="https://wa.me/2347052645530" target="_blank" rel="noopener"
                          class="btn btn-lg btn-success">Chat with Bella</a>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- Add New Manual Modal -->
                <div class="modal fade" id="addManual" tabindex="-1" role="dialog" aria-labelledby="addManualLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="addManualLabel">New Manual</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <form id="manual-form">
                        <input type="hidden" name="manual_id" value="0">
                        <div class="modal-body">
                          <div class="form-outline mb-4">
                            <input type="text" name="title" class="form-control form-control-lg w-100" required="">
                            <label class="form-label" for="title">Manual Title</label>
                          </div>
                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="text" name="course_code" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="course_code">Course Code</label>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="number" name="price" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="price">Unit Price</label>
                              </div>
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="number" name="quantity" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="quantity">Quantity</label>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="date" name="due_date" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="due_date">Due Date</label>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Cancel</button>
                          <button id="manual_submit" type="submit" class="btn btn-lg btn-primary">Submit</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
        <?php include('partials/_footer.php') ?>
        <!-- partial -->
      </div>
      <!-- Bootstrap alert container -->
      <div id="alertBanner"
        class="alert alert-info text-center fw-bold alert-dismissible end-2 top-2 fade show position-fixed w-auto p-2 px-4"
        role="alert" style="z-index: 5000; display: none;">
        An error occurred during the AJAX request.
      </div>
      <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->
  <!-- plugins:js -->
  <script src="assets/vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script src="assets/vendors/chart.js/Chart.min.js"></script>
  <script src="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
  <script src="assets/vendors/select2/select2.min.js"></script>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.1/mdb.min.js"></script>
  <script src="assets/vendors/progressbar.js/progressbar.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="assets/js/js/off-canvas.js"></script>
  <script src="assets/js/js/hoverable-collapse.js"></script>
  <script src="assets/js/js/template.js"></script>
  <script src="assets/js/js/settings.js"></script>
  <script src="assets/js/js/data-table.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="assets/js/js/dashboard.js"></script>
  <script src="assets/js/script.js"></script>
  <script>   
    // Fetch data from the JSON file
    $.getJSON('model/all-banks-NG.json', function(data) {
        var select = $('#bank');

        // Clear existing options
        select.empty();

        // Loop through the data and add options
        $.each(data.data, function(index, bank) {
            select.append('<option value="' + bank.code + '">' + bank.name + '</option>');
        });
    });
    
    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0');
      $('.academic-select').select2({
        width: '100%',
        theme: 'bootstrap'
      });
      
      $('#upload').on('change', function (event) {
        const file = event.target.files[0]; // Get the uploaded file
        if (file) {
          const reader = new FileReader();

          reader.onload = function (e) {
            $('.square-img-content').attr('src', e.target.result); // Set the src attribute of the image
          };

          reader.readAsDataURL(file); // Read the file as a data URL
        }
      });
      
      // toggle password visibility
      $('.toogle-password').on('click', function () {
        $(this).toggleClass('fa-eye-slash').toggleClass('fa-eye'); // toggle our classes for the eye icon
        var input = $('.passwords');

        if (input.attr("type") == "password") {
          input.attr("type", "text");
        } else {
          input.attr("type", "password");
        }
      });
      
      // Use AJAX to submit the profile form
      $('#password-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submission

        var button = $('#password_submit');
        var originalText = button.html();

        button.html(originalText + '  <div class="spinner-border text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        $.ajax({
          type: 'POST',
          url: 'model/user.php',
          data: $('#password-form').serialize(),
          success: function (data) {
            $('#alertBanner').html(data.message);

            if (data.status == 'success') {
              $('#alertBanner').removeClass('alert-info');
              $('#alertBanner').removeClass('alert-danger');
              $('#alertBanner').addClass('alert-success');

              setTimeout(function () {
                location.reload();
              }, 3000);
            } else {
              $('#alertBanner').removeClass('alert-success');
              $('#alertBanner').removeClass('alert-info');
              $('#alertBanner').addClass('alert-danger');
            }

            $('#alertBanner').fadeIn();

            setTimeout(function () {
                $('#alertBanner').fadeOut();
            }, 5000);

            button.html(originalText);
            button.prop("disabled", false);
          }
        });
      });

      // Use AJAX to submit the profile form
      $('#profile-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submission

        var button = $('#profile_submit');
        var originalText = button.html();

        button.html(originalText + '  <div class="spinner-border text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        var formData = new FormData($('#profile-form')[0]);

        $.ajax({
            type: 'POST',
            url: 'model/user.php',
            data: formData,
            contentType: false,
            processData: false,
            success: function (data) {
                $('#alertBanner').html(data.message);

                if (data.status == 'success') {
                    $('#alertBanner').removeClass('alert-info');
                    $('#alertBanner').removeClass('alert-danger');
                    $('#alertBanner').addClass('alert-success');
                } else {
                    $('#alertBanner').removeClass('alert-success');
                    $('#alertBanner').removeClass('alert-info');
                    $('#alertBanner').addClass('alert-danger');
                }

                $('#alertBanner').fadeIn();

                setTimeout(function () {
                    $('#alertBanner').fadeOut();
                }, 5000);

                button.html(originalText);
                button.prop("disabled", false);
            }
        });
      });

    });

    // Use AJAX to submit the academic info form
      $('#academic-info-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submission

        var button = $('#academic_info_submit');
        var originalText = button.html();
        var duplicateModalElement = document.getElementById('matricDuplicateModal');
        var duplicateModal = duplicateModalElement ? new bootstrap.Modal(duplicateModalElement) : null;
        var matricNumber = $.trim($('#new_matric_no').val());

        $('#new_matric_no').val(matricNumber);

        button.html(originalText + '  <div class="spinner-border text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        var formData = new FormData($('#academic-info-form')[0]);

        $.ajax({
            type: 'POST',
            url: 'model/user.php',
            data: formData,
            contentType: false,
            processData: false,
            success: function (data) {
              $('#alertBanner').html(data.message);

              if (data.status == 'success') {
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').removeClass('alert-danger');
                $('#alertBanner').addClass('alert-success');

                setTimeout(function () {
                  location.reload();
                }, 2000);
              } else if (data.status == 'duplicate') {
                $('#alertBanner').removeClass('alert-success');
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').addClass('alert-danger');

                var bellaBaseLink = data.bella_link || (window.NIVASITY_ENV && window.NIVASITY_ENV.supportWhatsAppLink) || 'https://wa.me/2347052645530';
                var bellaMessage = 'Hello Bella, another verified account already has my matric number (' + matricNumber + ') on Nivasity. Please help me update it if it belongs to me.';

                $('#matricDuplicateMessage').text(data.message);
                $('#bellaMatricHelpLink').attr('href', bellaBaseLink + '?text=' + encodeURIComponent(bellaMessage));

                if (duplicateModal) {
                  duplicateModal.show();
                }
              } else {
                $('#alertBanner').removeClass('alert-success');
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').addClass('alert-danger');
              }

              $('#alertBanner').fadeIn();

              setTimeout(function () {
                  $('#alertBanner').fadeOut();
              }, 5000);

              button.html(originalText);
              button.prop("disabled", false);
            }
        });
      });

    // Use AJAX to deactivate account
      $('#acct_deactivation-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submission

        var button = $('.deactivate-account');
        var originalText = button.html();

        button.html(originalText + '  <div class="spinner-border text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: 'model/user.php',
            data: $('#acct_deactivation-form').serialize(),
            success: function (data) {
              $('#alertBanner').html(data.message);

              if (data.status == 'success') {
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').removeClass('alert-danger');
                $('#alertBanner').addClass('alert-success');

                setTimeout(function () {
                  window.location.href = "signin.html?logout=1";
                }, 2000);
              } else {
                $('#alertBanner').removeClass('alert-success');
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').addClass('alert-danger');
              }

              $('#alertBanner').fadeIn();

              setTimeout(function () {
                  $('#alertBanner').fadeOut();
              }, 5000);

              button.html(originalText);
              button.prop("disabled", false);
            }
        });
      });

  </script>
  <!-- End custom js for this page-->
</body>


</html>
