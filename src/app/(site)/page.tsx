import { Hero } from "@/components/Hero";
import { ToolsGrid } from "@/components/ToolsGrid";
import { EvidenciasGrid } from "@/components/EvidenciasGrid";
import { SocialImpact } from "@/components/SocialImpact";

export default function Home() {
  return (
    <>
      <Hero />
      <ToolsGrid />
      <EvidenciasGrid />
      <SocialImpact />
    </>
  );
}
