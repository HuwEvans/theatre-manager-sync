# Theatre Manager Sync - Changelog

## [2.5] - 2025-11-04

### Added
- **Testimonials Rating Sync**: Fixed testimonials sync to properly extract `RatingNumber` field from SharePoint and display as star ratings
- **Enhanced Field Extraction**: Improved SharePoint API field selection with explicit field parameter support
- **Diagnostic Tools**: Added comprehensive debugging tools (`check-testimonial-data.php`, `view-logs.php`, `inspect-sharepoint.php`, `list-field-definitions.php`)
- **Field Definition Inspector**: Tool to identify correct SharePoint field names and types
- **Better Error Logging**: Enhanced logging with field availability detection and null value checking

### Fixed
- **Critical**: Testimonials RatingNumber field not being synced - was using incorrect field name casing
- **API Field Selection**: SharePoint Graph API now explicitly requests Ratingnumber field in expand parameter
- **Rating Value Handling**: Improved extraction of numeric rating values from SharePoint, handles both direct values and object properties
- **Rating Range Validation**: Ensures ratings stay within 1-5 range for proper star display
- **Undefined Array Key Warnings**: Better error handling for missing SharePoint fields
- **Board Member Name Sync**: Fixed position data appearing in name field (changed Title→Name field, Position→Position field)
- **Board Member Photos**: Fixed to use correct `_tm_photo` field and helper function for image display
- **Log File Path**: Fixed mismatch between logger and logs-page file paths
- **Log Format**: Changed from plain text to JSON format for proper parsing
- **Clear Logs Functionality**: Added proper wp_die() to prevent log recreation on delete
- **Pagination Duplication**: Fixed duplicate pagination controls in logs table
- **Plugin Load Order**: Reordered includes to ensure sync-handlers loads before settings-page

### Changed
- **Testimonials Sync**: Multiple field name fallbacks (RatingNumber, Ratingnumber, Rating, Rate)
- **Board Member Sync**: Updated to check Name field first, then Title as fallback
- **Logging System**: Switched to JSON format for better structured logging and parsing
- **Error Handling**: More robust field extraction with detailed debugging

### Technical Details
- **Files Modified**: 
  - `includes/sync/testimonials-sync.php` - Rating field extraction with better diagnostics
  - `includes/sync/board-member-sync.php` - Name/Position field mapping fix
  - `includes/api/class-tm-graph-client.php` - API field selection improvements
  - `admin/logs-page.php` - Pagination fix and clear logs AJAX handler
  - `includes/logger.php` - JSON log format implementation
  - `theatre-manager-sync.php` - Plugin load order fix

- **Commits**:
  - `cb0490b` - fix: correct spelling to RatingNumber (not Raiting)
  - `60dddc6` - tool: add field definitions inspector
  - `a517537` - debug: add detailed field logging
  - `bd89601` - fix: explicitly select Ratingnumber field in Testimonials list API request
  - `baf8820` - fix: correct SharePoint field name casing to RaitingNumber
  - `dfcd939` - fix: map SharePoint Ratingnumber field to WordPress testimonial rating
  - And 20+ more commits from session

### Known Issues
- None

### Dependencies
- WordPress 5.0+
- PHP 7.4+
- Theatre Manager plugin 2.5+
- Microsoft Graph API access to SharePoint

---

## [2.4] - Previous Release
See git history for details on earlier versions.
