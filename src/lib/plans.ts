export type SubscriptionPlan = {
  id: 1 | 2 | 3;
  icon: string;
  name: string;
  level: string;
  price: number;
  recommended?: boolean;
  disabled?: boolean;
  benefits: string[];
  stripeBaseUrl?: string;
};

export const subscriptionPlans: SubscriptionPlan[] = [
  {
    id: 1,
    icon: "👩‍🏫",
    name: "Docente con Causa",
    level: "Nivel Básico — IVA incluido",
    price: 125,
    benefits: [
      "5 planeaciones por mes.",
      "Acceso a la IA educativa entrenada en educación y NEM.",
      "Planeaciones automáticas.",
      "Acceso a Evaluación Diagnóstica.",
      "Acceso a la IA para clases de Educación Física.",
      "Ideal para iniciar con IA educativa.",
      "Acceso a nuestro catálogo de Biblioteca.",
    ],
    stripeBaseUrl: "https://buy.stripe.com/00w3cveQQarW6P1d7H4AU02",
  },
  {
    id: 2,
    icon: "🧑‍🏫",
    name: "Mentor con Causa",
    level: "Nivel Intermedio — IVA incluido",
    price: 179,
    recommended: true,
    benefits: [
      "7 planeaciones por mes.",
      "Todo lo del plan Básico +",
      "IA más personalizable y poderosa.",
      "Acceso a cursos de formación docente.",
      "Botón de protocolos escolares.",
      "Acceso a IA para analizar la Bitácora Docente.",
    ],
    stripeBaseUrl: "https://buy.stripe.com/eVqcN59wwfMg8X98Rr4AU03",
  },
  {
    id: 3,
    icon: "👨‍🏫",
    name: "Líder con Causa",
    level: "Nivel Avanzado — IVA incluido",
    price: 347,
    disabled: true,
    benefits: [
      "10 planeaciones por mes.",
      "Todo lo del plan Intermedio +",
      "1 sesión psicológica mensual.",
      "Apoyo emocional y profesional continuo de parte de un especialista.",
      "Preguntas diarias sobre la NEM enviadas a tu celular.",
    ],
  },
];

export function stripeCheckoutUrl(baseUrl: string, userId: number) {
  const url = new URL(baseUrl);
  url.searchParams.set("client_reference_id", String(userId));
  return url.toString();
}
