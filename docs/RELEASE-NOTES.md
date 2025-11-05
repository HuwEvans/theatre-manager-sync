# Theatre Manager Sync v2.6 - Release Package

**Release Date:** November 4, 2025

## Installation

1. Extract `theatre-manager-sync-2.6.zip` into your WordPress plugins directory
2. The folder should be named `theatre-manager-sync`
3. Activate the plugin from WordPress Admin

## What's Included

- Complete Theatre Manager Sync plugin v2.6
- SharePoint integration via Microsoft Graph API
- 8 CPT syncs (Advertisers, Board Members, Cast, Contributors, Seasons, Shows, Sponsors, Testimonials)
- Folder discovery and caching system
- Admin settings and logs pages
- Complete field mapping for all CPTs

## Key Features in v2.6

✅ **Complete Seasons Sync** - All season fields and images now sync properly
✅ **Complete Shows Sync** - All show details, dates, and season linking
✅ **Complete Cast Sync** - Cast members with character, actor, images, and show linking
✅ **Relationship Linking** - Automatic Cast→Show and Shows→Season linking
✅ **Fixed Field Mapping** - Sponsors, Contributors, all CPTs use exact SharePoint field names
✅ **Enhanced Debug Logging** - Detailed field extraction logging for troubleshooting
✅ **Testimonials Rating Sync** - Properly syncs star ratings from SharePoint
✅ **Board Member Images & Names** - Fixed display in shortcodes
✅ **Enhanced Logging** - JSON-formatted logs with detailed debugging
✅ **Orphaned Attachment Detection** - Prevents duplicate image uploads
✅ **Comprehensive Admin Interface** - Settings, logs, and data cleanup tabs

## Requirements

- WordPress 6.5+
- PHP 8.1+
- Theatre Manager plugin 2.6+
- Active Microsoft Graph API credentials for SharePoint access

## Documentation

See `CHANGELOG.md` in the plugin folder for complete list of changes and fixes.

## Support

For issues or questions, refer to the diagnostic tools included:
- `inspect-sharepoint.php` - View SharePoint list structure
- `check-testimonial-data.php` - Verify testimonial meta data
- `view-logs.php` - Access detailed sync logs
- `list-field-definitions.php` - View all available fields for each CPT

## Version History

- **v2.6** (2025-11-04): Complete field mapping for Seasons/Shows/Cast, relationship linking
- **v2.5** (2025-11-04): Testimonials rating fix, comprehensive logging improvements
- **v2.1** (Previous): Initial feature-complete release
