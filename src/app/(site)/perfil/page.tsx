import Link from "next/link";
import { redirect } from "next/navigation";
import { auth } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { getUsageRemaining } from "@/lib/tool-usage";
import {
  formatProfileDate,
  getPlanLabel,
  getPlanStatus,
} from "@/lib/profile";
import { normalizePlanActivo } from "@/lib/access";

export const metadata = {
  title: "Mi Perfil | Docentes con causa",
};

export default async function PerfilPage() {
  const session = await auth();

  if (!session?.user?.id) {
    redirect("/login?callbackUrl=/perfil");
  }

  const userId = Number(session.user.id);
  const user = await prisma.user.findUnique({
    where: { id: userId },
    select: {
      name: true,
      email: true,
      phone: true,
      planActivo: true,
      fechaProximoPago: true,
      estado: true,
      referidoPor: true,
      usosPlanBasico: true,
      usosPlanIntermedio: true,
      usosFisica: true,
      usosEvaluacionDiag: true,
      usosExamenes: true,
      usosProtocolos: true,
      usosBitacora: true,
    },
  });

  if (!user) {
    redirect("/login?callbackUrl=/perfil");
  }

  const padresCredits = await getUsageRemaining(userId, "usosDiariosPadres");
  const planLevel = normalizePlanActivo(user.planActivo);
  const planStatus = getPlanStatus(user.planActivo);

  const usageItems = [
    { label: "Planeaciones básicas", value: user.usosPlanBasico },
    { label: "Planeaciones avanzadas", value: user.usosPlanIntermedio },
    { label: "Educación física", value: user.usosFisica },
    { label: "Evaluaciones diagnósticas", value: user.usosEvaluacionDiag },
    { label: "Exámenes", value: user.usosExamenes },
    { label: "Protocolos", value: user.usosProtocolos },
    { label: "Bitácoras", value: user.usosBitacora },
    { label: "Herramientas para padres (hoy)", value: padresCredits },
  ];

  return (
    <section className="mx-auto max-w-[800px] px-5 py-16 md:py-24">
      <div className="mb-10">
        <p className="mb-3 text-[10px] font-[500] uppercase tracking-[0.16em] text-steel">
          Cuenta
        </p>
        <h1 className="text-[42px] font-[400] leading-[1.05] tracking-[-0.88px] text-snow">
          Mi perfil
        </h1>
        <p className="mt-3 text-[16px] leading-[1.5] text-fog">
          Consulta tus datos, tu plan activo y los créditos disponibles en la
          plataforma.
        </p>
      </div>

      <div className="space-y-6">
        <section className="rounded-[12px] bg-graphite p-6 shadow-[var(--shadow-card)] md:p-8">
          <h2 className="mb-5 text-[22px] font-[400] tracking-[-0.44px] text-snow">
            Datos personales
          </h2>
          <dl className="space-y-4">
            <ProfileRow label="Nombre" value={user.name} />
            <ProfileRow label="Correo electrónico" value={user.email} />
            <ProfileRow
              label="Teléfono"
              value={user.phone?.trim() || "No proporcionado"}
            />
          </dl>
        </section>

        <section className="rounded-[12px] bg-graphite p-6 shadow-[var(--shadow-card)] md:p-8">
          <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 className="text-[22px] font-[400] tracking-[-0.44px] text-snow">
              Suscripción
            </h2>
            <Link
              href="/planes"
              className="inline-flex justify-center rounded-[10px] bg-bone px-4 py-2 text-[14px] font-[400] tracking-[-0.32px] text-ink shadow-[var(--shadow-sm)] transition hover:bg-snow"
            >
              {planLevel > 0 ? "Cambiar plan" : "Ver planes"}
            </Link>
          </div>

          <dl className="space-y-4">
            <ProfileRow label="Plan actual" value={getPlanLabel(user.planActivo)} />
            <div className="flex items-center justify-between gap-4 border-b border-black/[0.06] pb-4 last:border-b-0 last:pb-0">
              <dt className="text-[14px] text-steel">Estado</dt>
              <dd>
                <span
                  className={`rounded-full px-3 py-1 text-[12px] font-[500] ${
                    planStatus === "Activa"
                      ? "bg-mint/15 text-mint"
                      : "bg-coral/10 text-coral"
                  }`}
                >
                  {planStatus}
                </span>
              </dd>
            </div>
            <ProfileRow
              label="Plan activo hasta"
              value={formatProfileDate(user.fechaProximoPago)}
            />
            <ProfileRow
              label="Estado de cuenta"
              value={user.estado || "pendiente"}
            />
            {user.referidoPor ? (
              <ProfileRow label="Código de asesor" value={user.referidoPor} />
            ) : null}
          </dl>
        </section>

        <section className="rounded-[12px] bg-graphite p-6 shadow-[var(--shadow-card)] md:p-8">
          <h2 className="mb-5 text-[22px] font-[400] tracking-[-0.44px] text-snow">
            Créditos disponibles
          </h2>
          <dl className="grid gap-4 sm:grid-cols-2">
            {usageItems.map((item) => (
              <div
                key={item.label}
                className="rounded-[10px] border border-black/[0.06] bg-charcoal px-4 py-3"
              >
                <dt className="text-[12px] text-steel">{item.label}</dt>
                <dd className="mt-1 text-[24px] font-[400] tracking-[-0.48px] text-snow">
                  {item.value}
                </dd>
              </div>
            ))}
          </dl>
        </section>

        <div className="flex flex-col gap-3 sm:flex-row">
          <Link
            href="/"
            className="inline-flex justify-center rounded-[10px] bg-black/[0.05] px-4 py-3 text-[14px] text-snow transition hover:bg-black/[0.08]"
          >
            Volver al inicio
          </Link>
          <Link
            href="/servicio-cliente"
            className="inline-flex justify-center rounded-[10px] border border-black/[0.08] px-4 py-3 text-[14px] text-fog transition hover:text-snow"
          >
            Contactar servicio al cliente
          </Link>
        </div>
      </div>
    </section>
  );
}

function ProfileRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-1 border-b border-black/[0.06] pb-4 last:border-b-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
      <dt className="text-[14px] text-steel">{label}</dt>
      <dd className="text-[15px] tracking-[-0.32px] text-snow">{value}</dd>
    </div>
  );
}
