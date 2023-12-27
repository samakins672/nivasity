<?php
session_start();
include('model/config.php');
include('model/page_config.php');

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>My Profile - Nivasity</title>
  
  <?php include('partials/_head.php') ?>
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
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold" id="contact-tab" data-bs-toggle="tab" href="#academics" role="tab"
                        aria-selected="false">Academic Info</a>
                    </li>
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
                            <div class="card-body px-2">
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
                          <div class="card-body px-2">
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
                          <div class="card-body px-2">
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
                          <div class="card-body px-2">
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
                  <div class="tab-pane fade hide" id="academics" role="tabpanel" aria-labelledby="academics">
                    <div class="row">
                      <div class="col-12">
                        <div class="card card-rounded p-3 px-2 shadow-sm">
                          <div class="card-header">
                            <h4 class="fw-bold">Academic Information</4>
                          </div>
                          <div class="card-body px-2">
                            <div class="row">
                              <?php
                              $school = mysqli_fetch_array(mysqli_query($conn, "SELECT name FROM schools WHERE id = $school_id"))[0];
                              $user_dept_name = mysqli_fetch_array(mysqli_query($conn, "SELECT name FROM depts_$school_id WHERE id = $user_dept"))[0];

                              ?>
                              <div class="col-md-6">
                                <div class="form-outline mb-4">
                                  <input type="text" id="new_institution"
                                    class="form-control form-control-lg w-100"
                                    value="<?php echo $school ?>" />
                                  <label class="form-label" for="institution">Institution Name</label>
                                </div>
                              </div>
                              <div class="col-md-6">
                                <div class="form-outline mb-4">
                                  <input type="text" id="new_adm_year"
                                    class="form-control form-control-lg w-100" value="<?php echo $user_adm_year ?>" />
                                  <label class="form-label" for="adm_year">Admission Year</label>
                                </div>
                              </div>

                              <div class="col-md-6">
                                <div class="form-outline mb-4">
                                  <input type="text" id="new_department"
                                    class="form-control form-control-lg w-100" value="<?php echo $user_dept_name ?>" />
                                  <label class="form-label" for="department">Department</label>
                                </div>
                              </div>
                              <div class="col-md-6">
                                <div class="form-outline mb-4">
                                  <input type="text" id="new_matric_no"
                                    class="form-control form-control-lg w-100" value="<?php echo $user_matric_no ?>" />
                                  <label class="form-label" for="matric_no">Matric Number</label>
                                </div>
                              </div>
                            </div>
                            <!-- Save button -->
                            <button id="req-academic-change" type="submit" data-bs-toggle="modal" data-bs-target="#reqAcctChange"
                              class="btn btn-primary fw-bold btn-lg btn-block mt-2">Request Change</button>

                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                
                                                
                <!-- Request Academic Info Change Modal -->
                <div class="modal fade" id="reqAcctChange" tabindex="-1" role="dialog" aria-labelledby="reqAcctChangeLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="reqAcctChangeLabel">Request Academic Info Change</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <form id="academic_info-form" enctype="multipart/form-data">
                        <input type="hidden" name="new_institution" value="">
                        <input type="hidden" name="new_adm_year" value="">
                        <input type="hidden" name="new_department" value="">
                        <input type="hidden" name="new_matric_no" value="">
                        <div class="modal-body">
                          <div class="wysi-editor mb-4">
                            <label class="form-label" for="message">Why are you making this change?.</label>
                            <textarea class="form-control w-100 px-3 py-2" id="message" name="message"
                              required></textarea>
                          </div>

                          <div>
                            <label for="attachment" class="form-label">Upload proof(s) - (<span
                                class="attach_ment">no file selected</span>)</label>
                            <div>
                              <input type="file" id="attachment" name="attachment" class="form-control"
                                accept=".pdf,.jpeg,.jpg,.png" style="display: none" required>
                              <label for="attachment"
                                class="btn btn-lg btn-secondary text-light">
                                <i class="mdi mdi-upload-outline"></i> Upload
                              </label>
                            </div>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Close</button>
                          <button id="academic_info_submit" type="submit" class="btn btn-lg btn-primary">Submit</button>
                        </div>
                      </form>
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
      
      // Trigger file upload when the icon/button is clicked
      $('#attachment').change(function () {
        // Get the number of files selected and display the count
        var numFiles = $(this)[0].files.length;
        $('.attach_ment').text(numFiles + (numFiles === 1 ? ' file' : ' files') + ' selected');
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

      // Handle click event of View/Edit button
      $('#req-academic-change').on('click', function () {
        // Get the manual details from the data- attributes
        var new_institution = $('#new_institution').val();
        var new_adm_year = $('#new_adm_year').val();
        var new_department = $('#new_department').val();
        var new_matric_no = $('#new_matric_no').val();

        // Set the values in the edit manual modal
        $('#academic_info-form input[name="new_institution"]').val(new_institution);
        $('#academic_info-form input[name="new_adm_year"]').val(new_adm_year);
        $('#academic_info-form input[name="new_department"]').val(new_department);
        $('#academic_info-form input[name="new_matric_no"]').val(new_matric_no);
      });
    });

    // Use AJAX to submit the academic_info form
      $('#academic_info-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submission

        var button = $('#academic_info_submit');
        var originalText = button.html();

        button.html(originalText + '  <div class="spinner-border text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        var formData = new FormData($('#academic_info-form')[0]);

        $.ajax({
            type: 'POST',
            url: 'model/academicInfo.php',
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