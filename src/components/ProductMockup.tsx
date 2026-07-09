const workflowNodes = [
  {
    label: "Datos del grupo",
    description: "Grado, campo formativo y contexto escolar",
    accent: "border-signal-blue/70 text-arc-blue",
  },
  {
    label: "Motor NEM",
    description: "PDA, ejes articuladores y secuencia didáctica",
    accent: "border-mint/70 text-mint",
  },
  {
    label: "Entrega docente",
    description: "Planeación, evaluación y adecuaciones listas",
    accent: "border-ember/70 text-ember",
  },
];

const rows = [
  ["Planeación avanzada", "Activo", "2.4s"],
  ["Diagnóstico inicial", "En cola", "4.1s"],
  ["Examen personalizado", "Listo", "1.8s"],
];

export function ProductMockup() {
  return (
    <div className="relative mx-auto mt-16 max-w-[1080px] overflow-hidden rounded-[12px] bg-graphite/80 p-3 shadow-[var(--shadow-xl)] ring-1 ring-black/[0.08] backdrop-blur">
      <div className="flex items-center justify-between border-b border-black/[0.05] px-3 pb-3">
        <div className="flex items-center gap-2">
          <span className="h-2.5 w-2.5 rounded-full bg-coral/80" />
          <span className="h-2.5 w-2.5 rounded-full bg-ember/80" />
          <span className="h-2.5 w-2.5 rounded-full bg-mint/80" />
        </div>
        <span className="rounded-[5px] border border-mint px-2 py-1 text-[10px] font-[500] text-mint">
          ● IA activa
        </span>
      </div>

      <div className="grid gap-3 p-3 lg:grid-cols-[1.05fr_0.95fr]">
        <section className="rounded-[12px] bg-charcoal p-5 shadow-[var(--shadow-panel)]">
          <div className="mb-5 flex items-center justify-between">
            <div>
              <p className="text-[9px] font-[500] uppercase tracking-[0.14em] text-steel">
                Flujo de planeación
              </p>
              <h3 className="mt-2 text-[18px] font-[400] tracking-[-0.61px] text-snow">
                De contexto escolar a clase lista
              </h3>
            </div>
            <span className="rounded-[5px] border border-signal-blue/60 px-2 py-1 text-[10px] text-arc-blue">
              NEM
            </span>
          </div>

          <div className="relative grid gap-3 md:grid-cols-3">
            <span className="absolute left-[16%] right-[16%] top-1/2 hidden h-px bg-black/[0.08] md:block" />
            {workflowNodes.map((node) => (
              <article
                key={node.label}
                className={`relative rounded-[8.77px] border bg-graphite p-3 ${node.accent}`}
              >
                <span className="mb-3 grid h-8 w-8 place-items-center rounded-[8.77px] border border-black/[0.08] bg-black/[0.04] text-[12px]">
                  ↳
                </span>
                <h4 className="text-[12px] font-[500] text-snow">{node.label}</h4>
                <p className="mt-1 text-[10px] leading-[1.4] text-fog">
                  {node.description}
                </p>
              </article>
            ))}
          </div>
        </section>

        <section className="rounded-[12px] bg-charcoal p-5 shadow-[var(--shadow-panel)]">
          <div className="mb-4 flex items-center justify-between">
            <p className="text-[9px] font-[500] uppercase tracking-[0.14em] text-steel">
              Operaciones
            </p>
            <span className="text-[10px] text-fog">Tiempo real</span>
          </div>

          <div className="overflow-hidden rounded-[8.77px] border border-black/[0.08]">
            <div className="grid grid-cols-[1.4fr_0.8fr_0.6fr] border-b border-black/[0.05] bg-black/[0.04] px-3 py-2 text-[9px] font-[500] uppercase tracking-[0.12em] text-fog">
              <span>Herramienta</span>
              <span>Estado</span>
              <span className="text-right">Latencia</span>
            </div>
            {rows.map(([tool, status, latency]) => (
              <div
                key={tool}
                className="grid grid-cols-[1.4fr_0.8fr_0.6fr] border-b border-black/[0.05] px-3 py-3 text-[11px] text-chalk last:border-b-0"
              >
                <span className="text-snow">{tool}</span>
                <span className="text-mint">{status}</span>
                <span className="text-right text-fog">{latency}</span>
              </div>
            ))}
          </div>
        </section>
      </div>
    </div>
  );
}
