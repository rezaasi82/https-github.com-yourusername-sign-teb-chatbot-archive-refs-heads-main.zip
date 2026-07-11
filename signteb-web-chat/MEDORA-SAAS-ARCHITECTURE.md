# Medora AI — SaaS Transformation: Architecture, Audit & Roadmap

> سند معماری و نقشه‌راه تبدیل Medora AI از یک افزونه‌ی چت‌بات به یک اکوسیستم SaaS تجاری دوسطحی.
> این سند «قبل از تغییر فایل‌ها» تهیه شده و مبنای فازبندی توسعه است. اصل حاکم: **هیچ قابلیت موجودی نباید بشکند؛ همه‌چیز افزایشی و ماژولار است.**

نسخه‌ی پایه هنگام نگارش: **v3.1.0**

---

## 0) وضعیت فعلی (Dependency Map)

```
signteb-web-chat.php  (bootstrap: constants, autoloader, activation/deactivation, plugins_loaded)
        │
        └── SWC_Autoloader (class-map, یک کلاس = یک فایل)
                    │
        SWC_Plugin::boot()  ── wires everything
        ├── SWC_Chat_Controller ─────► SWC_AI_Manager
        ├── SWC_Export_Controller ───► SWC_Export_Manager
        ├── SWC_Chat_Ajax_Handler ───► SWC_AI_Manager
        ├── SWC_Export_Manager (cron + swc_lead_detected)
        ├── [admin] SWC_Premium_Dashboard (+ integrity gate)
        ├── [admin] SWC_Admin_Menu ──► SWC_Settings_Page ─► {Conversations, Stats}
        ├── [admin] SWC_Export_Ajax_Handler
        └── [front] SWC_Widget ──► templates/widget.php

AI layer
  SWC_AI_Manager
    ├── SWC_Provider_Factory ─► SWC_AI_Provider_Interface
    │        ├── SWC_Provider_Anthropic
    │        └── SWC_OpenAI_Compatible_Provider ─► {OpenAI, GapGPT}
    ├── SWC_System_Prompt_Builder
    ├── SWC_Language_Detector · SWC_Cta_Detector
    ├── SWC_Lead_Scorer · SWC_Summary_Builder
    ├── SWC_Medical_Safety_Filter
    └── SWC_Rate_Limiter

Data layer (Repository pattern)
  SWC_Schema  (source of truth for table names + dbDelta)
    ├── SWC_Conversation_Repository   (swc_conversations)  ← "leads"
    ├── SWC_Message_Repository        (swc_messages)
    ├── SWC_Event_Repository          (swc_events)         ← channel clicks
    └── SWC_Sync_Log_Repository       (swc_sync_logs)      ← export attempts

Export / integrations
  SWC_Lead_Payload (normalized DTO) → {PDF_Generator, Webhook_Manager, Google_Sheets}
  SWC_Export_Logger · SWC_Sync_Status

Core services
  SWC_Settings · SWC_Encryption (AES-256-CBC) · SWC_Json_Guard · SWC_License_Manager
```

**نقاط قوت فعلی:** معماری interface-driven، Repository pattern، autoloader صریح، کلیدها رمزنگاری‌شده، بارگذاری مشروط asset، امنیت پایه‌ی خوب (nonce/cap/prepared SQL)، تک‌منبع بودن داده‌ی لید.

---

## 1) Audit — نقاط ضعف و ریسک‌ها

### 1.1 امنیت (Security Audit)
| مورد | وضعیت | اقدام |
|---|---|---|
| Nonce/Capability روی endpointها | ✅ موجود | حفظ |
| Prepared SQL | ✅ | حفظ؛ ممیزی هر `{$table}` interpolation (فقط نام جدولِ داخلی است) |
| رمزنگاری کلیدها (AES-256-CBC) | ✅ | ارتقا به AES-256-**GCM** (auth-tag) در فاز امنیت |
| Webhook HMAC | ✅ | حفظ؛ افزودن timestamp + replay-protection |
| Rate limiting | ⚠️ فقط چت عمومی | گسترش به login/export/telemetry + brute-force lockout |
| Audit log / security events | ❌ ندارد | جدول `swc_audit_logs` + رویدادنگاری |
| Secure download token | ⚠️ نام غیرقابل‌حدس | افزودن توکن یک‌بارمصرف امضاشده با انقضا |
| PII در لاگ | ✅ محافظت‌شده (WP_DEBUG only) | حفظ + ماسک‌کردن شماره/ایمیل |

### 1.2 کارایی (Performance Audit) — هدف: 10k+ لید / 100k+ پیام
| گلوگاه | تحلیل | راه‌حل |
|---|---|---|
| لیست لیدها: N کوئری وضعیت به‌ازای هر ردیف (`SWC_Sync_Status::for_lead` در حلقه) | O(rows) کوئری اضافه | batch: یک کوئری `latest per (lead,provider)` برای کل صفحه |
| `top_questions` با `GROUP BY LEFT(content,80)` روی LONGTEXT | full scan روی پیام‌ها | جدول تجمیعی `swc_analytics` + محاسبه‌ی روزانه در cron |
| نبود کش | هر بار محاسبه‌ی آمار | transient cache (۵–۱۵ دقیقه) + object cache |
| Export همزمان در حلقه‌ی bulk | بلاک‌کننده روی درخواست ادمین | صف پس‌زمینه (Action Scheduler / cron batch) |
| ایندکس‌ها | خوب ولی ناقص برای CRM | افزودن `idx_lead_status`, composite `(is_lead, created_at)` |

### 1.3 دیتابیس (Database Audit)
- `swc_conversations` عملاً «leads» است اما نام‌گذاری آن را پنهان می‌کند → یک **View/Facade به‌نام lead** بدون مهاجرت مخرب (backward-compatible).
- فقدان فیلدهای CRM: `email, lead_status, notes, tags, owner_id, branch_id`.
- فقدان جداول: `swc_analytics`, `swc_notifications`, `swc_license_logs`, `swc_cloud_events`, `swc_audit_logs`, `swc_branches`.
- `summary` به‌صورت متن آزاد ذخیره می‌شود؛ برای multi-tenant باید structured باشد (فاز ۲).

### 1.4 مقیاس‌پذیری (Scalability)
- Single-site الان کافی است؛ برای multi-clinic نیاز به `branch_id` روی همه‌ی جدول‌ها + scope در repositoryها.
- Level 2 (Cloud) باید **کاملاً جدا** از افزونه باشد (سرویس مستقل)، افزونه فقط heartbeat می‌فرستد.

---

## 2) معماری هدف (Two-Level)

```
┌────────────────────────── LEVEL 1: On-site (WordPress Plugin) ──────────────────────────┐
│  Widget → AI Engine → Leads(CRM) → Analytics → Export(PDF/Sheets/Webhook)                │
│  Premium Dashboard (HubSpot-like): Overview · CRM Kanban · Funnel · Revenue · SEO center │
│  Multi-clinic (branch scoping)                                                           │
│                          │ encrypted, signed heartbeat (opt-in)                          │
└──────────────────────────┼──────────────────────────────────────────────────────────────┘
                           ▼
┌────────────────────────── LEVEL 2: Medora Cloud (separate service) ──────────────────────┐
│  cloud.medora.ai / license.signteb.com                                                   │
│  Install registry · Heartbeat ingest · Realtime monitor · License server (plans)         │
│  Telegram/WhatsApp alerts · Email digests · Auto-update feed · Global analytics           │
└──────────────────────────────────────────────────────────────────────────────────────────┘
```

**قاعده‌ی طلایی جداسازی:** منطق تجاری/لایسنس در Cloud است، نه در افزونه. افزونه فقط: (۱) heartbeat می‌فرستد، (۲) پاسخ امضاشده‌ی وضعیت لایسنس را کش و اعمال می‌کند، (۳) فید آپدیت را چک می‌کند. این دقیقاً همان decoupling است که ضدکپی را عمیق نگه می‌دارد.

---

## 3) ERD هدف (v3)

```
swc_branches (NEW)        1───∞  swc_conversations (leads)     1───∞ swc_messages
  id, name, doctor,               + email, lead_status, notes,        conversation_id, role,
  phone, address,                   tags, owner_id, branch_id,        content, tokens, created_at
  created_at                        (existing: patient_*, lead_score,
                                     summary, pdf_url, booking_status)
                                          │ 1───∞ swc_events (clicks)
                                          │ 1───∞ swc_sync_logs (exports)
                                          │ 1───∞ swc_lead_notes (NEW, optional timeline)

swc_analytics (NEW)   day, branch_id, metric, value            ← rollup برای داشبورد سریع
swc_notifications(NEW) id, channel, type, payload, status, created_at
swc_license_logs (NEW) id, event, status, domain_hash, created_at
swc_cloud_events (NEW) id, type, payload, sent_at, ack
swc_audit_logs   (NEW) id, user_id, action, object, ip, created_at
```

سازگاری عقب‌رو: هیچ ستون/جدولی حذف نمی‌شود؛ همه‌ی موارد `ADD COLUMN/CREATE TABLE` از طریق dbDelta با bump نسخه‌ی DB.

---

## 4) نقشه‌راه فازبندی‌شده (v2 → v3)

| فاز | عنوان | محتوا | ریسک |
|---|---|---|---|
| **P0** | Analysis & Roadmap | همین سند | — |
| **P1 (این نسخه)** | CRM Core + Cloud spine | فیلدهای CRM (status/email/notes/tags)، Pipeline/Funnel، heartbeat client (opt-in) | پایین |
| P2 ✅ | Dashboard SaaS UI | Overview کارت‌ها + بازه‌ی زمانی + Revenue/ROI + Kanban drag&drop | انجام شد (v3.3) |
| P3 ✅ | Performance & DB | `swc_analytics` rollup + cron، batch status (رفع N+1)، `SWC_Cache`، صف پس‌زمینه (`swc_jobs`)، ایندکس‌های ترکیبی | انجام شد (v3.4) |
| P4 | Multi-clinic | `swc_branches` + branch scoping در repositoryها + آمار جداگانه | متوسط |
| P5 | Security hardening | audit log، GCM، rate-limit توسعه، replay-protection، secure tokens | متوسط |
| P6 | SEO Intelligence | استخراج FAQ/کلمات کلیدی/موضوعات از گفتگوها + پیشنهاد محتوا | پایین |
| P7 | Cloud Platform | سرویس مستقل: install registry، realtime monitor، Telegram/WhatsApp، email digest | بالا (خارج از افزونه) |
| P8 | License Server + Auto-update | پلن‌ها (Starter/Pro/Clinic/Enterprise)، domain lock، grace، update feed، rollback، integrity | بالا |

---

## 5) طراحی Level 2 (خلاصه‌ی مهندسی)

### 5.1 Heartbeat (سمت افزونه — در P1 پیاده شد)
- فعال‌سازی: ثبت install (domain hash، نسخه‌ی افزونه/WP/PHP، locale، timezone).
- هر ۲۴ ساعت (cron `swc_cloud_heartbeat`): شمارش پیام/لید/بوکینگ/خطا + وضعیت لایسنس.
- امنیت: بدنه JSON، امضای `X-Medora-Sign: sha256=HMAC(body, site_secret)` + timestamp؛ ارسال فقط وقتی ادمین **opt-in** کرده و endpoint تنظیم شده باشد. پیش‌فرض: خاموش (no-op).
- Privacy: هیچ داده‌ی بیمار ارسال نمی‌شود؛ فقط شمارنده‌ها و متادیتای فنی + hash دامنه.

### 5.2 License Server (P8)
- پاسخ امضاشده (Ed25519/HMAC): `{status, plan, expires, features[], grace}`.
- افزونه پاسخ را کش می‌کند (روزانه)، در قطعی سرور از کش استفاده می‌کند (عدم قفل فوری).
- `SWC_License_Manager::is_active()` نقطه‌ی واحد enforcement؛ کاملاً decoupled از هوک‌های عمومی.

### 5.3 Cloud API (طرح REST)
```
POST /v1/install            ثبت اولیه (heartbeat نوع install)
POST /v1/heartbeat          تله‌متری روزانه
GET  /v1/license/{domain}   وضعیت لایسنس (امضاشده)
GET  /v1/update/latest      فید نسخه + checksum
POST /v1/alert              (داخلی) صف Telegram/Email
```

---

## 6) REST Routes فعلی + جدید

فعلی: `signteb-web-chat/v1/message`, `/event` · `medora/v1/leads`, `/lead/{id}`, `/export/{pdf,webhook,google-sheet}`

افزوده در P1: AJAX `swc_lead_update` (CRM). برنامه‌ی P2+: `medora/v1/crm/*`, `medora/v1/analytics/*`.

---

## 7) گزارش امنیت و کارایی (خلاصه‌ی اجرایی)
- **Security posture: خوب**؛ بدون آسیب‌پذیری بحرانی شناسایی‌شده. بهبودها: audit log، GCM، replay-protection، توسعه‌ی rate-limit. (P5)
- **Performance: مناسب برای <چند هزار لید**؛ برای 10k+ نیاز به rollup analytics + batch queries + caching + background queue. (P3)

---

## 8) این نسخه (P1) — چه چیزی واقعاً اضافه شد
1. **CRM lead pipeline**: ستون‌های `email, lead_status, notes, tags` روی conversations (سازگار عقب‌رو، dbDelta)؛ واژگان وضعیت (New→Contacted→Follow-up→Booked→Visited→Closed/Lost)؛ ذخیره‌ی امن با nonce+cap؛ پنل CRM روی صفحه‌ی تک‌لید؛ نوار Funnel روی داشبورد پریمیوم.
2. **Cloud heartbeat client (اسپاین Level 2)**: `SWC_Cloud_Client` — opt-in، cron روزانه، امضاشده، بدون ارسال داده‌ی بیمار، no-op امن وقتی خاموش است.

بقیه‌ی موارد طبق جدول فازها به‌ترتیب و بدون شکستن چیزی اضافه می‌شوند.
