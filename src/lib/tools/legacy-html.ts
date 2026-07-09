import { readFileSync } from "fs";
import path from "path";
import type { ToolDefinition } from "@/lib/tools/registry";
import { SIMULATOR_REGISTRY } from "@/lib/tools/registry";

const LEGACY_HTML = path.join(process.cwd(), "legacy", "html");

const PROCESAR_MAP: Record<string, string> = {
  "procesar_planeacion.php": "plan-basico",
  "procesar_planeacion_intermedio.php": "plan-intermedio",
  "procesar_planeacion_avanzada_pale.php": "planeacion-pale",
  "procesar_planeacion_avanzada_pam.php": "planeacion-pam",
  "procesar_planeacion_fisica.php": "educacion-fisica",
  "procesar_diagnostico.php": "evaluacion-diagnostica",
  "procesar_examen_nem.php": "examenes",
  "procesar_protocolo.php": "protocolos",
  "procesar_bitacora_de_profesor.php": "bitacora",
  "procesar_planeacion_neurodivergentes.php": "neurodivergentes",
  "procesar_cultura_paz.php": "cultura-paz",
  "procesar_planeacion_transversal.php": "planeacion-transversal",
  "procesar_planeacion_potenciada.php": "adecuacion",
  "procesar_simulador_padres_problematicos.php": "simulador-padres",
  "procesar_periodico_mural.php": "eventos-civicos",
  "procesar_planeacion_competencias.php": "planeacion-asignaturas",
  "procesar_telesecundarias.php": "telesecundarias",
  "procesar_herramientas_de_padres_de_familia.php": "padres",
};

export function prepareLegacyHtml(
  tool: ToolDefinition,
  options: { usosRestantes: number; userName: string; questionsJson?: string }
) {
  if (!tool.generarFile) {
    throw new Error("Herramienta sin archivo legacy.");
  }

  let html = readFileSync(path.join(LEGACY_HTML, tool.generarFile), "utf8");

  if (options.questionsJson) {
    html = html.replace(
      /let preguntas = <\?php echo \$json_preguntas; \?>;/,
      `let preguntas = ${options.questionsJson};`
    );
  }

  html = html.replace(/<\?php[\s\S]*?\?>/g, "");

  for (const [procesar, slug] of Object.entries(PROCESAR_MAP)) {
    html = html.replaceAll(`'${procesar}'`, `'/api/tools/${slug}'`);
    html = html.replaceAll(`"${procesar}"`, `"/api/tools/${slug}"`);
  }

  html = html
    .replaceAll("datos_plan_basico/", "/data/plan-basico/")
    .replaceAll("datos_plan_pale/", "/data/plan-pale/")
    .replaceAll("datos_plan_pam/", "/data/plan-pam/")
    .replaceAll("protocolos_json/", "/data/protocolos/")
    .replaceAll("preguntas_", "/data/simuladores/preguntas_")
    .replaceAll('href="index.php"', 'href="/"')
    .replaceAll("href='index.php'", "href='/'")
    .replaceAll('href="catalogo_de_pagos.php"', 'href="/planes"')
    .replaceAll('href="catalogo_de_servicios.php"', 'href="/herramientas/simuladores"')
    .replaceAll('href="login.php"', 'href="/login"')
    .replaceAll('href="biblioteca.php"', 'href="/biblioteca"')
    .replaceAll('href="servicio_cliente.html"', 'href="/servicio-cliente"')
    .replaceAll('href="tutorial_de_usos.html"', 'href="/tutorial"')
    .replaceAll('src="logo.png"', 'src="/logo.png"');

  for (const sim of Object.values(SIMULATOR_REGISTRY)) {
    html = html.replaceAll(
      sim.generarFile,
      `/herramientas/simuladores/${Object.entries(SIMULATOR_REGISTRY).find(([, v]) => v.generarFile === sim.generarFile)?.[0] ?? ""}`
    );
  }

  const bootstrap = `
<script>
  window.__LEGACY_TOOL__ = {
    usosRestantes: ${options.usosRestantes},
    userName: ${JSON.stringify(options.userName)}
  };
  let csrfToken = "nextauth";
  if (typeof usosRestantes === "undefined") { var usosRestantes = ${options.usosRestantes}; }
</script>`;

  html = html.replace("</head>", `${bootstrap}</head>`);

  return html;
}
