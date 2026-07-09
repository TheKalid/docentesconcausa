import { readFileSync } from "fs";
import path from "path";
import Link from "next/link";

type LegacyPageProps = {
  fileName: string;
  title: string;
  subtitle?: string;
};

function extractBodyContent(html: string) {
  const bodyMatch = html.match(/<body[^>]*>([\s\S]*)<\/body>/i);
  if (!bodyMatch) return html;

  return bodyMatch[1]
    .replace(/<script[\s\S]*?<\/script>/gi, "")
    .replace(/<style[\s\S]*?<\/style>/gi, "")
    .replace(/href="([^"]+\.html)"/g, (_match, href: string) => {
      const map: Record<string, string> = {
        "mision.html": "/mision",
        "creadores.html": "/creadores",
        "evidencias.html": "/evidencias",
        "tutorial_de_usos.html": "/tutorial",
        "servicio_cliente.html": "/servicio-cliente",
        "index.php": "/",
        "login.php": "/login",
        "registro.php": "/registro",
        "catalogo_de_pagos.php": "/planes",
        "biblioteca.php": "/biblioteca",
      };
      return `href="${map[href] ?? "/"}"`;
    });
}

export function LegacyPage({ fileName, title, subtitle }: LegacyPageProps) {
  const filePath = path.join(process.cwd(), "legacy", "html", fileName);
  const html = readFileSync(filePath, "utf8");
  const content = extractBodyContent(html);

  return (
    <section className="mx-auto max-w-[1080px] px-5 py-16">
      <div className="mb-8">
        <Link href="/" className="text-[14px] text-fog hover:text-snow">
          ← Volver al inicio
        </Link>
        <h1 className="mt-4 text-[42px] font-[400] leading-[1.05] tracking-[-0.88px] text-snow">
          {title}
        </h1>
        {subtitle && (
          <p className="mt-3 max-w-[720px] text-[16px] leading-[1.5] text-fog">
            {subtitle}
          </p>
        )}
      </div>

      <div
        className="legacy-content rounded-[12px] bg-graphite p-6 shadow-[var(--shadow-card)] [&_a]:text-signal-blue [&_h2]:text-snow [&_h3]:text-snow [&_img]:max-w-full [&_img]:rounded-[8px] [&_li]:text-fog [&_p]:text-fog"
        dangerouslySetInnerHTML={{ __html: content }}
      />
    </section>
  );
}
