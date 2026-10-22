import { Router } from "express";
import { timingSafeEqual } from "node:crypto";
import { pool } from "../db.js";
import { listApplications } from "./applications.js";
import { LOAN_PRODUCTS } from "../lib/loan.js";

export const adminRouter = Router();

const STATUSES = ["new", "in_review", "approved", "rejected"];
const STATUS_LABELS = {
  new: "Новая",
  in_review: "На рассмотрении",
  approved: "Одобрена",
  rejected: "Отклонена",
};

adminRouter.use((req, res, next) => {
  const header = req.headers.authorization || "";
  const expected = "Basic " + Buffer.from(
    `${process.env.ADMIN_USER || "admin"}:${process.env.ADMIN_PASSWORD || "admin"}`
  ).toString("base64");
  const ok = header.length === expected.length &&
    timingSafeEqual(Buffer.from(header), Buffer.from(expected));
  if (!ok) {
    res.set("WWW-Authenticate", 'Basic realm="admin"');
    return res.status(401).send("Требуется авторизация");
  }
  next();
});

adminRouter.get("/", async (req, res, next) => {
  try {
    const status = STATUSES.includes(req.query.status) ? req.query.status : null;
    const rows = await listApplications(status);
    res.type("html").send(renderPage(rows, status));
  } catch (err) {
    next(err);
  }
});

adminRouter.post("/:id/status", async (req, res, next) => {
  try {
    const id = Number(req.params.id);
    const status = req.body?.status;
    if (!Number.isInteger(id) || !STATUSES.includes(status)) {
      return res.status(400).send("Некорректные данные");
    }
    await pool.query("UPDATE applications SET status = $1 WHERE id = $2", [status, id]);
    res.redirect("/admin");
  } catch (err) {
    next(err);
  }
});

const esc = (s) => String(s).replace(/[&<>"']/g, (c) =>
  ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

const fmtMoney = (n) => Number(n).toLocaleString("ru-RU", { maximumFractionDigits: 0 }) + " ₽";

function renderPage(rows, currentStatus) {
  const filterLinks = ['<a href="/admin">Все</a>']
    .concat(STATUSES.map((s) =>
      `<a href="/admin?status=${s}"${s === currentStatus ? ' style="font-weight:bold"' : ""}>${STATUS_LABELS[s]}</a>`))
    .join(" · ");

  const trs = rows.map((r) => `
    <tr>
      <td>${esc(new Date(r.created_at).toLocaleString("ru-RU"))}</td>
      <td>${esc(LOAN_PRODUCTS[r.loan_type]?.title || r.loan_type)}</td>
      <td>${esc(r.full_name)}</td>
      <td class="num">${fmtMoney(r.amount)}</td>
      <td class="num">${esc(r.term_months)}</td>
      <td class="num">${fmtMoney(r.monthly_payment)}</td>
      <td class="num">${fmtMoney(r.monthly_income)}</td>
      <td>
        <form method="post" action="/admin/${r.id}/status">
          <select name="status">
            ${STATUSES.map((s) =>
              `<option value="${s}"${s === r.status ? " selected" : ""}>${STATUS_LABELS[s]}</option>`).join("")}
          </select>
          <button type="submit">OK</button>
        </form>
      </td>
    </tr>`).join("");

  return `<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Заявки — админка</title>
<style>
  body { font-family: system-ui, sans-serif; margin: 24px; color: #1e293b; }
  table { border-collapse: collapse; width: 100%; font-size: 14px; }
  th, td { border: 1px solid #e2e8f0; padding: 6px 10px; text-align: left; }
  th { background: #f8fafc; }
  .num { text-align: right; white-space: nowrap; }
  .filters { margin-bottom: 12px; }
  form { display: inline-flex; gap: 4px; }
  select, button { font: inherit; }
</style>
</head>
<body>
<h1>Заявки на кредиты (${rows.length})</h1>
<p class="filters">${filterLinks}</p>
<table>
  <thead><tr>
    <th>Создана</th><th>Продукт</th><th>ФИО</th><th>Сумма</th>
    <th>Срок, мес</th><th>Платёж</th><th>Доход</th><th>Статус</th>
  </tr></thead>
  <tbody>${trs || '<tr><td colspan="8">Заявок пока нет</td></tr>'}</tbody>
</table>
</body>
</html>`;
}
