# Bulk Verification Resend Feature

## Overview

This document explains how the bulk verification resend functionality works in Nivasity.

## Purpose

Allows authorized administrators to resend verification emails to **all unverified users** in the system at once. This is useful for:
- Helping users who never received their initial verification email
- Re-sending after email system issues
- Cleaning up pending verifications

## Location

**File:** `admin/resend_pending_verifications.php`

**URL:** `https://funaab.nivasity.com/admin/resend_pending_verifications.php`

## How It Works

### 1. Access Control

The page is restricted to specific authorized admin emails:

```php
$allowedEmails = [
  'akinyemisamuel170@gmail.com',
  'samuel@nivasity.com',
  'blessing.cf@nivasity.com'
];
```

**Requirements:**
- User must be logged in (active session)
- User's email must be in the allowed list
- Returns 403 Forbidden if not authorized

### 2. Query Unverified Users

```php
$pendingUsersQuery = mysqli_query($conn, 
  "SELECT id, first_name, email, role FROM users WHERE status = 'unverified'"
);
```

Fetches ALL users with `status = 'unverified'` from the database.

### 3. Process Each User

For each unverified user, the system:

#### a) Generate Unique Verification Code
```php
$verificationCode = generateVerificationCode(12);

// Ensure uniqueness
while (!isCodeUnique($verificationCode, $conn, 'verification_code')) {
  $verificationCode = generateVerificationCode(12);
}
```

#### b) Update Database
- If user already has a verification code → UPDATE it
- If user has no verification code → INSERT new one

#### c) Determine Verification Link
Based on user role:
- **org_admin** → `setup_org.html?verify={code}`
- **visitor** → `verify.html?verify={code}`
- **student** (default) → `setup.html?verify={code}`

#### d) Send Email
```php
$mailStatus = sendBrevoMail($subject, $body, $pendingUser['email']);
```

Uses the Brevo email system with automatic SMTP fallback.

**Email Template:**
```
Subject: Verify Your Account on NIVASITY

Hello [First Name],

We're sending you a new verification link so you can finish setting up 
your Nivasity account.

Click on the following link to verify your account: 
[Verify Account Link]

If you are unable to click on the link, please copy and paste the 
following URL into your browser: 
https://funaab.nivasity.com/setup.html?verify={code}

Thank you for choosing Nivasity. We look forward to serving you!

Best regards,
Nivasity Team
```

### 4. Display Results

The page shows an HTML table with:

| Email | Status | Message |
|-------|--------|---------|
| user@example.com | Success | Verification email sent. |
| user2@example.com | Error | Failed to send verification email. |

**Summary:** "Successfully sent X of Y verification emails."

## Usage Instructions

### For Administrators

1. **Login** as an authorized admin account
2. **Navigate** to `/admin/resend_pending_verifications.php`
3. **Wait** for processing (page loads automatically)
4. **Review** the results table
   - Green "Success" = Email sent successfully
   - Red "Error" = Email failed to send

### When to Use

- After email system maintenance
- When multiple users report not receiving verification emails
- Before deleting old unverified accounts (give them one more chance)
- After bulk user imports

## Technical Details

### Email System

Uses `sendBrevoMail()` function which:
1. Checks Brevo credit balance
2. Sends via Brevo API if credits available
3. Falls back to SMTP if:
   - Brevo credits ≤ 50
   - Brevo API unreachable
   - Any error occurs

### Database Tables

**users table:**
- Queried for: `id`, `first_name`, `email`, `role`
- Filter: `status = 'unverified'`

**verification_code table:**
- Fields: `user_id`, `code`, `exp_date`
- Code is updated or inserted for each user

### Security Considerations

**Current Implementation:**
- ✅ Session-based authentication
- ✅ Email whitelist for access control
- ✅ XSS protection on output (htmlspecialchars)
- ⚠️ Uses string escaping instead of prepared statements
- ⚠️ No rate limiting on emails
- ⚠️ Hardcoded allowed emails (should be in config)

**Recommendations:**
1. Move allowed emails to config file or database
2. Use prepared statements for all DB queries
3. Add rate limiting or confirmation dialog
4. Add logging for audit trail
5. Consider adding filters (e.g., send to specific school only)

## Performance

**Scalability:**
- Processes users sequentially (one at a time)
- No pagination - loads ALL unverified users
- Could be slow with hundreds of pending users
- Email sending is the bottleneck

**Recommendations for Large Scale:**
- Add pagination or batch processing
- Implement background job queue
- Add progress indicator for long operations
- Consider async email sending

## Error Handling

**Database Errors:**
- If verification code update/insert fails → Skip user, show error in results
- Continues processing remaining users

**Email Errors:**
- If email fails → Mark as error in results
- Does not stop processing
- Shows clear error message in table

## Related Features

- **Individual Resend:** `API/auth/resend-verification.php` (for single user)
- **Auto-resend on Login:** Now implemented in login flows
- **Manual Resend:** Web form for users to request new link

## Change History

- **Original Implementation:** Bulk resend for all unverified users
- **Recent Updates:** Login flows now auto-resend verification emails
- **Current State:** Working as designed, documentation added

## Support

For issues with this feature:
1. Check user's email is in allowed list
2. Verify user is logged in
3. Check email system (Brevo credits, SMTP config)
4. Review results table for specific errors

## Example Output

```
Verification Resend Summary

Successfully sent 15 of 18 verification emails.

┌──────────────────────┬─────────┬──────────────────────────────┐
│ Email                │ Status  │ Message                      │
├──────────────────────┼─────────┼──────────────────────────────┤
│ student1@example.com │ Success │ Verification email sent.     │
│ student2@example.com │ Success │ Verification email sent.     │
│ student3@example.com │ Error   │ Failed to send email.        │
│ ...                  │ ...     │ ...                          │
└──────────────────────┴─────────┴──────────────────────────────┘
```

## Notes

- This page executes immediately when loaded (no form submission required)
- All unverified users are processed automatically
- Results are displayed on the same page
- No confirmation dialog before sending emails
