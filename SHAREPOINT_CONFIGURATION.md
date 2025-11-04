# SharePoint List Configuration Guide

This document describes how to configure SharePoint lists for use with Theatre Manager Sync plugin.

## Overview

The Theatre Manager Sync plugin syncs data from 8 SharePoint lists to WordPress custom post types. This guide provides the exact list names, column names, and data types needed.

## Table of Contents

1. [General Configuration](#general-configuration)
2. [Lists Overview](#lists-overview)
3. [List Details](#list-details)
   - [Advertisers](#advertisers)
   - [Board Members](#board-members)
   - [Cast](#cast)
   - [Contributors](#contributors)
   - [Seasons](#seasons)
   - [Shows](#shows)
   - [Sponsors](#sponsors)
   - [Testimonials](#testimonials)
4. [Setup Instructions](#setup-instructions)
5. [Automated Setup](#automated-setup)

---

## General Configuration

### Site URL
The plugin uses a single SharePoint site. Configure the site ID in the plugin settings.

### Access Requirements
- SharePoint Site Administrator permissions
- Microsoft Graph API app-only authentication configured
- Sites.Selected permission granted to the app

### Common Column Types
- **Text** - Single line of text
- **Multi-line Text** - Paragraph text
- **Choice** - Dropdown select
- **Date/Time** - Date field
- **Lookup** - Link to another list
- **Hyperlink** - URL field

---

## Lists Overview

| List Name | Primary Key | Main Fields | Purpose |
|-----------|------------|------------|---------|
| Advertisers | Title | Company, Contact, Website, Logo, Description | Business advertisements |
| Board Members | Title | Name, Position, Photo, Bio, Contact | Organization leadership |
| Cast | Title (Index) | CharacterName, ActorName, ShowIDLookup, Headshot | Actor/Character pairings |
| Contributors | Title | Name, Company, Tier, DonationDate, DonationAmount | Financial contributors |
| Seasons | Title | SeasonName, StartDate, EndDate, Images, Flags | Theatre seasons |
| Shows | Title (Index) | ShowName, Author, Director, Dates, SeasonIDLookup | Individual productions |
| Sponsors | Title | Company, SponsorshipLevel, Website, Logo | Event sponsors |
| Testimonials | Title | Comment, Rating, Author, Date | Customer testimonials |

---

## List Details

### Advertisers

**List Name:** `Advertisers`

| Column Name | Type | Required | Notes |
|------------|------|----------|-------|
| Title | Text | Yes | Advertiser name (index) |
| Company | Text | No | Company/business name |
| Contact | Text | No | Contact person name |
| Website | Hyperlink | No | Company website URL |
| Logo | Attachment/Hyperlink | No | Logo image |
| Description | Multi-line Text | No | Advertiser description |

---

### Board Members

**List Name:** `Board Members`

| Column Name | Type | Required | Notes |
|------------|------|----------|-------|
| Title | Text | Yes | Full name (index) - NOT used by sync |
| Name | Text | Yes | Board member full name |
| Position | Text | No | Title/position on board |
| Photo | Attachment/Hyperlink | No | Board member photograph |
| Bio | Multi-line Text | No | Biography |
| Contact | Text | No | Contact information |
| Email | Text | No | Email address |

---

### Cast

**List Name:** `Cast`

| Column Name | Type | Required | Notes |
|------------|------|----------|-------|
| Title | Text | Yes | Index/identifier (not synced) |
| CharacterName | Text | Yes | Character name for the role |
| ActorName | Text | Yes | Actor/performer name |
| ShowIDLookup | Lookup | Yes | Link to Shows list (looks up Show Title) |
| ShowIDLookupShowName | Lookup | No | Returns show name from lookup |
| Headshot | Attachment/Hyperlink | No | Actor headshot image |
| Notes | Multi-line Text | No | Additional notes |

**Lookup Configuration:**
- Source List: Shows
- Source Column: Title

---

### Contributors

**List Name:** `Contributors`

| Column Name | Type | Required | Notes |
|------------|------|----------|-------|
| Title | Text | Yes | Index/identifier (not synced) |
| Name | Text | Yes | Contributor full name |
| Title (used as Company) | Text | No | Company/organization name |
| Tier | Choice | No | Contribution level: Bronze, Silver, Gold, Platinum |
| DonationDate | Date/Time | No | Date of contribution |
| DonationAmount | Currency | No | Contribution amount |
| Contact | Text | No | Contact information |
| Email | Text | No | Email address |

**Choice Options for Tier:**
- Bronze
- Silver
- Gold
- Platinum

---

### Seasons

**List Name:** `Seasons`

| Column Name | Type | Required | Notes |
|------------|------|----------|-------|
| Title | Text | No | Index identifier (not primary) |
| SeasonName | Text | Yes | Season name/year (e.g., "Fall 2025") |
| StartDate | Date/Time | No | Season start date |
| EndDate | Date/Time | No | Season end date |
| IsCurrentSeason | Choice | No | Is this the current season? Yes/No |
| IsUpcomingSeason | Choice | No | Is this an upcoming season? Yes/No |
| WebsiteBanner | Hyperlink | No | Website banner image |
| 3-upFront | Hyperlink | No | 3-up front image (season lineup) |
| 3-upBack | Hyperlink | No | 3-up back image (season lineup) |
| SMSquare | Hyperlink | No | Social media square image |
| SMPortrait | Hyperlink | No | Social media portrait image |
| Description | Multi-line Text | No | Season description |

**Choice Options:**
- IsCurrentSeason: Yes, No
- IsUpcomingSeason: Yes, No

---

### Shows

**List Name:** `Shows`

| Column Name | Type | Required | Notes |
|------------|------|----------|-------|
| Title | Text | Yes | Index/identifier (not synced) |
| ShowName | Text | Yes | Show/production name |
| Author | Text | No | Playwright/author name |
| Director | Text | No | Director name |
| AssociateDirector | Text | No | Associate director name |
| StartDate | Date/Time | No | Performance start date |
| EndDate | Date/Time | No | Performance end date |
| ShowDatesText | Text | No | Text description of show dates |
| Description | Multi-line Text | No | Show synopsis/description |
| ProgramFileURL | Hyperlink | No | Program PDF URL |
| SeasonIDLookup | Lookup | Yes | Link to Seasons list (looks up SeasonName) |
| SeasonIDLookupSeasonName | Lookup | No | Returns season name from lookup |

**Lookup Configuration:**
- Source List: Seasons
- Source Column: SeasonName

---

### Sponsors

**List Name:** `Sponsors`

| Column Name | Type | Required | Notes |
|------------|------|----------|-------|
| Title | Text | Yes | Sponsor name |
| Company | Text | No | Company/business name |
| SponsorshipLevel | Choice | Yes | Sponsorship tier: Platinum, Gold, Silver, Bronze |
| Website | Hyperlink | No | Sponsor website URL |
| Logo | Attachment/Hyperlink | No | Sponsor logo |
| Contact | Text | No | Contact person |
| Email | Text | No | Email address |

**Choice Options for SponsorshipLevel:**
- Platinum
- Gold
- Silver
- Bronze

---

### Testimonials

**List Name:** `Testimonials`

| Column Name | Type | Required | Notes |
|------------|------|----------|-------|
| Title | Text | Yes | Testimonial title/summary |
| Comment | Multi-line Text | Yes | Full testimonial text |
| RatingNumber | Number | Yes | Rating: 1-5 (displays as stars) |
| Author | Text | Yes | Testimonial author name |
| AuthorTitle | Text | No | Author title/role |
| AuthorCompany | Text | No | Author company |
| Date | Date/Time | No | Testimonial date |
| Approved | Choice | No | Approved for display? Yes/No |

**Choice Options:**
- Approved: Yes, No

**RatingNumber Accepted Values:**
- 1 (one star)
- 2 (two stars)
- 3 (three stars)
- 4 (four stars)
- 5 (five stars)

---

## Setup Instructions

### Manual Setup

1. **Log into SharePoint**
   - Navigate to your SharePoint site
   - Ensure you have site administrator permissions

2. **Create Each List**
   - For each list in the overview above:
     - Click "New" → "List"
     - Enter the list name exactly as shown
     - Click "Create"

3. **Add Columns**
   - For each list, add columns with the exact names and types shown
   - Set required fields as indicated
   - Configure lookups with appropriate source lists

4. **Test Sync**
   - Add test data to one list
   - Run a dry-run sync from Theatre Manager Sync settings
   - Verify data appears in WordPress

### Lookup Column Dependencies

When setting up lookups, create lists in this order:
1. Seasons (no dependencies)
2. Shows (depends on Seasons)
3. Cast (depends on Shows)
4. All others (no dependencies)

---

## Automated Setup

### Using PowerShell Script

See `create-sharepoint-lists.ps1` for automated list creation.

**Requirements:**
- PowerShell 7+
- PnP.PowerShell module
- SharePoint Site Admin credentials
- Microsoft Graph API access

**Usage:**
```powershell
.\create-sharepoint-lists.ps1 -SiteUrl "https://yourtenant.sharepoint.com/sites/yoursite" `
  -TenantId "your-tenant-id" `
  -ClientId "your-app-id" `
  -ClientSecret "your-app-secret"
```

### Using JSON Configuration File

See `sharepoint-list-config.json` for complete field configuration in JSON format.

**This file can be used to:**
- Document current configuration
- Recreate lists in a different environment
- Reference field settings
- Validate list structure

---

## Important Notes

### Field Naming
- SharePoint field names are **case-sensitive** in the API
- Use exact column names as shown above
- Don't use spaces in lookup result field names

### Image/Attachment Fields
- Use "Hyperlink" type for URLs (recommended)
- "Attachment" type also works but adds metadata
- URLs should be direct links to images

### Lookup Fields
- Always create the source list first
- Configure lookup to reference the "Title" column by default
- The lookup result field is automatically named `[SourceColumn][SourceList]`
- Example: Linking Shows.Title from Cast creates `ShowIDLookupShowName`

### Choice Fields
- Enter exact choice values as shown
- Choices are case-sensitive

### Text Length
- Use "Text" for short strings (< 255 chars)
- Use "Multi-line Text" for longer content

### Migration
If migrating from another system:
1. Create all lists with proper columns
2. Import data using SharePoint CSV import or Microsoft Power Automate
3. Run initial sync in dry-run mode to verify
4. Check Theatre Manager Sync logs for any issues

---

## Troubleshooting

### Fields Not Syncing

1. **Check field names**
   - Verify exact spelling and capitalization
   - Look at debug logs in Theatre Manager Sync plugin

2. **Check field types**
   - Ensure field type matches what's expected
   - Text fields should be "Text" not "Number"

3. **Enable debug logging**
   - Run dry-run sync
   - Check WordPress debug log for [DEBUG] entries
   - Look for field extraction messages

### Lookup Fields Not Working

1. **Source list exists**
   - Verify source list is created
   - Ensure source column contains data

2. **Create lookups after source data**
   - Don't create lookup field before source list has items

3. **Link direction**
   - Verify "Show ID Lookup Show Name" is set correctly
   - The lookup result field should pull from the source list

### Images Not Syncing

1. **Use Hyperlink fields**
   - Image fields should be "Hyperlink" type
   - Ensure URLs are valid and publicly accessible

2. **Check file extensions**
   - Common formats: .jpg, .png, .gif, .webp

---

## Support

For issues with SharePoint configuration:
1. Check the Theatre Manager Sync logs
2. Review the sync debug output
3. Verify field names match exactly
4. Test with simple text fields first
5. Check Microsoft Graph API permissions

---

## Additional Resources

- [Microsoft SharePoint Documentation](https://support.microsoft.com/sharepoint)
- [Microsoft Graph API Documentation](https://docs.microsoft.com/graph)
- [Theatre Manager Sync Plugin README](./README.md)
- [Theatre Manager Sync Troubleshooting Guide](./TROUBLESHOOTING.md)
