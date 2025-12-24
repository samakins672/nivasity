# Nivasity Mobile API Documentation

This API is designed for the Nivasity mobile application and provides endpoints for student authentication, profile management, materials/manuals, payments, and support.

**Base URL:** `https://api.nivasity.com`

## Authentication

The API uses **JWT (JSON Web Token)** based authentication. After logging in, you'll receive an `access_token` and `refresh_token`. 

**Include the access token in all authenticated requests:**
```
Authorization: Bearer <access_token>
```

**Token Expiry:**
- Access tokens expire after 1 hour
- Refresh tokens expire after 7 days

**Token Refresh:**
When the access token expires, use the refresh token to get a new access token pair without requiring the user to login again.

## API Endpoints

### Authentication Endpoints

#### 1. Register
**Endpoint:** `POST /auth/register.php`

**Description:** Register a new student account. Sends a 6-digit OTP to the provided email for verification.

**Request Body (JSON):**
```json
{
  "email": "student@example.com",
  "password": "securepassword",
  "first_name": "John",
  "last_name": "Doe",
  "phone": "08012345678",
  "gender": "male",
  "school_id": 1
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Registration successful! We've sent a verification code (OTP) to your email address. Please check your inbox.",
  "data": {
    "user_id": 123,
    "email": "student@example.com",
    "message": "Use the verify-otp endpoint to complete registration",
    "expires_in": 600
  }
}
```

**Note:** 
- Account is created with status='pending' until OTP is verified
- OTP expires in 10 minutes (600 seconds)
- Academic information (department, matric number, admission year) is NOT required at registration
- Use `/auth/verify-otp.php` to complete registration and get tokens

#### 2. Verify OTP
**Endpoint:** `POST /auth/verify-otp.php`

**Description:** Verify the OTP sent during registration and complete account setup. Returns JWT tokens and user data.

**Request Body (JSON):**
```json
{
  "email": "student@example.com",
  "otp": "123456"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Account verified successfully! Welcome to Nivasity.",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 123,
      "first_name": "John",
      "last_name": "Doe",
      "email": "student@example.com",
      "phone": "08012345678",
      "gender": "male",
      "role": "student",
      "profile_pic": "user.jpg",
      "school_id": 1,
      "dept_id": null,
      "dept_name": null,
      "matric_no": null,
      "adm_year": null,
      "status": "active"
    }
  }
}
```

**Error Responses:**
- `404` - Invalid email address
- `400` - Account already verified (use login instead)
- `400` - Invalid or expired OTP

#### 3. Login
**Endpoint:** `POST /auth/login.php`

**Description:** Login to student account.

**Request Body (JSON):**
```json
{
  "email": "student@example.com",
  "password": "securepassword"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Logged in successfully!",
  "data": {
    "id": 123,
    "first_name": "John",
    "last_name": "Doe",
    "email": "student@example.com",
    "phone": "08012345678",
    "role": "student",
    "gender": "male",
    "status": "verified",
    "profile_pic": "user.jpg",
    "matric_no": "190101001",
    "dept": 5,
    "adm_year": "2019",
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

#### 3. Logout
**Endpoint:** `POST /auth/logout.php`

**Description:** Logout from current session.

**Response (Success):**
```json
{
  "status": "success",
  "message": "You have successfully logged out! Please delete your access and refresh tokens on the client side."
}
```

#### 4. Refresh Token
**Endpoint:** `POST /auth/refresh-token.php`

**Description:** Refresh access token using refresh token.

**Request Body (JSON):**
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response (Success):**
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

#### 5. Forgot Password
**Endpoint:** `POST /auth/forgot-password.php`

**Description:** Request a password reset OTP. Sends a 6-digit code to the user's email.

**Request Body (JSON):**
```json
{
  "email": "student@example.com"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "OTP sent to your email address. Please check your inbox.",
  "data": {
    "email": "student@example.com",
    "expires_in": 600
  }
}
```

**Error Responses:**
- `404` - No account found with this email address

**Note:** OTP expires in 10 minutes (600 seconds)

#### 6. Reset Password
**Endpoint:** `POST /auth/reset-password.php`

**Description:** Reset password using the OTP received via email.

**Request Body (JSON):**
```json
{
  "email": "student@example.com",
  "otp": "123456",
  "new_password": "newsecurepassword"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Password reset successfully! You can now login with your new password."
}
```

**Error Responses:**
- `404` - Invalid email address
- `400` - Invalid or expired OTP

#### 7. Resend Verification
**Endpoint:** `POST /auth/resend-verification.php`

**Description:** Resend email verification link.

**Request Body (JSON):**
```json
{
  "email": "student@example.com"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "We've sent you a fresh verification link. Please check your inbox (and spam folder)."
}
```

---

### Profile Management Endpoints

#### 5. Get Profile
**Endpoint:** `GET /profile/profile.php`

**Description:** Get current user profile information.

**Authentication:** Required (Include `Authorization: Bearer <access_token>` header)

**Response (Success):**
```json
{
  "status": "success",
  "message": "Profile retrieved successfully",
  "data": {
    "id": 123,
    "first_name": "John",
    "last_name": "Doe",
    "email": "student@example.com",
    "phone": "08012345678",
    "gender": "male",
    "role": "student",
    "status": "verified",
    "profile_pic": "user.jpg",
    "matric_no": "190101001",
    "dept": 5,
    "dept_name": "Computer Science",
    "adm_year": "2019",
    "school": 1
  }
}
```

#### 6. Update Profile
**Endpoint:** `POST /profile/update-profile.php`

**Description:** Update basic user profile information and profile picture.

**Authentication:** Required

**Request Body (Multipart Form Data):**
- `firstname`: First name (optional)
- `lastname`: Last name (optional)
- `phone`: Phone number (optional)
- `upload`: Profile picture file (optional, JPG/PNG/GIF)

**Response (Success):**
```json
{
  "status": "success",
  "message": "Profile successfully updated!",
  "data": {
    "id": 123,
    "first_name": "John",
    "last_name": "Doe",
    "email": "student@example.com",
    "phone": "08012345678",
    "gender": "male",
    "profile_pic": "user1234567890.jpg",
    "school_id": 1,
    "dept_id": 5,
    "matric_no": "190101001",
    "adm_year": "2019"
  }
}
```

#### 9. Update Academic Information
**Endpoint:** `POST /profile/update-academic-info.php`

**Description:** Update academic information (department, matric number, admission year).

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "dept_id": 5,
  "matric_no": "190101001",
  "adm_year": "2019"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Academic information successfully updated!",
  "data": {
    "dept_id": 5,
    "matric_no": "190101001",
    "adm_year": "2019"
  }
}
```

**Note:** All fields are optional. The department must belong to the user's school.

#### 10. Change Password
**Endpoint:** `POST /profile/change-password.php`

**Description:** Change user password.

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "current_password": "oldpassword",
  "new_password": "newpassword"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Password successfully changed!"
}
```

#### 8. Delete Account
**Endpoint:** `POST /profile/delete-account.php`

**Description:** Deactivate user account.

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "password": "userpassword"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Account successfully deactivated."
}
```

---

### Reference Data Endpoints

These endpoints provide institutional data needed for registration and profile setup. **No authentication required.**

#### 9. Get Schools
**Endpoint:** `GET /reference/schools.php`

**Description:** Get list of all active schools with pagination.

**Query Parameters:**
- `page`: Page number (default: 1)
- `limit`: Results per page (default: 50, max: 100)

**Response (Success):**
```json
{
  "status": "success",
  "message": "Schools retrieved successfully",
  "data": {
    "schools": [
      {
        "id": 1,
        "name": "Federal University of Agriculture, Abeokuta",
        "code": "FUNAAB",
        "created_at": "2023-01-01 00:00:00"
      }
    ],
    "pagination": {
      "total": 1,
      "page": 1,
      "limit": 50,
      "total_pages": 1
    }
  }
}
```

#### 10. Get Faculties
**Endpoint:** `GET /reference/faculties.php?school_id={id}`

**Description:** Get list of active faculties for a specific school.

**Query Parameters:**
- `school_id`: School ID (required)
- `page`: Page number (default: 1)
- `limit`: Results per page (default: 50, max: 100)

**Response (Success):**
```json
{
  "status": "success",
  "message": "Faculties retrieved successfully",
  "data": {
    "faculties": [
      {
        "id": 1,
        "name": "Faculty of Science",
        "school_id": 1,
        "created_at": "2023-01-01 00:00:00"
      }
    ],
    "pagination": {
      "total": 1,
      "page": 1,
      "limit": 50,
      "total_pages": 1
    }
  }
}
```

#### 11. Get Departments
**Endpoint:** `GET /reference/departments.php?school_id={id}&faculty_id={id}`

**Description:** Get list of active departments for a specific school (optionally filtered by faculty).

**Query Parameters:**
- `school_id`: School ID (required)
- `faculty_id`: Faculty ID (optional, for filtering)
- `page`: Page number (default: 1)
- `limit`: Results per page (default: 100, max: 100)

**Response (Success):**
```json
{
  "status": "success",
  "message": "Departments retrieved successfully",
  "data": {
    "departments": [
      {
        "id": 5,
        "name": "Computer Science",
        "school_id": 1,
        "faculty_id": 1,
        "faculty_name": "Faculty of Science",
        "created_at": "2023-01-01 00:00:00"
      }
    ],
    "pagination": {
      "total": 1,
      "page": 1,
      "limit": 100,
      "total_pages": 1
    }
  }
}
```

---

### Materials/Manuals Endpoints

#### 12. List Materials
**Endpoint:** `GET /materials/list.php`

**Description:** Get list of available materials/manuals.

**Authentication:** Required

**Query Parameters:**
- `search` (optional): Search by title or course code
- `dept` (optional): Filter by department ID
- `faculty` (optional): Filter by faculty ID
- `page` (optional, default: 1): Page number
- `limit` (optional, default: 20, max: 100): Items per page

**Response (Success):**
```json
{
  "status": "success",
  "message": "Materials retrieved successfully",
  "data": {
    "materials": [
      {
        "id": 45,
        "title": "Introduction to Algorithms",
        "course_code": "CSC301",
        "price": 1500,
        "quantity": 50,
        "due_date": "2024-12-31",
        "dept": 5,
        "dept_name": "Computer Science",
        "faculty": 2,
        "faculty_name": "Science",
        "seller_name": "Jane Smith",
        "is_purchased": false,
        "created_at": "2024-01-15 10:30:00"
      }
    ],
    "pagination": {
      "total": 100,
      "page": 1,
      "limit": 20,
      "total_pages": 5
    }
  }
}
```

#### 13. Get Material Details
**Endpoint:** `GET /materials/details.php`

**Description:** Get detailed information about a specific material.

**Authentication:** Required

**Query Parameters:**
- `id` (required): Material ID

**Response (Success):**
```json
{
  "status": "success",
  "message": "Material details retrieved successfully",
  "data": {
    "id": 45,
    "title": "Introduction to Algorithms",
    "course_code": "CSC301",
    "price": 1500,
    "quantity": 50,
    "due_date": "2024-12-31",
    "status": "open",
    "dept": 5,
    "dept_name": "Computer Science",
    "faculty": 2,
    "faculty_name": "Science",
    "seller": {
      "id": 78,
      "name": "Jane Smith",
      "phone": "08098765432",
      "email": "jane@example.com"
    },
    "is_purchased": false,
    "purchase_info": null,
    "created_at": "2024-01-15 10:30:00"
  }
}
```

#### 14. Add to Cart
**Endpoint:** `POST /materials/cart-add.php`

**Description:** Add a material to cart.

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "material_id": 45
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Material added to cart successfully",
  "data": {
    "total_items": 3,
    "cart_items": [45, 67, 89]
  }
}
```

#### 15. Remove from Cart
**Endpoint:** `POST /materials/cart-remove.php`

**Description:** Remove a material from cart.

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "material_id": 45
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Material removed from cart successfully",
  "data": {
    "total_items": 2,
    "cart_items": [67, 89]
  }
}
```

#### 16. View Cart
**Endpoint:** `GET /materials/cart-view.php`

**Description:** View current cart contents.

**Authentication:** Required

**Response (Success):**
```json
{
  "status": "success",
  "message": "Cart retrieved successfully",
  "data": {
    "items": [
      {
        "id": 45,
        "title": "Introduction to Algorithms",
        "course_code": "CSC301",
        "price": 1500,
        "status": "open",
        "dept_name": "Computer Science",
        "seller_name": "Jane Smith"
      }
    ],
    "total_amount": 4500,
    "total_items": 3
  }
}
```

#### 14. List Purchased Materials
**Endpoint:** `GET /materials/purchased.php`

**Description:** Get list of purchased materials.

**Authentication:** Required

**Query Parameters:**
- `page` (optional, default: 1): Page number
- `limit` (optional, default: 20, max: 100): Items per page

**Response (Success):**
```json
{
  "status": "success",
  "message": "Purchased materials retrieved successfully",
  "data": {
    "materials": [
      {
        "id": 45,
        "title": "Introduction to Algorithms",
        "course_code": "CSC301",
        "price": 1500,
        "dept_name": "Computer Science",
        "seller_name": "Jane Smith",
        "ref_id": "NIVAS_1234567890_123_abc",
        "purchased_at": "2024-02-01 14:25:00"
      }
    ],
    "pagination": {
      "total": 15,
      "page": 1,
      "limit": 20,
      "total_pages": 1
    }
  }
}
```

---

### Payment Endpoints

#### 18. Initialize Payment
**Endpoint:** `POST /payment/init.php`

**Description:** Initialize payment for cart items.

**Authentication:** Required

**Response (Success):**
```json
{
  "status": "success",
  "message": "Payment initialized successfully",
  "data": {
    "tx_ref": "NIVAS_1234567890_123_abc",
    "payment_url": "https://checkout.flutterwave.com/...",
    "gateway": "flutterwave",
    "amount": 4500,
    "items": [
      {
        "type": "manual",
        "id": 45,
        "title": "Introduction to Algorithms",
        "price": 1500
      }
    ]
  }
}
```

#### 19. Verify Payment
**Endpoint:** `GET /payment/verify.php`

**Description:** Verify payment transaction.

**Authentication:** Required

**Query Parameters:**
- `tx_ref` (required): Transaction reference

**Response (Success):**
```json
{
  "status": "success",
  "message": "Payment verified and processed successfully",
  "data": {
    "status": "success",
    "tx_ref": "NIVAS_1234567890_123_abc",
    "amount": 4500,
    "processed_at": "2024-02-01 14:30:00"
  }
}
```

#### 17. Get Transactions
**Endpoint:** `GET /payment/transactions.php`

**Description:** Get user transaction history.

**Authentication:** Required

**Query Parameters:**
- `page` (optional, default: 1): Page number
- `limit` (optional, default: 20, max: 100): Items per page

**Response (Success):**
```json
{
  "status": "success",
  "message": "Transactions retrieved successfully",
  "data": {
    "transactions": [
      {
        "id": 234,
        "ref_id": "NIVAS_1234567890_123_abc",
        "amount": 4500,
        "status": "successful",
        "gateway_ref": "FLW_REF_123456",
        "items": [
          {
            "type": "manual",
            "id": 45,
            "title": "Introduction to Algorithms",
            "course_code": "CSC301",
            "price": 1500
          }
        ],
        "created_at": "2024-02-01 14:30:00"
      }
    ],
    "pagination": {
      "total": 10,
      "page": 1,
      "limit": 20,
      "total_pages": 1
    }
  }
}
```

---

### Support Endpoints

#### 21. Create Support Ticket
**Endpoint:** `POST /support/create-ticket.php`

**Description:** Create a new support ticket.

**Authentication:** Required

**Request Body (Multipart Form Data):**
- `subject` (required): Ticket subject
- `message` (required): Ticket message
- `category` (optional): Category (default: "Technical and Other Issues")
- `attachment` (optional): File attachment (PDF, JPG, JPEG, PNG)

**Response (Success):**
```json
{
  "status": "success",
  "message": "Support ticket created successfully",
  "data": {
    "ticket_id": 56,
    "ticket_code": "ABC12345",
    "subject": "Cannot access materials",
    "category": "Technical and Other Issues",
    "status": "open",
    "created_at": "2024-02-05 09:15:00"
  }
}
```

#### 22. List Support Tickets
**Endpoint:** `GET /support/list-tickets.php`

**Description:** Get list of user support tickets.

**Authentication:** Required

**Query Parameters:**
- `status` (optional): Filter by status (open, closed, in_progress)
- `page` (optional, default: 1): Page number
- `limit` (optional, default: 20, max: 100): Items per page

**Response (Success):**
```json
{
  "status": "success",
  "message": "Tickets retrieved successfully",
  "data": {
    "tickets": [
      {
        "id": 56,
        "code": "ABC12345",
        "subject": "Cannot access materials",
        "category": "Technical and Other Issues",
        "status": "open",
        "message_count": 3,
        "latest_message": "We're looking into this issue...",
        "created_at": "2024-02-05 09:15:00",
        "updated_at": "2024-02-05 10:30:00"
      }
    ],
    "pagination": {
      "total": 5,
      "page": 1,
      "limit": 20,
      "total_pages": 1
    }
  }
}
```

#### 23. Get Ticket Details
**Endpoint:** `GET /support/ticket-details.php`

**Description:** Get detailed information about a support ticket.

**Authentication:** Required

**Query Parameters:**
- `id` (optional): Ticket ID
- `code` (optional): Ticket code

**Response (Success):**
```json
{
  "status": "success",
  "message": "Ticket details retrieved successfully",
  "data": {
    "id": 56,
    "code": "ABC12345",
    "subject": "Cannot access materials",
    "category": "Technical and Other Issues",
    "status": "open",
    "messages": [
      {
        "id": 123,
        "user_id": 789,
        "user_name": "John Doe",
        "user_role": "student",
        "message": "I cannot access the materials I purchased.",
        "attachment": null,
        "created_at": "2024-02-05 09:15:00"
      },
      {
        "id": 124,
        "user_id": null,
        "user_name": "Support Team",
        "user_role": "admin",
        "message": "We're looking into this issue. Could you provide more details?",
        "attachment": null,
        "created_at": "2024-02-05 10:30:00"
      }
    ],
    "created_at": "2024-02-05 09:15:00",
    "updated_at": "2024-02-05 10:30:00"
  }
}
```

#### 24. Reply to Ticket
**Endpoint:** `POST /support/reply.php`

**Description:** Reply to an existing support ticket.

**Authentication:** Required

**Request Body (Multipart Form Data):**
- `ticket_id` (required): Ticket ID
- `message` (required): Reply message
- `attachment` (optional): File attachment (PDF, JPG, JPEG, PNG)

**Response (Success):**
```json
{
  "status": "success",
  "message": "Reply added successfully",
  "data": {
    "ticket_id": 56,
    "message": "Here are the details you requested...",
    "created_at": "2024-02-05 11:00:00"
  }
}
```

---

## Error Responses

All error responses follow this format:

```json
{
  "status": "error",
  "message": "Error description"
}
```

Common HTTP status codes:
- `400` - Bad Request (missing or invalid parameters)
- `401` - Unauthorized (not logged in)
- `403` - Forbidden (access denied)
- `404` - Not Found (resource not found)
- `405` - Method Not Allowed (wrong HTTP method)
- `500` - Internal Server Error

---

## Access Control

This API is designed exclusively for students (role: `student` or `hoc`). All authenticated endpoints verify that the logged-in user has one of these roles. Access from other roles will result in a 403 Forbidden error.

## Domain Restriction

This API is only accessible through `api.nivasity.com`. Requests from other domains will be blocked.

## CORS Headers

The API includes CORS headers to allow cross-origin requests from authorized mobile applications.

---

## Notes

1. All dates are in `Y-m-d H:i:s` format (Africa/Lagos timezone)
2. All monetary amounts are in Nigerian Naira (NGN)
3. File uploads are limited to PDF and image files (JPG, JPEG, PNG, GIF)
4. Maximum file size for uploads is determined by server configuration
5. Session cookies are used for authentication - ensure your HTTP client supports cookies

---

## Support

For API support, contact: support@nivasity.com
