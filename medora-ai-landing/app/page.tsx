import Navbar from "@/components/sections/Navbar";
import Hero from "@/components/sections/Hero";
import Showcase3D from "@/components/sections/Showcase3D";
import Features from "@/components/sections/Features";
import ProductDemo from "@/components/sections/ProductDemo";
import Testimonials from "@/components/sections/Testimonials";
import Pricing from "@/components/sections/Pricing";
import FAQ from "@/components/sections/FAQ";
import FinalCTA from "@/components/sections/FinalCTA";
import Footer from "@/components/sections/Footer";

export default function Home() {
  return (
    <main className="relative">
      <Navbar />
      <Hero />
      <Showcase3D />
      <Features />
      <ProductDemo />
      <Testimonials />
      <Pricing />
      <FAQ />
      <FinalCTA />
      <Footer />
    </main>
  );
}
