"use client";

import Link from "next/link";
import { useState } from "react";
import { useSession, signOut } from "next-auth/react";
import { navLinks } from "@/lib/tools";

export function Header() {
  const [open, setOpen] = useState(false);
  const { data: session } = useSession();

  return (
    <header className="sticky top-0 z-[100] h-[72px] border-b border-black/[0.08] bg-void/80 backdrop-blur-md">
      <div className="mx-auto flex h-full max-w-[1080px] items-center justify-between px-5">
        <Link
          href="/"
          className="flex items-center gap-3 text-[14px] font-[480] tracking-[-0.32px] text-snow"
        >
          <span className="grid h-9 w-9 place-items-center rounded-[10px] border border-black/[0.08] bg-graphite text-[11px] font-[500] text-signal-blue shadow-[var(--shadow-panel)]">
            PC
          </span>
          <span>Planeando con Causa</span>
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
            {navLinks.map((link) => (
              <li key={link.href}>
                <Link
                  href={link.href}
                  onClick={() => setOpen(false)}
                  className="block rounded-[10px] px-3 py-2 text-[14px] font-[400] tracking-[-0.32px] text-fog transition hover:bg-black/[0.05] hover:text-snow"
                >
                  {link.label}
                </Link>
              </li>
            ))}
          </ul>

          {session?.user ? (
            <div className="flex items-center gap-3 md:ml-2">
              <Link
                href="/panel"
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
              onClick={() => setOpen(false)}
              className="block rounded-[10px] px-3 py-2 text-[14px] font-[400] text-snow transition hover:bg-black/[0.05] md:ml-2"
            >
              Iniciar Sesión
            </Link>
          )}
        </nav>
      </div>
    </header>
  );
}
