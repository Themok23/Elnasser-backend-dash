# Form Input Preservation Guide

This document explains how to ensure form inputs are preserved when validation fails across the project.

## How It Works

Laravel automatically preserves form input when using `$request->validate()`. However, when using manual `Validator::make()`, you need to explicitly add `withInput()`.

## Solution Implemented

### 1. Base Controller Trait
All controllers now have access to `backWithInput()` and `backWithErrors()` methods via the `KeepsFormInput` trait.

### 2. Global Helper Function
A `back_with_input()` helper function is available globally.

## Usage Examples

### Using the Trait Method (Recommended)
```php
use App\Traits\KeepsFormInput;

class YourController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->backWithInput($validator);
            // or
            return $this->backWithErrors($validator);
        }
        
        // ... rest of code
    }
}
```

### Using the Global Helper
```php
if ($validator->fails()) {
    return back_with_input($validator);
}
```

### Manual Approach
```php
if ($validator->fails()) {
    return redirect()->back()
        ->withErrors($validator)
        ->withInput($request->except(['password', 'password_confirmation', '_token']));
}
```

## Important Notes

1. **Sensitive Fields**: Password fields are automatically excluded for security
2. **AJAX Requests**: For AJAX requests, use `response()->json()` - no need for `withInput()`
3. **Laravel's validate()**: Already handles input preservation automatically

## View Template Usage

In your Blade templates, use the `old()` helper to repopulate fields:

```blade
<input type="text" name="name" value="{{ old('name', $model->name ?? '') }}">
<input type="email" name="email" value="{{ old('email', $model->email ?? '') }}">
```

## Controllers Updated

- ✅ LoginController - All login errors now preserve credentials
- ✅ HomeController - Contact form preserves input
- ✅ Base Controller - Added trait for all controllers

## Next Steps

When creating new controllers or updating existing ones:
1. Use `$this->backWithInput($validator)` instead of `back()->withErrors($validator)`
2. Or use `back_with_input($validator)` helper function
3. Ensure views use `old()` helper for input fields

