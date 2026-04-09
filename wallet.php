<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$walletDashboard = nivasityGetWalletDashboardPayload($conn, (int)$user_id, 25);
$wallet = $walletDashboard['wallet'] ?? null;
$walletEligibleRoles = ['student', 'hoc'];
$currentUserRole = isset($_SESSION['nivas_userRole']) ? (string)$_SESSION['nivas_userRole'] : '';
$isWalletEligibleRole = in_array($currentUserRole, $walletEligibleRoles, true);
$isVerifiedUser = ((string)$user_status === 'verified');
$canRequestWallet = $wallet === null && $isWalletEligibleRole && $isVerifiedUser;
$hasWalletPin = (bool)($walletDashboard['has_pin'] ?? false);
$walletEntries = $walletDashboard['entries'] ?? [];
$walletCreditsTotal = (int)($walletDashboard['credits_total'] ?? 0);
$walletDebitsTotal = (int)($walletDashboard['debits_total'] ?? 0);

$walletAccessMessage = '';
if ($wallet === null) {
  if (!$isWalletEligibleRole) {
    $walletAccessMessage = 'Wallets are currently available only to student and HOC accounts.';
  } elseif (!$isVerifiedUser) {
    $walletAccessMessage = 'Your account must be verified before you can request a wallet.';
  } else {
    $walletAccessMessage = 'Open this page and request your wallet when you are ready. Wallets are not created automatically.';
  }
}

function walletEntryBadgeClass($entryType) {
  $entryType = strtolower((string)$entryType);
  if (in_array($entryType, ['credit', 'refund'], true)) {
    return 'bg-success';
  }
  if (in_array($entryType, ['debit', 'fee'], true)) {
    return 'bg-danger';
  }
  return 'bg-secondary';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Nivasity Wallet</title>
  <?php include('partials/_head.php') ?>
  <style>
    .wallet-card {
      border: 0;
      overflow: hidden;
      background: linear-gradient(135deg, #0b5ed7 0%, #0f766e 100%);
      color: #fff;
    }

    .wallet-card .wallet-meta-label {
      font-size: 0.85rem;
      opacity: 0.8;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .wallet-card .wallet-balance {
      font-size: 2.3rem;
      font-weight: 700;
      line-height: 1.1;
    }

    .wallet-data-box {
      border: 1px dashed rgba(13, 110, 253, 0.25);
      border-radius: 0.75rem;
      padding: 1rem;
      background: rgba(13, 110, 253, 0.03);
    }

    .wallet-empty-state {
      min-height: 260px;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
    }

    #manage-wallet-pin-btn,
    #manage-wallet-pin-btn:hover,
    #manage-wallet-pin-btn:focus {
      color: #fff;
    }

    .wallet-pin-input {
      height: 4.5rem;
      border-radius: 0.85rem;
      font-size: 1.55rem;
      font-weight: 700;
      letter-spacing: 0.24em;
      text-align: center;
      padding: 0.75rem 1rem;
    }

    .wallet-pin-input::placeholder {
      letter-spacing: 0.08em;
      font-size: 0.9rem;
      font-weight: 600;
    }

    .wallet-pin-field {
      max-width: 21rem;
      margin: 0 auto;
      text-align: center;
    }

    .wallet-pin-field .form-label {
      display: block;
      text-align: center;
    }

    .wallet-pin-flow {
      position: relative;
      overflow: hidden;
    }

    .wallet-pin-track {
      position: relative;
      min-height: 20rem;
    }

    .wallet-pin-step {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      padding: 0 0.15rem;
      opacity: 0;
      pointer-events: none;
      transform: translateX(12%);
      transition: transform 0.3s ease, opacity 0.25s ease;
    }

    .wallet-pin-step.is-active {
      position: relative;
      opacity: 1;
      pointer-events: auto;
      transform: translateX(0);
    }

    .wallet-pin-step.is-exit-left {
      transform: translateX(-12%);
    }

    .wallet-pin-resend-btn {
      border: 0;
      background: transparent;
      padding: 0;
      color: #0d6efd;
      font-weight: 700;
      text-decoration: underline;
    }

    .wallet-pin-resend-btn:disabled {
      color: #6c757d;
      text-decoration: none;
      cursor: not-allowed;
    }

    @media (max-width: 767.98px) {
      .wallet-balance {
        font-size: 1.85rem;
      }

      .wallet-hide-mobile {
        display: none;
      }
    }
  </style>
</head>

<body>
  <div class="container-scroller">
    <?php include('partials/_navbar.php') ?>
    <div class="container-fluid page-body-wrapper">
      <?php include('partials/_sidebar_user.php') ?>
      <div class="main-panel">
        <div class="content-wrapper py-0">
          <div class="row">
            <div class="col-sm-12 px-2">
              <div class="home-tab">
                <div class="tab-content tab-content-basic py-0">
                  <div class="tab-pane fade show active" id="wallet" role="tabpanel" aria-labelledby="wallet">
                    <div class="row g-3">
                      <div class="col-12 col-xl-7">
                        <div class="card card-rounded shadow-sm wallet-card">
                          <div class="card-body p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                              <div>
                                <p class="wallet-meta-label mb-2">Nivasity Wallet</p>
                                <h2 class="wallet-balance mb-2" id="wallet-balance-value">₦ <?php echo number_format((int)($wallet['balance'] ?? 0)); ?></h2>
                                <p class="mb-0"><?php echo $wallet ? 'Fund this wallet with your dedicated account below.' : 'Request wallet access from this page before it can be created.'; ?></p>
                              </div>
                              <div class="d-flex flex-wrap gap-2">
                                <?php if ($wallet): ?>
                                  <button type="button" class="btn btn-light fw-bold" id="refresh-wallet-btn">Refresh Credits</button>
                                  <button type="button" class="btn btn-outline-light fw-bold" id="manage-wallet-pin-btn" data-mode="<?php echo $hasWalletPin ? 'update' : 'create'; ?>"><?php echo $hasWalletPin ? 'Update Wallet PIN' : 'Create Wallet PIN'; ?></button>
                                <?php elseif ($canRequestWallet): ?>
                                  <button type="button" class="btn btn-light fw-bold" id="request-wallet-btn">Request Wallet</button>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="col-12 col-xl-5">
                        <div class="card card-rounded shadow-sm h-100">
                          <div class="card-body p-4">
                            <h4 class="fw-bold mb-3">Wallet Access</h4>
                            <?php if ($wallet): ?>
                              <div class="row mt-3">
                                <div class="col-6">
                                  <p class="text-muted mb-1">Total Credits</p>
                                  <h6 class="fw-bold text-success" id="wallet-credits-total">₦ <?php echo number_format($walletCreditsTotal); ?></h6>
                                </div>
                                <div class="col-6">
                                  <p class="text-muted mb-1">Total Debits</p>
                                  <h6 class="fw-bold text-danger" id="wallet-debits-total">₦ <?php echo number_format($walletDebitsTotal); ?></h6>
                                </div>
                              </div>
                            <?php else: ?>
                              <div class="wallet-empty-state">
                                <div>
                                  <i class="mdi mdi-wallet-outline text-primary" style="font-size: 3rem;"></i>
                                  <h5 class="fw-bold mt-3">Wallet Not Yet Requested</h5>
                                  <p class="text-muted mb-0"><?php echo htmlspecialchars($walletAccessMessage); ?></p>
                                </div>
                              </div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>

                      <div class="col-12 col-lg-6">
                        <div class="card card-rounded shadow-sm h-100">
                          <div class="card-header bg-white border-0 pt-4 px-4">
                            <h4 class="fw-bold mb-0">Funding Account</h4>
                          </div>
                          <div class="card-body px-4 pb-4">
                            <?php if ($wallet): ?>
                              <div class="wallet-data-box mb-3">
                                <p class="text-muted mb-1">Account Name</p>
                                <h5 class="fw-bold mb-0" id="wallet-account-name"><?php echo htmlspecialchars((string)($wallet['account_name'] ?? $user_name)); ?></h5>
                              </div>
                              <div class="wallet-data-box mb-3">
                                <p class="text-muted mb-1">Account Number</p>
                                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                  <h4 class="fw-bold mb-0" id="wallet-account-number"><?php echo htmlspecialchars((string)($wallet['account_number'] ?? '')); ?></h4>
                                  <button type="button" class="btn btn-outline-primary btn-sm copy-wallet-value" id="wallet-account-copy-btn" data-copy-value="<?php echo htmlspecialchars((string)($wallet['account_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Copy</button>
                                </div>
                              </div>
                              <div class="row g-3">
                                <div class="col-sm-6">
                                  <div class="wallet-data-box h-100">
                                    <p class="text-muted mb-1">Bank</p>
                                    <h6 class="fw-bold mb-0" id="wallet-bank-name"><?php echo htmlspecialchars((string)($wallet['bank_name'] ?? 'Wema Bank')); ?></h6>
                                  </div>
                                </div>
                                <div class="col-sm-6">
                                  <div class="wallet-data-box h-100">
                                    <p class="text-muted mb-1">Provider</p>
                                    <h6 class="fw-bold mb-0 text-capitalize" id="wallet-provider-name"><?php echo htmlspecialchars((string)($wallet['provider'] ?? 'paystack')); ?></h6>
                                  </div>
                                </div>
                              </div>
                              <div class="alert alert-info mt-3 mb-0">
                                Transfer funds into this account and use the refresh button if the balance does not update quickly enough.
                              </div>
                            <?php else: ?>
                              <div class="alert alert-warning mb-0">
                                No funding account is available yet. Open this page and request a wallet first.
                              </div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>

                      <div class="col-12 col-lg-6">
                        <div class="card card-rounded shadow-sm h-100">
                          <div class="card-header bg-white border-0 pt-4 px-4">
                            <h4 class="fw-bold mb-0">How It Works</h4>
                          </div>
                          <div class="card-body px-4 pb-4">
                            <div class="wallet-data-box mb-3">
                              <h6 class="fw-bold">1. Open this page</h6>
                              <p class="mb-0 text-muted">Wallets are not auto-created. The request starts here.</p>
                            </div>
                            <div class="wallet-data-box mb-3">
                              <h6 class="fw-bold">2. Request wallet access</h6>
                              <p class="mb-0 text-muted">Verified student and HOC accounts can request a dedicated funding account.</p>
                            </div>
                            <div class="wallet-data-box mb-3">
                              <h6 class="fw-bold">3. Fund your wallet</h6>
                              <p class="mb-0 text-muted">Transfer into the dedicated account number displayed on this page.</p>
                            </div>
                            <div class="wallet-data-box">
                              <h6 class="fw-bold">4. Pay from cart</h6>
                              <p class="mb-0 text-muted">When your balance covers the subtotal, the cart shows a wallet payment button.</p>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="col-12">
                        <div class="card card-rounded shadow-sm px-2">
                          <div class="card-header bg-white border-0 pt-4 px-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                              <h4 class="fw-bold mb-0">Recent Wallet Activity</h4>
                              <?php if ($wallet): ?>
                                <span class="badge bg-light text-dark border" id="wallet-entries-count"><?php echo count($walletEntries); ?> entries</span>
                              <?php endif; ?>
                            </div>
                          </div>
                          <div class="card-body" id="wallet-activity-content">
                            <?php if ($wallet && !empty($walletEntries)): ?>
                              <div class="table-responsive mt-1">
                                <table class="table table-striped table-hover select-table">
                                  <thead>
                                    <tr>
                                      <th>Type</th>
                                      <th>Reference</th>
                                      <th class="wallet-hide-mobile">Description</th>
                                      <th>Amount</th>
                                      <th class="wallet-hide-mobile">Balance After</th>
                                      <th>Date</th>
                                    </tr>
                                  </thead>
                                  <tbody id="wallet-activity-body">
                                    <?php foreach ($walletEntries as $entry): ?>
                                      <tr>
                                        <td>
                                          <span class="badge <?php echo walletEntryBadgeClass($entry['entry_type'] ?? 'adjustment'); ?> text-uppercase">
                                            <?php echo htmlspecialchars((string)($entry['entry_type'] ?? 'adjustment')); ?>
                                          </span>
                                        </td>
                                        <td>
                                          <h6 class="mb-0"><?php echo htmlspecialchars((string)($entry['provider_reference'] ?: $entry['reference'])); ?></h6>
                                        </td>
                                        <td class="wallet-hide-mobile"><?php echo htmlspecialchars((string)($entry['description'] ?? '-')); ?></td>
                                        <td>
                                          <h6 class="mb-0 <?php echo in_array((string)$entry['entry_type'], ['credit', 'refund'], true) ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo in_array((string)$entry['entry_type'], ['credit', 'refund'], true) ? '+' : '-'; ?>₦ <?php echo number_format((int)($entry['amount'] ?? 0)); ?>
                                          </h6>
                                        </td>
                                        <td class="wallet-hide-mobile">₦ <?php echo number_format((int)($entry['balance_after'] ?? 0)); ?></td>
                                        <td><?php echo date('j M, Y h:i a', strtotime((string)$entry['created_at'])); ?></td>
                                      </tr>
                                    <?php endforeach; ?>
                                  </tbody>
                                </table>
                              </div>
                            <?php else: ?>
                              <div class="wallet-empty-state">
                                <div>
                                  <i class="mdi mdi-history text-secondary" style="font-size: 3rem;"></i>
                                  <h5 class="fw-bold mt-3">No Wallet Activity Yet</h5>
                                  <p class="text-muted mb-0"><?php echo $wallet ? 'Fund or spend from your wallet to see activity here.' : 'Request your wallet first to start seeing activity here.'; ?></p>
                                </div>
                              </div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php include('partials/_footer.php') ?>
      </div>

      <div id="alertBanner" class="alert alert-info text-center fw-bold alert-dismissible end-2 top-2 fade show position-fixed w-auto p-2 px-4" role="alert" style="z-index: 5000; display: none;">
        Action completed.
      </div>

      <div class="modal fade" id="walletPinModal" tabindex="-1" aria-labelledby="walletPinModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold" id="walletPinModalLabel">Wallet PIN</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-danger d-none" id="walletPinError"></div>
              <div class="wallet-pin-flow">
                <div class="wallet-pin-track" id="walletPinFlowTrack">
                  <div class="wallet-pin-step is-active" id="walletPinCodeStep">
                    <p class="text-muted text-center" id="walletPinModalIntro">Enter the verification code from your email to continue.</p>
                    <div class="mb-3 wallet-pin-field">
                      <label for="wallet-pin-code" class="form-label fw-bold">Email Code</label>
                      <input type="text" class="form-control wallet-pin-input" id="wallet-pin-code" maxlength="6" inputmode="numeric" placeholder="6-DIGIT CODE">
                      <div class="mt-2 small text-muted">
                        Didn&apos;t get it?
                        <button type="button" id="resend-wallet-pin-code-btn" class="wallet-pin-resend-btn">Resend code</button>
                      </div>
                    </div>
                    <div class="wallet-pin-field d-grid">
                      <button type="button" class="btn btn-outline-primary fw-bold" id="verify-wallet-pin-code-btn">Verify Code</button>
                    </div>
                </div>
                  <div class="wallet-pin-step" id="walletPinSetStep">
                    <p class="text-muted text-center" id="walletPinPinIntro">Code confirmed. Set your 4-digit Wallet PIN.</p>
                    <div class="mb-3 wallet-pin-field">
                      <label for="wallet-pin-value" class="form-label fw-bold">New 4-digit PIN</label>
                      <input type="password" class="form-control wallet-pin-input" id="wallet-pin-value" maxlength="4" inputmode="numeric" placeholder="4-DIGIT PIN">
                    </div>
                    <div class="mb-0 wallet-pin-field">
                      <label for="wallet-pin-confirm" class="form-label fw-bold">Confirm PIN</label>
                      <input type="password" class="form-control wallet-pin-input" id="wallet-pin-confirm" maxlength="4" inputmode="numeric" placeholder="CONFIRM PIN">
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
              <button type="button" class="btn btn-outline-secondary d-none" id="wallet-pin-back-btn">Back</button>
              <button type="button" class="btn btn-primary fw-bold d-none" id="save-wallet-pin-btn">Save Wallet PIN</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/vendors/js/vendor.bundle.base.js"></script>
  <script src="assets/js/js/off-canvas.js"></script>
  <script src="assets/js/js/hoverable-collapse.js"></script>
  <script src="assets/js/js/template.js"></script>
  <script src="assets/js/js/settings.js"></script>
  <script src="assets/js/script.js"></script>
  <script>
    function showWalletBanner(message, kind) {
      var banner = $('#alertBanner');
      banner.removeClass('alert-info alert-success alert-danger').addClass('alert-' + kind);
      banner.text(message).fadeIn();
      setTimeout(function() {
        banner.fadeOut();
      }, 4000);
    }

    $(document).ready(function() {
      $('.btn').attr('data-mdb-ripple-duration', '0');

      var walletPinMode = <?php echo json_encode($hasWalletPin ? 'update' : 'create'); ?>;
      var walletExists = <?php echo $wallet ? 'true' : 'false'; ?>;
      var walletPinStep = 'code';
      var walletPinVerificationToken = '';
      var walletPinModalElement = document.getElementById('walletPinModal');
      var walletPinModal = walletPinModalElement ? new bootstrap.Modal(walletPinModalElement) : null;

      function formatNaira(value) {
        return '₦ ' + Number(value || 0).toLocaleString();
      }

      function escapeHtml(value) {
        return String(value == null ? '' : value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function renderWalletEntries(entries, hasWallet) {
        if (!hasWallet) {
          return '<div class="wallet-empty-state"><div><i class="mdi mdi-history text-secondary" style="font-size: 3rem;"></i><h5 class="fw-bold mt-3">No Wallet Activity Yet</h5><p class="text-muted mb-0">Request your wallet first to start seeing activity here.</p></div></div>';
        }

        if (!entries || !entries.length) {
          return '<div class="wallet-empty-state"><div><i class="mdi mdi-history text-secondary" style="font-size: 3rem;"></i><h5 class="fw-bold mt-3">No Wallet Activity Yet</h5><p class="text-muted mb-0">Fund or spend from your wallet to see activity here.</p></div></div>';
        }

        var rows = entries.map(function(entry) {
          return '<tr>'
            + '<td><span class="badge ' + escapeHtml(entry.badge_class || 'bg-secondary') + ' text-uppercase">' + escapeHtml(entry.entry_type || 'adjustment') + '</span></td>'
            + '<td><h6 class="mb-0">' + escapeHtml(entry.display_reference || '') + '</h6></td>'
            + '<td class="wallet-hide-mobile">' + escapeHtml(entry.description || '-') + '</td>'
            + '<td><h6 class="mb-0 ' + escapeHtml(entry.amount_class || 'text-danger') + '">' + escapeHtml(entry.amount_sign || '-') + formatNaira(entry.amount || 0) + '</h6></td>'
            + '<td class="wallet-hide-mobile">' + formatNaira(entry.balance_after || 0) + '</td>'
            + '<td>' + escapeHtml(entry.display_date || '') + '</td>'
            + '</tr>';
        }).join('');

        return '<div class="table-responsive mt-1"><table class="table table-striped table-hover select-table"><thead><tr><th>Type</th><th>Reference</th><th class="wallet-hide-mobile">Description</th><th>Amount</th><th class="wallet-hide-mobile">Balance After</th><th>Date</th></tr></thead><tbody id="wallet-activity-body">' + rows + '</tbody></table></div>';
      }

      function applyWalletDashboard(dashboard) {
        if (!dashboard || !dashboard.wallet) {
          return;
        }

        var wallet = dashboard.wallet;
        var entries = dashboard.entries || [];
        $('#wallet-balance-value').text(formatNaira(wallet.balance || 0));
        $('#wallet-credits-total').text(formatNaira(dashboard.credits_total || 0));
        $('#wallet-debits-total').text(formatNaira(dashboard.debits_total || 0));
        $('#wallet-account-name').text(wallet.account_name || <?php echo json_encode((string)$user_name); ?>);
        $('#wallet-account-number').text(wallet.account_number || '');
        $('#wallet-account-copy-btn').attr('data-copy-value', wallet.account_number || '');
        $('#wallet-bank-name').text(wallet.bank_name || 'Wema Bank');
        $('#wallet-provider-name').text(wallet.provider || 'paystack');
        $('#wallet-entries-count').text((dashboard.entries_count || entries.length || 0) + ' entries');
        $('#wallet-activity-content').html(renderWalletEntries(entries, true));
      }

      function runWalletRefresh(options) {
        var settings = $.extend({
          silent: false,
          button: null,
          loadingText: 'Refreshing...'
        }, options || {});
        var button = settings.button ? $(settings.button) : $();
        var originalText = button.length ? button.text() : '';

        if (button.length) {
          button.prop('disabled', true).text(settings.loadingText);
        }

        return $.ajax({
          url: 'model/refresh-wallet-credits.php',
          type: 'POST',
          dataType: 'json'
        }).done(function(response) {
          if (response && response.status === 'success') {
            if (response.data && response.data.dashboard) {
              applyWalletDashboard(response.data.dashboard);
            }
            if (!settings.silent) {
              showWalletBanner(response.message || 'Wallet refreshed successfully.', 'success');
            }
            return;
          }
          if (!settings.silent) {
            showWalletBanner((response && response.message) ? response.message : 'Wallet refresh failed.', 'danger');
          }
        }).fail(function(xhr) {
          if (!settings.silent) {
            var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Wallet refresh failed.';
            showWalletBanner(message, 'danger');
          }
        }).always(function() {
          if (button.length) {
            button.prop('disabled', false).text(originalText);
          }
        });
      }

      function setWalletPinStep(step) {
        walletPinStep = step === 'pin' ? 'pin' : 'code';
        $('#walletPinCodeStep').toggleClass('is-active', walletPinStep === 'code').toggleClass('is-exit-left', walletPinStep === 'pin');
        $('#walletPinSetStep').toggleClass('is-active', walletPinStep === 'pin').toggleClass('is-exit-left', false);
        $('#wallet-pin-back-btn, #save-wallet-pin-btn').toggleClass('d-none', walletPinStep !== 'pin');
        if (walletPinStep === 'pin') {
          $('#wallet-pin-value').trigger('focus');
        } else {
          $('#wallet-pin-code').trigger('focus');
        }
      }

      function sendWalletPinCode(triggerButton, successMessage) {
        var button = triggerButton ? $(triggerButton) : $();
        var originalText = button.length ? button.text() : '';
        if (button.length) {
          button.prop('disabled', true).text('Sending...');
        }

        $('#walletPinError').addClass('d-none').text('');

        return $.ajax({
          url: 'model/wallet-pin.php',
          type: 'POST',
          dataType: 'json',
          data: { action: 'send_code' }
        }).done(function(response) {
          if (response && response.status === 'success') {
            walletPinVerificationToken = '';
            setWalletPinStep('code');
            showWalletBanner(successMessage || response.message || 'Code sent to your email.', 'success');
            return;
          }
          $('#walletPinError').removeClass('d-none').text((response && response.message) ? response.message : 'Unable to send Wallet PIN code.');
        }).fail(function(xhr) {
          var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Unable to send Wallet PIN code.';
          $('#walletPinError').removeClass('d-none').text(message);
        }).always(function() {
          if (button.length) {
            button.prop('disabled', false).text(originalText);
          }
        });
      }

      function resetWalletPinModal(mode) {
        walletPinMode = mode || walletPinMode;
        $('#walletPinModalLabel').text(walletPinMode === 'update' ? 'Update Wallet PIN' : 'Create Wallet PIN');
        $('#walletPinModalIntro').text(walletPinMode === 'update'
          ? 'Enter the verification code sent to your email to continue updating your Wallet PIN.'
          : 'Enter the verification code sent to your email to continue creating your Wallet PIN.');
        $('#walletPinPinIntro').text(walletPinMode === 'update'
          ? 'Code confirmed. Set your new 4-digit Wallet PIN.'
          : 'Code confirmed. Create your 4-digit Wallet PIN.');
        walletPinVerificationToken = '';
        $('#walletPinError').addClass('d-none').text('');
        $('#wallet-pin-code, #wallet-pin-value, #wallet-pin-confirm').val('');
        setWalletPinStep('code');
      }

      $('#manage-wallet-pin-btn').on('click', function() {
        resetWalletPinModal($(this).data('mode') || walletPinMode);
        if (walletPinModal) {
          walletPinModal.show();
        }
        sendWalletPinCode(null, 'A verification code has been sent to your email.');
      });

      $('#resend-wallet-pin-code-btn').on('click', function() {
        sendWalletPinCode(this, 'A new verification code has been sent to your email.');
      });

      $('#verify-wallet-pin-code-btn').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        var code = $('#wallet-pin-code').val().trim();

        $('#walletPinError').addClass('d-none').text('');
        if (!/^\d{6}$/.test(code)) {
          $('#walletPinError').removeClass('d-none').text('Enter the 6-digit code sent to your email.');
          return;
        }

        button.prop('disabled', true).text('Verifying...');
        $.ajax({
          url: 'model/wallet-pin.php',
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'verify_code',
            code: code
          }
        }).done(function(response) {
          if (response && response.status === 'success' && response.data && response.data.pin_token) {
            walletPinVerificationToken = response.data.pin_token;
            setWalletPinStep('pin');
            return;
          }
          $('#walletPinError').removeClass('d-none').text((response && response.message) ? response.message : 'Unable to verify Wallet PIN code.');
        }).fail(function(xhr) {
          var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Unable to verify Wallet PIN code.';
          $('#walletPinError').removeClass('d-none').text(message);
        }).always(function() {
          button.prop('disabled', false).text(originalText);
        });
      });

      $('#wallet-pin-back-btn').on('click', function() {
        walletPinVerificationToken = '';
        $('#wallet-pin-value, #wallet-pin-confirm').val('');
        $('#walletPinError').addClass('d-none').text('');
        setWalletPinStep('code');
      });

      $('#save-wallet-pin-btn').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        var pin = $('#wallet-pin-value').val().trim();
        var confirmPin = $('#wallet-pin-confirm').val().trim();

        $('#walletPinError').addClass('d-none').text('');
        if (!walletPinVerificationToken) {
          $('#walletPinError').removeClass('d-none').text('Verify the email code before setting your Wallet PIN.');
          return;
        }
        if (!/^\d{4}$/.test(pin)) {
          $('#walletPinError').removeClass('d-none').text('Wallet PIN must be exactly 4 digits.');
          return;
        }
        if (pin !== confirmPin) {
          $('#walletPinError').removeClass('d-none').text('Wallet PIN confirmation does not match.');
          return;
        }

        button.prop('disabled', true).text('Saving...');
        $.ajax({
          url: 'model/wallet-pin.php',
          type: 'POST',
          dataType: 'json',
          data: {
            action: 'save_pin',
            pin_token: walletPinVerificationToken,
            pin: pin,
            confirm_pin: confirmPin
          }
        }).done(function(response) {
          if (response && response.status === 'success') {
            showWalletBanner(response.message || 'Wallet PIN saved successfully.', 'success');
            window.location.reload();
            return;
          }
          $('#walletPinError').removeClass('d-none').text((response && response.message) ? response.message : 'Unable to save Wallet PIN.');
        }).fail(function(xhr) {
          var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Unable to save Wallet PIN.';
          $('#walletPinError').removeClass('d-none').text(message);
        }).always(function() {
          button.prop('disabled', false).text(originalText);
        });
      });

      $('#request-wallet-btn').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        button.prop('disabled', true).text('Requesting...');

        $.ajax({
          url: 'model/request-wallet.php',
          type: 'POST',
          dataType: 'json'
        }).done(function(response) {
          if (response && response.status === 'success') {
            showWalletBanner(response.message || 'Wallet requested successfully.', 'success');
            window.location.reload();
            return;
          }
          showWalletBanner((response && response.message) ? response.message : 'Wallet request failed.', 'danger');
        }).fail(function(xhr) {
          var message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Wallet request failed.';
          showWalletBanner(message, 'danger');
        }).always(function() {
          button.prop('disabled', false).text(originalText);
        });
      });

      $('#refresh-wallet-btn').on('click', function() {
        runWalletRefresh({ button: this, loadingText: 'Refreshing...' });
      });

      if (walletExists) {
        runWalletRefresh({ silent: true, button: '#refresh-wallet-btn', loadingText: 'Checking DVA...' });
      }

      $(document).on('click', '.copy-wallet-value', async function() {
        var value = $(this).data('copy-value');
        if (!value) {
          showWalletBanner('Nothing to copy.', 'danger');
          return;
        }

        try {
          await navigator.clipboard.writeText(String(value));
          showWalletBanner('Copied to clipboard.', 'success');
        } catch (error) {
          showWalletBanner('Copy failed. Please copy manually.', 'danger');
        }
      });
    });
  </script>
</body>

</html>