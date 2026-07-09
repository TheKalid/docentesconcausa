import { redirect } from "next/navigation";
import { auth } from "@/lib/auth";
import { LegacyToolEmbed } from "@/components/tools/LegacyToolEmbed";
import { prepareLegacyHtml } from "@/lib/tools/legacy-html";
import { getToolBySlug } from "@/lib/tools/registry";

export const metadata = {
  title: "Expertos en Educación | Planeando con Causa",
};

export default async function ExpertosPage() {
  const session = await auth();
  const tool = getToolBySlug("expertos") ?? {
    slug: "expertos",
    title: "Expertos en Educación",
    minPlan: 0,
    kind: "legacy-static" as const,
    generarFile: "generar_expertos_externos.php",
  };

  const html = prepareLegacyHtml(tool, {
    usosRestantes: 99,
    userName: session?.user?.name ?? "Docente",
  });

  return (
    <div className="w-full">
      <LegacyToolEmbed html={html} title={tool.title} />
    </div>
  );
}
