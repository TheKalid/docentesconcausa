import { readFileSync } from "fs";
import path from "path";
import type { ToolDefinition } from "@/lib/tools/registry";
import { SIMULATOR_REGISTRY } from "@/lib/tools/registry";

const LEGACY_HTML = path.join(process.cwd(), "legacy", "html");
const LEGACY_ROOT = path.join(process.cwd(), "legacy");
const TOOL_UI_CSS = readFileSync(
  path.join(LEGACY_ROOT, "tool-ui-enhancements.css"),
  "utf8"
);
const TOOL_UI_JS = readFileSync(
  path.join(LEGACY_ROOT, "tool-ui-enhancements.js"),
  "utf8"
);

function substituteLegacyPhp(
  html: string,
  options: { usosRestantes: number; userName: string; questionsJson?: string }
) {
  const { usosRestantes, userName } = options;
  const firstName = userName.split(" ")[0] ?? userName;
  const canGenerate = usosRestantes > 0;

  if (options.questionsJson) {
    html = html.replace(
      /let preguntas = <\?php echo \$json_preguntas; \?>;/,
      `let preguntas = ${options.questionsJson};`
    );
  }

  html = html.replace(
    /parseInt\(<\?php echo \(int\)\$\w+; \?>, 10\)/g,
    `parseInt(${usosRestantes}, 10)`
  );

  html = html.replace(
    /let usosRestantes = <\?php echo \$[\w]+; \?>;/g,
    `let usosRestantes = ${usosRestantes};`
  );

  html = html.replace(
    /const csrfToken = "<\?php echo (\$_SESSION\['csrf_token'\]|\$csrf_token); \?>";/g,
    'const csrfToken = "nextauth";'
  );

  html = html.replace(
    /'X-CSRF-Token': '<\?php echo \$_SESSION\['csrf_token'\]; \?>'/g,
    "'X-CSRF-Token': 'nextauth'"
  );

  html = html.replace(
    /content="<\?php echo \$_SESSION\['csrf_token'\]; \?>"/g,
    'content="nextauth"'
  );

  html = html.replace(
    /(<(?:strong|span) id="(?:contador-usos|contador-display|intentos-restantes)">)<\?php echo[^?]+\?>(<\/(?:strong|span)>)/g,
    `$1${usosRestantes}$2`
  );

  html = html.replace(
    /<\?php echo htmlspecialchars\(explode\(' ', \$nombre_usuario\)\[0\]\); \?>/g,
    firstName
  );

  html = html.replace(
    /<\?php echo \$usuario_nombre; \?>/g,
    userName
  );

  html = html.replace(
    /<\?php echo htmlspecialchars\(\$error_carga, ENT_QUOTES, 'UTF-8'\); \?>/g,
    ""
  );

  html = html.replace(
    /const contextoGrupo = <\?php echo \$contexto_json; \?>;/g,
    "const contextoGrupo = {};"
  );

  html = html.replace(
    /<\?php echo htmlspecialchars\(\$contexto_guardado, ENT_QUOTES, 'UTF-8'\); \?>/g,
    ""
  );

  html = html.replace(
    /<\?php echo !\$puede_generar \? 'disabled' : ''; \?>/g,
    canGenerate ? "" : "disabled"
  );

  html = html.replace(
    /<\?php echo \$puede_generar \? '([^']*)' : '([^']*)'; \?>/g,
    (_, yes, no) => (canGenerate ? yes : no)
  );

  html = html.replace(
    /<\?php echo \(\$usos_restantes > 0\) \? '([^']*)' : '([^']*)'; \?>/g,
    (_, yes, no) => (canGenerate ? yes : no)
  );

  return html;
}

function repairLegacyJs(
  html: string,
  options: { usosRestantes: number }
) {
  return html
    .replace(/parseInt\(\s*,\s*10\)/g, `parseInt(${options.usosRestantes}, 10)`)
    .replace(/let usosRestantes = \s*;/g, `let usosRestantes = ${options.usosRestantes};`)
    .replace(/const csrfToken = "";/g, 'const csrfToken = "nextauth";');
}

function stripReturnNavigation(html: string) {
  return html
    .replace(
      /<div[^>]*class="[^"]*return-button-container[^"]*"[^>]*>[\s\S]*?<\/div>\s*/gi,
      ""
    )
    .replace(
      /<section[^>]*class="[^"]*return-section[^"]*"[^>]*>[\s\S]*?<\/section>\s*/gi,
      ""
    )
    .replace(/<a[^>]*class="[^"]*btn-return[^"]*"[^>]*>[\s\S]*?<\/a>\s*/gi, "")
    .replace(/<a[^>]*class="[^"]*btn-regresar[^"]*"[^>]*>[\s\S]*?<\/a>\s*/gi, "")
    .replace(/<a[^>]*class="[^"]*back-button[^"]*"[^>]*>[\s\S]*?<\/a>\s*/gi, "")
    .replace(
      /htmlBotones \+= `<a href="index\.php" class="btn btn-return">[^`]*<\/a>`;\s*/g,
      ""
    );
}

function neutralizeLegacyLoadingUx(html: string) {
  return html
    .replace(/formSection\.style\.display\s*=\s*['"]none['"]\s*;?/g, "")
    .replace(/formSection\.style\.display\s*=\s*['"]block['"]\s*;?/g, "")
    .replace(/formulario\.style\.display\s*=\s*['"]none['"]\s*;?/g, "")
    .replace(/formulario\.style\.display\s*=\s*['"]block['"]\s*;?/g, "");
}

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

  html = substituteLegacyPhp(html, options);
  html = html.replace(/<\?php[\s\S]*?\?>/g, "");
  html = repairLegacyJs(html, options);

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

  html = stripReturnNavigation(html);
  html = neutralizeLegacyLoadingUx(html);

  const toolUiStyles = `<style id="legacy-tool-ui-styles">${TOOL_UI_CSS}</style>`;
  const toolUiModal = `
<div id="modalGenerando" class="modal-generando" aria-hidden="true" aria-labelledby="modalGenerandoTitulo">
  <div class="modal-generando-contenido" role="dialog" aria-modal="true">
    <div class="spinner-generando" aria-hidden="true"></div>
    <h3 id="modalGenerandoTitulo">Procesando tu solicitud</h3>
    <p>El sistema está trabajando en tu solicitud.</p>
    <small>Este proceso puede tardar unos segundos. Por favor, no cierres ni recargues la página.</small>
  </div>
</div>`;

  const bootstrap = `
<script>
  window.__LEGACY_TOOL__ = {
    usosRestantes: ${options.usosRestantes},
    userName: ${JSON.stringify(options.userName)}
  };
</script>
<script>${TOOL_UI_JS}</script>`;

  html = html.replace("</head>", `${toolUiStyles}${bootstrap}</head>`);
  html = html.replace("</body>", `${toolUiModal}</body>`);

  return html;
}
