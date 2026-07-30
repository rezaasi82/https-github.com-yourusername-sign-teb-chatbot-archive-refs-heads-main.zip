=== SEO Director AI ===
Contributors: rezaasiabi
Tags: seo, search console, analytics, ai, core web vitals
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 0.12.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI-powered SEO management platform that monitors, analyzes, prioritizes, explains, and recommends actions — a virtual SEO Director inside WordPress.

== Description ==

Most SEO plugins show data. SEO Director AI explains what happened, why it happened, and what to do next — with prioritized, ROI-ranked action plans.

* Google Search Console, Google Analytics 4, and PageSpeed Insights integration
* Google Business Profile (local SEO), Google Ads, and Google Trends integrations
* SEO Health Score with transparent sub-scores
* Winners & Losers with AI-explained causes and confidence scores
* Opportunity detection (striking-distance keywords, low-CTR pages, snippets, FAQs, schema, internal links)
* Weekly / monthly / quarterly roadmaps with impact, difficulty, and priority
* Alert Center for traffic drops, ranking losses, CWV regressions, and indexing problems
* Automated stakeholder reports (PDF, Excel, CSV, Google Sheets/Docs/Drive)
* Agency mode with white-label branding
* RTL-first UI with full Persian, Arabic, and English support
* Dark and light mode

== External services ==

This plugin connects to the following external services, only after you explicitly connect them:

* Google Search Console API, Google Analytics Data API, PageSpeed Insights API, Google Business Profile APIs, Google Ads API, and Google Trends — to fetch your own site's and listings' performance data (Google's terms: https://policies.google.com/terms)
* Alert channels you enable (webhook, Slack, Telegram, Bale/بله) — to deliver alert digests you configured
* Your chosen AI provider (OpenAI, Anthropic Claude, Google Gemini, or GapGPT) — to generate explanations and recommendations from aggregated metrics you choose to analyze
* The SEO Director license server — for license activation and update delivery

No data is sent anywhere until you connect a service. Disconnecting stops all traffic to that service.

== Installation ==

1. Upload the plugin and activate it.
2. Open "SEO Director" in the admin menu and follow the setup wizard.

== Changelog ==

= 0.12.6 =
* Medical Pack: added an "Obstetrics & gynecology (زنان و زایمان)" dictionary preset — pregnancy/childbirth (بارداری، سزارین، زایمان طبیعی، دیابت بارداری، پره‌اکلامپسی), gynecologic conditions (کیست تخمدان، تخمدان پلی‌کیستیک، فیبروم، اندومتریوز، عفونت واژن), fertility (IUI/IVF/میکرواینجکشن، فریز تخمک), and procedures (لاپاراسکوپی، هیستروسکوپی، پاپ اسمیر، هیسترکتومی، لابیاپلاستی/جوانسازی). Merged onto the general base.

= 0.12.5 =
* Medical Pack: added a "Dermatology, hair & cosmetic (پوست، مو و زیبایی)" dictionary preset — one of the most competitive Persian niches. Covers skin conditions (آکنه، ملاسما، ویتیلیگو، پسوریازیس، ریزش مو، روزاسه…) and the full aesthetic-procedure set (بوتاکس، فیلر، مزوتراپی، لیزر موهای زائد، هایفو، میکرونیدلینگ، کاشت مو، لیفت با نخ، رینوپلاستی، لیپوماتیک، پیلینگ، هیدرافیشیال…). Common Persian + transliterated names indexed. Merged onto the general base.

= 0.12.4 =
* Medical Pack: added a "Hand, shoulder & elbow surgery (جراحی دست، شانه و آرنج)" dictionary preset for upper-limb / hand-surgery fellows — carpal tunnel (تونل کارپال), rotator-cuff tears (پارگی روتاتور کاف), tennis/golf elbow (تنیس البو/گلف البو), frozen shoulder (شانه یخ‌زده), trigger finger, ganglion, De Quervain, plus procedures (آرتروسکوپی، ترمیم تاندون، آزادسازی تونل کارپال، تزریق PRP) and upper-limb anatomy. Merged onto the general base.

= 0.12.3 =
* Medical Pack: added a "General & bariatric surgery (جراحی عمومی و چاقی)" dictionary preset for hernia / gallbladder / weight-loss surgeons (the ایران‌هرنیا niche) — hernia repair (فتق اینگوینال/ناف/هیاتال + mesh), gallbladder surgery (کوله‌سیستکتومی), and bariatric procedures (اسلیو معده، بای‌پس، مینی بای‌پس، پلیکیشن معده، بالون معده). Common Persian spellings are both indexed (بای‌پس / بای پس، پلیکیشن / پلیکاسیون). Merged onto the general base so general symptoms and body parts are still detected.

= 0.12.2 =
* Medical Pack: added a "Medical web design, branding & SEO" dictionary preset built for agencies like SignTeb that serve the medical field. Instead of clinical terms it recognizes services (طراحی سایت پزشکی، سئو پزشکی، برندینگ پزشکی، لندینگ پیج، نوبت‌دهی آنلاین…), SEO vocabulary, branding, platforms (وردپرس/المنتور/ووکامرس), and target audiences — so the knowledge graph shows which services you have and haven't covered on your own agency site. These non-clinical categories feed entity detection and the knowledge graph but are intentionally excluded from clinical MedicalWebPage schema.

= 0.12.1 =
* Medical Pack: specialty dictionary presets. A new "Specialty dictionary" selector in the medical settings lets a clinic sharpen entity detection for its niche. Ships with a General preset and a deep Gastroenterology & hepatology (گوارش و کبد) preset — dozens of gut/liver-specific conditions, symptoms, procedures, and drugs. The selected preset feeds the entity engine, MedicalWebPage schema, and the knowledge graph. Sites can still extend any preset via the sda_medical_dictionary filter (now also passed the active preset slug).

= 0.12.0 =
Wave 3 — the Medical Pack, the differentiator for Persian medical/YMYL sites. Turn on "Medical mode" in Settings (Pro license) to reveal a new Medical screen with three tools:
* Medical E-E-A-T Analyzer: scores a medical page 0–100 against Google's YMYL trust signals — named author (not "admin"), author bio, medical reviewer, citations to authoritative sources (WHO, PubMed, Mayo Clinic, the Iranian Medical Council, and more), freshness, disclaimer, and topic depth — with a per-check breakdown. Deterministic.
* Medical Entity Engine + Schema: detects the diseases, symptoms, treatments, drugs, specialties, and body parts a post covers (Persian dictionary, filterable via sda_medical_dictionary), and generates MedicalWebPage schema listing them; site-level Physician + MedicalClinic schema is built from your Settings and printed on the front page.
* Medical Knowledge Graph: aggregates entity coverage across the whole site — which concepts you cover and how deeply — and lists dictionary concepts you have no page for yet (content gaps).
* New Settings card: medical mode toggle plus physician name / specialty / medical-license number and clinic name / phone / address (feed the schema).
* New sda/v1 endpoints under /medical/*. New feature key: medical_pack (Pro). The whole pack is hidden unless medical mode is enabled, so non-medical sites stay clean.

= 0.11.0 =
Wave 2 of the SEO Operating System — a new Research screen with three tools:
* Keyword Research (Starter+): discovers keywords from Google Autocomplete (free, question/commercial prefix expansion in Persian and English), People-Also-Ask and related searches when a SerpApi key is set, and your own Search Console queries. Deterministic intent classification (informational/commercial/transactional/local). Impressions and position come from your real GSC data — no fabricated volume numbers.
* Topic Cluster Builder (Pro): runs keyword research on a seed topic, then plans a pillar page + cluster articles with an internal-linking map; existing posts are reused as "update" items instead of proposing duplicates.
* Competitor Intelligence (Pro, needs a SerpApi key): checks the live SERP for your top 10 GSC queries and aggregates which domains out-rank you most (appearances, average position, sample queries), plus a single-query SERP view with your row highlighted. SERPs cached 24h to control API cost.
* New sda/v1 endpoints: /research/keywords, /research/cluster, /research/competitors, /research/serp. New feature keys: keyword_research (Starter), topic_clusters + competitor_intel (Pro).

= 0.10.0 =
Wave 1 of the SEO Operating System — five new content tools under the Content screen, all working on your own WordPress content with no extra API costs:
* AI SEO Brief Generator (Pro): enter a target keyword and get a full writing brief — goal, keyword set, H2/H3 outline, FAQs, entities to cover — grounded in your own Search Console demand data and existing related posts.
* Internal Linking Engine (Starter+): finds pages that mention another page's topic but don't link to it, and proposes anchor → target pairs. Persian-aware matching; per-post or site-wide.
* Schema Generator (Starter+): one-click Article + Breadcrumb JSON-LD per post, plus FAQPage schema auto-extracted from question-style headings (? or ؟); saved schema is injected into the page head.
* On-page Auditor (Starter+): scans all published posts/pages for short/long titles, missing meta descriptions, H1-in-content, thin content, missing headings, images without alt, and no internal links — worst pages first.
* Content Optimization Score (Pro): deterministic 0–100 score of a post against its target keyword (placement, structure, depth, FAQ, links, images) with a per-check breakdown, plus an optional AI entity-coverage list.
* The Content screen is now tabbed (Brief / Score / Internal links / Audit / Schema / Meta & Gap) and fully bilingual.

= 0.9.1 =
* New "Sync now" button in Settings → Data sync: kicks the Search Console + Analytics sync immediately instead of waiting for the daily cron. Safe to press repeatedly — a sync already in flight is never duplicated. The card shows live per-service status while the sync runs.

= 0.9.0 =
* Bilingual UI: the dashboard now renders in Persian when the WordPress admin language is Persian, and in English otherwise (shell, overview, alerts, and settings; more screens each release).
* New alert channel: Bale (بله) messenger — reachable from Iranian hosts without a VPN; configured with a bot token + chat id like Telegram.
* New integrations: Google Business Profile (accounts, locations, daily local-pack metrics), Google Ads (campaign performance and paid search terms via your developer token), and Google Trends (interest-over-time, no key needed). PageSpeed Insights was already built in.
* Automatic updates: the plugin now checks the SignTeb update server and can update itself in the background (toggle in Settings).
* Plugin authorship updated: Reza Asiabi (رضا آسیابی), homepage signteb.com.

= 0.8.2 =
* Fix: the admin dashboard rendered blank on real (subdirectory) WordPress installs. The code-split per-route chunks resolved against the site root and 404'd, and because every route was lazy-loaded the whole screen stayed empty. The admin app now ships as a single bundle (with a relative asset base), so there are no separate chunk requests to misresolve.
* Added a top-level error boundary: if the app ever fails to start, it now shows the error message instead of a blank screen, so problems can be diagnosed in the field.

= 0.8.1 =
* Added GapGPT (gapgpt.app) as an AI provider — an OpenAI-compatible gateway that is easier to pay for and reach from Iran, so users there can enable AI features without a VPN or foreign card. Selectable as the preferred provider and joins the automatic fallback chain.

= 0.8.0 =
* Onboarding: a fresh install now shows a clearly-labelled sample dashboard (health, traffic, opportunities, risks) instead of empty panels, so buyers see the plugin's value immediately. Sample data disappears automatically after the first real sync.
* Setup checklist on the dashboard tracks connecting Google, choosing a property, adding an AI provider, and the first sync.
* New demo_mode setting (auto | off) to control the sample-data experience.

= 0.7.0 =
* Enterprise: brand-voice profiles (tone, audience, style notes, words to avoid) injected into every AI generation, so explanations and copy match your house style.
* Enterprise: SLA alerting — critical/high alerts left open past a configurable threshold escalate once to all channels.
* Enterprise: task sync — push roadmap tasks to Jira or Trello with one click; idempotent so re-syncing never duplicates.
* Enterprise: SERP-enriched root cause — an optional SerpApi lookup adds SERP-feature causes (AI overview, ads, snippets) to declining-query analysis.
* New Enterprise settings panel and a per-feature upsell for locked capabilities.

= 0.6.0 =
* Lite build: a free WordPress.org edition (GSC overview + health score only), gated by the SDA_LITE build flag, with an in-dashboard upgrade funnel to the paid plans.
* Upgrade funnel: locked features now show a clear upsell in place of the screen, plus an edition badge with an upgrade link in the toolbar.
* Performance: the admin app is code-split per screen, cutting the initial bundle by ~20% — the rest load on navigation.
* i18n: full translation template at languages/seo-director-ai.pot, regenerated with bin/make-pot.php.
* Hardening: settings API secret redaction, "silence is golden" directory stubs, client-role cleanup on uninstall, and a documented security review (docs/08-security-hardening.md).

= 0.5.0 =
* Agency Hub: pair client sites over HMAC-signed snapshot push, with a sites grid showing each client's health, 28-day traffic, and active alerts at a glance. Pairing keys are shown once and stored encrypted.
* Client mode: a daily job pushes a compact, sanitized snapshot to the configured hub; no-op unless a hub URL and pairing key are set.
* White-label engine: brand name, logo, primary color, "powered by" toggle, and terminology overrides for reselling agencies (Agency license).
* Client read-only role (SEO Client) for giving clients dashboard/report access without settings control.
* Security: settings API no longer echoes secret fields (pairing key, license secret) — only a "which secrets are set" flag; snapshot ingest is replay-protected and whitelisted.

= 0.4.0 =
* Licensing engine: self-service activation/deactivation, editions (Starter/Pro/Agency/Enterprise), FeatureGate, 14-day grace period, and 21-day server-outage tolerance — Pro features pause after grace but data always stays readable.
* Full opportunity set: featured-snippet, FAQ, and schema detectors plus keyword cannibalization and internal-link ratio analysis (Pro).
* Alert channels: webhook, Slack, and Telegram delivery alongside email (Pro), with SSRF-validated webhook targets.
* Content Strategist: AI meta title/description generation with SERP pixel sizing, and content gap analysis (Pro).
* Automated reports: HTML and CSV renderers, weekly/monthly/quarterly builder, scheduled delivery, and a downloadable report log.

= 0.3.0 =
* AI layer: pluggable OpenAI/Claude/Gemini providers with fallback chain, token budget, evidence-hash cache, and strict JSON schema validation.
* Root-cause engine (deterministic candidates + AI ranking with confidence), on-demand explanations, roadmap generator, and AI weekly summaries.

= 0.2.0 =
* Deterministic intelligence: trend/changepoint analyzers, SEO Health Score, Winners/Losers, opportunity detectors, and the Alert Center.

= 0.1.0 =
* Phase 0 foundation: plugin core, database schema, job framework, REST API scaffold, React dashboard shell.
