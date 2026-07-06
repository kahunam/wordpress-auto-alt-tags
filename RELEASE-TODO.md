# wordpress-auto-alt-tags — Release Readiness TODO

> ## ✅ Status update — 2026-07-06
> - **[x] P1 (MEDIUM) — paid-API rate limiting:** DONE — extracted `check_rate_limit($cost)` helper; applied to all 3 paid endpoints (`ajax_process_alt_tags`, `ajax_regenerate_single`, `ajax_test_first_five`) — cost 1/1/5 from a shared budget so it can't be bypassed by spreading calls.
> - **[x] P3 — `console.log` removed** (`assets/js/admin.js`); **[x] `date()` → `gmdate()`** (WPCS).
> - **⬜ Remaining (follow-up):** `ajax_regenerate_single` still gated at `upload_files` (Contributor-level) — consider raising to `manage_options` to match the other paid endpoints. Rate limit now caps the blast radius. UI convention.

**Audit date:** 2026-07-06
**Overall verdict:** 🟢 Minor fixes needed — well-built and security-conscious. Every state-changing endpoint has nonce + capability checks, all SQL is parameterized, outbound API endpoints are hardcoded (no SSRF). Gaps: a cost-abuse vector on two endpoints, a hand-rolled admin UI, and cosmetic debug leftovers.
**Current version:** 1.1.0

---

## 🔴 Security

Verified clean: all AJAX handlers call `check_ajax_referer('auto_alt_nonce', ...)` + `current_user_can()`; all `$wpdb` queries use `->prepare()` (`:411`, `:785`, `:1759`, `:2040`, `:2101`, uninstall `:38`); IDs cast via `(int)`/`intval`/`absint`; local image path is realpath-confined to uploads dir before read (`:2143-2150`, `:2747-2754`); provider API URLs hardcoded + Gemini model allowlisted (`:2240`) — no SSRF; direct-access guards present; CSV import validates extension + `wp_attachment_is_image`. No `unserialize`/`eval`/`shell_exec`.

- [ ] **MEDIUM — Cost/resource abuse on paid-API endpoints.** `ajax_regenerate_single` (`auto-alt-tags.php:686-709`) is gated at `upload_files` (Author/Contributor-level) **and has no rate limiting** — the 30/hr limiter at `:1843` only guards `ajax_process_alt_tags`. A low-privileged user can repeatedly hit "Regenerate" in the Media Library to burn API credits. `ajax_test_first_five` (`:2597`) also fires 5 uncapped API calls. *Action:* apply the per-user rate limiter to both handlers and/or raise the capability to `manage_options`.
- [ ] **LOW — API key in URL query string (Gemini).** `?key=<API_KEY>` at `:1499`, `:2244`, and CLI `class-wp-cli-command.php:539` can leak into server/proxy logs. Google's documented pattern, so acceptable — prefer header auth where supported.
- [ ] **LOW (informational) — API keys rendered into settings DOM.** `:1155` — properly `esc_attr`-escaped, only visible to `manage_options` on the settings screen, never exposed to frontend. Standard WP; no action required.

## 🎨 UI — WordPress Components

- [ ] **Entirely hand-rolled (convention gap, not a bug).** Raw HTML tables, extensive inline `style="..."`, custom tab system, custom modal (`:1096`), jQuery AJAX (`assets/js/admin.js`, `media-column.js`). Uses WP core CSS classes but **no `@wordpress/components`**. Candidates if aligning: settings form → `Panel`/`ToggleControl`/`SelectControl`/`TextControl`; test-results modal → `Modal`; tab nav → `TabPanel`; progress bars → `ProgressBar`/`Notice`.

## 📦 Dependencies

Clean / release-ready. `composer.json` runtime require is only `php >=7.4`; everything else dev-only. No vendored runtime libs, no CDN, no npm bundle. Scripts enqueued via `wp_enqueue_script`/`wp_localize_script` with version + `jquery` dep (`:496-534`); no inline `<script>` blobs. **Nothing to do.**

## 🟡 General readiness

- Version consistent at 1.1.0 (header `:6`, constant `:27`, readme). ✅
- i18n solid: single `auto-alt-tags` text domain, `load_plugin_textdomain` (`:370`), `.pot` present. ✅
- Uninstall thorough (`uninstall.php`): deletes options, transients, `_auto_alt_attempts` postmeta, cron event; preserves core `_wp_attachment_image_alt`. Deactivation unschedules cron + clears transients. ✅
- [ ] **Remove debug leftover:** `console.log('[Auto Alt Tags]', ...)` in `assets/js/admin.js:27`.
- [ ] **WPCS polish:** `date()` → `gmdate()` at `:2799`; `error_log()` at `:2795` (gated behind debug_mode — acceptable, but WPCS-flagged) for a clean WordPress.org review.

---

## Top 3 before release
1. Add rate limiting (and reconsider the `upload_files` capability) on `ajax_regenerate_single` + `ajax_test_first_five`. *(MEDIUM)*
2. Remove the `console.log` (`assets/js/admin.js:27`) and switch `date()` → `gmdate()` (`:2799`). *(polish)*
3. *(Optional, convention)* Migrate custom modal/tabs/settings form toward `@wordpress/components`.
