import {
  calculateAnnuityPayment,
  calculateLoan,
  isIncomeSufficient,
} from "@/entities/loan";
import { LOAN_PRODUCTS } from "@/shared/config/loanProducts";

describe("calculateAnnuityPayment", () => {
  it.each([
    // [сумма, тип кредита, срок, ожидаемый платёж]
    [1_000_000, "mortgage", 60, 25_393.43], // 18%
    [1_000_000, "auto", 60, 28_478.48], // 23.5%
    [1_000_000, "consumer", 60, 27_732.75], // 22.2%
    [50_000, "payday", 12, 5_379.26], // 50%
  ] as const)(
    "%s ₽, %s, %i мес -> %s ₽/мес",
    (amount, loanType, termMonths, expected) => {
      const payment = calculateAnnuityPayment(
        amount,
        LOAN_PRODUCTS[loanType].annualRate,
        termMonths,
      );
      expect(payment).toBeCloseTo(expected, 2);
    },
  );

  it("срок 1 месяц: платёж = сумма + проценты за месяц", () => {
    // 100 000 под 18% годовых на 1 месяц: 100000 * 0.18 / 12 = 1500 процентов
    expect(calculateAnnuityPayment(100_000, 0.18, 1)).toBe(101_500);
  });

  it("выбрасывает ошибку при неположительной сумме", () => {
    expect(() => calculateAnnuityPayment(0, 0.18, 12)).toThrow(RangeError);
    expect(() => calculateAnnuityPayment(-5, 0.18, 12)).toThrow(RangeError);
  });

  it("выбрасывает ошибку при неположительном сроке", () => {
    expect(() => calculateAnnuityPayment(100_000, 0.18, 0)).toThrow(RangeError);
  });
});

describe("calculateLoan", () => {
  it("ипотека 5 000 000 ₽, 18%, 120 мес: платёж 90 092.60 ₽", () => {
    const result = calculateLoan({
      loanType: "mortgage",
      amount: 5_000_000,
      termMonths: 120,
      annualRate: LOAN_PRODUCTS.mortgage.annualRate,
    });
    expect(result.monthlyPayment).toBe(90_092.6);
    expect(result.paymentsCount).toBe(120);
    expect(result.totalRepaid).toBeCloseTo(90_092.6 * 120, 0);
    expect(result.totalInterest).toBeCloseTo(90_092.6 * 120 - 5_000_000, 0);
    // Требуемая зарплата: платёж / 0.5 = 180 185 -> округление вверх до сотен
    expect(result.requiredIncome).toBe(180_200);
  });

  it("требуемый доход округляется вверх до сотен", () => {
    const result = calculateLoan({
      loanType: "payday",
      amount: 20_000,
      termMonths: 2,
      annualRate: LOAN_PRODUCTS.payday.annualRate,
    });
    // платёж 10 629.25 -> /0.5 = 21 258.50 -> 21 300
    expect(result.requiredIncome).toBe(21_300);
    expect(result.requiredIncome).toBeGreaterThanOrEqual(result.monthlyPayment / 0.5);
  });
});

describe("isIncomeSufficient", () => {
  it.each([
    [100_000, 50_000, true], // ровно 50% дохода — достаточно
    [99_999, 50_000, false], // чуть меньше — недостаточно
    [200_000, 50_000, true],
    [0, 50_000, false],
  ])("доход %i, платёж %i -> %s", (income, payment, expected) => {
    expect(isIncomeSufficient(income, payment)).toBe(expected);
  });
});
