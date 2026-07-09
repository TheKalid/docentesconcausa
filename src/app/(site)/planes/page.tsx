import Image from "next/image";
import Link from "next/link";
import { redirect } from "next/navigation";
import { auth } from "@/lib/auth";
import { stripeCheckoutUrl, subscriptionPlans } from "@/lib/plans";

export const metadata = {
  title: "Planes de Suscripción | Planeando con Causa",
};

export default async function PlanesPage() {
  const session = await auth();

  if (!session?.user?.id) {
    redirect("/login?callbackUrl=/planes");
  }

  const userId = Number(session.user.id);

  return (
    <section className="mx-auto max-w-[1080px] px-5 py-24">
      <div className="mb-12 text-center">
        <Image
          src="/logo.png"
          alt="Logo de Planeando con Causa"
          width={150}
          height={150}
          className="mx-auto mb-6 h-auto w-[120px]"
        />
        <p className="mb-3 text-[10px] font-[500] uppercase tracking-[0.16em] text-steel">
          Suscripciones
        </p>
        <h1 className="mb-4 text-[42px] font-[400] leading-[1.05] tracking-[-0.88px] text-snow">
          Elige tu Plan de Apoyo
        </h1>
        <p className="mx-auto max-w-[560px] text-[16px] leading-[1.5] text-fog">
          Únete a nuestra comunidad y transforma tu práctica docente con
          herramientas y apoyo diseñados para ti.
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        {subscriptionPlans.map((plan) => (
          <article
            key={plan.id}
            className={`relative flex flex-col rounded-[12px] bg-graphite p-7 shadow-[var(--shadow-card)] ${
              plan.recommended ? "ring-2 ring-signal-blue/40" : ""
            } ${plan.disabled ? "opacity-70" : ""}`}
          >
            {plan.recommended && (
              <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-signal-blue px-3 py-1 text-[10px] font-[500] uppercase tracking-[0.12em] text-white">
                Más popular
              </span>
            )}

            <span className="mb-4 text-[40px]">{plan.icon}</span>
            <h2 className="mb-1 text-[22px] font-[400] tracking-[-0.44px] text-snow">
              {plan.name}
              {plan.disabled ? " — Próximamente" : ""}
            </h2>
            <p className="mb-5 text-[12px] font-[500] uppercase tracking-[0.12em] text-steel">
              {plan.level}
            </p>
            <p className="mb-6 text-[40px] font-[400] leading-none tracking-[-0.8px] text-snow">
              <span className="text-[20px]">$</span>
              {plan.price}
              <span className="text-[14px] text-fog">/mes</span>
            </p>

            <ul className="mb-8 grow space-y-3 text-[14px] leading-[1.43] text-fog">
              {plan.benefits.map((benefit) => (
                <li key={benefit} className="flex gap-2">
                  <span aria-hidden="true">✅</span>
                  <span>{benefit}</span>
                </li>
              ))}
            </ul>

            {plan.stripeBaseUrl && !plan.disabled ? (
              <a
                href={stripeCheckoutUrl(plan.stripeBaseUrl, userId)}
                className="inline-flex justify-center rounded-[10px] bg-bone px-4 py-3 text-[14px] font-[400] tracking-[-0.32px] text-ink shadow-[var(--shadow-sm)] transition hover:bg-snow"
              >
                Elegir este plan
              </a>
            ) : (
              <span className="inline-flex justify-center rounded-[10px] bg-black/[0.06] px-4 py-3 text-[14px] text-steel">
                Próximamente
              </span>
            )}
          </article>
        ))}
      </div>

      <div className="mt-12 flex flex-col items-center gap-4 text-center">
        <Link
          href="/"
          className="rounded-[10px] bg-bone px-5 py-2.5 text-[14px] text-ink shadow-[var(--shadow-sm)] transition hover:bg-snow"
        >
          Página principal
        </Link>
        <Link href="/servicio-cliente" className="text-[14px] text-fog hover:text-snow">
          ¿Necesitas algo?
        </Link>
      </div>
    </section>
  );
}
