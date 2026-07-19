<?php
/**
 * AJAX handler — processes lead submission, performs server-side validation,
 * recalculates FLI / lifestyle score, persists the lead, and returns the
 * full result payload to the front-end.
 *
 * Fatty Liver Index formula (Bedogni et al., 2006):
 *   FLI = e^X / (1 + e^X) × 100
 *   where X = 0.953·ln(TG_mmol) + 0.139·BMI + 0.718·ln(GGT) + 0.053·WC − 15.745
 *
 *   TG_mmol = TG_mgdl / 88.57
 *
 * FLI interpretation:
 *   < 30  → Grade 0 – steatosis excluded
 *   30–59 → Grade 1 – mild / inconclusive
 *   60–79 → Grade 2 – moderate steatosis
 *   ≥ 80  → Grade 3 – severe steatosis
 *
 * @package SignTeb_Fatty_Liver_Calculator
 */
defined( 'ABSPATH' ) || exit;

class SignTeb_Liver_Ajax {

    public function __construct() {
        // Both logged-in and guest users can submit
        add_action( 'wp_ajax_signteb_save_lead',        [ $this, 'handle_lead' ] );
        add_action( 'wp_ajax_nopriv_signteb_save_lead', [ $this, 'handle_lead' ] );
        // OTP (SMS verification) — send code before unlocking results
        add_action( 'wp_ajax_signteb_send_otp',         [ $this, 'handle_send_otp' ] );
        add_action( 'wp_ajax_nopriv_signteb_send_otp',  [ $this, 'handle_send_otp' ] );
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * PUBLIC HANDLER
     * ═══════════════════════════════════════════════════════════════════════ */

    public function handle_lead() {

        /* 1 ── Nonce verification ─────────────────────────────────────────── */
        check_ajax_referer( 'signteb_liver_nonce', 'nonce' );

        /* 2 ── Sanitise & validate lead fields ───────────────────────────── */
        $full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
        $phone     = sanitize_text_field( wp_unslash( $_POST['phone']     ?? '' ) );
        $test_type = sanitize_key(        wp_unslash( $_POST['test_type'] ?? 'clinical' ) );

        if ( strlen( $full_name ) < 2 ) {
            wp_send_json_error( [ 'message' => 'نام کامل را وارد کنید.' ], 422 );
        }

        if ( ! preg_match( '/^09\d{9}$/', $phone ) ) {
            wp_send_json_error( [ 'message' => 'شماره موبایل معتبر نیست (فرمت: 09XXXXXXXXX).' ], 422 );
        }

        /* 2b ── OTP verification (only when SMS verification is enabled) ──── */
        if ( '1' === get_option( 'signteb_liver_sms_enabled', '0' ) ) {
            $otp      = preg_replace( '/[^0-9]/', '', (string) wp_unslash( $_POST['otp'] ?? '' ) );
            $expected = get_transient( 'signteb_otp_' . $phone );
            if ( ! $expected || ! hash_equals( (string) $expected, (string) $otp ) ) {
                wp_send_json_error( [ 'message' => 'کد تأیید نادرست یا منقضی شده است.' ], 422 );
            }
            delete_transient( 'signteb_otp_' . $phone );
        }

        if ( ! in_array( $test_type, [ 'clinical', 'lifestyle' ], true ) ) {
            $test_type = 'clinical';
        }

        /* 3 ── Branch: clinical vs lifestyle ─────────────────────────────── */
        if ( 'clinical' === $test_type ) {
            [ $metrics, $score, $grade ] = $this->process_clinical();
        } else {
            [ $metrics, $score, $grade ] = $this->process_lifestyle();
        }

        // process_* methods call wp_send_json_error() internally on failure,
        // so if we reach here the data is valid.

        /* 4 ── Persist lead ───────────────────────────────────────────────── */
        $lead_id = SignTeb_Liver_DB::insert_lead( [
            'full_name' => $full_name,
            'phone'     => $phone,
            'test_type' => $test_type,
            'metrics'   => $metrics,
            'score'     => $score,
            'grade'     => $grade,
        ] );

        if ( false === $lead_id ) {
            wp_send_json_error( [ 'message' => 'خطا در ذخیره اطلاعات. لطفاً دوباره تلاش کنید.' ], 500 );
        }

        /* 5 ── Admin notification e-mail ─────────────────────────────────── */
        $this->send_notification( $lead_id, $full_name, $phone, $test_type, $score, $grade );

        /* 6 ── Build & return result payload ─────────────────────────────── */
        $payload = $this->build_result_payload( $test_type, $score, $grade, $metrics, $full_name );

        wp_send_json_success( [
            'lead_id' => $lead_id,
            'result'  => $payload,
        ] );
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * LAYER A — CLINICAL (FLI)
     * ═══════════════════════════════════════════════════════════════════════ */

    /**
     * Validate, sanitise and compute FLI from clinical inputs.
     *
     * @return array  [ metrics[], score(float), grade(string) ]
     */
    private function process_clinical() {
        $age    = absint(   $_POST['age']            ?? 0  );
        $gender = sanitize_key( wp_unslash( $_POST['gender'] ?? 'male' ) );
        $height = (float) ( $_POST['height']         ?? 0  );
        $weight = (float) ( $_POST['weight']         ?? 0  );
        $waist  = (float) ( $_POST['waist']          ?? 0  );
        $trig   = (float) ( $_POST['triglycerides']  ?? 0  );
        $ggt    = (float) ( $_POST['ggt']            ?? 0  );

        /* Server-side range validation (defence in depth) */
        $errors = [];
        if ( $age < 10 || $age > 120 )           $errors[] = 'سن';
        if ( $height < 100 || $height > 250 )    $errors[] = 'قد';
        if ( $weight < 20  || $weight > 500 )    $errors[] = 'وزن';
        if ( $waist  < 40  || $waist  > 250 )    $errors[] = 'دور کمر';
        if ( $trig   < 10  || $trig   > 2000 )   $errors[] = 'تری‌گلیسرید';
        if ( $ggt    < 1   || $ggt    > 5000 )   $errors[] = 'GGT';

        if ( ! empty( $errors ) ) {
            wp_send_json_error( [
                'message' => 'مقادیر نامعتبر: ' . implode( '، ', $errors ),
            ], 422 );
        }

        $gender = in_array( $gender, [ 'male', 'female' ], true ) ? $gender : 'male';

        /* BMI */
        $bmi = $weight / ( ( $height / 100 ) ** 2 );

        /* FLI — Bedogni et al. 2006 */
        $tg_mmol  = $trig / 88.57;
        $exponent = ( 0.953 * log( $tg_mmol ) )
                  + ( 0.139 * $bmi )
                  + ( 0.718 * log( $ggt ) )
                  + ( 0.053 * $waist )
                  - 15.745;
        $fli      = ( exp( $exponent ) / ( 1 + exp( $exponent ) ) ) * 100;
        $fli      = min( 100, max( 0, round( $fli, 2 ) ) );

        $grade   = $this->fli_to_grade( $fli );
        $metrics = [
            'age'           => $age,
            'gender'        => $gender,
            'height'        => $height,
            'weight'        => $weight,
            'bmi'           => round( $bmi, 2 ),
            'waist'         => $waist,
            'triglycerides' => $trig,
            'ggt'           => $ggt,
        ];

        return [ $metrics, $fli, $grade ];
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * LAYER B — LIFESTYLE SCORING
     * ═══════════════════════════════════════════════════════════════════════ */

    /**
     * Validate, sanitise and compute weighted lifestyle score.
     *
     * Scoring rubric (max = 16):
     *  BMI          : <25 → 0 | 25–29.9 → 2 | ≥30 → 4
     *  Waist (M/F)  : normal → 0 | borderline → 2 | high → 4
     *  Diabetes     : no → 0 | pre-diabetes → 1 | T2DM → 2
     *  Activity     : active → 0 | moderate → 1 | sedentary → 2
     *  Diet         : healthy → 0 | mixed → 1 | unhealthy → 2
     *  Alcohol      : none → 0 | occasional → 1 | regular → 2
     *
     * Grade mapping:
     *  0–4  → grade_1 (low risk)
     *  5–9  → grade_2 (moderate risk)
     *  10+  → grade_3 (high risk)
     *
     * @return array  [ metrics[], score(int), grade(string) ]
     */
    private function process_lifestyle() {
        $height   = (float) ( $_POST['ls_height']   ?? 0 );
        $weight   = (float) ( $_POST['ls_weight']   ?? 0 );
        $waist    = (float) ( $_POST['ls_waist']    ?? 0 );
        $gender   = sanitize_key( wp_unslash( $_POST['ls_gender']   ?? 'male' ) );
        $diabetes = min( 2, absint( $_POST['ls_diabetes'] ?? 0 ) );
        $activity = min( 2, absint( $_POST['ls_activity'] ?? 0 ) );
        $diet     = min( 2, absint( $_POST['ls_diet']     ?? 0 ) );
        $alcohol  = min( 2, absint( $_POST['ls_alcohol']  ?? 0 ) );

        if ( $height < 100 || $height > 250 ||
             $weight < 20  || $weight > 500 ||
             $waist  < 40  || $waist  > 250 ) {
            wp_send_json_error( [ 'message' => 'مقادیر اندازه‌گیری معتبر نیستند.' ], 422 );
        }

        $gender = in_array( $gender, [ 'male', 'female' ], true ) ? $gender : 'male';
        $bmi    = $weight / ( ( $height / 100 ) ** 2 );
        $score  = $this->calc_lifestyle_score( $bmi, $waist, $gender, $diabetes, $activity, $diet, $alcohol );
        $grade  = $this->lifestyle_to_grade( $score );

        $metrics = [
            'height'   => $height,
            'weight'   => $weight,
            'bmi'      => round( $bmi, 2 ),
            'gender'   => $gender,
            'waist'    => $waist,
            'diabetes' => $diabetes,
            'activity' => $activity,
            'diet'     => $diet,
            'alcohol'  => $alcohol,
        ];

        return [ $metrics, $score, $grade ];
    }

    /* ── Internal calculators ─────────────────────────────────────────────── */

    private function fli_to_grade( float $fli ) {
        if ( $fli < 30 ) return 'grade_0';
        if ( $fli < 60 ) return 'grade_1';
        if ( $fli < 80 ) return 'grade_2';
        return 'grade_3';
    }

    private function calc_lifestyle_score( float $bmi, float $waist, string $gender,
                                           int $diabetes, int $activity, int $diet, int $alcohol ) {
        $score = 0;

        // BMI
        if      ( $bmi >= 30 ) $score += 4;
        elseif  ( $bmi >= 25 ) $score += 2;

        // Waist — IDF thresholds
        if ( 'male' === $gender ) {
            if      ( $waist > 102 ) $score += 4;
            elseif  ( $waist >= 94 ) $score += 2;
        } else {
            if      ( $waist > 88 )  $score += 4;
            elseif  ( $waist >= 80 ) $score += 2;
        }

        $score += $diabetes + $activity + $diet + $alcohol;
        return $score;
    }

    private function lifestyle_to_grade( int $score ) {
        if ( $score <= 4 ) return 'grade_1';
        if ( $score <= 9 ) return 'grade_2';
        return 'grade_3';
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * RESULT PAYLOAD BUILDER
     * ═══════════════════════════════════════════════════════════════════════ */

    private function build_result_payload( string $type, float $score,
                                           string $grade, array $metrics, string $name ) {
        static $grades = [
            'grade_0' => [
                'label'      => 'طبیعی — بدون کبد چرب',
                'label_en'   => 'Normal',
                'grade_num'  => 0,
                'color'      => '#10b981',
                'risk'       => 'پایین',
                'gauge_pct'  => 8,
                'desc'       => 'شاخص‌های شما در محدوده طبیعی قرار دارند. احتمال ابتلا به کبد چرب بسیار پایین است. با ادامه سبک زندگی سالم، کبد خود را در وضعیت ایده‌آل نگه دارید.',
            ],
            'grade_1' => [
                'label'      => 'کبد چرب خفیف — گرید ۱',
                'label_en'   => 'Mild Fatty Liver',
                'grade_num'  => 1,
                'color'      => '#f59e0b',
                'risk'       => 'متوسط',
                'gauge_pct'  => 33,
                'desc'       => 'شاخص‌های شما احتمال کبد چرب خفیف را نشان می‌دهند. این مرحله کاملاً برگشت‌پذیر است. با تغییر رژیم غذایی، افزایش فعالیت بدنی و مشاوره پزشکی، بهبود قابل توجهی حاصل خواهد شد.',
            ],
            'grade_2' => [
                'label'      => 'کبد چرب متوسط — گرید ۲',
                'label_en'   => 'Moderate Fatty Liver',
                'grade_num'  => 2,
                'color'      => '#f97316',
                'risk'       => 'بالا',
                'gauge_pct'  => 63,
                'desc'       => 'شاخص‌های شما در محدوده کبد چرب متوسط است. پیگیری پزشکی، تغییر جدی رژیم غذایی و شروع برنامه ورزشی منظم ضروری است. مراجعه به متخصص گوارش توصیه می‌شود.',
            ],
            'grade_3' => [
                'label'      => 'کبد چرب شدید — گرید ۳',
                'label_en'   => 'Severe Fatty Liver',
                'grade_num'  => 3,
                'color'      => '#dc2626',
                'risk'       => 'بسیار بالا',
                'gauge_pct'  => 90,
                'desc'       => '⚠️ شاخص‌های شما در سطح هشدار قرار دارند. مراجعه فوری به متخصص هپاتولوژی (کبد) و انجام ارزیابی‌های تکمیلی (سونوگرافی، آنزیم‌های کبدی، Fibroscan) ضروری است.',
            ],
        ];

        $gi   = $grades[ $grade ] ?? $grades['grade_1'];
        $recs = $this->get_recommendations( $grade, $metrics );
        $bmi  = $metrics['bmi'] ?? 0;

        return [
            'name'            => $name,
            'test_type'       => $type,
            'score'           => $score,
            'grade'           => $grade,
            'grade_info'      => $gi,
            'bmi'             => $bmi,
            'recommendations' => $recs,
        ];
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * PERSONALISED RECOMMENDATIONS
     * ═══════════════════════════════════════════════════════════════════════ */

    private function get_recommendations( string $grade, array $metrics ) {
        $recs = [ 'diet' => [], 'activity' => [], 'medical' => [] ];

        switch ( $grade ) {
            case 'grade_0':
                $recs['diet']     = [
                    'رژیم مدیترانه‌ای را ادامه دهید: سبزیجات، ماهی، روغن زیتون.',
                    'روزانه ۸ لیوان آب بنوشید و از نوشیدنی‌های قندی پرهیز کنید.',
                    'مصرف فیبر را با غلات کامل و حبوبات افزایش دهید.',
                ];
                $recs['activity'] = [
                    'حداقل ۱۵۰ دقیقه فعالیت هوازی در هفته را حفظ کنید.',
                    'تمرینات قدرتی ۲–۳ بار در هفته انجام دهید.',
                ];
                $recs['medical']  = [
                    'چکاپ سالانه کبد و آزمایش آنزیم‌های کبدی را فراموش نکنید.',
                    'سونوگرافی شکمی هر ۲–۳ سال انجام دهید.',
                ];
                break;

            case 'grade_1':
                $recs['diet']     = [
                    'مصرف کربوهیدرات ساده (قند، شیرینی، نوشابه) را به شدت کاهش دهید.',
                    'چربی‌های اشباع را با روغن زیتون و آووکادو جایگزین کنید.',
                    'پروتئین بدون چربی (مرغ، ماهی، حبوبات) را افزایش دهید.',
                    'از مصرف الکل و فست‌فود کاملاً خودداری کنید.',
                    'قهوه سیاه بدون شکر (۲–۳ فنجان روزانه) برای کبد مفید است.',
                ];
                $recs['activity'] = [
                    'فعالیت هوازی را به ۱۸۰–۲۰۰ دقیقه در هفته افزایش دهید.',
                    'پیاده‌روی ۳۰–۴۵ دقیقه‌ای روزانه را شروع کنید.',
                    'از نشستن بیش از ۲ ساعت متوالی خودداری کنید.',
                ];
                $recs['medical']  = [
                    'مشاوره با متخصص تغذیه برای برنامه رژیمی اختصاصی.',
                    'آزمایش کامل کبدی (AST، ALT، ALP) هر ۶ ماه.',
                    'سونوگرافی شکمی جهت ارزیابی دقیق‌تر کبد.',
                ];
                break;

            case 'grade_2':
                $recs['diet']     = [
                    'رژیم کم‌کالری (کاهش ۵۰۰–۱۰۰۰ کیلوکالری از نیاز روزانه) زیر نظر متخصص.',
                    'فروکتوز و شکر افزوده را کاملاً حذف کنید.',
                    'غذاهای فراوری‌شده و فست‌فود را از رژیم حذف کنید.',
                    'مکمل ویتامین E (پس از تجویز پزشک) می‌تواند مفید باشد.',
                    'مصرف امگا-۳ از طریق ماهی چرب یا مکمل (با تأیید پزشک).',
                ];
                $recs['activity'] = [
                    'هدف: کاهش ۵–۱۰٪ وزن بدن در ۶ ماه.',
                    'ترکیب تمرین هوازی و مقاومتی ۲۵۰+ دقیقه در هفته.',
                    'مشاوره با مربی ورزشی برای برنامه اختصاصی.',
                ];
                $recs['medical']  = [
                    '🔶 مراجعه به متخصص گوارش برای ارزیابی تخصصی ضروری است.',
                    'سونوگرافی کبد و آزمایشات کامل (CBC، LFT، FBS، Lipid).',
                    'بررسی مقاومت به انسولین و قند خون ناشتا.',
                    'Fibroscan جهت ارزیابی درجه فیبروز احتمالی کبد.',
                ];
                break;

            case 'grade_3':
            default:
                $recs['diet']     = [
                    '⛔ رژیم غذایی سخت‌گیرانه فقط زیر نظر متخصص تغذیه.',
                    'حذف کامل الکل، قند، چربی‌های ترانس و غذاهای فراوری‌شده.',
                    'توت‌فرنگی، بلوبری، اسفناج و بروکلی را روزانه مصرف کنید.',
                    'قهوه سیاه بدون شکر (اثر محافظتی بر کبد دارد).',
                ];
                $recs['activity'] = [
                    '⛔ شروع ورزش فقط پس از تأیید پزشک.',
                    'کاهش تدریجی وزن تحت نظارت تیم پزشکی.',
                    'پیاده‌روی سبک به عنوان شروع، با افزایش تدریجی شدت.',
                ];
                $recs['medical']  = [
                    '🚨 مراجعه فوری به متخصص هپاتولوژی الزامی است.',
                    'Fibroscan (الاستوگرافی کبد) برای ارزیابی فیبروز.',
                    'بیوپسی کبد ممکن است توسط پزشک توصیه شود.',
                    'پایش آنزیم‌های کبدی و سونوگرافی هر ۳ ماه.',
                    'بررسی احتمال NASH و پیشگیری از پیشرفت به سیروز.',
                ];
                break;
        }

        return $recs;
    }

    /* ═══════════════════════════════════════════════════════════════════════
     * ADMIN EMAIL NOTIFICATION
     * ═══════════════════════════════════════════════════════════════════════ */

    private function send_notification( int $lead_id, string $name, string $phone,
                                        string $type, float $score, string $grade ) {
        $to      = get_option( 'signteb_liver_admin_email', get_option( 'admin_email' ) );
        $subject = sprintf( '[SignTeb] لید جدید #%d — %s | گرید: %s', $lead_id, $name, $grade );
        $body    = sprintf(
            "لید جدید دریافت شد:\n\n" .
            "═══════════════════════\n" .
            "شناسه:    #%d\n" .
            "نام:      %s\n" .
            "تلفن:     %s\n" .
            "آزمون:    %s\n" .
            "امتیاز:   %.2f\n" .
            "گرید:     %s\n" .
            "زمان:     %s\n" .
            "═══════════════════════\n\n" .
            "مشاهده در پنل:\n%s",
            $lead_id,
            $name,
            $phone,
            $type,
            $score,
            $grade,
            current_time( 'mysql' ),
            admin_url( 'admin.php?page=signteb-liver-leads' )
        );

        wp_mail( $to, $subject, $body );

        /* Dynamic SMS notification to the clinic (admin + secretary lines). */
        if ( class_exists( 'SignTeb_Liver_SMS' ) ) {
            $grade_fa = [ 'grade_0' => 'طبیعی', 'grade_1' => 'خفیف', 'grade_2' => 'متوسط', 'grade_3' => 'شدید' ];
            SignTeb_Liver_SMS::instance()->notify_lead( [
                'NAME'   => $name,
                'MOBILE' => $phone,
                'GRADE'  => ( $grade_fa[ $grade ] ?? $grade ),
                'TYPE'   => ( 'clinical' === $type ? 'بالینی' : 'سبک‌زندگی' ),
                'ID'     => $lead_id,
            ] );
        }
    }

    /* ═════════════════════════════════════════════════════════
     * OTP — SEND VERIFICATION CODE
     * ═════════════════════════════════════════════════════════ */

    public function handle_send_otp() {
        check_ajax_referer( 'signteb_liver_nonce', 'nonce' );

        if ( '1' !== get_option( 'signteb_liver_sms_enabled', '0' ) ) {
            wp_send_json_error( [ 'message' => 'تأیید پیامکی غیرفعال است.' ], 400 );
        }

        $phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
        if ( ! preg_match( '/^09\d{9}$/', $phone ) ) {
            wp_send_json_error( [ 'message' => 'شماره موبایل معتبر نیست.' ], 422 );
        }

        /* Rate-limit: one request per 60s per phone number */
        if ( get_transient( 'signteb_otp_rl_' . $phone ) ) {
            wp_send_json_error( [ 'message' => 'لطفاً کمی صبر کنید و دوباره تلاش کنید.' ], 429 );
        }

        $code = (string) wp_rand( 10000, 99999 );
        set_transient( 'signteb_otp_' . $phone, $code, 3 * MINUTE_IN_SECONDS );
        set_transient( 'signteb_otp_rl_' . $phone, 1, MINUTE_IN_SECONDS );

        $ok = class_exists( 'SignTeb_Liver_SMS' )
            ? SignTeb_Liver_SMS::instance()->send_otp( $phone, $code )
            : false;
        if ( ! $ok ) {
            wp_send_json_error( [ 'message' => 'ارسال پیامک ناموفق بود. تنظیمات سامانه پیامک را بررسی کنید.' ], 502 );
        }

        wp_send_json_success( [ 'message' => 'کد تأیید ارسال شد.' ] );
    }
}
