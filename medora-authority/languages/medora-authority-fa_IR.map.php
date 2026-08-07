<?php

declare(strict_types=1);

/**
 * Persian (fa_IR) translation draft.
 *
 * ⚠️  FIRST PASS — NEEDS NATIVE REVIEW BEFORE RELEASE.
 *
 * Written by an assistant, not a Persian-speaking editor. The terminology is
 * consistent and the grammar is sound, but register and idiom on a medical
 * product are exactly the things a non-native draft gets subtly wrong, and this
 * text is read by clinicians. Review it before shipping.
 *
 * Anything left out of this map stays English in the compiled catalogue, which
 * is safe: gettext falls back per string.
 *
 * Merge into the .po with:
 *   php bin/merge-po.php fa_IR
 *
 * Edits made directly in the .po win over this map on the next merge, so a
 * reviewer can correct the .po without their work being overwritten.
 *
 * ---------------------------------------------------------------------------
 * Terminology, fixed so the interface reads as one voice:
 *
 *   authority        اعتبار            entity        موجودیت
 *   score            امتیاز             knowledge graph گراف دانش
 *   crawler          خزنده             passage       بند
 *   citation         استناد            deduction     کسر امتیاز
 *   prompt pack      بستهٔ پرامپت       salience      برجستگی
 *   coverage         پوشش              readiness     آمادگی
 *   internal link    پیوند داخلی        brief         راهنمای نگارش
 * ---------------------------------------------------------------------------
 */

return [
    // --- Navigation and screens ---------------------------------------------
    'Overview'                 => 'نمای کلی',
    'Entities'                 => 'موجودیت‌ها',
    'Knowledge Graph'          => 'گراف دانش',
    'Content'                  => 'محتوا',
    'AI Crawlers'              => 'خزنده‌های هوش مصنوعی',
    'AI Analytics'             => 'تحلیل هوش مصنوعی',
    'Audit log'                => 'گزارش رویدادها',
    'Settings'                 => 'تنظیمات',
    'Dashboard'                => 'داشبورد',
    'Medora'                   => 'مدورا',
    'Medora Authority'         => 'مدورا اتوریتی',

    // --- Module names --------------------------------------------------------
    'AI Crawler Manager'       => 'مدیریت خزنده‌های هوش مصنوعی',
    'AI Sitemap Engine'        => 'موتور نقشهٔ سایت هوش مصنوعی',
    'Schema Intelligence'      => 'هوشمندی دادهٔ ساختاریافته',
    'Knowledge Graph Engine'   => 'موتور گراف دانش',
    'Entity Intelligence'      => 'هوشمندی موجودیت‌ها',
    'Semantic Engine'          => 'موتور معنایی',
    'AI Content Optimizer'     => 'بهینه‌ساز محتوای هوش مصنوعی',
    'Citation Engine'          => 'موتور استنادها',
    'E-E-A-T Engine'           => 'موتور E-E-A-T',
    'AI Prompt Engine'         => 'موتور پرامپت',
    'Vector Engine'            => 'موتور برداری',
    'Internal Linking AI'      => 'پیوندسازی داخلی هوشمند',
    'GEO Optimizer'            => 'بهینه‌ساز GEO',
    'Medical Intelligence'     => 'هوشمندی پزشکی',
    'AI API'                   => 'API هوش مصنوعی',
    'AI Writer'                => 'نویسندهٔ هوش مصنوعی',
    'AI Authority Score'       => 'امتیاز اعتبار هوش مصنوعی',
    'AI Authority'             => 'اعتبار هوش مصنوعی',

    // --- Score dimensions ----------------------------------------------------
    'AI Readiness'             => 'آمادگی برای هوش مصنوعی',
    'Entity Authority'         => 'اعتبار موجودیت',
    'Semantic Strength'        => 'قدرت معنایی',
    'Knowledge Quality'        => 'کیفیت دانش',
    'Prompt Quality'           => 'کیفیت پرامپت',
    'Medical Trust'            => 'اعتماد پزشکی',
    'LLM Compatibility'        => 'سازگاری با مدل‌های زبانی',
    'Authority'                => 'اعتبار',

    // --- Common actions ------------------------------------------------------
    'Save settings'            => 'ذخیرهٔ تنظیمات',
    'Save entity'              => 'ذخیرهٔ موجودیت',
    'Saving…'                  => 'در حال ذخیره…',
    'Saved.'                   => 'ذخیره شد.',
    'Settings saved.'          => 'تنظیمات ذخیره شد.',
    'Close'                    => 'بستن',
    'Cancel'                   => 'انصراف',
    'Continue'                 => 'ادامه',
    'Back'                     => 'بازگشت',
    'Edit'                     => 'ویرایش',
    'Hide'                     => 'پنهان کردن',
    'Restore'                  => 'بازگردانی',
    'Restore all'              => 'بازگردانی همه',
    'Add link'                 => 'افزودن پیوند',
    'Copied'                   => 'کپی شد',
    'Copy as Markdown'         => 'کپی به‌صورت Markdown',
    'Loading…'                 => 'در حال بارگذاری…',
    'Loading Medora…'          => 'در حال بارگذاری مدورا…',
    'Analysing…'               => 'در حال تحلیل…',
    'Linking…'                 => 'در حال پیوند دادن…',
    'Building brief…'          => 'در حال ساخت راهنمای نگارش…',
    'Building layout…'         => 'در حال چیدمان…',
    'Finding related pages…'   => 'در حال یافتن صفحه‌های مرتبط…',
    'Reading passages…'        => 'در حال خواندن بندها…',
    'Finish setup'             => 'پایان راه‌اندازی',
    'Done — reloading'         => 'انجام شد — در حال بارگذاری مجدد',
    'Newer'                    => 'جدیدتر',
    'Older'                    => 'قدیمی‌تر',
    'Activate'                 => 'فعال‌سازی',
    'Deactivate'               => 'غیرفعال‌سازی',
    'Refresh status'           => 'به‌روزرسانی وضعیت',

    // --- States and labels ---------------------------------------------------
    'Active'                   => 'فعال',
    'Expired'                  => 'منقضی‌شده',
    'Failed'                   => 'ناموفق',
    'Free'                     => 'رایگان',
    'Critical'                 => 'بحرانی',
    'High'                     => 'زیاد',
    'Medium'                   => 'متوسط',
    'Low'                      => 'کم',
    'Waiting'                  => 'در انتظار',
    'Running'                  => 'در حال اجرا',
    'Queue'                    => 'صف',
    'Background work'          => 'کارهای پس‌زمینه',
    'When'                     => 'زمان',
    'Who'                      => 'کاربر',
    'What happened'            => 'رویداد',
    'Page'                     => 'صفحه',
    'Score'                    => 'امتیاز',
    'Access'                   => 'دسترسی',
    'Crawler'                  => 'خزنده',
    'Crawler hits'             => 'بازدید خزنده‌ها',
    'Entity'                   => 'موجودیت',
    'Entity type'              => 'نوع موجودیت',
    'All types'                => 'همهٔ نوع‌ها',
    'Description'              => 'توضیح',
    'Anchor text'              => 'متن پیوند',
    'Credentials'              => 'مدارک',
    'Affiliation'              => 'وابستگی سازمانی',
    'Awards'                   => 'افتخارات',
    'Education'                => 'تحصیلات',
    'Evidence'                 => 'شواهد',
    'Evidence and sources'     => 'شواهد و منابع',
    'Examples'                 => 'نمونه‌ها',
    'Definition'               => 'تعریف',
    'Symptoms'                 => 'علائم',
    'Causes'                   => 'علت‌ها',
    'Diagnosis'                => 'تشخیص',
    'Treatment'                => 'درمان',
    'Prognosis'                => 'پیش‌آگهی',
    'When to seek care'        => 'زمان مراجعه به پزشک',
    'Preparation'              => 'آمادگی پیش از اقدام',
    'Recovery'                 => 'دورهٔ نقاهت',
    'Risks'                    => 'خطرها',
    'Cost'                     => 'هزینه',
    'Features'                 => 'ویژگی‌ها',
    'Pricing'                  => 'قیمت‌گذاری',
    'Comparison'               => 'مقایسه',
    'Reviews'                  => 'نظرها',
    'How it works'             => 'نحوهٔ عملکرد',
    'Why it matters'           => 'اهمیت موضوع',
    'Common questions'         => 'پرسش‌های رایج',
    'Review date'              => 'تاریخ بازبینی',
    'What it is'               => 'چیستی',
    'Who needs it'             => 'مناسب چه کسانی است',
    'What it does'             => 'کارکرد',
    'Canonical answer'         => 'پاسخ مرجع',
    'Opening'                  => 'شروع متن',
    'Measurements'             => 'اندازه‌گیری‌ها',
    'Passages'                 => 'بندها',
    'Fixes'                    => 'اصلاح‌ها',
    'Brief'                    => 'راهنمای نگارش',
    'Writing brief'            => 'راهنمای نگارش',
    'Internal links'           => 'پیوندهای داخلی',
    'Links'                    => 'پیوندها',
    'Relationships'            => 'روابط',
    'Modules'                  => 'ماژول‌ها',
    'Licence'                  => 'لایسنس',
    'Licence key'              => 'کلید لایسنس',
    'White label'              => 'برچسب سفید',
    'Publisher'                => 'ناشر',
    'Embeddings'               => 'بردارهای معنایی',
    'Content language'         => 'زبان محتوا',
    'Accent colour'            => 'رنگ شاخص',
    'Product name'             => 'نام محصول',
    'Menu label'               => 'برچسب منو',
    'Support URL'              => 'نشانی پشتیبانی',
    'Your company'             => 'شرکت شما',
    'Your website'             => 'وب‌سایت شما',
    'Organisation name'        => 'نام سازمان',
    'Model'                    => 'مدل',
    'Provider'                 => 'ارائه‌دهنده',
    'General'                  => 'عمومی',
    'Beginner'                 => 'مبتدی',
    'Professional'             => 'حرفه‌ای',
    'Agency'                   => 'آژانس',
    'Enterprise'               => 'سازمانی',
    'Allow'                    => 'اجازه',
    'Allow all'                => 'اجازه به همه',
    'Block'                    => 'مسدود',
    'Selective'                => 'گزینشی',
    'add'                      => 'افزودن',
    'no heading'               => 'بدون عنوان',
    'none'                     => 'هیچ',
    'nothing'                  => 'چیزی',
    'yes'                      => 'بله',
    'no'                       => 'خیر',
    'already linked'           => 'از قبل پیوند دارد',
    'not running'              => 'در حال اجرا نیست',
    'System'                   => 'سیستم',
    'Pages'                    => 'صفحه‌ها',
    'Clinic'                   => 'کلینیک',
    'Hospital'                 => 'بیمارستان',
    'Condition'                => 'بیماری',
    'Drug'                     => 'دارو',
    'Event'                    => 'رویداد',
    'Creative work'            => 'اثر خلاقانه',
    'Anatomical structure'     => 'ساختار آناتومیک',
    'Home'                     => 'خانه',

    // --- Empty and error states ---------------------------------------------
    'Nothing recorded yet.'    => 'هنوز رویدادی ثبت نشده است.',
    'Nothing outstanding'      => 'کار باقی‌مانده‌ای نیست',
    'Nothing deducted here.'   => 'در این بخش امتیازی کسر نشده است.',
    'Reads on its own.'        => 'به‌تنهایی قابل فهم است.',
    'Entity not found.'        => 'موجودیت پیدا نشد.',
    'Citation not found.'      => 'استناد پیدا نشد.',
    'Enter a licence key.'     => 'کلید لایسنس را وارد کنید.',
    'A page cannot link to itself.' => 'یک صفحه نمی‌تواند به خودش پیوند بدهد.',
    'Something went wrong.'    => 'خطایی رخ داد.',
    'Unknown module.'          => 'ماژول ناشناخته.',
    'No settings supplied.'    => 'تنظیماتی ارسال نشد.',

    // --- Sentences the user meets most often --------------------------------
    'An API key is configured.' => 'کلید API تنظیم شده است.',
    'Where the score comes from' => 'امتیاز از کجا می‌آید',
    'Do these, in this order'  => 'این کارها را به همین ترتیب انجام دهید',
    'Biggest wins available'   => 'بیشترین دستاورد ممکن',
    'Answer-first rewrite'     => 'بازنویسی پاسخ‌محور',
    'Entities to introduce'    => 'موجودیت‌هایی که باید معرفی شوند',
    'Questions not yet answered' => 'پرسش‌هایی که هنوز پاسخ داده نشده‌اند',
    'Pages this one should link to' => 'صفحه‌هایی که این صفحه باید به آن‌ها پیوند بدهد',
    'Pages that should link here' => 'صفحه‌هایی که باید به این صفحه پیوند بدهند',
    'Remove entity'            => 'حذف موجودیت',
    'Remove this entity'       => 'حذف این موجودیت',
    'Yes, remove it'           => 'بله، حذف شود',
    'Use my branding'          => 'استفاده از برند من',
    'How much to show'         => 'میزان نمایش',
    'Your pages are written in' => 'زبان صفحه‌های شما',
    'Settings were saved.'     => 'تنظیمات ذخیره شد.',
    'A module was turned on or off.' => 'یک ماژول روشن یا خاموش شد.',
    'The AI crawler policy changed.' => 'سیاست خزنده‌های هوش مصنوعی تغییر کرد.',
    'The licence status changed.' => 'وضعیت لایسنس تغییر کرد.',
    'AI crawler policy'        => 'سیاست خزنده‌های هوش مصنوعی',
    'AI crawler activity'      => 'فعالیت خزنده‌های هوش مصنوعی',
    'AI crawler visits'        => 'بازدید خزنده‌های هوش مصنوعی',
    'AI referral traffic'      => 'ترافیک ارجاعی هوش مصنوعی',
    'AI referrals'             => 'ارجاع‌های هوش مصنوعی',
    'By assistant'             => 'به تفکیک دستیار',
    'Distinct sources'         => 'منابع متمایز',
    'Graph connectivity'       => 'پیوستگی گراف',
    'External reconciliation'  => 'تطبیق با منابع بیرونی',
    'Editorial focus'          => 'تمرکز تحریریه',
    'Entity index (JSON)'      => 'نمایهٔ موجودیت‌ها (JSON)',
    'Full content bundle'      => 'بستهٔ کامل محتوا',
    'Curated site map for LLMs' => 'نقشهٔ گزیدهٔ سایت برای مدل‌های زبانی',
    'Follow the WordPress site language' => 'پیروی از زبان سایت وردپرس',
    'Health / medical (YMYL)'  => 'سلامت / پزشکی (YMYL)',
    'Built-in (offline, no API key)' => 'داخلی (آفلاین، بدون کلید API)',
    'OpenAI-compatible API'    => 'API سازگار با OpenAI',
    'Anthropic (Claude)'       => 'انتراپیک (کلاد)',
    // --- Second pass: remaining short labels ---------------------------------
    'Pro'                      => 'حرفه‌ای',
    'Type'                     => 'نوع',
    'Next'                     => 'بعدی',
    'Previous'                 => 'قبلی',
    'Retry'                    => 'تلاش دوباره',
    'Topic'                    => 'موضوع',
    'Thing'                    => 'چیز',
    'Place'                    => 'مکان',
    'Person'                   => 'شخص',
    'Product'                  => 'محصول',
    'Service'                  => 'خدمت',
    'Procedure'                => 'روش درمانی',
    'Specialty'                => 'تخصص',
    'Physician'                => 'پزشک',
    'Organization'             => 'سازمان',
    'Structure'                => 'ساختار',
    'Source'                   => 'منبع',
    'Trend'                    => 'روند',
    'Visits'                   => 'بازدیدها',
    'Visitors'                 => 'بازدیدکنندگان',
    'Security'                 => 'امنیت',
    'Licensing'                => 'صدور مجوز',
    'Unknown'                  => 'ناشناخته',
    'Invalid'                  => 'نامعتبر',
    'Unclassified'             => 'دسته‌بندی‌نشده',
    'Not licensed'             => 'بدون لایسنس',
    'White Label'              => 'برچسب سفید',
    'Job title'                => 'عنوان شغلی',
    'Site mode'                => 'حالت سایت',
    'Site policy'              => 'سیاست سایت',
    'Use site policy'          => 'استفاده از سیاست سایت',
    'Page tools'               => 'ابزارهای صفحه',
    'Start setup'              => 'شروع راه‌اندازی',
    'Setting up…'              => 'در حال راه‌اندازی…',
    'Welcome to Medora'        => 'به مدورا خوش آمدید',
    'Re-analyse now'           => 'تحلیل مجدد',
    'Last 7 days'              => '۷ روز گذشته',
    'Last 30 days'             => '۳۰ روز گذشته',
    'Last 90 days'             => '۹۰ روز گذشته',
    'Previous period'          => 'دورهٔ قبل',
    'Reporting window'         => 'بازهٔ گزارش',
    'Weakest pages'            => 'ضعیف‌ترین صفحه‌ها',
    'Pages analysed'           => 'صفحه‌های تحلیل‌شده',
    'Most-crawled URLs'        => 'پربازدیدترین نشانی‌ها برای خزنده‌ها',
    'Topical depth'            => 'عمق موضوعی',
    'Type specificity'         => 'دقت نوع',
    'Knowledge graph'          => 'گراف دانش',
    'Nodes to display'         => 'تعداد گره‌های نمایشی',
    'Top 60 entities'          => '۶۰ موجودیت برتر',
    'Top 150 entities'         => '۱۵۰ موجودیت برتر',
    'Top 300 entities'         => '۳۰۰ موجودیت برتر',
    'Open entity page'         => 'باز کردن صفحهٔ موجودیت',
    'No issues found.'         => 'مشکلی یافت نشد.',
    'No summary'               => 'بدون خلاصه',
    'No review date'           => 'بدون تاریخ بازبینی',
    'Last reviewed'            => 'آخرین بازبینی',
    'Medical review'           => 'بازبینی پزشکی',
    'Medical reviewer'         => 'بازبین پزشکی',
    'Years of practice'        => 'سال‌های سابقه',
    'Model training'           => 'آموزش مدل',
    'AI search index'          => 'نمایهٔ جست‌وجوی هوش مصنوعی',
    'llms.txt Engine'          => 'موتور llms.txt',
    'LLM site guide'           => 'راهنمای سایت برای مدل‌های زبانی',
    'Publish llms.txt'         => 'انتشار llms.txt',
    'Medora sections'          => 'بخش‌های مدورا',
    'Block AI crawlers'        => 'مسدود کردن خزنده‌های هوش مصنوعی',
    'Allow, throttled'         => 'اجازه با محدودیت سرعت',
    'Unknown crawler.'         => 'خزندهٔ ناشناخته.',
    'Post not found.'          => 'نوشته پیدا نشد.',
    'User not found.'          => 'کاربر پیدا نشد.',
    'Renewal due'              => 'موعد تمدید',
    'LinkedIn URL'             => 'نشانی لینکدین',
    'ResearchGate URL'         => 'نشانی ریسرچ‌گیت',
    'Google Scholar URL'       => 'نشانی گوگل اسکالر',
    'sameAs'                   => 'sameAs',
    'ORCID'                    => 'ORCID',
    'Harvard'                  => 'هاروارد',
    'Vancouver'                => 'ونکوور',
    'APA (7th edition)'        => 'APA (ویرایش هفتم)',
    'Case series'              => 'مجموعه موارد',
    'Case-control study'       => 'مطالعهٔ مورد-شاهدی',
    'Cohort study'             => 'مطالعهٔ هم‌گروهی',
    'Expert opinion'           => 'نظر کارشناسی',
    'Clinical practice guideline' => 'راهنمای بالینی',
    'Sign or symptom'          => 'نشانه یا علامت',
    'override'                 => 'بازنویسی',
    'Used for'                 => 'کاربرد',

    // --- Relationship predicates in the graph -------------------------------
    'affects'                  => 'تأثیر می‌گذارد بر',
    'mentions'                 => 'اشاره می‌کند به',
    'is about'                 => 'دربارهٔ',
    'provides'                 => 'ارائه می‌دهد',
    'written by'               => 'نوشتهٔ',
    'reviewed by'              => 'بازبینی‌شده توسط',
    'is part of'               => 'بخشی است از',
    'is related to'            => 'مرتبط است با',
    'is located in'            => 'واقع است در',
    'is a drug for'            => 'دارویی است برای',
    'is a symptom of'          => 'علامتی است از',
    'is diagnosed by'          => 'تشخیص داده می‌شود با',
    'is the same as'           => 'همان است که',

    // --- Question-shaped headings the brief suggests ------------------------
    'What is it?'              => 'این چیست؟',
    'What causes it?'          => 'علت آن چیست؟',
    'What does it do?'         => 'چه کاری انجام می‌دهد؟',
    'How does it work?'        => 'چگونه کار می‌کند؟',
    'Who is it for?'           => 'برای چه کسانی است؟',
    'What are the symptoms?'   => 'علائم آن چیست؟',
    'How is it diagnosed?'     => 'چگونه تشخیص داده می‌شود؟',
    'How is it treated?'       => 'چگونه درمان می‌شود؟',
    'What is the outlook?'     => 'پیش‌آگهی آن چگونه است؟',
    'When should you see a doctor?' => 'چه زمانی باید به پزشک مراجعه کرد؟',
    'How do you prepare?'      => 'چگونه آماده می‌شوید؟',
    'What does recovery involve?' => 'دورهٔ نقاهت شامل چیست؟',
    'What are the risks?'      => 'خطرهای آن چیست؟',
    'What does it cost?'       => 'هزینهٔ آن چقدر است؟',
    'How does it compare?'     => 'در مقایسه چگونه است؟',
    'What do people say?'      => 'نظر دیگران چیست؟',
    'What does it include?'    => 'شامل چه چیزهایی است؟',
    'Why does it matter?'      => 'چرا اهمیت دارد؟',

    // --- Coverage hints -----------------------------------------------------
    'most common first'        => 'ابتدا شایع‌ترین‌ها',
    'which are red flags'      => 'کدام‌ها هشداردهنده‌اند',
    'name the specific tests'  => 'نام دقیق آزمایش‌ها را بیاورید',
    'first-line option first'  => 'ابتدا گزینهٔ خط اول',
    'expected timeframe'       => 'بازهٔ زمانی مورد انتظار',
    'what happens if untreated' => 'اگر درمان نشود چه می‌شود',
    'separate causes from risk factors' => 'علت‌ها را از عوامل خطر جدا کنید',
    'give a figure with a source' => 'یک عدد همراه با منبع بیاورید',
    'a range, with what changes it' => 'یک بازه، به‌همراه عوامل مؤثر بر آن',
    'rate the common ones'     => 'شایع‌ها را با نرخ بیاورید',
    'name the rare serious ones' => 'موارد نادر اما جدی را نام ببرید',
    'say what each rules in or out' => 'بگویید هرکدام چه چیزی را تأیید یا رد می‌کند',
    // --- Third pass: the sentences on the main screens -----------------------
    'Lowest-scoring pages first. Pick one to see its ordered fix list.'
        => 'ابتدا صفحه‌های با کمترین امتیاز. یکی را انتخاب کنید تا فهرست اصلاح‌های مرتب‌شدهٔ آن را ببینید.',
    'Ordered by points recovered per unit of effort, not by severity alone.'
        => 'مرتب‌شده بر اساس امتیاز بازیافته به‌ازای هر واحد تلاش، نه صرفاً بر اساس شدت.',
    'Completing every action below would take this page to roughly %s / 100.'
        => 'انجام همهٔ کارهای زیر این صفحه را تقریباً به %s از ۱۰۰ می‌رساند.',
    '%1$s now → about %2$s if everything below is done.'
        => 'اکنون %1$s ← حدود %2$s در صورت انجام همهٔ موارد زیر.',
    'Where the score comes from' => 'امتیاز از کجا می‌آید',
    'Each dimension starts at 100 and loses points. Dimensions that do not apply to this site are left out, and the rest are re-weighted to fill the gap.'
        => 'هر بُعد از ۱۰۰ شروع می‌شود و امتیاز از دست می‌دهد. بُعدهایی که به این سایت مربوط نیستند کنار گذاشته می‌شوند و وزن بقیه بازتنظیم می‌شود.',

    // Graph.
    'No entities yet. Publish content and run an analysis to build the graph.'
        => 'هنوز موجودیتی نیست. برای ساخت گراف، محتوا منتشر کنید و یک تحلیل اجرا کنید.',
    'Circle size = number of relationships. Fill = authority score. Click a node to open it.'
        => 'اندازهٔ دایره = تعداد روابط. رنگ = امتیاز اعتبار. برای باز کردن یک گره روی آن کلیک کنید.',
    '%1$d entities, %2$d relationships across the site.'
        => '%1$d موجودیت و %2$d رابطه در کل سایت.',

    // Links.
    'No related pages found yet. Publish more on this subject, or check the Vector Engine is running.'
        => 'هنوز صفحهٔ مرتبطی پیدا نشد. دربارهٔ این موضوع بیشتر منتشر کنید، یا بررسی کنید موتور برداری در حال اجرا باشد.',
    'No shared phrase in this page to use as an anchor.'
        => 'عبارت مشترکی در این صفحه برای استفاده به‌عنوان متن پیوند وجود ندارد.',
    'Inbound links are what actually move authority toward a page. Open each and add the link there.'
        => 'این پیوندهای ورودی هستند که واقعاً اعتبار را به یک صفحه منتقل می‌کنند. هر کدام را باز کنید و پیوند را همان‌جا اضافه کنید.',

    // Passages.
    '%1$d of %2$d passages (%3$d%%) stand on their own when retrieved without the rest of the page.'
        => '%1$d از %2$d بند (%3$d%%) وقتی جدا از بقیهٔ صفحه بازیابی شوند، به‌تنهایی معنا دارند.',
    'Nothing here to retrieve. This page has no body copy a model could be handed.'
        => 'چیزی برای بازیابی نیست. این صفحه متنی ندارد که بتوان به یک مدل داد.',
    'Long page with no table, procedure or list. Structure gets quoted intact; prose gets paraphrased.'
        => 'صفحهٔ بلند بدون جدول، مراحل یا فهرست. ساختار دست‌نخورده نقل می‌شود؛ متن پیوسته بازنویسی می‌شود.',
    'No H2 or H3 headings, so passage boundaries fall mid-argument.'
        => 'هیچ عنوان H2 یا H3 وجود ندارد، پس مرز بندها وسط بحث می‌افتد.',
    'No heading is phrased as a question. A question heading makes the passage under it a direct answer.'
        => 'هیچ عنوانی به شکل پرسش نوشته نشده است. عنوان پرسشی باعث می‌شود بند زیر آن یک پاسخ مستقیم باشد.',

    // Brief.
    'Drawn from your own knowledge graph — concepts this subject connects to elsewhere on the site.'
        => 'برگرفته از گراف دانش خودتان — مفاهیمی که این موضوع در جای دیگری از سایت به آن‌ها متصل است.',
    'Could not copy. Open the brief endpoint directly to grab the Markdown.'
        => 'کپی نشد. برای گرفتن Markdown، مستقیماً نشانی راهنمای نگارش را باز کنید.',

    // Analytics and crawlers.
    'The closest available proxy for which of your pages are being cited.'
        => 'نزدیک‌ترین معیار در دسترس برای اینکه کدام صفحه‌های شما مورد استناد قرار می‌گیرند.',
    'What AI crawlers fetched most over the last %d days — the pages most likely to end up cited.'
        => 'آنچه خزنده‌های هوش مصنوعی در %d روز گذشته بیشتر دریافت کرده‌اند — صفحه‌هایی که بیشترین احتمال استناد را دارند.',
    'Allowed but never seen: %s. Usually a robots, DNS or firewall issue rather than disinterest.'
        => 'مجاز اما هرگز دیده‌نشده: %s. معمولاً مشکل robots، DNS یا فایروال است، نه بی‌علاقگی.',
    'The issues that recur most across your site — one fix each, applied broadly.'
        => 'مشکلاتی که در سراسر سایت بیشتر تکرار می‌شوند — هرکدام یک اصلاح، با اثر گسترده.',
    '%d background job(s) failed. Check the site health log — analysis may be incomplete.'
        => '%d کار پس‌زمینه ناموفق بود. گزارش سلامت سایت را بررسی کنید — ممکن است تحلیل ناقص باشد.',
    'These are what AI systems consume. They are public and require no key.'
        => 'این‌ها چیزی هستند که سامانه‌های هوش مصنوعی مصرف می‌کنند. عمومی‌اند و به کلید نیاز ندارند.',

    // Crawler policy.
    'Maximum AI visibility, including model training.'
        => 'بیشترین دیده‌شدن در هوش مصنوعی، شامل آموزش مدل.',
    'Stay citable in AI answers, opt out of corpus collection. This is what most publishers want.'
        => 'در پاسخ‌های هوش مصنوعی قابل استناد بمانید، اما از جمع‌آوری برای پیکره خارج شوید. اکثر ناشران همین را می‌خواهند.',
    'Block AI crawlers (classic search still allowed)'
        => 'مسدود کردن خزنده‌های هوش مصنوعی (جست‌وجوی کلاسیک همچنان مجاز است)',
    'Allow all — maximum AI visibility'
        => 'اجازه به همه — بیشترین دیده‌شدن در هوش مصنوعی',
    'Selective — allow search, block training'
        => 'گزینشی — اجازه به جست‌وجو، مسدود کردن آموزش',
    'Classic search crawlers still allowed.'
        => 'خزنده‌های جست‌وجوی کلاسیک همچنان مجازند.',

    // Licence and settings.
    'Licence expired. Everything keeps working for %d more day(s).'
        => 'لایسنس منقضی شده است. همه چیز %d روز دیگر کار می‌کند.',
    'Saved. Reload the page for the change to take full effect.'
        => 'ذخیره شد. برای اعمال کامل تغییر، صفحه را دوباره بارگذاری کنید.',
    'This becomes the publisher node every page in your knowledge graph points back to.'
        => 'این گرهٔ ناشر می‌شود که هر صفحه در گراف دانش شما به آن ارجاع می‌دهد.',
    'No API key configured. Set MEDORA_EMBEDDING_API_KEY in wp-config.php rather than storing it in the database.'
        => 'کلید API تنظیم نشده است. به‌جای ذخیره در پایگاه داده، MEDORA_EMBEDDING_API_KEY را در wp-config.php قرار دهید.',
    'No API key configured. Set MEDORA_LLM_API_KEY in wp-config.php rather than storing it in the database.'
        => 'کلید API تنظیم نشده است. به‌جای ذخیره در پایگاه داده، MEDORA_LLM_API_KEY را در wp-config.php قرار دهید.',
    'Let a model rewrite page summaries and canonical answers'
        => 'اجازه بده یک مدل خلاصه‌ها و پاسخ‌های مرجع صفحه‌ها را بازنویسی کند',

    // White label.
    'Present the plugin under your own name. Nothing here changes what it does.'
        => 'افزونه را با نام خودتان ارائه دهید. هیچ‌چیز در اینجا عملکرد آن را تغییر نمی‌دهد.',
    'Shown as the plugin name and the dashboard title.'
        => 'به‌عنوان نام افزونه و عنوان داشبورد نمایش داده می‌شود.',
    'The admin menu entry. Keep it short.'
        => 'ورودی منوی مدیریت. کوتاه نگه دارید.',
    'Replaces the author on the Plugins screen.'
        => 'جایگزین نویسنده در صفحهٔ افزونه‌ها می‌شود.',
    'Where your clients go for help.'
        => 'جایی که مشتریان شما برای کمک مراجعه می‌کنند.',
    'Replace the author and links on the Plugins screen too'
        => 'نویسنده و پیوندها در صفحهٔ افزونه‌ها هم جایگزین شوند',
    'Branding forced from code by a host takes precedence over anything saved here.'
        => 'برندینگی که میزبان از طریق کد تحمیل می‌کند، بر هر چیزی که اینجا ذخیره شود اولویت دارد.',

    // Experience modes.
    'Beginner — score, problems, fixes'
        => 'مبتدی — امتیاز، مشکل‌ها، اصلاح‌ها',
    'Professional — adds entities, analytics, passages'
        => 'حرفه‌ای — به‌همراه موجودیت‌ها، تحلیل و بندها',
    'Agency — adds the graph, modules, white-label'
        => 'آژانس — به‌همراه گراف، ماژول‌ها و برچسب سفید',
    'Enterprise — adds the audit log and queue health'
        => 'سازمانی — به‌همراه گزارش رویدادها و سلامت صف',

    // Wizard.
    'Run the two-minute setup to start measuring your AI authority.'
        => 'راه‌اندازی دو دقیقه‌ای را اجرا کنید تا سنجش اعتبار هوش مصنوعی شما آغاز شود.',
    'Analyse my existing content now (runs in the background)'
        => 'محتوای موجود من همین حالا تحلیل شود (در پس‌زمینه اجرا می‌شود)',
    'Step %1$d of %2$d'        => 'گام %1$d از %2$d',
    'Page %1$d of %2$d'        => 'صفحهٔ %1$d از %2$d',

    // Audit log.
    'Generated %1$s for %2$s was discarded — the page did not support it.'
        => '%1$s تولیدشده برای %2$s کنار گذاشته شد — صفحه آن را پشتیبانی نمی‌کرد.',
    'A model rewrote %1$s on %2$s.' => 'یک مدل %1$s را در %2$s بازنویسی کرد.',
    'An internal link was added to %s.' => 'یک پیوند داخلی به %s اضافه شد.',
    'Medora links were removed from %s.' => 'پیوندهای مدورا از %s حذف شدند.',
    'The model declined to write for %s.' => 'مدل از نوشتن برای %s خودداری کرد.',
    'The model request failed for %s.' => 'درخواست مدل برای %s ناموفق بود.',
];
