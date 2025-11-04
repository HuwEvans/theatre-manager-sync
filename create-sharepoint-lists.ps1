# Theatre Manager SharePoint Lists Creation Script
# 
# This script creates all required SharePoint lists for Theatre Manager Sync
# 
# Requirements:
#   - PowerShell 7+
#   - PnP.PowerShell module: Install-Module PnP.PowerShell
#   - SharePoint Site Admin permissions
#   - Valid credentials (certificate or user)
#
# Usage:
#   .\create-sharepoint-lists.ps1 -SiteUrl "https://tenant.sharepoint.com/sites/yoursite"
#
# With certificate authentication:
#   .\create-sharepoint-lists.ps1 -SiteUrl "https://tenant.sharepoint.com/sites/yoursite" `
#     -TenantId "12345-67890" -ClientId "app-id" -CertificatePath "C:\cert.pfx"

param(
    [Parameter(Mandatory=$true)]
    [string]$SiteUrl,
    
    [Parameter(Mandatory=$false)]
    [string]$TenantId,
    
    [Parameter(Mandatory=$false)]
    [string]$ClientId,
    
    [Parameter(Mandatory=$false)]
    [string]$CertificatePath,
    
    [Parameter(Mandatory=$false)]
    [string]$Username,
    
    [Parameter(Mandatory=$false)]
    [string]$Password,
    
    [Parameter(Mandatory=$false)]
    [switch]$Interactive
)

# Color output
function Write-Success {
    param([string]$Message)
    Write-Host "✓ $Message" -ForegroundColor Green
}

function Write-Error-Custom {
    param([string]$Message)
    Write-Host "✗ $Message" -ForegroundColor Red
}

function Write-Info {
    param([string]$Message)
    Write-Host "ℹ $Message" -ForegroundColor Cyan
}

function Write-Warning-Custom {
    param([string]$Message)
    Write-Host "⚠ $Message" -ForegroundColor Yellow
}

# Verify PnP.PowerShell is installed
Write-Info "Checking for PnP.PowerShell module..."
$module = Get-Module -ListAvailable -Name "PnP.PowerShell"
if (-not $module) {
    Write-Error-Custom "PnP.PowerShell module not found!"
    Write-Info "Install it with: Install-Module PnP.PowerShell -Scope CurrentUser"
    exit 1
}
Write-Success "PnP.PowerShell module found"

# Connect to SharePoint
Write-Info "Connecting to SharePoint site: $SiteUrl"

try {
    if ($CertificatePath) {
        # Certificate authentication
        Connect-PnPOnline -Url $SiteUrl -ClientId $ClientId -Tenant $TenantId `
            -CertificatePath $CertificatePath -ErrorAction Stop
        Write-Success "Connected using certificate authentication"
    }
    elseif ($Username -and $Password) {
        # Username/password authentication
        Connect-PnPOnline -Url $SiteUrl -UserName $Username -Password $Password -ErrorAction Stop
        Write-Success "Connected using username/password authentication"
    }
    elseif ($TenantId -and $ClientId) {
        # Interactive device flow
        Connect-PnPOnline -Url $SiteUrl -ClientId $ClientId -Tenant $TenantId `
            -Interactive -ErrorAction Stop
        Write-Success "Connected using interactive authentication"
    }
    else {
        # Default interactive
        Connect-PnPOnline -Url $SiteUrl -Interactive -ErrorAction Stop
        Write-Success "Connected using interactive authentication"
    }
}
catch {
    Write-Error-Custom "Failed to connect to SharePoint: $_"
    exit 1
}

# Function to create a list
function New-SharePointList {
    param(
        [string]$ListName,
        [string]$ListDescription
    )
    
    Write-Info "Creating list: $ListName"
    try {
        $list = New-PnPList -Title $ListName -Template GenericList -ErrorAction Stop
        Write-Success "List created: $ListName (ID: $($list.Id))"
        return $list
    }
    catch {
        Write-Warning-Custom "List $ListName may already exist or failed to create: $_"
        # Try to get existing list
        $existingList = Get-PnPList -Identity $ListName -ErrorAction SilentlyContinue
        if ($existingList) {
            Write-Info "Using existing list: $ListName"
            return $existingList
        }
        throw $_
    }
}

# Function to add a column
function Add-SharePointColumn {
    param(
        [object]$List,
        [string]$ColumnName,
        [string]$ColumnType,
        [hashtable]$ColumnProperties = @{}
    )
    
    # Check if column already exists
    $existingField = Get-PnPField -List $List.Title -Identity $ColumnName -ErrorAction SilentlyContinue
    if ($existingField) {
        Write-Info "Column already exists: $ColumnName"
        return $existingField
    }
    
    try {
        Write-Info "  Adding column: $ColumnName ($ColumnType)"
        
        switch ($ColumnType) {
            "Text" {
                Add-PnPField -List $List.Title -DisplayName $ColumnName -InternalName $ColumnName `
                    -Type Text @ColumnProperties -ErrorAction Stop | Out-Null
            }
            "MultilineText" {
                Add-PnPField -List $List.Title -DisplayName $ColumnName -InternalName $ColumnName `
                    -Type Note @ColumnProperties -ErrorAction Stop | Out-Null
            }
            "Choice" {
                Add-PnPField -List $List.Title -DisplayName $ColumnName -InternalName $ColumnName `
                    -Type Choice -Choices $ColumnProperties.Choices @ColumnProperties -ErrorAction Stop | Out-Null
            }
            "Date" {
                Add-PnPField -List $List.Title -DisplayName $ColumnName -InternalName $ColumnName `
                    -Type DateTime @ColumnProperties -ErrorAction Stop | Out-Null
            }
            "Currency" {
                Add-PnPField -List $List.Title -DisplayName $ColumnName -InternalName $ColumnName `
                    -Type Currency @ColumnProperties -ErrorAction Stop | Out-Null
            }
            "Number" {
                Add-PnPField -List $List.Title -DisplayName $ColumnName -InternalName $ColumnName `
                    -Type Number @ColumnProperties -ErrorAction Stop | Out-Null
            }
            "Hyperlink" {
                Add-PnPField -List $List.Title -DisplayName $ColumnName -InternalName $ColumnName `
                    -Type URL @ColumnProperties -ErrorAction Stop | Out-Null
            }
            default {
                Write-Warning-Custom "Unknown column type: $ColumnType"
            }
        }
        Write-Success "  ✓ Column added: $ColumnName"
    }
    catch {
        Write-Error-Custom "Failed to add column $ColumnName : $_"
    }
}

# Function to add a lookup field
function Add-SharePointLookup {
    param(
        [object]$List,
        [string]$ColumnName,
        [string]$SourceListName,
        [string]$SourceColumnName = "Title"
    )
    
    # Check if column already exists
    $existingField = Get-PnPField -List $List.Title -Identity $ColumnName -ErrorAction SilentlyContinue
    if ($existingField) {
        Write-Info "  Lookup column already exists: $ColumnName"
        return $existingField
    }
    
    try {
        Write-Info "  Adding lookup column: $ColumnName (from $SourceListName)"
        
        $sourceList = Get-PnPList -Identity $SourceListName -ErrorAction Stop
        
        Add-PnPField -List $List.Title -DisplayName $ColumnName -InternalName $ColumnName `
            -Type Lookup -LookupListId $sourceList.Id -LookupColumnName $SourceColumnName `
            -ErrorAction Stop | Out-Null
        
        Write-Success "  ✓ Lookup column added: $ColumnName"
    }
    catch {
        Write-Error-Custom "Failed to add lookup column $ColumnName : $_"
    }
}

# Create all lists
Write-Info "Creating SharePoint lists for Theatre Manager Sync..."
Write-Info ""

# 1. ADVERTISERS
Write-Info "=== ADVERTISERS LIST ==="
try {
    $advList = New-SharePointList "Advertisers" "Business advertisements"
    Add-SharePointColumn $advList "Company" "Text"
    Add-SharePointColumn $advList "Contact" "Text"
    Add-SharePointColumn $advList "Website" "Hyperlink"
    Add-SharePointColumn $advList "Logo" "Hyperlink"
    Add-SharePointColumn $advList "Description" "MultilineText"
    Write-Success "Advertisers list created successfully"
} catch {
    Write-Error-Custom "Failed to create Advertisers list"
}

Write-Info ""

# 2. BOARD MEMBERS
Write-Info "=== BOARD MEMBERS LIST ==="
try {
    $boardList = New-SharePointList "Board Members" "Organization board members"
    Add-SharePointColumn $boardList "Name" "Text"
    Add-SharePointColumn $boardList "Position" "Text"
    Add-SharePointColumn $boardList "Photo" "Hyperlink"
    Add-SharePointColumn $boardList "Bio" "MultilineText"
    Add-SharePointColumn $boardList "Contact" "Text"
    Add-SharePointColumn $boardList "Email" "Text"
    Write-Success "Board Members list created successfully"
} catch {
    Write-Error-Custom "Failed to create Board Members list"
}

Write-Info ""

# 3. SPONSORS
Write-Info "=== SPONSORS LIST ==="
try {
    $sponsorList = New-SharePointList "Sponsors" "Event sponsors"
    Add-SharePointColumn $sponsorList "Company" "Text"
    Add-SharePointColumn $sponsorList "SponsorshipLevel" "Choice" @{
        Choices = @("Platinum", "Gold", "Silver", "Bronze")
    }
    Add-SharePointColumn $sponsorList "Website" "Hyperlink"
    Add-SharePointColumn $sponsorList "Logo" "Hyperlink"
    Add-SharePointColumn $sponsorList "Contact" "Text"
    Add-SharePointColumn $sponsorList "Email" "Text"
    Write-Success "Sponsors list created successfully"
} catch {
    Write-Error-Custom "Failed to create Sponsors list"
}

Write-Info ""

# 4. CONTRIBUTORS
Write-Info "=== CONTRIBUTORS LIST ==="
try {
    $contribList = New-SharePointList "Contributors" "Financial contributors"
    Add-SharePointColumn $contribList "Name" "Text"
    Add-SharePointColumn $contribList "Company" "Text"
    Add-SharePointColumn $contribList "Tier" "Choice" @{
        Choices = @("Bronze", "Silver", "Gold", "Platinum")
    }
    Add-SharePointColumn $contribList "DonationDate" "Date"
    Add-SharePointColumn $contribList "DonationAmount" "Currency"
    Add-SharePointColumn $contribList "Contact" "Text"
    Add-SharePointColumn $contribList "Email" "Text"
    Write-Success "Contributors list created successfully"
} catch {
    Write-Error-Custom "Failed to create Contributors list"
}

Write-Info ""

# 5. TESTIMONIALS
Write-Info "=== TESTIMONIALS LIST ==="
try {
    $testList = New-SharePointList "Testimonials" "Customer testimonials"
    Add-SharePointColumn $testList "Comment" "MultilineText"
    Add-SharePointColumn $testList "RatingNumber" "Number"
    Add-SharePointColumn $testList "Author" "Text"
    Add-SharePointColumn $testList "AuthorTitle" "Text"
    Add-SharePointColumn $testList "AuthorCompany" "Text"
    Add-SharePointColumn $testList "Date" "Date"
    Add-SharePointColumn $testList "Approved" "Choice" @{
        Choices = @("Yes", "No")
    }
    Write-Success "Testimonials list created successfully"
} catch {
    Write-Error-Custom "Failed to create Testimonials list"
}

Write-Info ""

# 6. SEASONS (create before Shows)
Write-Info "=== SEASONS LIST ==="
try {
    $seasonList = New-SharePointList "Seasons" "Theatre seasons"
    Add-SharePointColumn $seasonList "SeasonName" "Text"
    Add-SharePointColumn $seasonList "StartDate" "Date"
    Add-SharePointColumn $seasonList "EndDate" "Date"
    Add-SharePointColumn $seasonList "IsCurrentSeason" "Choice" @{
        Choices = @("Yes", "No")
    }
    Add-SharePointColumn $seasonList "IsUpcomingSeason" "Choice" @{
        Choices = @("Yes", "No")
    }
    Add-SharePointColumn $seasonList "WebsireBanner" "Hyperlink"
    Add-SharePointColumn $seasonList "3-upFront" "Hyperlink"
    Add-SharePointColumn $seasonList "3-upBack" "Hyperlink"
    Add-SharePointColumn $seasonList "SMSquare" "Hyperlink"
    Add-SharePointColumn $seasonList "SMPortrait" "Hyperlink"
    Add-SharePointColumn $seasonList "Description" "MultilineText"
    Write-Success "Seasons list created successfully"
} catch {
    Write-Error-Custom "Failed to create Seasons list"
}

Write-Info ""

# 7. SHOWS (create before Cast)
Write-Info "=== SHOWS LIST ==="
try {
    $showList = New-SharePointList "Shows" "Individual productions"
    Add-SharePointColumn $showList "ShowName" "Text"
    Add-SharePointColumn $showList "Author" "Text"
    Add-SharePointColumn $showList "Director" "Text"
    Add-SharePointColumn $showList "AssociateDirector" "Text"
    Add-SharePointColumn $showList "StartDate" "Date"
    Add-SharePointColumn $showList "EndDate" "Date"
    Add-SharePointColumn $showList "ShowDatesText" "Text"
    Add-SharePointColumn $showList "Description" "MultilineText"
    Add-SharePointColumn $showList "ProgramFileURL" "Hyperlink"
    
    # Wait a moment for Seasons list to be fully created
    Start-Sleep -Seconds 2
    
    Add-SharePointLookup $showList "SeasonIDLookup" "Seasons" "SeasonName"
    Write-Success "Shows list created successfully"
} catch {
    Write-Error-Custom "Failed to create Shows list"
}

Write-Info ""

# 8. CAST (create after Shows)
Write-Info "=== CAST LIST ==="
try {
    $castList = New-SharePointList "Cast" "Actor/character pairings"
    Add-SharePointColumn $castList "CharacterName" "Text"
    Add-SharePointColumn $castList "ActorName" "Text"
    Add-SharePointColumn $castList "Headshot" "Hyperlink"
    Add-SharePointColumn $castList "Notes" "MultilineText"
    
    # Wait a moment for Shows list to be fully created
    Start-Sleep -Seconds 2
    
    Add-SharePointLookup $castList "ShowIDLookup" "Shows" "Title"
    Write-Success "Cast list created successfully"
} catch {
    Write-Error-Custom "Failed to create Cast list"
}

Write-Info ""
Write-Success "SharePoint list creation complete!"
Write-Info "Next steps:"
Write-Info "1. Add test data to your SharePoint lists"
Write-Info "2. Configure Theatre Manager Sync plugin with your site ID and list IDs"
Write-Info "3. Run a dry-run sync to verify connectivity"
Write-Info "4. Check the sync logs in WordPress admin"

Disconnect-PnPOnline
