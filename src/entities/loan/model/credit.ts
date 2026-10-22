import { MAX_PAYMENT_TO_INCOME_RATIO } from "@/shared/config/loanProducts";
import type { LoanParams, LoanResult } from "./types";

/**
 * Ежемесячный аннуитетный платёж:
 *   P = S * i * (1+i)^n / ((1+i)^n - 1),  i = годовая ставка / 12
 */
export function calculateAnnuityPayment(
  amount: number,
  annualRate: number,
  termMonths: number,
): number {
  const i = annualRate / 12;
  const n = termMonths;

  if (amount <= 0 || n <= 0) {
    throw new RangeError("Сумма и срок должны быть положительными");
  }
  if (annualRate < 0) {
    throw new RangeError("Ставка не может быть отрицательной");
  }
  if (i === 0) {
    // беспроцентный (не встречается, но формула вырождается)
    return round2(amount / n);
  }

  const factor = Math.pow(1 + i, n);
  const payment = (amount * i * factor) / (factor - 1);
  return round2(payment);
}

/** Полный расчёт кредита по параметрам */
export function calculateLoan(params: LoanParams): LoanResult {
  const monthlyPayment = calculateAnnuityPayment(
    params.amount,
    params.annualRate,
    params.termMonths,
  );

  const totalRepaid = round2(monthlyPayment * params.termMonths);

  return {
    monthlyPayment,
    paymentsCount: params.termMonths,
    totalRepaid,
    totalInterest: round2(totalRepaid - params.amount),
    requiredIncome: Math.ceil((monthlyPayment / MAX_PAYMENT_TO_INCOME_RATIO) / 100) * 100,
  };
}

/** Достаточен ли доход: платёж не должен превышать 50% дохода */
export function isIncomeSufficient(
  monthlyIncome: number,
  monthlyPayment: number,
): boolean {
  return monthlyIncome >= monthlyPayment / MAX_PAYMENT_TO_INCOME_RATIO;
}

function round2(value: number): number {
  return Math.round(value * 100) / 100;
}
