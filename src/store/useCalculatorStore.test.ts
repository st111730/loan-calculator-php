// TDD, «красная» фаза: внедрение Zustand (Plan.md, Часть 2).
// Стора ещё нет — suite обязан падать на импорте (Red).
// Начальные значения повторяют INITIAL_DRAFT из виджета LoanCalculator.
import { useCalculatorStore } from "./useCalculatorStore";

const get = () => useCalculatorStore.getState();

describe("useCalculatorStore: начальное состояние", () => {
  it("содержит черновик заявки с дефолтными значениями", () => {
    const { draft, touched } = get();

    expect(draft.loanType).toBe("mortgage");
    expect(draft.amount).toBe(5_000_000);
    expect(draft.termMonths).toBe(120);
    expect(draft.fullName).toBe("");
    expect(draft.monthlyIncome).toBe(0);
    expect(draft.hasCitizenship).toBe(false);
    expect(draft.consentGiven).toBe(false);
    expect(touched).toEqual({});
  });
});

describe("useCalculatorStore: обновление суммы кредита", () => {
  it('setField("amount", …) меняет только сумму', () => {
    get().setField("amount", 10_000_000);
    const { draft } = get();

    expect(draft.amount).toBe(10_000_000);
    // остальные поля черновика не затронуты
    expect(draft.loanType).toBe("mortgage");
    expect(draft.termMonths).toBe(120);
  });
});
