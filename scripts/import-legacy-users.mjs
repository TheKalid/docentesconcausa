/**
 * Importa usuarios desde legacy/respaldo-db-2026-07-09.sql a PostgreSQL.
 * Uso: npm run db:import-legacy
 */
import { readFileSync } from "fs";
import path from "path";
import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();
const dumpPath = path.join(
  process.cwd(),
  "legacy",
  "respaldo-db-2026-07-09.sql"
);

function parseSqlRows(valuesSection) {
  const rows = [];
  let i = 0;

  while (i < valuesSection.length) {
    if (valuesSection[i] !== "(") {
      i++;
      continue;
    }

    i++;
    const row = [];

    while (i < valuesSection.length && valuesSection[i] !== ")") {
      while (i < valuesSection.length && /[\s,]/.test(valuesSection[i])) i++;

      if (valuesSection.substring(i, i + 4) === "NULL") {
        row.push(null);
        i += 4;
        continue;
      }

      if (valuesSection[i] === "'") {
        i++;
        let value = "";
        while (i < valuesSection.length) {
          if (valuesSection[i] === "'" && valuesSection[i + 1] === "'") {
            value += "'";
            i += 2;
            continue;
          }
          if (valuesSection[i] === "'") {
            i++;
            break;
          }
          value += valuesSection[i++];
        }
        row.push(value);
        continue;
      }

      let number = "";
      while (i < valuesSection.length && /[0-9.-]/.test(valuesSection[i])) {
        number += valuesSection[i++];
      }
      row.push(number === "" ? null : Number(number));
    }

    rows.push(row);
    i++;
  }

  return rows;
}

function toDate(value) {
  if (!value || value === "NULL") return null;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

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

async function main() {
  const sql = readFileSync(dumpPath, "utf8");
  const match = sql.match(/INSERT INTO `usuarios` VALUES (.+);/s);

  if (!match) {
    throw new Error("No se encontró INSERT INTO usuarios en el dump.");
  }

  const rows = parseSqlRows(match[1]);
  console.log(`Filas detectadas: ${rows.length}`);

  let imported = 0;
  let skipped = 0;

  for (const row of rows) {
    const data = mapUser(row);

    try {
      await prisma.user.upsert({
        where: { id: data.id },
        create: data,
        update: data,
      });
      imported++;
    } catch (error) {
      skipped++;
      console.warn(`Omitido id=${data.id} (${data.email}):`, error.message);
    }
  }

  const maxId = await prisma.user.aggregate({ _max: { id: true } });
  if (maxId._max.id) {
    await prisma.$executeRawUnsafe(
      `SELECT setval(pg_get_serial_sequence('usuarios', 'id'), ${maxId._max.id}, true)`
    );
  }

  console.log(`Importación terminada. OK: ${imported}, omitidos: ${skipped}`);
}

main()
  .catch((error) => {
    console.error(error);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
