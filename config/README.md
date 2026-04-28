# Configuration Files

## Mail configuration

Create a `config/mail.php` file in this directory (it remains ignored by git) and define your SMTP and Brevo credentials as PHP `define` statements. You can copy from `mail.example.php` and update the placeholders. The application automatically loads `config/mail.php` wherever mail functions are used.

## JWT Configuration (API Authentication)

The API uses JWT (JSON Web Tokens) for authentication. The JWT secret key must be configured securely.

### Setup Instructions

1. **Create jwt.php from example:**
   ```bash
   cp config/jwt.example.php config/jwt.php
   ```

2. **Edit jwt.php and set a secure secret key:**
   - Generate a random secret key (at least 32 characters)
   - You can use: `openssl rand -base64 64`
   - Replace the default value in `JWT_SECRET_KEY`

3. **Production deployment options:**

   **Option A: Use environment variables (Recommended)**
   ```php
   define('JWT_SECRET_KEY', getenv('JWT_SECRET_KEY') ?: 'fallback_dev_key');
   ```
   Then set `JWT_SECRET_KEY` in your server environment variables.

   **Option B: Use the config file directly**
   ```php
   define('JWT_SECRET_KEY', 'your_long_random_secret_key_here');
   ```
   Ensure `config/jwt.php` is NOT committed to version control.

### Important Security Notes

- **NEVER commit the actual jwt.php file** - it's already in .gitignore
- The JWT secret key is critical for API security
- If compromised, all issued tokens can be forged
- Change the key immediately if you suspect it has been exposed
- Use different keys for development, staging, and production environments

### Token Expiry Settings

You can also configure token expiration times in `config/jwt.php`:
- `JWT_ACCESS_TOKEN_EXPIRY` - Default: 3600 seconds (1 hour)
- `JWT_REFRESH_TOKEN_EXPIRY` - Default: 604800 seconds (7 days)

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

The payment freeze system allows you to temporarily pause all payment operations (similar to a staging or maintenance mode). When enabled, users will see a modal notification when they attempt to checkout, informing them that payments are paused until a specified date/time or until you manually disable the freeze.

- **Configuration File**
  - Create `config/payment_freeze.php` by copying `config/payment_freeze.example.php`
  - `config/payment_freeze.php` is ignored by git for per-environment configuration
  - Five main settings:
    - `PAYMENT_FREEZE_ENABLED` — Set to `true` to freeze payments, `false` to allow normal operations
    - `PAYMENT_FREEZE_EXPIRY` — Date/time when the freeze will be lifted (format: `'YYYY-MM-DD HH:MM:SS'`). Leave it as `''` when using manual disable mode.
    - `PAYMENT_FREEZE_NO_EXPIRY` — Set to `true` to keep the freeze active until you manually switch `PAYMENT_FREEZE_ENABLED` back to `false`
    - `PAYMENT_FREEZE_MESSAGE` — Optional custom message to display (leave empty for default message)
    - `PAYMENT_FREEZE_SCOPE` — Set to `'all'` to block every payment path, or `'gateway'` to block only hosted gateway checkout while keeping wallet/free checkout available

- **How It Works**
  - When `PAYMENT_FREEZE_ENABLED` is set to `true` and `PAYMENT_FREEZE_SCOPE = 'all'`, all checkout attempts are blocked
  - When `PAYMENT_FREEZE_ENABLED` is set to `true` and `PAYMENT_FREEZE_SCOPE = 'gateway'`, only hosted gateway checkout is blocked while wallet and free checkout remain available
  - When `PAYMENT_FREEZE_NO_EXPIRY` is set to `true`, the freeze stays active until you manually disable it, and `PAYMENT_FREEZE_EXPIRY` can be left empty
  - The website disables the gateway checkout action in the cart and shows the freeze message if the user still reaches a blocked gateway path
  - The API returns a `403` error for gateway checkout attempts when the freeze scope is `gateway`, which keeps older mobile app builds from using the gateway path
  - The modal displays when payments will resume based on `PAYMENT_FREEZE_EXPIRY`
  - Once the expiry date/time passes, payments automatically resume even if the config is not updated
  - The system checks the freeze status on every checkout attempt

- **Usage Examples**
  ```php
  // Example 1: Enable freeze until January 15, 2025 at 2:30 PM
  define('PAYMENT_FREEZE_ENABLED', true);
  define('PAYMENT_FREEZE_EXPIRY', '2025-01-15 14:30:00');
  define('PAYMENT_FREEZE_NO_EXPIRY', false);
  define('PAYMENT_FREEZE_MESSAGE', '');
  define('PAYMENT_FREEZE_SCOPE', 'all');

  // Example 2: Custom message for system maintenance
  define('PAYMENT_FREEZE_ENABLED', true);
  define('PAYMENT_FREEZE_EXPIRY', '2025-01-20 09:00:00');
  define('PAYMENT_FREEZE_NO_EXPIRY', false);
  define('PAYMENT_FREEZE_MESSAGE', 'We are performing system maintenance. Payment services will resume on January 20, 2025 at 9:00 AM.');
  define('PAYMENT_FREEZE_SCOPE', 'all');

  // Example 3: Allow only wallet/free checkout while disabling hosted gateway checkout
  define('PAYMENT_FREEZE_ENABLED', true);
  define('PAYMENT_FREEZE_EXPIRY', '2025-01-20 09:00:00');
  define('PAYMENT_FREEZE_NO_EXPIRY', false);
  define('PAYMENT_FREEZE_MESSAGE', '');
  define('PAYMENT_FREEZE_SCOPE', 'gateway');

  // Example 4: Freeze indefinitely until manually disabled
  define('PAYMENT_FREEZE_ENABLED', true);
  define('PAYMENT_FREEZE_EXPIRY', '');
  define('PAYMENT_FREEZE_NO_EXPIRY', true);
  define('PAYMENT_FREEZE_MESSAGE', '');
  define('PAYMENT_FREEZE_SCOPE', 'gateway');

  // Example 5: Disable freeze (normal operations)
  define('PAYMENT_FREEZE_ENABLED', false);
  define('PAYMENT_FREEZE_EXPIRY', '2025-01-15 14:30:00');
  define('PAYMENT_FREEZE_NO_EXPIRY', false);
  define('PAYMENT_FREEZE_MESSAGE', '');
  define('PAYMENT_FREEZE_SCOPE', 'all');
  ```

- **Default Message**
  - If `PAYMENT_FREEZE_MESSAGE` is empty, the system displays:
    - "Payments are currently paused until [formatted date/time]. You will be notified when we activate all operations again."
  - When `PAYMENT_FREEZE_SCOPE = 'gateway'`, the default message becomes:
    - "Gateway payments are currently paused until [formatted date/time]. Only wallet payments are allowed right now."
  - When `PAYMENT_FREEZE_NO_EXPIRY = true`, the default messages omit the `until [formatted date/time]` portion.
  - The date/time is automatically formatted for user-friendly display (e.g., "Monday, January 15, 2025 at 2:30 PM")

- **Files Involved**
  - `config/payment_freeze.example.php` — Example configuration template
  - `config/payment_freeze.php` — Your actual configuration (git-ignored)
  - `model/payment_freeze.php` — Helper functions for checking freeze status
  - `index.php` — Frontend checkout integration with modal display

## Material Management Configuration

The material management configuration allows School Management to control whether HOC/Admin users can manage materials in the admin panel. When disabled, HOC/Admin users can view materials but cannot add, edit, or delete them.

- **Configuration File**
  - Create `config/material_management.php` by copying `config/material_management.example.php`
  - `config/material_management.php` is ignored by git for per-environment configuration
  - Two main settings:
    - `MATERIAL_MANAGEMENT_ENABLED` — Set to `true` to allow HOC/Admin to manage materials (default), `false` to disable material management
    - `MATERIAL_MANAGEMENT_DISABLED_MESSAGE` — Custom message displayed when users click the disabled "Add new material" button

- **How It Works**
  - When `MATERIAL_MANAGEMENT_ENABLED` is set to `true` (default):
    - HOC/Admin can add new materials
    - The action column on the materials table is visible with edit/export/delete options
    - Everything works as normal
  - When `MATERIAL_MANAGEMENT_ENABLED` is set to `false`:
    - The "Add new material" button is disabled
    - Clicking the disabled button shows an alert with the configured message
    - The action column on the materials table is completely removed
    - HOC/Admin can still view materials and their statistics but cannot modify them

- **Usage Examples**
  ```php
  // Example 1: Enable material management (default behavior)
  define('MATERIAL_MANAGEMENT_ENABLED', true);
  define('MATERIAL_MANAGEMENT_DISABLED_MESSAGE', 'The School Management has disabled material management at this level. Please reach out to your faculty managers to make enquiry.');

  // Example 2: Disable material management with custom message
  define('MATERIAL_MANAGEMENT_ENABLED', false);
  define('MATERIAL_MANAGEMENT_DISABLED_MESSAGE', 'Material management is currently restricted. Please contact your department head for assistance.');

  // Example 3: Disable with default message
  define('MATERIAL_MANAGEMENT_ENABLED', false);
  define('MATERIAL_MANAGEMENT_DISABLED_MESSAGE', 'The School Management has disabled material management at this level. Please reach out to your faculty managers to make enquiry.');
  ```

- **Default Message**
  - The default message is:
    - "The School Management has disabled material management at this level. Please reach out to your faculty managers to make enquiry."
  - This message guides users to contact their faculty managers for material-related inquiries

- **Files Involved**
  - `config/material_management.example.php` — Example configuration template
  - `config/material_management.php` — Your actual configuration (git-ignored)
  - `admin/index.php` — Admin dashboard with conditional material management UI
