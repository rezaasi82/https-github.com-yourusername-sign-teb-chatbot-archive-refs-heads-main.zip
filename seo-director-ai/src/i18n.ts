/**
 * Bilingual UI (English/Persian). The active language follows the WordPress
 * admin locale delivered in sdaBoot — no separate toggle to keep in sync.
 * `t()` looks the English source string up in the Persian dictionary and
 * falls back to English, so untranslated strings degrade gracefully instead
 * of showing keys. RTL layout is already handled by CSS logical properties.
 */

import { boot } from './api/client';

const FA: Record<string, string> = {
  // Shell / navigation
  'Overview': 'نمای کلی',
  'Winners & Losers': 'برنده‌ها و بازنده‌ها',
  'Opportunities': 'فرصت‌ها',
  'Content': 'محتوا',
  'Roadmap': 'نقشه راه',
  'Alerts': 'هشدارها',
  'Reports': 'گزارش‌ها',
  'Agency': 'آژانس',
  'Settings': 'تنظیمات',
  'Toggle theme': 'تغییر پوسته',
  'Upgrade': 'ارتقا',

  // Common
  'Save': 'ذخیره',
  'Saved': 'ذخیره شد',
  'Saving…': 'در حال ذخیره…',
  'Connect': 'اتصال',
  'Disconnect': 'قطع اتصال',
  'Connected': 'متصل',
  'Not connected': 'متصل نیست',
  'Loading…': 'در حال بارگذاری…',
  'Unknown error': 'خطای نامشخص',
  'Set up ▸': 'راه‌اندازی ▸',
  'done': 'انجام شد',

  // Error boundary
  'SEO Director AI could not start': 'SEO Director AI اجرا نشد',
  'Please copy this message (and your browser console output) to support. Reloading the page may help if this was a temporary network error.':
    'لطفاً این پیام (و خروجی کنسول مرورگر) را برای پشتیبانی ارسال کنید. اگر خطای موقت شبکه بوده، بارگذاری مجدد صفحه ممکن است مشکل را حل کند.',

  // Upgrade funnel
  'Upgrade to': 'ارتقا به',
  'This is available on the {plan} plan and above.': 'این قابلیت در پلن {plan} و بالاتر در دسترس است.',
  'is not available on your plan': 'در پلن فعلی شما در دسترس نیست',

  // Overview page
  'You’re viewing sample data': 'شما در حال مشاهدهٔ دادهٔ نمونه هستید',
  'This is a preview so you can see what SEO Director AI does. Connect Google Search Console in': 'این یک پیش‌نمایش است تا کارکرد SEO Director AI را ببینید. برای جایگزینی با آمار واقعی سایت، سرچ‌کنسول گوگل را در',
  'to replace it with your site’s real numbers.': 'متصل کنید.',
  'Get set up': 'راه‌اندازی اولیه',
  'SEO Health Score': 'امتیاز سلامت سئو',
  'No score yet': 'هنوز امتیازی نیست',
  'Scores appear after the first analysis pass over synced data.': 'امتیاز پس از نخستین تحلیل روی داده‌های همگام‌شده نمایش داده می‌شود.',
  'Weekly Summary': 'خلاصهٔ هفتگی',
  'Organic Traffic': 'ترافیک ارگانیک',
  'No traffic data': 'دادهٔ ترافیک موجود نیست',
  'Select a property in Settings to start syncing.': 'برای شروع همگام‌سازی، یک property در تنظیمات انتخاب کنید.',
  'Connect Google in Settings to begin.': 'برای شروع، گوگل را در تنظیمات متصل کنید.',
  'Top Opportunities': 'فرصت‌های برتر',
  'Opportunities appear after the first analysis run.': 'فرصت‌ها پس از نخستین اجرای تحلیل نمایش داده می‌شوند.',
  'All opportunities ▸': 'همهٔ فرصت‌ها ▸',
  'Top Risks': 'ریسک‌های مهم',
  'No active risks detected.': 'ریسک فعالی شناسایی نشده است.',
  'All alerts ▸': 'همهٔ هشدارها ▸',
  'Could not load the dashboard': 'داشبورد بارگذاری نشد',
  'clicks/mo': 'کلیک/ماه',
  'Search Console': 'سرچ‌کنسول',
  'Analytics 4': 'آنالیتیکس ۴',
  'PageSpeed': 'پیج‌اسپید',
  'AI': 'هوش مصنوعی',

  // Alerts page
  'Alert Center': 'مرکز هشدار',
  'No alerts': 'هشداری نیست',
  'New alerts appear here when traffic drops, rankings slip, or Core Web Vitals regress.':
    'هشدارهای جدید هنگام افت ترافیک، سقوط رتبه یا پسرفت Core Web Vitals اینجا نمایش داده می‌شوند.',
  'Acknowledge': 'تأیید',
  'Resolve': 'رفع شد',
  'Severity': 'شدت',
  'Message': 'پیام',
  'Raised': 'زمان ایجاد',
  'Status': 'وضعیت',
  'Actions': 'اقدام‌ها',

  // Settings page (section titles + key labels)
  'Google (Search Console + Analytics)': 'گوگل (سرچ‌کنسول + آنالیتیکس)',
  'AI Provider': 'ارائه‌دهندهٔ هوش مصنوعی',
  'Alert channels': 'کانال‌های هشدار',
  'Automatic updates': 'به‌روزرسانی خودکار',
  'Update automatically when a new version is released': 'با انتشار نسخهٔ جدید، به‌صورت خودکار به‌روزرسانی شود',
  'License': 'لایسنس',
  'Email': 'ایمیل',
  'Webhook URL': 'آدرس Webhook',
  'Slack webhook URL': 'آدرس وب‌هوک اسلک',
  'Telegram bot token': 'توکن ربات تلگرام',
  'Telegram chat id': 'شناسهٔ چت تلگرام',
  'Bale bot token': 'توکن ربات بله',
  'Bale chat id': 'شناسهٔ چت بله',
  'Bale (بله) messenger': 'پیام‌رسان بله',
  'Google Ads': 'گوگل ادز',
  'Developer token': 'توکن توسعه‌دهنده',
  'Customer ID': 'شناسهٔ مشتری',
  'Google Ads needs a developer token and the account’s customer id; it uses the same Google connection above.':
    'گوگل ادز به توکن توسعه‌دهنده و شناسهٔ مشتری اکانت نیاز دارد؛ از همان اتصال گوگل بالا استفاده می‌کند.',
  'Google Business Profile and Google Ads use the same Google connection — reconnect Google to grant the new permissions.':
    'پروفایل کسب‌وکار گوگل و گوگل ادز از همان اتصال گوگل استفاده می‌کنند — برای اعطای دسترسی‌های جدید، گوگل را دوباره متصل کنید.',
  'Email always works. Webhook, Slack, Telegram and Bale (بله) require a Pro license.':
    'ایمیل همیشه فعال است. وب‌هوک، اسلک، تلگرام و بله نیازمند لایسنس Pro هستند.',
  'Alert email': 'ایمیل هشدار',
  'Slack incoming webhook': 'وب‌هوک ورودی اسلک',
  'Telegram chat ID': 'شناسهٔ چت تلگرام',
  'White label': 'برند اختصاصی (White label)',
  'Agency (client mode)': 'آژانس (حالت کلاینت)',
  'Enterprise': 'سازمانی (Enterprise)',
  'Search Console Property': 'پراپرتی سرچ‌کنسول',
  'Analytics 4 Property': 'پراپرتی آنالیتیکس ۴',
  'PageSpeed Insights': 'پیج‌اسپید اینسایتس',
  'AI — Anthropic Claude': 'هوش مصنوعی — Claude',
  'AI — OpenAI': 'هوش مصنوعی — OpenAI',
  'AI — Google Gemini': 'هوش مصنوعی — Gemini',
  'AI — GapGPT (گپ‌جی‌پی‌تی)': 'هوش مصنوعی — گپ‌جی‌پی‌تی (GapGPT)',
  'Could not load connection state': 'وضعیت اتصال‌ها بارگذاری نشد',
  'Active': 'فعال',
  'Resolved': 'رفع‌شده',
  'Failed to load': 'بارگذاری ناموفق بود',
  'No active alerts': 'هشدار فعالی نیست',
  'No resolved alerts': 'هشدار رفع‌شده‌ای نیست',
  'All monitored conditions are healthy.': 'همهٔ شرایط پایش‌شده سالم هستند.',
  'Ack': 'تأیید',
  'Data sync': 'همگام‌سازی داده',
  'Data refreshes automatically once a day. Use this to pull the latest Search Console / Analytics numbers right now.':
    'داده‌ها روزی یک‌بار خودکار تازه می‌شوند. با این دکمه همین حالا آخرین آمار سرچ‌کنسول / آنالیتیکس را دریافت کنید.',
  'Sync now': 'همگام‌سازی الان',
  'Syncing…': 'در حال همگام‌سازی…',
  'running…': 'در حال اجرا…',
  'up to date': 'به‌روز',
  'Select a property first.': 'ابتدا یک پراپرتی انتخاب کنید.',

  // Content tools (Wave 1)
  'Brief': 'بریف',
  'Score': 'امتیاز',
  'Internal links': 'لینک‌های داخلی',
  'Audit': 'ممیزی',
  'Schema': 'اسکیما',
  'Meta & Gap': 'متا و شکاف محتوا',
  'This tool is not included in your plan': 'این ابزار در پلن شما نیست',
  'Upgrade your license to unlock it.': 'برای فعال‌سازی، لایسنس خود را ارتقا دهید.',
  'Select a post…': 'یک نوشته انتخاب کنید…',
  'SEO brief generator': 'تولید بریف سئو',
  'Enter a target keyword — you get a full writing brief: goal, keyword set, outline, FAQs, and the entities the article must cover. Demand evidence comes from your own Search Console queries.':
    'کلیدواژهٔ هدف را وارد کنید — یک بریف کامل نگارش می‌گیرید: هدف، مجموعهٔ کلمات، فهرست مطالب، پرسش‌های متداول و موجودیت‌هایی که مقاله باید پوشش دهد. شواهد تقاضا از کوئری‌های سرچ‌کنسول خودتان می‌آید.',
  'Target keyword (e.g. عمل فتق شکم)': 'کلیدواژهٔ هدف (مثلاً عمل فتق شکم)',
  'Generate brief': 'تولید بریف',
  'Writing…': 'در حال نوشتن…',
  'Generate': 'تولید',
  'Goal': 'هدف',
  'Keywords': 'کلیدواژه‌ها',
  'Outline': 'فهرست مطالب',
  'FAQ': 'پرسش‌های متداول',
  'Entities to cover': 'موجودیت‌هایی که باید پوشش داده شوند',
  'Demand evidence (your GSC queries)': 'شواهد تقاضا (کوئری‌های سرچ‌کنسول شما)',
  'Existing related posts (link to these)': 'نوشته‌های مرتبط موجود (به این‌ها لینک بدهید)',
  'Internal link suggestions': 'پیشنهاد لینک داخلی',
  'Pages that mention another page’s topic but don’t link to it yet. Pick a post, or leave empty for a site-wide pass over recent posts.':
    'صفحاتی که موضوع صفحهٔ دیگری را ذکر کرده‌اند ولی هنوز به آن لینک نداده‌اند. یک نوشته انتخاب کنید یا خالی بگذارید تا نوشته‌های اخیر کل سایت بررسی شوند.',
  'No suggestions': 'پیشنهادی نیست',
  'No unlinked topic mentions were found.': 'اشارهٔ بدون لینکی به موضوعات پیدا نشد.',
  'Anchor text': 'متن لنگر',
  'Link to': 'لینک به',
  'On-page audit': 'ممیزی درون‌صفحه‌ای',
  'Scans your published posts and pages for on-page problems. Results are cached for an hour.':
    'نوشته‌ها و برگه‌های منتشرشده را از نظر مشکلات درون‌صفحه‌ای بررسی می‌کند. نتایج یک ساعت کش می‌شوند.',
  'Re-scan': 'اسکن مجدد',
  'Scanning…': 'در حال اسکن…',
  'pages scanned': 'صفحه اسکن شد',
  'issues': 'مشکل',
  'No on-page issues found': 'مشکل درون‌صفحه‌ای پیدا نشد',
  'Schema generator': 'تولید اسکیما',
  'Generates Article + Breadcrumb JSON-LD for a post, plus FAQ schema from question-style headings (ending with ? or ؟). Saving injects it into the page head.':
    'برای نوشته، JSON-LD از نوع Article و Breadcrumb تولید می‌کند، به‌علاوهٔ اسکیمای FAQ از تیترهای سؤالی (که به ? یا ؟ ختم می‌شوند). با ذخیره، در head صفحه تزریق می‌شود.',
  'Save to page': 'ذخیره در صفحه',
  'Remove': 'حذف',
  'Saved — the JSON-LD is now printed on the page.': 'ذخیره شد — JSON-LD اکنون در صفحه چاپ می‌شود.',
  'FAQ question(s) detected in the content.': 'پرسش FAQ در محتوا پیدا شد.',
  'No FAQ-style headings detected — only Article and Breadcrumb will be generated.':
    'تیتر سؤالی پیدا نشد — فقط Article و Breadcrumb تولید می‌شوند.',
  'Optimization score': 'امتیاز بهینه‌سازی',
  'Scores a post against its target keyword (0–100): placement, structure, depth, FAQ, links, images. The number is deterministic — the optional AI pass only lists missing entities.':
    'نوشته را نسبت به کلیدواژهٔ هدف امتیاز می‌دهد (۰ تا ۱۰۰): جایگذاری کلمه، ساختار، عمق، FAQ، لینک‌ها و تصاویر. عدد قطعی و تکرارپذیر است — بخش اختیاری AI فقط موجودیت‌های جاافتاده را فهرست می‌کند.',
  'Target keyword': 'کلیدواژهٔ هدف',
  'Also check entity coverage with AI': 'پوشش موجودیت‌ها هم با هوش مصنوعی بررسی شود',
  'Calculate score': 'محاسبهٔ امتیاز',
  'Scoring…': 'در حال محاسبه…',
  'Missing entities:': 'موجودیت‌های جاافتاده:',
  'Covered:': 'پوشش‌داده‌شده:',
  'Meta generator': 'تولید متا',
  'Paste a page hash from the Winners & Losers or Opportunities tables to generate an intent-matched title and description, sized against SERP pixel limits.':
    'هش صفحه را از جدول برنده‌ها/بازنده‌ها یا فرصت‌ها اینجا بگذارید تا تایتل و توضیحات متناسب با intent تولید شود، با اندازه‌گیری پیکسلی SERP.',
  'Page hash': 'هش صفحه',
  'Title': 'تایتل',
  'Description': 'توضیحات',
  'may be truncated': 'ممکن است بریده شود',
  'Content gap analysis': 'تحلیل شکاف محتوا',
  'Topics you rank for on the fringe but have no dedicated page to serve.':
    'موضوعاتی که در حاشیهٔ آن‌ها رتبه دارید ولی صفحهٔ اختصاصی برایشان ندارید.',
  'Run analysis': 'اجرای تحلیل',
  'Analyzing…': 'در حال تحلیل…',
  'No clear gaps found': 'شکاف مشخصی پیدا نشد',
  'Your current pages cover the queries you rank for.': 'صفحات فعلی شما کوئری‌هایتان را پوشش می‌دهند.',
  'Could not generate': 'تولید ناموفق بود',
  'Could not analyze': 'تحلیل ناموفق بود',

  // Research (Wave 2)
  'Research': 'ریسرچ',
  'Clusters': 'کلاسترها',
  'Competitors': 'رقبا',
  'Keyword research': 'تحقیق کلیدواژه',
  'Discovers keywords from Google Autocomplete (free), People-Also-Ask and related searches (when a SerpApi key is set), and your own Search Console queries — impressions and position are your real numbers, not estimates.':
    'کلیدواژه‌ها را از اتوکامپلیت گوگل (رایگان)، سؤالات PAA و جست‌وجوهای مرتبط (وقتی کلید SerpApi ثبت باشد) و کوئری‌های سرچ‌کنسول خودتان کشف می‌کند — ایمپرشن و جایگاه، اعداد واقعی خود شما هستند، نه تخمین.',
  'Seed keyword (e.g. فتق شکم)': 'کلیدواژهٔ بذر (مثلاً فتق شکم)',
  'Researching…': 'در حال تحقیق…',
  'keywords found': 'کلیدواژه پیدا شد',
  'add a SerpApi key in Settings to also get People-Also-Ask questions': 'برای دریافت سؤالات PAA، کلید SerpApi را در تنظیمات اضافه کنید',
  'Keyword': 'کلیدواژه',
  'Intent': 'اینتنت',
  'Sources': 'منابع',
  'Impressions (yours)': 'ایمپرشن (شما)',
  'Position (yours)': 'جایگاه (شما)',
  'Topic cluster builder': 'سازندهٔ کلاستر موضوعی',
  'Runs keyword research on the seed topic, then plans one pillar page plus cluster articles with an internal-linking map. Existing posts are reused as “update” items instead of duplicates.':
    'اول روی موضوع بذر تحقیق کلیدواژه اجرا می‌کند، بعد یک صفحهٔ پیلار + مقالات کلاستر با نقشهٔ لینک داخلی می‌چیند. نوشته‌های موجود به‌جای تکرار، به‌عنوان «به‌روزرسانی» استفاده می‌شوند.',
  'Seed topic (e.g. فتق شکم)': 'موضوع بذر (مثلاً فتق شکم)',
  'Build cluster': 'ساخت کلاستر',
  'Planning…': 'در حال برنامه‌ریزی…',
  'Pillar': 'پیلار',
  'Update existing': 'به‌روزرسانی موجود',
  'Create new': 'ایجاد جدید',
  'Internal-linking plan': 'نقشهٔ لینک‌سازی داخلی',
  'Competitor overview': 'نمای کلی رقبا',
  'Checks the live SERP for your top 10 Search Console queries and shows which domains out-rank you most. Needs a SerpApi key (Settings → Enterprise card or any plan with a key saved). SERPs are cached 24h.':
    'SERP زندهٔ ۱۰ کوئری برتر سرچ‌کنسول شما را بررسی می‌کند و نشان می‌دهد کدام دامنه‌ها بیشتر از شما بالاترند. به کلید SerpApi نیاز دارد. نتایج SERP ۲۴ ساعت کش می‌شوند.',
  'Analyze competitors': 'تحلیل رقبا',
  'queries checked against': 'کوئری بررسی شد در برابر',
  'Competitor domain': 'دامنهٔ رقیب',
  'Times above you': 'دفعات بالاتر از شما',
  'Avg position': 'میانگین جایگاه',
  'Sample queries': 'نمونه کوئری‌ها',
  'SERP check for one query': 'بررسی SERP برای یک کوئری',
  'e.g. جراح فتق تهران': 'مثلاً جراح فتق تهران',
  'Check SERP': 'بررسی SERP',
  'Your position:': 'جایگاه شما:',
  'You are not in the top 10 for this query.': 'برای این کوئری در ۱۰ نتیجهٔ اول نیستید.',

  // Medical Pack (Wave 3)
  'Medical': 'پزشکی',
  'E-E-A-T': 'E-E-A-T',
  'Entities & Schema': 'موجودیت‌ها و اسکیما',
  'Knowledge Graph': 'گراف دانش',
  'The Medical Pack is a Pro feature': 'پک پزشکی یک قابلیت Pro است',
  'Upgrade your license to unlock medical E-E-A-T, entity detection, medical schema, and the knowledge graph.':
    'برای فعال‌سازی E-E-A-T پزشکی، تشخیص موجودیت، اسکیمای پزشکی و گراف دانش، لایسنس خود را ارتقا دهید.',
  'Medical E-E-A-T analyzer': 'تحلیلگر E-E-A-T پزشکی',
  'Agency trust analyzer': 'تحلیلگر اعتماد آژانس',
  'Scores a service/agency page against trust signals: contact info, pricing, portfolio, testimonials, about/experience, a clear call to action, and freshness.':
    'یک صفحهٔ خدمات/آژانس را بر اساس سیگنال‌های اعتماد امتیاز می‌دهد: اطلاعات تماس، تعرفه، نمونه‌کار، نظرات مشتریان، درباره ما/سابقه، فراخوان اقدام روشن، و به‌روز بودن.',
  'Scores a medical (YMYL) page against Google’s trust signals: named author, author bio, medical reviewer, authoritative citations, freshness, disclaimer, and topic depth.':
    'یک صفحهٔ پزشکی (YMYL) را بر اساس سیگنال‌های اعتماد گوگل امتیاز می‌دهد: نویسندهٔ مشخص، بیوگرافی نویسنده، بازبین پزشکی، استناد به منابع معتبر، به‌روز بودن، سلب مسئولیت و عمق موضوعی.',
  'Analyze': 'تحلیل',
  'No medical entities detected — is this a medical page?': 'موجودیت پزشکی تشخیص داده نشد — آیا این صفحه پزشکی است؟',
  'Medical entities & schema': 'موجودیت‌های پزشکی و اسکیما',
  'Detects the medical concepts a post covers, and generates MedicalWebPage schema (with those conditions/procedures) that you can inject into the page head. Physician / MedicalClinic come from the Settings card.':
    'مفاهیم پزشکی موجود در نوشته را تشخیص می‌دهد و اسکیمای MedicalWebPage (شامل همان بیماری‌ها/روش‌ها) تولید می‌کند که می‌توانید در head صفحه تزریق کنید. اطلاعات پزشک/کلینیک از کارت تنظیمات می‌آید.',
  'Save schema to page': 'ذخیرهٔ اسکیما در صفحه',
  'Saved — MedicalWebPage schema now prints on the page.': 'ذخیره شد — اسکیمای MedicalWebPage اکنون در صفحه چاپ می‌شود.',
  'No medical entities detected in this post.': 'در این نوشته موجودیت پزشکی تشخیص داده نشد.',
  'Medical knowledge graph': 'گراف دانش پزشکی',
  'Which medical concepts your whole site covers, how deeply, and which important concepts you have no page for yet.':
    'کل سایت چه مفاهیم پزشکی‌ای را پوشش می‌دهد، با چه عمقی، و برای کدام مفاهیم مهم هنوز صفحه‌ای ندارید.',
  'dictionary concepts covered': 'مفهوم از دیکشنری پوشش داده شده',
  'Not covered yet (content gaps)': 'هنوز پوشش داده نشده (شکاف محتوا)',
  'Medical mode (Medical Pack)': 'حالت پزشکی (پک پزشکی)',
  'Turn this on for medical/clinic sites to unlock E-E-A-T analysis, medical entity detection, medical schema, and the knowledge graph. Reload the dashboard after toggling.':
    'برای سایت‌های پزشکی/کلینیک این را روشن کنید تا تحلیل E-E-A-T، تشخیص موجودیت پزشکی، اسکیمای پزشکی و گراف دانش فعال شود. پس از تغییر، داشبورد را دوباره بارگذاری کنید.',
  'The Medical Pack requires a Pro license.': 'پک پزشکی نیازمند لایسنس Pro است.',
  'Enable medical mode': 'فعال‌سازی حالت پزشکی',
  'Physician & clinic (for schema)': 'پزشک و کلینیک (برای اسکیما)',
  'Physician name': 'نام پزشک',
  'Specialty (e.g. جراح عمومی)': 'تخصص (مثلاً جراح عمومی)',
  'Medical license no. (شماره نظام پزشکی)': 'شمارهٔ نظام پزشکی',
  'Clinic name': 'نام کلینیک',
  'Clinic phone': 'تلفن کلینیک',
  'Clinic address': 'آدرس کلینیک',
  'Specialty dictionary (sharpens entity detection)': 'دیکشنری تخصصی (تشخیص موجودیت را دقیق‌تر می‌کند)',
  'General medical': 'پزشکی عمومی',
  'Gastroenterology & hepatology (گوارش و کبد)': 'گوارش و کبد',
  'General & bariatric surgery (جراحی عمومی و چاقی)': 'جراحی عمومی و چاقی',
  'Hand, shoulder & elbow surgery (دست، شانه و آرنج)': 'جراحی دست، شانه و آرنج',
  'Dermatology, hair & cosmetic (پوست، مو و زیبایی)': 'پوست، مو و زیبایی',
  'Obstetrics & gynecology (زنان و زایمان)': 'زنان و زایمان',
  'Medical web design, branding & SEO': 'طراحی سایت، برندینگ و سئوی پزشکی',
  '1) Choose your specialty dictionary (sharpens entity detection)': '۱) ابتدا دیکشنری تخصصی را انتخاب کنید (تشخیص موجودیت را دقیق‌تر می‌کند)',
  '2) Enable medical mode': '۲) فعال‌سازی حالت پزشکی',

  // Site-vertical architecture (v0.14.0)
  'Business': 'کسب‌وکار',
  'Site type (vertical pack)': 'نوع سایت (پک تخصصی)',
  'Pick what this site is. Medical/clinic and Business/services each unlock a tailored screen (entity detection, trust analysis, schema, knowledge graph). Reload the dashboard after changing.':
    'مشخص کنید این سایت چیست. پزشکی/کلینیک و کسب‌وکار/خدمات هرکدام یک صفحهٔ اختصاصی باز می‌کنند (تشخیص موجودیت، تحلیل اعتماد، اسکیما، گراف دانش). پس از تغییر، داشبورد را دوباره بارگذاری کنید.',
  'The vertical pack requires a Pro license.': 'پک تخصصی نیازمند لایسنس Pro است.',
  'The vertical pack is a Pro feature': 'پک تخصصی یک قابلیت Pro است',
  'Site type': 'نوع سایت',
  'General (off)': 'عمومی (غیرفعال)',
  'Medical / clinic': 'پزشکی / کلینیک',
  'Business & services': 'کسب‌وکار و خدمات',
  'Specialty dictionary': 'دیکشنری تخصصی',
  'Business type': 'نوع کسب‌وکار',
  'Web design, branding & SEO agency': 'آژانس طراحی سایت، برندینگ و سئو',
  'Beauty salon / hairdresser': 'سالن زیبایی / آرایشگاه',
  'Lawyer / legal advisor': 'وکیل / مشاور حقوقی',
  'Gym / fitness': 'باشگاه / تناسب اندام',
  'Business (for LocalBusiness schema)': 'کسب‌وکار (برای اسکیمای LocalBusiness)',
  'Business name': 'نام کسب‌وکار',
  'Business phone': 'تلفن کسب‌وکار',
  'Business address': 'آدرس کسب‌وکار',
  'Entities & schema': 'موجودیت‌ها و اسکیما',
  'Knowledge graph': 'گراف دانش',
  // Vertical section nav labels (mirror the chosen site type).
  // 'Agency' is already defined above (main nav).
  'Salon': 'سالن زیبایی',
  'Legal': 'حقوقی',
  'Fitness': 'باشگاه',
  // Business-vertical entities & schema tool
  'Detects the business concepts a post covers, and generates WebPage schema (with those services/topics) that you can inject into the page head. The business (LocalBusiness) comes from the Settings card.':
    'مفاهیم کسب‌وکاری موجود در نوشته را تشخیص می‌دهد و اسکیمای WebPage (شامل همان خدمات/موضوعات) تولید می‌کند که می‌توانید در head صفحه تزریق کنید. اطلاعات کسب‌وکار (LocalBusiness) از کارت تنظیمات می‌آید.',
  'Removing…': 'در حال حذف…',
  'Saved — WebPage schema now prints on the page.': 'ذخیره شد — اسکیمای WebPage اکنون در صفحه چاپ می‌شود.',
  'Removed — schema no longer prints on the page.': 'حذف شد — اسکیما دیگر در صفحه چاپ نمی‌شود.',
  'No entities detected in this post.': 'در این نوشته موجودیتی تشخیص داده نشد.',
  'Which concepts your whole site covers, how deeply, and which important concepts you have no page for yet.':
    'کل سایت چه مفاهیمی را پوشش می‌دهد، با چه عمقی، و برای کدام مفاهیم مهم هنوز صفحه‌ای ندارید.',

  // Zombie pages
  'Zombies': 'صفحات زامبی',
  'Zombie pages': 'صفحات زامبی',
  'Pages that earn zero organic clicks in the window — they waste crawl budget and dilute site quality. Each gets a recommended fix.':
    'صفحاتی که در بازهٔ انتخابی هیچ کلیک ارگانیکی نمی‌گیرند — بودجهٔ خزش را هدر می‌دهند و کیفیت سایت را پایین می‌آورند. برای هرکدام یک اقدام پیشنهادی داده می‌شود.',
  'last 90 days': '۹۰ روز اخیر',
  'last 180 days': '۱۸۰ روز اخیر',
  'last 365 days': '۳۶۵ روز اخیر',
  'Search Console isn’t connected, so this uses content signals only (thin + orphan pages). Connect Google for click-based detection.':
    'سرچ‌کنسول متصل نیست، پس فقط از سیگنال‌های محتوایی (صفحات کم‌محتوا و یتیم) استفاده می‌شود. برای تشخیص بر اساس کلیک، گوگل را متصل کنید.',
  'zombie page(s)': 'صفحهٔ زامبی',
  'No zombie pages found': 'صفحهٔ زامبی‌ای پیدا نشد',
  'Every scanned page earns organic clicks — nice.': 'همهٔ صفحات اسکن‌شده کلیک ارگانیک می‌گیرند — عالی.',
  'Page': 'صفحه',
  'Clicks': 'کلیک',
  'Impr.': 'نمایش',
  'Words': 'کلمات',
  'In-links': 'لینک ورودی',
  'Recommended fix': 'اقدام پیشنهادی',
  'Edit': 'ویرایش',
  'Wait': 'صبر کنید',
  'Improve content': 'بهبود محتوا',
  'Rewrite title & meta': 'بازنویسی عنوان و متا',
  'Add internal links': 'افزودن لینک داخلی',
  'Prune (merge/301 or delete)': 'هرس (ادغام/۳۰۱ یا حذف)',
};

/** True when the wp-admin locale is Persian (fa_IR, fa_AF, …). */
export function isFa(): boolean {
  try {
    return boot().locale.toLowerCase().startsWith('fa');
  } catch {
    return false;
  }
}

/** Translate an English source string; falls back to the input untouched. */
export function t(source: string): string {
  return isFa() ? (FA[source] ?? source) : source;
}
