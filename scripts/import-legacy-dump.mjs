/**
 * Importa el respaldo MySQL legacy a PostgreSQL (Prisma).
 *
 * Uso:
 *   npm run db:migrate
 *   npm run db:import-legacy
 */
import { PrismaClient } from "@prisma/client";
import {
  DEFAULT_DUMP_PATH,
  extractInsertValues,
  readLegacyDump,
  resetSequence,
  toDate,
} from "./lib/legacy-dump.mjs";

const prisma = new PrismaClient();

function mapUser(row) {
  const [
    id,
    nombre,
    email,
    password,
    estado,
    tokenVerificacion,
    telefono,
    planActivo,
    planId,
    fechaProximoPago,
    stripeCustomerId,
    stripeSubscriptionId,
    resetToken,
    resetTokenExpiresAt,
    usosPlanBasico,
    usosPlanIntermedio,
    usosFisica,
    usosEvaluacionDiag,
    usosEvaluacion,
    usosProtocolos,
    usosBitacora,
    usosContexto,
    fechaInicioCiclo,
    psicologoSolicitado,
    usosExamenes,
    status,
    usosDiariosPadres,
    ultimoUsoPadres,
    referidoPor,
    contextoGuardado,
  ] = row;

  return {
    id,
    name: nombre,
    email: String(email).toLowerCase().trim(),
    password,
    estado: estado ?? "pendiente",
    phone: telefono,
    planActivo: planActivo ?? 0,
    planId,
    fechaProximoPago: toDate(fechaProximoPago),
    stripeCustomerId,
    stripeSubscriptionId,
    tokenVerificacion,
    resetToken,
    resetTokenExpiresAt: toDate(resetTokenExpiresAt),
    usosPlanBasico: usosPlanBasico ?? 0,
    usosPlanIntermedio: usosPlanIntermedio ?? 0,
    usosFisica: usosFisica ?? 0,
    usosEvaluacionDiag: usosEvaluacionDiag ?? 0,
    usosEvaluacion: usosEvaluacion ?? 0,
    usosProtocolos: usosProtocolos ?? 0,
    usosBitacora: usosBitacora ?? 0,
    usosContexto: usosContexto ?? 0,
    fechaInicioCiclo: toDate(fechaInicioCiclo),
    psicologoSolicitado: Boolean(psicologoSolicitado),
    usosExamenes: usosExamenes ?? 5,
    status: status ?? "activo",
    usosDiariosPadres: usosDiariosPadres ?? 2,
    ultimoUsoPadres: toDate(ultimoUsoPadres),
    referidoPor,
    contextoGuardado,
  };
}

function mapGrupo(row) {
  const [
    id,
    userId,
    gradoPlaneacion,
    duracionPlaneacion,
    numeroTotalEstudiantes,
    auditivos,
    visuales,
    kinestesicos,
    refuerzoLectoGeneral,
    estrategiasLectoGeneral,
    refuerzoCalculo,
    estrategiasCalculo,
    refuerzoOperaciones,
    estrategiasOperaciones,
    tieneNoLectoescritura,
    numeroAlumnosLecto,
    refuerzoLectoAdecuacion,
    estrategiasLectoAdecuacion,
  ] = row;

  return {
    id,
    userId,
    gradoPlaneacion,
    duracionPlaneacion,
    numeroTotalEstudiantes,
    auditivos,
    visuales,
    kinestesicos,
    refuerzoLectoGeneral,
    estrategiasLectoGeneral,
    refuerzoCalculo,
    estrategiasCalculo,
    refuerzoOperaciones,
    estrategiasOperaciones,
    tieneNoLectoescritura,
    numeroAlumnosLecto,
    refuerzoLectoAdecuacion,
    estrategiasLectoAdecuacion,
  };
}

function mapHistorial(row) {
  const [id, userId, herramienta, ipUsuario, fecha] = row;

  return {
    id,
    userId,
    herramienta,
    ipUsuario,
    fecha: toDate(fecha) ?? new Date(),
  };
}

function mapRecurso(row) {
  const [
    id,
    titulo,
    descripcion,
    categoria,
    nombreArchivo,
    rutaArchivo,
    fechaSubida,
  ] = row;

  return {
    id,
    titulo,
    descripcion,
    categoria,
    archivo: nombreArchivo,
    ruta: rutaArchivo,
    createdAt: toDate(fechaSubida) ?? new Date(),
  };
}

function mapConvenio(row) {
  const [id, userId, activo, fechaAlta] = row;

  return {
    id,
    userId,
    activo: Boolean(activo),
    fechaAlta: toDate(fechaAlta) ?? new Date(),
  };
}

async function importRows({
  label,
  rows,
  mapRow,
  upsert,
}) {
  let imported = 0;
  let skipped = 0;

  for (const row of rows) {
    const data = mapRow(row);

    try {
      await upsert(data);
      imported++;
    } catch (error) {
      skipped++;
      const identifier =
        data.email ?? data.id ?? data.userId ?? JSON.stringify(row.slice(0, 2));
      console.warn(`Omitido ${label} (${identifier}):`, error.message);
    }
  }

  return { imported, skipped };
}

async function main() {
  const sql = readLegacyDump();
  const summary = {};

  console.log(`Leyendo dump: ${DEFAULT_DUMP_PATH}`);

  const usuarios = extractInsertValues(sql, "usuarios");
  summary.usuarios = await importRows({
    label: "usuario",
    rows: usuarios,
    mapRow: mapUser,
    upsert: (data) =>
      prisma.user.upsert({
        where: { id: data.id },
        create: data,
        update: data,
      }),
  });
  await resetSequence(prisma, "usuarios");

  const grupos = extractInsertValues(sql, "grupos");
  summary.grupos = await importRows({
    label: "grupo",
    rows: grupos,
    mapRow: mapGrupo,
    upsert: (data) =>
      prisma.grupo.upsert({
        where: { id: data.id },
        create: data,
        update: data,
      }),
  });
  await resetSequence(prisma, "grupos");

  const recursos = extractInsertValues(sql, "recursos");
  summary.recursos = await importRows({
    label: "recurso",
    rows: recursos,
    mapRow: mapRecurso,
    upsert: (data) =>
      prisma.recurso.upsert({
        where: { id: data.id },
        create: data,
        update: data,
      }),
  });
  await resetSequence(prisma, "recursos");

  const historial = extractInsertValues(sql, "historial_uso");
  summary.historial_uso = await importRows({
    label: "historial",
    rows: historial,
    mapRow: mapHistorial,
    upsert: (data) =>
      prisma.historialUso.upsert({
        where: { id: data.id },
        create: data,
        update: data,
      }),
  });
  await resetSequence(prisma, "historial_uso");

  const convenios = extractInsertValues(sql, "convenio_cetl");
  summary.convenio_cetl = await importRows({
    label: "convenio",
    rows: convenios,
    mapRow: mapConvenio,
    upsert: (data) =>
      prisma.convenioCetl.upsert({
        where: { id: data.id },
        create: data,
        update: data,
      }),
  });
  await resetSequence(prisma, "convenio_cetl");

  console.log("Importación terminada:");
  for (const [table, stats] of Object.entries(summary)) {
    console.log(`- ${table}: ${stats.imported} OK, ${stats.skipped} omitidos`);
  }
}

main()
  .catch((error) => {
    console.error(error);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
