# Brevo Credit Check and SMTP Fallback

## Overview

This implementation adds intelligent email delivery with automatic fallback from Brevo to SMTP when credits are low.

## How It Works

### Credit Checking

Before sending any email via Brevo, the system:

1. Calls the Brevo API (`https://api.brevo.com/v3/account`) to check subscription credits
2. Parses the response to extract the current credit count
3. Caches the result for 5 minutes to reduce API calls

### Automatic Fallback

The system automatically falls back to default SMTP when:

- Brevo subscription credits are **50 or less**
- The Brevo API is unreachable
- No subscription plan is found in the account
- Any error occurs during the credit check

### Configuration

Two constants control the behavior (defined in `model/mail.php`):

```php
define('BREVO_MIN_CREDITS_THRESHOLD', 50);  // Minimum credits before fallback
define('BREVO_CREDITS_CACHE_TTL', 300);     // Cache duration in seconds (5 minutes)
```

## API Response Format

The Brevo API returns account information including a `plan` array:

```json
{
  "plan": [
    {
      "type": "subscription",
      "credits": 100,
      "creditsType": "sendLimit"
    }
  ]
}
```

The system specifically looks for entries where `type === "subscription"` and extracts the `credits` value.

## Functions

### `checkBrevoCredits()`

- **Returns**: Integer (credit count) or `false` on error
- **Caching**: Results cached for 5 minutes
- **Error Handling**: Returns `false` for any API errors or missing data

### `sendBrevoMail($subject, $body, $to, $replyToEmail = null)`

- **Modified Behavior**: Now checks credits before sending
- **Fallback**: Calls `sendMail()` when credits are low or unavailable
- **Logging**: All decisions logged via `error_log()`

## Logging

All operations are logged for monitoring:

- Credit check results
- Fallback decisions
- API errors
- Email send attempts

Example log messages:
```
Brevo subscription credits: 100
Brevo credits (45) are low (<=50), falling back to default SMTP
Unable to check Brevo credits, falling back to default SMTP
```

## Setup

1. Copy `config/mail.example.php` to `config/mail.php`
2. Fill in both Brevo and SMTP credentials
3. The system will automatically use Brevo when credits are available
4. Falls back to SMTP when needed

## Benefits

- **Uninterrupted Service**: Emails continue sending even when Brevo credits run out
- **Cost Efficiency**: Automatically switches to SMTP, preventing service disruption
- **Performance**: Caching reduces API overhead
- **Transparency**: Comprehensive logging for monitoring
- **Maintainability**: Configurable thresholds via constants
