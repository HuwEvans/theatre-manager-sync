# Synced Images Folder Management - Implementation Summary

## Overview
Theatre Manager Sync now has a dedicated folder for all synced images that can be easily managed, viewed, and deleted.

## Features Implemented

### 1. **Dedicated Images Folder**
- **Location:** `wp-content/uploads/theatre-manager-sync-images/`
- **Auto-created:** On plugin activation or first admin page load
- **Security protected:** Includes `.htaccess` and `index.php` to prevent script execution

### 2. **Folder Functions** (`includes/image-management.php`)

#### Core Functions:
- `tm_sync_get_images_dir()` - Get folder path
- `tm_sync_get_images_url()` - Get folder URL
- `tm_sync_create_images_folder()` - Create folder with security files
- `tm_sync_delete_all_images()` - Delete all images and folder
- `tm_sync_delete_directory_recursive()` - Recursive directory deletion

### 3. **Plugin Lifecycle Integration**

#### Activation Hook:
```php
register_activation_hook(__FILE__, 'tm_sync_activate_setup')
```
- Creates the images folder
- Sets up required security files

#### Deactivation Hook:
```php
register_deactivation_hook(__FILE__, 'tm_sync_deactivate_cleanup')
```
- Deletes all synced images
- Removes the folder
- Clears scheduled events

### 4. **Settings Page Integration**

**Location:** Theatre Manager Sync → Settings → Clean Data tab

**New Section: "Delete Synced Images"**
- Shows folder path: `wp-content/uploads/theatre-manager-sync-images`
- Displays file/folder count in the folder
- Red danger button: "Delete Synced Images Folder"
- Confirmation dialog before deletion
- Status messages (success/error)

**UI Features:**
- Shows current folder status (e.g., "5 files/folders")
- Safe deletion with AJAX confirmation
- User-friendly error messages
- Automatic page reload after deletion

### 5. **AJAX Handler Updates**

Modified `tm_sync_handle_clean_ajax()` to support two actions:

1. **delete_cpts** (existing)
   - Delete posts by CPT type

2. **delete_images** (new)
   - Delete synced images folder
   - Requires nonce and manage_options capability
   - Returns success/error messages

### 6. **Security Features**

#### .htaccess Protection:
```apache
<FilesMatch "\.php$">
    Deny from all
</FilesMatch>
```

#### index.php:
Prevents directory listing while not affecting image serving

#### Permission Checks:
- All cleanup operations require `manage_options` capability
- AJAX endpoints verify nonce tokens

## Benefits

✅ **Easy Cleanup:** Delete all synced images with one click from settings  
✅ **Storage Management:** Know exactly how much space images are using  
✅ **Automatic Cleanup:** Images removed automatically on plugin deactivation  
✅ **Data Isolation:** All synced data in one predictable location  
✅ **Re-download Capability:** Images automatically re-downloaded on next sync  
✅ **Security:** Protected folder prevents direct script execution  
✅ **User-Friendly:** Visual status and confirmation dialogs  

## File Changes

### New Files:
- `includes/image-management.php` - Image folder management functions

### Modified Files:
- `theatre-manager-sync.php` - Added activation/deactivation hooks
- `admin/settings-page.php` - Added UI and AJAX handlers

### Commit Hash:
`1ca8f5d` - feat: add dedicated synced images folder management

## Usage

### For Plugin Users:
1. Navigate to: Theatre Manager Sync → Settings
2. Click "Clean Data" tab
3. Scroll to "Delete Synced Images"
4. Click "Delete Synced Images Folder"
5. Confirm the action
6. Images will be re-downloaded on next sync

### For Plugin Deactivation:
- Simply deactivate the plugin
- Synced images folder is automatically deleted
- No manual cleanup needed

### For Future Sync:
- Any previously downloaded images will need to be re-downloaded
- This is automatic and transparent to the user

## Technical Notes

- **Performance:** Folder creation uses `wp_mkdir_p()` for compatibility
- **Logging:** All operations logged with `tm_sync_log()` for debugging
- **Recursive Deletion:** Handles nested subdirectories if needed
- **Error Handling:** Graceful fallback if folder can't be deleted

## Testing Checklist

- [ ] Plugin activates successfully
- [ ] Images folder created at `wp-content/uploads/theatre-manager-sync-images`
- [ ] .htaccess and index.php files present
- [ ] Settings page shows folder path and status
- [ ] Delete button works with confirmation
- [ ] Status messages appear after deletion
- [ ] Images re-download on next sync
- [ ] Plugin deactivation removes folder
- [ ] No errors in debug.log

## Future Enhancements

Potential improvements for future versions:
- Image cleanup scheduling (auto-delete old images)
- Folder size calculation and display
- Per-image delete capability
- Archive/backup before deletion
- Image compression options
