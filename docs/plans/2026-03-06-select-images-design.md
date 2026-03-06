# Design: Select Images to Run Auto Alt Tags

**Date:** 2026-03-06
**Status:** Approved

## Overview

Add a "Select Images" tab to the plugin's admin page (Media → Auto Alt Tags) so users can cherry-pick specific images to generate alt tags for, rather than always processing all missing images at once.

## Scope

- Images shown are limited to those **missing alt text** (no toggle needed)
- The existing "Generate All" tab and workflow remain **completely unchanged**
- No new database tables or options required

## UI Layout

The admin page gains two tabs at the top:

- **Generate All** — existing content, untouched
- **Select Images** — new tab

### Select Images tab structure

1. **Thumbnail grid** — 4 columns, 24 images per page. Each cell shows the medium thumbnail, filename below it, and a checkbox overlay in the top-left corner.
2. **Toolbar** (above grid):
   - "Select All" / "Deselect All" toggle button
   - Count label: "X of Y selected"
3. **Pagination** — Prev / Next buttons + "Page N of M" indicator
4. **"Generate Alt Tags for Selected (N)"** button — disabled until at least 1 image is checked
5. Once generation starts, the existing progress bar + Stop button appear (same as Generate All tab)

Checkbox selections **persist across pagination** — checking images on page 1, navigating to page 2, checking more, then clicking Generate processes all of them.

On completion, processed thumbnails receive a **green checkmark overlay** so you can see what was done. The button resets; uncheck and re-check to re-run any image.

## Data Flow

### New AJAX endpoint: `auto_alt_get_missing_images`

- **Input:** `page` (int, default 1), `per_page` (int, default 24), `nonce`
- **Query:** attachments where `_wp_attachment_image_alt` is empty or not set
- **Output:** `{ images: [{id, thumbnail_url, title}], total, page, per_page }`

### Modified `process_alt_tags` AJAX handler

- If `image_ids[]` is POSTed → process only those IDs, skip the "find all missing" query
- If no `image_ids` → existing behavior unchanged (find and process all missing in batches)
- The configured batch size still applies (e.g. 30 selected + batch size 10 = 3 rounds)

### JS state

- `selectedIds` — a `Set` of checked attachment IDs, maintained across page navigation
- On "Generate" click: full `selectedIds` array is chunked by `batch_size` and sent round-by-round, reusing the existing `processAltTags` loop
- Progress: `processed / selectedIds.size` (e.g. "Processed 10/30 images")

## Components Affected

| File | Change |
|------|--------|
| `auto-alt-tags.php` | Add `auto_alt_get_missing_images` AJAX handler; modify `process_alt_tags` to accept optional `image_ids[]` |
| `assets/js/admin.js` | Add tab switching, grid rendering, checkbox state, pagination, modified process loop |
| `assets/css/admin.css` | Thumbnail grid styles, checkbox overlay, green checkmark overlay, tab styles |

## What Is NOT Changing

- The "Generate All" tab, its AJAX handlers, and all existing JS functions
- Settings, API configuration, rate limits, debug log, CSV import/export
- WP-CLI integration
- Media Library column and filter
- Auto-generation on upload / background queue
