// Копия конфигурации продуктов и аннуитетной формулы с фронтенда
// (src/shared/config/loanProducts.ts, src/entities/loan/model/credit.ts).
// Сервер считает независимо — клиентским числам не доверяем.

export const LOAN_PRODUCTS = {
  mortgage: { title: "Ипотека", annualRate: 0.18, minAmount: 300000, maxAmount: 30000000, minTermMonths: 12, maxTermMonths: 360 },
  auto: { title: "Автокредит", annualRate: 0.235, minAmount: 100000, maxAmount: 5000000, minTermMonths: 12, maxTermMonths: 84 },
  consumer: { title: "Потребительский", annualRate: 0.222, minAmount: 50000, maxAmount: 5000000, minTermMonths: 6, maxTermMonths: 84 },
  payday: { title: "Деньги до зарплаты", annualRate: 0.5, minAmount: 5000, maxAmount: 100000, minTermMonths: 1, maxTermMonths: 12 },
};

const round2 = (v) => Math.round(v * 100) / 100;

/** Аннуитетный платёж: P = S*i*(1+i)^n / ((1+i)^n - 1) */
export function calculateLoan(loanType, amount, termMonths) {
  const product = LOAN_PRODUCTS[loanType];
  const i = product.annualRate / 12;
  const factor = Math.pow(1 + i, termMonths);
  const monthlyPayment = round2((amount * i * factor) / (factor - 1));
  const totalRepaid = round2(monthlyPayment * termMonths);
  return {
    monthlyPayment,
    totalRepaid,
    totalInterest: round2(totalRepaid - amount),
  };
}
