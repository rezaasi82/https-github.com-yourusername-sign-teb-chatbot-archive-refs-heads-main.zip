#!/usr/bin/env bash
# Checks every WordPress plugin in the repo against the two standing rules:
# RTL/Persian-first UI, and "رضا آسیابی" credited as the author.
#
# Usage: bash .claude/skills/rtl-plugin-standards/scripts/check_rtl_credits.sh [repo-root]
# Exit code 0 = all checks passed, 1 = at least one FAIL.

set -uo pipefail

ROOT="${1:-$(git rev-parse --show-toplevel 2>/dev/null || pwd)}"
cd "$ROOT" || exit 1

failures=0

pass() { printf '  \033[0;32mPASS\033[0m  %s\n' "$1"; }
fail() { printf '  \033[0;31mFAIL\033[0m  %s\n' "$1"; failures=$((failures + 1)); }
warn() { printf '  \033[0;33mWARN\033[0m  %s\n' "$1"; }

# A plugin main file is a .php file whose header block declares "Plugin Name:".
mapfile -t main_files < <(
  find . -maxdepth 2 -name '*.php' \
    -not -path './.git/*' -not -path './*/vendor/*' -not -path './*/node_modules/*' \
    -print0 2>/dev/null | xargs -0 grep -l -m1 'Plugin Name:' 2>/dev/null | sort
)

if [ "${#main_files[@]}" -eq 0 ]; then
  echo "No WordPress plugin main files found under $ROOT"
  exit 0
fi

for main in "${main_files[@]}"; do
  dir="$(dirname "$main")"
  printf '\n\033[1m%s\033[0m (%s)\n' "$(basename "$dir")" "$main"

  # --- Rule 1: author credit ---------------------------------------------
  author_line="$(grep -m1 '^\s*\*\s*Author:' "$main" || true)"
  if [ -z "$author_line" ]; then
    fail "no 'Author:' line in the plugin header"
  elif printf '%s' "$author_line" | grep -q 'رضا آسیابی'; then
    pass "author credit: رضا آسیابی"
  else
    fail "author is not رضا آسیابی →${author_line#*Author:}"
  fi

  # composer.json / package.json authorship, when those files exist.
  for meta in "$dir/composer.json" "$dir/package.json"; do
    [ -f "$meta" ] || continue
    if grep -qi 'Reza Asiabi\|رضا آسیابی' "$meta"; then
      pass "author credit in $(basename "$meta")"
    else
      fail "$(basename "$meta") does not credit Reza Asiabi"
    fi
  done

  # readme.txt is what the marketplace shows, so it matters when present.
  if [ -f "$dir/readme.txt" ]; then
    if grep -qi 'rezaasiabi\|Reza Asiabi\|رضا آسیابی' "$dir/readme.txt"; then
      pass "author credit in readme.txt"
    else
      fail "readme.txt does not credit Reza Asiabi"
    fi
  fi

  # --- Rule 2: RTL-first styling -----------------------------------------
  mapfile -t css_files < <(find "$dir" -name '*.css' -not -path '*/vendor/*' -not -path '*/node_modules/*' 2>/dev/null | sort)

  # RTL can legitimately come from CSS (direction: rtl) or from a dir="rtl"
  # attribute on the plugin's root element — accept either mechanism.
  rtl_via_css=0
  rtl_via_attr=0
  [ "${#css_files[@]}" -gt 0 ] && grep -rqE 'direction:\s*rtl' "${css_files[@]}" && rtl_via_css=1
  grep -rqE "dir=[\"']rtl|dir=[\"']?\\\$|['\"]direction['\"]\s*=>|is_rtl\(" "$dir" --include='*.php' 2>/dev/null && rtl_via_attr=1

  if [ "$rtl_via_css" -eq 1 ] && [ "$rtl_via_attr" -eq 1 ]; then
    pass "RTL declared in CSS and via dir attribute"
  elif [ "$rtl_via_css" -eq 1 ]; then
    pass "RTL direction declared in CSS"
  elif [ "$rtl_via_attr" -eq 1 ]; then
    pass "RTL applied via dir attribute / is_rtl()"
  elif [ "${#css_files[@]}" -eq 0 ]; then
    warn "no CSS and no dir/is_rtl usage — fine only for a UI-less plugin"
  else
    fail "no RTL direction anywhere: neither 'direction: rtl' in CSS nor dir=\"rtl\"/is_rtl() in PHP"
  fi

  if [ "${#css_files[@]}" -gt 0 ]; then

    # Global direction/text-align resets leak into the host theme.
    if global_hit="$(grep -rnE '^\s*(body|html|\*)\s*(,[^{]*)?\{[^}]*(direction|text-align)' "${css_files[@]}" | head -3)"; [ -n "$global_hit" ]; then
      fail "direction/text-align applied globally instead of scoped to the plugin wrapper:"
      printf '        %s\n' "$global_hit"
    else
      pass "direction is scoped, not global"
    fi

    # Remote fonts are unreliable from Iran; fonts must be self-hosted.
    if cdn_hit="$(grep -rniE 'fonts\.googleapis|fonts\.gstatic|cdn\.jsdelivr[^)]*font|use\.fontawesome' "${css_files[@]}" | head -3)"; [ -n "$cdn_hit" ]; then
      fail "font loaded from a CDN — bundle it instead:"
      printf '        %s\n' "$cdn_hit"
    else
      pass "no CDN font references"
    fi
  fi

  # PHP-side remote font enqueues bypass the CSS check above.
  if php_cdn="$(grep -rniE 'wp_enqueue_style\([^)]*fonts\.(googleapis|gstatic)' "$dir" --include='*.php' | head -3)"; [ -n "$php_cdn" ]; then
    fail "remote font enqueued from PHP:"
    printf '        %s\n' "$php_cdn"
  fi
done

printf '\n'
if [ "$failures" -eq 0 ]; then
  printf '\033[0;32mAll checks passed.\033[0m\n'
  exit 0
fi
printf '\033[0;31m%d check(s) failed.\033[0m\n' "$failures"
exit 1
