# Mail configuration

Create a `config/mail.php` file in this directory (it remains ignored by git) and define your SMTP and Brevo credentials as PHP `define` statements. You can copy from `mail.example.php` and update the placeholders. The application automatically loads `config/mail.php` wherever mail functions are used.

## Tawk.to configuration

Site‑wide control of the Tawk chat widget is handled via a small config file and a shared client loader.

- Create `config/tawk.php` by copying `config/tawk.example.php`.
  - Only one constant is required:
    - `TAWK_ENABLED` — set to `true` to show the Tawk widget, or `false` to hide it and show the fallback Help button.
  - `config/tawk.php` is ignored by git, so you can set different values per environment.

- The client loader is `assets/js/tawk-widget.js`.
  - When enabled, it injects the Tawk embed.
  - When disabled, it renders a floating “Help” button (left side) that opens the help center in a new tab.
  - Defaults inside the loader:
    - Widget ID: `6722bbbb4304e3196adae0cd/1ibfqqm4s`
    - Help URL: `https://nivasity.tawk.help`
  - You can override these without editing the loader by setting the values before the loader runs, e.g. in a PHP page or template:
    
    <script>
      window.NIVASITY_ENV = window.NIVASITY_ENV || {};
      window.NIVASITY_ENV.tawk = Object.assign({}, window.NIVASITY_ENV.tawk, {
        enabled: <?php echo (defined('TAWK_ENABLED') && TAWK_ENABLED) ? 'true' : 'false'; ?>,
        // Optional overrides:
        // widgetId: 'your-widget-id',
        // helpUrl: 'https://your.help.center'
      });
    </script>

- Where it is included
  - For authenticated/dashboard pages, `partials/_footer.php` injects the `enabled` flag and loads the loader with the correct relative path.
  - For standalone pages that are plain PHP (e.g., `passwordreset.php`, `t&c.php`, `p&p.php`), the same loader is included at the bottom with the toggle injected.
  - If you add a new pure HTML page and need the same behavior, either convert it to `.php` and include the snippet above, or add an inline `<script>` with `window.NIVASITY_ENV.tawk = { enabled: false }` to force the Help button.

- Styling
  - The Help button uses the primary color and a subtle shadow.
  - Styles live in:
    - `assets/css/dashboard/style.css` (dashboard pages)
    - `assets/css/style.css` (standalone pages)
  - Look for the `.tawk-help-float` block if you want to tweak placement or appearance.

- Notes
  - The loader uses modern JS (optional chaining and nullish coalescing). If you must support older browsers, we can provide a transpiled variant.
  - If you see a 404 fetching the loader, make sure the page is using the correct relative path to `assets/js/tawk-widget.js`. Our footer template already adjusts for `/admin/` pages.
