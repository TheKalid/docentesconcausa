import Link from "next/link";

export function Footer() {
  return (
    <footer className="border-t border-black/[0.08] bg-graphite px-5 py-12">
      <div className="mx-auto grid max-w-[1080px] gap-8 md:grid-cols-[1.2fr_0.8fr_0.8fr]">
        <div>
          <p className="mb-3 text-[14px] font-[400] tracking-[-0.32px] text-snow">
            Planeando con Causa
          </p>
          <p className="max-w-[360px] text-[12px] leading-[1.6] text-fog">
            Herramientas para docentes con IA, suscripciones y propósito social.
          </p>
        </div>
        <div>
          <h3 className="mb-3 text-[9px] font-[500] uppercase tracking-[0.16em] text-steel">
            Plataforma
          </h3>
          <div className="flex flex-col gap-2">
            <Link href="/planes" className="text-[12px] text-fog hover:text-snow">
              Planes
            </Link>
            <Link href="/registro" className="text-[12px] text-fog hover:text-snow">
              Registro
            </Link>
            <Link href="/login" className="text-[12px] text-fog hover:text-snow">
              Iniciar sesión
            </Link>
          </div>
        </div>
        <div>
          <h3 className="mb-3 text-[9px] font-[500] uppercase tracking-[0.16em] text-steel">
            Soporte
          </h3>
          <div className="flex flex-col gap-2">
            <Link
              href="/servicio-cliente"
              className="text-[12px] text-fog hover:text-snow"
            >
              Ayuda y Servicio al Cliente
            </Link>
            <span className="text-[12px] text-steel">
              © {new Date().getFullYear()} Todos los derechos reservados
            </span>
          </div>
        </div>
      </div>
    </footer>
  );
}
