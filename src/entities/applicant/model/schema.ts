import { z } from "zod";
import { LOAN_PRODUCTS, LOAN_TYPE_IDS } from "@/shared/config/loanProducts";

const fullNameRegex = /^[А-Яа-яЁёA-Za-z\s'-]{2,120}$/;

/**
 * Схема заявки на кредит. Все пользовательские данные проходят
 * через эту валидацию перед использованием в расчётах и выводе.
 */
export const loanApplicationSchema = z.object({
  loanType: z.enum(LOAN_TYPE_IDS),

  amount: z
    .number({ invalid_type_error: "Сумма должна быть числом" })
    .int("Сумма должна быть целым числом")
    .refine((v) => Number.isFinite(v), "Сумма обязательна"),

  termMonths: z
    .number({ invalid_type_error: "Срок должен быть числом" })
    .int("Срок должен быть целым числом месяцев")
    .refine((v) => Number.isFinite(v), "Срок обязателен"),

  fullName: z
    .string()
    .trim()
    .min(2, "Укажите ФИО (минимум 2 символа)")
    .max(120, "ФИО слишком длинное")
    .regex(fullNameRegex, "ФИО может содержать только буквы, пробелы и дефисы"),

  monthlyIncome: z
    .number({ invalid_type_error: "Доход должен быть числом" })
    .refine((v) => Number.isFinite(v), "Доход обязателен")
    .refine((v) => v > 0, "Доход должен быть положительным"),

  hasCitizenship: z.literal(true, {
    errorMap: () => ({ message: "Требуется гражданство РФ" }),
  }),

  consentGiven: z.literal(true, {
    errorMap: () => ({ message: "Требуется согласие на обработку персональных данных" }),
  }),
}).superRefine((data, ctx) => {
  const product = LOAN_PRODUCTS[data.loanType];
  if (data.amount < product.minAmount || data.amount > product.maxAmount) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ["amount"],
      message: `Сумма для «${product.title}»: от ${product.minAmount} до ${product.maxAmount} ₽`,
    });
  }
  if (
    data.termMonths < product.minTermMonths ||
    data.termMonths > product.maxTermMonths
  ) {
    ctx.addIssue({
      code: z.ZodIssueCode.custom,
      path: ["termMonths"],
      message: `Срок для «${product.title}»: от ${product.minTermMonths} до ${product.maxTermMonths} мес`,
    });
  }
});

export type LoanApplication = z.infer<typeof loanApplicationSchema>;

/** Валидация одного поля (для вывода ошибок под инпутами) */
export function validateField(
  field: keyof LoanApplication,
  value: unknown,
  draft: Partial<LoanApplication>,
): string | undefined {
  const result = loanApplicationSchema.safeParse({ ...draft, [field]: value });
  if (result.success) return undefined;
  return result.error.issues.find((issue) => issue.path[0] === field)?.message;
}
