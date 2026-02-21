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

# Search and sort
GET /materials/list.php?search=algorithm&sort=low-high
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

**Description:** Get cart contents with detailed pricing breakdown including subtotal, gateway charges, and total amount.

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
    "total_items": 3
  }
}
```

**Note:** 
- `subtotal` - Sum of all item prices
- `charge` - Gateway processing fees (calculated using active gateway's fee structure)
- `total_amount` - Final amount to be charged (subtotal + charge)

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

#### 18. Get Payment Gateway
**Endpoint:** `GET /payment/gateway.php`

**Description:** Get active payment gateway information.

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
    "message": ""
  }
}
```

#### 19. Initialize Payment
**Endpoint:** `POST /payment/init.php`

**Description:** Initialize payment for cart items. Supports automatic payment splitting for multi-seller transactions using gateway-specific split mechanisms. Optionally accepts a custom redirect URL for mobile app callback.

**Authentication:** Required

**Request Body (JSON):**
```json
{
  "redirect_url": "https://yourapp.com/payment-callback"
}
```

**Parameters:**
- `redirect_url` (optional): Custom URL where users will be redirected after payment verification. The gateway callback itself always points to `/payment/callback.php`, and this value is forwarded in payment metadata.

**Payment Split Features:**
- **Paystack:** Uses Paystack Split API with intelligent caching to avoid recreating splits for identical seller combinations
- **Flutterwave:** Uses subaccounts array for direct settlement to sellers
- Sellers receive their exact item prices automatically
- Platform charges are calculated separately by the gateway
- Split configurations are cached based on seller combinations to improve performance and reduce API calls

**Split Caching:**
The endpoint uses an intelligent caching system to avoid recreating payment splits:
- Cache key is generated from sorted seller subaccounts and their shares
- Same seller combination with same amounts reuses existing split code
- Cache stored in `model/paystack_split_cache.json`
- Reduces unnecessary API calls to Paystack Split API
- Improves payment initialization performance

**Response (Success):**
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

**Response Fields:**
- `tx_ref`: Transaction reference for tracking the payment
- `payment_url`: Hosted checkout URL where user completes payment
- `redirect_url` (optional): Returned only when you passed `redirect_url` in request body
- `gateway`: Payment gateway being used (paystack/flutterwave)
- `subtotal`: Total cost of items before gateway charges
- `charge`: Gateway processing fees
- `total_amount`: Final amount to be paid (subtotal + charge)
- `items`: Array of cart items with details

**Mobile App Integration:**
For mobile apps, provide a custom `redirect_url` that uses your app's deep link scheme:
```json
{
  "redirect_url": "myapp://payment-callback"
}
```

This allows the payment gateway to redirect back to your mobile app after the user completes or cancels payment on the hosted checkout page.

**How Payment Splitting Works:**

1. **Collection Phase:**
   - System collects all cart items with their seller information
   - Retrieves subaccount codes from `settlement_accounts` table using `getSettlementSubaccount()` function
   - Function checks school-level accounts first, then falls back to seller's personal account
   - Calculates total amount per seller

2. **Paystack Split (with caching):**
   - Sellers are sorted by subaccount code for consistent cache keys
   - Cache key is generated: `md5(json_encode(sorted_sellers))`
   - System checks cache file for existing split with same configuration
   - If cached split exists, reuses the `split_code`
   - If no cache, creates new split via Paystack Split API
   - New splits are cached with their configuration for future reuse
   - Split code is included in payment initialization

3. **Flutterwave Subaccounts:**
   - Builds array of subaccounts with flat charge type
   - Each seller's total is set as their transaction charge
   - Subaccounts array is passed to payment initialization

4. **Payment Distribution:**
   - Gateway automatically settles each seller's share to their subaccount
   - Platform receives gateway processing fees
   - No manual settlement required

**Requirements:**
- Sellers must have subaccount codes in `settlement_accounts` table
- Subaccounts are retrieved by gateway type (paystack or flutterwave)
- System first checks for school-level accounts, then user-level accounts
- Platform must have valid gateway credentials (PAYSTACK_SECRET_KEY, FLUTTERWAVE_SECRET_KEY)
- Cart items are saved to database before payment initialization

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
    "processed_at": "2024-02-01 14:30:00"
  }
}
```

#### 21. Get Transactions
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

#### Bulk Verify Pending Payments
**Endpoint:** `GET /payment/verify-bulk.php`
**Endpoint:** `POST /payment/verify-bulk.php`

**Description:** Bulk verification utility for pending cart payments. Used for scheduled reconciliation and maintenance.

**Authentication:** Not required

**Notes:**
- `POST /payment/verify-bulk.php` is also supported for filtered checks
- Supports CLI execution for cron jobs

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

