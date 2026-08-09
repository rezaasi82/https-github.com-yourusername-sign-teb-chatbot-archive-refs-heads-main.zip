<?php
/** Demo data for each view. Believable clinic numbers, no real people. */

function o(array $a) { return (object) $a; }

function demo_settings(): \Pezhkam\Core\Settings
{
    $s = new \Pezhkam\Core\Settings();
    $s->data = [
        'provider'          => 'anthropic',
        'model_anthropic'   => 'claude-haiku-4-5-20251001',
        'clinic_name'       => 'کلینیک نمونه سلامت',
        'bot_name'          => 'دستیار هوشمند کلینیک',
        'welcome_message'   => 'سلام! چطور می‌تونم کمکتون کنم؟',
        'teaser_message'    => 'سوالی دارید؟ همین‌جا بپرسید.',
        'business_hours'    => '09:00-21:00',
        'phone'             => '021-00000000',
        'whatsapp'          => '',
        'booking_url'       => 'https://example.com/booking',
        'avg_service_price' => 2500000,
        'direction'         => 'rtl',
        'widget_color'      => '#0f1f3d',
        'accent_color'      => '#c8a04e',
        'tone'              => 'friendly',
        'language'          => 'auto',
        'use_bundled_font'  => 1,
        'lead_capture'      => 1,
        'ch_booking'        => 1,
        'ch_whatsapp'       => 1,
        'ch_call'           => 1,
        'ch_bale'           => 0,
        'sms_provider'      => 'kavenegar',
        'sms_enabled'       => 1,
        'quick_replies'     => "هزینه ویزیت\nساعات کاری\nآدرس کلینیک",
    ];
    return $s;
}

function demo_leads(): array
{
    $names = [
        ['مریم احمدی', '09121234567', 'hot',  'ایمپلنت دندان'],
        ['رضا کریمی',  '09351112233', 'warm', 'ارتودنسی'],
        ['سارا نوری',  '09014445566', 'hot',  'لیزر موهای زائد'],
        ['امیر رستمی', '09197778899', 'cold', 'مشاوره عمومی'],
        ['نگار سلطانی','09302223344', 'warm', 'بوتاکس'],
        ['حسین مرادی', '09385556677', 'hot',  'جراحی بینی'],
    ];
    $out = [];
    foreach ($names as $i => [$n, $p, $sc, $topic]) {
        $out[] = o([
            'id' => 1200 + $i, 'patient_name' => $n, 'patient_phone' => $p,
            'lead_score' => $sc, 'language' => 'fa', 'summary' => $topic,
            'created_at' => '2025-08-1' . (8 - ($i % 6)) . ' 14:0' . $i . ':00',
            'messages' => 6 + $i, 'crm_status' => ['new','contacted','follow_up','booked','visited','closed'][$i % 6],
            'email' => '', 'branch_id' => 0, 'title' => $topic,
        ]);
    }
    return $out;
}

function demo_data(string $view): array
{
    $settings = demo_settings();
    $leads    = demo_leads();

    switch ($view) {
        case 'premium-dashboard':
            return [
                'settings'  => $settings,
                'integrity' => ['level' => 'ok', 'label' => 'فعال و ایمن'],
                'metrics'   => [
                    'conversations' => 59, 'leads' => 12, 'hot_leads' => 6,
                    'conversion' => '20.3', 'revenue' => 12180000,
                    'clicks'  => ['booking' => 31, 'whatsapp' => 22, 'call' => 17, 'bale' => 4],
                    'funnel'  => ['new' => 48, 'contacted' => 2, 'follow_up' => 0, 'booked' => 3,
                                  'visited' => 3, 'closed' => 0, 'lost' => 1],
                ],
            ];

        case 'dashboard':
            $days = [];
            foreach ([4,7,3,9,12,6,8,14,11,5,9,13,7,10] as $i => $v) {
                $days['۱۴۰۴/۰۵/' . str_pad((string) ($i + 5), 2, '۰', STR_PAD_LEFT)] = $v;
            }
            $clicksDaily = array_map(static fn($v) => max(0, (int) round($v * 0.6)), $days);
            return [
                'stats'  => ['conversations' => 59, 'leads' => 12, 'hot_leads' => 6,
                             'booked' => 9, 'conversion_rate' => '20.3', 'messages' => 412],
                'clicks' => ['booking' => 31, 'whatsapp' => 22, 'call' => 17, 'bale' => 4],
                'daily'  => $days,
                'daily_c'=> $clicksDaily,
                'top'    => [
                    o(['q' => 'هزینه ایمپلنت دندان چقدر است؟', 'c' => 23]),
                    o(['q' => 'ارتودنسی چند وقت طول می‌کشد؟', 'c' => 17]),
                    o(['q' => 'لیزر موهای زائد چند جلسه لازم دارد؟', 'c' => 14]),
                    o(['q' => 'آیا بوتاکس عوارض دارد؟', 'c' => 11]),
                    o(['q' => 'ساعات کاری کلینیک چیست؟', 'c' => 9]),
                ],
                'demand' => ['ایمپلنت' => 18, 'ارتودنسی' => 14, 'لیزر' => 11, 'بوتاکس' => 7, 'جراحی بینی' => 5],
            ];

        case 'crm-board':
            $cols = [];
            $buckets = [
                'new'       => array_slice($leads, 0, 3),
                'contacted' => array_slice($leads, 3, 1),
                'follow_up' => array_slice($leads, 4, 1),
                'booked'    => array_slice($leads, 5, 1),
                'visited'   => [], 'closed' => [], 'lost' => [],
            ];
            foreach (\Pezhkam\Crm\LeadCrm::STATUSES as $k => $def) {
                $cols[$k] = ['label' => $def[0], 'color' => $def[1], 'leads' => $buckets[$k] ?? []];
            }
            return ['columns' => $cols];

        case 'seo':
            return [
                'questions' => [
                    o(['q' => 'هزینه ایمپلنت دندان چقدر است؟', 'c' => 23]),
                    o(['q' => 'ارتودنسی چند وقت طول می‌کشد؟', 'c' => 17]),
                    o(['q' => 'لیزر موهای زائد چند جلسه لازم دارد؟', 'c' => 14]),
                    o(['q' => 'آیا بوتاکس عوارض دارد؟', 'c' => 11]),
                    o(['q' => 'ساعات کاری کلینیک چیست؟', 'c' => 9]),
                ],
                'keywords' => ['ایمپلنت' => 34, 'ارتودنسی' => 26, 'لیزر' => 21, 'بوتاکس' => 15, 'کامپوزیت' => 12, 'جرم‌گیری' => 8],
                'topics'   => ['هزینه و تعرفه' => 41, 'مدت درمان' => 28, 'عوارض و ریسک' => 19, 'نوبت‌دهی' => 16],
                'nonce'    => 'nonce',
                'cached'   => "۱. راهنمای کامل هزینه ایمپلنت دندان در ۱۴۰۴\n۲. ارتودنسی نامرئی در برابر سیمی: کدام مناسب شماست؟\n۳. لیزر موهای زائد: تعداد جلسات و فاصله بین آن‌ها",
            ];

        case 'branches':
            $branches = [
                o(['id' => 1, 'name' => 'شعبه مرکزی', 'doctor' => 'دکتر نمونه‌زاده', 'city' => 'تهران', 'phone' => '021-00000000', 'address' => 'خیابان نمونه، پلاک ۱', 'is_active' => 1]),
                o(['id' => 2, 'name' => 'شعبه شمال', 'doctor' => 'دکتر نمونه‌فر', 'city' => 'تهران', 'phone' => '021-00000001', 'address' => 'بلوار نمونه، پلاک ۲۲', 'is_active' => 1]),
                o(['id' => 3, 'name' => 'شعبه کرج', 'doctor' => '', 'city' => 'کرج', 'phone' => '026-00000000', 'address' => 'میدان نمونه', 'is_active' => 0]),
            ];
            return [
                'branches' => $branches,
                'stats'    => [1 => ['total' => 38, 'leads' => 9], 2 => ['total' => 15, 'leads' => 3], 3 => ['total' => 6, 'leads' => 0]],
                'editing'  => null,
                'nonce'    => 'nonce',
            ];

        case 'conversations-list':
            return [
                'items' => $leads, 'total' => 59, 'pages' => 3, 'page' => 1,
                'leads_only' => false, 'score' => '', 'branch' => 0, 'branches' => [],
            ];

        case 'conversation-single':
            $c = demo_leads()[0];
            $c->summary = "بیمار درباره هزینه و مراحل ایمپلنت دندان پرسیده است. بودجه مشخص دارد و "
                        . "برای هفته آینده دنبال نوبت مشاوره است. شماره تماس ثبت شد.";
            $c->created_at = '2025-08-18 14:30:00';
            $c->email = '';
            return [
                'conversation' => $c,
                'messages' => [
                    o(['role' => 'user', 'content' => 'سلام، هزینه ایمپلنت دندان چقدره؟', 'flagged' => 0]),
                    o(['role' => 'assistant', 'content' => "هزینه ایمپلنت به نوع پایه و برند آن بستگی دارد و بعد از معاینه دقیق مشخص می‌شود.\nبرای برآورد دقیق، معاینه حضوری لازم است.", 'flagged' => 0]),
                    o(['role' => 'user', 'content' => 'چند جلسه طول می‌کشه؟', 'flagged' => 0]),
                    o(['role' => 'assistant', 'content' => 'معمولاً بین سه تا شش ماه، بسته به روند جوش‌خوردن استخوان. جلسه اول مشاوره و عکس‌برداری است.', 'flagged' => 0]),
                    o(['role' => 'user', 'content' => 'ممنون، لطفاً نوبت مشاوره ثبت کنید.', 'flagged' => 0]),
                ],
                'branches' => [],
            ];

        case 'settings':
        default:
            \Pezhkam\Core\Input::$bag = ['tab' => 'provider'];
            return ['s' => $settings, 'tab' => 'provider'];
    }
}

function demo_css(string $view): array
{
    $base = ['assets/css/admin.css'];
    if ($view === 'premium-dashboard') { return ['assets/css/dashboard.css']; }
    if ($view === 'crm-board')         { return array_merge($base, ['assets/css/board.css']); }
    return $base;
}

function demo_page_css(string $view): string
{
    $pad = $view === 'premium-dashboard' ? '0' : '18px 22px';
    return 'body{margin:0;background:' . ($view === 'premium-dashboard' ? '#f0f0f1' : '#f0f0f1')
        . ';padding:' . $pad . ';}'
        . '.wrap{margin:0!important;max-width:none!important;}'
        . '.pzk-admin{max-width:1150px;margin-inline-end:0!important;}'
        . '.pzk-dash{margin:0!important;}';
}

/**
 * Chrome the parent page classes emit around the partial views: the
 * .wrap.pzk-admin container, the shared PageHeader, and the tab bar. Views
 * that already carry their own wrapper get nothing extra.
 */
function demo_chrome(string $view, $settings, ?string $forceTab = null): array
{
    $selfWrapped = ['premium-dashboard', 'crm-board', 'seo', 'branches'];
    if ($view === 'conversation-single') { $view = 'conversations-list'; }
    if (in_array($view, $selfWrapped, true)) {
        return ['', ''];
    }

    $tab = $forceTab ?? (['settings' => 'provider', 'dashboard' => 'stats', 'conversations-list' => 'conversations'][$view] ?? 'provider');

    ob_start();
    echo '<div class="wrap pzk-admin" dir="rtl">';
    \Pezhkam\Admin\PageHeader::render(
        'پژکام',
        'دستیار هوشمند جذب بیمار',
        [
            ['label' => 'ویجت شناور + شورت‌کد', 'state' => 'on'],
            ['label' => 'کلید API تنظیم شده', 'state' => 'on'],
            ['label' => 'نسخه ' . PZK_VERSION],
        ]
    );

    $labels = [
        'provider'      => 'هوش مصنوعی',
        'clinic'        => 'اطلاعات کلینیک',
        'appearance'    => 'ظاهر ویجت',
        'integrations'  => 'اتصال‌ها و خروجی',
        'conversations' => 'لیدها و مکالمات',
        'stats'         => 'آمار',
    ];
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($labels as $slug => $label) {
        $active = $slug === $tab ? ' nav-tab-active' : '';
        printf('<a href="#" class="nav-tab%s">%s</a>', esc_attr($active), esc_html($label));
    }
    echo '</h2>';

    return [ob_get_clean(), '</div>'];
}

/** Config for templates/widget.php, matching Widget::build_config(). */
function demo_widget_config(): array
{
    return [
        'direction' => 'rtl', 'widget_color' => '#0f1f3d', 'accent_color' => '#c8a04e',
        'bot_name' => 'دستیار هوشمند کلینیک', 'avatar_url' => '', 'brand_footer' => '',
        'welcome' => 'سلام! به کلینیک نمونه سلامت خوش آمدید. چطور می‌تونم کمکتون کنم؟',
        'offhours' => '', 'teaser' => 'سوالی دارید؟', 'teaser_delay' => 3, 'teaser_sound' => false,
        'inline' => true,
        'quick_replies' => ['هزینه ویزیت', 'ساعات کاری', 'آدرس کلینیک'],
        'within_hours' => true, 'booking_url' => 'https://example.com/booking',
        'whatsapp' => '9120000000', 'phone' => '02100000000', 'bale_url' => '',
        'branch' => 0, 'lead_capture' => 1,
        'channels' => ['booking' => true, 'whatsapp' => true, 'call' => true, 'bale' => false],
    ];
}
