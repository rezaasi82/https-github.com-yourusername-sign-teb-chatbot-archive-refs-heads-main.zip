import type { Metadata, Viewport } from "next";
import { Vazirmatn } from "next/font/google";
import "./globals.css";
import SmoothScroll from "@/components/providers/SmoothScroll";

const vazirmatn = Vazirmatn({
  subsets: ["arabic", "latin"],
  display: "swap",
  variable: "--font-sans",
});

export const metadata: Metadata = {
  metadataBase: new URL("https://medora.ai"),
  title: "مدورا AI — لایهٔ هوشمند پزشکیِ مدرن",
  description:
    "مدورا AI آشفتگی بالینی را به شفافیت تبدیل می‌کند. سریع‌تر تشخیص دهید، مستندسازی را خودکار کنید و با دستیار هوش مصنوعیِ ساخته‌شده برای پزشکی، مراقبتی در کلاس جهانی ارائه دهید.",
  keywords: [
    "مدورا",
    "Medora AI",
    "هوش مصنوعی پزشکی",
    "دستیار بالینی هوشمند",
    "مستندسازی خودکار پزشکی",
    "تشخیص با هوش مصنوعی",
  ],
  openGraph: {
    title: "مدورا AI — لایهٔ هوشمند پزشکیِ مدرن",
    description:
      "سریع‌تر تشخیص دهید، مستندسازی را خودکار کنید و با دستیار هوش مصنوعیِ ساخته‌شده برای پزشکی، مراقبتی در کلاس جهانی ارائه دهید.",
    url: "https://medora.ai",
    siteName: "Medora AI",
    type: "website",
    locale: "fa_IR",
  },
  twitter: {
    card: "summary_large_image",
    title: "مدورا AI — لایهٔ هوشمند پزشکیِ مدرن",
    description:
      "سریع‌تر تشخیص دهید، مستندسازی را خودکار کنید و به هر بیمار یک همراه ۲۴ساعته بدهید.",
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
    <html lang="fa" dir="rtl" className={vazirmatn.variable}>
      <body className="font-sans">
        <SmoothScroll>{children}</SmoothScroll>
      </body>
    </html>
  );
}
