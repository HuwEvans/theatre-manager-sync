# Theatre Manager Sync - Test Scripts

This directory contains utility and diagnostic scripts for the Theatre Manager SharePoint sync system. Use these scripts to debug issues, verify sync operations, and inspect data.

## ⚠️ Important Notes

- **Use with care**: Some scripts modify data or sync processes. Test in development first.
- **CLI execution**: Run scripts via WordPress CLI or direct PHP execution when needed.
- **Sync dependencies**: Some scripts require active SharePoint connections and valid API credentials.
- **Output**: Most scripts output directly to terminal/command line.

## Running Test Scripts

### Via WordPress CLI
```bash
wp eval-file test-scripts/script-name.php
```

### Direct PHP Execution
```bash
php test-scripts/script-name.php
```

### From WordPress Admin
- Use the admin debug pages when available
- Check `/theatre-manager-sync/admin/` for sync interfaces

## Test Scripts by Category

### Configuration & Setup Tests (3 scripts)
Test and verify system configuration.

- **check-scott.php** - Verify Scott-specific configuration and settings
- **check-testimonial-data.php** - Verify testimonial data structure and storage
- **list-sharepoint-lists.php** - List all SharePoint lists available for sync

### API & Authentication Tests (2 scripts)
Verify Microsoft Graph API connectivity and authentication.

- **test-sandy-auth.php** - Test Sandy's authentication token (if exists)
- **inspect-sharepoint.php** - Inspect SharePoint structure and available fields

### SharePoint Data Tests (3 scripts)
Verify data coming from SharePoint.

- **debug-filenames.php** - Debug filename handling and conversion
- **debug-search.php** - Debug search functionality in SharePoint
- **list-field-definitions.php** - List all field definitions from SharePoint

### Season/Show/Cast Sync Tests (5 scripts)
Test specific content type syncs.

- **debug-seasons-meta.php** - Inspect season metadata after sync
- **debug-seasons-sync.php** - Debug season sync process in detail
- **test-seasons-fields.php** - Test season field mapping and storage
- **test-seasons-only.php** - Run seasons-only sync (no shows/cast)
- **verify-show-fields.php** - Verify show field data after sync

### Data Inspection Tests (5 scripts)
Inspect synced data in WordPress.

- **check-testimonial-data.php** - Check testimonial data structure
- **debug-filenames.php** - Inspect filename handling
- **test-finder.php** - Test and debug finder/search functionality
- **trace-sync.php** - Trace sync execution and log operations
- **view-logs.php** - View sync logs and debug information

### Deletion & Orphan Tests (2 scripts)
Test data cleanup and orphan handling.

- **test-delete-sync.php** - Test deletion sync process
- **test-orphaned-attachment.php** - Find and handle orphaned attachments

### Utility Scripts (3 scripts)
General utilities for debugging and reporting.

- **find-bm.php** - Find board members by various criteria
- **run-sync-check.php** - Run a comprehensive sync check/verification
- **debug-testimonials-fields.php** - Debug testimonials field structure

## Quick Reference

### When to Use Each Script

**Starting sync troubleshooting:**
1. `list-sharepoint-lists.php` - See what's available in SharePoint
2. `inspect-sharepoint.php` - Inspect field structures
3. `list-field-definitions.php` - View field definitions

**During sync issues:**
1. `trace-sync.php` - Watch sync execution with logging
2. `view-logs.php` - Review sync logs after completion
3. `debug-seasons-sync.php` - Deep dive into season sync

**After sync completion:**
1. `verify-show-fields.php` - Check shows synced correctly
2. `debug-seasons-meta.php` - Verify season metadata
3. `run-sync-check.php` - Full verification check

**Data cleanup:**
1. `test-orphaned-attachment.php` - Find orphaned files
2. `test-delete-sync.php` - Test deletion process
3. `find-bm.php` - Search specific records

## Creating New Test Scripts

When adding new test scripts, follow these guidelines:

### Naming Conventions
- **check-*.php** - Verify system state or configuration
- **debug-*.php** - Deep debugging with detailed output
- **test-*.php** - Test functionality (may modify data)
- **verify-*.php** - Verify data integrity after operations
- **inspect-*.php** - Inspect SharePoint or WordPress data structure
- **find-*.php** - Search for specific records
- **list-*.php** - List all records of a type
- **trace-*.php** - Trace execution flow with logging
- **view-*.php** - Display information/logs
- **run-*.php** - Execute sync or utility operations

### Script Template
```php
<?php
/**
 * Script: description-of-what-it-does
 * Purpose: What problem does this solve?
 * Usage: wp eval-file test-scripts/script-name.php
 * 
 * Safety: Does this modify data?
 */

// Load WordPress if needed
if (!function_exists('get_option')) {
    require_once('../../../../wp-load.php');
}

// Your script code here
echo "Script output...\n";
?>
```

### Best Practices
- Add PHP comment header explaining purpose and usage
- Include safety warnings if script modifies data
- Output clear, readable results
- Use error handling for API calls
- Log important operations
- Avoid long-running operations (>5 minutes)

## Safety Notes

⚠️ **Data-Modifying Scripts** (use with caution):
- `test-delete-sync.php` - Tests deletion process
- `test-orphaned-attachment.php` - May move/delete files
- `test-seasons-only.php` - Triggers actual sync

✅ **Safe to Run Anytime**:
- `check-*.php` - Read-only verification
- `debug-*.php` - Debug output only
- `inspect-*.php` - Read-only inspection
- `list-*.php` - Display only
- `view-*.php` - Display only
- `verify-*.php` - Read-only verification

## Troubleshooting

### Script won't run
- Check PHP version compatibility
- Verify WordPress is loaded
- Check file permissions (executable)
- Ensure all dependencies are available

### No output
- Script may be running silently - check for errors with error reporting enabled
- Some scripts may have conditional output based on data

### API errors
- Verify SharePoint connection settings in admin
- Check Microsoft Graph API credentials
- Ensure token hasn't expired

## Related Documentation

- **SHAREPOINT_CONFIGURATION.md** - SharePoint setup and field mappings
- **SYNCED_IMAGES_FOLDER.md** - Image handling and storage
- **ORPHANED_ATTACHMENT_FIX.md** - Attachment cleanup procedures
- **TIER2_ADMIN_SETTINGS.md** - Advanced admin settings
- **FOLDER_DISCOVERY_GUIDE.md** - Folder discovery troubleshooting

## Adding to Version Control

When committing new test scripts:
1. Ensure they're in the `test-scripts/` folder
2. Update this README.md with the new script
3. Add appropriate naming prefix and category
4. Include brief description of purpose
5. Mark data-modifying scripts clearly
