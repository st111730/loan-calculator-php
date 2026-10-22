import { z } from "zod";
import { LOAN_PRODUCTS } from "./loan.js";

const fullNameRegex = /^[А-Яа-яЁёA-Za-z\s'-]{2,120}$/;

export const applicationSchema = z
  .object({
    loanType: z.enum(["mortgage", "auto", "consumer", "payday"]),
    amount: z.number().int().finite(),
    termMonths: z.number().int().finite().positive(),
    fullName: z
      .string()
      .trim()
      .min(2)
      .max(120)
      .regex(fullNameRegex),
    monthlyIncome: z.number().finite().positive(),
    hasCitizenship: z.literal(true),
    consentGiven: z.literal(true),
  })
  .superRefine((data, ctx) => {
    const product = LOAN_PRODUCTS[data.loanType];
    if (data.amount < product.minAmount || data.amount > product.maxAmount) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ["amount"], message: `Сумма для «${product.title}»: от ${product.minAmount} до ${product.maxAmount} ₽` });
    }
    if (data.termMonths < product.minTermMonths || data.termMonths > product.maxTermMonths) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ["termMonths"], message: `Срок для «${product.title}»: от ${product.minTermMonths} до ${product.maxTermMonths} мес` });
    }
  });
