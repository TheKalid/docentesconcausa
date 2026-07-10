"use client";

import Link from "next/link";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { signIn } from "next-auth/react";
import { AuthShell } from "@/components/AuthShell";

export default function LoginPage() {
  const router = useRouter();
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const onSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    const formData = new FormData(e.currentTarget);
    const res = await signIn("credentials", {
      email: formData.get("email"),
      password: formData.get("password"),
      redirect: false,
    });

    setLoading(false);

    if (res?.error) {
      setError("Correo o contraseña incorrectos.");
      return;
    }

    router.push("/");
    router.refresh();
  };

  return (
    <AuthShell
      title="Iniciar Sesión"
      footer={
        <>
          <Link href="/olvide-password" className="text-arc-blue hover:underline">
            ¿Olvidaste tu contraseña?
          </Link>

          <div className="mt-5 rounded-[12px] border border-signal-blue/25 bg-signal-blue/[0.06] p-4">
            <p className="mb-3 text-[13px] font-[500] tracking-[-0.26px] text-chalk">
              ¿Aún no eres socio?
            </p>
            <Link
              href="/registro"
              className="cta-attention motion-reduce:transform-none inline-flex w-full items-center justify-center gap-1.5 rounded-full bg-signal-blue px-5 py-2.5 text-[14px] font-[500] tracking-[-0.32px] text-white transition hover:bg-[#1d4ed8] animate-[cta-glow_1.8s_ease-in-out_infinite]"
            >
              Regístrate aquí
              <span aria-hidden="true" className="text-[16px] leading-none">
                →
              </span>
            </Link>
          </div>
        </>
      }
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        {error && (
          <p className="rounded-[8.77px] border border-coral/50 bg-coral/10 px-4 py-2 text-[13px] text-coral">
            {error}
          </p>
        )}

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
          Contraseña
          <div className="flex items-center rounded-[10px] border border-black/[0.08] bg-charcoal focus-within:border-ring-blue">
            <input
              name="password"
              type={showPassword ? "text" : "password"}
              required
              autoComplete="current-password"
              className="flex-1 rounded-[10px] bg-transparent px-4 py-2.5 text-snow outline-none"
            />
            <button
              type="button"
              onClick={() => setShowPassword((v) => !v)}
              className="px-3 text-[13px] text-fog hover:text-snow"
              aria-label="Mostrar u ocultar contraseña"
            >
              ver
            </button>
          </div>
        </label>

        <button
          type="submit"
          disabled={loading}
          className="mt-2 rounded-[10px] bg-bone px-6 py-2.5 text-[14px] font-[400] tracking-[-0.32px] text-ink shadow-[var(--shadow-sm)] transition hover:bg-snow disabled:opacity-60"
        >
          {loading ? "Entrando..." : "Entrar"}
        </button>
      </form>
    </AuthShell>
  );
}
