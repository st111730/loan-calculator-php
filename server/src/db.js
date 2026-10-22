import pg from "pg";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export const pool = new pg.Pool({
  host: process.env.PGHOST || "localhost",
  port: Number(process.env.PGPORT) || 5432,
  database: process.env.PGDATABASE || "calc",
  user: process.env.PGUSER || "calc",
  password: process.env.PGPASSWORD || "calc",
});

/** Применяет миграцию при старте (идемпотентно). */
export async function migrate() {
  const sql = readFileSync(path.join(__dirname, "migrate.sql"), "utf8");
  await pool.query(sql);
}
