import Image from "next/image";
import { causaTagStyles, evidencias } from "@/lib/evidencias";

export function EvidenciasGrid() {
  return (
    <section className="px-5 py-24" id="evidencias">
      <div className="mx-auto max-w-[1080px]">
        <div className="mb-12 grid gap-5 md:grid-cols-[0.9fr_1.1fr] md:items-end">
          <div>
            <p className="mb-3 text-[10px] font-[500] uppercase tracking-[0.16em] text-steel">
              Evidencias con causa
            </p>
            <h2 className="text-[42px] font-[400] leading-[1.05] tracking-[-0.88px] text-snow">
              Nuestros docentes haciendo la diferencia
            </h2>
          </div>
          <p className="max-w-[560px] text-[16px] leading-[1.5] text-fog">
            Para garantizar la transparencia, elegimos al azar a docentes
            suscriptores de nuestra comunidad para que sean ellos mismos quienes
            entreguen personalmente las donaciones que tu apoyo hace posibles.
          </p>
        </div>

        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          {evidencias.map((evidencia) => {
            const tagStyle = causaTagStyles[evidencia.causa];

            return (
              <article
                key={evidencia.maestro}
                className="group overflow-hidden rounded-[12px] bg-graphite shadow-[var(--shadow-card)] transition duration-200 hover:-translate-y-1 hover:shadow-[var(--shadow-panel)]"
              >
                <div className="relative h-[220px] overflow-hidden">
                  <Image
                    src={evidencia.image}
                    alt={evidencia.imageAlt}
                    fill
                    sizes="(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw"
                    className="object-cover transition duration-300 group-hover:scale-[1.03]"
                  />
                </div>

                <div className="p-6">
                  <div className="mb-4 flex items-center justify-between gap-3">
                    <span
                      className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-[500] ${tagStyle.bg} ${tagStyle.text}`}
                    >
                      <span aria-hidden="true">{evidencia.causaIcon}</span>
                      {evidencia.causaLabel}
                    </span>
                    <span className="text-[12px] font-[500] text-steel">
                      {evidencia.lugar}
                    </span>
                  </div>

                  <h3 className="mb-3 text-[18px] font-[400] leading-[1.2] tracking-[-0.61px] text-snow">
                    {evidencia.maestro}
                  </h3>

                  <blockquote className="border-l-[3px] border-ember pl-4 text-[14px] italic leading-[1.5] tracking-[-0.32px] text-fog">
                    &ldquo;{evidencia.testimonio}&rdquo;
                  </blockquote>
                </div>
              </article>
            );
          })}
        </div>
      </div>
    </section>
  );
}
