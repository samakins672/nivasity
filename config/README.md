# Mail configuration

Create a `config/mail.php` file in this directory (it remains ignored by git) and define your SMTP and Brevo credentials as PHP `define` statements. You can copy from `mail.example.php` and update the placeholders. The application automatically loads `config/mail.php` wherever mail functions are used.

## Payment Gateway Configuration

The system supports multiple payment gateways (Flutterwave, Paystack, and Interswitch). Configuration is managed through a centralized config file:

### Setup Instructions

1. **Create your config file:**
   ```bash
   cp config/payment_gateway.example.php config/payment_gateway.php
   ```

2. **Edit `config/payment_gateway.php`:**
   - Add your gateway credentials (public keys, secret keys, etc.)
   - Set the `active` field to your desired gateway: `'flutterwave'`, `'paystack'`, or `'interswitch'`

3. **Important:** The `config/payment_gateway.php` file is ignored by git for security.

### How it Works

- `config/fw.php` - This is the loader file (tracked in git). It loads credentials from `payment_gateway.php` and defines backward-compatible constants.
- `config/payment_gateway.example.php` - Example configuration showing the structure (tracked in git).
- `config/payment_gateway.php` - Your actual config with real credentials (ignored by git).

### Troubleshooting

If checkout is opening the wrong gateway:

1. Verify `config/payment_gateway.php` exists and has the correct `active` value
2. Check browser console for `console.log('Active Gateway:', ...)` to see what's being detected
3. Clear browser cache if needed
4. Ensure `config/fw.php` exists (it should be in the repository)

For detailed setup and testing instructions, see [PAYMENT_GATEWAY_GUIDE.md](../PAYMENT_GATEWAY_GUIDE.md) in the root directory.

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

## System Alerts

System-wide alerts can be displayed to all users at the top of the application index page and admin pages. Alerts are stored in a database table and automatically hidden after their expiry date.

- **Database Setup**
  - Run the migration file `sql/add_system_alerts.sql` to create the `system_alerts` table.
  - Table schema:
    - `id` — Auto-increment primary key
    - `title` — Title of the alert (displayed in bold before the message)
    - `message` — Text content of the alert
    - `expiry_date` — DateTime when the alert should stop being displayed
    - `active` — Boolean flag to enable/disable the alert manually
    - `created_at` — Timestamp of when the alert was created

- **Model Functions**
  - `get_active_system_alerts($conn)` — Fetches all active, non-expired alerts from the database
  - `render_system_alerts($alerts)` — Renders alerts as HTML (single alert or carousel for multiple alerts)
  - Both functions are defined in `model/system_alerts.php`

- **Usage**
  - Alerts are automatically displayed on:
    - `index.php` (application index page)
    - `admin/index.php` (admin dashboard)
  - If multiple alerts exist, they are shown in a Bootstrap carousel with navigation controls
  - Single alerts are displayed as dismissible info alerts

- **Styling**
  - Alert styles are defined in `assets/css/system-alerts.css`
  - The CSS file is included in `partials/_head.php` for all pages

- **Managing Alerts**
  - Insert new alerts directly into the `system_alerts` table
  - Set `expiry_date` to control when the alert should stop showing
  - Set `active = 0` to manually disable an alert before expiry
  - Alerts are automatically filtered by expiry date and active status

- **Example Alert**
  ```sql
  INSERT INTO `system_alerts` (`title`, `message`, `expiry_date`, `active`) VALUES
  ('New Features', 'Welcome to Nivasity! Check out our new features.', DATE_ADD(NOW(), INTERVAL 7 DAY), 1);
  ```
