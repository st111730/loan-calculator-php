import type { LoanTypeId } from "@/shared/config/loanProducts";

export interface LoanParams {
  loanType: LoanTypeId;
  /** Сумма кредита, ₽ */
  amount: number;
  /** Срок, месяцев */
  termMonths: number;
  /** Годовая ставка, доли единицы */
  annualRate: number;
}

export interface LoanResult {
  /** Ежемесячный аннуитетный платёж, ₽ */
  monthlyPayment: number;
  /** Количество платежей */
  paymentsCount: number;
  /** Общая сумма выплат, ₽ */
  totalRepaid: number;
  /** Переплата (проценты), ₽ */
  totalInterest: number;
  /** Минимально необходимый подтверждённый доход, ₽ */
  requiredIncome: number;
}

export interface ApplicantForm {
  fullName: string;
  monthlyIncome: number;
  hasCitizenship: boolean;
  consentGiven: boolean;
}
