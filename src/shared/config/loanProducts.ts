export const LOAN_TYPE_IDS = ["mortgage", "auto", "consumer", "payday"] as const;

export type LoanTypeId = (typeof LOAN_TYPE_IDS)[number];

export interface LoanProduct {
  id: LoanTypeId;
  title: string;
  /** Годовая процентная ставка, доли единицы (0.18 = 18%) */
  annualRate: number;
  minAmount: number;
  maxAmount: number;
  /** Минимальный срок, месяцев */
  minTermMonths: number;
  /** Максимальный срок, месяцев */
  maxTermMonths: number;
}

export const LOAN_PRODUCTS: Record<LoanTypeId, LoanProduct> = {
  mortgage: {
    id: "mortgage",
    title: "Ипотека",
    annualRate: 0.18,
    minAmount: 300_000,
    maxAmount: 30_000_000,
    minTermMonths: 12,
    maxTermMonths: 360,
  },
  auto: {
    id: "auto",
    title: "Автокредит",
    annualRate: 0.235,
    minAmount: 100_000,
    maxAmount: 5_000_000,
    minTermMonths: 12,
    maxTermMonths: 84,
  },
  consumer: {
    id: "consumer",
    title: "Потребительский",
    annualRate: 0.222,
    minAmount: 50_000,
    maxAmount: 5_000_000,
    minTermMonths: 6,
    maxTermMonths: 84,
  },
  payday: {
    id: "payday",
    title: "Деньги до зарплаты",
    annualRate: 0.5,
    minAmount: 5_000,
    maxAmount: 100_000,
    minTermMonths: 1,
    maxTermMonths: 12,
  },
};

/** Доля дохода, которую не должен превышать ежемесячный платёж */
export const MAX_PAYMENT_TO_INCOME_RATIO = 0.5;
