# MSP Google Reviews Widget

**Version:** 1.0.0
**Author:** Joshua Garza
**Organization:** MSP WebOps
**Requires WordPress:** 6.0+
**Requires PHP:** 8.0+

---

## Overview

MSP Google Reviews Widget is a production-ready WordPress plugin that provides a custom Elementor widget for displaying Google Business reviews.

The plugin fetches review data from the Google Places API and stores it in custom WordPress database tables. The Elementor widget renders only from this locally cached data and **never calls the Google API during page rendering**.

Design principle: **Ingest full, render partial.**

---

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- Elementor (free or Pro)
- Google Cloud project with the **Places API** enabled
- A Google Places API key

---

## Installation

1. Download or build the plugin `.zip` file
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Upload `msp-google-reviews.zip` and click **Install Now**
4. Click **Activate Plugin**

---

## Initial Setup

### Step 1 — Configure your API key

1. Go to **Google Reviews → Settings** in the WordPress admin menu
2. Enter your Google Places API key
3. Click **Save Settings**

Your API key must have the **Places API** enabled in Google Cloud Console.

### Step 2 — Add the widget

1. Edit any Elementor page
2. Find the **MSP Google Reviews** widget in the Elementor widget panel (search "reviews" or "MSP")
3. Drag it onto your page canvas

### Step 3 — Bind a location

1. In the widget settings, expand the **Location** section
2. Type a business name or address in the search field
3. Click **Search**
4. Select the correct location from the results
5. The plugin will automatically fetch and store reviews

The location will also appear in **Google Reviews → Locations** for management.

---

## Widget Options

### Location
- Search for and bind a Google Business location
- The bound location is shared globally — other widgets can reference the same location

### Display
- **Number of Reviews:** 1–5 reviews displayed in the carousel
- **Show Aggregate Rating:** Toggle the rating summary below the carousel

### Filters
- **Star Rating Filter:** Select specific star ratings to display (e.g., 5-star only, or 4 and 5-star)
- **Include Keywords:** Only show reviews containing specified words (comma-separated)
- **Exclude Keywords:** Hide reviews containing specified words (comma-separated)
- If include and exclude keywords both match the same review, exclusion wins
- Filtering is non-destructive — stored data is never altered

### Carousel
- **Autoplay:** Enable/disable automatic slide advancement
- **Autoplay Interval:** Time between slides in milliseconds (1000–30000)
- **Arrow Navigation:** Show/hide previous and next arrows

### CTA Buttons
- **Read More Reviews:** Show/hide button + optional custom URL
- **Write a Review:** Show/hide button + optional custom URL
- If no custom URL is set, the buttons link to the bound Google Business profile

### Styling
- Card background color
- Text color
- Star color
- Button background and text color

---

## Review Card Format

Each review card displays in this order:

```
★★★★★
Great service and very friendly staff.
— G. W.
```

- Reviewer names are displayed as initials only (e.g. George Washington → G. W.)
- Reviewer profile photos are never shown
- Review text longer than 255 characters is truncated with a "Read more" toggle

---

## Fallback Behavior

- If filtering results in zero eligible reviews, a summary-only view is shown (Google badge, aggregate rating, review count, CTA buttons)
- If a scheduled sync fails, the last successfully cached dataset continues to be served
- Technical errors are never displayed to site visitors

---

## Admin Interface

### Settings Page (`Google Reviews → Settings`)
- Configure Google Places API key
- Enable/disable full data deletion on uninstall (default: OFF — data is retained)

### Locations Page (`Google Reviews → Locations`)
- View all saved locations with sync status and timestamps
- Trigger manual review refresh per location
- Delete locations (associated reviews are marked inactive, not deleted)

---

## Sync Behavior

- **Initial sync:** Triggered automatically when a location is bound in the widget editor
- **Scheduled sync:** Runs every 24 hours via WordPress cron (`msp_google_reviews_daily_sync`)
- **Manual sync:** Available via the Locations admin page or the AJAX refresh endpoint

Sync upserts reviews by a deterministic identifier. Reviews that disappear from the API response are marked inactive (stale) rather than deleted.

Empty reviews (null, blank, or whitespace-only text) are never stored.

---

## Privacy

The plugin follows strict privacy-safe rendering rules:

| Data | Stored | Displayed |
|---|---|---|
| Full author name | Yes (canonical) | No — initials only |
| Profile photo URL | Yes (canonical) | Never |
| Review text | Yes | Yes (escaped plain text) |
| Aggregate rating | Yes | Yes |

All displayed content is properly escaped for its HTML context.

---

## Security

- All admin actions require `manage_options` capability
- All form submissions and AJAX endpoints require nonce validation
- Review text is rendered as plain escaped text — never as HTML
- User-configurable URLs are validated — `javascript:`, `data:`, and `vbscript:` schemes are rejected
- All database queries use `$wpdb->prepare()`
- The sync endpoint is never publicly accessible

---

## Analytics Readiness

The schema preserves canonical data for future analytics export:

- `relative_time_description` — source-faithful human-readable timestamp
- `author_profile_photo_url` — stored canonically, never rendered
- `raw_payload` — full API JSON per review, for audit and export
- `source_last_seen_at` — tracks when each review was last present in the API response
- `review_identifier` — deterministic stable hash for reconciliation

BigQuery synchronization is not implemented in v1 but the schema supports it.

---

## Uninstall

**Default behavior (safe):**
- Scheduled cron event is removed
- All plugin options are deleted
- Custom database tables are **retained** (prevents accidental data loss)

**Full cleanup:**
Enable "Delete all plugin data on uninstall" in Settings before deleting the plugin. This will drop the custom tables.

---

## Support

For internal support, contact: **MSP WebOps**
Plugin maintained by: **Joshua Garza**

---

## Changelog

### [1.0.0] - 2026-08-04
- First tagged production release
- Added .gitignore and removed OS/build artifacts from version control
- Added build.sh for reproducible, correctly-versioned release zips
- Added .gitattributes to enforce LF line endings for shell scripts
- Added GitHub Actions workflow to build and publish a release automatically
  on tag push, with auto-generated release notes
- Added GitHub-based auto-update support (Plugin Update Checker), so
  installed sites can check for and install new versions directly from
  wp-admin
