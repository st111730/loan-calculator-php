// Глобальный стейт калькулятора (перенос useState черновика из виджета).
// Plan.md, Часть 2: zustand вместо useState. Persist не используется —
// персональные данные нельзя хранить в localStorage (152-ФЗ).
import { create } from "zustand";
import type { LoanTypeId } from "@/shared/config/loanProducts";

export interface CalculatorDraft {
  loanType: LoanTypeId;
  amount: number;
  termMonths: number;
  fullName: string;
  monthlyIncome: number;
  hasCitizenship: boolean;
  consentGiven: boolean;
}

export type CalculatorTouched = Record<string, boolean>;

export const initialDraft: CalculatorDraft = {
  loanType: "mortgage",
  amount: 5_000_000,
  termMonths: 120,
  fullName: "",
  monthlyIncome: 0,
  hasCitizenship: false,
  consentGiven: false,
};

/** Статус отправки заявки (перенесён из useLoanCalculatorPush, Plan.md 6.4) */
export type PushStatus = "idle" | "submitting" | "success" | "error";

interface CalculatorStoreState {
  draft: CalculatorDraft;
  touched: CalculatorTouched;
  pushStatus: PushStatus;
  pushError: string | undefined;
  setField: <K extends keyof CalculatorDraft>(field: K, value: CalculatorDraft[K]) => void;
  setTouched: (field: keyof CalculatorDraft) => void;
  startedPush: () => void;
  pushSucceeded: () => void;
  pushFailed: (error: string) => void;
  resetPush: () => void;
}

export const useCalculatorStore = create<CalculatorStoreState>()((set) => ({
  draft: initialDraft,
  touched: {},
  pushStatus: "idle",
  pushError: undefined,
  setField: (field, value) =>
    set((state) => ({ draft: { ...state.draft, [field]: value } })),
  setTouched: (field) =>
    set((state) => ({ touched: { ...state.touched, [field]: true } })),
  startedPush: () => set({ pushStatus: "submitting", pushError: undefined }),
  pushSucceeded: () => set({ pushStatus: "success" }),
  pushFailed: (error) => set({ pushStatus: "error", pushError: error }),
  resetPush: () => set({ pushStatus: "idle", pushError: undefined }),
}));
