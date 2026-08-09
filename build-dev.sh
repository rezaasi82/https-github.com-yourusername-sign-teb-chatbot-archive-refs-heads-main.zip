#!/usr/bin/env bash
#
# ساخت «بیلد آزمایشی» پژکام — بدون گیت لایسنس راست‌چین.
#
#   خروجی: dist/pezhkam-DEV-NO-LICENSE.zip
#
# این بیلد فقط برای تست روی سایت خودتان است. هیچ‌وقت آن را به راست‌چین نفرستید:
# لایسنس داخلش نیست و هر کسی می‌تواند بدون خرید نصبش کند.
#
# پوشهٔ `pezhkam/` اصلاً دست نمی‌خورد — همهٔ تغییرها روی یک کپی موقت اعمال
# می‌شود و اسکریپت در پایان بررسی می‌کند که سورس اصلی هنوز دست‌نخورده است.
#
#   bash build-dev.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

PLUGIN_DIR="pezhkam"
LICENSE_SHA1="7a08b73e61f2c61ca287e1f88650085a47328a54"
LICENSE_FILE="$PLUGIN_DIR/includes/RTL_License_0949e1086a8664d9.php"
OUT="dist/pezhkam-DEV-NO-LICENSE.zip"

red() { printf '\033[31m%s\033[0m\n' "$*"; }
grn() { printf '\033[32m%s\033[0m\n' "$*"; }
ylw() { printf '\033[33m%s\033[0m\n' "$*"; }

before="$(sha1sum "$LICENSE_FILE" | cut -d' ' -f1)"
if [ "$before" != "$LICENSE_SHA1" ]; then
	red "  ✗ سورس اصلی از قبل خراب است — sha1 لایسنس نمی‌خواند. اول آن را درست کنید."
	exit 1
fi
grn "  ✓ سورس اصلی سالم است؛ روی کپی کار می‌کنیم"

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
cp -r "$PLUGIN_DIR" "$tmp/"
dev="$tmp/$PLUGIN_DIR"

# ۱) فایل ionCube راست‌چین اصلاً وارد بیلد آزمایشی نمی‌شود.
rm -f "$dev/includes/RTL_License_0949e1086a8664d9.php"
grn "  ✓ فایل لایسنس از کپی حذف شد"

# ۲) گیت بوت: مستقیم استارت می‌زند، به‌جای چک sha1 → isActive().
cat > "$dev/includes/setup.php" <<'PHP'
<?php
/**
 * ⚠ بیلد آزمایشی — گیت فعال‌سازی مارکت‌پلیس در این نسخه وجود ندارد.
 *
 * نسخهٔ اصلی این فایل، فایل ionCube راست‌چین را با تطبیق sha1 بارگذاری می‌کند و
 * فقط وقتی isActive() درست باشد افزونه را بوت می‌کند. اینجا برای تست محلی حذف
 * شده است. این فایل را هرگز به‌جای نسخهٔ اصلی در مخزن ننشانید.
 *
 * @package Pezhkam
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', static function (): void {
    \Pezhkam\Core\Plugin::start();
});

// یادآوری همیشگی در پنل، تا این بیلد با نسخهٔ فروش اشتباه گرفته نشود.
add_action('admin_notices', static function (): void {
    if (! current_user_can('manage_options')) {
        return;
    }
    echo '<div class="notice notice-warning"><p><strong>'
        . esc_html__('پژکام — بیلد آزمایشی بدون لایسنس', 'pezhkam')
        . '</strong> '
        . esc_html__('این نسخه فقط برای تست روی سایت خودتان است و گیت فعال‌سازی ندارد. نسخهٔ فروش را با build.sh بسازید.', 'pezhkam')
        . '</p></div>';
});
PHP
grn "  ✓ setup.php آزمایشی نوشته شد"

# ۳) لایهٔ دوم (Plugin::licensed) هم باید در این بیلد کوتاه شود، وگرنه
#    start() و boot() هر دو زود برمی‌گردند و افزونه بالا نمی‌آید.
python3 - "$dev/includes/core/class-plugin.php" <<'PY'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
old = """    private static function licensed(): bool
    {
        $class = self::LICENSE_CLASS;"""
new = """    private static function licensed(): bool
    {
        // بیلد آزمایشی: گیت لایسنس غیرفعال است. در نسخهٔ فروش این خط وجود ندارد.
        return true;

        $class = self::LICENSE_CLASS;"""
assert old in s, 'licensed() signature changed — dev patch needs updating'
open(p, 'w', encoding='utf-8').write(s.replace(old, new, 1))
PY
grn "  ✓ Plugin::licensed() در کپی کوتاه شد"

# ۴) هدر افزونه طوری علامت بخورد که در فهرست افزونه‌ها قابل تشخیص باشد.
python3 - "$dev/pezhkam.php" <<'PY'
import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()
s = s.replace(' * Plugin Name:       Pezhkam',
              ' * Plugin Name:       Pezhkam (DEV — no licence)', 1)
s = s.replace(" * Version:           1.0.0", " * Version:           1.0.0-dev", 1)
s = s.replace("define('PZK_VERSION', '1.0.0');", "define('PZK_VERSION', '1.0.0-dev');", 1)
open(p, 'w', encoding='utf-8').write(s)
PY
grn "  ✓ هدر افزونه به‌عنوان DEV علامت خورد"

# ۵) بررسی نحوی روی همان چیزی که قرار است نصب شود.
bad=0
while IFS= read -r f; do php -l "$f" >/dev/null 2>&1 || { red "     $f"; bad=1; }; done \
	< <(find "$dev" -name '*.php')
[ "$bad" -eq 0 ] && grn "  ✓ PHP کپی سالم است" || { red "  ✗ خطای نحوی"; exit 1; }

mkdir -p dist
rm -f "$OUT"
( cd "$tmp" && zip -rq "$ROOT/$OUT" "$PLUGIN_DIR" -x '*.DS_Store' -x '__MACOSX/*' )

# ۶) اطمینان از اینکه فایل لایسنس واقعاً داخل زیپ نیست و سورس اصلی تکان نخورده.
if unzip -Z1 "$OUT" | grep -q 'RTL_License'; then
	red "  ✗ فایل لایسنس داخل زیپ آزمایشی مانده — متوقف شد"
	rm -f "$OUT"
	exit 1
fi
grn "  ✓ زیپ آزمایشی فایل لایسنس ندارد"

after="$(sha1sum "$LICENSE_FILE" | cut -d' ' -f1)"
if [ "$after" = "$LICENSE_SHA1" ]; then
	grn "  ✓ سورس اصلی دست‌نخورده ماند"
else
	red "  ✗ سورس اصلی تغییر کرد — این نباید اتفاق بیفتد"
	exit 1
fi

echo
ls -la "$OUT"
echo
ylw "این فایل فقط برای تست روی سایت خودتان است."
ylw "برای ارسال به راست‌چین از bash build.sh استفاده کنید، نه از این."
