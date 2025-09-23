# Email Verification API Documentation

## Overview
The email verification system requires users to verify their email address within 20 minutes of registration before they can login to their account.

## API Endpoints

### 1. User Registration
**POST** `/api/v1/register`

**Request Body:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "1234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "city": "Dhaka",
    "current_latitude": 23.8103,
    "current_longitude": 90.4125
}
```

**Response (Success - 201):**
```json
{
    "success": true,
    "message": "Registration successful! Please check your email for verification code.",
    "data": {
        "user_id": 1,
        "email": "john@example.com",
        "verification_expires_at": "2025-09-23T12:24:22.000000Z",
        "verification_required": true
    }
}
```

**Response (Email Already Verified - 409):**
```json
{
    "success": false,
    "message": "This email is already registered and verified. Please try logging in."
}
```

### 2. User Login
**POST** `/api/v1/login`

**Request Body:**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Response (Email Not Verified - 403):**
```json
{
    "success": false,
    "message": "Please verify your email address before logging in. Check your inbox for the verification code.",
    "verification_required": true,
    "verification_expires_at": "2025-09-23T12:24:22.000000Z"
}
```

**Response (Verification Expired - 403):**
```json
{
    "success": false,
    "message": "Your verification code has expired. Please request a new one.",
    "verification_required": true,
    "verification_expired": true
}
```

**Response (Success - 200):**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_type": "Bearer",
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "1234567890",
            "city": "Dhaka",
            "is_active": true,
            "role": "user"
        }
    }
}
```

### 3. Verify Email
**POST** `/api/v1/email/verify`

**Request Body:**
```json
{
    "email": "john@example.com",
    "code": "123456"
}
```

**Response (Success - 200):**
```json
{
    "success": true,
    "message": "Email verified successfully! You can now login.",
    "data": {
        "verified": true,
        "verified_at": "2025-09-23T12:10:22.000000Z",
        "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
        "token_type": "Bearer",
        "user": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "phone": "1234567890",
            "city": "Dhaka",
            "is_active": true,
            "role": "user"
        }
    }
}
```

**Response (Invalid Code - 400):**
```json
{
    "success": false,
    "message": "Invalid or expired verification code"
}
```

### 4. Resend Verification Code
**POST** `/api/v1/email/resend`

**Request Body:**
```json
{
    "email": "john@example.com"
}
```

**Response (Success - 200):**
```json
{
    "success": true,
    "message": "New verification code sent to your email.",
    "data": {
        "email": "john@example.com",
        "verification_expires_at": "2025-09-23T12:44:22.000000Z"
    }
}
```

**Response (Rate Limited - 429):**
```json
{
    "success": false,
    "message": "Please wait at least 2 minutes before requesting a new code.",
    "retry_after_seconds": 85
}
```

**Response (Already Verified - 400):**
```json
{
    "success": false,
    "message": "Email is already verified. Please try logging in."
}
```

### 5. Check Verification Status
**GET** `/api/v1/email/status?email=john@example.com`

**Response (Not Verified):**
```json
{
    "success": true,
    "data": {
        "email": "john@example.com",
        "is_verified": false,
        "verification_required": true,
        "verification_expires_at": "2025-09-23T12:24:22.000000Z",
        "is_expired": false,
        "minutes_remaining": 14
    }
}
```

**Response (Verified):**
```json
{
    "success": true,
    "data": {
        "email": "john@example.com",
        "is_verified": true,
        "verification_required": false
    }
}
```

## Email Template Features

The verification email includes:
- 6-digit verification code prominently displayed
- Clear instructions on how to verify
- 20-minute expiration warning
- Security notes
- Professional styling with responsive design
- Timestamp and sender information

## Security Features

1. **Time-based Expiration**: Codes expire after 20 minutes
2. **Rate Limiting**: 2-minute cooldown between resend requests
3. **One-time Use**: Codes are marked as used after verification
4. **Automatic Cleanup**: Old unverified codes are removed when new ones are generated
5. **Login Prevention**: Unverified users cannot login
6. **Secure Code Generation**: 6-digit codes using cryptographically secure random generation

## Integration Notes

- Email configuration uses SMTP with TLS encryption
- All responses follow consistent JSON format
- Proper HTTP status codes for different scenarios
- Comprehensive error handling with debug information (in debug mode)
- Integration with existing Laravel Sanctum authentication system

## Test Email Sent

✅ **A test verification email has been successfully sent to naimur404cse@gmail.com**

- **Verification Code**: 912770
- **Expires At**: 2025-09-23 12:04:22
- **Time Remaining**: 20 minutes from send time

Please check your inbox (and spam folder) for the verification email!