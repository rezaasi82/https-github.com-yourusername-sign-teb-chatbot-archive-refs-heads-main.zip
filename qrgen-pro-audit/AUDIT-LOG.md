# AUDIT LOG — QRGen Pro (تولید کننده QR Code)

- **Package audited:** `qrgrenforwordpresspro.zip` (uploaded), plugin main file `qrgen-pro.php`
- **Current version:** 1.0.0
- **Target version:** 1.1.0
- **Files in package:** `qrgen-pro.php`, `templates/qr-generator.php`, `includes/assets/admin.php`, `includes/assets/shortcodes.php`, `includes/assets/css/qrgen-pro.css`, `includes/assets/js/qrgen-pro.js`

## How the plugin actually works (traced)

Everything the front-end user sees is produced by **one class in one file**, `QRGen_Pro_Plugin` in `qrgen-pro.php`. Its constructor registers two shortcode aliases (`[qrgen_pro]`, `[qrgen]`) that both call `get_qr_generator_html()`, which `ob_start()`s a giant literal block of HTML + an inline `<style>` + an inline `<script>`, and returns it. QR generation itself happens **entirely client-side** in the browser via the third-party `qrcode.js` library (loaded from `cdnjs.cloudflare.com`) — there is no AJAX handler, no REST endpoint, no database table, and no server-side image processing anywhere in this plugin.

Because of that, most of the standard WordPress-plugin attack surface (nonce/AJAX/capability checks, `$wpdb->prepare`, `file_put_contents`, `mail()`, `exec()`) **does not apply** — there is none of it. The real bugs are in the client-side JS logic and in the fact that four of the six shipped files are dead code.

**`templates/qr-generator.php`, `includes/assets/admin.php`, `includes/assets/shortcodes.php`, `includes/assets/js/qrgen-pro.js`, `includes/assets/css/qrgen-pro.css` are never `require`d/`include`d by `qrgen-pro.php`.** There is no `require`, `include`, or `QRGEN_PLUGIN_PATH` constant anywhere in the main file. This was confirmed with a full-file grep — zero matches. They represent an abandoned, more ambitious "v2" rebuild (jQuery-based, different element IDs, extra shape/pattern options) that has never actually shipped to a user.

## Findings

> Finding #1 was found only after Phase 2 began, while writing a browser-based regression test for the other fixes — `node --check` on the extracted `<script>` block revealed it, and re-running it against the **original, untouched** upload confirmed it was already there in the shipped v1.0.0. It is promoted to the top of this table because it explains/subsumes several of the others: if the script never parses, none of the JS-dependent findings below (#2 getElementById, tab switching, color pickers, generate button, everything) can ever run in the first place.

| # | Severity | File | Issue | Fix plan |
|---|----------|------|-------|----------|
| 1 | **Critical** | `qrgen-pro.php` (`getContent()`) | Inside the `switch(currentDataType)` block, `const phone` is declared in **both** `case 'sms'` and `case 'vcard'` (switch cases share one block scope unless individually braced). This is a fatal `SyntaxError: Identifier 'phone' has already been declared` — confirmed with `node --check` against the original file, not just the working copy. A `<script>` tag that fails to parse never executes **any** of its code: no tab switching, no data-type switching, no color pickers, no generate button, nothing. This is very likely the exact real-world complaint that triggered this audit ("the plugin does nothing"). | Rename the second declaration (`vcard` case) to `vcardPhoneValue` to remove the collision. |
| 2 | **Critical** | `qrgen-pro.php` (tab-switch script) | `container.getElementById(...)` is called on a `<div>` element. `getElementById` only exists on `Document`/`DocumentFragment`, not on a generic `Element` — this throws `TypeError: container.getElementById is not a function`. It fires *after* the code has already removed the `active` class from every tab panel, so clicking the "طراحی" (Design) tab hides **both** panels and shows neither. The logo-upload and shape controls become permanently unreachable via the UI. | Replace with a container-scoped lookup, e.g. `container.querySelector('.qrgen-pro-tab-content[data-tab-content="design"]')`. |
| 3 | **High** | `qrgen-pro.php` (logo upload / `generateQRCode`) | The "افزودن لوگو" (add logo) control reads the file, decodes it into `logoImage` — and that variable is **never used again**. `generateQRCode()` never draws it onto the QR canvas. The advertised logo-in-QR feature is a non-functional UI shell: users pick a logo, see a preview, and it never appears in the generated or downloaded QR code. | Draw `logoImage` centered on the generated `<canvas>` (with a white padding square behind it so the QR stays scannable at error-correction level H, which is already set). |
| 4 | **High** | `qrgen-pro.php` (`showResult` / test button) | The "test" button builds `` `محتوای QR Code: ${currentContent}` `` and assigns it to `resultDiv.innerHTML` unescaped. `currentContent` is whatever the visitor typed into the content fields (URL/text/SMS/etc.), so a value like `<img src=x onerror=alert(1)>` executes as HTML/JS in the visitor's own page — a DOM-based self-XSS via `innerHTML`. | Escape the interpolated value (HTML-entity encode) before inserting into `innerHTML`, instead of raw template-literal interpolation. |
| 5 | **Medium** | `qrgen-pro.php` (whole template) | The wrapper `id="qrgen-pro-container"` and `id="qrcode"` are hard-coded, and the click handlers look them up with unscoped `document.getElementById(...)`. If the shortcode is placed more than once on the same page (perfectly valid WP usage), **every instance's script operates on the very first instance in the DOM** — the 2nd/3rd instance's buttons silently do nothing. The whole `<style>`/`<script>` block is also duplicated verbatim per instance, bloating the page. | Generate a unique per-render instance id (`wp_unique_id('qrgen-pro-')`), scope all lookups through it, and print the shared `<style>` block only once per request. |
| 6 | **Medium** | `includes/assets/admin.php` never loaded; `qrgen-pro.php` ignores its own defaults | The Settings API page (`options-general.php?page=qrgen-settings`) that lets an admin set default size/colors is registered but **never `require`d**, so the menu item never appears. Even if it were loaded, the shortcode's HTML hard-codes `300` / `#000000` / `#ffffff` and never reads the stored options — so the settings panel would have zero effect either way. This violates the product requirement that all user-configurable defaults (size, colors) be editable from an admin panel. | `require` `includes/assets/admin.php` from the bootstrap, and have `get_qr_generator_html()` read the 3 options via `get_option()` (escaped) to seed the default size `<select>` and the two color inputs. Also hardened the size field itself: it used to be a freeform `<input type="number" min="100" max="1000">` that could store a value with no matching `<option>` on the front-end select — changed to a `<select>` with the same 4 values, and added a `sanitize_callback` (`absint` + allow-list, `sanitize_hex_color`) to `register_setting()`. |
| 7 | **Medium** | `qrgen-pro.php` `enqueue_scripts()` | `wp_enqueue_script('qrcode-js', 'https://cdnjs.cloudflare.com/...')` runs unconditionally on **every** front-end page load (hooked to `wp_enqueue_scripts` with no shortcode check), not only on pages that actually use `[qrgen_pro]`/`[qrgen]`. Unnecessary external request + a small perf hit site-wide. | Skip enqueuing when the current view is a single post/page (`is_singular()`) whose own content provably doesn't contain either shortcode alias (`has_shortcode()`). Deliberately conservative: archives, the home page, admin-widget areas, and FSE template parts still always enqueue, so a shortcode placed outside `post_content` (widget, template) keeps working exactly as before — only the common "shortcode absent from this singular page" case is skipped. |
| 8 | **Low** | `qrgen-pro.php` header | Missing `Requires PHP: 8.1` and `Requires at least: 6.4` header fields required by the SignTeb packaging standard. | Add both header lines. |
| 9 | **Low** | `qrgen-pro.php` | Declares `Text Domain: qrgen-pro` but never calls `load_plugin_textdomain()`, and there is no `languages/` folder. All strings are hard-coded literal Persian (not wrapped in `__()`), so full i18n is a larger follow-up, but the missing loader call is a one-line gap worth closing now. | Add `load_plugin_textdomain('qrgen-pro', ...)` on `init`. Full string-wrapping left as a documented follow-up (out of surgical scope — this plugin's target market is Persian-only, so it wasn't flagged as a functional bug). |
| 10 | **Low** | Whole plugin | No `register_activation_hook`/`register_deactivation_hook`/`uninstall.php`. Previously there were zero real options in play, so this was harmless; once fix #6 wires in real `wp_options` entries, they need a matching cleanup path to avoid orphaned rows on uninstall. | Add `register_activation_hook` (sets option defaults + `qrgen_version` once) and an `uninstall.php` that deletes all 4 `qrgen_*` options. |
| 11 | **Low** | `templates/qr-generator.php`, `includes/assets/shortcodes.php`, `includes/assets/js/qrgen-pro.js`, `includes/assets/css/qrgen-pro.css` | Confirmed 100% unreachable dead code (see trace above): an abandoned rebuild that (a) uses jQuery, violating the vanilla-JS standard if it were ever wired in by mistake, (b) registers its own competing `add_shortcode('qrgen', 'qrgen_shortcode')` that would silently clobber the real one if anyone ever adds the missing `require`, and (c) bloats the shipped ZIP with ~1,500 lines that do nothing. | Remove these 4 files from the package. Nothing user-facing depends on them — behavior for existing users is unchanged. |
| 12 | **Info (not fixed)** | `qrgen-pro.php` shortcode callback | `qrgen_pro_shortcode($atts)` ignores `$atts` completely — `[qrgen_pro size="400"]` has no effect, all customization is only possible through the front-end UI dropdowns. Not a regression (never worked), and wiring per-instance shortcode attributes into the inline-HTML renderer is a larger change than this pass's scope. Documented as a known limitation for a future minor release. | — |

## Verification performed

Since there's no live WordPress install in this environment, verification was done two ways:

1. **PHP-level**: a stub harness (fake `add_option`/`get_option`/`add_shortcode`/etc.) actually `require`s the fixed `qrgen-pro.php`, runs activation, renders the shortcode twice, and asserts: no PHP notices/warnings/fatals (`error_reporting(E_ALL)`), options round-trip correctly, the `<style>` block prints exactly once across two renders, and the two instances get distinct ids. `php -l` was also run on all 3 shipped PHP files.
2. **Browser-level** (Playwright + the pre-installed headless Chromium): rendered the actual shortcode output (with a minimal stand-in for the external `qrcode.js` library, since this sandbox cannot reach `cdnjs.cloudflare.com`) twice on one page and drove it like a real user:
   - Clicked the "Design" tab → confirmed the design panel becomes active and the basic panel does not (finding #2, fixed).
   - Typed `<img src=x onerror=alert(1)>` into the text field, generated, clicked "Test" → no JS dialog fired, `innerHTML` contains no live `<img>` tag, and the text renders as literal escaped text (finding #4, fixed).
   - Uploaded a real 20×20 red PNG as the logo, generated the QR code, and read back the canvas's center pixel via `getImageData` — it changed from white (empty background) to red, proving the logo is now actually drawn onto the output (finding #3, fixed).
   - Generated in both on-page instances independently and confirmed both got their own canvas and their own visible download button (finding #5, fixed).
   - No `pageerror`/console-error events were captured in any of the above.
   - Additionally: typed Persian/Unicode content (`شماره تماس: ۰۹۱۲۳۴۵۶۷۸۹ - متن فارسی با یونیکد`) into the URL field, generated, switched format to JPG, and clicked download — a `download` event fired with a valid filename and zero console/page errors.

## Final QA checklist (Phase 3.4)

| Check | Result |
|---|---|
| Plugin activates with zero PHP notices/warnings (`WP_DEBUG`-equivalent: `error_reporting(E_ALL)`) | ✅ — verified via a stub harness that actually executes the real plugin file against fake WP core functions (no live WP 6.7/PHP 8.2 install available in this sandbox; PHP 8.4 CLI + `php -l` used instead, which is a strictly newer/stricter runtime) |
| Deactivate → reactivate → settings preserved | ✅ — `qrgen_pro_activate()` uses `add_option()`, which WordPress core no-ops if the option already exists, so a customized default is never reset on reactivation |
| QR generates correctly with Persian/UTF-8 content (فارسی + URL + شماره تلفن) | ✅ — verified in headless Chromium; the plugin never transforms/encodes the string itself, so this was really a test that nothing in the fixed code mangles Unicode, which it doesn't |
| Download/export works (PNG/JPG) | ✅ — verified in headless Chromium, including the JPG code path (canvas re-composite with white background) |
| No JS console errors on front-end | ✅ — zero `pageerror`/console-error events across all Playwright runs (tab switch, data-type switch, generate, logo upload, test, download) |
| No CSS bleed into theme | ✅ — all selectors remain scoped to pre-existing `.qrgen-pro-*` classes; nothing renamed or made global |
| No JS console errors on admin settings screen | ⚠️ Not verified in a real browser — no WP admin chrome available in this sandbox. The settings page itself is plain Settings-API markup (`add_options_page` + `settings_fields`/`do_settings_sections`/`submit_button`), which is standard, cannot introduce console errors, and was PHP-lint clean. |

## پاراگراف فارسی — خلاصه تغییرات برای مشتریان

نسخه ۱.۱.۰ از QRGen Pro یک به‌روزرسانی حیاتی است: در بررسی این نسخه مشخص شد که به‌دلیل یک خطای فنی در کد جاوااسکریپت، ابزار تولید QR Code در نسخه قبلی (۱.۰.۰) در هیچ مرورگری به‌درستی اجرا نمی‌شده و عملاً هیچ‌یک از قابلیت‌ها — از جمله تعویض نوع محتوا، تب طراحی، انتخاب رنگ و دکمه تولید — کار نمی‌کرده‌اند. این مشکل به همراه چند باگ دیگر (از جمله عدم نمایش لوگو روی QR Code تولیدشده، و خرابی ابزار در صورت استفاده بیش از یک بار در یک صفحه) به‌طور کامل رفع شده است. همچنین صفحه تنظیمات که امکان تعیین اندازه و رنگ پیش‌فرض را می‌دهد، فعال شده و یک نقطه‌ضعف امنیتی جزئی نیز برطرف شده است. به‌روزرسانی به این نسخه به‌شدت توصیه می‌شود و هیچ تنظیم یا داده‌ای از کاربران فعلی از بین نمی‌رود.

## PHP 8.1/8.2 compatibility check

No dynamic property assignment (`$this->x = ...`) anywhere in the class, no `utf8_encode`, no removed/deprecated functions used. The file is otherwise PHP 8.1/8.2-clean.

## Security checklist (per Phase 1.3)

- Direct file access guard (`if (!defined('ABSPATH')) exit;`) — present in all 6 files. ✅
- Output escaping — main file is 100% static markup (no PHP variable interpolation) except where fix #5 introduces `get_option()` reads, which are `esc_attr()`-wrapped. ✅ (after fix)
- SQL / `$wpdb` — not used anywhere. N/A.
- `file_put_contents` / `include` with user input — not used anywhere. N/A.
- Nonce / AJAX — no AJAX endpoints exist in this plugin at all. N/A.
- Client-side XSS — finding #3 above (fixed).

## Iranian hosting constraints (Phase 1.4)

- No `mail()`. ✅
- No `exec()`/`shell_exec()`/`proc_open()`. ✅
- All work is either instant PHP string output or client-side JS; nothing approaches the 30s execution limit. ✅
- No bundled fonts to verify. N/A.
- The one external dependency (`cdnjs.cloudflare.com/.../qrcode.min.js`) is a pre-existing, already-present external call; the hard rules permit keeping already-present external calls. Recommend (not applied — see below) eventually self-hosting this file for reliability against CDN filtering, but adding a `integrity`/SRI hash without being able to verify the exact deployed bytes would risk **breaking QR generation entirely** if the hash were wrong, so this pass only fixes the *unconditional* loading (finding #6) and leaves the CDN URL itself untouched.
