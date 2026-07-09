import { readFileSync, readdirSync } from "fs";
import path from "path";

const LEGACY_HTML = path.join(process.cwd(), "legacy", "html");

export function loadLegacySystemPrompt(procesarFile: string): string {
  const filePath = path.join(LEGACY_HTML, procesarFile);
  const content = readFileSync(filePath, "utf8");

  const heredoc = content.match(/\$systemPrompt\s*=\s*<<<EOT\n([\s\S]*?)\nEOT;/);
  if (heredoc) return heredoc[1];

  const heredocAlt = content.match(/\$PROMPT_SISTEMA\s*=\s*<<<EOT\n([\s\S]*?)\nEOT;/);
  if (heredocAlt) return heredocAlt[1];

  const stringPrompt = content.match(/\$systemPrompt\s*=\s*"([\s\S]*?)";/);
  if (stringPrompt) {
    return stringPrompt[1].replace(/\\n/g, "\n").replace(/\\"/g, '"');
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
