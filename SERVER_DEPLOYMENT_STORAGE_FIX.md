# Server Deployment - Storage Image Fix

## Issue
Images don't work on the server after local development changes.

## Solution 1: Create Storage Symlink (RECOMMENDED)

On your server, run this command via SSH:

```bash
cd /path/to/your/project
php artisan storage:link
```

This creates a symlink from `public/storage` to `storage/app/public`, which is the standard Laravel way.

**Verify the symlink was created:**
```bash
ls -la public/ | grep storage
```

You should see:
```
lrwxr-xr-x storage -> /path/to/your/project/storage/app/public
```

## Solution 2: Revert Code Change (If symlink doesn't work)

If for some reason you can't create the symlink, you can modify the code to use the full path.

**File:** `app/CentralLogics/Helpers.php` (around line 3217)

**Change from:**
```php
if ($data && Storage::disk('public')->exists($path . '/' . $data)) {
    return asset('storage') . '/' . $path . '/' . $data;
}
```

**Change to:**
```php
if ($data && Storage::disk('public')->exists($path . '/' . $data)) {
    return asset('storage/app/public') . '/' . $path . '/' . $data;
}
```

**Note:** This is NOT recommended as it goes against Laravel conventions and may cause issues with other parts of the application.

## Recommended Approach

**Use Solution 1** - Create the symlink. This is the standard Laravel way and ensures compatibility with all Laravel features.

## After Fix

1. Clear cache:
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

2. Set proper permissions (if needed):
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

3. Test image loading in your application.

