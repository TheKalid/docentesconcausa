import { prisma } from "@/lib/prisma";
import type { UsageField } from "@/lib/tools/registry";

export async function getUsageRemaining(userId: number, field: UsageField) {
  if (field === "usosDiariosPadres") {
    return getPadresCreditsRemaining(userId);
  }

  const user = await prisma.user.findUnique({ where: { id: userId } });
  if (!user) return 0;
  return user[field] ?? 0;
}

export async function deductUsage(
  userId: number,
  field: UsageField,
  historialName: string,
  ip: string
) {
  if (field === "usosDiariosPadres") {
    return deductPadresUsage(userId, historialName, ip);
  }

  return prisma.$transaction(async (tx) => {
    const user = await tx.user.findUnique({ where: { id: userId } });
    const remaining = user?.[field] ?? 0;

    if (!user || remaining <= 0) {
      throw new Error("NO_CREDITS");
    }

    await tx.user.update({
      where: { id: userId },
      data: { [field]: { decrement: 1 } },
    });

    await tx.historialUso.create({
      data: {
        userId,
        herramienta: historialName,
        ipUsuario: ip,
      },
    });

    return remaining - 1;
  });
}

export async function refundUsage(
  userId: number,
  field: UsageField,
  historialName: string
) {
  await prisma.$transaction(async (tx) => {
    await tx.user.update({
      where: { id: userId },
      data: { [field]: { increment: 1 } },
    });

    const last = await tx.historialUso.findFirst({
      where: { userId, herramienta: historialName },
      orderBy: { fecha: "desc" },
      select: { id: true },
    });

    if (last) {
      await tx.historialUso.delete({ where: { id: last.id } });
    }
  });
}

async function getPadresCreditsRemaining(userId: number) {
  const user = await prisma.user.findUnique({
    where: { id: userId },
    select: { usosDiariosPadres: true, ultimoUsoPadres: true },
  });
  if (!user) return 0;

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  if (!user.ultimoUsoPadres || user.ultimoUsoPadres < today) {
    return 2;
  }

  return user.usosDiariosPadres;
}

async function deductPadresUsage(
  userId: number,
  historialName: string,
  ip: string
) {
  return prisma.$transaction(async (tx) => {
    const user = await tx.user.findUnique({
      where: { id: userId },
      select: { usosDiariosPadres: true, ultimoUsoPadres: true },
    });

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    let remaining =
      !user?.ultimoUsoPadres || user.ultimoUsoPadres < today
        ? 2
        : user.usosDiariosPadres;

    if (remaining <= 0) {
      throw new Error("NO_CREDITS");
    }

    await tx.user.update({
      where: { id: userId },
      data: {
        usosDiariosPadres: remaining - 1,
        ultimoUsoPadres: today,
      },
    });

    await tx.historialUso.create({
      data: { userId, herramienta: historialName, ipUsuario: ip },
    });

    return remaining - 1;
  });
}
