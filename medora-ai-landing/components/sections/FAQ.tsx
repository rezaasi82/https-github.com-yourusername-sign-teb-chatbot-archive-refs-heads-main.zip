"use client";

import { AnimatePresence, motion } from "framer-motion";
import { Plus } from "lucide-react";
import { useState } from "react";
import Reveal from "@/components/ui/Reveal";
import SectionHeading from "@/components/ui/SectionHeading";
import { FAQS } from "@/lib/data";
import { cn } from "@/lib/utils";

function FaqItem({
  faq,
  index,
  open,
  onToggle,
}: {
  faq: (typeof FAQS)[number];
  index: number;
  open: boolean;
  onToggle: () => void;
}) {
  return (
    <Reveal delay={0.06 * index}>
      <div
        className={cn(
          "glass overflow-hidden rounded-2xl transition-colors duration-300",
          open && "border-primary/35 bg-white/[0.06]"
        )}
      >
        <button
          onClick={onToggle}
          aria-expanded={open}
          className="flex w-full items-center justify-between gap-4 px-6 py-5 text-left"
        >
          <span className="text-[15px] font-medium text-white/85 md:text-base">
            {faq.question}
          </span>
          <motion.span
            animate={{ rotate: open ? 45 : 0 }}
            transition={{ duration: 0.3, ease: [0.16, 1, 0.3, 1] }}
            className="glass flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
          >
            <Plus className="h-4 w-4 text-highlight" />
          </motion.span>
        </button>
        <AnimatePresence initial={false}>
          {open ? (
            <motion.div
              initial={{ height: 0, opacity: 0 }}
              animate={{ height: "auto", opacity: 1 }}
              exit={{ height: 0, opacity: 0 }}
              transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
            >
              <p className="px-6 pb-6 text-sm leading-relaxed text-white/55">
                {faq.answer}
              </p>
            </motion.div>
          ) : null}
        </AnimatePresence>
      </div>
    </Reveal>
  );
}

export default function FAQ() {
  const [openIndex, setOpenIndex] = useState<number | null>(0);

  return (
    <section id="faq" className="relative py-28 md:py-36">
      <div className="section-shell">
        <SectionHeading
          eyebrow="FAQ"
          title="Questions,"
          highlight="answered"
          description="Everything hospital IT, compliance officers, and skeptical physicians usually ask us first."
        />
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
          {FAQS.map((faq, i) => (
            <FaqItem
              key={faq.question}
              faq={faq}
              index={i}
              open={openIndex === i}
              onToggle={() => setOpenIndex(openIndex === i ? null : i)}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
