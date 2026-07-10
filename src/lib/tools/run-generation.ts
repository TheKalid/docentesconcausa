import { chatCompletionJson, chatCompletionText } from "@/lib/openai";
import { deductUsage, refundUsage } from "@/lib/tool-usage";
import {
  loadLegacySystemPrompt,
  loadProtocolContext,
} from "@/lib/tools/prompt-loader";
import type { ToolDefinition } from "@/lib/tools/registry";

function sanitizeUserPrompt(body: Record<string, unknown>) {
  if (typeof body.prompt !== "string") return null;
  const prompt = body.prompt.trim();
  if (!prompt) return null;
  if (prompt.length > 1500) {
    throw new Error("La solicitud es demasiado extensa.");
  }
  return prompt.replace(/<[^>]*>/g, "");
}

function buildUserPrompt(slug: string, body: Record<string, unknown>): string {
  const sanitizedPrompt = sanitizeUserPrompt(body);
  if (sanitizedPrompt) return sanitizedPrompt;

  switch (slug) {
    case "evaluacion-diagnostica": {
      const areas = Array.isArray(body.areas)
        ? body.areas.join(", ")
        : String(body.areas ?? "");
      return `Genera la evaluación diagnóstica para:
- Grado: ${String(body.grado ?? "").replace(/_/g, " ")}
- Áreas: ${areas}
- Alumnos: ${body.num_estudiantes ?? "No especificado"}
- Contexto/Necesidad: ${body.necesidad ?? "Ninguna especificada."}`;
    }
    case "examenes":
      return `Datos para el examen:
- Grado: ${body.grado}
- Asignatura: ${body.asignatura ?? "No especificada"}
- Campo Formativo: ${body.campoFormativo ?? "No especificado"}
- Contenido: ${body.contenido ?? "No especificado"}
- PDA: ${body.pda}
- Nivel de Complejidad: ${body.complejidad}`;
    case "protocolos":
      return `Situación: "${body.consulta ?? body.query ?? ""}"
Nivel Educativo: ${body.nivel ?? body.nivel_educativo ?? "No especificado"}`;
    case "cultura-paz":
      return `Diseña una actividad para promover la Cultura de Paz.
- Tema Principal: ${body.tema_principal}
- Subtema/Enfoque: ${body.subtema}
- Estrategia preferida: ${body.estrategia_didactica ?? "No especificada"}
- Duración esperada: ${body.duracion ?? "No especificada"}
- Contexto del Grupo: ${body.contexto_grupo ?? "No especificado"}`;
    case "padres":
      return `Actúa como un psicólogo educativo y experto en crianza positiva con gran empatía.
**Contexto del problema:**
- Categoría General: ${body.categoria}
- Problema Específico: ${body.problema}
- Descripción Adicional: ${body.descripcion ?? "No se proporcionó descripción adicional."}
Responde en Markdown con las secciones: Entendiendo la Situación, Estrategias Prácticas, Cómo Comunicarte, Cuándo Considerar Ayuda Profesional.`;
    default:
      return JSON.stringify(body, null, 2);
  }
}

function bitacoraPrompt(action: string, data: Record<string, unknown>) {
  switch (action) {
    case "analizar":
      return {
        system:
          "Tu única tarea es analizar la calidad de una bitácora docente. Recibirás datos en JSON. Devuelve un objeto JSON con una clave 'feedback' que contenga un array de objetos. Cada objeto debe tener 'status' ('OK' o 'WARN') y 'mensaje'. Valida que los campos no estén vacíos y que la descripción sea objetiva (sin juicios de valor ni opiniones).",
        user: JSON.stringify({ datos_bitacora: data.datos_bitacora }),
      };
    case "obtener_protocolo":
      return {
        system:
          "Eres un Asesor Experto en Protocolos Educativos. Basado en la descripción de un incidente, genera una recomendación clara y paso a paso. Tu respuesta debe ser un objeto JSON con una clave 'recomendacion_html' que contenga el string HTML con la guía. El HTML debe estar bien formateado con títulos (<h3>), párrafos (<p>) y listas (<ul><li>). Incluye siempre un aviso de responsabilidad al final del HTML.",
        user: JSON.stringify(data),
      };
    case "generar_vista_previa":
      return {
        system:
          "Tu única tarea es formatear los datos de una bitácora en un HTML limpio y profesional para una vista previa oficial escolar. Usa divs, clases para estilos y una estructura formal (incluyendo espacios para firmas del docente y directivo). Recibirás los datos en JSON. Devuelve un objeto JSON con una clave 'html_preview' que contenga el string HTML completo y bien estructurado.",
        user: JSON.stringify({ datos_bitacora: data.datos_bitacora }),
      };
    default:
      throw new Error("Acción de bitácora no válida.");
  }
}

function formatLegacyResponse(
  tool: ToolDefinition,
  iaData: Record<string, unknown> | string,
  usosRestantes: number
) {
  if (tool.slug === "educacion-fisica") {
    return {
      status: "completo",
      plan: iaData as string,
      usos_restantes: usosRestantes,
    };
  }

  if (tool.slug === "examenes") {
    return {
      success: true,
      examen: iaData,
      creditos_restantes: usosRestantes,
    };
  }

  if (tool.slug === "cultura-paz" || tool.slug === "simulador-padres") {
    return {
      success: true,
      data: iaData,
      usos_restantes: usosRestantes,
    };
  }

  if (tool.slug === "padres") {
    return {
      success: true,
      recomendacion: iaData,
      usos_restantes: usosRestantes,
    };
  }

  const data = iaData as Record<string, unknown>;

  switch (tool.responseShape) {
    case "nem-plan":
      return {
        status: "completo",
        plan: data,
        usos_restantes: usosRestantes,
      };
    case "markdown": {
      const markdown =
        (data.evaluacion as string) ??
        (data.examen_texto as string) ??
        (data.contenido_markdown as string) ??
        (data.planeacion as string) ??
        JSON.stringify(data, null, 2);
      return {
        status: "completo",
        data: markdown,
        usos_restantes: usosRestantes,
      };
    }
    case "bitacora":
      return {
        success: true,
        data: iaData,
        usos_restantes: usosRestantes,
      };
    case "raw":
    default:
      return {
        status: "completo",
        data,
        usos_restantes: usosRestantes,
        success: true,
      };
  }
}

export function mapGenerationError(error: unknown) {
  if (error instanceof Error) {
    if (error.message === "NO_CREDITS") {
      return "Has agotado tus créditos para esta herramienta.";
    }
    if (error.message.includes("OPENAI_API_KEY")) {
      return "El servicio de IA no está configurado. Contacta al administrador.";
    }
    if (error.message.includes("No se encontró system prompt")) {
      return "No pudimos generar el contenido en este momento. Inténtelo de nuevo más tarde. No se han descontado créditos.";
    }
    if (error.message.includes("motor de inteligencia artificial")) {
      return "No pudimos generar la planeación en este momento. Inténtelo de nuevo más tarde. No se han descontado créditos.";
    }
    return error.message;
  }
  return "No pudimos generar el contenido en este momento. Inténtelo de nuevo más tarde.";
}

export async function runToolGeneration(
  tool: ToolDefinition,
  userId: number,
  body: Record<string, unknown>,
  ip: string
) {
  if (!tool.procesarFile || !tool.usageField || !tool.historialName) {
    throw new Error("Herramienta no configurada para generación.");
  }

  try {
    const usosRestantes = await deductUsage(
      userId,
      tool.usageField,
      tool.historialName,
      ip
    );

    let systemPrompt: string;
    let userPrompt: string;

    if (tool.slug === "bitacora") {
      const action = String(body.action ?? "");
      const prompts = bitacoraPrompt(action, (body.data as Record<string, unknown>) ?? {});
      systemPrompt = prompts.system;
      userPrompt = prompts.user;
    } else if (tool.slug === "protocolos") {
      const context = loadProtocolContext();
      systemPrompt = loadLegacySystemPrompt(tool.procesarFile).replace(
        "{$contexto_oficial}",
        context
      );
      userPrompt = buildUserPrompt(tool.slug, body);
    } else {
      systemPrompt = loadLegacySystemPrompt(tool.procesarFile);
      userPrompt = buildUserPrompt(tool.slug, body);
    }

    if (tool.slug === "padres") {
      const markdown = await chatCompletionText(
        "Eres un psicólogo educativo experto en crianza positiva. Responde en Markdown estructurado.",
        userPrompt
      );
      return {
        success: true,
        recomendacion: markdown,
        problema: String(body.problema ?? ""),
        usos_restantes: usosRestantes,
      };
    }

    if (tool.slug === "educacion-fisica") {
      const markdown = await chatCompletionText(
        systemPrompt,
        `Por favor elabora la planeación de educación física con estos datos:\n${userPrompt}`
      );
      return formatLegacyResponse(tool, markdown, usosRestantes);
    }

    const temperature =
      tool.slug === "protocolos" ? 0.3 : tool.slug === "adecuacion" ? 0.6 : 0.7;

    const iaData = await chatCompletionJson(systemPrompt, userPrompt, {
      temperature,
    });

    return formatLegacyResponse(tool, iaData, usosRestantes);
  } catch (error) {
    if (
      tool.usageField &&
      tool.historialName &&
      error instanceof Error &&
      error.message !== "NO_CREDITS"
    ) {
      try {
        await refundUsage(userId, tool.usageField, tool.historialName);
      } catch {
        // ignore refund failure
      }
    }

    if (error instanceof Error && error.message === "NO_CREDITS") {
      throw new Error("Has agotado tus créditos para esta herramienta.");
    }

    throw error;
  }
}
