import Link from "next/link";
import { MatrixBackground } from "./MatrixBackground";
import { ProductMockup } from "./ProductMockup";

export function Hero() {
  return (
    <section className="relative overflow-hidden px-5 pb-24 pt-20">
      <div className="absolute inset-0 opacity-40">
        <MatrixBackground />
      </div>
      <div className="relative z-[1] mx-auto max-w-[1080px]">
        <div className="grid items-end gap-10 lg:grid-cols-[1.15fr_0.85fr]">
          <div>
            <div className="mb-8 inline-flex items-center gap-2 rounded-[10px] bg-black/[0.05] px-3 py-2 text-[12px] font-[400] tracking-[-0.28px] text-chalk ring-1 ring-black/[0.08]">
              <span className="h-1.5 w-1.5 rounded-full bg-signal-blue shadow-[0_0_8px_rgba(59,130,246,0.8)]" />
              Plataforma docente con IA en migración activa
            </div>
            <h1 className="max-w-[690px] text-[44px] font-[400] leading-[0.98] tracking-[-1.1px] text-snow md:text-[64px] md:tracking-[-1.28px]">
              Planeación didáctica inteligente para docentes que necesitan avanzar.
            </h1>
          </div>

          <div className="lg:pb-2">
            <p className="mb-6 text-[18px] font-[450] leading-[1.38] tracking-[-0.61px] text-fog">
              Un centro de control para generar planeaciones, diagnósticos,
              exámenes y materiales alineados a la NEM con una interfaz compacta,
              clara y lista para crecer.
            </p>
            <div className="flex flex-col gap-3 sm:flex-row">
              <Link
                href="/registro"
                className="rounded-[10px] bg-bone px-5 py-2.5 text-center text-[14px] font-[400] tracking-[-0.32px] text-ink shadow-[var(--shadow-sm)] transition hover:bg-snow"
              >
                Regístrate aquí
              </Link>
              <Link
                href="/planes"
                className="rounded-[10px] bg-black/[0.05] px-5 py-2.5 text-center text-[14px] font-[400] tracking-[-0.32px] text-snow transition hover:bg-black/[0.08]"
              >
                Ver planes
              </Link>
            </div>
          </div>
        </div>

        <ProductMockup />
      </div>
    </section>
  );
}
