# Testimonials Rating Sync - Diagnostic Guide

## Issue
SharePoint Testimonials list Rating field is not syncing properly to WordPress `_tm_rating` meta field.

## What I've Fixed

### 1. **Enhanced Rating Extraction** (Commit: 9e49979)
- Now handles both simple values and complex objects from SharePoint
- Tries multiple field names: `Rating`, `Rate`, `Stars`, `Score`
- Added fallback to extract `value` property if it's an object
- Added detailed logging of raw rating values

### 2. **Metadata Verification**
- Logs the actual saved rating after sync
- Compares extracted vs. saved values to catch storage issues

### 3. **Diagnostic Script**
- Created `debug-testimonials-fields.php` to inspect SharePoint fields directly

## How to Diagnose

### Step 1: Check Available Fields
Run the debug script or check the logs when syncing testimonials. Look for "Extracted fields" log entry showing:
- `available_fields`: List of all fields in the SharePoint item
- `raw_rating`: The actual Rating value from SharePoint
- `rating`: The converted integer value

### Step 2: Run a Dry-Run Sync
1. WordPress Admin → Theatre Manager Sync
2. Check "Dry Run" checkbox
3. Click "Run Sync" for Testimonials
4. Check the logs for entries showing:
   ```
   Extracted fields: { rating: X, raw_rating: "...", available_fields: [...] }
   Saved testimonial metadata: { rating: X, saved_rating: X }
   ```

### Step 3: Check WordPress Database
Verify the rating was actually saved:
```sql
SELECT post_id, meta_key, meta_value FROM wp_postmeta 
WHERE meta_key = '_tm_rating' 
ORDER BY post_id DESC LIMIT 5;
```

### Step 4: Check Display
View the testimonials shortcode output:
- Do stars appear?
- Are they the correct count?
- Check shortcode rendering in `includes/shortcodes/testimonials-shortcode.php`

## Expected Log Flow

```
Starting testimonials sync
Fetching SharePoint testimonials data
Successfully fetched items, count: 2

Processing testimonial item
Extracted fields: { sp_id: "xxx", name: "John", rating: 5, available_fields: ["Title", "Comment", "Rating", ...] }
Processing testimonial: { name: "John", rating: 5, sp_id: "xxx" }
Created/Updated testimonial
Saved testimonial metadata: { post_id: 123, rating: 5, saved_rating: "5" }

Testimonials sync completed: 2 testimonial(s) synced
```

## Possible Issues & Solutions

### Issue A: Ratings showing as 0
**Cause**: SharePoint field name is not "Rating" or "Rate"
**Solution**: Check logs for `available_fields` list and identify correct field name
**Action**: Update line 50 in testimonials-sync.php with correct field name

### Issue B: Rating is complex object (e.g., `{value: 5}`)
**Cause**: SharePoint returns choice field as object
**Solution**: Already fixed! Now extracts `.value` property
**Action**: Run sync again with enhanced code

### Issue C: Rating stored but not displaying
**Cause**: Shortcode issue or display options problem
**Solution**: Check `includes/shortcodes/testimonials-shortcode.php` line 79-87
**Verify**: 
- `$rating = intval(get_post_meta(get_the_ID(), '_tm_rating', true))`
- Loop renders correct number of symbols

### Issue D: Dry-run shows rating but sync doesn't save it
**Cause**: WordPress permissions or meta saving issue
**Solution**: Check WordPress user permissions and error logs
**Action**: Run full sync (not dry-run) and check `wp_postmeta` table

## Next Steps

1. **Run testimonials sync with dry-run enabled**
2. **Share the detailed logs** showing:
   - What `available_fields` are returned
   - What `raw_rating` value is extracted
   - Whether `saved_rating` matches extracted rating
3. **If rating is 0 in logs**:
   - Check which field contains the rating in SharePoint
   - Update the field name in testimonials-sync.php line 50

## Code References

- **Sync File**: `includes/sync/testimonials-sync.php` (lines 45-65 for extraction)
- **Shortcode**: `includes/shortcodes/testimonials-shortcode.php` (lines 79-87 for display)
- **CPT Definition**: `Theatre-Manager/cpt/testimonials.php` (lines 60-75 for meta box)

The entire chain is now properly instrumented with logging to help identify where the issue occurs.
