#!/usr/bin/env bash
#
# ساخت سه فایل تحویلی راست‌چین برای پژکام.
#
#   dist/pezhkam-package.zip  →  فیلد «پکیج محصول»   (افزونه + راهنما + نکات قبل از نصب)
#   dist/pezhkam.zip          →  فیلد «فایل بروزرسان» (فقط پوشه افزونه)
#   dist/pezhkam-guide.pdf    →  فیلد «فایل راهنما»
#
# اسکریپت قبل از ساختن، همه چیزهایی را که قبلاً باعث ریجکت شده‌اند بررسی می‌کند
# و اگر هر کدام رد شود، هیچ فایلی ساخته نمی‌شود.
#
#   bash build.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

PLUGIN_DIR="pezhkam"
LICENSE_FILE="$PLUGIN_DIR/includes/RTL_License_0949e1086a8664d9.php"
LICENSE_SHA1="7a08b73e61f2c61ca287e1f88650085a47328a54"
GUIDE_PDF="marketing/guide/pezhkam-guide.pdf"
NOTES="marketing/guide/pre-install-notes.txt"

red()  { printf '\033[31m%s\033[0m\n' "$*"; }
grn()  { printf '\033[32m%s\033[0m\n' "$*"; }
fail() { red "  ✗ $*"; FAILED=1; }
ok()   { grn "  ✓ $*"; }
FAILED=0

echo "── بررسی‌های پیش از ساخت ──"

# 1) فایل لایسنس ionCube باید بایت‌به‌بایت دست‌نخورده باشد.
if [ ! -f "$LICENSE_FILE" ]; then
	fail "فایل لایسنس پیدا نشد: $LICENSE_FILE"
else
	actual="$(sha1sum "$LICENSE_FILE" | cut -d' ' -f1)"
	if [ "$actual" = "$LICENSE_SHA1" ]; then
		ok "sha1 فایل لایسنس درست است"
	else
		fail "sha1 فایل لایسنس عوض شده — افزونه بوت نمی‌شود. انتظار: $LICENSE_SHA1 / واقعی: $actual"
	fi
fi

# 2) همان هش باید در هر دو گیت داخل کد باشد.
gates="$(grep -rl "$LICENSE_SHA1" "$PLUGIN_DIR" --include='*.php' | wc -l)"
[ "$gates" -ge 2 ] && ok "هش در هر دو گیت موجود است ($gates فایل)" \
                   || fail "هش فقط در $gates فایل است — باید در setup.php و class-plugin.php باشد"

# 3) نسخه باید در همه‌جا یکی باشد.
hdr_ver="$(grep -m1 '^ \* Version:' "$PLUGIN_DIR/$PLUGIN_DIR.php" | awk '{print $3}')"
const_ver="$(grep -m1 "define('PZK_VERSION'" "$PLUGIN_DIR/$PLUGIN_DIR.php" | sed "s/.*'\([0-9.]*\)'.*/\1/")"
if [ "$hdr_ver" = "$const_ver" ]; then
	ok "نسخه یکدست است: $hdr_ver"
else
	fail "نسخه در هدر ($hdr_ver) با PZK_VERSION ($const_ver) فرق دارد"
fi

# 4) هیچ ایموجی‌ای نباید باقی مانده باشد.
emoji_hits="$(python3 - <<'PY'
import os, re, unicodedata
pat = re.compile('&#1[0-9]{4,5};')
n = 0
for dp, _, fs in os.walk('pezhkam'):
    for f in fs:
        if f.endswith(('.png', '.pdf', '.woff2', '.mo')):
            continue
        try:
            t = open(os.path.join(dp, f), encoding='utf-8').read()
        except Exception:
            continue
        if pat.search(t):
            n += 1
            continue
        if any(ord(c) > 0x2100 and unicodedata.category(c) == 'So' for c in t):
            n += 1
print(n)
PY
)"
[ "$emoji_hits" = "0" ] && ok "بدون ایموجی" || fail "$emoji_hits فایل هنوز ایموجی دارد"

# 5) هیچ ردی از برندهای قبلی.
if grep -rqiE "signteb|pazira|clinovix|medora" "$PLUGIN_DIR" --include='*.php' --include='*.js' --include='*.css' --include='*.md' 2>/dev/null; then
	fail "ارجاع به برند قبلی در افزونه باقی مانده"
else
	ok "بدون ارجاع به برند قبلی"
fi

# 6) فونت واقعاً همراه بسته باشد.
[ -f "$PLUGIN_DIR/assets/fonts/vazirmatn-variable.woff2" ] \
	&& ok "فونت وزیرمتن همراه بسته است" || fail "فایل فونت وجود ندارد"
[ -f "$PLUGIN_DIR/assets/fonts/OFL.txt" ] \
	&& ok "مجوز OFL فونت موجود است" || fail "OFL.txt فونت جا افتاده (شرط لایسنس فونت)"

# 7) سلامت نحوی PHP.
if command -v php >/dev/null 2>&1; then
	syntax_bad=0
	while IFS= read -r f; do php -l "$f" >/dev/null 2>&1 || { red "     $f"; syntax_bad=1; }; done \
		< <(find "$PLUGIN_DIR" -name '*.php')
	[ "$syntax_bad" -eq 0 ] && ok "همه فایل‌های PHP سالم‌اند" || fail "خطای نحوی PHP"
else
	echo "  … php نصب نیست، از بررسی نحوی صرف‌نظر شد"
fi

# 8) فایل‌های تحویلی جانبی.
[ -f "$GUIDE_PDF" ] && ok "راهنمای PDF موجود است" || fail "پیدا نشد: $GUIDE_PDF"
[ -f "$NOTES" ]     && ok "نکات قبل از نصب موجود است" || fail "پیدا نشد: $NOTES"

if [ "$FAILED" -ne 0 ]; then
	echo
	red "ساخت متوقف شد. اول موارد بالا را درست کنید."
	exit 1
fi

echo
echo "── ساخت ──"
# فقط خروجی خودِ این اسکریپت پاک می‌شود، نه کل dist —
# وگرنه بیلد آزمایشی build-dev.sh هم هر بار از بین می‌رفت.
mkdir -p dist
rm -f dist/pezhkam.zip dist/pezhkam-package.zip dist/pezhkam-guide.pdf

# فایل بروزرسان: فقط پوشه افزونه، بدون هیچ پوشه والد اضافه.
zip -rq dist/pezhkam.zip "$PLUGIN_DIR" -x '*.DS_Store' -x '__MACOSX/*' -x '*/.git/*'
ok "dist/pezhkam.zip"

# پکیج محصول: افزونه + راهنما + نکات، همه در ریشه زیپ.
tmp="$(mktemp -d)"
cp -r "$PLUGIN_DIR" "$tmp/"
cp "$GUIDE_PDF" "$NOTES" "$tmp/"
( cd "$tmp" && zip -rq "$ROOT/dist/pezhkam-package.zip" . -x '*.DS_Store' -x '__MACOSX/*' )
rm -rf "$tmp"
ok "dist/pezhkam-package.zip"

cp "$GUIDE_PDF" dist/
ok "dist/pezhkam-guide.pdf"

echo
echo "── بررسی بعد از ساخت ──"

# داخل هر زیپ، فایل لایسنس باید هنوز همان هش را داشته باشد.
for z in dist/pezhkam.zip dist/pezhkam-package.zip; do
	tmp="$(mktemp -d)"
	unzip -qq "$z" -d "$tmp"
	inner="$(sha1sum "$tmp/$PLUGIN_DIR/includes/$(basename "$LICENSE_FILE")" | cut -d' ' -f1)"
	if [ "$inner" = "$LICENSE_SHA1" ]; then
		ok "$(basename "$z") — لایسنس داخل زیپ سالم"
	else
		fail "$(basename "$z") — لایسنس داخل زیپ خراب است"
	fi
	rm -rf "$tmp"
done

# زیپ نباید فایل اضافی داشته باشد.
if unzip -Z1 dist/pezhkam.zip | grep -qiE '\.DS_Store|__MACOSX|/\.git'; then
	fail "زیپ فایل اضافی دارد"
else
	ok "زیپ‌ها بدون فایل اضافه‌اند"
fi

echo
ls -la dist/
echo
[ "$FAILED" -eq 0 ] && grn "آماده ارسال." || { red "بعد از ساخت، بررسی رد شد."; exit 1; }
