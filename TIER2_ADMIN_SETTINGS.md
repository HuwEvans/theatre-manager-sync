# Tier 2: Admin Settings Page - Documentation

## Overview

The Admin Settings Page provides a user-friendly interface for managing folder discovery cache, manual overrides, and system diagnostics.

Access via: **WordPress Admin Menu → TM Sync Settings**

## Features

### 1. Cached Folders Tab

**View all discovered folders**
- Displays every folder that has been auto-discovered from SharePoint
- Shows folder names and their corresponding SharePoint folder IDs
- **Copy ID button:** Click to copy folder ID to clipboard for manual reference

**Cache Management Buttons**

- **Refresh Cache:**
  - Re-discovers all folders from SharePoint
  - Updates the cache with latest folder IDs
  - Useful if folders were added/renamed in SharePoint
  - Takes normal time (first sync speed)

- **Clear Cache:**
  - Removes all cached folder IDs
  - Next sync will auto-discover folders again
  - Slower first sync but ensures fresh data

**When Cache is Empty**
- Message: "No folders cached yet. Run a sync to discover folders."
- Simply run a board member/advertiser sync to populate the cache

### 2. Manual Overrides Tab

**Why Use Manual Overrides?**
- Auto-discovery fails for non-standard folder structures
- Testing with different SharePoint configurations
- Pinning folder IDs when organizational structure changes frequently
- Performance: Manual overrides skip discovery (instant folder lookup)

**How to Add Override**

1. Click **"Add Override"** button
2. Enter folder name (e.g., "People", "Sponsors", "Marketing")
3. Enter folder ID from SharePoint
4. Click **"Save Overrides"**

**Example Overrides**

```
People          → 01JHLCF5BHTBPD7EHS5FCJTTOE7DL5ZNW7
Sponsors        → 01JHLCF5AIKO4PRKPVQFA36VOMS6GA3A7Z
Advertisers     → 01JHLCF5HIHU4LFHIXHNBISNH3NBF2BDOM
Marketing       → 01JHLCF5AWK6E64PEDCZF3YIFIIOSV2JP6
```

**Priority System**

Folder lookup priority:
1. **Manual Overrides** (highest) - Checked first
2. **Cached IDs** - If not overridden
3. **Auto-Discovery** (lowest) - If not cached

### 3. Help Tab

**Comprehensive guide covering:**
- How folder discovery works (3-step process)
- How to use manual overrides
- Cache management options
- How to find folder IDs in SharePoint
- Performance tips and optimization

## System Status

Bottom of all tabs shows:
- Generic Image Sync status (✓ or ✗)
- Folder Discovery status (✓ or ✗)
- Graph API Client status (✓ or ✗)
- Current number of cached folders

Green checkmark = Working, Red X = Not found

## Performance Impact

| Action | First Sync | Subsequent Syncs | Impact |
|--------|-----------|-----------------|--------|
| Auto-discovery | Normal | ~80% faster (cached) | Optimal |
| Manual overrides | Fast | Instant | Best |
| Clear cache | Normal | Normal | Reset |
| Multiple syncs same data | Fast | Instant (attachment reuse) | Optimal |

## AJAX Handlers

All cache operations happen via AJAX (no page reload needed for view):

- `tm_sync_cache_action` (nonce required: `tm_sync_settings_nonce`)
- Actions: `refresh_cache`, `clear_cache`, `get_cache_status`

## Settings Storage

**Where Settings Are Stored**
- Cached folders: `wp_options → tm_sync_image_media_folder_ids` (auto-managed)
- Manual overrides: `wp_options → tm_sync_folder_overrides` (user-managed)
- Both are auto-serialized by WordPress

## Integration with Sync

**Folder Discovery Tier System**

The settings page integrates with a 3-tier folder discovery system:

```php
// When syncing any CPT, the system automatically:
1. Check tm_sync_folder_overrides (manual)
2. Check tm_sync_image_media_folder_ids (cached)
3. Query SharePoint to discover new folder
4. Cache the result for next time
```

This means:
- You never need to manually clear cache (unless debugging)
- Manual overrides always take precedence
- Auto-discovery only happens when needed

## Troubleshooting

**"No folders cached yet" message**
- Solution: Run any sync (board members, advertisers, etc.)

**Image not syncing**
- Check: Do you have a manual override for this folder?
- Check: Is the folder ID correct?
- Solution: Copy from Cached Folders tab or verify in SharePoint

**Sync slower than expected**
- Problem: Auto-discovering many new folders
- Solution: Run once (caches folders), subsequent runs are fast

**Can't find folder ID**
- Use "Copy ID" button in Cached Folders tab
- Or navigate to folder in SharePoint and look in URL

## User Capabilities

Settings page requires `manage_options` capability (admins only).

Form sanitization handles:
- Folder names: `sanitize_text_field()`
- Folder IDs: `sanitize_text_field()`
- Rejects empty values

## Browser Features Used

- **Tabs:** DOM-based tab switching (no AJAX)
- **Copy to Clipboard:** `document.execCommand('copy')`
- **AJAX:** jQuery POST to `wp_ajax_tm_sync_cache_action`
- **Responsive:** Mobile-friendly admin styles

## Code Quality

- Nonce verification on all AJAX actions
- Capability checks (`manage_options`)
- Comprehensive logging to tm-sync.log
- Error handling with user-friendly messages
- Graceful fallback for JavaScript disabled

## Future Enhancements (Optional)

- "Test Connection" button (verify folder exists)
- Import/Export overrides (JSON format)
- Folder structure visualization
- Sync history and statistics dashboard
- Bulk override management
