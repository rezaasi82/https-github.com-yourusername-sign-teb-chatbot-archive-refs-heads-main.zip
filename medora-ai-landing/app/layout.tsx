import type { Metadata, Viewport } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import SmoothScroll from "@/components/providers/SmoothScroll";

const inter = Inter({
  subsets: ["latin"],
  display: "swap",
  variable: "--font-inter",
});

export const metadata: Metadata = {
  metadataBase: new URL("https://medora.ai"),
  title: "Medora AI — The Intelligence Layer for Modern Healthcare",
  description:
    "Medora AI turns clinical chaos into clarity. Diagnose faster, automate documentation, and deliver world-class patient care with an AI copilot built for medicine.",
  keywords: [
    "Medora AI",
    "healthcare AI",
    "clinical copilot",
    "medical AI assistant",
    "AI diagnostics",
  ],
  openGraph: {
    title: "Medora AI — The Intelligence Layer for Modern Healthcare",
    description:
      "Diagnose faster, automate documentation, and deliver world-class patient care with an AI copilot built for medicine.",
    url: "https://medora.ai",
    siteName: "Medora AI",
    type: "website",
  },
  twitter: {
    card: "summary_large_image",
    title: "Medora AI — The Intelligence Layer for Modern Healthcare",
    description:
      "Diagnose faster, automate documentation, and deliver world-class patient care with an AI copilot built for medicine.",
  },
  robots: { index: true, follow: true },
};

export const viewport: Viewport = {
  themeColor: "#050816",
  width: "device-width",
  initialScale: 1,
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en" className={inter.variable}>
      <body className="font-sans">
        <SmoothScroll>{children}</SmoothScroll>
      </body>
    </html>
  );
}
