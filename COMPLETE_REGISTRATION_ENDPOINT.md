# Complete Registration Endpoint Documentation

## Endpoint
**POST** `/api/v1/auth/complete-registration`

## Description
This endpoint allows users with incomplete registration data (`account_type: "old"`) to complete their missing information. It updates only the missing fields and automatically logs in the user if all required data is now complete.

## Request

### Headers
```
Accept: application/json
Content-Type: application/json
```

### Body Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `phone` | string | Yes | Phone number (must match existing incomplete user) |
| `name` | string | No | Full name (will be split into first/last name) |
| `email` | string | No | Email address (must be unique if provided) |
| `password` | string | No | Password (minimum 8 characters) |

**Note:** At least one optional field (`name`, `email`, or `password`) must be provided.

### Phone OTP Verification (Important)
If **phone verification is enabled** (`phone_verification_status = 1`) and the user’s phone is **not verified**, this endpoint will **send an OTP** to the phone number and will **NOT** auto-verify the phone.

After receiving the OTP, verify it using:
- **POST** `/api/v1/auth/verify-phone`

Example verify request:
```json
{
  "verification_type": "phone",
  "login_type": "manual",
  "phone": "+201234567890",
  "otp": "123456"
}
```

### Example Request
```json
{
  "phone": "+201234567890",
  "name": "Ahmed Ali",
  "email": "ahmed@example.com",
  "password": "Password123"
}
```

## Response Scenarios

### Success - Registration Completed
**Status Code:** `200 OK`

```json
{
  "message": "Registration completed successfully",
  "user": {
    "id": 2,
    "name": "Ahmed Ali",
    "phone": "+201234567890",
    "email": "ahmed@example.com"
  },
  "requires_phone_verification": false,
  "otp_sent": false,
  "is_complete": true,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "updated_fields": ["f_name", "l_name", "email", "password"]
}
```

**Note:** If user becomes complete after update, a `token` is returned for automatic login.

---

### Success - Partial Update (Still Incomplete)
**Status Code:** `200 OK`

```json
{
  "message": "Registration completed successfully",
  "user": {
    "id": 2,
    "name": "Ahmed Ali",
    "phone": "+201234567890",
    "email": null
  },
  "requires_phone_verification": false,
  "otp_sent": false,
  "is_complete": false,
  "token": null,
  "updated_fields": ["f_name", "l_name"]
}
```

**Note:** User still needs to complete more fields. No token is returned.

---

### Success - OTP Sent (Phone Verification Required)
**Status Code:** `200 OK`

```json
{
  "message": "Otp sent successfull",
  "user": {
    "id": 2,
    "name": "Ahmed Ali",
    "phone": "+201234567890",
    "email": "ahmed@example.com"
  },
  "requires_phone_verification": true,
  "otp_sent": true,
  "is_complete": false,
  "token": null,
  "updated_fields": ["password"]
}
```

**Note:** Verify the OTP via `/api/v1/auth/verify-phone`. After verification, the user can login normally.

---

## Error Responses

### User Not Found
**Status Code:** `404 Not Found`

```json
{
  "errors": [
    {
      "code": "phone",
      "message": "User not found"
    }
  ]
}
```

### User Already Complete
**Status Code:** `403 Forbidden`

```json
{
  "errors": [
    {
      "code": "user",
      "message": "User already complete"
    }
  ]
}
```

### No Fields to Update
**Status Code:** `403 Forbidden`

```json
{
  "errors": [
    {
      "code": "data",
      "message": "No fields to update"
    }
  ]
}
```

### Validation Errors
**Status Code:** `403 Forbidden`

```json
{
  "errors": [
    {
      "code": "email",
      "message": "Email already exists"
    },
    {
      "code": "password",
      "message": "The password must be at least 8 characters."
    }
  ]
}
```

---

## Usage Flow

### Complete Flow Example

```
1. User enters phone number
   ↓
2. POST /api/v1/auth/check-phone
   Response: { "account_type": "old", ... }
   ↓
3. User fills missing data (password, email, etc.)
   ↓
4. POST /api/v1/auth/complete-registration
   {
     "phone": "+201234567890",
     "name": "Ahmed Ali",
     "email": "ahmed@example.com",
     "password": "Password123"
   }
   ↓
5. If phone verification is enabled and phone is not verified:
   - Response: { "requires_phone_verification": true, "otp_sent": true, "is_complete": false, "token": null }
   - Then POST /api/v1/auth/verify-phone with OTP
   ↓
6. If user becomes complete:
   - Response includes { "is_complete": true, "token": "..." }
   - User is logged in
```

---

## Field Update Logic

The endpoint only updates **missing** fields:

- **Name:** Only updates if `f_name` or `l_name` is empty
- **Email:** Only updates if `email` is empty
- **Password:** Only updates if `password` is empty

**Example:**
- User has: `phone: "+201234567890"`, `f_name: "Ahmed"`, `password: null`
- Request: `{ "phone": "+201234567890", "name": "Ahmed Ali", "password": "Password123" }`
- Result: Updates `l_name` and `password`, but does NOT change `f_name` (already exists)

---

## User Completeness Check

After updating, the endpoint checks if user is now complete:

**Complete Criteria:**
- ✅ Password is set
- ✅ First name (`f_name`) is set
- ✅ Last name (`l_name`) is set
- ✅ Phone verified OR Email verified

**If Complete:**
- Returns `is_complete: true`
- Generates and returns authentication `token`
- User can proceed to use the app

**If Still Incomplete:**
- Returns `is_complete: false`
- No token returned
- User needs to complete more fields

---

## Test Cases

### Test 1: Complete Registration (All Fields)
**Request:**
```json
{
  "phone": "+201234567890",
  "name": "Ahmed Ali",
  "email": "ahmed@example.com",
  "password": "Password123"
}
```

**Expected Response:**
- `is_complete: true`
- `token` present
- All fields updated

---

### Test 2: Partial Update (Only Password)
**Request:**
```json
{
  "phone": "+201234567890",
  "password": "Password123"
}
```

**Expected Response:**
- `is_complete: false` (if name still missing)
- `token: null`
- Only password updated

---

### Test 3: User Not Found
**Request:**
```json
{
  "phone": "+999999999999",
  "password": "Password123"
}
```

**Expected Response:**
- `404 Not Found`
- Error: "User not found"

---

### Test 4: User Already Complete
**Request:**
```json
{
  "phone": "+201156683330",
  "password": "NewPassword123"
}
```

**Expected Response:**
- `403 Forbidden`
- Error: "User already complete"

---

## Integration with Mobile App

```javascript
// Mobile app example
async function completeRegistration(phone, name, email, password) {
  const response = await fetch('/api/v1/auth/complete-registration', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      phone,
      name,
      email,
      password
    })
  });
  
  const data = await response.json();
  
  if (data.is_complete && data.token) {
    // Save token and navigate to home
    saveAuthToken(data.token);
    navigate('/home');
  } else {
    // Still incomplete, show what's missing
    showIncompleteMessage(data.user);
  }
}
```

---

## Notes

- Phone number is required and must match an existing incomplete user
- At least one optional field must be provided
- Fields are only updated if they're currently empty/null
- If user becomes complete, they're automatically logged in (token returned)
- Email must be unique if provided
- Password must be at least 8 characters
- Name will be split into first and last name automatically


