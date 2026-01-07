# Google OAuth Authentication Setup Guide

## Overview
The Google OAuth authentication endpoint allows users to sign in or sign up using their Google accounts. This provides a seamless authentication experience without requiring password management.

## Setup Instructions

### 1. Create Google OAuth Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the **Google Identity Services** API
4. Navigate to **Credentials** section
5. Click **Create Credentials** → **OAuth 2.0 Client ID**
6. Choose application type:
   - For web: Select "Web application"
   - For mobile: Select "iOS" or "Android"
7. Configure authorized origins and redirect URIs:
   - **Authorized JavaScript origins**: `https://yourdomain.com`
   - **Authorized redirect URIs**: `https://yourdomain.com/auth/callback`
8. Copy the **Client ID** (and optionally **Client Secret**)

### 2. Configure the Application

1. Copy the example configuration file:
   ```bash
   cp config/google-oauth.php.example config/google-oauth.php
   ```

2. Edit `config/google-oauth.php` and add your credentials:
   ```php
   define('GOOGLE_CLIENT_ID', 'YOUR_ACTUAL_CLIENT_ID');
   define('GOOGLE_CLIENT_SECRET', 'YOUR_ACTUAL_CLIENT_SECRET');
   ```

3. **IMPORTANT**: Add `config/google-oauth.php` to `.gitignore`:
   ```
   config/google-oauth.php
   ```

### 3. Security Considerations

- ✅ Never commit actual credentials to version control
- ✅ Use environment variables in production
- ✅ Restrict OAuth client IDs to specific domains
- ✅ Enable HTTPS for all authentication endpoints
- ✅ Regularly rotate credentials
- ✅ Monitor OAuth usage in Google Cloud Console

## API Endpoint

### `POST /auth/google-auth.php`

Authenticates or registers a user using a Google ID token.

**Authentication Required**: No

**Request Body**:
```json
{
  "id_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6...",
  "school_id": 1,
  "phone": "+2348012345678",  // Optional for new users
  "gender": "male"             // Optional for new users
}
```

**Parameters**:
- `id_token` (string, required) - Google ID token received from Google Sign-In
- `school_id` (integer, required) - ID of the school the user belongs to
- `phone` (string, optional) - User's phone number (for new registrations)
- `gender` (string, optional) - User's gender (for new registrations)

**Success Response** (200 OK for existing user, 201 Created for new user):
```json
{
  "status": "success",
  "message": "Logged in successfully with Google!",
  "data": {
    "id": 123,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone": "+2348012345678",
    "role": "student",
    "gender": "male",
    "status": "active",
    "profile_pic": "https://lh3.googleusercontent.com/a/...",
    "school_id": 1,
    "matric_no": null,
    "dept": null,
    "adm_year": null,
    "auth_provider": "google",
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "expires_in": 900,
    "token_type": "Bearer"
  }
}
```

**Error Responses**:

- **401 Unauthorized** - Invalid or expired Google ID token
- **403 Forbidden** - Account suspended or deactivated
- **400 Bad Request** - Invalid school ID
- **500 Internal Server Error** - Configuration error

## How It Works

1. **Client Side**:
   - User initiates Google Sign-In
   - Google authenticates the user
   - Client receives an ID token from Google
   - Client sends ID token + school_id to the API

2. **Server Side**:
   - API receives ID token
   - Validates token with Google's tokeninfo endpoint
   - Verifies token audience matches configured Client ID
   - Checks token expiration
   - Extracts user information (email, name, profile picture)

3. **User Lookup**:
   - **Existing User**: Logs in the user, updates profile picture if needed
   - **New User**: Creates account with Google profile data, marks as active if email is verified

4. **Response**:
   - Returns JWT tokens (access + refresh)
   - Returns complete user profile
   - Client stores tokens for subsequent API calls

## Benefits

- ✅ **No password management** - Users don't need to remember passwords
- ✅ **Fast onboarding** - New users can sign up instantly
- ✅ **Email verification** - Google-verified emails are automatically trusted
- ✅ **Profile data** - Automatically get user's name and profile picture
- ✅ **Security** - Leverage Google's robust authentication infrastructure
- ✅ **Mobile-friendly** - Works seamlessly on iOS and Android

## Testing

Use Google's [OAuth 2.0 Playground](https://developers.google.com/oauthplayground/) to test token generation and validation during development.

## Production Checklist

- [ ] Set up Google OAuth credentials for production domain
- [ ] Configure `google-oauth.php` with production credentials
- [ ] Add `google-oauth.php` to `.gitignore`
- [ ] Enable HTTPS on all API endpoints
- [ ] Set up monitoring for failed authentication attempts
- [ ] Document OAuth client usage limits
- [ ] Configure authorized domains in Google Cloud Console
- [ ] Test on actual mobile devices (iOS and Android)
- [ ] Set up error tracking and logging
