<?php
/**
 * SignTeb Fatty Liver Calculator — Frontend Template
 * Standalone commercial plugin widget. No nav, no footer, no site chrome.
 * All dynamic CTA variables resolved from Plugin Settings.
 *
 * @package SignTeb_Fatty_Liver_Calculator
 */
defined( 'ABSPATH' ) || exit;

/* ── Resolve CTA template variables from plugin settings ──────────── */
$st_cta_title    = esc_html( get_option( 'signteb_liver_cta_title',    'برای مشاوره تخصصی با ما تماس بگیرید' ) );
$st_cta_desc     = esc_html( get_option( 'signteb_liver_cta_desc',     'پس از مشاهده نتایج غربالگری، مشاوره با متخصص گوارش را جدی بگیرید.' ) );
$st_cta_btn      = esc_html( get_option( 'signteb_liver_cta_btn',      'رزرو نوبت آنلاین' ) );
$st_appt_url     = esc_url(  get_option( 'signteb_liver_appointment_url', '#' ) );
$st_phone        = esc_html( get_option( 'signteb_liver_phone',        '' ) );
$st_wa           = esc_attr( get_option( 'signteb_liver_wa_number',    '989191182649' ) );
$st_address      = esc_html( get_option( 'signteb_liver_address',      '' ) );
$st_hours        = esc_html( get_option( 'signteb_liver_hours',        '' ) );
$st_doctor       = esc_html( get_option( 'signteb_liver_doctor_name',  '' ) );
$st_clinic       = esc_html( get_option( 'signteb_liver_clinic_name',  '' ) );
$st_wa_url       = 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $st_wa );
$st_show_cta     = ( $st_cta_title || $st_phone || $st_wa );
?>

<div id="signteb-calc-wrapper" role="main" aria-label="محاسبه‌گر گرید کبد چرب — SignTeb">

  <!-- ═══════════════════════════════════════════════════════════
       PLUGIN TOP BAR
  ═══════════════════════════════════════════════════════════ -->
  <div class="st-topbar">
    <div class="st-topbar-brand">
      <div class="st-topbar-mark" aria-hidden="true">
        <svg viewBox="0 0 18 18" fill="none">
          <path d="M1 9 L4.5 9 L6 4.5 L7.5 13.5 L9 7 L10.5 11 L12 8 L13.5 10 L17 9"
                stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <span class="st-topbar-name">SignTeb</span>
      <span class="st-topbar-sep" aria-hidden="true">|</span>
      <span class="st-topbar-tool">Fatty Liver Calculator — Medical Plugin</span>
    </div>
    <div class="st-topbar-right">
      <span class="st-topbar-ver">v<?php echo esc_html( SIGNTEB_LIVER_VERSION ); ?></span>
      <span class="st-topbar-secure">
        <svg viewBox="0 0 14 14" fill="none" width="11" height="11">
          <path d="M7 1L2 3v4c0 2.8 2.1 5.4 5 6 2.9-.6 5-3.2 5-6V3L7 1z" stroke="currentColor" stroke-width="1.2" fill="none"/>
          <path d="M5 7l1.5 1.5L9 5.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        HIPAA-safe
      </span>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════
       HERO
  ═══════════════════════════════════════════════════════════ -->
  <div class="st-hero">
    <div class="st-hero-content">
      <div class="st-hero-badge">
        <svg viewBox="0 0 14 14" fill="none" width="12" height="12"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.3"/><path d="M5 7l1.5 1.5L9.5 5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
        ابزار غربالگری بالینی — Bedogni FLI Formula 2006
      </div>
      <h1 class="st-hero-title">محاسبه‌گر گرید کبد چرب</h1>
      <p class="st-hero-sub">
        <svg viewBox="0 0 14 14" fill="none" width="14" height="14"><circle cx="7" cy="7" r="5.5" stroke="#0891b2" stroke-width="1.3"/><path d="M7 4.5v3l2 1.5" stroke="#0891b2" stroke-width="1.3" stroke-linecap="round"/></svg>
        بر اساس شاخص جهانی FLI — اعتبارسنجی‌شده با حساسیت ۸۷٪ و ویژگی ۸۶٪
      </p>
      <div class="st-hero-chips">
        <span class="st-chip st-chip-green">
          <svg viewBox="0 0 12 12" fill="none" width="10" height="10"><path d="M2 6l2.5 2.5L10 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          اعتبارسنجی بالینی
        </span>
        <span class="st-chip">
          <svg viewBox="0 0 12 12" fill="none" width="10" height="10"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.2"/><path d="M6 3.5V6l2 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
          زیر ۲ دقیقه
        </span>
        <span class="st-chip">
          <svg viewBox="0 0 12 12" fill="none" width="10" height="10"><rect x="2" y="5" width="8" height="6" rx="1.5" stroke="currentColor" stroke-width="1.2"/><path d="M4 5V3.5a2 2 0 014 0V5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
          بدون ذخیره داده
        </span>
        <span class="st-chip">
          <svg viewBox="0 0 12 12" fill="none" width="10" height="10"><circle cx="6" cy="6" r="4.5" stroke="currentColor" stroke-width="1.2"/><path d="M6 3v2l1.5 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
          فارسی / عربی / EN
        </span>
      </div>
    </div>
    <div class="st-hero-stats">
      <div class="st-hero-stat">
        <div class="st-hero-stat-val" id="st-eval-count">+۱۲۰۰</div>
        <div class="st-hero-stat-lbl">ارزیابی انجام شده</div>
      </div>
      <div class="st-hero-stat">
        <div class="st-hero-stat-val st-green">۴ گرید</div>
        <div class="st-hero-stat-lbl">طبقه‌بندی دقیق</div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════
       TAB NAVIGATION
  ═══════════════════════════════════════════════════════════ -->
  <div class="st-tabs-wrap">
    <div class="st-tabs" role="tablist" aria-label="انتخاب روش غربالگری">
      <button class="st-tab-btn active" data-tab="clinical"
              role="tab" aria-selected="true" aria-controls="st-tab-clinical">
        <svg viewBox="0 0 16 16" fill="none" width="14" height="14"><circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.3"/><path d="M5.5 8h5M8 5.5v5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
        <span class="st-tab-label">غربالگری بالینی</span>
        <span class="st-tab-sub">نیاز به آزمایش</span>
      </button>
      <button class="st-tab-btn" data-tab="lifestyle"
              role="tab" aria-selected="false" aria-controls="st-tab-lifestyle">
        <svg viewBox="0 0 16 16" fill="none" width="14" height="14"><path d="M5 13V8l-2-3h10l-2 3v5M6 13h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span class="st-tab-label">غربالگری سبک زندگی</span>
        <span class="st-tab-sub">بدون آزمایش</span>
      </button>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════
       FORM SECTION
  ═══════════════════════════════════════════════════════════ -->
  <div id="signteb-form-section" class="st-form-wrap">

    <!-- ──────────── TAB A: CLINICAL ──────────── -->
    <div id="st-tab-clinical" class="st-tab-pane active" role="tabpanel">

      <!-- Row 1: Patient info + Waist -->
      <div class="st-row-2">

        <!-- Card: Patient Info -->
        <div class="st-card">
          <div class="st-card-hd">
            <span class="st-card-ico st-ico-blue">
              <svg viewBox="0 0 16 16" fill="none" width="14"><circle cx="8" cy="5" r="3" stroke="#185FA5" stroke-width="1.3"/><path d="M2 14c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="#185FA5" stroke-width="1.3" stroke-linecap="round"/></svg>
            </span>
            <span class="st-card-title">اطلاعات پایه بیمار</span>
          </div>

          <div class="st-grid-2">
            <div class="st-field">
              <label class="st-lbl" for="st-age">سن <span class="st-req">*</span></label>
              <div class="st-input-wrap">
                <input type="number" id="st-age" name="age" class="st-input"
                       placeholder="۳۵" min="10" max="120" inputmode="numeric" autocomplete="off">
                <span class="st-unit">سال</span>
              </div>
            </div>
            <div class="st-field">
              <label class="st-lbl" for="st-gender">جنسیت <span class="st-req">*</span></label>
              <select id="st-gender" name="gender" class="st-input st-select">
                <option value="" disabled selected>انتخاب کنید…</option>
                <option value="male">مرد</option>
                <option value="female">زن</option>
              </select>
              <span class="st-field-err"></span>
            </div>
            <div class="st-field">
              <label class="st-lbl" for="st-height">قد <span class="st-req">*</span></label>
              <div class="st-input-wrap">
                <input type="number" id="st-height" name="height" class="st-input"
                       placeholder="170" min="100" max="250" step="0.5" inputmode="decimal" autocomplete="off">
                <span class="st-unit">cm</span>
              </div>
            </div>
            <div class="st-field">
              <label class="st-lbl" for="st-weight">وزن <span class="st-req">*</span></label>
              <div class="st-input-wrap">
                <input type="number" id="st-weight" name="weight" class="st-input"
                       placeholder="75" min="20" max="500" step="0.5" inputmode="decimal" autocomplete="off">
                <span class="st-unit">kg</span>
              </div>
            </div>
          </div>

          <!-- BMI auto-display -->
          <div id="st-bmi-box" class="st-bmi-box" style="display:none" aria-live="polite">
            <div class="st-bmi-inner">
              <div>
                <div class="st-bmi-label">BMI — محاسبه خودکار</div>
                <div class="st-bmi-cat" id="st-bmi-cat"></div>
              </div>
              <div class="st-bmi-val" id="st-bmi-display">—</div>
            </div>
            <div class="st-bmi-track"><div class="st-bmi-fill" id="st-bmi-bar"></div></div>
            <div class="st-bmi-scale"><span>کم‌وزن</span><span>طبیعی</span><span>اضافه‌وزن</span><span>چاقی</span></div>
          </div>
          <input type="hidden" id="st-bmi-val">
        </div><!-- /patient card -->

        <!-- Card: Waist -->
        <div class="st-card st-card-flex">
          <div class="st-card-hd">
            <span class="st-card-ico st-ico-teal">
              <svg viewBox="0 0 16 16" fill="none" width="14"><path d="M2 8h12M2 11h12M2 5h12" stroke="#0F6E56" stroke-width="1.3" stroke-linecap="round"/></svg>
            </span>
            <span class="st-card-title">دور کمر</span>
          </div>

          <div class="st-waist-hint">
            <svg viewBox="0 0 12 12" fill="none" width="11" height="11"><circle cx="6" cy="6" r="4.5" stroke="#0891b2" stroke-width="1.2"/><path d="M6 5v3" stroke="#0891b2" stroke-width="1.3" stroke-linecap="round"/><circle cx="6" cy="4" r=".5" fill="#0891b2"/></svg>
            اندازه‌گیری در بالاترین نقطه استخوان لگن
          </div>

          <div class="st-field">
            <label class="st-lbl" for="st-waist">دور کمر <span class="st-req">*</span></label>
            <div class="st-input-wrap">
              <input type="number" id="st-waist" name="waist" class="st-input"
                     placeholder="88" min="40" max="250" step="0.5" inputmode="decimal">
              <span class="st-unit">cm</span>
            </div>
          </div>

          <div class="st-ref-block">
            <div class="st-ref-item"><span class="st-ref-dot" style="background:#1D9E75"></span>مرد: طبیعی &lt; ۹۴ cm</div>
            <div class="st-ref-item"><span class="st-ref-dot" style="background:#534AB7"></span>زن: طبیعی &lt; ۸۰ cm</div>
          </div>
        </div><!-- /waist card -->

      </div><!-- /row-2 -->

      <!-- Card: Lab Values -->
      <div class="st-card">
        <div class="st-card-hd">
          <span class="st-card-ico st-ico-violet">
            <svg viewBox="0 0 16 16" fill="none" width="14"><path d="M6 2v6l-2.5 4.5h9L10 8V2M5 2h6" stroke="#534AB7" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </span>
          <span class="st-card-title">مقادیر آزمایشگاهی</span>
          <span class="st-card-badge">آزمایش خون اخیر</span>
        </div>

        <div class="st-lab-grid">
          <div class="st-field">
            <label class="st-lbl" for="st-triglycerides">تری‌گلیسرید <span class="st-req">*</span></label>
            <div class="st-lab-wrap">
              <input type="number" id="st-triglycerides" name="triglycerides" class="st-input st-lab-input"
                     placeholder="150" min="10" max="2000" inputmode="numeric">
              <div class="st-lab-unit">mg/dL</div>
            </div>
            <div class="st-lab-refs">
              <span class="st-ref-ok">طبیعی: &lt;۱۵۰</span>
              <span class="st-ref-warn">بالا: &gt;۲۰۰</span>
            </div>
          </div>

          <div class="st-field">
            <label class="st-lbl" for="st-ggt">GGT — گاما گلوتامیل ترانسفراز <span class="st-req">*</span></label>
            <div class="st-lab-wrap">
              <input type="number" id="st-ggt" name="ggt" class="st-input st-lab-input"
                     placeholder="35" min="1" max="5000" inputmode="numeric">
              <div class="st-lab-unit">U/L</div>
            </div>
            <div class="st-lab-refs">
              <span class="st-ref-ok">مرد: &lt;۵۵</span>
              <span class="st-ref-ok">زن: &lt;۳۸</span>
            </div>
          </div>
        </div>

        <!-- FLI Live Preview -->
        <div id="st-fli-preview" class="st-fli-box" style="display:none" aria-live="polite">
          <div class="st-fli-header">
            <div class="st-fli-label-grp">
              <svg viewBox="0 0 14 14" fill="none" width="12" height="12"><rect x="2" y="9" width="2.5" height="4" rx=".5" fill="#185FA5"/><rect x="5.75" y="6" width="2.5" height="7" rx=".5" fill="#185FA5"/><rect x="9.5" y="3" width="2.5" height="10" rx=".5" fill="#185FA5"/></svg>
              پیش‌نمایش شاخص FLI
            </div>
            <div class="st-fli-val-grp">
              <span class="st-fli-num" id="st-fli-val">—</span>
              <span class="st-fli-denom">/ 100</span>
            </div>
          </div>
          <div class="st-fli-track">
            <div class="st-fli-zones">
              <div class="st-fz" style="width:30%;background:rgba(29,158,117,.14)"></div>
              <div class="st-fz" style="width:30%;background:rgba(239,159,39,.13)"></div>
              <div class="st-fz" style="width:20%;background:rgba(249,115,22,.11)"></div>
              <div class="st-fz" style="width:20%;background:rgba(220,38,38,.10)"></div>
            </div>
            <div class="st-fli-fill" id="st-fli-bar" style="width:0%"></div>
          </div>
          <div class="st-fli-scale"><span>۰</span><span>۳۰</span><span>۶۰</span><span>۸۰</span><span>۱۰۰</span></div>
        </div>
      </div><!-- /lab card -->

      <!-- CTA -->
      <button type="button" class="st-cta-btn" id="st-calc-clinical">
        <svg viewBox="0 0 16 16" fill="none" width="15" height="15"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 10.5L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        تحلیل بالینی و محاسبه گرید کبد چرب
        <svg viewBox="0 0 14 14" fill="none" width="13" height="13"><path d="M10 7H4M7 4l-3 3 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>

    </div><!-- /#st-tab-clinical -->

    <!-- ──────────── TAB B: LIFESTYLE ──────────── -->
    <div id="st-tab-lifestyle" class="st-tab-pane" role="tabpanel">

      <div class="st-info-banner">
        <svg viewBox="0 0 14 14" fill="none" width="13" height="13" style="flex-shrink:0;margin-top:1px"><circle cx="7" cy="7" r="5.5" stroke="#92400e" stroke-width="1.2"/><path d="M7 5v4" stroke="#92400e" stroke-width="1.3" stroke-linecap="round"/><circle cx="7" cy="4" r=".5" fill="#92400e"/></svg>
        این روش برای کسانی است که آزمایش خون اخیر ندارند. نتیجه یک <strong>تخمین کیفی</strong> از سطح ریسک خواهد بود.
      </div>

      <!-- Body measurements -->
      <div class="st-card">
        <div class="st-card-hd">
          <span class="st-card-ico st-ico-blue">
            <svg viewBox="0 0 16 16" fill="none" width="14"><path d="M2 14c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="#185FA5" stroke-width="1.3" stroke-linecap="round"/><circle cx="8" cy="5" r="3" stroke="#185FA5" stroke-width="1.3"/></svg>
          </span>
          <span class="st-card-title">اندازه‌های بدنی</span>
        </div>
        <div class="st-grid-2">
          <div class="st-field">
            <label class="st-lbl" for="ls-gender">جنسیت <span class="st-req">*</span></label>
            <select id="ls-gender" name="ls_gender" class="st-input st-select">
              <option value="" disabled selected>انتخاب کنید…</option>
              <option value="male">مرد</option>
              <option value="female">زن</option>
            </select>
            <span class="st-field-err"></span>
          </div>
          <div class="st-field">
            <label class="st-lbl" for="ls-height">قد <span class="st-req">*</span></label>
            <div class="st-input-wrap">
              <input type="number" id="ls-height" name="ls_height" class="st-input"
                     placeholder="170" min="100" max="250" step="0.5" inputmode="decimal">
              <span class="st-unit">cm</span>
            </div>
          </div>
          <div class="st-field">
            <label class="st-lbl" for="ls-weight">وزن <span class="st-req">*</span></label>
            <div class="st-input-wrap">
              <input type="number" id="ls-weight" name="ls_weight" class="st-input"
                     placeholder="75" min="20" max="500" step="0.5" inputmode="decimal">
              <span class="st-unit">kg</span>
            </div>
          </div>
          <div class="st-field">
            <label class="st-lbl" for="ls-waist">دور کمر <span class="st-req">*</span></label>
            <div class="st-input-wrap">
              <input type="number" id="ls-waist" name="ls_waist" class="st-input"
                     placeholder="88" min="40" max="250" step="0.5" inputmode="decimal">
              <span class="st-unit">cm</span>
            </div>
          </div>
        </div>
        <div id="ls-bmi-box" class="st-bmi-box" style="display:none" aria-live="polite">
          <div class="st-bmi-inner">
            <div><div class="st-bmi-label">BMI محاسبه شده</div><div class="st-bmi-cat" id="ls-bmi-cat"></div></div>
            <div class="st-bmi-val" id="ls-bmi-display">—</div>
          </div>
        </div>
        <input type="hidden" id="ls-bmi-val">
      </div>

      <!-- Health history -->
      <div class="st-card">
        <div class="st-card-hd">
          <span class="st-card-ico st-ico-red">
            <svg viewBox="0 0 16 16" fill="none" width="14"><path d="M8 3C5 3 2 5 2 8s3 5 6 5 6-2 6-5-3-5-6-5z" stroke="#A32D2D" stroke-width="1.3"/><path d="M8 6v3l2 1.5" stroke="#A32D2D" stroke-width="1.3" stroke-linecap="round"/></svg>
          </span>
          <span class="st-card-title">سابقه سلامت</span>
        </div>
        <div class="st-tog-group">
          <div class="st-tog-lbl">وضعیت دیابت نوع ۲ <span class="st-req">*</span></div>
          <div class="st-togs st-togs-3" id="ls-diabetes-opts" role="radiogroup">
            <button type="button" class="st-opt" data-group="ls-diabetes" data-val="0" aria-pressed="false">
              <span class="st-tog-icon">✅</span>
              <span class="st-tog-lbl-t">ندارم</span>
            </button>
            <button type="button" class="st-opt" data-group="ls-diabetes" data-val="1" aria-pressed="false">
              <span class="st-tog-icon">⚠️</span>
              <span class="st-tog-lbl-t">پیش‌دیابت</span>
            </button>
            <button type="button" class="st-opt" data-group="ls-diabetes" data-val="2" aria-pressed="false">
              <span class="st-tog-icon">🩸</span>
              <span class="st-tog-lbl-t">دیابت نوع ۲</span>
            </button>
          </div>
          <input type="hidden" id="ls-diabetes" name="ls_diabetes" value="">
          <span class="st-tog-err" id="ls-diabetes-err"></span>
        </div>
      </div>

      <!-- Lifestyle habits -->
      <div class="st-card">
        <div class="st-card-hd">
          <span class="st-card-ico st-ico-green">
            <svg viewBox="0 0 16 16" fill="none" width="14"><path d="M8 3c0 0-5 2.5-5 6.5 0 2.8 2.2 4.5 5 4.5s5-1.7 5-4.5C13 5.5 8 3 8 3z" stroke="#3B6D11" stroke-width="1.3"/><path d="M8 7v5" stroke="#3B6D11" stroke-width="1.3" stroke-linecap="round"/></svg>
          </span>
          <span class="st-card-title">عادات سبک زندگی</span>
        </div>

        <div class="st-tog-group">
          <div class="st-tog-lbl">فعالیت بدنی هفتگی <span class="st-req">*</span></div>
          <div class="st-togs st-togs-3" id="ls-activity-opts" role="radiogroup">
            <button type="button" class="st-opt" data-group="ls-activity" data-val="0" aria-pressed="false">
              <span class="st-tog-icon">🏅</span>
              <span class="st-tog-lbl-t">فعال</span>
              <span class="st-tog-sub">+۱۵۰ دقیقه</span>
            </button>
            <button type="button" class="st-opt" data-group="ls-activity" data-val="1" aria-pressed="false">
              <span class="st-tog-icon">🚶</span>
              <span class="st-tog-lbl-t">نیمه‌فعال</span>
              <span class="st-tog-sub">۶۰–۱۵۰ دقیقه</span>
            </button>
            <button type="button" class="st-opt" data-group="ls-activity" data-val="2" aria-pressed="false">
              <span class="st-tog-icon">🛋️</span>
              <span class="st-tog-lbl-t">کم‌تحرک</span>
              <span class="st-tog-sub">زیر ۶۰ دقیقه</span>
            </button>
          </div>
          <input type="hidden" id="ls-activity" name="ls_activity" value="">
          <span class="st-tog-err" id="ls-activity-err"></span>
        </div>

        <div class="st-tog-group">
          <div class="st-tog-lbl">کیفیت رژیم غذایی <span class="st-req">*</span></div>
          <div class="st-togs st-togs-3" id="ls-diet-opts" role="radiogroup">
            <button type="button" class="st-opt" data-group="ls-diet" data-val="0" aria-pressed="false">
              <span class="st-tog-icon">🥗</span>
              <span class="st-tog-lbl-t">سالم</span>
              <span class="st-tog-sub">کم‌چرب، سبزیجات</span>
            </button>
            <button type="button" class="st-opt" data-group="ls-diet" data-val="1" aria-pressed="false">
              <span class="st-tog-icon">🍽️</span>
              <span class="st-tog-lbl-t">متوسط</span>
              <span class="st-tog-sub">ترکیبی</span>
            </button>
            <button type="button" class="st-opt" data-group="ls-diet" data-val="2" aria-pressed="false">
              <span class="st-tog-icon">🍔</span>
              <span class="st-tog-lbl-t">ناسالم</span>
              <span class="st-tog-sub">پرچرب، فست‌فود</span>
            </button>
          </div>
          <input type="hidden" id="ls-diet" name="ls_diet" value="">
          <span class="st-tog-err" id="ls-diet-err"></span>
        </div>

        <div class="st-tog-group st-tog-last">
          <div class="st-tog-lbl">مصرف الکل <span class="st-req">*</span></div>
          <div class="st-togs st-togs-3" id="ls-alcohol-opts" role="radiogroup">
            <button type="button" class="st-opt" data-group="ls-alcohol" data-val="0" aria-pressed="false">
              <span class="st-tog-icon">🚫</span>
              <span class="st-tog-lbl-t">هیچ</span>
            </button>
            <button type="button" class="st-opt" data-group="ls-alcohol" data-val="1" aria-pressed="false">
              <span class="st-tog-icon">🍷</span>
              <span class="st-tog-lbl-t">گاهی</span>
            </button>
            <button type="button" class="st-opt" data-group="ls-alcohol" data-val="2" aria-pressed="false">
              <span class="st-tog-icon">⚠️</span>
              <span class="st-tog-lbl-t">منظم</span>
            </button>
          </div>
          <input type="hidden" id="ls-alcohol" name="ls_alcohol" value="">
          <span class="st-tog-err" id="ls-alcohol-err"></span>
        </div>
      </div><!-- /habits card -->

      <button type="button" class="st-cta-btn st-cta-btn-green" id="st-calc-lifestyle">
        <svg viewBox="0 0 16 16" fill="none" width="15" height="15"><circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 10.5L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        ارزیابی ریسک و تخمین گرید کبد چرب
        <svg viewBox="0 0 14 14" fill="none" width="13" height="13"><path d="M10 7H4M7 4l-3 3 3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>

    </div><!-- /#st-tab-lifestyle -->

  </div><!-- /#signteb-form-section -->

  <!-- ═══════════════════════════════════════════════════════════
       LOADING OVERLAY
  ═══════════════════════════════════════════════════════════ -->
  <div id="st-loading" role="status" aria-live="polite">
    <div class="st-loader-card">
      <div class="st-loader-ring">
        <svg viewBox="0 0 24 24" fill="none" width="24" height="24">
          <path d="M2 12L6 12L8 5L10 19L12 9L14 14.5L16 11L18 13L22 12"
                stroke="#0891b2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="st-loader-title">تحلیل داده‌های بالینی</div>
      <div class="st-loader-step" id="st-loader-step">دریافت اطلاعات...</div>
      <div class="st-loader-dots"><span></span><span></span><span></span></div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════
       LEAD GATE — LOCK/UNLOCK
  ═══════════════════════════════════════════════════════════ -->
  <div id="st-gate" role="dialog" aria-modal="true" aria-labelledby="st-gate-title">
    <div class="st-gate-bg"></div>
    <div class="st-gate-card">
      <button class="st-gate-close" id="st-gate-close" aria-label="بستن">
        <svg viewBox="0 0 14 14" fill="none" width="13" height="13"><path d="M3 3l8 8M11 3L3 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      </button>

      <div class="st-gate-lock-wrap">
        <div class="st-gate-lock-ring">
          <svg viewBox="0 0 24 24" fill="none" width="26" height="26">
            <rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/>
            <path d="M7 11V7a5 5 0 0110 0v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="st-gate-lock-label">نتایج آماده است</div>
      </div>

      <h2 class="st-gate-title" id="st-gate-title">برای دریافت گزارش کامل وارد شوید</h2>
      <p class="st-gate-sub">با ثبت‌نام رایگان، دسترسی فوری به گزارش تخصصی خود را دریافت کنید</p>

      <div class="st-gate-perks">
        <div class="st-gate-perk"><div class="st-perk-check">✓</div><span>گیج ریسک تعاملی با انیمیشن</span></div>
        <div class="st-gate-perk"><div class="st-perk-check">✓</div><span>برنامه رژیم غذایی اختصاصی</span></div>
        <div class="st-gate-perk"><div class="st-perk-check">✓</div><span>توصیه‌های پزشکی مرحله‌به‌مرحله</span></div>
      </div>

      <div class="st-gate-field">
        <label class="st-gate-lbl" for="st-lead-name">نام و نام‌خانوادگی <span class="st-req">*</span></label>
        <input type="text" id="st-lead-name" class="st-input st-gate-input"
               placeholder="مثلاً: علی رضایی" autocomplete="name" maxlength="200">
        <span class="st-field-err" id="st-lead-name-err" role="alert"></span>
      </div>

      <div class="st-gate-field">
        <label class="st-gate-lbl" for="st-lead-phone">شماره موبایل <span class="st-req">*</span></label>
        <div class="st-gate-phone-row">
          <div class="st-gate-prefix">🇮🇷 +98</div>
          <input type="tel" id="st-lead-phone" class="st-input st-gate-phone-input"
                 placeholder="09XXXXXXXXX" dir="ltr" autocomplete="tel" maxlength="11" inputmode="tel">
        </div>
        <span class="st-field-err" id="st-lead-phone-err" role="alert"></span>
      </div>

      <!-- OTP verification (revealed only when SMS verification is enabled) -->
      <div class="st-gate-field" id="st-otp-field" style="display:none">
        <label class="st-gate-lbl" for="st-lead-otp">کد تأیید پیامک‌شده <span class="st-req">*</span></label>
        <input type="text" id="st-lead-otp" class="st-input st-gate-input st-otp-input"
               inputmode="numeric" maxlength="6" dir="ltr" autocomplete="one-time-code"
               placeholder="– – – – –">
        <div class="st-otp-meta">
          <span id="st-otp-sent-to"></span>
          <button type="button" id="st-otp-resend" class="st-otp-resend" disabled>ارسال مجدد کد (<span id="st-otp-timer">۹۰</span>)</button>
        </div>
        <span class="st-field-err" id="st-lead-otp-err" role="alert"></span>
      </div>

      <div class="st-gate-server-err" id="st-gate-server-err" role="alert"></div>

      <button type="button" id="st-gate-submit" class="st-gate-submit">
        <svg viewBox="0 0 22 22" fill="none" width="17" height="17">
          <rect x="3" y="11" width="16" height="10" rx="2" stroke="currentColor" stroke-width="1.7"/>
          <path d="M7 11V7.5a4 4 0 017.9-.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
        </svg>
        <span id="st-gate-submit-text">مشاهده نتایج کامل — رایگان</span>
      </button>

      <p class="st-gate-privacy">
        <svg viewBox="0 0 12 12" fill="none" width="10" height="10"><path d="M6 1L1.5 3v3c0 2.5 2 4.7 4.5 5.5C8.5 10.7 10.5 8.5 10.5 6V3L6 1z" stroke="currentColor" stroke-width="1.2"/></svg>
        اطلاعات شما محرمانه است و هرگز با اشخاص ثالث به اشتراک گذاشته نخواهد شد
      </p>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════
       RESULTS
  ═══════════════════════════════════════════════════════════ -->
  <div id="st-results" style="display:none" aria-label="نتایج">

    <!-- Result header -->
    <div class="st-result-hero" id="st-result-header-card">
      <div class="st-result-deco" aria-hidden="true">
        <svg viewBox="0 0 80 80" fill="none" width="80" height="80" opacity=".06">
          <circle cx="40" cy="40" r="36" stroke="currentColor" stroke-width="3"/>
          <path d="M8 40L18 40L22 26L26 54L30 38L34 45L38 41L42 43L46 40L72 40"
                stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="st-result-greeting">گزارش تخصصی برای</div>
      <h2 class="st-result-name" id="st-result-name">—</h2>
      <span class="st-result-badge" id="st-result-badge">—</span>
      <p class="st-result-desc" id="st-result-desc">—</p>
    </div>

    <!-- Gauge + stats -->
    <div class="st-result-metrics">

      <div class="st-gauge-card">
        <div class="st-card-hd">
          <span class="st-card-ico st-ico-teal">
            <svg viewBox="0 0 14 14" fill="none" width="12"><rect x="1" y="8" width="3" height="5" rx=".5" fill="#0F6E56"/><rect x="5.5" y="5" width="3" height="8" rx=".5" fill="#0F6E56"/><rect x="10" y="2" width="3" height="11" rx=".5" fill="#0F6E56"/></svg>
          </span>
          <span class="st-card-title">سطح ریسک</span>
        </div>
        <div class="st-gauge-wrap">
          <canvas id="st-gauge-chart" width="260" height="155" role="img" aria-label="نمودار سطح ریسک"></canvas>
          <div class="st-gauge-center">
            <div class="st-gauge-pct" id="st-gauge-pct">0%</div>
            <div class="st-gauge-sub">ریسک کلی</div>
          </div>
        </div>
        <div class="st-gauge-legend">
          <span><span class="st-leg-dot" style="background:#10b981"></span>طبیعی</span>
          <span><span class="st-leg-dot" style="background:#f59e0b"></span>خفیف</span>
          <span><span class="st-leg-dot" style="background:#f97316"></span>متوسط</span>
          <span><span class="st-leg-dot" style="background:#dc2626"></span>شدید</span>
        </div>
      </div>

      <div class="st-stat-col">
        <div class="st-stat-card">
          <span class="st-stat-icon">⚖️</span>
          <div class="st-stat-val" id="st-stat-bmi">—</div>
          <div class="st-stat-lbl">شاخص BMI</div>
        </div>
        <div class="st-stat-card">
          <span class="st-stat-icon">📈</span>
          <div class="st-stat-val" id="st-stat-score">—</div>
          <div class="st-stat-lbl" id="st-stat-score-lbl">شاخص FLI</div>
        </div>
        <div class="st-stat-card" id="st-stat-grade-card">
          <span class="st-stat-icon">🏥</span>
          <div class="st-stat-val" id="st-stat-grade">—</div>
          <div class="st-stat-lbl">گرید تشخیصی</div>
        </div>
      </div>

    </div><!-- /.st-result-metrics -->

    <!-- Recommendations -->
    <div class="st-recs-wrap">
      <div class="st-card-hd" style="margin-bottom:1rem">
        <span class="st-card-ico st-ico-teal">
          <svg viewBox="0 0 14 14" fill="none" width="12"><path d="M2 3h10v1.5L8 9v4H6V9L2 4.5V3z" stroke="#0F6E56" stroke-width="1.2" stroke-linejoin="round"/></svg>
        </span>
        <span class="st-card-title">توصیه‌های اختصاصی بر اساس نتایج شما</span>
      </div>
      <div class="st-recs-grid">
        <div class="st-rec-panel" id="st-rec-diet"></div>
        <div class="st-rec-panel" id="st-rec-activity"></div>
        <div class="st-rec-panel" id="st-rec-medical"></div>
      </div>
    </div>

    <div class="st-result-footer">
      <p class="st-result-disc">⚠️ این نتایج صرفاً جنبه غربالگری دارند و جایگزین معاینه پزشکی تخصصی نمی‌شوند.</p>
      <button type="button" class="st-recalc-btn" id="st-recalc-btn">↺ محاسبه مجدد</button>
    </div>

  </div><!-- /#st-results -->

  <!-- ═══════════════════════════════════════════════════════════
       DYNAMIC CTA BLOCK — configured from Plugin Settings
  ═══════════════════════════════════════════════════════════ -->
  <?php if ( $st_show_cta ) : ?>
  <div class="st-dyn-cta">
    <div class="st-dyn-cta-body">
      <div class="st-dyn-cta-content">
        <?php if ( $st_doctor || $st_clinic ) : ?>
        <div class="st-dyn-cta-tag">
          <svg viewBox="0 0 12 12" fill="none" width="11" height="11"><path d="M6 1L1.5 3v3c0 2.2 2 4.2 4.5 5C8.5 10.2 10.5 8.2 10.5 6V3L6 1z" stroke="#0891b2" stroke-width="1.2"/><path d="M4 6l1.5 1.5L8 4.5" stroke="#0891b2" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <?php echo $st_doctor ? $st_doctor : $st_clinic; ?>
        </div>
        <?php endif; ?>

        <div class="st-dyn-cta-title"><?php echo $st_cta_title; ?></div>
        <p class="st-dyn-cta-desc"><?php echo $st_cta_desc; ?></p>

        <div class="st-dyn-cta-meta">
          <?php if ( $st_phone ) : ?>
          <a href="tel:<?php echo esc_attr( preg_replace('/[^0-9+]/','',$st_phone) ); ?>" class="st-dyn-meta-item">
            <svg viewBox="0 0 14 14" fill="none" width="13" height="13"><path d="M3 2h3l1.5 3.5-1.5 1c.5 1 1.5 2 2.5 2.5l1-1.5L13 9v3a1 1 0 01-1 1C5 13 1 9 1 3a1 1 0 011-1z" stroke="#0891b2" stroke-width="1.2"/></svg>
            <?php echo $st_phone; ?>
          </a>
          <?php endif; ?>
          <?php if ( $st_address ) : ?>
          <div class="st-dyn-meta-item">
            <svg viewBox="0 0 14 14" fill="none" width="13" height="13"><path d="M7 1C4.8 1 3 2.8 3 5c0 3.2 4 8 4 8s4-4.8 4-8c0-2.2-1.8-4-4-4z" stroke="#0891b2" stroke-width="1.2"/><circle cx="7" cy="5" r="1.5" stroke="#0891b2" stroke-width="1.2"/></svg>
            <?php echo $st_address; ?>
          </div>
          <?php endif; ?>
          <?php if ( $st_hours ) : ?>
          <div class="st-dyn-meta-item">
            <svg viewBox="0 0 14 14" fill="none" width="13" height="13"><circle cx="7" cy="7" r="5.5" stroke="#0891b2" stroke-width="1.2"/><path d="M7 4v3l2 1.5" stroke="#0891b2" stroke-width="1.2" stroke-linecap="round"/></svg>
            <?php echo $st_hours; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="st-dyn-cta-actions">
        <?php if ( $st_appt_url && $st_appt_url !== '#' ) : ?>
        <a href="<?php echo $st_appt_url; ?>" class="st-dyn-btn-primary" target="_blank" rel="noopener">
          <?php echo $st_cta_btn ? $st_cta_btn : 'مشاوره فوری'; ?>
        </a>
        <?php endif; ?>
        <?php if ( $st_wa ) : ?>
        <a href="<?php echo esc_url($st_wa_url); ?>" class="st-dyn-btn-wa" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          مشاوره واتساپ
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════════════
       ACCORDION
  ═══════════════════════════════════════════════════════════ -->
  <div class="st-acc-wrap">
    <div class="st-acc-item">
      <button class="st-acc-hd" aria-expanded="false">
        <div class="st-acc-hd-left">
          <div class="st-acc-ico">📖</div>
          <div><div class="st-acc-title">راهنمای استفاده</div><div class="st-acc-sub">نحوه وارد کردن اطلاعات و تفسیر نتایج</div></div>
        </div>
        <div class="st-acc-hd-right">
          <span class="st-acc-badge">۳ مرحله</span>
          <svg class="st-acc-arrow" viewBox="0 0 12 12" fill="none" width="12" height="12"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
      </button>
      <div class="st-acc-body">
        <p>۱. تب مناسب را انتخاب کنید: اگر آزمایش خون اخیر دارید از «غربالگری بالینی» استفاده کنید. ۲. فیلدها را با دقت پر کنید. BMI به‌صورت خودکار محاسبه می‌شود. ۳. دکمه محاسبه را بزنید و پس از ثبت اطلاعات تماس، نتایج کامل را مشاهده کنید.</p>
      </div>
    </div>
    <div class="st-acc-item">
      <button class="st-acc-hd" aria-expanded="true">
        <div class="st-acc-hd-left">
          <div class="st-acc-ico">🔬</div>
          <div><div class="st-acc-title">درباره محاسبه‌گر</div><div class="st-acc-sub">فرمول FLI، دقت بالینی و منابع علمی</div></div>
        </div>
        <div class="st-acc-hd-right">
          <span class="st-acc-badge">Bedogni 2006</span>
          <svg class="st-acc-arrow st-acc-open" viewBox="0 0 12 12" fill="none" width="12" height="12"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
      </button>
      <div class="st-acc-body st-acc-body-open">
        <p>شاخص FLI بر اساس مقاله Bedogni و همکاران (۲۰۰۶) در مجله BMC Gastroenterology طراحی شده است. این شاخص با حساسیت ۸۷٪ و ویژگی ۸۶٪ وجود کبد چرب را پیش‌بینی می‌کند و شامل چهار متغیر بالینی: تری‌گلیسرید، BMI، GGT و دور کمر است.</p>
      </div>
    </div>
    <div class="st-acc-item">
      <button class="st-acc-hd" aria-expanded="false">
        <div class="st-acc-hd-left">
          <div class="st-acc-ico">🔒</div>
          <div><div class="st-acc-title">حفظ حریم خصوصی</div><div class="st-acc-sub">سیاست استفاده از داده‌ها</div></div>
        </div>
        <div class="st-acc-hd-right">
          <svg class="st-acc-arrow" viewBox="0 0 12 12" fill="none" width="12" height="12"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
      </button>
      <div class="st-acc-body">
        <p>اطلاعات بالینی شما صرفاً برای محاسبه شاخص FLI استفاده می‌شود. اطلاعات تماس (نام و موبایل) تنها برای ارسال گزارش ذخیره می‌شود و هرگز با اشخاص ثالث به اشتراک گذاشته نخواهد شد.</p>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════
       DISCLAIMER
  ═══════════════════════════════════════════════════════════ -->
  <div class="st-disclaimer">
    <svg viewBox="0 0 14 14" fill="none" width="13" height="13" style="flex-shrink:0;margin-top:1px"><path d="M7 1L1 13h12L7 1z" stroke="#BA7517" stroke-width="1.2" stroke-linejoin="round"/><path d="M7 6v3" stroke="#BA7517" stroke-width="1.3" stroke-linecap="round"/><circle cx="7" cy="11" r=".5" fill="#BA7517"/></svg>
    این ابزار صرفاً جهت غربالگری اولیه طراحی شده است و جایگزین تشخیص و معاینه توسط پزشک متخصص نمی‌شود.
  </div>

  <!-- ═══════════════════════════════════════════════════════════
       PLUGIN FOOTER BAR
  ═══════════════════════════════════════════════════════════ -->
  <div class="st-plugin-foot">
    <div class="st-foot-brand">
      <div class="st-foot-mark">ST</div>
      Powered by <strong>SignTeb</strong> Medical Plugin Suite
    </div>
    <div class="st-foot-links">
      <a href="https://signteb.com" target="_blank" rel="noopener" class="st-foot-link">signteb.com</a>
      <a href="https://signteb.com/support" target="_blank" rel="noopener" class="st-foot-link">Support</a>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════
       WHATSAPP FLOATING BUTTON
  ═══════════════════════════════════════════════════════════ -->
  <div id="st-wa-cta" style="display:none" aria-label="مشاوره واتساپ">
    <a id="st-wa-link" href="#" class="st-wa-btn" target="_blank" rel="noopener noreferrer">
      <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      درخواست مشاوره تخصصی
    </a>
  </div>

</div><!-- /#signteb-calc-wrapper -->
