# Check Phone Endpoint Documentation

## Endpoint
**POST** `/api/v1/auth/check-phone`

## Description
This endpoint checks if a phone number exists in the database and returns the user's status. It helps determine whether the user should register (new user) or complete their registration (existing incomplete user) or login (complete user).

## Request

### Headers
```
Accept: application/json
Content-Type: application/json
```

### Body Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `phone` | string | Yes | Phone number (minimum 10 characters, valid format) |

### Example Request
```json
{
  "phone": "+201234567890"
}
```

## Response Scenarios

### 1. New User (Phone Not Found)
**Status Code:** `200 OK`

```json
{
  "account_type": "new",
  "message": "New user detected",
  "phone": "+201234567890"
}
```

**Action:** User should be redirected to the registration endpoint (`/api/v1/auth/sign-up`)

---

### 2. Existing Incomplete User (Phone Found, Missing Data)
**Status Code:** `200 OK`

```json
{
  "account_type": "old",
  "message": "Welcome back! Please complete your registration",
  "user": {
    "id": 2,
    "name": "Ahmed Ali",
    "phone": "+201234567890",
    "has_password": false,
    "has_email": false,
    "is_phone_verified": false,
    "is_email_verified": false
  }
}
```

**Action:** User should be redirected to complete registration endpoint (update-info or similar)

---

### 3. Complete User (Phone Found, All Data Complete)
**Status Code:** `200 OK`

```json
{
  "account_type": "exist",
  "message": "Welcome back",
  "user": {
    "id": 12,
    "name": "John Doe",
    "phone": "+201156683330",
    "email": "john.doe@example.com"
  }
}
```

**Action:** User should be redirected to the login endpoint (`/api/v1/auth/login`)

---

## Error Responses

### Invalid Phone Format
**Status Code:** `403 Forbidden`

```json
{
  "errors": [
    {
      "code": "phone",
      "message": "Phone must be valid"
    }
  ]
}
```

### Missing Phone
**Status Code:** `403 Forbidden`

```json
{
  "errors": [
    {
      "code": "phone",
      "message": "Phone is required"
    }
  ]
}
```

---

## Account Type Values

- **`"new"`** - User doesn't exist in database (new user, needs registration)
- **`"old"`** - User exists but incomplete (has phone/name but missing password or other required fields)
- **`"exist"`** - User exists and complete (has all required data, can login)

## User Completeness Criteria

A user is considered **complete** (`account_type: "exist"`) if they have:
- ✅ Password set
- ✅ First name (`f_name`)
- ✅ Last name (`l_name`)
- ✅ Phone verified OR Email verified

A user is considered **incomplete** (`account_type: "old"`) if:
- ❌ Missing password, OR
- ❌ Missing name fields, OR
- ❌ Neither phone nor email verified

---

## Usage Flow

### Flow Diagram
```
User enters phone number
        ↓
POST /api/v1/auth/check-phone
        ↓
    ┌───┴───┐
    │       │
account_type: "new"  account_type: "old" | "exist"
    │       │
    │   ┌───┴───┐
    │   │       │
    │ "exist"  "old"
    │   │       │
    │ Login  Complete Registration
    │
Register
```

### Example Implementation

```javascript
// Frontend/Mobile App example
async function checkPhone(phone) {
  const response = await fetch('/api/v1/auth/check-phone', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({ phone })
  });
  
  const data = await response.json();
  
  switch(data.account_type) {
    case 'new':
      // Redirect to registration
      navigate('/register', { phone });
      break;
      
    case 'exist':
      // Redirect to login
      navigate('/login', { phone, name: data.user.name });
      break;
      
    case 'old':
      // Redirect to complete registration
      navigate('/complete-registration', { 
        userId: data.user.id,
        name: data.user.name,
        phone: data.user.phone 
      });
      break;
  }
}
```

---

## Test Cases

### Test 1: New User
**Request:**
```json
{
  "phone": "+999999999999"
}
```

**Expected Response:**
```json
{
  "account_type": "new",
  "message": "New user detected",
  "phone": "+999999999999"
}
```

---

### Test 2: Incomplete User (No Password)
**Request:**
```json
{
  "phone": "+201234567890"
}
```

**Expected Response:**
```json
{
  "account_type": "old",
  "message": "Welcome back! Please complete your registration",
  "user": {
    "id": 2,
    "name": "Ahmed Ali",
    "phone": "+201234567890",
    "has_password": false,
    "has_email": false,
    "is_phone_verified": false,
    "is_email_verified": false
  }
}
```

---

### Test 3: Complete User
**Request:**
```json
{
  "phone": "+201156683330"
}
```

**Expected Response:**
```json
{
  "account_type": "exist",
  "message": "Welcome back",
  "user": {
    "id": 12,
    "name": "John Doe",
    "phone": "+201156683330",
    "email": "john.doe@example.com"
  }
}
```

---

## Seeded Test Data

### Incomplete Users (No Password)
- +201234567890 - Ahmed Ali
- +201234567891 - Sara Mohamed
- +201234567892 - Mohamed Hassan
- +201234567893 - Fatima Ibrahim
- +201234567894 - Omar Khalil
- +201234567895 - Layla Youssef
- +201234567896 - Youssef Mahmoud
- +201234567897 - Nour Said
- +201234567898 - Khaled Fahmy
- +201234567899 - Mariam Tarek

### Complete User
- +201156683330 - John Doe
  - Email: john.doe@example.com
  - Password: Password123

---

## Notes

- Phone numbers must be unique in the database
- The endpoint validates phone format before checking database
- All responses return HTTP 200 for successful checks (even if user not found)
- Error responses (403) are only for validation failures
- The endpoint does not authenticate users, it only checks existence

