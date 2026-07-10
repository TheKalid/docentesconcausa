"use client";

import { useRef, useState } from "react";

type Message = { text: string; from: "user" | "bot" };

export function ChatWidget() {
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([
    {
      text: "Hola. Soy el asistente de Docentes con Causa. ¿Tienes alguna duda? Pregúntanos.",
      from: "bot",
    },
  ]);
  const [input, setInput] = useState("");
  const [typing, setTyping] = useState(false);
  const listRef = useRef<HTMLDivElement>(null);

  const scrollToBottom = () => {
    requestAnimationFrame(() => {
      if (listRef.current) {
        listRef.current.scrollTop = listRef.current.scrollHeight;
      }
    });
  };

  const send = async () => {
    const text = input.trim();
    if (!text) return;

    setMessages((m) => [...m, { text, from: "user" }]);
    setInput("");
    setTyping(true);
    scrollToBottom();

    try {
      const res = await fetch("/api/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ mensaje: text }),
      });
      const data = await res.json();
      setMessages((m) => [
        ...m,
        {
          text: data.respuesta ?? "Lo siento, no pude procesar la respuesta.",
          from: "bot",
        },
      ]);
    } catch {
      setMessages((m) => [
        ...m,
        {
          text: "Hubo un error de conexión. Intenta de nuevo más tarde.",
          from: "bot",
        },
      ]);
    } finally {
      setTyping(false);
      scrollToBottom();
    }
  };

  return (
    <div className="fixed bottom-6 right-6 z-[200] flex flex-col items-end gap-4">
      {open && (
        <div className="flex h-[440px] w-[min(360px,calc(100vw-3rem))] flex-col overflow-hidden rounded-[12px] bg-graphite shadow-[var(--shadow-card)] ring-1 ring-black/[0.08]">
          <div className="flex items-center justify-between border-b border-black/[0.08] bg-charcoal px-4 py-3 text-snow">
            <div>
              <span className="block text-[12px] font-[500] tracking-[-0.26px]">
                Asistente Docente
              </span>
              <span className="text-[10px] text-mint">● En línea</span>
            </div>
            <button
              type="button"
              aria-label="Cerrar chat"
              onClick={() => setOpen(false)}
              className="rounded-[8.77px] bg-black/[0.05] px-2 py-1 text-[12px] text-fog hover:text-snow"
            >
              ✕
            </button>
          </div>

          <div
            ref={listRef}
            className="flex flex-1 flex-col gap-2 overflow-y-auto bg-void p-4"
          >
            {messages.map((m, i) => (
              <div
                key={i}
                className={`max-w-[85%] rounded-[12px] px-3 py-2 text-[12px] leading-[1.45] ${
                  m.from === "user"
                    ? "self-end bg-bone text-ink shadow-[var(--shadow-sm)]"
                    : "self-start bg-charcoal text-chalk shadow-[var(--shadow-panel)]"
                }`}
                dangerouslySetInnerHTML={
                  m.from === "bot" ? { __html: m.text } : undefined
                }
              >
                {m.from === "user" ? m.text : undefined}
              </div>
            ))}
            {typing && (
              <div className="self-start rounded-[12px] bg-charcoal px-3 py-2 text-[12px] text-fog shadow-[var(--shadow-panel)]">
                Escribiendo...
              </div>
            )}
          </div>

          <div className="flex items-center gap-2 border-t border-black/[0.08] bg-charcoal p-3">
            <input
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={(e) => e.key === "Enter" && send()}
              placeholder="Escribe tu pregunta..."
              className="flex-1 rounded-[10px] border border-black/[0.08] bg-black/[0.05] px-3 py-2 text-[12px] text-snow outline-none placeholder:text-ash focus:border-ring-blue"
            />
            <button
              type="button"
              aria-label="Enviar mensaje"
              onClick={send}
              className="grid h-9 w-9 place-items-center rounded-[10px] bg-bone text-[13px] text-ink transition hover:bg-snow"
            >
              ➤
            </button>
          </div>
        </div>
      )}

      <button
        type="button"
        aria-label={open ? "Cerrar ayuda" : "¿Necesitas ayuda?"}
        onClick={() => setOpen((v) => !v)}
        className={`rounded-full border border-black/[0.08] bg-graphite px-5 py-3.5 text-[14px] font-[500] tracking-[-0.32px] text-signal-blue shadow-[var(--shadow-card)] transition hover:bg-black/[0.05] ${
          open ? "" : "animate-[pulse-animation_2.5s_infinite]"
        }`}
      >
        {open ? "Cerrar" : "¿Necesitas ayuda?"}
      </button>
    </div>
  );
}
