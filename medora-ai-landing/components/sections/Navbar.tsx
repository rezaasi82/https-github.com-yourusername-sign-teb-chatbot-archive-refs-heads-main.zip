"use client";

import { AnimatePresence, motion, useScroll } from "framer-motion";
import { Menu, Sparkles, X } from "lucide-react";
import { useEffect, useState } from "react";
import { NAV_LINKS } from "@/lib/data";
import { cn } from "@/lib/utils";

export default function Navbar() {
  const [scrolled, setScrolled] = useState(false);
  const [open, setOpen] = useState(false);
  const { scrollY } = useScroll();

  useEffect(() => {
    return scrollY.on("change", (y) => setScrolled(y > 24));
  }, [scrollY]);

  return (
    <motion.header
      initial={{ y: -80, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ duration: 0.7, ease: [0.21, 0.65, 0.28, 0.99] }}
      className="fixed inset-x-0 top-0 z-50"
    >
      <nav
        className={cn(
          "mx-auto mt-3 flex max-w-6xl items-center justify-between rounded-2xl px-5 py-3 transition-all duration-500 sm:px-6",
          scrolled ? "glass-strong mx-4 sm:mx-auto" : "bg-transparent"
        )}
        aria-label="ناوبری اصلی"
      >
        <a href="#" className="flex items-center gap-2.5">
          <span className="relative flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-secondary shadow-glow">
            <Sparkles className="h-5 w-5 text-white" />
          </span>
          <span className="text-lg font-bold">
            مدورا <span className="text-gradient">AI</span>
          </span>
        </a>

        <ul className="hidden items-center gap-8 md:flex">
          {NAV_LINKS.map((link) => (
            <li key={link.href}>
              <a
                href={link.href}
                className="text-sm text-white/65 transition-colors duration-200 hover:text-white"
              >
                {link.label}
              </a>
            </li>
          ))}
        </ul>

        <div className="hidden items-center gap-3 md:flex">
          <a
            href="#pricing"
            className="text-sm font-medium text-white/70 transition-colors hover:text-white"
          >
            ورود
          </a>
          <a
            href="#cta"
            className="rounded-full bg-gradient-to-r from-primary to-secondary px-5 py-2.5 text-sm font-semibold shadow-glow transition-shadow hover:shadow-glow-violet"
          >
            شروع کنید
          </a>
        </div>

        <button
          className="glass rounded-xl p-2 md:hidden"
          onClick={() => setOpen((v) => !v)}
          aria-label={open ? "بستن منو" : "باز کردن منو"}
          aria-expanded={open}
        >
          {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
        </button>
      </nav>

      <AnimatePresence>
        {open ? (
          <motion.div
            initial={{ opacity: 0, y: -12 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -12 }}
            transition={{ duration: 0.25 }}
            className="glass-strong mx-4 mt-2 rounded-2xl p-4 md:hidden"
          >
            <ul className="flex flex-col gap-1">
              {NAV_LINKS.map((link) => (
                <li key={link.href}>
                  <a
                    href={link.href}
                    onClick={() => setOpen(false)}
                    className="block rounded-xl px-4 py-3 text-sm text-white/75 transition-colors hover:bg-white/5 hover:text-white"
                  >
                    {link.label}
                  </a>
                </li>
              ))}
              <li className="mt-2">
                <a
                  href="#cta"
                  onClick={() => setOpen(false)}
                  className="block rounded-xl bg-gradient-to-r from-primary to-secondary px-4 py-3 text-center text-sm font-semibold"
                >
                  شروع کنید
                </a>
              </li>
            </ul>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </motion.header>
  );
}
