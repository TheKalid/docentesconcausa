import Link from "next/link";

export const metadata = { title: "Biblioteca | Planeando con Causa" };

export default function BibliotecaPage() {
  return (
    <section className="mx-auto max-w-[1080px] px-5 py-24">
      <p className="mb-3 text-[10px] font-[500] uppercase tracking-[0.16em] text-steel">
        Recursos
      </p>
      <h1 className="mb-5 text-[42px] font-[400] leading-[1.05] tracking-[-0.88px] text-snow">
        Biblioteca
      </h1>
      <p className="mb-8 max-w-[640px] text-[16px] leading-[1.5] text-fog">
        La biblioteca de PDFs del legacy se conectará desde la tabla{" "}
        <code className="text-snow">recursos</code> del respaldo SQL. Por ahora
        puedes consultar los archivos originales en{" "}
        <code className="text-snow">legacy/html/uploads/</code>.
      </p>
      <Link
        href="/"
        className="rounded-[10px] bg-bone px-4 py-2 text-[14px] text-ink shadow-[var(--shadow-sm)]"
      >
        Volver al inicio
      </Link>
    </section>
  );
}
