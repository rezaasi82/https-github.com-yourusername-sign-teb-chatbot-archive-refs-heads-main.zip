import { Github, Linkedin, Sparkles, Twitter } from "lucide-react";

const FOOTER_COLUMNS = [
  {
    heading: "Product",
    links: ["Clinical Reasoning", "Ambient Docs", "Imaging Co-Pilot", "Patient Companion", "Pricing"],
  },
  {
    heading: "Company",
    links: ["About", "Careers", "Press", "Partners", "Contact"],
  },
  {
    heading: "Resources",
    links: ["Documentation", "API Reference", "Clinical Evidence", "Security", "Status"],
  },
  {
    heading: "Legal",
    links: ["Privacy", "Terms", "BAA", "Compliance", "Cookies"],
  },
];

export default function Footer() {
  return (
    <footer className="relative border-t border-white/10">
      <div className="section-shell py-14 md:py-20">
        <div className="grid gap-12 lg:grid-cols-[1.2fr_2fr]">
          <div>
            <a href="#" className="flex items-center gap-2.5">
              <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-secondary shadow-glow">
                <Sparkles className="h-5 w-5 text-white" />
              </span>
              <span className="text-lg font-bold tracking-tight">
                Medora<span className="text-gradient"> AI</span>
              </span>
            </a>
            <p className="mt-4 max-w-xs text-sm leading-relaxed text-white/50">
              The intelligence layer for modern healthcare. Built with
              clinicians, for clinicians.
            </p>
            <div className="mt-6 flex gap-3">
              {[
                { icon: Twitter, label: "Twitter" },
                { icon: Linkedin, label: "LinkedIn" },
                { icon: Github, label: "GitHub" },
              ].map(({ icon: Icon, label }) => (
                <a
                  key={label}
                  href="#"
                  aria-label={label}
                  className="glass flex h-10 w-10 items-center justify-center rounded-xl transition-all duration-300 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-glow"
                >
                  <Icon className="h-4 w-4 text-white/70" />
                </a>
              ))}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-8 sm:grid-cols-4">
            {FOOTER_COLUMNS.map((column) => (
              <div key={column.heading}>
                <h3 className="text-sm font-semibold text-white/85">
                  {column.heading}
                </h3>
                <ul className="mt-4 space-y-2.5">
                  {column.links.map((link) => (
                    <li key={link}>
                      <a
                        href="#"
                        className="text-sm text-white/45 transition-colors duration-200 hover:text-white"
                      >
                        {link}
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>

        <div className="mt-14 flex flex-col items-center justify-between gap-4 border-t border-white/10 pt-8 sm:flex-row">
          <p className="text-xs text-white/40">
            © {new Date().getFullYear()} Medora AI, Inc. All rights reserved.
          </p>
          <p className="text-xs text-white/40">
            Medora is decision support — not a replacement for clinical judgment.
          </p>
        </div>
      </div>
    </footer>
  );
}
