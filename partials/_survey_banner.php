<?php
// Rendered only when $survey_banner_active is set (see model/page_config.php).
// Full-width, non-blocking bar pinned to the bottom of the viewport. Stays
// visible across page loads until the student dismisses it or clicks
// through to take the survey (both recorded server-side per user in
// survey_banner_dismissals, so it stays hidden across devices too).
// The survey itself is answered on the public survey page (main_site),
// opened in a new tab — this widget never submits anything itself.
$__survey = $survey_banner_active;
$__surveySlug = (string) ($__survey['slug'] ?? '');
$__surveyLink = 'https://nivasity.com/survey/' . rawurlencode($__surveySlug);
?>
<div class="survey-banner" id="surveyBannerWidget" role="region" aria-label="Survey announcement" data-survey-id="<?php echo (int) $__survey['id']; ?>">
  <div class="survey-banner-inner">
    <div class="survey-banner-text">
      <i class="bx bx-message-rounded-dots survey-banner-icon"></i>
      <div class="survey-banner-copy">
        <div class="survey-banner-title"><?php echo htmlspecialchars((string) ($__survey['title'] ?? 'We would love your feedback'), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php if (!empty($__survey['description'])) {
          $__descFull = (string) $__survey['description'];
          $__descPreview = mb_strlen($__descFull) > 90 ? rtrim(mb_substr($__descFull, 0, 90)) . '…' : $__descFull;
        ?>
          <div class="survey-banner-desc" title="<?php echo htmlspecialchars($__descFull, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($__descPreview, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>
      </div>
    </div>
    <div class="survey-banner-actions">
      <a href="<?php echo htmlspecialchars($__surveyLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="survey-banner-cta" id="surveyBannerTakeBtn">Take Survey</a>
      <button type="button" class="survey-banner-close" id="surveyBannerCloseBtn" aria-label="Dismiss survey">&times;</button>
    </div>
  </div>
</div>

<style>
  /* Lift the WhatsApp float above this full-width bottom banner so they don't overlap. */
  .whatsapp-float {
    bottom: 100px !important;
  }

  .survey-banner {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1035;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #ffffff;
    box-shadow: 0 -8px 24px rgba(0,0,0,0.18);
  }

  .survey-banner-inner {
    max-width: 1100px;
    margin: 0 auto;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
  }

  .survey-banner-text {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    min-width: 0;
    flex: 1;
  }

  .survey-banner-copy {
    min-width: 0;
  }

  .survey-banner-icon {
    font-size: 22px;
    flex-shrink: 0;
    margin-top: 1px;
  }

  .survey-banner-title {
    font-weight: 700;
    font-size: 14px;
    line-height: 1.3;
  }

  .survey-banner-desc {
    font-size: 12px;
    color: rgba(255,255,255,0.85);
    margin-top: 2px;
    max-width: 560px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .survey-banner-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    margin-left: auto;
  }

  .survey-banner-cta {
    background: #ffffff;
    color: #4f46e5;
    font-weight: 700;
    font-size: 13px;
    padding: 8px 18px;
    border-radius: 999px;
    text-decoration: none;
    white-space: nowrap;
    transition: transform 0.15s ease, box-shadow 0.2s ease;
  }

  .survey-banner-cta:hover {
    color: #4f46e5;
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.15);
  }

  .survey-banner-close {
    background: transparent;
    border: none;
    color: #ffffff;
    font-size: 24px;
    line-height: 1;
    padding: 0 4px;
    cursor: pointer;
    opacity: 0.85;
  }

  .survey-banner-close:hover {
    opacity: 1;
  }

  @media (max-width: 576px) {
    .survey-banner-inner {
      padding: 12px 14px;
    }
    .survey-banner-text {
      max-width: 100%;
    }
    .survey-banner-desc {
      max-width: 100%;
    }
  }
</style>

<script>
  (function () {
    var widget = document.getElementById('surveyBannerWidget');
    if (!widget) return;

    var surveyId = widget.getAttribute('data-survey-id');
    var closeBtn = document.getElementById('surveyBannerCloseBtn');
    var takeBtn = document.getElementById('surveyBannerTakeBtn');

    function dismiss() {
      var body = new URLSearchParams({ action: 'dismiss', survey_id: surveyId }).toString();
      fetch('model/survey_banner_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
      }).catch(function () {});
    }

    closeBtn.addEventListener('click', function () {
      widget.remove();
      dismiss();
    });

    takeBtn.addEventListener('click', function () {
      // Opens the survey in a new tab (default link behavior); also mark it
      // dismissed here so the banner doesn't keep following the student
      // around after they've already gone to take it.
      widget.remove();
      dismiss();
    });
  })();
</script>
