import { readFileSync, readdirSync } from "fs";
import path from "path";

const LEGACY_HTML = path.join(process.cwd(), "legacy", "html");

const HEREDOC_VARS = ["systemPrompt", "PROMPT_SISTEMA"] as const;

function unescapePhpString(value: string) {
  return value.replace(/\\n/g, "\n").replace(/\\r/g, "\r").replace(/\\t/g, "\t").replace(/\\"/g, '"');
}

function extractHeredoc(content: string, varName: string) {
  const pattern = new RegExp(
    `\\$${varName}\\s*=\\s*<<<EOT[\\r\\n]+([\\s\\S]*?)[\\r\\n]+EOT;`,
    "m"
  );
  return content.match(pattern)?.[1] ?? null;
}

function extractQuotedString(content: string, varName: string) {
  const anchor = content.match(
    new RegExp(`\\$${varName}\\s*=\\s*"`, "m")
  );
  if (!anchor || anchor.index === undefined) return null;

  const start = anchor.index + anchor[0].length;
  let result = "";
  let escaped = false;

  for (let i = start; i < content.length; i++) {
    const char = content[i];

    if (escaped) {
      result += char;
      escaped = false;
      continue;
    }

    if (char === "\\") {
      escaped = true;
      result += char;
      continue;
    }

    if (char === '"') {
      return unescapePhpString(result);
    }

    result += char;
  }

  return null;
}

export function loadLegacySystemPrompt(procesarFile: string): string {
  const filePath = path.join(LEGACY_HTML, procesarFile);
  const content = readFileSync(filePath, "utf8");

  for (const varName of HEREDOC_VARS) {
    const heredoc = extractHeredoc(content, varName);
    if (heredoc) return heredoc.trim();
  }

  for (const varName of HEREDOC_VARS) {
    const quoted = extractQuotedString(content, varName);
    if (quoted) return quoted.trim();
  }

  throw new Error(`No se encontró system prompt en ${procesarFile}`);
}

export function loadProtocolContext(): string {
  const dir = path.join(process.cwd(), "public", "data", "protocolos");
  let context = "";

  for (const file of readdirSync(dir)) {
    if (!file.endsWith(".json")) continue;
    const body = readFileSync(path.join(dir, file), "utf8");
    context += `\n\n--- DOCUMENTO: ${file} ---\n${body}`;
  }

  return context;
}

export function validateAllToolPrompts(
  procesarFiles: Array<string | undefined>
): { ok: string[]; failed: Array<{ file: string; error: string }> } {
  const ok: string[] = [];
  const failed: Array<{ file: string; error: string }> = [];
  const unique = [...new Set(procesarFiles.filter(Boolean))] as string[];

  for (const file of unique) {
    try {
      loadLegacySystemPrompt(file);
      ok.push(file);
    } catch (error) {
      failed.push({
        file,
        error: error instanceof Error ? error.message : "Error desconocido",
      });
    }
  }

  return { ok, failed };
}
