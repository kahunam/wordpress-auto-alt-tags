# Auto Alt Tags Plugin — Improvements Design

**Date:** 2026-03-05
**Target version:** 1.1.0
**Status:** Approved for implementation

---

## Overview

Three phases of improvements, each independently shippable. No breaking changes to existing settings or data storage.

| Phase | Feature | Priority |
|-------|---------|----------|
| 1 | Auto-generation on upload + WP Cron background processing | High |
| 2 | Media Library integration (column, filter, regenerate, CSV) | Medium |
| 3 | WordPress.org readiness (i18n, uninstall, Plugin Check) | Medium |

---

## Phase 1: Auto-generation on Upload + Background Processing

### Problem

Users must manually visit the admin page and click "Start" to generate alt text. Images uploaded after a bulk run are left without alt text until someone remembers to run it again.

### Design

**New WordPress hooks:**
- `add_attachment` → when a new image is uploaded, push its ID to the processing queue (if auto-generate setting is enabled)
- `auto_alt_tags_process_queue` WP Cron event → fires every 5 minutes, pulls up to `batch_size` IDs from the queue, calls the AI API, saves results, removes processed IDs

**Queue storage:**
- Option key: `auto_alt_queue` — JSON-encoded array of attachment IDs
- No new database table; WP options handle queues of thousands of IDs efficiently
- Cap at 500 IDs to prevent option bloat; oldest-first processing

**New setting:**
- "Auto-generate on upload" checkbox (default: on)
- When off, `add_attachment` hook is skipped entirely

**Admin notice:**
- If `auto_alt_queue` has items, show dismissible notice: *"Auto Alt Tags is processing X images in the background."*
- If queue is non-empty but cron hasn't fired in 30 minutes, suggest checking WP Cron configuration

**Failure handling:**
- Track attempts in postmeta `_auto_alt_attempts` (int)
- After 3 failed attempts, drop from queue and log the ID
- New WP-CLI command: `wp auto-alt retry-failed` — re-queues all failed attachment IDs

**Developer filter:**
- `auto_alt_use_real_cron` (bool) — allows disabling WP Cron in favour of system cron or WP-CLI

### Data flow

```
Image uploaded
  └─> add_attachment hook fires
      └─> [auto-generate enabled?] push ID to auto_alt_queue option
          └─> WP Cron: auto_alt_tags_process_queue (every 5 min)
              └─> pull batch → call AI API → save _wp_attachment_image_alt
                  └─> success: remove ID from queue
                  └─> failure: increment _auto_alt_attempts → drop after 3
```

---

## Phase 2: Media Library Integration

### Problem

Once alt text is generated there is no way to review or manage it without opening each attachment edit screen individually.

### Design

**Custom Media Library column:**
- Registered via `manage_media_columns` / `manage_media_custom_column` filters
- Column label: "Alt Text"
- Content: truncated alt text if present; red "Missing" badge if absent
- Inline "Regenerate" link per row → fires existing AJAX handler for single attachment ID

**Media Library filter:**
- Dropdown above media list: "All | Has Alt Text | Missing Alt Text"
- Implemented via `restrict_manage_posts` + `parse_query` filters on `attachment` post type
- Uses a meta query: presence/absence of `_wp_attachment_image_alt` postmeta

**CSV Export:**
- New AJAX action: `auto_alt_export_csv`
- Output: `ID, Filename, Alt Text, URL` columns
- Uses PHP native `fputcsv` with `php://output` — no library dependency
- Button added to admin page: "Export Alt Text as CSV"

**CSV Import:**
- File upload field on admin page
- Validates: correct column headers, attachment IDs exist in WP, alt text sanitized
- Row-level validation: one bad row does not abort the import
- Updates `_wp_attachment_image_alt` postmeta for each valid row
- Returns structured report: X updated, Y skipped (already had alt text), Z errors (ID not found)

---

## Phase 3: WordPress.org Readiness

### Problem

The plugin README references "WordPress.org submission — coming soon." Several requirements are not yet met.

### Design

**i18n audit:**
- Scan all PHP files for hardcoded English strings missing `__()` / `esc_html__()`
- Generate POT file: `wp i18n make-pot . languages/auto-alt-tags.pot`
- Add `composer i18n` command to `composer.json`

**`uninstall.php`:**
- Delete all `auto_alt_*` options from `wp_options`
- Delete `auto_alt_queue` option
- Clear the `auto_alt_tags_process_queue` WP Cron event
- Delete `_auto_alt_attempts` postmeta from all attachments
- Preserve `_wp_attachment_image_alt` — this is standard WP core data belonging to the user

**Plugin Check compliance:**
- Run `composer check` and resolve all remaining warnings
- Bump `Tested up to: 6.7` in plugin header
- Add `readme.txt` (WordPress.org format) alongside existing `README.md`

---

## Error Handling Summary

| Scenario | Handling |
|----------|----------|
| Queue grows > 500 IDs | Cap enforced, oldest-first, admin log warning |
| WP Cron not firing | Admin notice after 30 min stale queue |
| AI API failure per image | Retry up to 3×, then drop + log |
| CSV import bad row | Skip row, continue, report in summary |
| Media Library regenerate failure | Inline error next to image |

---

## Testing Plan

**Existing test suite** (`composer test`) covers API calls, provider handling, security, settings — no changes needed.

**New unit tests:**
- Queue push/pop logic (pure functions, no WP dependency)
- CSV parse and row validation logic
- Failure attempt counter logic (`_auto_alt_attempts`)

**Manual testing:**
- Upload image with auto-generate on → confirm alt text set after cron fires
- Upload image with auto-generate off → confirm no processing
- Media Library: column visible, filter works, "Regenerate" triggers correctly
- CSV: export → edit → import → confirm updates applied
- Uninstall: all `auto_alt_*` options removed, `_wp_attachment_image_alt` preserved

---

## Version

All three phases together = **v1.1.0** (minor version, no breaking changes to existing API or settings).
