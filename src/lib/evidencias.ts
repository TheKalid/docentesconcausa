export type EvidenciaCausa = "cancer" | "reforestacion" | "tea";

export type Evidencia = {
  image: string;
  imageAlt: string;
  causa: EvidenciaCausa;
  causaLabel: string;
  causaIcon: string;
  lugar: string;
  maestro: string;
  testimonio: string;
};

export const evidencias: Evidencia[] = [
  {
    image: "/evidencias/contraelcancer.webp",
    imageAlt: "Docente apoyando la causa contra el cáncer infantil",
    causa: "cancer",
    causaLabel: "Cáncer",
    causaIcon: "🎗",
    lugar: "Guadalajara, JAL",
    maestro: "Prof. Ana Sofía Ramírez",
    testimonio:
      "Ver la sonrisa de estos niños no tiene precio. Saber que mi suscripción ayuda a esto le da un nuevo significado a mi profesión.",
  },
  {
    image: "/evidencias/reforestacion.webp",
    imageAlt: "Maestro en campaña de reforestación",
    causa: "reforestacion",
    causaLabel: "Reforestación",
    causaIcon: "🌱",
    lugar: "Nevado de Toluca, EDOMEX",
    maestro: "Prof. Javier Mendoza",
    testimonio:
      "Enseñar a cuidar el planeta empieza con el ejemplo. Plantar estos árboles junto a la comunidad fue una experiencia inolvidable.",
  },
  {
    image: "/evidencias/ayudaaautismo.webp",
    imageAlt: "Docente en un centro de apoyo para TEA",
    causa: "tea",
    causaLabel: "TEA",
    causaIcon: "🧩",
    lugar: "Monterrey, NL",
    maestro: "Prof. Laura Fernández",
    testimonio:
      "Como docente, entiendo la importancia del apoyo especializado. Ver los recursos que se logran con las donaciones me llena de esperanza.",
  },
];

export const causaTagStyles: Record<
  EvidenciaCausa,
  { bg: string; text: string }
> = {
  cancer: { bg: "bg-coral/10", text: "text-coral" },
  reforestacion: { bg: "bg-mint/10", text: "text-mint" },
  tea: { bg: "bg-signal-blue/10", text: "text-signal-blue" },
};
