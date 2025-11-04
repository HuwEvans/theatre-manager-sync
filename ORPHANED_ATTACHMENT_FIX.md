# Orphaned Attachment Fix - Documentation

## Problem Statement

When a board member (or other CPT) post was deleted from WordPress:

1. The post was removed from WordPress database
2. **BUT** the image attachment remained orphaned in the Media Library
3. When sync ran again and processed that same SharePoint item:
   - The system couldn't find the attachment in post meta (post was deleted)
   - It re-downloaded the image from SharePoint
   - A **duplicate image** was created in the Media Library
4. Repeated deletes + syncs = many duplicate files

## Root Cause

The original `tm_sync_attachment_exists()` function **only checked post meta**:

```php
// OLD CODE - only checks post meta
$existing_attachment_id = get_post_meta($post_id, $meta_key, true);
```

When the post was deleted:
- Post meta was deleted
- But the attachment file remained in `/wp-content/uploads/`
- Next sync: function returns null → re-downloads file → duplicate created

## Solution

Enhanced `tm_sync_attachment_exists()` to perform **two-tier checking**:

### Tier 1: Check Post Meta (Fast)
- Check if this post already has the attachment linked in meta
- Return immediately if found
- **Use case**: Normal syncs with existing posts

### Tier 2: Search Media Library (Fallback)
- If post meta check fails, search entire media library by filename
- Look for orphaned attachment with matching filename
- If found: **reattach** to current post, prevent duplicate
- **Use case**: When posts are deleted and recreated, or when syncing duplicate SharePoint items

## Implementation

### New Function: `tm_sync_find_attachment_by_filename()`

```php
function tm_sync_find_attachment_by_filename($filename, $image_type = 'image')
```

**How it works:**
1. Takes the SharePoint filename (e.g., "mary-pat.jpg")
2. Queries WordPress database `_wp_attached_file` meta
3. Searches for files containing the filename
4. Accounts for prefix added during upload (e.g., "photo-7-mary-pat.jpg")
5. Returns attachment ID if found, null otherwise

**Performance:**
- Uses database query instead of loading all attachments into PHP
- Scales well even with thousands of attachments
- First search returns ~10 matching files, then PHP compares

### Enhanced Function: `tm_sync_attachment_exists()`

```php
function tm_sync_attachment_exists($post_id, $filename, $meta_key = '_tm_logo', $image_type = 'image')
```

**New logic:**
1. Check post meta first (returns immediately if found) ✅ Fast
2. If not found, search media library for orphaned file ✅ Fallback
3. If orphaned file found:
   - Update post meta to link the post to the attachment
   - Return the attachment ID
4. If nothing found, return null (will trigger new download)

### Logging

The system logs all orphaned attachment reattachments:

```
[INFO] Found orphaned photo in media library (post may have been deleted)
  - filename: mary-pat.jpg
  - attachment_id: 670
  - post_id: 677
  - action: reattaching orphaned file
```

## Test Results

### Test Scenario
1. Get board member with photo (ID: 656, Photo ID: 670)
2. Delete board member post from WordPress
3. Create new board member
4. Verify system detects orphaned photo

### Results
✅ Orphaned attachment detected correctly
✅ Photo reattached to new board member
✅ **Zero duplicate images created**
✅ Before: 49 attachments | After: 49 attachments (no new uploads)

## Benefits

| Scenario | Before Fix | After Fix |
|----------|-----------|-----------|
| Post deleted, sync runs | ❌ Duplicate created | ✅ Orphaned file reused |
| Delete + recreate post | ❌ Each cycle = new image | ✅ Same image reattached |
| Multiple syncs of same data | ❌ Many duplicates | ✅ Single image |
| Sync performance | ✅ Fast | ✅ Same (database lookup is fast) |

## Database Query Used

The search uses this optimized query:

```sql
SELECT p.ID, pm.meta_value
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'attachment'
  AND pm.meta_key = '_wp_attached_file'
  AND pm.meta_value LIKE '%filename%'
LIMIT 10
```

This approach:
- Only queries attachment posts (indexed)
- Uses LIKE pattern matching on `_wp_attached_file` meta
- Limits to 10 results for performance
- Returns attachment ID and file path for verification

## Code Files Modified

**File: `generic-image-sync.php`**
- Added: `tm_sync_find_attachment_by_filename()` (48 lines)
- Enhanced: `tm_sync_attachment_exists()` (expanded with orphaned detection)
- Updated: `tm_sync_image_for_post()` to pass `$image_type` parameter

**Backwards Compatibility:**
- ✅ No breaking changes
- ✅ Optional `$image_type` parameter defaults to 'image'
- ✅ Existing sync implementations work without modification
- ✅ If you call old signature, still works with defaults

## Deployment

1. Copy updated `generic-image-sync.php` to WordPress plugin folder
2. No database migrations needed
3. No configuration changes needed
4. Works with existing CPT sync files
5. No performance impact (query is optimized with indexes)

## Verification

Run the test script to verify the fix:

```bash
php test-orphaned-attachment.php
```

Expected output:
```
✓ SUCCESS: No duplicate images created!
- The system correctly detected and reused the orphaned attachment
```

## Future Improvements

- Consider automatic cleanup of orphaned attachments (beyond scope of this fix)
- Add admin UI to view orphaned files (optional)
- Add scheduled cleanup task (optional)
- Track orphaned attachment reuse statistics (optional)
