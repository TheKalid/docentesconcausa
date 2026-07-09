import { NextResponse } from "next/server";
import { auth } from "@/lib/auth";
import { canUsePlan } from "@/lib/access";
import { getUsageRemaining } from "@/lib/tool-usage";
import { getToolBySlug } from "@/lib/tools/registry";
import { runToolGeneration } from "@/lib/tools/run-generation";

type RouteContext = { params: Promise<{ slug: string }> };

export async function GET(_request: Request, context: RouteContext) {
  const { slug } = await context.params;
  const tool = getToolBySlug(slug);

  if (!tool || tool.kind !== "legacy-ai") {
    return NextResponse.json({ error: "Herramienta no encontrada." }, { status: 404 });
  }

  const session = await auth();
  if (!session?.user?.id) {
    return NextResponse.json({ error: "No autenticado." }, { status: 401 });
  }

  const planActivo = session.user.planActivo ?? 0;
  if (tool.minPlan > 0 && !canUsePlan(planActivo, tool.minPlan)) {
    return NextResponse.json({ error: "Plan insuficiente." }, { status: 403 });
  }

  const userId = Number(session.user.id);
  const usosRestantes = tool.usageField
    ? await getUsageRemaining(userId, tool.usageField)
    : 0;

  return NextResponse.json({
    usosRestantes,
    userName: session.user.name ?? "Docente",
    slug,
  });
}

export async function POST(request: Request, context: RouteContext) {
  const { slug } = await context.params;
  const tool = getToolBySlug(slug);

  if (!tool || tool.kind !== "legacy-ai") {
    return NextResponse.json({ error: "Herramienta no encontrada." }, { status: 404 });
  }

  const session = await auth();
  if (!session?.user?.id) {
    return NextResponse.json(
      { success: false, error: "No se ha iniciado sesión." },
      { status: 401 }
    );
  }

  const planActivo = session.user.planActivo ?? 0;
  if (!canUsePlan(planActivo, tool.minPlan)) {
    return NextResponse.json(
      { success: false, error: "Plan insuficiente." },
      { status: 403 }
    );
  }

  const body = (await request.json().catch(() => null)) as Record<string, unknown> | null;
  if (!body) {
    return NextResponse.json(
      { success: false, error: "Datos inválidos." },
      { status: 400 }
    );
  }

  const ip =
    request.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ??
    request.headers.get("x-real-ip") ??
    "desconocida";

  try {
    const result = await runToolGeneration(
      tool,
      Number(session.user.id),
      body,
      ip
    );
    return NextResponse.json(result);
  } catch (error) {
    const message =
      error instanceof Error
        ? error.message
        : "No pudimos generar el contenido en este momento.";

    return NextResponse.json(
      {
        status: "error",
        success: false,
        error: message,
      },
      { status: message.includes("agotado") ? 200 : 500 }
    );
  }
}
