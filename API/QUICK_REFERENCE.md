# Nivasity Mobile API - Quick Reference

## Base URL
```
https://api.nivasity.com
```

## Authentication
JWT-based. Include `Authorization: Bearer <access_token>` header for authenticated requests.

## Common Headers
```
Content-Type: application/json
Authorization: Bearer <access_token>
```

## Token Information
- Access tokens expire in 1 hour
- Refresh tokens expire in 7 days
- Use `/auth/refresh-token.php` to get new tokens

## Quick Endpoint Reference

### 🔐 Authentication
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/auth/register.php` | Register student (sends OTP) | ❌ |
| POST | `/auth/verify-otp.php` | Verify OTP & get tokens | ❌ |
| POST | `/auth/login.php` | Login (returns tokens) | ❌ |
| POST | `/auth/refresh-token.php` | Refresh access token | ❌ |
| POST | `/auth/forgot-password.php` | Request password reset OTP | ❌ |
| POST | `/auth/reset-password.php` | Reset password with token | ❌ |
| POST | `/auth/resend-verification.php` | Resend verification | ❌ |
| POST | `/auth/logout.php` | Logout | ❌ |

### 👤 Profile
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/profile/profile.php` | Get profile | ✅ |
| POST | `/profile/update-profile.php` | Update basic profile | ✅ |
| POST | `/profile/update-academic-info.php` | Update academic info | ✅ |
| POST | `/profile/change-password.php` | Change password | ✅ |
| POST | `/profile/delete-account.php` | Delete account | ✅ |

### 📖 Reference Data
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/reference/schools.php` | List active schools | ❌ |
| GET | `/reference/faculties.php?school_id={id}` | List faculties by school | ❌ |
| GET | `/reference/departments.php?school_id={id}` | List departments by school | ❌ |

### 📚 Materials
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/materials/list.php` | List materials | ✅ |
| GET | `/materials/details.php?id={id}` | Material details | ✅ |
| POST | `/materials/cart-add.php` | Add to cart | ✅ |
| POST | `/materials/cart-remove.php` | Remove from cart | ✅ |
| GET | `/materials/cart-view.php` | View cart | ✅ |
| GET | `/materials/purchased.php` | Purchased items | ✅ |

### 💳 Payment
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/payment/init.php` | Initialize payment | ✅ |
| GET | `/payment/verify.php?tx_ref={ref}` | Verify payment | ✅ |
| GET | `/payment/transactions.php` | Transaction history | ✅ |

### 🎫 Support
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/support/create-ticket.php` | Create ticket | ✅ |
| GET | `/support/list-tickets.php` | List tickets | ✅ |
| GET | `/support/ticket-details.php?id={id}` | Ticket details | ✅ |
| POST | `/support/reply.php` | Reply to ticket | ✅ |

## Response Format

### Success Response
```json
{
  "status": "success",
  "message": "Operation successful",
  "data": { /* response data */ }
}
```

### Error Response
```json
{
  "status": "error",
  "message": "Error description"
}
```

## Status Codes
- `200` - OK
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `405` - Method Not Allowed
- `500` - Internal Server Error

## Example Requests

### Register (Step 1)
```bash
curl -X POST https://api.nivasity.com/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"pass123","first_name":"John","last_name":"Doe","phone":"08012345678","gender":"male","school_id":1}'
```

### Verify OTP (Step 2 - Complete Registration)
```bash
curl -X POST https://api.nivasity.com/auth/verify-otp.php \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","otp":"123456","reason":"registration"}'
```

### Login
```bash
curl -X POST https://api.nivasity.com/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"pass123"}'
```

### Forgot Password (Step 1 - Request OTP)
```bash
curl -X POST https://api.nivasity.com/auth/forgot-password.php \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com"}'
```

### Verify Password Reset OTP (Step 2 - Get Reset Token)
```bash
curl -X POST https://api.nivasity.com/auth/verify-otp.php \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","otp":"123456","reason":"password_reset"}'
```

### Reset Password (Step 3 - Update Password)
```bash
curl -X POST https://api.nivasity.com/auth/reset-password.php \
  -H "Content-Type: application/json" \
  -d '{"token":"RESET_TOKEN_FROM_STEP2","new_password":"newpass123"}'
```

### Refresh Token
```bash
curl -X POST https://api.nivasity.com/auth/refresh-token.php \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"YOUR_REFRESH_TOKEN"}'
```

### Get Schools
```bash
curl -X GET "https://api.nivasity.com/reference/schools.php?page=1&limit=50"
```

### Get Departments
```bash
curl -X GET "https://api.nivasity.com/reference/departments.php?school_id=1"
```

### Get Profile
```bash
curl -X GET https://api.nivasity.com/profile/profile.php \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### Update Academic Info
```bash
curl -X POST https://api.nivasity.com/profile/update-academic-info.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -d '{"dept_id":5,"matric_no":"190101001","adm_year":"2019"}'
```

### List Materials
```bash
curl -X GET "https://api.nivasity.com/materials/list.php?page=1&limit=20" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### Add to Cart
```bash
curl -X POST https://api.nivasity.com/materials/cart-add.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -d '{"material_id":45}'
```

### Initialize Payment
```bash
curl -X POST https://api.nivasity.com/payment/init.php \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### Create Support Ticket
```bash
curl -X POST https://api.nivasity.com/support/create-ticket.php \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -F "subject=Help needed" \
  -F "message=Description" \
  -F "category=Technical and Other Issues"
```

## Query Parameters

### Pagination
- `page` - Page number (default: 1)
- `limit` - Items per page (default: 20, max: 100)

### Materials List
- `search` - Search by title or course code
- `dept` - Filter by department ID
- `faculty` - Filter by faculty ID

### Support Tickets
- `status` - Filter by status (open, closed, in_progress)

## File Uploads

Supported file types:
- **Profile pictures:** JPG, PNG, GIF
- **Support attachments:** PDF, JPG, JPEG, PNG

Max file size: 10MB

## Notes

✅ Student role required (student or hoc)
✅ All dates in Africa/Lagos timezone
✅ Amounts in Nigerian Naira (NGN)
✅ JWT Bearer tokens required for auth
✅ JSON responses for all endpoints

## Support

📧 support@nivasity.com
📖 Full docs: `/API/README.md`
🧪 Testing: `/API/TESTING.md`

---

**API Version:** 1.0.0
**Last Updated:** December 2024
