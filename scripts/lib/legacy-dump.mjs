import { readFileSync } from "fs";
import path from "path";

export const DEFAULT_DUMP_PATH = path.join(
  process.cwd(),
  "legacy",
  "respaldo-db-2026-07-09.sql"
);

export function readLegacyDump(dumpPath = DEFAULT_DUMP_PATH) {
  return readFileSync(dumpPath, "utf8");
}

export function extractInsertValues(sql, tableName) {
  const match = sql.match(
    new RegExp(`INSERT INTO \`${tableName}\` VALUES ([\\s\\S]*?);\\r?\\n`)
  );

  if (!match) {
    throw new Error(`No se encontró INSERT INTO ${tableName} en el dump.`);
  }

  return parseSqlRows(match[1]);
}

export function parseSqlRows(valuesSection) {
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

      if (number === "") {
        throw new Error(
          `Carácter inesperado en fila SQL en posición ${i}: ${JSON.stringify(
            valuesSection.slice(i, i + 30)
          )}`
        );
      }

      row.push(Number(number));
    }

    rows.push(row);
    i++;
  }

  return rows;
}

export function toDate(value) {
  if (!value || value === "NULL") return null;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

export async function resetSequence(prisma, tableName, columnName = "id") {
  const result = await prisma.$queryRawUnsafe(
    `SELECT MAX("${columnName}")::int AS max_id FROM "${tableName}"`
  );
  const maxId = result[0]?.max_id;
  if (!maxId) return;

  await prisma.$executeRawUnsafe(
    `SELECT setval(pg_get_serial_sequence('${tableName}', '${columnName}'), ${maxId}, true)`
  );
}
