<footer class="footer">
  <div class="d-sm-flex justify-content-center">
    <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">© Copyright <?php echo date('Y')?> Nivasity. All rights reserved.</span>
  </div>
</footer>

<?php if (!defined('WHATSAPP_FLOAT_RENDERED')) { define('WHATSAPP_FLOAT_RENDERED', true); ?>
  <!-- Floating WhatsApp Button (excluded on auth HTML pages since they don't include this footer) -->
  <div class="whatsapp-float" aria-live="polite">
    <a href="https://wa.me/2349059527495" target="_blank" rel="noopener" aria-label="Chat with Nivasity support on WhatsApp" title="Chat with support">
      <!-- Inline WhatsApp SVG icon to avoid external icon dependencies -->
      <svg width="28" height="28" viewBox="0 0 32 32" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
        <path d="M19.11 17.16c-.27-.14-1.61-.79-1.86-.88-.25-.09-.43-.14-.61.14-.18.27-.7.88-.86 1.06-.16.18-.32.2-.59.07-.27-.14-1.14-.42-2.18-1.34-.8-.71-1.34-1.58-1.5-1.85-.16-.27-.02-.41.12-.55.12-.12.27-.32.41-.48.14-.16.18-.27.27-.45.09-.18.05-.34-.02-.48-.07-.14-.61-1.47-.84-2.02-.22-.53-.44-.46-.61-.46-.16 0-.34-.02-.52-.02-.18 0-.48.07-.73.34-.25.27-.96.94-.96 2.29 0 1.35.98 2.66 1.11 2.84.14.18 1.93 2.95 4.68 4.02.65.28 1.16.45 1.55.58.65.21 1.24.18 1.7.11.52-.08 1.61-.66 1.84-1.29.23-.63.23-1.18.16-1.29-.07-.11-.25-.18-.52-.32z"/>
        <path d="M26.73 5.27C23.89 2.43 20.09 1 16.01 1 7.73 1 1 7.73 1 16.01c0 2.65.69 5.23 2 7.5L1 31l7.7-2.02c2.2 1.2 4.69 1.84 7.29 1.84 8.28 0 15.01-6.73 15.01-15.01 0-4.01-1.56-7.78-4.27-10.54zM16 28.74c-2.39 0-4.71-.64-6.73-1.85l-.48-.29-4.57 1.2 1.22-4.46-.31-.5C3.89 20.6 3.26 18.33 3.26 16 3.26 8.99 8.99 3.26 16 3.26c3.39 0 6.57 1.32 8.96 3.71A12.59 12.59 0 0 1 28.74 16c0 7.01-5.73 12.74-12.74 12.74z"/>
      </svg>
    </a>
    <span class="whatsapp-tooltip">Chat with support</span>
  </div>
<?php } ?>
