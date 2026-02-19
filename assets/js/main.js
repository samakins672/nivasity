function showAlert() {
  // Automatically show and hide the alert after 5 seconds
  $('#alertBanner').fadeIn();

  setTimeout(function () {
    $('#alertBanner').fadeOut();
  }, 5000);
}

function getAdmissionYears() {
  let $selectElement = $("#admissionYear");

  // Get the current year
  let currentYear = new Date().getFullYear();

  // Set the starting year
  let startYear = 2019;

  // Loop through the years and generate the options
  for (let year = currentYear + 1; year >= startYear; year--) {
    let option = $("<option/>", {
      value: `${year - 1}/${year}`,
      text: `${year - 1}/${year}`
    });

    $selectElement.append(option);
  }
}

// Disable MDB ripple app-wide to avoid click distortion on buttons.
// Keep duration as string ("0ms") to satisfy MDB type checks.
(function disableMdbRippleGlobally() {
  if (window.__nivasityRippleDisabled) {
    return;
  }
  window.__nivasityRippleDisabled = true;

  function applyRippleGuard() {
    if (window.mdb && window.mdb.Ripple && window.mdb.Ripple.prototype) {
      // Hard-disable ripple behavior globally.
      window.mdb.Ripple.prototype.init = function () {};
      window.mdb.Ripple.prototype._autoInit = function () {};
      window.mdb.Ripple.prototype._createRipple = function () {};
      window.mdb.Ripple.prototype._appendRipple = function () {};
      window.mdb.Ripple.prototype._removeHTMLRipple = function () {};
    }

    const styleId = "nivasity-disable-mdb-ripple";
    if (!document.getElementById(styleId)) {
      const style = document.createElement("style");
      style.id = styleId;
      style.textContent = [
        ".ripple-wave {",
        "  display: none !important;",
        "  opacity: 0 !important;",
        "  animation: none !important;",
        "  transition: none !important;",
        "}",
        ".ripple-surface {",
          "  transition: none !important;",
        "}",
        ".btn, .btn:active, .btn.active {",
        "  transition: none !important;",
        "  transform: none !important;",
        "}",
      ].join("\n");
      document.head.appendChild(style);
    }

    document.querySelectorAll("[data-mdb-ripple-duration]").forEach(function (el) {
      el.setAttribute("data-mdb-ripple-duration", "0ms");
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", applyRippleGuard);
  } else {
    applyRippleGuard();
  }
})();
