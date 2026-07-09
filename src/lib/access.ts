export type PlanLevel = 0 | 1 | 2 | 3;

export function normalizePlanActivo(plan: number | null | undefined): PlanLevel {
  const value = plan ?? 0;
  if (value >= 3) return 3;
  if (value >= 2) return 2;
  if (value >= 1) return 1;
  return 0;
}

export function canUsePlan(userPlan: number | null | undefined, required: PlanLevel) {
  return normalizePlanActivo(userPlan) >= required;
}

export function toolHref(
  userPlan: number | null | undefined,
  required: PlanLevel,
  toolPath: string
) {
  return canUsePlan(userPlan, required) ? toolPath : "/planes";
}
