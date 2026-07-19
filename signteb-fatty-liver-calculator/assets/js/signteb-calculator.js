/**
 * SignTeb Fatty Liver Calculator — Front-end Engine
 *
 * Architecture:
 *  - ENGINE   : Pure calculation functions (FLI, BMI, lifestyle score)
 *  - STATE    : Single source of truth for app state
 *  - UI       : DOM manipulation helpers
 *  - VALIDATE : Input validation
 *  - AJAX     : Server communication
 *  - EVENTS   : Event delegation setup
 *
 * Dependencies: jQuery (WP core), signTebConfig (wp_localize_script)
 */
(function ($) {
  'use strict';

  /* ══════════════════════════════════════════════════════════════════════════
   * STATE
   * ══════════════════════════════════════════════════════════════════════════ */
  var STATE = {
    activeTab   : 'clinical',  // 'clinical' | 'lifestyle'
    calcData    : null,        // collected form data (set before gate opens)
    submitted   : false,       // prevent duplicate AJAX calls
    otpSent     : false,       // OTP verification: code has been dispatched
  };

  /* ══════════════════════════════════════════════════════════════════════════
   * ENGINE — Pure calculation helpers
   * ══════════════════════════════════════════════════════════════════════════ */
  var ENGINE = {

    /**
     * BMI = weight(kg) / (height(m))²
     * @returns {number}  0 if inputs invalid
     */
    bmi: function (heightCm, weightKg) {
      if (!heightCm || !weightKg || heightCm <= 0 || weightKg <= 0) return 0;
      var h = heightCm / 100;
      return weightKg / (h * h);
    },

    /**
     * Fatty Liver Index — Bedogni et al. 2006
     * FLI = e^X / (1 + e^X) × 100
     * X   = 0.953·ln(TG_mmol) + 0.139·BMI + 0.718·ln(GGT) + 0.053·WC − 15.745
     * TG_mmol = TG_mgdl / 88.57
     *
     * @returns {number|null}  null when any input is zero/invalid
     */
    fli: function (tg_mgdl, bmi, ggt, waist) {
      if (tg_mgdl <= 0 || bmi <= 0 || ggt <= 0 || waist <= 0) return null;
      var tg_mmol  = tg_mgdl / 88.57;
      var exponent = (0.953 * Math.log(tg_mmol))
                   + (0.139 * bmi)
                   + (0.718 * Math.log(ggt))
                   + (0.053 * waist)
                   - 15.745;
      var fli = (Math.exp(exponent) / (1 + Math.exp(exponent))) * 100;
      return Math.min(100, Math.max(0, fli));
    },

    /**
     * Weighted lifestyle risk score (0–16)
     * See class-signteb-ajax.php for full rubric.
     */
    lifestyleScore: function (bmi, waist, gender, diabetes, activity, diet, alcohol) {
      var s = 0;
      if      (bmi >= 30) s += 4;
      else if (bmi >= 25) s += 2;
      if (gender === 'male') {
        if      (waist > 102) s += 4;
        else if (waist >= 94) s += 2;
      } else {
        if      (waist > 88) s += 4;
        else if (waist >= 80) s += 2;
      }
      s += Math.min(2, parseInt(diabetes, 10) || 0);
      s += Math.min(2, parseInt(activity, 10) || 0);
      s += Math.min(2, parseInt(diet,     10) || 0);
      s += Math.min(2, parseInt(alcohol,  10) || 0);
      return s;
    },

    /** Map FLI → grade string */
    fliGrade: function (fli) {
      if (fli < 30) return 'grade_0';
      if (fli < 60) return 'grade_1';
      if (fli < 80) return 'grade_2';
      return 'grade_3';
    },

    /** Map lifestyle score → grade string */
    lifestyleGrade: function (score) {
      if (score <= 4) return 'grade_1';
      if (score <= 9) return 'grade_2';
      return 'grade_3';
    },

    /** BMI category label + color */
    bmiCategory: function (bmi) {
      if (bmi < 18.5) return { label: 'کم‌وزن',      color: '#60a5fa' };
      if (bmi < 25)   return { label: 'طبیعی',       color: '#10b981' };
      if (bmi < 30)   return { label: 'اضافه‌وزن',   color: '#f59e0b' };
      if (bmi < 35)   return { label: 'چاقی درجه ۱', color: '#f97316' };
      return               { label: 'چاقی شدید',    color: '#dc2626' };
    },

    /** Colour for a given grade */
    gradeColor: function (grade) {
      return {
        grade_0: '#10b981',
        grade_1: '#f59e0b',
        grade_2: '#f97316',
        grade_3: '#dc2626',
      }[grade] || '#f59e0b';
    },

    /** Human-readable grade label */
    gradeLabel: function (grade) {
      return {
        grade_0: 'Grade 0 — طبیعی',
        grade_1: 'Grade 1 — خفیف',
        grade_2: 'Grade 2 — متوسط',
        grade_3: 'Grade 3 — شدید',
      }[grade] || grade;
    },
  };

  /* ══════════════════════════════════════════════════════════════════════════
   * UI — DOM helpers
   * ══════════════════════════════════════════════════════════════════════════ */
  var UI = {

    /* ── Tab switching ──────────────────────────────────────────────────── */
    switchTab: function (tab) {
      STATE.activeTab = tab;
      $('.st-tab-btn').each(function () {
        var isActive = $(this).data('tab') === tab;
        $(this).toggleClass('active', isActive)
               .attr('aria-selected', isActive ? 'true' : 'false');
      });
      $('.st-tab-pane').removeClass('active');
      $('#st-tab-' + tab).addClass('active');
    },

    /* ── BMI widget ─────────────────────────────────────────────────────── */
    updateBmi: function (prefix) {
      // prefix = 'st' (clinical) or 'ls' (lifestyle)
      var h   = parseFloat($('#' + prefix + '-height').val()) || 0;
      var w   = parseFloat($('#' + prefix + '-weight').val()) || 0;
      var bmi = ENGINE.bmi(h, w);

      if (bmi > 0) {
        var cat = ENGINE.bmiCategory(bmi);
        $('#' + prefix + '-bmi-display').text(bmi.toFixed(1));
        $('#' + prefix + '-bmi-cat').text(cat.label).css('color', cat.color);
        $('#' + prefix + '-bmi-val').val(bmi.toFixed(2));

        // BMI bar: scale 15–45 to 0–100%
        var pct = Math.min(100, Math.max(0, ((bmi - 15) / 30) * 100));
        $('#' + prefix + '-bmi-bar').css('width', pct + '%');
        $('#' + prefix + '-bmi-box').slideDown(220);
      } else {
        $('#' + prefix + '-bmi-box').slideUp(200);
      }
    },

    /* ── Live FLI preview bar ───────────────────────────────────────────── */
    updateFliPreview: function () {
      var h     = parseFloat($('#st-height').val())        || 0;
      var w     = parseFloat($('#st-weight').val())        || 0;
      var tg    = parseFloat($('#st-triglycerides').val()) || 0;
      var ggt   = parseFloat($('#st-ggt').val())           || 0;
      var waist = parseFloat($('#st-waist').val())         || 0;
      var bmi   = ENGINE.bmi(h, w);
      var fli   = ENGINE.fli(tg, bmi, ggt, waist);

      if (fli === null) {
        $('#st-fli-preview').slideUp(200);
        return;
      }

      var grade = ENGINE.fliGrade(fli);
      var color = ENGINE.gradeColor(grade);

      $('#st-fli-val').text(fli.toFixed(1));
      $('#st-fli-bar').css({ width: fli.toFixed(1) + '%', background: color });
      $('#st-fli-preview').slideDown(220);
    },

    /* ── Loading overlay ────────────────────────────────────────────────── */
    showLoading: function () {
      $('#st-loading').addClass('visible').css('display', 'flex');
      UI._animateLoadingSteps();
    },
    hideLoading: function () {
      $('#st-loading').removeClass('visible').fadeOut(300);
    },
    _animateLoadingSteps: function () {
      var steps = [
        'دریافت داده‌های بالینی...',
        'محاسبه شاخص FLI (Bedogni et al.)...',
        'تحلیل ریسک‌فاکتورهای متابولیک...',
        'بررسی معیارهای سبک زندگی...',
        'تهیه گزارش پزشکی اختصاصی...',
        'در حال آماده‌سازی نتایج...',
      ];
      var i = 0;
      var $el = $('#st-loader-step');
      var interval = setInterval(function () {
        if (i < steps.length) {
          $el.fadeOut(180, function () {
            $(this).text(steps[i]).fadeIn(180);
          });
          i++;
        } else {
          clearInterval(interval);
        }
      }, 550);
    },

    /* ── Lead gate ──────────────────────────────────────────────────────── */
    showGate: function () {
      $('#st-gate').addClass('visible').css('display', 'flex');
      // Focus first input for accessibility
      setTimeout(function () { $('#st-lead-name').trigger('focus'); }, 420);
    },
    hideGate: function () {
      $('#st-gate').removeClass('visible').fadeOut(300);
    },

    /* ── Results ────────────────────────────────────────────────────────── */
    showResults: function (data) {
      var gi    = data.grade_info;
      var color = gi.color;
      var bmi   = parseFloat(data.bmi) || 0;
      var cat   = ENGINE.bmiCategory(bmi);

      // Header card — VIP severity glass: tint hero by grade
      var rgbMap  = { 0: '16,185,129', 1: '245,158,11', 2: '249,115,22', 3: '220,38,38'  };
      var deepMap = { 0: '5,150,105',  1: '180,120,10', 2: '200,80,15',  3: '159,18,57'  };
      var rgb  = rgbMap[gi.grade_num]  || '245,158,11';
      var deep = deepMap[gi.grade_num] || '180,120,10';
      $('#st-result-header-card')
        .css('--st-result-color', color)
        .css({
          background: 'linear-gradient(135deg, rgba(' + rgb + ',.24) 0%, rgba(' + deep + ',.10) 55%, rgba(255,255,255,.88) 100%)',
          borderColor: 'rgba(' + rgb + ',.4)',
        });
      $('#st-result-name').text(data.name);
      $('#st-result-badge')
        .text('Grade ' + gi.grade_num + ' — ' + gi.label_en)
        .css({
          background: color, color: '#fff', borderRadius: '100px',
          boxShadow: '0 8px 22px rgba(' + rgb + ',.4)',
        });
      $('#st-result-desc').text(gi.desc);

      // Stats
      $('#st-stat-bmi').text(bmi.toFixed(1))
        .css('color', cat.color)
        .closest('.st-stat-card').find('.st-stat-lbl')
        .text('BMI — ' + cat.label);

      if (data.test_type === 'clinical') {
        $('#st-stat-score').text(parseFloat(data.score).toFixed(1) + ' / 100');
        $('#st-stat-score-lbl').text('شاخص FLI');
      } else {
        $('#st-stat-score').text(data.score + ' / 16');
        $('#st-stat-score-lbl').text('امتیاز سبک‌زندگی');
      }

      $('#st-stat-grade').text('گرید ' + gi.grade_num)
        .css('color', color);

      // Gauge
      UI._renderGauge(gi.gauge_pct, color);

      // Recommendations
      UI._renderRecs(data.recommendations);

      // WhatsApp link
      var waNum = (window.signTebConfig && signTebConfig.waNumber) || '989191182649';
      var waMsg = encodeURIComponent(
        'سلام، می‌خواهم مشاوره تخصصی درباره نتایج کبد چرب دریافت کنم.\n' +
        'گرید: ' + gi.label + '\n' +
        'نام: ' + data.name
      );
      $('#st-wa-link').attr('href', 'https://wa.me/' + waNum + '?text=' + waMsg);

      // Show sections
      $('#signteb-form-section').hide();
      $('#st-results').fadeIn(600);
      setTimeout(function () {
        $('#st-wa-cta').fadeIn(500);
      }, 1200);

      // Scroll to results
      $('html, body').animate({
        scrollTop: ($('#st-results').offset().top || 0) - 80
      }, 900);
    },

    /* ── Semi-circle gauge (pure SVG arc — VIP redesign, no Chart.js) ───── */
    _renderGauge: function (pct, color) {
      var fill = document.getElementById('st-gauge-fill');
      if (!fill) return;

      pct = Math.min(100, Math.max(0, pct));
      var LEN = 276.5; // arc path length of "M16 104 A 88 88 0 0 1 192 104"

      // Reset to empty without transition, force reflow, then animate.
      fill.style.transition = 'none';
      fill.setAttribute('stroke', color);
      fill.setAttribute('stroke-dashoffset', LEN);
      void fill.getBoundingClientRect();
      fill.style.transition = 'stroke-dashoffset 1.4s cubic-bezier(.4,0,.2,1)';
      fill.setAttribute('stroke-dashoffset', (LEN * (1 - pct / 100)).toFixed(1));

      // Count-up readout synced with the arc animation.
      var dur = 1400, t0 = null;
      function frame(ts) {
        if (!t0) t0 = ts;
        var p     = Math.min((ts - t0) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        $('#st-gauge-pct').text(toFa(Math.round(pct * eased)) + '%');
        if (p < 1) requestAnimationFrame(frame);
      }
      requestAnimationFrame(frame);
    },

    /* ── Render recommendation panels ──────────────────────────────────── */
    _renderRecs: function (recs) {
      var sections = [
        { key: 'diet',     icon: '🥗', title: 'رژیم غذایی توصیه‌شده',   el: '#st-rec-diet'     },
        { key: 'activity', icon: '🏃', title: 'فعالیت بدنی',             el: '#st-rec-activity' },
        { key: 'medical',  icon: '⚕️', title: 'توصیه‌های پزشکی',         el: '#st-rec-medical'  },
      ];
      sections.forEach(function (sec) {
        var items = (recs && recs[sec.key]) || [];
        var html  = '<h4>' + sec.icon + ' ' + sec.title + '</h4><ul>';
        items.forEach(function (item) {
          html += '<li>' + $('<span>').text(item).html() + '</li>';
        });
        html += '</ul>';
        $(sec.el).html(html);
      });
    },
  };

  /* ══════════════════════════════════════════════════════════════════════════
   * VALIDATE
   * ══════════════════════════════════════════════════════════════════════════ */
  var VALIDATE = {

    /** Highlight a field and optionally set inline error text */
    _setError: function ($el, msg) {
      $el.addClass('st-err');
      if (msg) {
        $el.closest('.st-field').find('.st-field-err').text(msg);
      }
    },
    _clearError: function ($el) {
      $el.removeClass('st-err');
      $el.closest('.st-field').find('.st-field-err').text('');
    },

    /** Validate a numeric input against [min, max] */
    _num: function (selector, min, max, label) {
      var $el  = $(selector);
      var val  = parseFloat($el.val());
      if (isNaN(val) || val < min || val > max) {
        VALIDATE._setError($el, label + ': ' + min + '–' + max);
        return false;
      }
      VALIDATE._clearError($el);
      return true;
    },

    /** Validate a <select>/required value is chosen */
    _req: function (selector, msg) {
      var $el = $(selector);
      if ($el.val() === '' || $el.val() == null) {
        VALIDATE._setError($el, msg);
        return false;
      }
      VALIDATE._clearError($el);
      return true;
    },

    /** Validate a toggle-group (hidden input + .st-togs container) has a selection */
    _opt: function (group, msg) {
      if ($('#' + group).val() === '' || $('#' + group).val() == null) {
        $('#' + group + '-opts').addClass('st-err');
        $('#' + group + '-err').text(msg);
        return false;
      }
      $('#' + group + '-opts').removeClass('st-err');
      $('#' + group + '-err').text('');
      return true;
    },

    /**
     * Soft plausibility warning: value is inside the hard [min,max] range but
     * outside the clinically-common window → warn without blocking submit.
     */
    _warn: function (selector, min, max, msg) {
      var $el    = $(selector);
      var $field = $el.closest('.st-field');
      if (!$field.length) return;
      var $w = $field.find('.st-field-warn');
      if (!$w.length) $w = $('<span class="st-field-warn"></span>').appendTo($field);
      var val = parseFloat($el.val());
      $w.text((!isNaN(val) && (val < min || val > max)) ? msg : '');
    },

    /** Lab-value plausibility checks (FLI inputs: TG, GGT, waist) */
    plausibility: function () {
      VALIDATE._warn('#st-triglycerides', 30, 1000,
        'مقدار تری‌گلیسرید غیرمعمول است — لطفاً با برگه آزمایش مطابقت دهید.');
      VALIDATE._warn('#st-ggt', 5, 500,
        'مقدار GGT غیرمعمول است — لطفاً با برگه آزمایش مطابقت دهید.');
      VALIDATE._warn('#st-waist', 50, 180,
        'دور کمر واردشده غیرمعمول است — اندازه‌گیری را بازبینی کنید.');
    },

    clinical: function () {
      var ok = true;
      VALIDATE.plausibility();
      ok = VALIDATE._num('#st-age',           10,  120,  'سن')           && ok;
      ok = VALIDATE._num('#st-height',        100, 250,  'قد (cm)')       && ok;
      ok = VALIDATE._num('#st-weight',        20,  500,  'وزن (kg)')      && ok;
      ok = VALIDATE._num('#st-waist',         40,  250,  'دور کمر (cm)')  && ok;
      ok = VALIDATE._num('#st-triglycerides', 10,  2000, 'تری‌گلیسرید')   && ok;
      ok = VALIDATE._num('#st-ggt',           1,   5000, 'GGT')           && ok;
      ok = VALIDATE._req('#st-gender', 'انتخاب جنسیت الزامی است.') && ok;
      if (!ok) {
        // Scroll to first error
        var $first = $('#st-tab-clinical .st-err').first();
        if ($first.length) {
          $('html,body').animate({ scrollTop: $first.offset().top - 140 }, 400);
        }
      }
      return ok;
    },

    lifestyle: function () {
      var ok = true;
      ok = VALIDATE._num('#ls-height', 100, 250, 'قد (cm)')      && ok;
      ok = VALIDATE._num('#ls-weight', 20,  500, 'وزن (kg)')     && ok;
      ok = VALIDATE._num('#ls-waist',  40,  250, 'دور کمر (cm)') && ok;
      ok = VALIDATE._req('#ls-gender', 'انتخاب جنسیت الزامی است.') && ok;
      ok = VALIDATE._opt('ls-diabetes', 'وضعیت دیابت را انتخاب کنید.') && ok;
      ok = VALIDATE._opt('ls-activity', 'سطح فعالیت را انتخاب کنید.') && ok;
      ok = VALIDATE._opt('ls-diet',     'کیفیت رژیم غذایی را انتخاب کنید.') && ok;
      ok = VALIDATE._opt('ls-alcohol',  'مصرف الکل را انتخاب کنید.') && ok;
      if (!ok) {
        var $first = $('#st-tab-lifestyle .st-err').first();
        if ($first.length) {
          $('html,body').animate({ scrollTop: $first.offset().top - 140 }, 400);
        }
      }
      return ok;
    },

    gate: function () {
      var ok    = true;
      var name  = $.trim($('#st-lead-name').val());
      var phone = $.trim($('#st-lead-phone').val());

      $('#st-lead-name-err, #st-lead-phone-err').text('');
      $('#st-lead-name, #st-lead-phone').removeClass('st-err');

      if (name.length < 2) {
        $('#st-lead-name-err').text('نام کامل را وارد کنید (حداقل ۲ کاراکتر).');
        $('#st-lead-name').addClass('st-err');
        ok = false;
      }
      if (!/^09\d{9}$/.test(phone)) {
        var errMsg = (window.signTebConfig && signTebConfig.i18n && signTebConfig.i18n.errorPhone)
          || 'شماره موبایل معتبر نیست. فرمت: 09XXXXXXXXX';
        $('#st-lead-phone-err').text(errMsg);
        $('#st-lead-phone').addClass('st-err');
        ok = false;
      }
      return ok;
    },
  };

  /* ══════════════════════════════════════════════════════════════════════════
   * DATA COLLECTION
   * ══════════════════════════════════════════════════════════════════════════ */
  function collectClinical() {
    var h     = parseFloat($('#st-height').val())        || 0;
    var w     = parseFloat($('#st-weight').val())        || 0;
    var tg    = parseFloat($('#st-triglycerides').val()) || 0;
    var ggt   = parseFloat($('#st-ggt').val())           || 0;
    var waist = parseFloat($('#st-waist').val())         || 0;
    var bmi   = ENGINE.bmi(h, w);
    var fli   = ENGINE.fli(tg, bmi, ggt, waist);

    return {
      test_type     : 'clinical',
      age           : $('#st-age').val(),
      gender        : $('#st-gender').val(),
      height        : h,
      weight        : w,
      bmi           : bmi.toFixed(2),
      waist         : waist,
      triglycerides : tg,
      ggt           : ggt,
      _fli          : fli,
      _grade        : ENGINE.fliGrade(fli || 0),
    };
  }

  function collectLifestyle() {
    var h      = parseFloat($('#ls-height').val()) || 0;
    var w      = parseFloat($('#ls-weight').val()) || 0;
    var waist  = parseFloat($('#ls-waist').val())  || 0;
    var gender = $('#ls-gender').val();
    var diab   = parseInt($('#ls-diabetes').val(), 10) || 0;
    var act    = parseInt($('#ls-activity').val(), 10) || 0;
    var diet   = parseInt($('#ls-diet').val(),     10) || 0;
    var alc    = parseInt($('#ls-alcohol').val(),  10) || 0;
    var bmi    = ENGINE.bmi(h, w);
    var score  = ENGINE.lifestyleScore(bmi, waist, gender, diab, act, diet, alc);

    return {
      test_type   : 'lifestyle',
      ls_height   : h,
      ls_weight   : w,
      ls_bmi      : bmi.toFixed(2),
      ls_gender   : gender,
      ls_waist    : waist,
      ls_diabetes : diab,
      ls_activity : act,
      ls_diet     : diet,
      ls_alcohol  : alc,
      _score      : score,
      _grade      : ENGINE.lifestyleGrade(score),
    };
  }

  /* ══════════════════════════════════════════════════════════════════════════
   * AJAX
   * ══════════════════════════════════════════════════════════════════════════ */
  var AJAX = {
    sendOtp: function (phone) {
      var cfg      = window.signTebConfig || {};
      var $btn     = $('#st-gate-submit');
      var $btnText = $('#st-gate-submit-text');
      var origText = $btnText.text();

      $btn.prop('disabled', true);
      $btnText.text('در حال ارسال کد...');
      $('#st-gate-server-err').text('');

      $.ajax({
        url  : cfg.ajaxUrl || '/wp-admin/admin-ajax.php',
        type : 'POST',
        data : { action: 'signteb_send_otp', nonce: cfg.nonce || '', phone: phone },
        success: function (res) {
          if (res && res.success) {
            STATE.otpSent = true;
            $('#st-otp-field').slideDown(200);
            $('#st-otp-sent-to').text('کد تأیید به شماره ' + phone + ' ارسال شد.');
            $('#st-lead-phone').prop('readonly', true);
            $btnText.text('تأیید و مشاهده نتایج');
            setTimeout(function () { $('#st-lead-otp').trigger('focus'); }, 250);
            startOtpCountdown();
          } else {
            $('#st-gate-server-err').text((res && res.data && res.data.message) || 'خطا در ارسال کد تأیید.');
          }
        },
        error: function (xhr) {
          var msg = 'خطا در ارسال کد تأیید.';
          if (xhr.status === 429) msg = 'تعداد درخواست زیاد است. کمی صبر کنید.';
          try { msg = JSON.parse(xhr.responseText).data.message || msg; } catch (e) {}
          $('#st-gate-server-err').text(msg);
        },
        complete: function () {
          $btn.prop('disabled', false);
          if (!STATE.otpSent) $btnText.text(origText);
        },
      });
    },

    submit: function (leadData, calcData) {
      if (STATE.submitted) return;
      STATE.submitted = true;

      var cfg      = window.signTebConfig || {};
      var $btn     = $('#st-gate-submit');
      var $btnText = $('#st-gate-submit-text');
      var origText = $btnText.text();

      $btn.prop('disabled', true);
      $btnText.text((cfg.i18n && cfg.i18n.submitting) || 'در حال ارسال...');
      $('#st-gate-server-err').text('');

      // Flatten calcData + leadData into POST body
      var postData = $.extend({}, calcData, {
        action    : 'signteb_save_lead',
        nonce     : cfg.nonce || '',
        full_name : leadData.name,
        phone     : leadData.phone,
        otp       : $.trim($('#st-lead-otp').val() || ''),
      });

      $.ajax({
        url      : cfg.ajaxUrl || '/wp-admin/admin-ajax.php',
        type     : 'POST',
        data     : postData,
        success  : function (res) {
          if (res && res.success && res.data && res.data.result) {
            bumpEvalCount();
            UI.hideGate();
            UI.showResults(res.data.result);
          } else {
            var msg = (res && res.data && res.data.message)
              || (cfg.i18n && cfg.i18n.errorServer)
              || 'خطا در سرور.';
            $('#st-gate-server-err').text(msg);
            STATE.submitted = false;
          }
        },
        error: function (xhr) {
          var msg = (cfg.i18n && cfg.i18n.errorServer)
            || 'خطا در ارتباط با سرور.';
          if (xhr.status === 403) msg = 'خطای امنیتی. صفحه را رفرش کنید.';
          if (xhr.status === 422) {
            try { msg = JSON.parse(xhr.responseText).data.message; } catch (e) {}
          }
          $('#st-gate-server-err').text(msg);
          STATE.submitted = false;
        },
        complete: function () {
          $btn.prop('disabled', false);
          $btnText.text(origText);
        },
      });
    },
  };

  /* ══════════════════════════════════════════════════════════════════════════
   * FLOW HANDLERS
   * ══════════════════════════════════════════════════════════════════════════ */
  function handleCalculate(tab) {
    var valid    = (tab === 'clinical') ? VALIDATE.clinical() : VALIDATE.lifestyle();
    if (!valid) return;

    STATE.calcData  = (tab === 'clinical') ? collectClinical() : collectLifestyle();
    STATE.submitted = false;

    // Hide form → short delay → loading → gate
    $('#signteb-form-section').fadeOut(280, function () {
      UI.showLoading();
      setTimeout(function () {
        UI.hideLoading();
        setTimeout(function () {
          UI.showGate();
        }, 320);
      }, 3400);
    });
  }

  function toFa(n) {
    return String(n).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
  }

  var otpTimer = null;
  function startOtpCountdown() {
    var t    = 90;
    var $btn = $('#st-otp-resend');
    var $tm  = $('#st-otp-timer');
    $btn.prop('disabled', true);
    $tm.text(toFa(t));
    clearInterval(otpTimer);
    otpTimer = setInterval(function () {
      t -= 1;
      if (t <= 0) {
        clearInterval(otpTimer);
        $btn.prop('disabled', false).text('ارسال مجدد کد');
      } else {
        $tm.text(toFa(t));
      }
    }, 1000);
  }

  function handleGateSubmit() {
    if (!VALIDATE.gate()) return;
    var cfg   = window.signTebConfig || {};
    var name  = $.trim($('#st-lead-name').val());
    var phone = $.trim($('#st-lead-phone').val());

    // OTP enabled & not yet verified → send the code first.
    if (cfg.otpEnabled && !STATE.otpSent) {
      AJAX.sendOtp(phone);
      return;
    }
    // OTP enabled & code shown → require a code before submitting.
    if (cfg.otpEnabled && STATE.otpSent) {
      var code = $.trim($('#st-lead-otp').val());
      if (!/^\d{4,6}$/.test(code)) {
        $('#st-lead-otp-err').text('کد تأیید را وارد کنید.');
        return;
      }
      $('#st-lead-otp-err').text('');
    }
    AJAX.submit({ name: name, phone: phone }, STATE.calcData);
  }

  function handleRecalc() {
    $('#st-results').hide();
    $('#st-wa-cta').hide().removeClass('st-hub-open');
    $('#st-hub-toggle').attr('aria-expanded', 'false');
    STATE.calcData  = null;
    STATE.submitted = false;
    STATE.otpSent   = false;
    clearInterval(otpTimer);
    $('#st-otp-field').hide();
    $('#st-lead-otp').val('');
    $('#st-lead-phone').prop('readonly', false);
    $('#st-gate-submit-text').text('مشاهده نتایج کامل — رایگان');
    $('#signteb-form-section').fadeIn(400);
    $('html,body').animate({
      scrollTop: ($('#signteb-calc-wrapper').offset().top || 0) - 40
    }, 500);
  }

  function handleGateClose() {
    UI.hideGate();
    $('#signteb-form-section').fadeIn(380);
  }

  /* ── Dynamic “assessments completed” counter ─────────────────────────── */
  var evalTarget = 0;
  function initEvalCount() {
    var $el = $('#st-eval-count');
    if (!$el.length) return;
    var cfg    = window.signTebConfig || {};
    var target = parseInt(cfg.evalCount, 10);
    if (!target || target < 1) {
      target = parseInt(localStorage.getItem('signteb_eval_count'), 10) || 1247;
    }
    evalTarget = target;
    try { localStorage.setItem('signteb_eval_count', target); } catch (e) {}
    var from = Math.max(0, Math.round(target * 0.86));
    var dur  = 1400, t0 = null;
    function frame(ts) {
      if (!t0) t0 = ts;
      var p     = Math.min((ts - t0) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      $el.text('+' + toFa(Math.round(from + (target - from) * eased)));
      if (p < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }
  function bumpEvalCount() {
    evalTarget += 1;
    var $el = $('#st-eval-count');
    if ($el.length) $el.text('+' + toFa(evalTarget));
    try { localStorage.setItem('signteb_eval_count', evalTarget); } catch (e) {}
  }

  /* ══════════════════════════════════════════════════════════════════════════
   * UTILITY
   * ══════════════════════════════════════════════════════════════════════════ */
  function debounce(fn, delay) {
    var t;
    return function () {
      clearTimeout(t);
      t = setTimeout(fn.bind(this, arguments), delay);
    };
  }

  /* ══════════════════════════════════════════════════════════════════════════
   * EVENT DELEGATION (single point of truth)
   * ══════════════════════════════════════════════════════════════════════════ */
  function bindEvents() {
    var $doc = $(document);

    /* ── Tab switching ─────────────────────────────────────────────────── */
    $doc.on('click', '.st-tab-btn', function () {
      UI.switchTab($(this).data('tab'));
    });

    /* ── BMI auto-calc — Clinical ──────────────────────────────────────── */
    $doc.on('input', '#st-height, #st-weight', function () {
      UI.updateBmi('st');
      UI.updateFliPreview();
    });

    /* ── BMI auto-calc — Lifestyle ─────────────────────────────────────── */
    $doc.on('input', '#ls-height, #ls-weight', function () {
      UI.updateBmi('ls');
    });

    /* ── Live FLI preview + lab plausibility warnings ──────────────────── */
    $doc.on('input', '#st-triglycerides, #st-ggt, #st-waist',
      debounce(function () {
        UI.updateFliPreview();
        VALIDATE.plausibility();
      }, 380));

    /* ── Connect hub toggle ────────────────────────────────────────────── */
    $doc.on('click', '#st-hub-toggle', function () {
      var open = $('#st-wa-cta').toggleClass('st-hub-open').hasClass('st-hub-open');
      $(this).attr('aria-expanded', open ? 'true' : 'false');
    });

    /* ── Option card selection (lifestyle) ─────────────────────────────── */
    $doc.on('click', '.st-opt', function () {
      var $opt   = $(this);
      var group  = $opt.data('group');
      var val    = $opt.data('val');
      $opt.closest('.st-togs')
          .find('.st-opt').removeClass('active').attr('aria-pressed', 'false');
      $opt.addClass('active').attr('aria-pressed', 'true');
      $('#' + group).val(val);
      // clear the “please choose” error for this group once a choice is made
      $('#' + group + '-opts').removeClass('st-err');
      $('#' + group + '-err').text('');
    });

    /* ── Calculate buttons ─────────────────────────────────────────────── */
    $doc.on('click', '#st-calc-clinical',  function () { handleCalculate('clinical');  });
    $doc.on('click', '#st-calc-lifestyle', function () { handleCalculate('lifestyle'); });

    /* ── Gate submit / close ───────────────────────────────────────────── */
    $doc.on('click', '#st-gate-submit', handleGateSubmit);
    $doc.on('click', '#st-gate-close',  handleGateClose);
    $doc.on('click', '#st-otp-resend',  function () {
      if ($(this).prop('disabled')) return;
      AJAX.sendOtp($.trim($('#st-lead-phone').val()));
    });

    /* ── Close gate on backdrop click ──────────────────────────────────── */
    $doc.on('click', '.st-gate-backdrop', handleGateClose);

    /* ── Recalculate ───────────────────────────────────────────────────── */
    $doc.on('click', '#st-recalc-btn', handleRecalc);

    /* ── Phone: enforce numeric + max length ───────────────────────────── */
    $doc.on('input', '#st-lead-phone', function () {
      var v = $(this).val().replace(/[^0-9]/g, '').substring(0, 11);
      $(this).val(v);
    });

    /* ── Keyboard: Enter on gate fields ────────────────────────────────── */
    $doc.on('keydown', '#st-lead-name, #st-lead-phone', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); handleGateSubmit(); }
    });

    /* ── Escape closes gate / loading ──────────────────────────────────── */
    $doc.on('keydown', function (e) {
      if (e.key === 'Escape') {
        if ($('#st-gate').hasClass('visible'))   handleGateClose();
        if ($('#st-loading').hasClass('visible')) {
          UI.hideLoading();
          $('#signteb-form-section').fadeIn(380);
        }
      }
    });
  }

  /* ══════════════════════════════════════════════════════════════════════════
   * INIT
   * ══════════════════════════════════════════════════════════════════════════ */
  $(function () {
    if ($('#signteb-calc-wrapper').length === 0) return; // shortcode not on this page

    UI.switchTab('clinical');
    bindEvents();
    initEvalCount();
  });

})(jQuery);

/* ── Accordion toggle (appended) ─────────────────────────────── */
/* Wrapped in a jQuery closure: outside the main IIFE the bare `$` is
   undefined under WordPress noConflict, so the handler never bound. */
jQuery(function ($) {
  $(document).on('click', '.st-acc-hd', function () {
    var $hd   = $(this);
    var $body = $hd.siblings('.st-acc-body');
    var $icon = $hd.find('.st-acc-arrow');
    var open  = $body.hasClass('st-acc-body-open');
    $body.toggleClass('st-acc-body-open', !open);
    $icon.toggleClass('st-acc-open', !open);
    $hd.attr('aria-expanded', !open);
  });
});
