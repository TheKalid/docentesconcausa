import type { PlanLevel } from "@/lib/access";

export type UsageField =
  | "usosPlanBasico"
  | "usosPlanIntermedio"
  | "usosFisica"
  | "usosEvaluacionDiag"
  | "usosExamenes"
  | "usosProtocolos"
  | "usosBitacora"
  | "usosDiariosPadres";

export type ToolKind = "legacy-ai" | "legacy-static" | "simulator-catalog" | "simulator-quiz" | "stub";

export type ToolDefinition = {
  slug: string;
  title: string;
  minPlan: PlanLevel;
  kind: ToolKind;
  generarFile?: string;
  procesarFile?: string;
  usageField?: UsageField;
  historialName?: string;
  questionsFile?: string;
  responseShape?: "nem-plan" | "markdown" | "raw" | "bitacora";
};

export const TOOL_REGISTRY: Record<string, ToolDefinition> = {
  "plan-basico": {
    slug: "plan-basico",
    title: "Generador de Planeaciones",
    minPlan: 1,
    kind: "legacy-ai",
    generarFile: "generar_plan_basico.php",
    procesarFile: "procesar_planeacion.php",
    usageField: "usosPlanBasico",
    historialName: "Planeación Básica",
    responseShape: "nem-plan",
  },
  "plan-intermedio": {
    slug: "plan-intermedio",
    title: "Planeación Avanzada",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_plan_intermedio.php",
    procesarFile: "procesar_planeacion_intermedio.php",
    usageField: "usosPlanIntermedio",
    historialName: "Planeación Avanzada",
    responseShape: "nem-plan",
  },
  "planeacion-pale": {
    slug: "planeacion-pale",
    title: "Planeación por Niveles (PALE)",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_planeacion_avanzada_pale.php",
    procesarFile: "procesar_planeacion_avanzada_pale.php",
    usageField: "usosPlanIntermedio",
    historialName: "Planeación PALE",
    responseShape: "nem-plan",
  },
  "planeacion-pam": {
    slug: "planeacion-pam",
    title: "Planeación por Niveles (PAM)",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_planeacion_avanzada_pam.php",
    procesarFile: "procesar_planeacion_avanzada_pam.php",
    usageField: "usosPlanIntermedio",
    historialName: "Planeación PAM",
    responseShape: "nem-plan",
  },
  "educacion-fisica": {
    slug: "educacion-fisica",
    title: "Planeación de Educación Física",
    minPlan: 1,
    kind: "legacy-ai",
    generarFile: "generar_planeacion_fisica.php",
    procesarFile: "procesar_planeacion_fisica.php",
    usageField: "usosFisica",
    historialName: "Planeación Educación Física",
    responseShape: "markdown",
  },
  "evaluacion-diagnostica": {
    slug: "evaluacion-diagnostica",
    title: "Evaluación Diagnóstica",
    minPlan: 1,
    kind: "legacy-ai",
    generarFile: "generar_evaluacion.php",
    procesarFile: "procesar_diagnostico.php",
    usageField: "usosEvaluacionDiag",
    historialName: "Evaluaciones Diagnósticas",
    responseShape: "markdown",
  },
  examenes: {
    slug: "examenes",
    title: "Exámenes Personalizados",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_examen_nem.php",
    procesarFile: "procesar_examen_nem.php",
    usageField: "usosExamenes",
    historialName: "Exámenes BIM/TRIM (NEM)",
    responseShape: "markdown",
  },
  protocolos: {
    slug: "protocolos",
    title: "Protocolos Educativos",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "protocolostest.php",
    procesarFile: "procesar_protocolo.php",
    usageField: "usosProtocolos",
    historialName: "Protocolos Educativos",
    responseShape: "raw",
  },
  bitacora: {
    slug: "bitacora",
    title: "Bitácora de Incidentes",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_bitacora_de_profesor.php",
    procesarFile: "procesar_bitacora_de_profesor.php",
    usageField: "usosBitacora",
    historialName: "Bitácoras Docentes",
    responseShape: "bitacora",
  },
  simuladores: {
    slug: "simuladores",
    title: "Material de Estudio SIMULADORES",
    minPlan: 2,
    kind: "simulator-catalog",
    generarFile: "catalogo_de_servicios.php",
  },
  neurodivergentes: {
    slug: "neurodivergentes",
    title: "Planeación para NEURODIVERGENTES",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_planeacion_neurodivergentes.php",
    procesarFile: "procesar_planeacion_neurodivergentes.php",
    usageField: "usosPlanIntermedio",
    historialName: "Planificador Inclusivo (DUA)",
    responseShape: "nem-plan",
  },
  "cultura-paz": {
    slug: "cultura-paz",
    title: "Cultura de Paz y Convivencia",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_cultura_paz.php",
    procesarFile: "procesar_cultura_paz.php",
    usageField: "usosPlanIntermedio",
    historialName: "Cultura de Paz y Convivencia",
    responseShape: "raw",
  },
  "planeacion-transversal": {
    slug: "planeacion-transversal",
    title: "Planeación Transversal",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_planeacion_transversal.php",
    procesarFile: "procesar_planeacion_transversal.php",
    usageField: "usosPlanIntermedio",
    historialName: "Planeación Transversal",
    responseShape: "nem-plan",
  },
  adecuacion: {
    slug: "adecuacion",
    title: "Adecuación Inteligente",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_planeacion_potenciada.php",
    procesarFile: "procesar_planeacion_potenciada.php",
    usageField: "usosPlanIntermedio",
    historialName: "Adecuaciones Curriculares",
    responseShape: "nem-plan",
  },
  "simulador-padres": {
    slug: "simulador-padres",
    title: "Simulador de Crisis con Padres",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_simulador_padres_problematicos.php",
    procesarFile: "procesar_simulador_padres_problematicos.php",
    usageField: "usosPlanIntermedio",
    historialName: "Simulador Padres",
    responseShape: "raw",
  },
  "eventos-civicos": {
    slug: "eventos-civicos",
    title: "Eventos Cívicos y Periódicos",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_periodico_mural.php",
    procesarFile: "procesar_periodico_mural.php",
    usageField: "usosPlanIntermedio",
    historialName: "Eventos Cívicos",
    responseShape: "markdown",
  },
  "planeacion-asignaturas": {
    slug: "planeacion-asignaturas",
    title: "Planeación por Asignaturas",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_planeacion_competencias.php",
    procesarFile: "procesar_planeacion_competencias.php",
    usageField: "usosPlanIntermedio",
    historialName: "Planeación por Competencias",
    responseShape: "nem-plan",
  },
  "sesiones-psicologicas": {
    slug: "sesiones-psicologicas",
    title: "Sesiones Psicológicas",
    minPlan: 3,
    kind: "stub",
  },
  telesecundarias: {
    slug: "telesecundarias",
    title: "Planeación Telesecundarias",
    minPlan: 2,
    kind: "legacy-ai",
    generarFile: "generar_telesecundarias.php",
    procesarFile: "procesar_telesecundarias.php",
    usageField: "usosPlanIntermedio",
    historialName: "Planeación Telesecundarias",
    responseShape: "nem-plan",
  },
  usicamm: {
    slug: "usicamm",
    title: "Simulador y Tutor IA para USICAMM",
    minPlan: 2,
    kind: "legacy-static",
    generarFile: "index_agente_de_estudio.php",
  },
  padres: {
    slug: "padres",
    title: "Herramientas para Padres de familia",
    minPlan: 0,
    kind: "legacy-ai",
    generarFile: "generar_herramientas_de_padres_de_familia.php",
    procesarFile: "procesar_herramientas_de_padres_de_familia.php",
    usageField: "usosDiariosPadres",
    historialName: "Herramientas Padres",
    responseShape: "markdown",
  },
};

export const SIMULATOR_REGISTRY: Record<
  string,
  { title: string; generarFile: string; questionsFile: string }
> = {
  "ingreso-preescolar": {
    title: "Ingreso a Preescolar",
    generarFile: "generar_simulador_ingreso_preescolar.php",
    questionsFile: "preguntas_ingreso_preescolar.json",
  },
  "ingreso-primaria": {
    title: "Ingreso a Primaria",
    generarFile: "generar_simulador_ingreso_primaria.php",
    questionsFile: "preguntas_ingreso_primaria.json",
  },
  "ingreso-secundaria": {
    title: "Ingreso a Secundaria",
    generarFile: "generar_simulador_ingreso_secundaria.php",
    questionsFile: "preguntas_ingreso_secundaria.json",
  },
  "horizontal-preescolar": {
    title: "Promoción Horizontal Preescolar",
    generarFile: "generar_simulador_maestro_promocion_horizontal_preescolar.php",
    questionsFile: "preguntas_promocion_horizontal_preescolar.json",
  },
  "horizontal-primaria": {
    title: "Promoción Horizontal Primaria",
    generarFile: "generar_simulador_maestro_promocion_horizontal_primaria.php",
    questionsFile: "preguntas_promocion_horizontal_primaria.json",
  },
  "horizontal-secundaria": {
    title: "Promoción Horizontal Secundaria",
    generarFile: "generar_simulador_maestro_promocion_horizontal_secundaria.php",
    questionsFile: "preguntas_promocion_horizontal_secundaria.json",
  },
  "vertical-preescolar": {
    title: "Promoción Vertical Preescolar",
    generarFile: "generar_simulador_maestro_promocion_vertical_preescolar.php",
    questionsFile: "preguntas_promocion_vertical_preescolar.json",
  },
  "vertical-primaria": {
    title: "Promoción Vertical Primaria",
    generarFile: "generar_simulador_basico.php",
    questionsFile: "preguntas_promocion_vertical_primaria.json",
  },
  "vertical-secundaria": {
    title: "Promoción Vertical Secundaria",
    generarFile: "generar_simulador_maestro_promocion_vertical_secundaria.php",
    questionsFile: "preguntas_promocion_vertical_secundaria.json",
  },
  "director-horizontal-preescolar": {
    title: "Director Horizontal Preescolar",
    generarFile: "generar_simulador_promocion_horizontal_para_director_de_preescolar.php",
    questionsFile: "preguntas_promocion_horizontal_para_director_de_preescolar.json",
  },
  "director-horizontal-primaria": {
    title: "Director Horizontal Primaria",
    generarFile: "generar_simulador_promocion_horizontal_para_director_de_primaria.php",
    questionsFile: "preguntas_promocion_horizontal_para_director_de_primaria.json",
  },
  "director-horizontal-secundaria": {
    title: "Director Horizontal Secundaria",
    generarFile: "generar_simulador_promocion_horizontal_para_director_de_secundaria.php",
    questionsFile: "preguntas_promocion_horizontal_para_director_de_secundaria.json",
  },
  "supervisor-horizontal-primaria": {
    title: "Supervisor Horizontal Primaria",
    generarFile: "generar_simulador_promocion_horizontal_para_supervisor_de_primaria.php",
    questionsFile: "preguntas_promocion_horizontal_supervisor_primaria.json",
  },
};

export function getToolBySlug(slug: string) {
  return TOOL_REGISTRY[slug] ?? null;
}
