# Nivasity Mobile API - Testing Guide

This guide provides instructions for testing the API endpoints using tools like cURL, Postman, or any HTTP client.

## Prerequisites

1. Ensure the API is deployed and accessible
2. Have a REST client installed (Postman, Insomnia, Thunder Client, or cURL)
3. For authenticated endpoints, you need to login first to get JWT tokens

## Base URL

For testing: `https://api.nivasity.com` or your testing domain

## Testing Flow

### 1. Test Registration

**Request:**
```bash
curl -X POST https://api.nivasity.com/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "testuser@example.com",
    "password": "Test123!",
    "first_name": "Test",
    "last_name": "User",
    "phone": "08012345678",
    "gender": "male"
  }'
```

**Expected Response:**
```json
{
  "status": "success",
  "message": "Registration successful! We've sent an account verification link to your email address.",
  "data": {
    "user_id": 123,
    "email": "testuser@example.com"
  }
}
```

### 2. Test Login

**Request:**
```bash
curl -X POST https://api.nivasity.com/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "testuser@example.com",
    "password": "Test123!"
  }'
```

**Expected Response:**
```json
{
  "status": "success",
  "message": "Logged in successfully!",
  "data": {
    "id": 123,
    "first_name": "Test",
    "last_name": "User",
    "email": "testuser@example.com",
    "role": "student",
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600,
    ...
  }
}
```

**Note:** Save the `access_token` from the response. You'll need it for authenticated requests.

### 3. Test Token Refresh

**Request:**
```bash
curl -X POST https://api.nivasity.com/auth/refresh-token.php \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  }'
```

**Expected Response:**
```json
{
  "status": "success",
  "message": "Token refreshed successfully",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

### 4. Test Get Profile

**Request:**
```bash
curl -X GET https://api.nivasity.com/profile/profile.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

**Note:** Replace `YOUR_ACCESS_TOKEN` with the actual token from the login response.

### 5. Test List Materials

**Request:**
```bash
curl -X GET "https://api.nivasity.com/materials/list.php?page=1&limit=10" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### 6. Test Add to Cart

**Request:**
```bash
curl -X POST https://api.nivasity.com/materials/cart-add.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -d '{
    "material_id": 45
  }'
```

### 6. Test View Cart

**Request:**
```bash
curl -X GET https://api.nivasity.com/materials/cart-view.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### 7. Test Initialize Payment

**Request:**
```bash
curl -X POST https://api.nivasity.com/payment/init.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### 8. Test Create Support Ticket

**Request:**
```bash
curl -X POST https://api.nivasity.com/support/create-ticket.php \
  -H "Content-Type: multipart/form-data" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -F "subject=Test Issue" \
  -F "message=I need help with accessing materials" \
  -F "category=Technical and Other Issues"
```

### 9. Test List Support Tickets

**Request:**
```bash
curl -X GET https://api.nivasity.com/support/list-tickets.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

### 10. Test Logout

**Request:**
```bash
curl -X POST https://api.nivasity.com/auth/logout.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

## Testing with Postman

1. **Import the API:**
   - Create a new collection in Postman
   - Add requests for each endpoint

2. **Handle Authentication:**
   - Enable "Cookies" in Postman settings
   - After login, cookies will be automatically saved
   - Subsequent requests will use the saved cookies

3. **Test File Uploads:**
   - Use "form-data" body type
   - Add files using the file selector

## Common Test Scenarios

### Scenario 1: Complete Purchase Flow
1. Login
2. List materials
3. Add materials to cart
4. View cart
5. Initialize payment
6. (Complete payment on gateway)
7. Verify payment
8. View purchased materials

### Scenario 2: Support Ticket Flow
1. Login
2. Create support ticket
3. List tickets
4. Get ticket details
5. Reply to ticket

### Scenario 3: Profile Management
1. Login
2. Get profile
3. Update profile
4. Change password

## Error Testing

Test these error scenarios:

1. **Unauthorized Access:**
   - Try accessing authenticated endpoints without login
   - Expected: 401 Unauthorized

2. **Invalid Input:**
   - Send requests with missing required fields
   - Expected: 400 Bad Request

3. **Wrong Role:**
   - Login as non-student user
   - Expected: 403 Forbidden

4. **Not Found:**
   - Request non-existent resource
   - Expected: 404 Not Found

## Performance Testing

Use tools like Apache Bench or JMeter to test:
- Response times
- Concurrent requests handling
- Load capacity

Example with Apache Bench:
```bash
ab -n 100 -c 10 -H "Cookie: PHPSESSID=your_session_id" \
  https://api.nivasity.com/materials/list.php
```

## Security Testing

1. **SQL Injection:**
   - Try SQL injection patterns in inputs
   - All inputs should be sanitized

2. **XSS:**
   - Try XSS payloads
   - All outputs should be escaped

3. **CSRF:**
   - Test with different origins
   - CORS headers should be properly configured

## Automated Testing

Consider creating automated tests using:
- PHPUnit for unit tests
- Codeception for functional tests
- Newman (Postman CLI) for API tests

## Notes

- All endpoints return JSON responses
- Authentication uses session cookies
- File uploads support PDF and images only
- Maximum file size: 10MB (configurable)
- All dates are in Africa/Lagos timezone

## Troubleshooting

1. **Session Issues:**
   - Clear cookies and login again
   - Check PHP session configuration

2. **CORS Errors:**
   - Verify .htaccess CORS headers
   - Check browser console for details

3. **File Upload Failures:**
   - Check file size limits
   - Verify file type is allowed
   - Ensure directory permissions

## Support

For testing support, contact: support@nivasity.com
