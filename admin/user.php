<?php
session_start();
include('../model/config.php');
include('../model/page_config.php');

if ($_SESSION['nivas_userRole'] == 'student') {
  header('Location: /');
  exit();
}

$settlement_query = mysqli_query($conn, "SELECT * FROM settlement_accounts WHERE school_id = $school_id AND type = 'school' ORDER BY `id` DESC LIMIT 1");
$from_school = false;
if (mysqli_num_rows($settlement_query) == 0) {
  $settlement_query = mysqli_query($conn, "SELECT * FROM settlement_accounts WHERE user_id = $user_id ORDER BY `id` DESC LIMIT 1");
} else {
  $from_school = true;
}

$d_icon = 'plus';
$d_text = 'Add new account';
$settlement_id = 0;

$acct_name = '';
$acct_number = '';
$bank = '1';

if (mysqli_num_rows($settlement_query) == 1) {
  $acct = mysqli_fetch_array($settlement_query);

  $acct_name = $acct['acct_name'];
  $acct_number = $acct['acct_number'];
  $bank = $acct['bank'];

  if (!$from_school) {
    $d_icon = 'edit';
    $d_text = 'Edit account';
    $settlement_id = 1;
  }
}

$filePath = '../model/all-banks-NG-flw.json';

// Read JSON data from the file
$bankListJson = file_get_contents($filePath);

// Decode the bank list JSON
$bankList = json_decode($bankListJson, true);

// Function to get bank name by code
function getBankName($code, $bankList)
{
  foreach ($bankList['data'] as $bank) {
    if ($bank['code'] === $code) {
      return $bank['name'];
    }
  }
  return ''; // Return an empty string if not found
}

// Get the bank name based on the bank code
$bankName = getBankName($bank, $bankList);

$faculties = [];
if ($_SESSION['nivas_userRole'] == 'hoc') {
  $faculties_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE school_id = $school_id AND status = 'active' ORDER BY name ASC");

  while ($faculty = mysqli_fetch_assoc($faculties_query)) {
    $faculties[$faculty['id']] = $faculty['name'];
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
  <!-- plugins:css -->
  <link rel="stylesheet" href="../assets/vendors/feather/feather.css">
  <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="../assets/vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../assets/vendors/typicons/typicons.css">
  <link rel="stylesheet" href="../assets/vendors/simple-line-icons/css/simple-line-icons.css">
  <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
  <!-- endinject -->
  <!-- Plugin css for this page -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="../assets/css/dashboard/style.css">
  <!-- endinject -->
  <style>
    .manual-faculty-select:focus {
      border-color: var(--bs-primary, #FF9100);
      box-shadow: 0 0 0 0.25rem rgba(255, 145, 0, 0.25);
      outline: 0;
    }
  </style>

  <!-- Google Sign-In API library -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>

  <link rel="shortcut icon" href="../favicon.ico" />

  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-30QJ6DSHBN"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-30QJ6DSHBN');
  </script>
  
  

</head>

<body>
  <div class="container-scroller">
    <!-- partial:partials/_navbar.php -->
    <?php include('../partials/_navbar.php') ?>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_sidebar_admin.php -->
      <?php include('../partials/_sidebar_admin.php') ?>
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
                    <?php if ($user_status == 'verified' && $_SESSION['nivas_userRole'] == 'hoc'): ?>
                      <li class="nav-item">
                        <a class="nav-link px-3 fw-bold" id="contact-tab" data-bs-toggle="tab" href="#academics" role="tab"
                          aria-selected="false">Academic Info</a>
                      </li>
                    <?php elseif ($user_status == 'verified' && $_SESSION['nivas_userRole'] == 'org_admin'): ?>
                      <li class="nav-item">
                        <a class="nav-link px-3 fw-bold" id="organisation-tab" data-bs-toggle="tab" href="#organisation" role="tab"
                          aria-selected="false">Organisation Info</a>
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
                        <div class="card card-rounded p-3 shadow-sm">
                          <form id="profile-form" enctype="multipart/form-data">
                            <div class="card-header">
                              <h4 class="fw-bold">Profile Details</4>
                                <div class="d-sm-flex justify-content-start">
                                  <div class="square-img rounded rounded-10 shadow-sm my-3" style="width: 150px;">
                                    <img src="../assets/images/users/<?php echo $user_image ?>" class="square-img-content" alt="Avatar" />
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
                      <div class="col-12 mb-4">
                        <div class="card card-rounded p-3 shadow-sm">
                          <h4 class="card-header pb-3">
                            <div class="d-sm-flex justify-content-between align-items-center">
                              <h4 class="fw-bold">Settlement Account</h4>
                              <?php if (!$from_school): ?>
                                <button class="btn btn-primary btn-lg fw-bold text-white mb-0 me-0 view-edit-account" type="button" data-bs-toggle="modal" data-bs-target="#addSettlement"
                                data-settlement_id="<?php echo $settlement_id ?>" data-acct_name="<?php echo $acct_name ?>" data-acct_number="<?php echo $acct_number ?>" data-bank="<?php echo $bank ?>"><i
                                    class="mdi mdi-briefcase-<?php echo $d_icon ?>"></i><?php echo $d_text ?></button>
                              <?php else: ?>
                                <span class="badge bg-success">Using school account</span>
                              <?php endif; ?>
                            </div>
                          </h4>
                          <div class="card-body">
                            <div class="table-responsive  mt-1">
                              <table class="table table-hover select-table">
                                <thead>
                                  <tr>
                                    <th>Bank Name</th>
                                    <th>Account Number</th>
                                    <th>Currency</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <tr>
                                    <td>
                                      <div class="d-flex ">
                                        <div>
                                          <p class="pb-2"><?php echo $bankName ?></p>
                                          <h6 class="text-uppercase"><?php echo $acct_name ?></h6>
                                        </div>
                                      </div>
                                    </td>
                                    <td>
                                      <h6 class="text-secondary"><?php echo $acct_number ?></h6>
                                    </td>
                                    <td>
                                      <h6>NGN</h6>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade hide" id="security" role="tabpanel" aria-labelledby="security">
                    <div class="row">
                      <div class="col-12 mb-4">
                        <div class="card card-rounded p-3 shadow-sm">
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
                        <div class="card card-rounded p-3 shadow-sm">
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
                        <div class="card card-rounded p-3 shadow-sm">
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
                  <?php if ($user_status == 'verified' && $_SESSION['nivas_userRole'] == 'hoc'): ?>
                    <div class="tab-pane fade hide" id="academics" role="tabpanel" aria-labelledby="academics">
                      <div class="row">
                        <div class="col-12">
                          <div class="card card-rounded p-3 shadow-sm">
                            <div class="card-header">
                              <h4 class="fw-bold">Academic Information</4>
                            </div>
                            <div class="card-body">
                              <div class="row">
                                <?php
                                $school = mysqli_fetch_array(mysqli_query($conn, "SELECT name FROM schools WHERE id = $school_id"))[0];
                                $user_dept_name = mysqli_fetch_array(mysqli_query($conn, "SELECT name FROM depts WHERE id = $user_dept AND school_id = $school_id"))[0];

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
                  <?php elseif ($user_status == 'verified' && $_SESSION['nivas_userRole'] == 'org_admin'): ?>
                    <div class="tab-pane fade hide" id="organisation" role="tabpanel" aria-labelledby="organisation">
                      <div class="row">
                        <div class="col-12">
                          <div class="card card-rounded p-3 shadow-sm">
                            <div class="card-header">
                              <h4 class="fw-bold">Organisation Information</4>
                            </div>
                            <div class="card-body">
                              <div class="row">
                                <?php
                                $organisation = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM organisation WHERE user_id = $user_id"));

                                ?>
                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="text" id="business_name" class="form-control form-control-lg w-100"
                                      value="<?php echo $organisation['business_name'] ?>" />
                                    <label class="form-label" for="business_name">Business Name</label>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="text" id="business_address"
                                      class="form-control form-control-lg w-100" value="<?php echo $organisation['business_address'] ?>" />
                                    <label class="form-label" for="business_address">Business Address</label>
                                  </div>
                                </div>

                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="url" id="web_url"
                                      class="form-control form-control-lg w-100" value="<?php echo $organisation['web_url'] ?>" />
                                    <label class="form-label" for="web_url">Business Website</label>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="text" id="work_email"
                                      class="form-control form-control-lg w-100" value="<?php echo $organisation['work_email'] ?>" />
                                    <label class="form-label" for="work_email">Work Email</label>
                                  </div>
                                </div>
                                <div class="col-md-6">
                                  <div class="form-outline mb-4">
                                    <input type="text" id="socials"
                                      class="form-control form-control-lg w-100" value="<?php echo $organisation['socials'] ?>" />
                                    <label class="form-label" for="socials">Social Media</label>
                                  </div>
                                </div>
                              </div>
                              <!-- Save button -->
                              <button id="req-organisation-change" type="submit" data-bs-toggle="modal" data-bs-target="#reqOrgChange"
                                class="btn btn-primary fw-bold btn-lg btn-block mt-2">Request Change</button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
                
                <!-- Settlement Account Modal -->
                <div class="modal fade" id="addSettlement" tabindex="-1" role="dialog" aria-labelledby="addSettlementLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="addSettlementLabel">Settlement Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <form id="settlement-form">
                        <input type="hidden" name="settlement_id" value="0">
                        <div class="modal-body">
                          <div class="form-outline mb-4">
                            <input type="number" id="acct_number" name="acct_number" class="form-control form-control-lg w-100" maxlength="11" required="">
                            <label class="form-label" for="acct_number">Account Number</label>
                          </div>
                          <div class="form-group mb-4">
                            <label class="form-label" for="bank">Bank Name</label>
                            <select id="bank" name="bank" class="form-control form-control-lg w-100"></select>
                          </div>
                          <div class="form-outline mb-4">
                            <input type="text" name="acct_name" class="form-control form-control-lg w-100" readonly required>
                            <label class="form-label" for="acct_name">Account Name</label>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Cancel</button>
                          <button id="settlement_submit" type="submit" class="btn btn-lg btn-primary" disabled>Submit</button>
                        </div>
                      </form>
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

                <!-- Request organisation Info Change Modal -->
                <div class="modal fade" id="reqOrgChange" tabindex="-1" role="dialog" aria-labelledby="reqOrgChangeLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="reqOrgChangeLabel">Request Organisation Info Change</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <form id="organisation_info-form" enctype="multipart/form-data">
                        <input type="hidden" name="business_name" value="">
                        <input type="hidden" name="business_address" value="">
                        <input type="hidden" name="web_url" value="">
                        <input type="hidden" name="work_email" value="">
                        <input type="hidden" name="socials" value="">
                        <div class="modal-body">
                          <div class="wysi-editor mb-4">
                            <label class="form-label" for="message">Why are you making this change?.</label>
                            <textarea class="form-control w-100 px-3 py-2" id="message" name="message"
                              required></textarea>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Close</button>
                          <button id="organisation_info_submit" type="submit" class="btn btn-lg btn-primary">Submit</button>
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
                          <div class="form-group mb-0">
                            <label class="form-label" for="manual_faculty">Associated Faculty</label>
                            <select id="manual_faculty" name="faculty"
                              class="form-control form-control-lg manual-faculty-select" <?php echo empty($faculties) ? 'disabled' : 'required'; ?>>
                              <option value="" selected disabled><?php echo empty($faculties) ? 'No faculties available' : 'Select faculty'; ?></option>
                              <?php foreach ($faculties as $faculty_id => $faculty_name): ?>
                                <option value="<?php echo (int) $faculty_id; ?>"><?php echo htmlspecialchars($faculty_name); ?></option>
                              <?php endforeach; ?>
                            </select>
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
        <?php include('../partials/_footer.php') ?>
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
  <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script src="../assets/vendors/chart.js/Chart.min.js"></script>
  <script src="../assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.1/mdb.min.js"></script>
  <script src="../assets/vendors/progressbar.js/progressbar.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="../assets/js/js/off-canvas.js"></script>
  <script src="../assets/js/js/hoverable-collapse.js"></script>
  <script src="../assets/js/js/template.js"></script>
  <script src="../assets/js/js/settings.js"></script>
  <script src="../assets/js/js/data-table.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="../assets/js/js/dashboard.js"></script>
  <script src="../assets/js/script.js"></script>
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
    // Fetch data from the JSON file
    $.getJSON('../model/all-banks-NG-flw.json', function(data) {
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
          url: '../model/user.php',
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
            url: '../model/user.php',
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
      $('.view-edit-account').on('click', function () {
        // Get the manual details from the data- attributes
        var settlement = $(this).data('settlement_id');
        var acct_name = $(this).data('acct_name');
        var acct_number = $(this).data('acct_number');
        var bank = $(this).data('bank');

        // Set the values in the edit manual modal
        $('#settlement-form input[name="settlement_id"]').val(settlement);
        $('#settlement-form input[name="acct_name"]').val(acct_name);
        $('#settlement-form input[name="acct_number"]').val(acct_number);
        $('#settlement-form select[name="bank"]').val(bank);
      });

      function fetchAccountName() {
        let acct_number = $('#acct_number').val();
        let bank_code = $('#settlement-form select[name="bank"]').val();
        let acct_name = $('#settlement-form input[name="acct_name"]');
        let submitButton = $('#settlement_submit');
        
        // Change the value to "Searching..."
        acct_name.val('Searching...');
        
        $('#acct_number').val(acct_number.slice(0, 10)); // Slice to 11 digits

        // Disable the submit button
        submitButton.prop('disabled', true);

        // Make an Ajax request to your PHP script
        $.ajax({
          url: '../model/getAcct_flw.php', // Replace with the actual path to your PHP script
          type: 'GET',
          data: {
            account_number: acct_number,
            bank_code: bank_code
          },
          success: function(response) {
            try {
              let jsonResponse = JSON.parse(response);

              if (jsonResponse.error) {
                acct_name.val(jsonResponse.error);
              } else {
                // Enable the submit button
                $('#settlement_submit').prop('disabled', false);

                // Update the result with the fetched account name
                acct_name.val(jsonResponse.account_name);
              }
            } catch (e) {
              acct_name.val('Error parsing server response.');
            }
          },
          error: function() {
            acct_name.val('Error fetching account name. Please try again.');
          }
        });
      }

      // Trigger the fetchAccountName function on keyup event
      $('#acct_number').on('keyup', fetchAccountName);

      // Trigger the fetchAccountName function on select change event
      $('#settlement-form select[name="bank"]').on('change', fetchAccountName);


      // Use AJAX to submit the settlement form
      $('#settlement-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submission

        // Define settlement button
        var button = $('#settlement_submit');
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border text-white" style="width: 1.5rem; height: 1.5rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        // Simulate an AJAX call using setTimeout
        setTimeout(function () {
          $.ajax({
            type: 'POST',
            url: 'model/settlement_flw.php',
            data: $('#settlement-form').serialize(),
            success: function (data) {
              console.log(data);
              $('#alertBanner').html(data.message);

              if (data.status == 'success') {
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').removeClass('alert-danger');
                $('#alertBanner').addClass('alert-success');

                location.reload();
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
        }, 2000); // Simulated AJAX delay of 2 seconds
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
          url: '../model/academicInfo.php',
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
                window.location.href = "support.php";
              }, 1000);
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
    $('#req-organisation-change').on('click', function () {
      // Get the manual details from the data- attributes
      var business_name = $('#business_name').val();
      var business_address = $('#business_address').val();
      var web_url = $('#web_url').val();
      var work_email = $('#work_email').val();
      var socials = $('#socials').val();

      // Set the values in the edit manual modal
      $('#organisation_info-form input[name="business_name"]').val(business_name);
      $('#organisation_info-form input[name="business_address"]').val(business_address);
      $('#organisation_info-form input[name="web_url"]').val(web_url);
      $('#organisation_info-form input[name="work_email"]').val(work_email);
      $('#organisation_info-form input[name="socials"]').val(socials);
    });

    // Use AJAX to submit the organisation_info form
    $('#organisation_info-form').submit(function (event) {
      event.preventDefault(); // Prevent the default form submission

      var button = $('#organisation_info_submit');
      var originalText = button.html();

      button.html(originalText + '  <div class="spinner-border text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
      button.prop('disabled', true);

      var formData = new FormData($('#organisation_info-form')[0]);

      $.ajax({
          type: 'POST',
          url: '../model/organisationInfo.php',
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
                window.location.href = "support.php";
              }, 1000);
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
            url: '../model/user.php',
            data: $('#acct_deactivation-form').serialize(),
            success: function (data) {
              $('#alertBanner').html(data.message);

              if (data.status == 'success') {
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').removeClass('alert-danger');
                $('#alertBanner').addClass('alert-success');

                setTimeout(function () {
                  window.location.href = "../signin.html?logout=1";
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