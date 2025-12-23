# Nivasity Mobile API - Quick Reference

## Base URL
```
https://api.nivasity.com
```

## Authentication
Session-based. Login first, then use session cookies for subsequent requests.

## Common Headers
```
Content-Type: application/json
Cookie: PHPSESSID=<session_id>
```

## Quick Endpoint Reference

### 🔐 Authentication
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/auth/register.php` | Register student | ❌ |
| POST | `/auth/login.php` | Login | ❌ |
| POST | `/auth/logout.php` | Logout | ✅ |
| POST | `/auth/resend-verification.php` | Resend verification | ❌ |

### 👤 Profile
| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/profile/profile.php` | Get profile | ✅ |
| POST | `/profile/update-profile.php` | Update profile | ✅ |
| POST | `/profile/change-password.php` | Change password | ✅ |
| POST | `/profile/delete-account.php` | Delete account | ✅ |

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

### Login
```bash
curl -X POST https://api.nivasity.com/auth/login.php \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{"email":"user@example.com","password":"pass123"}'
```

### Get Profile
```bash
curl -X GET https://api.nivasity.com/profile/profile.php \
  -b cookies.txt
```

### List Materials
```bash
curl -X GET "https://api.nivasity.com/materials/list.php?page=1&limit=20" \
  -b cookies.txt
```

### Add to Cart
```bash
curl -X POST https://api.nivasity.com/materials/cart-add.php \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"material_id":45}'
```

### Initialize Payment
```bash
curl -X POST https://api.nivasity.com/payment/init.php \
  -b cookies.txt
```

### Create Support Ticket
```bash
curl -X POST https://api.nivasity.com/support/create-ticket.php \
  -b cookies.txt \
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
✅ Session cookies required for auth
✅ JSON responses for all endpoints

## Support

📧 support@nivasity.com
📖 Full docs: `/API/README.md`
🧪 Testing: `/API/TESTING.md`

---

**API Version:** 1.0.0
**Last Updated:** December 2024
