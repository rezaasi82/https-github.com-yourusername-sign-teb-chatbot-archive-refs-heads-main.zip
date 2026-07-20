import {
  Activity,
  BrainCircuit,
  FileText,
  HeartPulse,
  Lock,
  MessagesSquare,
  ScanEye,
  Stethoscope,
  Workflow,
} from "lucide-react";

export const NAV_LINKS = [
  { label: "Product", href: "#showcase" },
  { label: "Features", href: "#features" },
  { label: "Demo", href: "#demo" },
  { label: "Pricing", href: "#pricing" },
  { label: "FAQ", href: "#faq" },
];

export const HERO_STATS = [
  { value: 4.2, suffix: "M+", label: "Clinical decisions assisted" },
  { value: 98.6, suffix: "%", label: "Documentation accuracy" },
  { value: 11, suffix: "hrs", label: "Saved per clinician / week" },
  { value: 240, suffix: "+", label: "Health systems onboard" },
];

export const TRUST_BADGES = [
  "HIPAA Compliant",
  "SOC 2 Type II",
  "ISO 27001",
  "GDPR Ready",
  "FDA SaMD Track",
];

export const FEATURES = [
  {
    icon: BrainCircuit,
    title: "Clinical Reasoning Engine",
    description:
      "A medical-grade LLM that cross-references symptoms, labs, and history against 40M+ peer-reviewed studies — surfacing differentials in seconds, not hours.",
    size: "large" as const,
    glow: "primary" as const,
  },
  {
    icon: FileText,
    title: "Ambient Documentation",
    description:
      "Medora listens to the visit and writes the note. SOAP, H&P, discharge — signed and coded before you leave the room.",
    size: "small" as const,
    glow: "violet" as const,
  },
  {
    icon: ScanEye,
    title: "Imaging Co-Pilot",
    description:
      "Second-read AI for radiology that flags anomalies with pixel-level heatmaps and confidence scoring.",
    size: "small" as const,
    glow: "cyan" as const,
  },
  {
    icon: Workflow,
    title: "Care Pathway Automation",
    description:
      "Orders, referrals, and follow-ups triggered automatically from encounter context — fully auditable, always physician-approved.",
    size: "small" as const,
    glow: "cyan" as const,
  },
  {
    icon: MessagesSquare,
    title: "Patient Companion",
    description:
      "24/7 multilingual triage and aftercare chat that knows the care plan and escalates to humans the moment it should.",
    size: "small" as const,
    glow: "violet" as const,
  },
  {
    icon: Lock,
    title: "Zero-Trust PHI Vault",
    description:
      "End-to-end encryption, on-prem or private cloud deployment, and full audit trails. Your data never trains our models. Compliance isn't a feature — it's the foundation.",
    size: "large" as const,
    glow: "primary" as const,
  },
];

export const DEMO_VITALS = [
  { icon: HeartPulse, label: "Patient throughput", value: "+34%" },
  { icon: Activity, label: "Avg. note time", value: "48s" },
  { icon: Stethoscope, label: "Dx concordance", value: "97.1%" },
];

export const TESTIMONIALS = [
  {
    name: "Dr. Sarah Chen",
    role: "Chief of Emergency Medicine, Northview Health",
    quote:
      "Medora cut our documentation burden by 70%. My residents actually look at patients now instead of screens.",
    initials: "SC",
    hue: "from-blue-500 to-cyan-400",
  },
  {
    name: "Dr. Marcus Webb",
    role: "Radiologist, Atlas Imaging Group",
    quote:
      "The imaging co-pilot caught a 4mm nodule I'd flagged as review-later. That's the moment I stopped calling it a gadget.",
    initials: "MW",
    hue: "from-violet-500 to-fuchsia-400",
  },
  {
    name: "Amara Okafor",
    role: "CIO, Meridian Hospital Network",
    quote:
      "Deployment across 12 hospitals in six weeks, zero PHI incidents, and the first tech our physicians asked for more of.",
    initials: "AO",
    hue: "from-cyan-400 to-emerald-400",
  },
  {
    name: "Dr. Elena Rodriguez",
    role: "Family Medicine, Solano Clinic",
    quote:
      "I finish my charts before dinner now. My kids think Medora is a member of the family — honestly, fair.",
    initials: "ER",
    hue: "from-blue-400 to-violet-500",
  },
  {
    name: "James Park",
    role: "VP Clinical Ops, Helio Care",
    quote:
      "ROI was positive in month two. Nurse triage times dropped 41% and patient satisfaction hit an all-time high.",
    initials: "JP",
    hue: "from-cyan-500 to-blue-500",
  },
  {
    name: "Dr. Priya Nair",
    role: "Hospitalist, St. Auburn Medical",
    quote:
      "It reads the whole chart — every consult, every lab trend — and briefs me in 30 seconds before I walk in the door.",
    initials: "PN",
    hue: "from-fuchsia-500 to-violet-400",
  },
];

export const PRICING_PLANS = [
  {
    name: "Clinic",
    price: 149,
    period: "/clinician · month",
    description: "For independent practices ready to reclaim their evenings.",
    features: [
      "Ambient documentation",
      "Clinical reasoning engine",
      "Patient companion chat",
      "EHR integration (FHIR/HL7)",
      "Email & chat support",
    ],
    cta: "Start free trial",
    popular: false,
  },
  {
    name: "Health System",
    price: 289,
    period: "/clinician · month",
    description: "For hospitals and networks that run on outcomes.",
    features: [
      "Everything in Clinic",
      "Imaging co-pilot",
      "Care pathway automation",
      "Private cloud deployment",
      "Advanced analytics dashboard",
      "Dedicated success engineer",
    ],
    cta: "Book a demo",
    popular: true,
  },
  {
    name: "Enterprise",
    price: null,
    period: "custom",
    description: "For national networks, payers, and research institutions.",
    features: [
      "Everything in Health System",
      "On-prem deployment",
      "Custom model fine-tuning",
      "99.99% uptime SLA",
      "White-glove onboarding",
      "24/7 priority support",
    ],
    cta: "Talk to sales",
    popular: false,
  },
];

export const FAQS = [
  {
    question: "Is Medora AI HIPAA compliant?",
    answer:
      "Yes. Medora is HIPAA compliant, SOC 2 Type II certified, and ISO 27001 audited. PHI is encrypted end-to-end (AES-256 at rest, TLS 1.3 in transit), and we sign BAAs with every customer. Your data is never used to train our models.",
  },
  {
    question: "How does Medora integrate with our EHR?",
    answer:
      "Medora connects natively to Epic, Cerner, Athena, and 40+ other systems via FHIR R4 and HL7v2. Typical integration takes days, not months — our deployment team handles the interface work end-to-end.",
  },
  {
    question: "Does the AI make clinical decisions on its own?",
    answer:
      "Never. Medora is a decision-support copilot: every suggestion, order, and note requires explicit clinician review and sign-off. Full audit trails record what the AI proposed and what the physician approved.",
  },
  {
    question: "What languages does the Patient Companion support?",
    answer:
      "The patient-facing companion speaks 38 languages with medical-grade accuracy, including English, Spanish, Mandarin, Arabic, Farsi, and French — with automatic escalation to human staff when clinically indicated.",
  },
  {
    question: "How long does deployment take?",
    answer:
      "A single clinic can go live in under a week. Multi-hospital networks typically deploy in 4–8 weeks including EHR integration, security review, and clinician onboarding.",
  },
  {
    question: "Can we deploy on-premises?",
    answer:
      "Yes. Enterprise plans support fully air-gapped on-prem deployment as well as private cloud (AWS, Azure, GCP) with customer-managed keys.",
  },
];
