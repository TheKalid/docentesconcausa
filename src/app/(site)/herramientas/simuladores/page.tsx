import Link from "next/link";
import { redirect } from "next/navigation";
import { auth } from "@/lib/auth";
import { canUsePlan } from "@/lib/access";
import { LegacyToolEmbed } from "@/components/tools/LegacyToolEmbed";
import { prepareLegacyHtml } from "@/lib/tools/legacy-html";
import { getToolBySlug } from "@/lib/tools/registry";

export const metadata = {
  title: "Simuladores USICAMM | Planeando con Causa",
};

export default async function SimuladoresCatalogPage() {
  const session = await auth();
  if (!session?.user?.id) {
    redirect("/login?callbackUrl=/herramientas/simuladores");
  }

  const tool = getToolBySlug("simuladores");
  if (!tool || !canUsePlan(session.user.planActivo ?? 0, tool.minPlan)) {
    redirect("/planes");
  }

  const html = prepareLegacyHtml(tool, {
    usosRestantes: 5,
    userName: session.user.name ?? "Docente",
  });

  return (
    <div className="w-full">
      <LegacyToolEmbed html={html} title="Catálogo de Simuladores" />
    </div>
  );
}
