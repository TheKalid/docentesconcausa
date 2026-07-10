"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { useSession, signOut } from "next-auth/react";
import { headerToolLinks, navLinks } from "@/lib/tools";

const linkClassName =
  "block rounded-[10px] px-3 py-2 text-[14px] font-[400] tracking-[-0.32px] text-fog transition hover:bg-black/[0.05] hover:text-snow";

export function Header() {
  const [open, setOpen] = useState(false);
  const [toolsOpen, setToolsOpen] = useState(false);
  const toolsMenuRef = useRef<HTMLLIElement>(null);
  const { data: session } = useSession();

  useEffect(() => {
    if (!toolsOpen) return;

    function handleClickOutside(event: MouseEvent) {
      if (
        toolsMenuRef.current &&
        !toolsMenuRef.current.contains(event.target as Node)
      ) {
        setToolsOpen(false);
      }
    }

    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [toolsOpen]);

  function closeMenus() {
    setOpen(false);
    setToolsOpen(false);
  }

  return (
    <header className="sticky top-0 z-[100] h-[72px] border-b border-black/[0.08] bg-void/80 backdrop-blur-md">
      <div className="mx-auto flex h-full max-w-[1080px] items-center justify-between px-5">
        <Link
          href="/"
          className="flex items-center gap-3 text-[14px] font-[480] tracking-[-0.32px] text-snow"
        >
          <img
            src="/logo-header.png"
            alt="Docentes con causa"
            width={44}
            height={44}
            className="h-11 w-11 shrink-0 rounded-[10px] object-contain"
            decoding="async"
          />
          <span>Docentes con causa</span>
        </Link>

        <button
          type="button"
          aria-label={open ? "Cerrar menú" : "Abrir menú"}
          onClick={() => setOpen((v) => !v)}
          className="rounded-[10px] bg-black/[0.05] px-3 py-2 text-[14px] text-snow md:hidden"
        >
          {open ? "✕" : "☰"}
        </button>

        <nav
          className={`${
            open ? "flex" : "hidden"
          } absolute left-0 right-0 top-full flex-col gap-4 border-b border-black/[0.08] bg-void/95 p-5 shadow-[var(--shadow-panel)] backdrop-blur-md md:static md:flex md:flex-row md:items-center md:gap-6 md:border-0 md:bg-transparent md:p-0 md:shadow-none md:backdrop-blur-0`}
        >
          <ul className="flex flex-col gap-1 md:flex-row md:items-center md:gap-1">
            <li>
              <Link
                href={navLinks[0].href}
                onClick={closeMenus}
                className={linkClassName}
              >
                {navLinks[0].label}
              </Link>
            </li>

            <li ref={toolsMenuRef} className="relative">
              <button
                type="button"
                aria-expanded={toolsOpen}
                aria-haspopup="true"
                onClick={() => setToolsOpen((value) => !value)}
                className={`${linkClassName} flex w-full items-center justify-between gap-2 md:w-auto ${
                  toolsOpen ? "bg-black/[0.05] text-snow" : ""
                }`}
              >
                <span>Herramientas</span>
                <span
                  className={`text-[10px] text-steel transition-transform ${
                    toolsOpen ? "rotate-180" : ""
                  }`}
                  aria-hidden="true"
                >
                  ▾
                </span>
              </button>

              <div
                className={`${
                  toolsOpen ? "block" : "hidden"
                } md:absolute md:left-0 md:top-[calc(100%+4px)] md:z-[110] md:mt-0 md:w-[min(360px,calc(100vw-2.5rem))]`}
              >
                <div className="mt-1 max-h-[min(70vh,520px)] overflow-y-auto rounded-[12px] border border-black/[0.08] bg-void/95 p-2 shadow-[var(--shadow-panel)] backdrop-blur-md md:mt-0">
                  <p className="px-3 py-2 text-[10px] font-[500] uppercase tracking-[0.16em] text-steel">
                    Todas las herramientas
                  </p>
                  <ul className="flex flex-col gap-0.5">
                    {headerToolLinks.map((tool) => (
                      <li key={tool.href}>
                        <Link
                          href={tool.href}
                          onClick={closeMenus}
                          className="flex items-start gap-3 rounded-[10px] px-3 py-2.5 text-[14px] tracking-[-0.32px] text-fog transition hover:bg-black/[0.05] hover:text-snow"
                        >
                          <span aria-hidden="true" className="mt-0.5 shrink-0">
                            {tool.icon}
                          </span>
                          <span className="leading-[1.35]">{tool.label}</span>
                        </Link>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            </li>

            <li>
              <Link
                href={navLinks[1].href}
                onClick={closeMenus}
                className={linkClassName}
              >
                {navLinks[1].label}
              </Link>
            </li>
          </ul>

          {session?.user ? (
            <div className="flex items-center gap-3 border-t border-black/[0.08] pt-4 md:ml-6 md:border-l md:border-t-0 md:pl-6 md:pt-0">
              <Link
                href="/perfil"
                className="flex items-center gap-2 rounded-[10px] bg-black/[0.05] px-4 py-2 text-[14px] font-[400] text-snow transition hover:bg-black/[0.08]"
              >
                {session.user.name ?? "Mi cuenta"}
              </Link>
              <button
                type="button"
                onClick={() => signOut({ callbackUrl: "/" })}
                className="rounded-[10px] px-3 py-2 text-[14px] font-[400] text-fog transition hover:text-snow"
              >
                Salir
              </button>
            </div>
          ) : (
            <Link
              href="/login"
              onClick={closeMenus}
              className="block rounded-[10px] border-t border-black/[0.08] px-3 py-2 pt-4 text-[14px] font-[400] text-snow transition hover:bg-black/[0.05] md:ml-6 md:border-l md:border-t-0 md:pl-6 md:pt-2"
            >
              Iniciar Sesión
            </Link>
          )}
        </nav>
      </div>
    </header>
  );
}
