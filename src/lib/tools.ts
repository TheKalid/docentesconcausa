import type { PlanLevel } from "@/lib/access";

export type Tool = {
  icon: string;
  title: string;
  description: string;
  cta: string;
  href: string;
  minPlan: PlanLevel;
  beta?: boolean;
};

export const tools: Tool[] = [
  {
    icon: "📌",
    title: "Generador de Planeaciones",
    description:
      "Ahorra tiempo con planeaciones didácticas personalizadas, generadas por IA y alineadas a la NEM en minutos.",
    cta: "Prueba el Plan Básico",
    href: "/herramientas/plan-basico",
    minPlan: 1,
  },
  {
    icon: "✨",
    title: "Planeación Avanzada",
    description:
      "Añade el contexto de tu grupo y tus necesidades específicas para obtener una planeación profundamente adaptada y realista.",
    cta: "Crear Plan Avanzado",
    href: "/herramientas/plan-intermedio",
    minPlan: 2,
  },
  {
    icon: "📶",
    title: "Planeación por Niveles (PALE)",
    description:
      "Crea planes para 1º y 2º de primaria, con actividades específicas para cada nivel de lectoescritura: presilábico, silábico, silábico-alfabético y alfabético.",
    cta: "Diseñar Plan Diferenciado",
    href: "/herramientas/planeacion-pale",
    minPlan: 2,
  },
  {
    icon: "🧮",
    title: "Planeación por Niveles (PAM)",
    description:
      "Propuestas de aprendizaje para matemáticas, trabajando con el campo de saberes y pensamiento científico. Elige los PDA y te sugerimos las fichas PAM.",
    cta: "Diseñar Plan PAM",
    href: "/herramientas/planeacion-pam",
    minPlan: 2,
  },
  {
    icon: "🤸‍♀️",
    title: "Planeación de Educación Física",
    description:
      "Crea planeaciones para Educación Física, desde preescolar hasta secundaria, alineadas a los PDA y contenidos vigentes.",
    cta: "Crear Plan de E.F.",
    href: "/herramientas/educacion-fisica",
    minPlan: 1,
  },
  {
    icon: "📊",
    title: "Evaluación Diagnóstica",
    description:
      "Maestro, aquí podemos elaborar tu evaluación diagnóstica para conocer el punto de partida de tus alumnos.",
    cta: "Elaborar Diagnóstico",
    href: "/herramientas/evaluacion-diagnostica",
    minPlan: 1,
  },
  {
    icon: "🧐",
    title: "Exámenes Personalizados",
    description:
      "Elige los contenidos, campos y PDA para generar exámenes con nivel de complejidad básico, intermedio o avanzado.",
    cta: "Crear Examen",
    href: "/herramientas/examenes",
    minPlan: 2,
  },
  {
    icon: "📄",
    title: "Protocolos Educativos",
    description:
      "Accede a normativas y guías actualizadas para asegurar que tu práctica docente cumpla con los más altos estándares.",
    cta: "Consultar Protocolos",
    href: "/herramientas/protocolos",
    minPlan: 2,
  },
  {
    icon: "📝",
    title: "Bitácora de Incidentes",
    description:
      "Registra, analiza y gestiona incidentes escolares con el apoyo de IA para garantizar el seguimiento y cumplimiento de protocolos.",
    cta: "Gestionar Bitácora",
    href: "/herramientas/bitacora",
    minPlan: 2,
  },
  {
    icon: "📚",
    title: "Material de Estudio SIMULADORES",
    description:
      "Prepárate para tu crecimiento profesional con material, simuladores y guías actualizadas para tu éxito.",
    cta: "Estudiar Ahora",
    href: "/herramientas/simuladores",
    minPlan: 2,
  },
  {
    icon: "🧠",
    title: "Planeación para NEURODIVERGENTES",
    description:
      "Genera planes de clase inclusivos y adaptados para estudiantes con TDAH, autismo, dislexia y otras neurodivergencias.",
    cta: "Crear Plan Inclusivo",
    href: "/herramientas/neurodivergentes",
    minPlan: 2,
  },
  {
    icon: "🕊️",
    title: "Cultura de Paz y Convivencia",
    description:
      "Genera estrategias y actividades para promover un ambiente escolar positivo, respetuoso y de convivencia pacífica.",
    cta: "Fomentar la Paz",
    href: "/herramientas/cultura-paz",
    minPlan: 2,
  },
  {
    icon: "🧩",
    title: "Planeación Transversal",
    description:
      "Diseña proyectos integrales seleccionando múltiples campos formativos, ejes articuladores y Procesos de Desarrollo de Aprendizaje (PDA) en una sola secuencia didáctica.",
    cta: "Crear Planeación Transversal",
    href: "/herramientas/planeacion-transversal",
    minPlan: 2,
  },
  {
    icon: "📂",
    title: "Adecuación Inteligente",
    description:
      "Sube tu planeación y mejora su impacto. Describe las necesidades o problemáticas de tus estudiantes y recibe adecuaciones personalizadas utilizando todo el poder de la inteligencia artificial.",
    cta: "Subir y Adecuar Planeación",
    href: "/herramientas/adecuacion",
    minPlan: 2,
  },
  {
    icon: "🛡️",
    title: "Simulador de Crisis con Padres",
    description:
      "¿Reuniones que terminan en conflicto? Entrena con nuestro simulador de IA. Enfréntate a escenarios problemáticos virtuales y domina el arte de la comunicación asertiva antes de la reunión real.",
    cta: "Iniciar Simulador",
    href: "/herramientas/simulador-padres",
    minPlan: 2,
  },
  {
    icon: "📆",
    title: "Eventos Cívicos y Periódicos",
    description:
      "Organiza fácilmente efemérides, bailables, kermeses, representaciones y altares adaptados a las fechas cívicas del ciclo escolar.",
    cta: "Organizar Evento",
    href: "/herramientas/eventos-civicos",
    minPlan: 2,
    beta: true,
  },
  {
    icon: "✏️",
    title: "Planeación por Asignaturas",
    description:
      "Diseña secuencias didácticas enfocadas en el desarrollo de contenidos específicos, integrando los PDA vigentes con una evaluación formativa estructurada.",
    cta: "Crear Planeación",
    href: "/herramientas/planeacion-asignaturas",
    minPlan: 2,
    beta: true,
  },
  {
    icon: "🛋️",
    title: "Sesiones Psicológicas",
    description:
      "Accede a tu sesión de apoyo psicológico mensual. Un espacio seguro, confidencial y profesional dedicado a cuidar tu bienestar emocional y mental.",
    cta: "Ingresar a Sesión",
    href: "/herramientas/sesiones-psicologicas",
    minPlan: 3,
  },
  {
    icon: "📺",
    title: "Planeación Telesecundarias",
    description:
      "Diseña tus clases articulando hasta dos campos formativos. Selecciona múltiples asignaturas con sus respectivos contenidos y PDA en una herramienta exclusiva para el modelo de Telesecundaria.",
    cta: "Ingresar a Herramienta",
    href: "/herramientas/telesecundarias",
    minPlan: 2,
  },
  {
    icon: "🤖",
    title: "Simulador y Tutor IA para USICAMM",
    description:
      "Nuestro algoritmo de IA optimizado para estudiar y aprobar los exámenes de USICAMM. Un agente interactivo que evalúa lo positivo y negativo de tus proyectos, ayudándote a mejorar mediante la recomendación autónoma de talleres, libros, capacitaciones y diplomados.",
    cta: "Ingresar a Herramienta",
    href: "/herramientas/usicamm",
    minPlan: 2,
  },
];

export const navLinks = [
  { label: "Misión", href: "/mision" },
  { label: "Creadores", href: "/creadores" },
  { label: "Evidencias", href: "/evidencias" },
  { label: "Biblioteca", href: "/biblioteca" },
  { label: "Tutorial", href: "/tutorial" },
];
