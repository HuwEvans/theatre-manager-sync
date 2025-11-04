# Theatre Manager Sync

Sync SharePoint lists to Theatre Manager Custom Post Types via Microsoft Graph API.

**Version:** 2.6

This plugin is a companion to the Theatre Manager plugin and synchronizes SharePoint lists into Theatre Manager Custom Post Types (one-way sync). Data flows from SharePoint → WordPress automatically.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [SharePoint Setup](#sharepoint-setup)
- [Usage](#usage)
- [Troubleshooting](#troubleshooting)
- [Support](#support)

## Features

✅ **8 CPT Syncs**
- Advertisers
- Board Members  
- Cast
- Contributors
- Seasons
- Shows
- Sponsors
- Testimonials

✅ **Automatic Field Mapping**
- 40+ SharePoint fields synced automatically
- Intelligent type conversion
- Relationship linking (Cast→Show, Shows→Season)

✅ **Image Management**
- Automatic image sync and caching
- Orphaned attachment detection
- Support for multiple image fields per CPT

✅ **Admin Interface**
- Settings page with 4 tabs
- Real-time sync logs with JSON formatting
- Manual sync triggers with dry-run mode
- Data cleanup functionality

✅ **Developer Features**
- Comprehensive debug logging
- Field extraction diagnostics
- Detailed error messages
- PowerShell script for automated setup

## Requirements

- **WordPress:** 6.5+
- **PHP:** 8.1+
- **Theatre Manager Plugin:** 2.6+
- **Microsoft Graph API:** Active credentials with Sites.Selected permission
- **SharePoint:** Office 365 environment with list management access

## Installation

### Via WordPress Admin

1. Download `theatre-manager-sync-2.6.zip`
2. Go to Plugins → Add New → Upload Plugin
3. Select the ZIP file and activate

### Manual Installation

1. Extract `theatre-manager-sync-2.6.zip`
2. Upload `theatre-manager-sync` folder to `/wp-content/plugins/`
3. Activate from WordPress Plugins page

## Configuration

### 1. Microsoft Graph API Setup

First, register your app with Azure AD:

1. Go to [Azure Portal](https://portal.azure.com)
2. Create New App Registration
3. Grant permissions: `Sites.Selected` (Application)
4. Create client secret
5. Note your **Tenant ID**, **Client ID**, and **Client Secret**

### 2. SharePoint Lists

See **[SHAREPOINT_CONFIGURATION.md](./SHAREPOINT_CONFIGURATION.md)** for detailed setup instructions.

**Quick Start:**
- Create 8 SharePoint lists with proper columns
- Use PowerShell script: `.\create-sharepoint-lists.ps1`
- Or follow manual setup guide

### 3. Plugin Settings

1. Go to WordPress Admin → Theatre Manager Sync
2. Click **Settings** tab
3. Enter your **Tenant ID**, **Client ID**, **Client Secret**
4. Enter **Site ID** (from SharePoint)
5. Save settings

### 4. Test Connection

1. Go to Theatre Manager Sync → Settings
2. Check **Dry Run** checkbox
3. Select a CPT and click **Run Sync**
4. Check **Logs** tab for output

## SharePoint Setup

### Automated Setup (Recommended)

Use the included PowerShell script to create all lists automatically:

```powershell
.\create-sharepoint-lists.ps1 -SiteUrl "https://tenant.sharepoint.com/sites/yoursite" `
  -TenantId "your-tenant-id" -ClientId "your-app-id" -Interactive
```

**Requires:**
- PowerShell 7+
- PnP.PowerShell module: `Install-Module PnP.PowerShell`
- SharePoint Site Admin permissions

### Manual Setup

See **[SHAREPOINT_CONFIGURATION.md](./SHAREPOINT_CONFIGURATION.md)** for:
- Complete list of all 8 lists
- Column names and types
- Field relationships
- Step-by-step setup instructions

### Configuration Reference

**File:** `sharepoint-list-config.json`

Contains complete documentation of all lists, columns, and field types for:
- Reference
- Backup
- Environment migration
- Validation

## Usage

### Running a Sync

1. Go to **Theatre Manager Sync** in WordPress Admin
2. Choose sync type (all CPTs or specific ones)
3. Optionally check **Dry Run** to preview without saving
4. Click **Run Sync**

### Viewing Logs

1. Go to **Theatre Manager Sync** → **Logs** tab
2. Filter by CPT or date range
3. Search for specific items
4. Download logs as needed

### Monitoring Fields

Enable debug logging to see field extraction:

1. Run a sync in dry-run mode
2. Check logs for `[SEASONS_DEBUG]`, `[SHOWS_DEBUG]`, etc.
3. Verify all expected fields appear

## Synced CPTs

### Advertisers
- Company, Contact, Website, Logo, Description

### Board Members
- Name, Position, Photo, Bio, Contact Info

### Cast
- Character Name, Actor Name, Headshot, Show Link

### Contributors
- Name, Company, Tier, Donation Date/Amount

### Seasons
- Name, Dates, Current/Upcoming Flags, 5 Image Fields

### Shows
- Name, Author, Director, Dates, Program URL, Season Link

### Sponsors
- Name, Company, Sponsorship Level, Logo, Website

### Testimonials
- Comment, Author, Rating (1-5 stars), Date, Approval

## Troubleshooting

### General Issues

**"Failed to fetch items from SharePoint"**
- Check Microsoft Graph API credentials
- Verify Site ID is correct
- Ensure list names match exactly (case-sensitive)

**"Fields not syncing"**
- Check SharePoint field names match documentation
- Enable debug logging
- Check WordPress error log for details

**"Images not appearing"**
- Verify image URLs are publicly accessible
- Check attachment IDs are valid
- Look for orphaned attachment warnings in logs

### Debug Tools

The plugin includes diagnostic scripts:

- `inspect-sharepoint.php` - View SharePoint list structure
- `check-testimonial-data.php` - Verify testimonial meta data
- `view-logs.php` - Access detailed sync logs
- `list-field-definitions.php` - View all available fields

Run these from WordPress root: `php inspect-sharepoint.php`

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| Invalid tenant | Tenant ID wrong | Check Azure Portal |
| Site not found | Site URL/ID wrong | Verify SharePoint site |
| Permission denied | Insufficient permissions | Grant Sites.Selected permission |
| Field not found | Wrong field name | Check SharePoint exact spelling |
| Attachment error | File not accessible | Verify URL/permissions |

## Architecture

### Sync Flow

```
SharePoint List
    ↓ (Graph API)
TM_Graph_Client
    ↓ (fields extracted)
Sync Handler (seasons-sync.php, etc.)
    ↓ (create/update posts)
WordPress CPT
    ↓ (store metadata)
Post Meta (_tm_fieldname)
    ↓ (sync images)
Media Library (attachment IDs)
```

### Field Mapping Pattern

Each sync file follows this pattern:
1. Fetch list items via Graph API
2. Extract fields from SharePoint response
3. Check for existing WordPress post via `_tm_sp_id`
4. Create/update post with synced data
5. Store metadata with `_tm_` prefix
6. Sync images to Media Library

### Image Syncing

- Images stored as attachment IDs in post meta
- Original URLs cached in metadata
- Orphaned images detected and cleaned up
- Supports both Hyperlink and Attachment fields

## File Structure

```
theatre-manager-sync/
├── theatre-manager-sync.php          # Plugin entry point
├── README.md                         # This file
├── SHAREPOINT_CONFIGURATION.md       # SharePoint setup guide
├── create-sharepoint-lists.ps1       # Automated list creation
├── sharepoint-list-config.json       # Field configuration reference
├── admin/
│   ├── admin-menu.php               # Menu structure
│   ├── settings-page.php            # Settings UI
│   ├── sync-page.php                # Sync UI
│   ├── logs-page.php                # Logs viewer
│   └── assets/js/admin-sync.js      # Admin JavaScript
├── includes/
│   ├── logger.php                   # Logging functions
│   ├── helpers.php                  # Utility functions
│   ├── api/
│   │   └── class-tm-graph-client.php  # Graph API client
│   └── sync/
│       ├── sync-handlers.php        # Sync orchestration
│       ├── folder-discovery.php     # Image cache system
│       ├── generic-image-sync.php   # Image handling
│       ├── advertisers-sync.php
│       ├── board-member-sync.php
│       ├── cast-sync.php
│       ├── contributors-sync.php
│       ├── seasons-sync.php
│       ├── shows-sync.php
│       ├── sponsors-sync.php
│       └── testimonials-sync.php
└── tools/                            # Diagnostic tools
    ├── inspect-sharepoint.php
    ├── check-testimonial-data.php
    └── list-field-definitions.php
```

## Support & Documentation

- **Full Configuration:** See `SHAREPOINT_CONFIGURATION.md`
- **Setup Script:** See `create-sharepoint-lists.ps1`
- **Field Reference:** See `sharepoint-list-config.json`
- **Changelog:** See `CHANGELOG.md`
- **Release Notes:** See `RELEASE-NOTES.md`

## License

GNU General Public License v2.0 - See LICENSE file

## Changelog

See `CHANGELOG.md` for complete version history and feature details.

**Current Version:** 2.6 (November 4, 2025)

