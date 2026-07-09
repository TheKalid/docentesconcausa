export function SocialImpact() {
  return (
    <section className="px-5 py-24">
      <div className="mx-auto grid max-w-[1080px] gap-6 rounded-[12px] bg-graphite p-7 shadow-[var(--shadow-card)] md:grid-cols-[0.8fr_1.2fr] md:p-8">
        <div>
          <p className="mb-3 text-[10px] font-[500] uppercase tracking-[0.16em] text-steel">
            Transparencia
          </p>
          <h2 className="text-[32px] font-[400] leading-[1.25] tracking-[-0.64px] text-snow">
            Impacto social con seguimiento visible.
          </h2>
        </div>
        <div className="space-y-5">
          <p className="text-[16px] leading-[1.5] text-chalk">
            Creemos en un mundo donde la generosidad es la norma, no la
            excepción. No somos solo una plataforma de suscripción; somos un
            movimiento de personas que eligen ser agentes de cambio.
          </p>
          <p className="text-[14px] leading-[1.43] tracking-[-0.32px] text-fog">
            Con cada suscripción, no solo adquieres un producto o servicio: te
            unes a una promesa de sembrar vida y esperanza, un niño a la vez, un
            árbol a la vez.
          </p>
          <div className="flex flex-wrap gap-2">
            {["Donaciones", "Reportes", "Comunidad", "Propósito"].map((tag) => (
              <span
                key={tag}
                className="rounded-[5.26px] border border-mint/60 px-2 py-1 text-[10px] font-[500] text-mint"
              >
                ● {tag}
              </span>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
