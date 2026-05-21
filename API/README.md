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
- `email`, `password`, `first_name`, `last_name`, `gender`, and `school_id` are required at registration.
- `phone` is optional and can be supplied later if the student chooses.
- Account is created with status='unverified' until OTP is verified
- OTP expires in 10 minutes (600 seconds)
- Academic information (department, matric number, admission year) is NOT required at registration
- Use `/auth/verify-otp.php` to complete registration and get tokens

#### 2. Verify OTP
**Endpoint:** `POST /auth/verify-otp.php`

**Description:** Unified endpoint to verify OTP for both registration and password reset. Returns JWT tokens for registration, or reset token for password reset.

**Request Body (JSON):**
```json
{
  "email": "student@example.com",
  "otp": "123456",
  "reason": "registration"
}
```

**Parameters:**
- `email` (required): User's email address
- `otp` (required): 6-digit OTP code
- `reason` (optional): Purpose of verification - `"registration"` (default) or `"password_reset"`

**Response (Success - Registration):**
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
      "status": "unverified"
    }
  }
}
```

**Response (Success - Password Reset):**
```json
{
  "status": "success",
  "message": "OTP verified successfully. Use the reset token to update your password.",
  "data": {
    "reset_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 600
  }
}
```

**Error Responses:**
- `404` - Invalid email address
- `400` - Account already verified (use login instead) [registration only]
- `400` - Invalid or expired OTP

#### 3. Resend Registration OTP
**Endpoint:** `POST /auth/resend-otp.php`

**Description:** Resend verification OTP for unverified accounts. Use this if the user didn't receive the initial OTP or if it expired.

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
  "message": "Verification code sent successfully! Please check your email inbox.",
  "data": {
    "email": "student@example.com",
    "message": "Use the verify-otp endpoint to complete registration",
    "expires_in": 600
  }
}
```

**Error Responses:**
- `404` - No account found with this email address
- `400` - Account already verified (use login instead)

**Note:**
- Only works for accounts with status='unverified'
- Deletes any previous unused OTPs
- Generates a new 6-digit OTP that expires in 10 minutes

#### 4. Login
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
    "school_id": 1,
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

#### Google Auth
**Endpoint:** `POST /auth/google-auth.php`

**Description:** Login or register a student account using a Google ID token.

**Request Body (JSON):**
```json
{
  "id_token": "google_id_token",
  "school_id": 1
}
```

**Parameters:**
- `id_token` (required): Google ID token from client OAuth flow
- `school_id` (required for first-time users): Active school ID for new account creation
- `phone` (optional): Used when creating a new account
- `gender` (optional): Used when creating a new account

**Response (Success):**
- Existing user: `200` with JWT tokens and user profile
- New user: `201` with JWT tokens and newly created profile (`status` is `unverified`)

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

**Note:** 
- OTP expires in 10 minutes (600 seconds)
- After receiving OTP, use `/auth/verify-otp.php` with `reason: "password_reset"` to get a reset token

#### Password Reset Flow Overview:
The password reset process is a **3-step flow** for enhanced security:

1. **Request OTP** -> Call `/auth/forgot-password.php` with email
   - User receives 6-digit OTP via email
   
2. **Verify OTP** -> Call `/auth/verify-otp.php` with email, OTP, and `reason: "password_reset"`
   - Returns single-use reset token (expires in 10 minutes)
   
3. **Reset Password** -> Call `/auth/reset-password.php` with token and new password
   - Password is updated, user can login with new credentials

This approach separates OTP verification from password update for better security.

#### 6. Reset Password
**Endpoint:** `POST /auth/reset-password.php`

**Description:** Reset password using the reset token obtained from verify-otp endpoint.

**Request Body (JSON):**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
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
- `400` - Missing required fields
- `401` - Invalid or expired reset token
- `404` - User not found
- `500` - Failed to reset password

**Note:** 
- Reset token is single-use only and expires in 10 minutes
- Get reset token from `/auth/verify-otp.php` with `reason: "password_reset"`

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
- `profile_pic`: Profile picture file (optional, JPG/PNG/GIF)

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

#### Request Email Change OTP
**Endpoint:** `POST /profile/request-email-change.php`

**Description:** Send a 6-digit OTP to a new email address before changing the authenticated user's account email.

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "new_email": "newaddress@example.com"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "OTP sent to your new email address. Please check your inbox.",
  "data": {
    "new_email": "newaddress@example.com",
    "expires_in": 600
  }
}
```

#### Verify Email Change OTP
**Endpoint:** `POST /profile/verify-email-change.php`

**Description:** Verify the OTP sent to a pending new email address and update the authenticated user's account email.

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "new_email": "newaddress@example.com",
  "otp": "123456"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Email address updated successfully.",
  "data": {
    "old_email": "student@example.com",
    "email": "newaddress@example.com"
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

#### Profile Stats
**Endpoint:** `GET /profile/stats.php`

**Description:** Get dashboard statistics for the authenticated student.

**Authentication:** Required

**Response (Success):**
```json
{
  "status": "success",
  "message": "Profile statistics retrieved successfully",
  "data": {
    "total_materials": 12,
    "total_spent": 18500,
    "pending_orders": 1
  }
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

#### Support Contact
**Endpoint:** `GET /reference/support.php`

**Description:** Get public support contact information (WhatsApp, email, phone).

**Authentication:** Not required

**Response (Success):**
```json
{
  "status": "success",
  "message": "Support contact information retrieved successfully",
  "data": {
    "contact": {
      "whatsapp": "+2348012345678",
      "email": "support@nivasity.com",
      "phone": "+2348012345678",
      "updated_at": "2026-02-21 12:00:00"
    }
  }
}
```

---

### Materials/Manuals Endpoints

#### 12. List Materials
**Endpoint:** `GET /materials/list.php`

**Description:** Get list of available materials/manuals filtered by user's school and academic scope.

**Authentication:** Required

**Query Parameters:**
- `search` (optional): Search by title or course code
- `sort` (optional, default: `recommended`): Sort order
  - `recommended` - Sort by latest due date (soonest deadlines first) **[DEFAULT]**
  - `low-high` - Sort by price (lowest to highest)
  - `high-low` - Sort by price (highest to lowest)
- `level` (optional): Filter by exact level value (e.g., `100`, `200`, `300`)
- `page` (optional, default: 1): Page number
- `limit` (optional, default: 20, max: 100): Items per page

**Filtering Rules:**
- Materials are filtered by **user's school**
- If user department and faculty are available, results include:
  - Department-specific materials (`dept = user_dept`)
  - Faculty-level shared materials (`dept = 0` with matching faculty)
- Only `open` materials are returned, and due dates older than 24 hours are excluded

**Response (Success):**
```json
{
  "status": "success",
  "message": "Materials retrieved successfully",
  "data": {
    "materials": [
      {
        "id": 45,
        "code": "MAN-2024-001",
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

**Example Requests:**
```bash
# Get recommended materials (default - sorted by soonest due date)
GET /materials/list.php

# Get materials sorted by price (low to high)
GET /materials/list.php?sort=low-high

# Get materials sorted by price (high to low)
GET /materials/list.php?sort=high-low

# Filter materials by level
GET /materials/list.php?level=300

# Search and filter by level
GET /materials/list.php?search=algorithm&level=300
```

#### 13. Get Material Details
**Endpoint:** `GET /materials/details.php`

**Description:** Get detailed information about a specific material. Supports lookup by ID or code.

**Authentication:** Required

**Query Parameters (one required):**
- `id` (optional): Material ID
- `code` (optional): Material code (e.g., `MAN-2024-001`)

**Filtering Rules:**
- Materials are filtered by **user's school only**
- Allows viewing materials from other departments in your school
- Enables cross-department discovery when you have a code/link

**Response (Success):**
```json
{
  "status": "success",
  "message": "Material details retrieved successfully",
  "data": {
    "id": 45,
    "code": "MAN-2024-001",
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

**Example Requests:**
```bash
# Get material by ID
GET /materials/details.php?id=45

# Get material by code
GET /materials/details.php?code=MAN-2024-001
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

**Description:** Get cart contents with detailed pricing breakdown for both gateway checkout and wallet checkout.

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
        "dept": 5,
        "dept_name": "Computer Science",
        "host_faculty": 2,
        "host_faculty_name": "Faculty of Science",
        "level": "300",
        "seller_name": "Dr. John Smith"
      }
    ],
    "subtotal": 5000,
    "charge": 100,
    "total_amount": 5100,
    "total_items": 3,
    "wallet": {
      "has_wallet": true,
      "balance": 7200,
      "wallet_charge": 50,
      "wallet_total_amount": 5050,
      "can_pay_with_wallet": true
    }
  }
}
```

**Note:** 
- `subtotal` - Sum of all item prices
- `charge` - Gateway processing fees (calculated using active gateway's fee structure)
- `total_amount` - Final amount to be charged (subtotal + charge)
- `wallet.balance` - Current Nivasity Wallet balance
- `wallet.wallet_charge` - Wallet-specific handling fee derived from wallet fee thresholds and capped below gateway fee
- `wallet.wallet_total_amount` - Amount that will be debited if the user pays with wallet
- `wallet.can_pay_with_wallet` - Whether the current wallet balance covers the wallet total amount

#### 17. List Purchased Materials
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
        "purchased_at": "2024-02-01 14:25:00",
        "is_external_payment": false,
        "payment_source": "nivasity",
        "payment_source_label": "Paid on Nivasity",
        "payment_source_note": ""
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

**Response Fields:**
- `is_external_payment`: `true` when the material was recorded as paid outside Nivasity
- `payment_source`: `nivasity` for regular platform purchases, `external` for outside-Nivasity payments
- `payment_source_label`: Human-readable payment source label for UI display
- `payment_source_note`: Extra note for external purchases

---

### App Endpoints

#### App Update Config
**Endpoint:** `GET /app/update-config.php`

**Description:** Retrieve the latest mobile app update configuration for Android and iOS. The endpoint reads the newest row from the `app_update_configs` table and returns soft-update and force-update settings for both platforms.

**Authentication:** Not required

**Response (Success):**
```json
{
  "status": "success",
  "message": "App update configuration retrieved successfully",
  "data": {
    "android": {
      "latestVersion": "1.0.1",
      "minimumVersion": "1.0.0",
      "storeUrl": "https://play.google.com/store/apps/details?id=com.nivasity.app",
      "title": "Update available",
      "message": "A newer version is available.",
      "required": false
    },
    "ios": {
      "latestVersion": "1.0.1",
      "minimumVersion": "1.0.0",
      "storeUrl": "https://apps.apple.com/app/id1234567890",
      "title": "Update available",
      "message": "A newer version is available.",
      "required": false
    }
  }
}
```

**Response Fields:**
- `latestVersion`: Used for soft update prompts when the installed app version is behind the newest available build
- `minimumVersion`: Used to force an update when the installed app version is below the minimum supported build
- `storeUrl`: Destination URL to open the relevant app store listing
- `title`: Title text for the update prompt
- `message`: Body text for the update prompt
- `required`: Explicit force-update flag for the platform

---

### Payment Endpoints

#### 18. Get Payment Gateway
**Endpoint:** `GET /payment/gateway.php`

**Description:** Get active payment gateway information and the currently allowed checkout channels.

**Authentication:** Not required (public endpoint)

**Response (Success):**
```json
{
  "status": "success",
  "message": "Active payment gateway retrieved",
  "data": {
    "active": "paystack",
    "available": ["paystack", "flutterwave"],
    "status": true,
    "message": "",
    "gateway_enabled": true,
    "wallet_enabled": true,
    "allowed_payment_channels": ["gateway", "wallet"]
  }
}
```

**Response Fields:**
- `status`: `true` when hosted gateway checkout is available.
- `message`: Freeze message for the hosted gateway checkout path when gateway payments are paused.
- `gateway_enabled`: Whether hosted gateway checkout is available.
- `wallet_enabled`: Whether wallet checkout is available.
- `allowed_payment_channels`: Checkout channels that can currently be used by clients.

#### 19. Initialize Payment
**Endpoint:** `POST /payment/init.php`

**Description:** Initialize checkout for cart items. Supports both hosted gateway checkout and direct Nivasity Wallet checkout. Gateway payments are collected by Nivasity first and then credited into the internal school payable ledger for later settlement.

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "redirect_url": "https://yourapp.com/payment-callback",
  "payment_channel": "gateway"
}
```

**Parameters:**
- `redirect_url` (optional): Custom URL where users will be redirected after payment verification. The gateway callback itself always points to `/payment/callback.php`, and this value is forwarded in payment metadata.
- `payment_channel` (optional): `gateway` or `wallet`. Defaults to `gateway`.
- `wallet_pin` (required when `payment_channel = wallet`): The user's 4-digit Wallet PIN used to authorize wallet checkout.

**Gateway Freeze Behavior:**
- If the payment freeze config is enabled with `PAYMENT_FREEZE_SCOPE = 'gateway'`, requests with `payment_channel = gateway` return `403` and wallet checkout remains available.
- The default gateway-freeze error message is: `Gateway payments are currently paused. Only wallet payments are allowed right now.`

**Current Payment Model:**
- Gateway checkout still uses the active provider's hosted payment page
- Wallet checkout debits the user's Nivasity Wallet immediately and returns success in the same API call
- Gateway payments no longer split directly to schools or sellers at checkout time
- After successful purchase processing, the school's payable balance is credited internally and later settled by the settlement cron
- Refund reservations are tracked against the internal school share before final settlement

**Gateway Checkout Response (Success):**
```json
{
  "status": "success",
  "message": "Payment initialized successfully",
  "data": {
    "tx_ref": "nivas_123_1703689200",
    "payment_url": "https://checkout.paystack.com/...",
    "gateway": "paystack",
    "subtotal": 5000,
    "charge": 100,
    "total_amount": 5100,
    "internal_settlement_mode": true,
    "refund_reserved": 0,
    "school_share_before": 5000,
    "school_share_after": 5000,
    "items": [
      {
        "type": "manual",
        "id": 45,
        "title": "Introduction to Algorithms",
        "price": 1500,
        "seller_id": 67
      }
    ]
  }
}
```

**Wallet Checkout Response (Success):**
```json
{
  "status": "success",
  "message": "Wallet payment completed successfully",
  "data": {
    "tx_ref": "nivas_123_1703689200",
    "gateway": "nivasity",
    "payment_channel": "wallet",
    "subtotal": 5000,
    "charge": 50,
    "total_amount": 5050,
    "refund_applied": 0,
    "wallet_balance_after": 2200,
    "items": [
      {
        "type": "manual",
        "id": 45,
        "title": "Introduction to Algorithms",
        "price": 1500,
        "seller_id": 67
      }
    ]
  }
}
```

**Gateway Freeze Response (Error):**
```json
{
  "status": "error",
  "message": "Gateway payments are currently paused. Only wallet payments are allowed right now."
}
```

**Response Fields:**
- `tx_ref`: Transaction reference for tracking the payment
- `payment_url`: Hosted checkout URL where user completes payment. Present only for `gateway` checkout.
- `redirect_url` (optional): Returned only when you passed `redirect_url` in request body
- `gateway`: Payment provider used for the flow (`paystack`, `flutterwave`, `interswitch`, or `nivasity` for wallet checkout)
- `payment_channel`: `gateway` or `wallet`
- `subtotal`: Total cost of items before gateway charges
- `charge`: Handling fee for the selected checkout path. Wallet checkout uses wallet fee thresholds and is capped below the active gateway fee.
- `total_amount`: Final amount to be paid or debited
- `internal_settlement_mode`: `true` when the purchase is using the internal school-settlement ledger
- `refund_reserved`: Amount reserved from the school's future payable for pending refunds
- `school_share_before`: School payable share before refund reservation is applied
- `school_share_after`: School payable share after refund reservation is applied
- `wallet_balance_after`: Present for wallet checkout after successful debit
- `items`: Array of cart items with details

**Mobile App Integration:**
For mobile apps, provide a custom `redirect_url` that uses your app's deep link scheme:
```json
{
  "redirect_url": "myapp://payment-callback"
}
```

This allows the payment gateway to redirect back to your mobile app after the user completes or cancels payment on the hosted checkout page.

**Wallet Checkout Notes:**
- Wallets are not auto-created; the client must call `POST /wallet/create.php` first
- Wallet-funded checkout requires a valid 4-digit Wallet PIN
- Before wallet debit, the API attempts a Paystack dedicated-account sync to capture missed funding credits
- On insufficient balance or missing wallet, the API returns an error with HTTP `422`
- Wallet-funded purchases are recorded with `payment_channel = wallet`

#### 20. Verify Payment
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
    "refund_applied": 0,
    "processed_at": "2024-02-01 14:30:00",
    "date_formatted": "1st February, 2024",
    "payer_name": "John Doe",
    "matric_no": "190101001",
    "payer_name_with_matric": "John Doe (Matric No.: 190101001)"
  }
}
```

#### 21. Get Transactions
**Endpoint:** `GET /payment/transactions.php`

**Description:** Get user material transaction history, including externally recorded material purchases without a hosted Nivasity transaction row.

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
        "refund": 0,
        "status": "successful",
        "medium": "PAYSTACK",
        "payment_channel": "gateway",
        "transaction_context": "purchase",
        "gateway_ref": "FLW_REF_123456",
        "is_external_payment": false,
        "payment_source": "nivasity",
        "payment_source_label": "Paid on Nivasity",
        "payment_source_note": "",
        "items": [
          {
            "type": "manual",
            "id": 45,
            "title": "Introduction to Algorithms",
            "course_code": "CSC301",
            "price": 1500,
            "is_external_payment": false,
            "payment_source": "nivasity",
            "payment_source_label": "Paid on Nivasity",
            "payment_source_note": ""
          }
        ],
        "created_at": "2024-02-01 14:30:00",
        "date_formatted": "1st February, 2024",
        "payer_name": "John Doe",
        "matric_no": "190101001",
        "payer_name_with_matric": "John Doe (Matric No.: 190101001)"
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

**Response Fields:**
- `medium`: Payment provider or source that produced the transaction record
- `payment_channel`: `gateway` for hosted checkout flows, `wallet` for wallet-funded purchases and wallet funding credits, `external_manual` for manually recorded outside-Nivasity purchases
- `transaction_context`: `purchase` for actual material/event purchases, `wallet_funding` for dedicated-account wallet topups
- `is_external_payment`: `true` when the reference belongs to a manually recorded outside-Nivasity material payment
- `payment_source`: `nivasity` for regular platform purchases, `external` for outside-Nivasity payments
- `payment_source_label`: Human-readable payment source label for UI display
- `payment_source_note`: Extra note for external purchases

---

### Wallet Endpoints

#### Create Wallet
**Endpoint:** `POST /wallet/create.php`

**Description:** Create a Nivasity Wallet explicitly for the authenticated user. Wallets are never auto-provisioned.

**Authentication:** Required

**Response (Success):**
```json
{
  "status": "success",
  "message": "Wallet created successfully",
  "data": {
    "created": true,
    "wallet": {
      "id": 12,
      "user_id": 123,
      "school_id": 5,
      "status": "active",
      "balance": 0,
      "currency": "NGN",
      "provider": "paystack",
      "provider_account_id": "1234567",
      "account_name": "John Doe",
      "account_number": "0123456789",
      "bank_name": "Wema Bank",
      "bank_slug": "wema-bank"
    }
  }
}
```

**Behavior:**
- If a wallet already exists, the endpoint still returns success with `created: false`
- The wallet includes the dedicated virtual account details to display in the mobile app

#### Wallet Summary
**Endpoint:** `GET /wallet/summary.php`

**Description:** Retrieve wallet availability and current wallet details without auto-creating a wallet.

**Authentication:** Required

**Response (Success):**
```json
{
  "status": "success",
  "message": "Wallet summary retrieved successfully",
  "data": {
    "has_wallet": true,
    "has_pin": true,
    "wallet": {
      "id": 12,
      "user_id": 123,
      "school_id": 5,
      "status": "active",
      "balance": 7200,
      "currency": "NGN",
      "provider": "paystack",
      "provider_account_id": "1234567",
      "account_name": "John Doe",
      "account_number": "0123456789",
      "bank_name": "Wema Bank",
      "bank_slug": "wema-bank",
      "account_status": "active"
    }
  }
}
```

**Response Fields:**
- `has_wallet`: Whether the authenticated user already has a provisioned wallet
- `has_pin`: Whether the authenticated user has already configured a Wallet PIN

#### Wallet Transactions
**Endpoint:** `GET /wallet/transactions.php`

**Description:** List wallet ledger transactions for the authenticated user. The endpoint returns credits and debits in reverse chronological order with a fixed page size of 20 records.

**Authentication:** Required

**Query Parameters:**
- `page` (optional): Page number starting from `1`. Default is `1`

**Response (Success):**
```json
{
  "status": "success",
  "message": "Wallet transactions retrieved successfully",
  "data": {
    "wallet": {
      "id": 12,
      "user_id": 123,
      "school_id": 5,
      "status": "active",
      "balance": 7200,
      "currency": "NGN",
      "provider": "paystack",
      "provider_account_id": "1234567",
      "account_name": "John Doe",
      "account_number": "0123456789",
      "bank_name": "Wema Bank",
      "bank_slug": "wema-bank",
      "account_status": "active"
    },
    "has_wallet": true,
    "has_pin": true,
    "transactions": [
      {
        "id": 91,
        "entry_type": "credit",
        "direction": "credit",
        "amount": 5000,
        "signed_amount": 5000,
        "status": "posted",
        "reference": "wallet_funding:PSK_REF_123",
        "provider_reference": "PSK_REF_123",
        "display_reference": "PSK_REF_123",
        "description": "Wallet funded via dedicated account",
        "balance_before": 2200,
        "balance_after": 7200,
        "created_at": "2026-04-11 08:15:42",
        "display_date": "11 Apr, 2026 08:15 am"
      },
      {
        "id": 90,
        "entry_type": "debit",
        "direction": "debit",
        "amount": 1800,
        "signed_amount": -1800,
        "status": "posted",
        "reference": "wallet_purchase:nivas_123_1712817300",
        "provider_reference": "nivas_123_1712817300",
        "display_reference": "nivas_123_1712817300",
        "description": "Wallet purchase",
        "balance_before": 4000,
        "balance_after": 2200,
        "created_at": "2026-04-10 18:04:17",
        "display_date": "10 Apr, 2026 06:04 pm"
      }
    ],
    "pagination": {
      "total": 34,
      "page": 1,
      "limit": 20,
      "total_pages": 2
    }
  }
}
```

**Response Fields:**
- `entry_type`: Raw wallet ledger entry type such as `credit`, `debit`, `refund`, or `fee`
- `direction`: Normalized transaction direction. One of `credit`, `debit`, or `neutral`
- `signed_amount`: Positive for credit-like entries and negative for debit-like entries
- `pagination.limit`: Always `20` for this endpoint

#### Wallet PIN Management
**Endpoint:** `POST /wallet/pin.php`

**Description:** Send a Wallet PIN verification code to email, verify that code, then create/update the authenticated user's 4-digit Wallet PIN with the returned verification token.

**Authentication:** Required

**Request Body (JSON) to Send Code:**
```json
{
  "action": "send_code"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "A Wallet PIN code has been sent to your email.",
  "data": {
    "status": "sent",
    "purpose": "create",
    "expires_at": "2026-04-09 14:35:00"
  }
}
```

**Request Body (JSON) to Verify Code:**
```json
{
  "action": "verify_code",
  "code": "123456"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Wallet PIN code verified successfully.",
  "data": {
    "status": "verified",
    "purpose": "create",
    "pin_token": "5b1f42c7baf2c7d4f98d6e4830ce8d35adf74f2a3a6d4f11",
    "token_expires_at": "2026-04-09 14:45:00"
  }
}
```

**Request Body (JSON) to Save PIN:**
```json
{
  "action": "save_pin",
  "pin_token": "5b1f42c7baf2c7d4f98d6e4830ce8d35adf74f2a3a6d4f11",
  "pin": "1234",
  "confirm_pin": "1234"
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Wallet PIN saved successfully.",
  "data": {
    "status": "saved",
    "has_pin": true
  }
}
```

**Behavior:**
- `action = send_code` emails a 6-digit verification code to the authenticated user
- `purpose` is `create` for first-time Wallet PIN setup and `update` when a Wallet PIN already exists
- `action = verify_code` validates the email code and returns a short-lived `pin_token`
- `action = save_pin` requires that `pin_token` plus matching 4-digit PIN values
- The authenticated user must already have a wallet before PIN setup or update is allowed

#### Refresh Wallet Credits
**Endpoint:** `POST /wallet/refresh-credits.php`

**Description:** Re-sync Paystack dedicated-account funding transactions for the authenticated user's wallet. This is useful if a credit webhook was delayed or missed.

**Authentication:** Required

**Response (Success):**
```json
{
  "status": "success",
  "message": "Wallet funding refresh completed",
  "data": {
    "status": "ok",
    "processed": 3,
    "posted": 1
  }
}
```

**Response Fields:**
- `processed`: Number of provider funding rows inspected
- `posted`: Number of new wallet credits actually applied during this refresh

#### Bulk Refresh Wallet Credits
**Endpoint:** `GET /wallet/refresh-credits-bulk.php`
**Endpoint:** `POST /wallet/refresh-credits-bulk.php`

**Description:** Runs a Paystack DVA reconciliation sweep for all known wallet virtual accounts, or for a specific user when filtered. Intended for cron recovery of missed wallet-credit webhooks.

**Authentication:** Not required by default. If `NIVASITY_WALLET_CREDIT_CRON_TOKEN` is configured, pass `token` with the request.

**Optional Parameters:**
- `user_id`: Limit the sweep to one user
- `limit`: Limit how many wallets are checked in one run
- `token`: Optional cron token for protected HTTP runs

**Response (Success):**
```json
{
  "status": "success",
  "message": "Bulk wallet credit refresh completed",
  "data": {
    "summary": {
      "wallets_checked": 12,
      "wallets_with_new_credits": 3,
      "processed_rows": 14,
      "posted_rows": 3,
      "failed_wallets": 0
    },
    "results": [
      {
        "wallet_id": 15,
        "user_id": 11988,
        "status": "ok",
        "processed": 2,
        "posted": 1,
        "message": "Wallet funding sync completed successfully."
      }
    ]
  }
}
```

**Notes:**
- Supports CLI execution for cron jobs
- Uses the same idempotent wallet funding posting flow as the webhook path
- Filters out purchase references so checkout payments are not misclassified as wallet credits

---

### Support Endpoints

#### 22. Create Support Ticket
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

#### 23. List Support Tickets
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

#### 24. Get Ticket Details
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

#### 25. Reply to Ticket
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

### Additional Implemented Endpoints

#### Payment Callback
**Endpoint:** `GET /payment/callback.php`

**Description:** Gateway callback endpoint used after hosted checkout. Verifies and processes payment, then either redirects to `redirect_url` (if provided in metadata) or returns JSON.

**Authentication:** Not required

**Query Parameters:**
- `tx_ref` (required): Transaction reference

**Response (Success, when no `redirect_url` is provided):**
```json
{
  "status": "success",
  "message": "Payment verified and processed successfully",
  "data": {
    "status": "success",
    "tx_ref": "NIVAS_1234567890_123_abc",
    "amount": 4500,
    "refund_applied": 0,
    "processed_at": "2024-02-01 14:30:00",
    "date_formatted": "1st February, 2024",
    "payer_name": "John Doe",
    "matric_no": "190101001",
    "payer_name_with_matric": "John Doe (Matric No.: 190101001)"
  }
}
```

#### Bulk Verify Pending Payments
**Endpoint:** `GET /payment/verify-bulk.php`
**Endpoint:** `POST /payment/verify-bulk.php`

**Description:** Bulk verification utility for pending cart payments. Used for scheduled reconciliation and maintenance.

**Authentication:** Not required

**Notes:**
- `POST /payment/verify-bulk.php` is also supported for filtered checks
- Supports CLI execution for cron jobs

#### Process School Settlements
**Endpoint:** `GET /payment/process-settlements.php`
**Endpoint:** `POST /payment/process-settlements.php`

**Description:** Runs the school settlement sweep for internal payable balances.

**Authentication:** Not required by default. If `NIVASITY_SETTLEMENT_CRON_TOKEN` is configured, pass `token` with the request.

**Notes:**
- Runs only on Friday unless `force=1` is supplied
- Settles at most `₦8,000,000` per school on each trigger
- Any unpaid remainder stays pending for the next trigger
- Supports CLI execution for cron jobs

**Optional Parameters:**
- `school_id`: Limit settlement to one school
- `limit`: Limit how many schools are scanned in one run
- `dry_run`: Preview allocations without creating transfers
- `force`: Allow execution outside Friday for testing or recovery
- `scheduled_for`: Override the settlement date using `YYYY-MM-DD`
- `token`: Optional cron token for protected HTTP runs

#### Repair School Ledger
**Endpoint:** `GET /payment/repair-school-ledger.php`
**Endpoint:** `POST /payment/repair-school-ledger.php`

**Description:** Scans successful purchase transactions older than a safety window and repairs any missing `school_payable_ledger` rows, including the corresponding `school_internal_wallets` balances.

**Authentication:** Not required by default. If `NIVASITY_LEDGER_REPAIR_CRON_TOKEN` is configured, pass `token` with the request.

**Notes:**
- Supports CLI execution for cron jobs
- Default safety window is `10` minutes, so freshly written transactions are skipped
- Only scans transactions created on or after `2026-04-18 20:00:00`
- Repairs only purchase-like transactions and ignores wallet funding rows

**Optional Parameters:**
- `school_id`: Limit repair to one school
- `ref_id`: Repair one specific transaction reference
- `limit`: Limit how many refs are checked in one run
- `dry_run`: Preview candidate refs without creating ledger rows
- `older_than_minutes`: Minimum transaction age before repair is attempted; defaults to `10`
- `token`: Optional cron token for protected HTTP runs

**Response (Success):**
```json
{
  "status": "success",
  "message": "School ledger repair sweep completed",
  "data": {
    "summary": {
      "tracked_from": "2026-04-18 20:00:00",
      "refs_checked": 4,
      "repaired": 2,
      "already_present": 0,
      "unresolved": 0,
      "missing_transaction": 0,
      "failed": 0,
      "dry_run": 0
    },
    "results": [
      {
        "ref_id": "nivas_11988_1776702258882",
        "transaction_id": 12345,
        "user_id": 11988,
        "status": "created",
        "payable_amount": 2500,
        "message": "School payable ledger repaired"
      }
    ]
  }
}
```

#### Notifications: List Inbox
**Endpoint:** `GET /notifications/list.php`

**Description:** Get paginated notifications inbox and unread count for authenticated user.

**Authentication:** Required

**Query Parameters:**
- `page` (optional, default: 1)
- `limit` (optional, default: 50, max: 100)
- `end_date` (optional): `YYYY-MM-DD` or `YYYY-MM-DD HH:MM:SS`

#### Notifications: Mark One as Read
**Endpoint:** `POST /notifications/mark-read.php`

**Description:** Mark a single notification as read.

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "id": 123
}
```

#### Notifications: Mark All as Read
**Endpoint:** `POST /notifications/mark-all-read.php`

**Description:** Mark all unread notifications as read for authenticated user.

**Authentication:** Required

#### Notifications: Register Device
**Endpoint:** `POST /notifications/register-device.php`

**Description:** Register or update an Expo push token for authenticated user.

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "expo_push_token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]",
  "platform": "android",
  "app_version": "1.0.0"
}
```

#### Notifications: Unregister Device
**Endpoint:** `POST /notifications/unregister-device.php`

**Description:** Disable a registered Expo push token for authenticated user.

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "expo_push_token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]"
}
```

#### Notifications: Admin Send
**Endpoint:** `POST /notifications/admin/send.php`

**Description:** Admin-only notification dispatch endpoint (single user, multiple users, school-wide, or broadcast).

**Authentication:** Uses admin credentials in request body (not Bearer token)

**Request Body (JSON):**
```json
{
  "email": "admin@example.com",
  "password": "md5_hash_of_password",
  "title": "Important Update",
  "body": "Message body",
  "type": "general",
  "broadcast": true
}
```

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

Domain restriction can be enforced at web-server level. In this repository, the host restriction rule exists in `API/.htaccess` but is currently commented out for flexibility across environments.

## CORS Headers

The API includes CORS headers to allow cross-origin requests from authorized mobile applications.

---

## Notes

1. All dates are in `Y-m-d H:i:s` format (Africa/Lagos timezone)
2. All monetary amounts are in Nigerian Naira (NGN)
3. File uploads are limited to PDF and image files (JPG, JPEG, PNG, GIF)
4. Maximum file size for uploads is determined by server configuration
5. Authentication uses `Authorization: Bearer <access_token>` for protected endpoints
6. Session storage is used for cart state (`/materials/cart-*` and payment initialization flow)

---

## Support

For API support, contact: support@nivasity.com

