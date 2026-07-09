import Link from "next/link";
import { notFound, redirect } from "next/navigation";
import { auth } from "@/lib/auth";
import { canUsePlan } from "@/lib/access";
import { getUsageRemaining } from "@/lib/tool-usage";
import { LegacyToolEmbed } from "@/components/tools/LegacyToolEmbed";
import { prepareLegacyHtml } from "@/lib/tools/legacy-html";
import { getToolBySlug } from "@/lib/tools/registry";

type PageProps = {
  params: Promise<{ slug: string }>;
};

export async function generateStaticParams() {
  return Object.keys(
    (await import("@/lib/tools/registry")).TOOL_REGISTRY
  ).map((slug) => ({ slug }));
}

export async function generateMetadata({ params }: PageProps) {
  const { slug } = await params;
  const tool = getToolBySlug(slug);
  return { title: tool ? `${tool.title} | Planeando con Causa` : "Herramienta" };
}

export default async function HerramientaPage({ params }: PageProps) {
  const { slug } = await params;
  const tool = getToolBySlug(slug);

  if (!tool) notFound();

  const session = await auth();
  if (!session?.user?.id) {
    redirect(`/login?callbackUrl=/herramientas/${slug}`);
  }

  const planActivo = session.user.planActivo ?? 0;
  if (tool.minPlan > 0 && !canUsePlan(planActivo, tool.minPlan)) {
    redirect("/planes");
  }

  if (tool.kind === "stub") {
    return (
      <section className="mx-auto max-w-[720px] px-5 py-24 text-center">
        <h1 className="mb-4 text-[32px] text-snow">{tool.title}</h1>
        <p className="mb-8 text-fog">
          Esta herramienta estará disponible próximamente. En el sitio legacy el
          archivo <code>index3.php</code> no estaba incluido en el respaldo.
        </p>
        <Link href="/" className="rounded-[10px] bg-bone px-4 py-2 text-ink">
          Volver al inicio
        </Link>
      </section>
    );
  }

  if (tool.kind === "simulator-catalog") {
    redirect("/herramientas/simuladores");
  }

  if (tool.kind === "legacy-static" && tool.generarFile) {
    const html = prepareLegacyHtml(tool, {
      usosRestantes: 99,
      userName: session.user.name ?? "Docente",
    });
    return (
      <div className="w-full">
        <LegacyToolEmbed html={html} title={tool.title} />
      </div>
    );
  }

  if (!tool.generarFile) notFound();

  const userId = Number(session.user.id);
  const usosRestantes = tool.usageField
    ? await getUsageRemaining(userId, tool.usageField)
    : 99;

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
