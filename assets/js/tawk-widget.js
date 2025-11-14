(function () {
  const defaultWidget = '6722bbbb4304e3196adae0cd/1ibfqqm4s';
  const defaultHelpUrl = 'https://nivasity.tawk.help';
  const config = window.NIVASITY_ENV?.tawk ?? {};
  const enabled = typeof config.enabled === 'boolean' ? config.enabled : true;
  const widgetId = typeof config.widgetId === 'string' && config.widgetId !== '' ? config.widgetId : defaultWidget;
  const helpUrl = typeof config.helpUrl === 'string' && config.helpUrl !== '' ? config.helpUrl : defaultHelpUrl;
  const widgetScriptId = 'nivasity-tawk-script';
  const helpFloatId = 'nivasity-help-float';

  function loadTawkWidget() {
    if (document.getElementById(widgetScriptId)) {
      return;
    }

    const script = document.createElement('script');
    script.id = widgetScriptId;
    script.type = 'text/javascript';
    script.async = true;
    script.src = `https://embed.tawk.to/${widgetId}`;
    script.charset = 'UTF-8';
    script.crossOrigin = 'anonymous';

    const target = document.getElementsByTagName('script')[0];
    if (target?.parentNode) {
      target.parentNode.insertBefore(script, target);
    } else {
      document.head.appendChild(script);
    }
  }

  function renderHelpButton() {
    if (document.getElementById(helpFloatId)) {
      return;
    }

    const container = document.createElement('div');
    container.id = helpFloatId;
    container.className = 'tawk-help-float';
    container.setAttribute('aria-live', 'polite');

    const anchor = document.createElement('a');
    anchor.href = helpUrl;
    anchor.target = '_blank';
    anchor.rel = 'noopener';
    anchor.setAttribute('aria-label', 'Open Nivasity help');
    anchor.setAttribute('title', 'Open Help Center');
    anchor.innerHTML = `
      <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
        <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/>
        <path d="M11.001 17h2v2h-2zm2.07-9.75c-1.64-.55-3.09.42-3.52 1.88-.11.37.19.74.58.74h.24c.26 0 .49-.17.56-.42.15-.52.63-.98 1.22-.98.69 0 1.25.56 1.25 1.25 0 .69-.56 1.25-1.25 1.25-.55 0-1 .45-1 1v1h2v-.29c1.16-.41 2-1.51 2-2.83 0-1.31-.86-2.5-2.08-2.8z"/>
      </svg>`;

    const tooltip = document.createElement('span');
    tooltip.className = 'tawk-help-tooltip';
    tooltip.textContent = 'Click to open the help desk';

    container.appendChild(anchor);
    container.appendChild(tooltip);
    document.body.appendChild(container);
  }

  function init() {
    if (enabled) {
      loadTawkWidget();
    } else {
      renderHelpButton();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
