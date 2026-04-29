<?php
$nivasity_intro_seen = isset($nivasity_2_intro_seen) ? (bool) $nivasity_2_intro_seen : false;
$nivasity_intro_mark_seen_url = isset($nivasity_intro_mark_seen_url) ? (string) $nivasity_intro_mark_seen_url : 'model/user.php';
$nivasity_intro_cta_label = isset($nivasity_intro_cta_label) ? (string) $nivasity_intro_cta_label : 'Continue';
$nivasity_intro_role = isset($_SESSION['nivas_userRole']) ? (string) $_SESSION['nivas_userRole'] : '';
$nivasity_intro_is_student_type = in_array($nivasity_intro_role, ['student', 'hoc'], true);
?>

<style>
  .nivasity-2-modal .modal-content {
    border: 0;
    border-radius: 1.5rem;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(28, 24, 20, 0.18);
  }

  .nivasity-2-modal .modal-header {
    border-bottom: 0;
    padding: 0;
  }

  .nivasity-2-modal .modal-body {
    padding: 0;
  }

  .nivasity-2-modal__hero {
    background: linear-gradient(135deg, #1f1206 0%, #7a3f00 58%, #ff9100 100%);
    color: #fffaf3;
    padding: 2rem 2rem 1.35rem;
    position: relative;
  }

  .nivasity-2-modal__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.8rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
  }

  .nivasity-2-modal__title {
    margin: 1rem 0 0.55rem;
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.05;
    color: #fff;
  }

  .nivasity-2-modal__subtitle {
    margin: 0;
    max-width: 34rem;
    color: rgba(255, 250, 243, 0.92);
    font-size: 1rem;
    line-height: 1.6;
  }

  .nivasity-2-modal__content {
    padding: 1.5rem 2rem 2rem;
    background: linear-gradient(180deg, #fffdf9 0%, #ffffff 100%);
  }

  .nivasity-2-modal__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
  }

  .nivasity-2-modal__card {
    border: 1px solid rgba(28, 24, 20, 0.08);
    border-radius: 1.15rem;
    padding: 1rem 1rem 1.05rem;
    background: #fff;
    box-shadow: 0 14px 35px rgba(39, 28, 13, 0.06);
  }

  .nivasity-2-modal__icon {
    width: 2.75rem;
    height: 2.75rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    background: rgba(255, 145, 0, 0.12);
    color: #c76a00;
    font-size: 1.25rem;
    margin-bottom: 0.85rem;
  }

  .nivasity-2-modal__card h5 {
    margin-bottom: 0.45rem;
    font-size: 1rem;
    font-weight: 800;
    color: #201913;
  }

  .nivasity-2-modal__card p {
    margin-bottom: 0;
    color: #5e534a;
    font-size: 0.92rem;
    line-height: 1.55;
  }

  .nivasity-2-modal__card-action {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-top: 0.9rem;
    padding: 0.7rem 1rem;
    border: 1px solid rgba(255, 145, 0, 0.24);
    border-radius: 999px;
    color: #a95a00;
    font-size: 0.88rem;
    font-weight: 800;
    text-decoration: none;
    background: #fff7ec;
    transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
  }

  .nivasity-2-modal__card-action:hover,
  .nivasity-2-modal__card-action:focus {
    color: #8c4a00;
    background: #fff1db;
    box-shadow: 0 10px 22px rgba(216, 116, 0, 0.15);
    transform: translateY(-1px);
  }

  .nivasity-2-modal__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 0.25rem;
  }

  .nivasity-2-modal__note {
    margin: 0;
    color: #6d6158;
    font-size: 0.92rem;
    line-height: 1.5;
    max-width: 29rem;
  }

  .nivasity-2-modal__cta {
    min-width: 13rem;
    border-radius: 999px;
    padding: 0.9rem 1.4rem;
    font-weight: 800;
    background: linear-gradient(135deg, #ff9100 0%, #d87400 100%);
    border: 0;
    box-shadow: 0 18px 30px rgba(216, 116, 0, 0.24);
  }

  .nivasity-2-modal__cta:disabled {
    opacity: 0.72;
  }

  .nivasity-2-modal__error {
    display: none;
    margin-top: 1rem;
  }

  @media (max-width: 767.98px) {
    .nivasity-2-modal .modal-dialog {
      margin: 0.75rem;
    }

    .nivasity-2-modal__hero,
    .nivasity-2-modal__content {
      padding-left: 1.2rem;
      padding-right: 1.2rem;
    }

    .nivasity-2-modal__title {
      font-size: 1.55rem;
    }

    .nivasity-2-modal__grid {
      grid-template-columns: 1fr;
    }

    .nivasity-2-modal__cta {
      width: 100%;
    }
  }
</style>

<div class="modal fade nivasity-2-modal" id="nivasity2IntroModal" tabindex="-1" aria-labelledby="nivasity2IntroModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div class="nivasity-2-modal__hero w-100">
          <span class="nivasity-2-modal__eyebrow">
            <i class="mdi mdi-rocket-launch-outline"></i>
            New update
          </span>
          <h2 class="nivasity-2-modal__title" id="nivasity2IntroModalLabel">Nivasity 2.0 is here</h2>
          <p class="nivasity-2-modal__subtitle">
            This is as a result of your complaints, and we listened. The experience is now more flexible, faster, and better for everyday student and HOC workflows.
          </p>
        </div>
      </div>
      <div class="modal-body">
        <div class="nivasity-2-modal__content">
          <div class="nivasity-2-modal__grid">
            <?php if ($nivasity_intro_is_student_type): ?>
            <article class="nivasity-2-modal__card">
              <div class="nivasity-2-modal__icon"><i class="mdi mdi-cash-multiple"></i></div>
              <h5>Bulk payments</h5>
              <p>Open the bulk payment picker directly from here and pay for multiple students under one material.</p>
              <a href="javascript:;" class="nivasity-2-modal__card-action" data-nivasity-action="modal" data-nivasity-open-modal="#bulkPaymentManualPickerModal">
                <i class="mdi mdi-arrow-top-right"></i>
                Open bulk payment
              </a>
            </article>
            <article class="nivasity-2-modal__card">
              <div class="nivasity-2-modal__icon"><i class="mdi mdi-bank-transfer"></i></div>
              <h5>Transfer to other students</h5>
              <p>Jump into the wallet transfer flow and send funds to another verified student in your school.</p>
              <a href="<?php echo htmlspecialchars(nivasity_app_url('wallet.php?open_transfer=1'), ENT_QUOTES, 'UTF-8'); ?>" class="nivasity-2-modal__card-action" data-nivasity-action="navigate">
                <i class="mdi mdi-arrow-top-right"></i>
                Open wallet transfer
              </a>
            </article>
            <article class="nivasity-2-modal__card">
              <div class="nivasity-2-modal__icon"><i class="mdi mdi-book-refresh-outline"></i></div>
              <h5>Repay for a material when lost</h5>
              <p>We now guide you through the approved flow: mark the old copy as lost, then return to store and buy another one.</p>
              <a href="<?php echo htmlspecialchars(nivasity_app_url('orders.php?lost_material_guide=1'), ENT_QUOTES, 'UTF-8'); ?>" class="nivasity-2-modal__card-action" data-nivasity-action="navigate">
                <i class="mdi mdi-arrow-top-right"></i>
                Start lost-material guide
              </a>
            </article>
            <?php endif; ?>
            <article class="nivasity-2-modal__card">
              <div class="nivasity-2-modal__icon"><i class="mdi mdi-email-edit-outline"></i></div>
              <h5>Update your email</h5>
              <p>You can now change your profile email without opening a brand new account.</p>
            </article>
            <article class="nivasity-2-modal__card">
              <div class="nivasity-2-modal__icon"><i class="mdi mdi-account-switch-outline"></i></div>
              <h5>Switch academic role</h5>
              <p>Move between Student and HOC if you picked the wrong role at signup or your status changes later.</p>
            </article>
            <article class="nivasity-2-modal__card">
              <div class="nivasity-2-modal__icon"><i class="mdi mdi-file-document-plus-outline"></i></div>
              <h5>Request missing materials</h5>
              <p>Ask for materials you cannot find, share the request with course mates, and once it reaches 40% demand, it can be added ASAP.</p>
            </article>
            <article class="nivasity-2-modal__card">
              <div class="nivasity-2-modal__icon"><i class="mdi mdi-wallet-outline"></i></div>
              <h5>Pay with Nivasity Wallet</h5>
              <p>Fund your wallet and pay faster while saving up to 80% compared with what users used to pay before.</p>
            </article>
          </div>

          <div class="alert alert-danger nivasity-2-modal__error" id="nivasity2IntroError" role="alert">
            We could not save this update right now. Please try again.
          </div>

          <div class="nivasity-2-modal__footer">
            <p class="nivasity-2-modal__note">Tap continue and this update will not be shown to you again on this account.</p>
            <button type="button" class="btn btn-primary nivasity-2-modal__cta" id="nivasity2IntroConfirm">
              <?php echo htmlspecialchars($nivasity_intro_cta_label, ENT_QUOTES, 'UTF-8'); ?>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var alreadySeen = <?php echo $nivasity_intro_seen ? 'true' : 'false'; ?>;
    if (alreadySeen) {
      return;
    }

    function initModal() {
      if (typeof bootstrap === 'undefined') {
        return;
      }

      var modalElement = document.getElementById('nivasity2IntroModal');
      var confirmButton = document.getElementById('nivasity2IntroConfirm');
      var errorElement = document.getElementById('nivasity2IntroError');

      if (!modalElement || !confirmButton) {
        return;
      }

      var modalInstance = new bootstrap.Modal(modalElement, {
        backdrop: 'static',
        keyboard: false
      });
      var actionButtons = modalElement.querySelectorAll('[data-nivasity-action]');
      var isSaving = false;

      function resetConfirmState() {
        isSaving = false;
        confirmButton.disabled = false;
        confirmButton.textContent = <?php echo json_encode($nivasity_intro_cta_label); ?>;
      }

      function showError(message) {
        if (!errorElement) {
          return;
        }

        errorElement.textContent = message || 'We could not save this update right now. Please try again.';
        errorElement.style.display = 'block';
      }

      function markIntroSeen(onSuccess) {
        if (isSaving) {
          return;
        }

        isSaving = true;
        confirmButton.disabled = true;
        confirmButton.textContent = 'Saving...';
        if (errorElement) {
          errorElement.style.display = 'none';
        }

        $.ajax({
          type: 'POST',
          url: <?php echo json_encode($nivasity_intro_mark_seen_url); ?>,
          dataType: 'json',
          data: {
            dismiss_nivasity_2_intro: 1
          },
          success: function (response) {
            if (response && response.status === 'success') {
              isSaving = false;
              if (typeof onSuccess === 'function') {
                onSuccess();
                return;
              }

              modalInstance.hide();
              return;
            }

            resetConfirmState();
            showError((response && response.message) ? response.message : 'We could not save this update right now. Please try again.');
          },
          error: function () {
            resetConfirmState();
            showError('We could not save this update right now. Please try again.');
          }
        });
      }

      confirmButton.addEventListener('click', function () {
        markIntroSeen(function () {
          modalInstance.hide();
        });
      });

      actionButtons.forEach(function (actionButton) {
        actionButton.addEventListener('click', function (event) {
          event.preventDefault();

          var navigateHref = actionButton.getAttribute('href');
          var modalTargetSelector = actionButton.getAttribute('data-nivasity-open-modal');

          markIntroSeen(function () {
            if (modalTargetSelector) {
              var modalTarget = document.querySelector(modalTargetSelector);
              if (!modalTarget) {
                modalInstance.hide();
                return;
              }

              var hiddenHandler = function () {
                modalElement.removeEventListener('hidden.bs.modal', hiddenHandler);
                var targetInstance = (typeof bootstrap.Modal.getOrCreateInstance === 'function')
                  ? bootstrap.Modal.getOrCreateInstance(modalTarget)
                  : new bootstrap.Modal(modalTarget);
                targetInstance.show();
              };

              modalElement.addEventListener('hidden.bs.modal', hiddenHandler);
              modalInstance.hide();
              return;
            }

            if (navigateHref && navigateHref !== 'javascript:;') {
              window.location.href = navigateHref;
              return;
            }

            modalInstance.hide();
          });
        });
      });

      modalInstance.show();
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initModal);
    } else {
      initModal();
    }
  })();
</script>
