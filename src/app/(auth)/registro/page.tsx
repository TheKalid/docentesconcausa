"use client";

import Link from "next/link";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { signIn } from "next-auth/react";
import { AuthShell } from "@/components/AuthShell";

export default function RegistroPage() {
  const router = useRouter();
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const onSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    const formData = new FormData(e.currentTarget);
    const payload = {
      name: formData.get("name"),
      phone: formData.get("phone"),
      email: formData.get("email"),
      password: formData.get("password"),
    };

    const res = await fetch("/api/auth/register", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      const data = await res.json().catch(() => ({}));
      setError(data.error ?? "No se pudo crear la cuenta.");
      setLoading(false);
      return;
    }

    await signIn("credentials", {
      email: payload.email,
      password: payload.password,
      redirect: false,
    });

    router.push("/");
    router.refresh();
  };

  return (
    <AuthShell
      title="Crear Cuenta"
      subtitle="Únete a la comunidad de Planeando con Causa"
      footer={
        <p>
          ¿Ya tienes una cuenta?{" "}
          <Link href="/login" className="text-snow hover:underline">
            Inicia sesión aquí
          </Link>
        </p>
      }
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        {error && (
          <p className="rounded-[8.77px] border border-coral/50 bg-coral/10 px-4 py-2 text-[13px] text-coral">
            {error}
          </p>
        )}

        <label className="flex flex-col gap-1.5 text-[13px] font-[400] tracking-[-0.26px] text-chalk">
          Nombre Completo
          <input
            name="name"
            type="text"
            required
            autoComplete="name"
            className="rounded-[10px] border border-black/[0.08] bg-charcoal px-4 py-2.5 text-snow outline-none placeholder:text-ash focus:border-ring-blue"
          />
        </label>

        <label className="flex flex-col gap-1.5 text-[13px] font-[400] tracking-[-0.26px] text-chalk">
          Teléfono (WhatsApp)
          <input
            name="phone"
            type="tel"
            autoComplete="tel"
            className="rounded-[10px] border border-black/[0.08] bg-charcoal px-4 py-2.5 text-snow outline-none placeholder:text-ash focus:border-ring-blue"
          />
        </label>

        <label className="flex flex-col gap-1.5 text-[13px] font-[400] tracking-[-0.26px] text-chalk">
          Correo Electrónico
          <input
            name="email"
            type="email"
            required
            autoComplete="email"
            className="rounded-[10px] border border-black/[0.08] bg-charcoal px-4 py-2.5 text-snow outline-none placeholder:text-ash focus:border-ring-blue"
          />
        </label>

        <label className="flex flex-col gap-1.5 text-[13px] font-[400] tracking-[-0.26px] text-chalk">
          Contraseña (Mín. 8 caracteres)
          <input
            name="password"
            type="password"
            required
            minLength={8}
            autoComplete="new-password"
            className="rounded-[10px] border border-black/[0.08] bg-charcoal px-4 py-2.5 text-snow outline-none placeholder:text-ash focus:border-ring-blue"
          />
        </label>

        <label className="flex items-start gap-2 text-[12px] leading-[1.5] text-fog">
          <input type="checkbox" required className="mt-1 accent-signal-blue" />
          He leído y acepto los Términos y Condiciones y la Política de
          Privacidad.
        </label>

        <button
          type="submit"
          disabled={loading}
          className="mt-2 rounded-[10px] bg-bone px-6 py-2.5 text-[14px] font-[400] tracking-[-0.32px] text-ink shadow-[var(--shadow-sm)] transition hover:bg-snow disabled:opacity-60"
        >
          {loading ? "Creando cuenta..." : "Registrarse"}
        </button>
      </form>
    </AuthShell>
  );
}
