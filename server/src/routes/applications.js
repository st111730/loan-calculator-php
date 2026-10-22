import { Router } from "express";
import { pool } from "../db.js";
import { applicationSchema } from "../lib/validate.js";
import { calculateLoan } from "../lib/loan.js";

export const applicationsRouter = Router();

applicationsRouter.post("/applications", async (req, res, next) => {
  try {
    const parsed = applicationSchema.safeParse(req.body);
    if (!parsed.success) {
      return res.status(400).json({
        error: "validation_error",
        issues: parsed.error.issues.map((i) => ({ path: i.path.join("."), message: i.message })),
      });
    }
    const app = parsed.data;
    const calc = calculateLoan(app.loanType, app.amount, app.termMonths);

    const { rows } = await pool.query(
      `INSERT INTO applications
         (loan_type, amount, term_months, monthly_income, full_name,
          monthly_payment, total_repaid, total_interest)
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
       RETURNING *`,
      [app.loanType, app.amount, app.termMonths, app.monthlyIncome, app.fullName.trim(),
       calc.monthlyPayment, calc.totalRepaid, calc.totalInterest],
    );
    res.status(201).json(rows[0]);
  } catch (err) {
    next(err);
  }
});

export async function listApplications(status) {
  const { rows } = await pool.query(
    status
      ? "SELECT * FROM applications WHERE status = $1 ORDER BY created_at DESC"
      : "SELECT * FROM applications ORDER BY created_at DESC",
    status ? [status] : [],
  );
  return rows;
}
