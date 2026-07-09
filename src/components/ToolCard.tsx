import Link from "next/link";
import type { Tool } from "@/lib/tools";
import { canUsePlan } from "@/lib/access";

type ToolCardProps = {
  tool: Tool;
  planActivo?: number;
};

export function ToolCard({ tool, planActivo = 0 }: ToolCardProps) {
  const hasAccess = canUsePlan(planActivo, tool.minPlan);
  const href = hasAccess ? tool.href : "/planes";

  return (
    <article className="group relative flex min-h-[260px] flex-col rounded-[12px] bg-graphite p-7 shadow-[var(--shadow-card)] transition duration-200 hover:bg-black/[0.05]">
      <span className="absolute inset-x-7 top-0 h-px bg-signal-blue/0 transition group-hover:bg-signal-blue/80" />

      {tool.beta && (
        <span className="absolute right-5 top-5 rounded-[5.26px] border border-signal-blue/60 px-2 py-1 text-[9px] font-[500] uppercase tracking-[0.12em] text-arc-blue">
          Beta
        </span>
      )}

      <span className="mb-6 grid h-12 w-12 place-items-center rounded-[8.77px] border border-black/[0.08] bg-black/[0.04] text-[22px] transition group-hover:border-signal-blue/50">
        {tool.icon}
      </span>
      <h3 className="mb-3 text-[18px] font-[400] leading-[1.2] tracking-[-0.61px] text-snow">
        {tool.title}
      </h3>
      <p className="mb-7 grow text-[14px] font-[400] leading-[1.43] tracking-[-0.32px] text-fog">
        {tool.description}
      </p>

      <Link
        href={href}
        className={`mt-auto inline-flex w-fit items-center gap-2 rounded-[10px] px-4 py-2 text-[14px] font-[400] tracking-[-0.32px] transition ${
          hasAccess
            ? "bg-black/[0.05] text-snow hover:bg-bone hover:text-ink"
            : "bg-black/[0.04] text-steel hover:bg-black/[0.06]"
        }`}
        title={
          hasAccess
            ? "Acceder a la herramienta"
            : "Requiere un plan activo. Ver planes de suscripción."
        }
      >
        {hasAccess ? tool.cta : "Ver planes"}
        <span aria-hidden="true">→</span>
      </Link>
    </article>
  );
}
