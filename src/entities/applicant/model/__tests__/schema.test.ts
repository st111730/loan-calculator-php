import { loanApplicationSchema } from "@/entities/applicant";

const valid = {
  loanType: "mortgage" as const,
  amount: 5_000_000,
  termMonths: 120,
  fullName: "Иванов Иван Иванович",
  monthlyIncome: 200_000,
  hasCitizenship: true,
  consentGiven: true,
};

describe("loanApplicationSchema", () => {
  it("пропускает корректную заявку", () => {
    const result = loanApplicationSchema.safeParse(valid);
    expect(result.success).toBe(true);
  });

  it.each([
    ["пустое ФИО", { fullName: "" }],
    ["цифры в ФИО", { fullName: "Иван123" }],
    ["HTML-вставка в ФИО", { fullName: "<script>alert(1)</script>" }],
    ["один символ", { fullName: "И" }],
  ])("отклоняет %s", (_name, patch) => {
    const result = loanApplicationSchema.safeParse({ ...valid, ...patch });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues.some((i) => i.path[0] === "fullName")).toBe(true);
    }
  });

  it.each([
    ["отрицательная сумма", { amount: -100 }],
    ["нулевая сумма", { amount: 0 }],
    ["сумма выше максимума", { amount: 100_000_000 }],
    ["дробная сумма", { amount: 123.45 }],
  ])("отклоняет %s", (_name, patch) => {
    const result = loanApplicationSchema.safeParse({ ...valid, ...patch });
    expect(result.success).toBe(false);
  });

  it("отклоняет неизвестный тип кредита", () => {
    const result = loanApplicationSchema.safeParse({ ...valid, loanType: "crypto" });
    expect(result.success).toBe(false);
  });

  it("отклоняет срок вне диапазона продукта", () => {
    // для ипотеки минимум 12 месяцев
    const result = loanApplicationSchema.safeParse({ ...valid, termMonths: 1 });
    expect(result.success).toBe(false);
    if (!result.success) {
      expect(result.error.issues.some((i) => i.path[0] === "termMonths")).toBe(true);
    }
  });

  it.each([
    ["без гражданства", { hasCitizenship: false }],
    ["без согласия 152-ФЗ", { consentGiven: false }],
  ])("отклоняет заявку %s", (_name, patch) => {
    const result = loanApplicationSchema.safeParse({ ...valid, ...patch });
    expect(result.success).toBe(false);
  });

  it("отклоняет нулевой и отрицательный доход", () => {
    expect(loanApplicationSchema.safeParse({ ...valid, monthlyIncome: 0 }).success).toBe(false);
    expect(loanApplicationSchema.safeParse({ ...valid, monthlyIncome: -1 }).success).toBe(false);
  });

  it("тримит пробелы в ФИО", () => {
    const result = loanApplicationSchema.parse({ ...valid, fullName: "  Иванов Иван  " });
    expect(result.fullName).toBe("Иванов Иван");
  });
});
