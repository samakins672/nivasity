# Configuration Files

## Mail configuration

Create a `config/mail.php` file in this directory (it remains ignored by git) and define your SMTP and Brevo credentials as PHP `define` statements. You can copy from `mail.example.php` and update the placeholders. The application automatically loads `config/mail.php` wherever mail functions are used.

## Payment Gateway Configuration

The system supports multiple payment gateways (Flutterwave, Paystack, and Interswitch). 

### Important Files

- **`config/fw.php`** (ignored by git) - Contains your credentials and configuration
- **`config/fw.example.php`** (tracked in git) - Example template for fw.php
- **`config/payment_gateway.php`** (ignored by git) - Optional multi-gateway config
- **`config/payment_gateway.example.php`** (tracked in git) - Example for payment_gateway.php

### Setup Instructions

#### Option 1: Using existing fw.php (Legacy + New System)

If you already have `config/fw.php` with credentials:

1. **Add multi-gateway support to your existing fw.php:**
   - Your existing defines (`FLW_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY`, `STAGING_GATE`, etc.) should remain
   - Add the multi-gateway loading code from `fw.example.php` at the end
   - This allows both old constants and new gateway switching to work together

2. **Create payment_gateway.php for switching:**
   ```bash
   cp config/payment_gateway.example.php config/payment_gateway.php
   ```
   
3. **Edit payment_gateway.php:**
   - Set `'active'` to your desired gateway: `'flutterwave'`, `'paystack'`, or `'interswitch'`
   - Add credentials for each gateway you want to use

#### Option 2: Fresh Setup

If you're setting up from scratch:

1. **Create fw.php from example:**
   ```bash
   cp config/fw.example.php config/fw.php
   ```

2. **Edit fw.php:**
   - Add your gateway credentials
   - Configure STAGING_GATE if needed

3. **Optional - Create payment_gateway.php for easier switching:**
   ```bash
   cp config/payment_gateway.example.php config/payment_gateway.php
   ```

### How It Works

- `config/fw.php` loads credentials and defines constants (backward compatible)
- `config/fw.php` can optionally load `config/payment_gateway.php` for multi-gateway support
- If `payment_gateway.php` exists, it sets the active gateway
- If `payment_gateway.php` doesn't exist, system uses Flutterwave with credentials from fw.php

### Troubleshooting

If checkout is opening the wrong gateway:

1. Check that `config/fw.php` exists
2. If using multi-gateway, verify `config/payment_gateway.php` exists and has correct `'active'` value
3. Check browser console for `console.log('Active Gateway:', ...)` 
4. Clear browser cache if needed
5. Verify your `fw.php` includes the multi-gateway loading code (see `fw.example.php`)

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

## Payment Freeze System

The payment freeze system allows you to temporarily pause all payment operations (similar to a staging or maintenance mode). When enabled, users will see a modal notification when they attempt to checkout, informing them that payments are paused until a specified date/time.

- **Configuration File**
  - Create `config/payment_freeze.php` by copying `config/payment_freeze.example.php`
  - `config/payment_freeze.php` is ignored by git for per-environment configuration
  - Three main settings:
    - `PAYMENT_FREEZE_ENABLED` — Set to `true` to freeze payments, `false` to allow normal operations
    - `PAYMENT_FREEZE_EXPIRY` — Date/time when the freeze will be lifted (format: `'YYYY-MM-DD HH:MM:SS'`)
    - `PAYMENT_FREEZE_MESSAGE` — Optional custom message to display (leave empty for default message)

- **How It Works**
  - When `PAYMENT_FREEZE_ENABLED` is set to `true`, all checkout attempts are blocked
  - Users clicking the checkout button will see a modal popup with the freeze message
  - The modal displays when payments will resume based on `PAYMENT_FREEZE_EXPIRY`
  - Once the expiry date/time passes, payments automatically resume even if the config is not updated
  - The system checks the freeze status on every checkout attempt

- **Usage Examples**
  ```php
  // Example 1: Enable freeze until January 15, 2025 at 2:30 PM
  define('PAYMENT_FREEZE_ENABLED', true);
  define('PAYMENT_FREEZE_EXPIRY', '2025-01-15 14:30:00');
  define('PAYMENT_FREEZE_MESSAGE', '');

  // Example 2: Custom message for system maintenance
  define('PAYMENT_FREEZE_ENABLED', true);
  define('PAYMENT_FREEZE_EXPIRY', '2025-01-20 09:00:00');
  define('PAYMENT_FREEZE_MESSAGE', 'We are performing system maintenance. Payment services will resume on January 20, 2025 at 9:00 AM.');

  // Example 3: Disable freeze (normal operations)
  define('PAYMENT_FREEZE_ENABLED', false);
  define('PAYMENT_FREEZE_EXPIRY', '2025-01-15 14:30:00');
  define('PAYMENT_FREEZE_MESSAGE', '');
  ```

- **Default Message**
  - If `PAYMENT_FREEZE_MESSAGE` is empty, the system displays:
    - "Payments are currently paused until [formatted date/time]. You will be notified when we activate all operations again."
  - The date/time is automatically formatted for user-friendly display (e.g., "Monday, January 15, 2025 at 2:30 PM")

- **Files Involved**
  - `config/payment_freeze.example.php` — Example configuration template
  - `config/payment_freeze.php` — Your actual configuration (git-ignored)
  - `model/payment_freeze.php` — Helper functions for checking freeze status
  - `index.php` — Frontend checkout integration with modal display
