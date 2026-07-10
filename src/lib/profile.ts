import { subscriptionPlans } from "@/lib/plans";
import { normalizePlanActivo } from "@/lib/access";

export function getPlanLabel(planActivo: number | null | undefined) {
  const level = normalizePlanActivo(planActivo);
  if (level === 0) return "Sin suscripción";

  const plan = subscriptionPlans.find((item) => item.id === level);
  return plan?.name ?? "Sin suscripción";
}

export function getPlanStatus(planActivo: number | null | undefined) {
  return normalizePlanActivo(planActivo) > 0 ? "Activa" : "Inactiva";
}

export function formatProfileDate(date: Date | null | undefined) {
  if (!date) return "N/A";

  return new Intl.DateTimeFormat("es-MX", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(date);
}
