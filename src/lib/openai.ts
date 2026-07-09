const OPENAI_URL = "https://api.openai.com/v1/chat/completions";

export async function chatCompletionJson(
  systemPrompt: string,
  userPrompt: string,
  options?: { temperature?: number; timeoutMs?: number }
) {
  const apiKey = process.env.OPENAI_API_KEY;
  if (!apiKey) {
    throw new Error(
      "OPENAI_API_KEY no está configurada. Agrega la variable en tu archivo .env."
    );
  }

  const controller = new AbortController();
  const timeout = setTimeout(
    () => controller.abort(),
    options?.timeoutMs ?? 110_000
  );

  try {
    const response = await fetch(OPENAI_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${apiKey}`,
      },
      body: JSON.stringify({
        model: "gpt-4o-mini",
        response_format: { type: "json_object" },
        messages: [
          { role: "system", content: systemPrompt },
          { role: "user", content: userPrompt },
        ],
        temperature: options?.temperature ?? 0.7,
      }),
      signal: controller.signal,
    });

    const decoded = (await response.json()) as {
      error?: { message?: string };
      choices?: Array<{ message?: { content?: string } }>;
    };

    if (!response.ok) {
      throw new Error(
        decoded.error?.message ??
          "El motor de inteligencia artificial está temporalmente fuera de servicio."
      );
    }

    const content = decoded.choices?.[0]?.message?.content;
    if (!content) {
      throw new Error("La IA no devolvió contenido válido.");
    }

    const cleaned = content
      .replace(/^```json\s*/i, "")
      .replace(/\s*```$/i, "")
      .trim();

    const parsed = JSON.parse(cleaned) as Record<string, unknown>;
    return parsed;
  } finally {
    clearTimeout(timeout);
  }
}

export async function chatCompletionText(
  systemPrompt: string,
  userPrompt: string,
  options?: { temperature?: number; timeoutMs?: number }
) {
  const apiKey = process.env.OPENAI_API_KEY;
  if (!apiKey) {
    throw new Error(
      "OPENAI_API_KEY no está configurada. Agrega la variable en tu archivo .env."
    );
  }

  const controller = new AbortController();
  const timeout = setTimeout(
    () => controller.abort(),
    options?.timeoutMs ?? 110_000
  );

  try {
    const response = await fetch(OPENAI_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${apiKey}`,
      },
      body: JSON.stringify({
        model: "gpt-4o-mini",
        messages: [
          { role: "system", content: systemPrompt },
          { role: "user", content: userPrompt },
        ],
        temperature: options?.temperature ?? 0.7,
      }),
      signal: controller.signal,
    });

    const decoded = (await response.json()) as {
      error?: { message?: string };
      choices?: Array<{ message?: { content?: string } }>;
    };

    if (!response.ok) {
      throw new Error(
        decoded.error?.message ??
          "El motor de inteligencia artificial está temporalmente fuera de servicio."
      );
    }

    const content = decoded.choices?.[0]?.message?.content;
    if (!content) {
      throw new Error("La IA no devolvió contenido válido.");
    }

    return content.trim();
  } finally {
    clearTimeout(timeout);
  }
}
