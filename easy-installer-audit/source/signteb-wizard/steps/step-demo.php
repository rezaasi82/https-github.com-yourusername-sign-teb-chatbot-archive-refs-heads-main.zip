<?php
/**
 * SignTeb Setup Wizard — مرحله‌ی انتخاب دمو (Marketplace)
 *
 * مرورگرِ دموها به‌سبک بازارِ قالب: جست‌وجوی زنده، فیلترِ دسته/نوع‌چیدمان/رنگ،
 * بج‌های Premium/New، و کارت‌های غنی با فهرست ویژگی‌ها و برچسبِ چیدمان.
 * همه‌ی فیلترها سمت‌کلاینت‌اند (بدون رفت‌وبرگشت به سرور) و روی متادیتای هر دمو
 * کار می‌کنند که از تعریفِ خودِ دمو استخراج می‌شود (مقیاس‌پذیر: دموی جدید =
 * پوشه‌ی جدید، بدون تغییر این فایل).
 *
 * @package SignTeb_Wizard
 */

defined( 'ABSPATH' ) || exit;

$installed = get_option( 'stwiz_demo_installed', '' );

$registry = new \SignTeb\Wizard\Setup\DemoRegistry();

/** برچسبِ فارسیِ هر نوع چیدمان (آرکی‌تایپ). */
$layout_labels = [
	'clinic'      => 'کلینیک استاندارد',
	'expertise'   => 'تخصص‌محور',
	'enterprise'  => 'بیمارستانی',
	'showcase'    => 'گالری و نمایش',
	'agency'      => 'آژانسی',
	'tech'        => 'اپلیکیشنی',
	'journey'     => 'مسیر بیمار',
	'educational' => 'آموزشی',
	'results'     => 'نتیجه‌محور',
];

/** متادیتای بازار برای هر دمو (دسته، تخصص، ویژگی‌ها، بج). */
$market = [
	'general-clinic'   => [ 'cat' => 'عمومی',    'layout' => 'clinic',    'badge' => '',        'features' => [ 'نوبت آنلاین', 'پروفایل پزشک', 'وبلاگ سلامت', 'سئو', 'راست‌چین' ] ],
	'dental-clinic'    => [ 'cat' => 'زیبایی',   'layout' => 'showcase',  'badge' => '',        'features' => [ 'گالری قبل/بعد', 'نوبت آنلاین', 'خدمات', 'سئو', 'راست‌چین' ] ],
	'cardiology'       => [ 'cat' => 'تخصصی',    'layout' => 'expertise', 'badge' => '',        'features' => [ 'تیم فوق‌تخصص', 'نوبت آنلاین', 'نظرات بیماران', 'سئو', 'راست‌چین' ] ],
	'fertility'        => [ 'cat' => 'تخصصی',    'layout' => 'journey',   'badge' => '',        'features' => [ 'مسیر درمان', 'نوبت آنلاین', 'داستان موفقیت', 'سئو', 'راست‌چین' ] ],
	'gastroenterology' => [ 'cat' => 'تخصصی',    'layout' => 'educational','badge' => '',        'features' => [ 'محتوای آموزشی', 'سؤالات شایع', 'نوبت آنلاین', 'سئو', 'راست‌چین' ] ],
	'multi-hospital'   => [ 'cat' => 'بیمارستان','layout' => 'enterprise','badge' => 'Premium', 'features' => [ 'بخش‌های متعدد', 'اورژانس ۲۴س', 'کادر درمان', 'سئو', 'راست‌چین' ] ],
	'orthopedic'       => [ 'cat' => 'تخصصی',    'layout' => 'results',   'badge' => '',        'features' => [ 'نتایج درمان', 'نوبت آنلاین', 'نظرات بیماران', 'سئو', 'راست‌چین' ] ],
	'plastic-surgery'  => [ 'cat' => 'زیبایی',   'layout' => 'showcase',  'badge' => 'Premium', 'features' => [ 'گالری قبل/بعد', 'نظرات بیماران', 'مشاوره', 'سئو', 'راست‌چین' ] ],
	'telemedicine'     => [ 'cat' => 'آنلاین',   'layout' => 'tech',      'badge' => 'New',     'features' => [ 'ویزیت آنلاین', 'امکانات اپ', 'چطور کار می‌کند', 'سئو', 'راست‌چین' ] ],
	'branding-agency'  => [ 'cat' => 'آژانس',    'layout' => 'agency',    'badge' => 'New',     'features' => [ 'نمونه‌کار', 'فرآیند کار', 'دارک‌مود', 'سئو', 'راست‌چین' ] ],
];

/** نامِ فارسیِ رنگ از رویِ hue رنگِ اصلیِ پالت (برای فیلترِ طرح‌رنگ). */
$color_name = static function ( string $hex ): string {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
		return 'آبی';
	}
	$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
	$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
	$b = hexdec( substr( $hex, 4, 2 ) ) / 255;
	$max = max( $r, $g, $b );
	$min = min( $r, $g, $b );
	$d = $max - $min;
	if ( $d < 0.08 ) {
		return $max < 0.25 ? 'مشکی' : 'خاکستری';
	}
	if ( $max === $r ) {
		$h = 60 * fmod( ( ( $g - $b ) / $d ), 6 );
	} elseif ( $max === $g ) {
		$h = 60 * ( ( $b - $r ) / $d + 2 );
	} else {
		$h = 60 * ( ( $r - $g ) / $d + 4 );
	}
	if ( $h < 0 ) {
		$h += 360;
	}
	return match ( true ) {
		$h < 20  => 'قرمز',
		$h < 45  => 'نارنجی',
		$h < 70  => 'زرد/طلایی',
		$h < 160 => 'سبز',
		$h < 200 => 'فیروزه‌ای',
		$h < 255 => 'آبی',
		$h < 290 => 'بنفش',
		$h < 335 => 'صورتی',
		default  => 'قرمز',
	};
};

// ── ساخت لیستِ غنی‌شده‌ی دموها + استخراجِ گزینه‌های فیلتر ──────────────────────
$demos       = [];
$cats        = [];
$layout_opts = [];
$color_opts  = [];

foreach ( $registry->all() as $id => $def ) {
	$m       = $market[ $id ] ?? [];
	$lay_key = $m['layout'] ?? ( $def['layout'] ?? 'clinic' );
	$primary = (string) ( $def['palette']['primary'] ?? ( $def['color'] ?? '#1a56db' ) );

	$row = [
		'id'       => $id,
		'icon'     => $def['icon'] ?? '🏥',
		'title'    => $def['title'] ?? $id,
		'desc'     => $def['description'] ?? '',
		'pages'    => $def['pages_count'] ?? '',
		'lang'     => $def['lang'] ?? 'فارسی',
		'primary'  => $primary,
		'accent'   => (string) ( $def['palette']['accent'] ?? '#C9A84C' ),
		'cat'      => $m['cat'] ?? 'عمومی',
		'layout'   => $lay_key,
		'lay_lbl'  => $layout_labels[ $lay_key ] ?? 'کلینیک استاندارد',
		'color'    => $color_name( $primary ),
		'badge'    => $m['badge'] ?? '',
		'features' => $m['features'] ?? [ 'نوبت آنلاین', 'سئو', 'راست‌چین' ],
	];
	$demos[ $id ]            = $row;
	$cats[ $row['cat'] ]     = true;
	$layout_opts[ $row['lay_lbl'] ] = true;
	$color_opts[ $row['color'] ]    = true;
}

$cats        = array_keys( $cats );
$layout_opts = array_keys( $layout_opts );
$color_opts  = array_keys( $color_opts );
sort( $cats );
sort( $layout_opts );
sort( $color_opts );
$total = count( $demos );
?>
<div class="stwiz-market" data-step="demo">
  <div class="stwiz-market__head">
    <h2><?php esc_html_e( 'کتابخانه‌ی دموها', STWIZ_TEXT ); ?></h2>
    <p class="stwiz-step-desc"><?php esc_html_e( 'یک دمو انتخاب کنید؛ صفحات، منوها، رنگ‌بندی و محتوای نمونه به‌صورت خودکار ساخته می‌شوند. هر دمو یک محصول مستقل با چیدمان و هویت بصری متفاوت است.', STWIZ_TEXT ); ?></p>
  </div>

  <!-- Toolbar -->
  <div class="stwiz-market__toolbar">
    <div class="stwiz-market__search">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="search" id="stwiz-market-search" placeholder="<?php esc_attr_e( 'جست‌وجو در دموها…', STWIZ_TEXT ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'جست‌وجوی دمو', STWIZ_TEXT ); ?>">
    </div>

    <div class="stwiz-market__filters">
      <select class="stwiz-market__select" id="stwiz-filter-cat" aria-label="<?php esc_attr_e( 'دسته', STWIZ_TEXT ); ?>">
        <option value=""><?php esc_html_e( 'همه‌ی دسته‌ها', STWIZ_TEXT ); ?></option>
        <?php foreach ( $cats as $c ) : ?><option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option><?php endforeach; ?>
      </select>
      <select class="stwiz-market__select" id="stwiz-filter-layout" aria-label="<?php esc_attr_e( 'نوع چیدمان', STWIZ_TEXT ); ?>">
        <option value=""><?php esc_html_e( 'همه‌ی چیدمان‌ها', STWIZ_TEXT ); ?></option>
        <?php foreach ( $layout_opts as $l ) : ?><option value="<?php echo esc_attr( $l ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
      </select>
      <select class="stwiz-market__select" id="stwiz-filter-color" aria-label="<?php esc_attr_e( 'طرح‌رنگ', STWIZ_TEXT ); ?>">
        <option value=""><?php esc_html_e( 'همه‌ی رنگ‌ها', STWIZ_TEXT ); ?></option>
        <?php foreach ( $color_opts as $col ) : ?><option value="<?php echo esc_attr( $col ); ?>"><?php echo esc_html( $col ); ?></option><?php endforeach; ?>
      </select>
    </div>

    <div class="stwiz-market__count"><span id="stwiz-market-count"><?php echo esc_html( (string) $total ); ?></span> <?php esc_html_e( 'دمو', STWIZ_TEXT ); ?></div>
  </div>

  <!-- Grid -->
  <div class="stwiz-market__grid" id="stwiz-market-grid">
    <?php foreach ( $demos as $key => $d ) :
      $is_installed = ( $installed === $key );
      $search_blob  = mb_strtolower( trim( $d['title'] . ' ' . $d['desc'] . ' ' . $d['cat'] . ' ' . $d['lay_lbl'] . ' ' . implode( ' ', $d['features'] ) ) );
    ?>
    <article
      class="stwiz-tpl <?php echo $is_installed ? 'is-installed' : ''; ?>"
      data-cat="<?php echo esc_attr( $d['cat'] ); ?>"
      data-layout="<?php echo esc_attr( $d['lay_lbl'] ); ?>"
      data-color="<?php echo esc_attr( $d['color'] ); ?>"
      data-search="<?php echo esc_attr( $search_blob ); ?>"
      style="--tpl-primary:<?php echo esc_attr( $d['primary'] ); ?>;--tpl-accent:<?php echo esc_attr( $d['accent'] ); ?>"
    >
      <!-- Preview area (تا فاز اسکرین‌شات واقعی: پیش‌نمایشِ برندِ دمو) -->
      <div class="stwiz-tpl__preview" aria-hidden="true">
        <span class="stwiz-tpl__icon"><?php echo $d['icon']; ?></span>
        <?php if ( $d['badge'] ) : ?>
          <span class="stwiz-tpl__badge stwiz-tpl__badge--<?php echo esc_attr( strtolower( $d['badge'] ) ); ?>"><?php echo esc_html( $d['badge'] ); ?></span>
        <?php endif; ?>
        <?php if ( $is_installed ) : ?><span class="stwiz-tpl__badge stwiz-tpl__badge--active"><?php esc_html_e( 'فعال', STWIZ_TEXT ); ?></span><?php endif; ?>
      </div>

      <div class="stwiz-tpl__body">
        <div class="stwiz-tpl__titlerow">
          <h3 class="stwiz-tpl__title"><?php echo esc_html( $d['title'] ); ?></h3>
          <span class="stwiz-tpl__cat"><?php echo esc_html( $d['cat'] ); ?></span>
        </div>
        <p class="stwiz-tpl__desc"><?php echo esc_html( $d['desc'] ); ?></p>

        <ul class="stwiz-tpl__features">
          <?php foreach ( array_slice( $d['features'], 0, 5 ) as $f ) : ?>
          <li><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><?php echo esc_html( $f ); ?></li>
          <?php endforeach; ?>
        </ul>

        <div class="stwiz-tpl__tags">
          <span class="stwiz-tpl__tag"><?php echo esc_html( $d['lay_lbl'] ); ?></span>
          <span class="stwiz-tpl__tag"><span class="stwiz-tpl__swatch" style="background:<?php echo esc_attr( $d['primary'] ); ?>"></span><?php echo esc_html( $d['color'] ); ?></span>
          <?php if ( $d['pages'] ) : ?><span class="stwiz-tpl__tag"><?php echo esc_html( $d['pages'] ); ?></span><?php endif; ?>
        </div>

        <button
          type="button"
          class="stwiz-btn stwiz-tpl__import <?php echo $is_installed ? 'stwiz-btn--ghost' : 'stwiz-btn--primary'; ?>"
          data-demo="<?php echo esc_attr( $key ); ?>"
        >
          <?php echo $is_installed ? '↻ ' . esc_html__( 'راه‌اندازی مجدد', STWIZ_TEXT ) : esc_html__( 'انتخاب و راه‌اندازی', STWIZ_TEXT ); ?>
        </button>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <!-- Empty state -->
  <div class="stwiz-market__empty" id="stwiz-market-empty" hidden>
    <div class="stwiz-market__empty-icon">🔍</div>
    <p><?php esc_html_e( 'دمویی با این فیلترها پیدا نشد.', STWIZ_TEXT ); ?></p>
    <button type="button" class="stwiz-btn stwiz-btn--ghost" id="stwiz-market-reset"><?php esc_html_e( 'پاک کردن فیلترها', STWIZ_TEXT ); ?></button>
  </div>

  <div class="stwiz-demo-status" id="demo-status" hidden>
    <div class="stwiz-demo-progress">
      <div class="stwiz-spinner-lg" aria-hidden="true"></div>
      <p id="demo-status-msg"><?php esc_html_e( 'در حال آماده‌سازی…', STWIZ_TEXT ); ?></p>
    </div>
  </div>

  <p class="stwiz-demo-skip">
    <?php esc_html_e( 'ترجیح می‌دهید بدون دمو شروع کنید؟', STWIZ_TEXT ); ?>
    <strong><?php esc_html_e( 'این مرحله اختیاری است.', STWIZ_TEXT ); ?></strong>
  </p>
</div>

<script>
(function () {
  var grid    = document.getElementById('stwiz-market-grid');
  var cards   = Array.prototype.slice.call(grid.querySelectorAll('.stwiz-tpl'));
  var search  = document.getElementById('stwiz-market-search');
  var fCat    = document.getElementById('stwiz-filter-cat');
  var fLayout = document.getElementById('stwiz-filter-layout');
  var fColor  = document.getElementById('stwiz-filter-color');
  var count   = document.getElementById('stwiz-market-count');
  var empty   = document.getElementById('stwiz-market-empty');
  var reset   = document.getElementById('stwiz-market-reset');
  var installBase = <?php echo wp_json_encode( admin_url( 'admin.php?page=signteb-wizard&step=install' ) ); ?>;

  function norm(s) { return (s || '').toString().toLowerCase().trim(); }

  function apply() {
    var q = norm(search.value);
    var c = fCat.value, l = fLayout.value, col = fColor.value;
    var shown = 0;
    cards.forEach(function (card) {
      var ok = (!q   || card.dataset.search.indexOf(q) !== -1)
            && (!c   || card.dataset.cat === c)
            && (!l   || card.dataset.layout === l)
            && (!col || card.dataset.color === col);
      card.hidden = !ok;
      if (ok) shown++;
    });
    count.textContent = shown;
    empty.hidden = shown !== 0;
  }

  search.addEventListener('input', apply);
  [fCat, fLayout, fColor].forEach(function (el) { el.addEventListener('change', apply); });
  reset.addEventListener('click', function () {
    search.value = ''; fCat.value = ''; fLayout.value = ''; fColor.value = '';
    apply();
  });

  // Import → صفحه‌ی راه‌اندازی خودکار (اجرای ۸ تسک با نوار پیشرفت)
  cards.forEach(function (card) {
    var btn = card.querySelector('.stwiz-tpl__import');
    if (!btn) return;
    btn.addEventListener('click', function () {
      cards.forEach(function (c) { c.style.opacity = '0.45'; });
      card.style.opacity = '1';
      window.location.href = installBase + '&demo=' + encodeURIComponent(btn.dataset.demo);
    });
  });
})();
</script>
