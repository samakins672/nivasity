<?php
// Rendered only when $survey_banner_active is set (see model/page_config.php).
// Non-blocking, bottom-right widget. Stays visible (collapsed pill) across
// page loads until the student submits or explicitly dismisses it, both of
// which are recorded server-side per user in survey_banner_dismissals /
// survey_responses so it stays hidden across devices too.
$__survey = $survey_banner_active;
$__surveyQuestions = [];
if (!empty($__survey['sections']) && is_array($__survey['sections'])) {
  foreach ($__survey['sections'] as $__section) {
    if (!empty($__section['questions']) && is_array($__section['questions'])) {
      foreach ($__section['questions'] as $__q) {
        $__surveyQuestions[] = $__q;
      }
    }
  }
} elseif (!empty($__survey['questions']) && is_array($__survey['questions'])) {
  $__surveyQuestions = $__survey['questions'];
}
?>
<div class="survey-banner" id="surveyBannerWidget" aria-live="polite" data-survey-id="<?php echo (int) $__survey['id']; ?>">
  <button type="button" class="survey-banner-pill" id="surveyBannerPillBtn" aria-expanded="false" aria-controls="surveyBannerCard">
    <i class="bx bx-message-rounded-dots"></i>
    <span>Quick Survey</span>
  </button>

  <div class="survey-banner-card d-none" id="surveyBannerCard" role="dialog" aria-label="<?php echo htmlspecialchars((string) ($__survey['title'] ?? 'Survey'), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="survey-banner-header">
      <div class="survey-banner-title"><?php echo htmlspecialchars((string) ($__survey['title'] ?? 'Quick Survey'), ENT_QUOTES, 'UTF-8'); ?></div>
      <button type="button" class="survey-banner-close" id="surveyBannerCloseBtn" aria-label="Dismiss survey">&times;</button>
    </div>
    <div class="survey-banner-body">
      <?php if (!empty($__survey['description'])) { ?>
        <p class="survey-banner-desc"><?php echo htmlspecialchars((string) $__survey['description'], ENT_QUOTES, 'UTF-8'); ?></p>
      <?php } ?>

      <div id="surveyBannerAlert" class="survey-banner-alert d-none"></div>

      <form id="surveyBannerForm">
        <div class="mb-2">
          <input type="text" class="form-control form-control-sm" name="first_name" placeholder="First name" value="<?php echo htmlspecialchars((string) ($f_name ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
        </div>
        <div class="mb-2">
          <input type="text" class="form-control form-control-sm" name="last_name" placeholder="Last name" value="<?php echo htmlspecialchars((string) ($l_name ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
        </div>
        <div class="mb-2">
          <input type="email" class="form-control form-control-sm" name="email" placeholder="Email" value="<?php echo htmlspecialchars((string) ($user_email ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
        </div>
        <div class="mb-2">
          <input type="text" class="form-control form-control-sm" name="phone" placeholder="Phone (optional)" value="<?php echo htmlspecialchars((string) ($user_phone ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
        </div>

        <div id="surveyBannerQuestions">
          <?php foreach ($__surveyQuestions as $__q) {
            $__id = htmlspecialchars((string) ($__q['id'] ?? ''), ENT_QUOTES, 'UTF-8');
            $__label = htmlspecialchars((string) ($__q['question'] ?? ''), ENT_QUOTES, 'UTF-8');
            $__type = (string) ($__q['type'] ?? 'text');
            $__required = !empty($__q['required']);
            $__placeholder = htmlspecialchars((string) ($__q['placeholder'] ?? ''), ENT_QUOTES, 'UTF-8');
            $__condition = !empty($__q['condition']) && is_array($__q['condition']) ? $__q['condition'] : null;
            $__conditionAttrs = '';
            if ($__condition) {
              $__conditionAttrs = ' data-condition-field="' . htmlspecialchars((string) ($__condition['fieldId'] ?? ''), ENT_QUOTES, 'UTF-8')
                . '" data-condition-operator="' . htmlspecialchars((string) ($__condition['operator'] ?? 'equals'), ENT_QUOTES, 'UTF-8')
                . '" data-condition-value="' . htmlspecialchars((string) ($__condition['value'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            }
            if ($__id === '') continue;
          ?>
          <div class="mb-2 survey-banner-question" data-field-id="<?php echo $__id; ?>" <?php echo $__conditionAttrs; ?> <?php echo $__condition ? 'hidden' : ''; ?>>
            <label class="form-label small mb-1"><?php echo $__label; ?><?php echo $__required ? ' <span class="text-danger">*</span>' : ''; ?></label>
            <?php if ($__type === 'textarea') { ?>
              <textarea class="form-control form-control-sm" name="q_<?php echo $__id; ?>" rows="2" placeholder="<?php echo $__placeholder; ?>" <?php echo $__required ? 'data-required="1"' : ''; ?>></textarea>
            <?php } elseif ($__type === 'number') { ?>
              <input type="number" class="form-control form-control-sm" name="q_<?php echo $__id; ?>" placeholder="<?php echo $__placeholder; ?>" <?php echo isset($__q['min']) ? 'min="' . (int) $__q['min'] . '"' : ''; ?> <?php echo isset($__q['max']) ? 'max="' . (int) $__q['max'] . '"' : ''; ?> <?php echo $__required ? 'data-required="1"' : ''; ?> />
            <?php } elseif ($__type === 'select') { ?>
              <select class="form-select form-select-sm" name="q_<?php echo $__id; ?>" <?php echo $__required ? 'data-required="1"' : ''; ?>>
                <option value="">Select...</option>
                <?php foreach ((array) ($__q['options'] ?? []) as $__opt) { $__optSafe = htmlspecialchars((string) $__opt, ENT_QUOTES, 'UTF-8'); ?>
                  <option value="<?php echo $__optSafe; ?>"><?php echo $__optSafe; ?></option>
                <?php } ?>
              </select>
            <?php } elseif ($__type === 'multi-select') { ?>
              <div class="survey-banner-checks">
                <?php foreach ((array) ($__q['options'] ?? []) as $__i => $__opt) { $__optSafe = htmlspecialchars((string) $__opt, ENT_QUOTES, 'UTF-8'); ?>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="q_<?php echo $__id; ?>[]" value="<?php echo $__optSafe; ?>" id="sbq_<?php echo $__id . '_' . $__i; ?>" />
                    <label class="form-check-label small" for="sbq_<?php echo $__id . '_' . $__i; ?>"><?php echo $__optSafe; ?></label>
                  </div>
                <?php } ?>
              </div>
            <?php } else { ?>
              <input type="text" class="form-control form-control-sm" name="q_<?php echo $__id; ?>" placeholder="<?php echo $__placeholder; ?>" <?php echo $__required ? 'data-required="1"' : ''; ?> />
            <?php } ?>
          </div>
          <?php } ?>
        </div>

        <button type="submit" class="btn btn-primary btn-sm w-100 mt-2" id="surveyBannerSubmitBtn">Submit</button>
      </form>
    </div>
  </div>
</div>

<style>
  .survey-banner {
    position: fixed;
    right: 20px;
    bottom: 90px; /* stacked above the WhatsApp float */
    z-index: 1035;
    font-size: 14px;
  }

  .survey-banner-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    background-color: #4f46e5;
    color: #ffffff;
    border: none;
    border-radius: 999px;
    padding: 10px 16px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.15), 0 6px 6px rgba(0,0,0,0.10);
    font-size: 13px;
    font-weight: 600;
    transition: transform 0.15s ease, box-shadow 0.2s ease;
  }

  .survey-banner-pill:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 24px rgba(0,0,0,0.18), 0 10px 10px rgba(0,0,0,0.12);
  }

  .survey-banner-pill i {
    font-size: 18px;
  }

  .survey-banner-card {
    position: absolute;
    right: 0;
    bottom: 50px;
    width: 320px;
    max-height: 70vh;
    overflow-y: auto;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2), 0 10px 10px rgba(0,0,0,0.1);
    border: 1px solid #e5e7eb;
  }

  .survey-banner-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    background: #4f46e5;
    color: #fff;
    border-radius: 12px 12px 0 0;
  }

  .survey-banner-title {
    font-weight: 700;
    font-size: 14px;
  }

  .survey-banner-close {
    background: transparent;
    border: none;
    color: #fff;
    font-size: 20px;
    line-height: 1;
    padding: 0;
    cursor: pointer;
  }

  .survey-banner-body {
    padding: 12px 14px;
  }

  .survey-banner-desc {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 10px;
  }

  .survey-banner-alert {
    font-size: 12px;
    padding: 8px 10px;
    border-radius: 6px;
    margin-bottom: 10px;
  }

  .survey-banner-alert.alert-success {
    background: #ecfdf5;
    color: #047857;
  }

  .survey-banner-alert.alert-danger {
    background: #fef2f2;
    color: #b91c1c;
  }

  .survey-banner-checks {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  @media (max-width: 420px) {
    .survey-banner-card {
      width: calc(100vw - 40px);
    }
  }
</style>

<script>
  (function () {
    var widget = document.getElementById('surveyBannerWidget');
    if (!widget) return;

    var surveyId = widget.getAttribute('data-survey-id');
    var pillBtn = document.getElementById('surveyBannerPillBtn');
    var card = document.getElementById('surveyBannerCard');
    var closeBtn = document.getElementById('surveyBannerCloseBtn');
    var form = document.getElementById('surveyBannerForm');
    var alertBox = document.getElementById('surveyBannerAlert');
    var submitBtn = document.getElementById('surveyBannerSubmitBtn');

    function showAlert(type, message) {
      alertBox.className = 'survey-banner-alert alert-' + type;
      alertBox.textContent = message;
      alertBox.classList.remove('d-none');
    }

    function toggleCard(show) {
      card.classList.toggle('d-none', !show);
      pillBtn.setAttribute('aria-expanded', show ? 'true' : 'false');
    }

    pillBtn.addEventListener('click', function () {
      toggleCard(card.classList.contains('d-none'));
    });

    function applyConditions() {
      var fields = form.querySelectorAll('.survey-banner-question[data-condition-field]');
      fields.forEach(function (fieldEl) {
        var targetId = fieldEl.getAttribute('data-condition-field');
        var operator = fieldEl.getAttribute('data-condition-operator') || 'equals';
        var expected = fieldEl.getAttribute('data-condition-value') || '';
        var targetInput = form.querySelector('[name="q_' + targetId + '"]');
        var currentValue = targetInput ? targetInput.value : '';
        var matches = operator === 'equals' ? currentValue === expected : currentValue !== expected;
        fieldEl.hidden = !matches;
      });
    }

    form.addEventListener('change', applyConditions);
    applyConditions();

    function postAction(payload) {
      var body = new URLSearchParams(payload).toString();
      return fetch('model/survey_banner_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
      }).then(function (res) { return res.json(); });
    }

    closeBtn.addEventListener('click', function () {
      widget.remove();
      postAction({ action: 'dismiss', survey_id: surveyId }).catch(function () {});
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var formData = new FormData(form);
      var responses = {};
      var missingRequired = false;

      form.querySelectorAll('.survey-banner-question').forEach(function (fieldEl) {
        if (fieldEl.hidden) return;
        var fieldId = fieldEl.getAttribute('data-field-id');
        var multiInputs = fieldEl.querySelectorAll('input[type="checkbox"]');
        if (multiInputs.length > 0) {
          var checked = Array.prototype.slice.call(multiInputs).filter(function (i) { return i.checked; }).map(function (i) { return i.value; });
          responses[fieldId] = checked;
          return;
        }
        var input = fieldEl.querySelector('[name="q_' + fieldId + '"]');
        if (!input) return;
        var value = input.value.trim();
        if (input.hasAttribute('data-required') && value === '') {
          missingRequired = true;
        }
        responses[fieldId] = value;
      });

      if (missingRequired) {
        showAlert('danger', 'Please answer all required questions.');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';

      postAction({
        action: 'submit',
        survey_id: surveyId,
        first_name: formData.get('first_name') || '',
        last_name: formData.get('last_name') || '',
        email: formData.get('email') || '',
        phone: formData.get('phone') || '',
        responses: JSON.stringify(responses)
      }).then(function (res) {
        if (res && res.status === 'success') {
          showAlert('success', 'Thank you! Your response has been recorded.');
          form.querySelector('button[type="submit"]').remove();
          setTimeout(function () { widget.remove(); }, 1800);
        } else {
          showAlert('danger', (res && res.message) || 'Unable to submit. Please try again.');
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit';
        }
      }).catch(function () {
        showAlert('danger', 'Network error. Please try again.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit';
      });
    });
  })();
</script>
