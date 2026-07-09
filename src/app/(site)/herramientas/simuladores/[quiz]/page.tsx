import { readFileSync } from "fs";
import path from "path";
import { notFound, redirect } from "next/navigation";
import { auth } from "@/lib/auth";
import { canUsePlan } from "@/lib/access";
import { LegacyToolEmbed } from "@/components/tools/LegacyToolEmbed";
import { prepareLegacyHtml } from "@/lib/tools/legacy-html";
import { SIMULATOR_REGISTRY } from "@/lib/tools/registry";

type PageProps = {
  params: Promise<{ quiz: string }>;
};

export async function generateStaticParams() {
  return Object.keys(SIMULATOR_REGISTRY).map((quiz) => ({ quiz }));
}

export async function generateMetadata({ params }: PageProps) {
  const { quiz } = await params;
  const sim = SIMULATOR_REGISTRY[quiz];
  return {
    title: sim ? `${sim.title} | Simuladores` : "Simulador",
  };
}

export default async function SimuladorQuizPage({ params }: PageProps) {
  const { quiz } = await params;
  const sim = SIMULATOR_REGISTRY[quiz];
  if (!sim) notFound();

  const session = await auth();
  if (!session?.user?.id) {
    redirect(`/login?callbackUrl=/herramientas/simuladores/${quiz}`);
  }

  if (!canUsePlan(session.user.planActivo ?? 0, 2)) {
    redirect("/planes");
  }

  const questionsPath = path.join(
    process.cwd(),
    "public",
    "data",
    "simuladores",
    sim.questionsFile
  );
  const questionsJson = readFileSync(questionsPath, "utf8");

  const html = prepareLegacyHtml(
    {
      slug: quiz,
      title: sim.title,
      minPlan: 2,
      kind: "simulator-quiz",
      generarFile: sim.generarFile,
    },
    {
      usosRestantes: 5,
      userName: session.user.name ?? "Docente",
      questionsJson,
    }
  );

  return (
    <div className="w-full">
      <LegacyToolEmbed html={html} title={sim.title} />
    </div>
  );
}
