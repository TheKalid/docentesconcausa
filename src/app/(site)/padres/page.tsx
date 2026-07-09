import { redirect } from "next/navigation";
import { auth } from "@/lib/auth";
import { canUsePlan } from "@/lib/access";
import { getUsageRemaining } from "@/lib/tool-usage";
import { LegacyToolEmbed } from "@/components/tools/LegacyToolEmbed";
import { prepareLegacyHtml } from "@/lib/tools/legacy-html";
import { getToolBySlug } from "@/lib/tools/registry";

export const metadata = {
  title: "Herramientas para Padres | Planeando con Causa",
};

export default async function PadresPage() {
  const session = await auth();
  if (!session?.user?.id) {
    redirect("/login?callbackUrl=/padres");
  }

  const tool = getToolBySlug("padres");
  if (!tool?.generarFile) redirect("/");

  const usosRestantes = await getUsageRemaining(
    Number(session.user.id),
    "usosDiariosPadres"
  );

  const html = prepareLegacyHtml(tool, {
    usosRestantes,
    userName: session.user.name ?? "Docente",
  });

  return (
    <div className="w-full">
      <LegacyToolEmbed html={html} title={tool.title} />
    </div>
  );
}
