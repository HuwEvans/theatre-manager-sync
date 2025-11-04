# Theatre Manager Sync v2.5 - Release Package

**Release Date:** November 4, 2025

## Installation

1. Extract `theatre-manager-sync-2.5.zip` into your WordPress plugins directory
2. The folder should be named `theatre-manager-sync`
3. Activate the plugin from WordPress Admin

## What's Included

- Complete Theatre Manager Sync plugin v2.5
- SharePoint integration via Microsoft Graph API
- 8 CPT syncs (Advertisers, Board Members, Cast, Contributors, Seasons, Shows, Sponsors, Testimonials)
- Folder discovery and caching system
- Admin settings and logs pages

## Key Features in v2.5

✅ **Testimonials Rating Sync** - Properly syncs star ratings from SharePoint
✅ **Board Member Images & Names** - Fixed display in shortcodes
✅ **Enhanced Logging** - JSON-formatted logs with detailed debugging
✅ **Orphaned Attachment Detection** - Prevents duplicate image uploads
✅ **Comprehensive Admin Interface** - Settings, logs, and data cleanup tabs

## Requirements

- WordPress 6.5+
- PHP 8.1+
- Theatre Manager plugin 2.5+
- Active Microsoft Graph API credentials for SharePoint access

## Documentation

See `CHANGELOG.md` in the plugin folder for complete list of changes and fixes.

## Support

For issues or questions, refer to the diagnostic tools included:
- `inspect-sharepoint.php` - View SharePoint list structure
- `check-testimonial-data.php` - Verify testimonial meta data
- `view-logs.php` - Access detailed sync logs

## Version History

- **v2.5** (2025-11-04): Testimonials rating fix, comprehensive logging improvements
- **v2.1** (Previous): Initial feature-complete release
