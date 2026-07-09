import Link from "next/link";
import { auth } from "@/lib/auth";
import { tools } from "@/lib/tools";
import { ToolCard } from "./ToolCard";

export async function ToolsGrid() {
  const session = await auth();
  const planActivo = session?.user?.planActivo ?? 0;

  return (
    <section className="px-5 py-24" id="herramientas">
      <div className="mx-auto max-w-[1080px]">
        <div className="mb-12 grid gap-5 md:grid-cols-[0.9fr_1.1fr] md:items-end">
          <div>
            <p className="mb-3 text-[10px] font-[500] uppercase tracking-[0.16em] text-steel">
              Stack docente
            </p>
            <h2 className="text-[42px] font-[400] leading-[1.05] tracking-[-0.88px] text-snow">
              Nuestras Herramientas
            </h2>
          </div>
          <p className="max-w-[560px] text-[16px] leading-[1.5] text-fog">
            Cada herramienta respeta los mismos permisos del sitio legacy según tu
            plan activo: Básico (1), Mentor (2) o Líder (3).
          </p>
        </div>

        <div className="grid grid-cols-[repeat(auto-fit,minmax(260px,1fr))] gap-4">
          {tools.map((tool) => (
            <ToolCard key={tool.title} tool={tool} planActivo={planActivo} />
          ))}
        </div>

        <div className="mt-8 flex flex-col gap-3 rounded-[12px] bg-charcoal p-4 shadow-[var(--shadow-panel)] sm:flex-row sm:items-center sm:justify-between">
          <p className="text-[14px] tracking-[-0.32px] text-fog">
            Canales adicionales para orientar familias, docentes y equipos
            escolares.
          </p>
          <div className="flex flex-col gap-3 sm:flex-row">
            <Link
              href="/expertos"
              className="rounded-[10px] bg-bone px-4 py-2 text-center text-[14px] font-[400] tracking-[-0.32px] text-ink shadow-[var(--shadow-sm)] transition hover:bg-snow"
            >
              Consulta expertos
            </Link>
            <Link
              href="/padres"
              className="rounded-[10px] bg-black/[0.05] px-4 py-2 text-center text-[14px] font-[400] tracking-[-0.32px] text-snow transition hover:bg-black/[0.08]"
            >
              Herramientas para padres
            </Link>
          </div>
        </div>
      </div>
    </section>
  );
}
