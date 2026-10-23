import { useMemo } from "react";
import { useCalculatorStore } from "@/store/useCalculatorStore";
import { LoanTypeChips } from "@/features/loan-type-select/ui/LoanTypeChips";
import { AmountSlider } from "@/features/loan-amount/ui/AmountSlider";
import { TermSlider } from "@/features/loan-term/ui/TermSlider";
import { SubmitButton } from "@/features/submit-application/ui/SubmitButton";
import { useLoanCalculatorPush } from "@/features/loan-application/model/useLoanCalculatorPush";
import { loanApplicationSchema, type LoanApplication } from "@/entities/applicant";
import { calculateLoan, isIncomeSufficient } from "@/entities/loan";
import { LOAN_PRODUCTS } from "@/shared/config/loanProducts";
import { formatCurrency, formatNumber, parseFormattedNumber } from "@/shared/lib/format";

export function LoanCalculator() {
  const draft = useCalculatorStore((s) => s.draft);
  const touched = useCalculatorStore((s) => s.touched);
  const setField = useCalculatorStore((s) => s.setField);
  const setTouched = useCalculatorStore((s) => s.setTouched);

  const product = LOAN_PRODUCTS[draft.loanType];

  const result = useMemo(
    () =>
      calculateLoan({
        loanType: draft.loanType,
        amount: clamp(draft.amount, product.minAmount, product.maxAmount),
        termMonths: clamp(draft.termMonths, product.minTermMonths, product.maxTermMonths),
        annualRate: product.annualRate,
      }),
    [draft.loanType, draft.amount, draft.termMonths, product],
  );

  const validation = loanApplicationSchema.safeParse(draft);
  const isValid = validation.success;

  const errors = useMemo(() => {
    const map: Partial<Record<keyof LoanApplication, string>> = {};
    if (validation.success) return map;
    for (const issue of validation.error.issues) {
      const key = issue.path[0] as keyof LoanApplication;
      if (!map[key]) map[key] = issue.message;
    }
    return map;
  }, [validation]);

  const showError = (field: keyof LoanApplication) =>
    touched[field] ? errors[field] : undefined;

  const incomeSufficient = isIncomeSufficient(draft.monthlyIncome, result.monthlyPayment);

  const { push } = useLoanCalculatorPush();
  const pushStatus = useCalculatorStore((s) => s.pushStatus);
  const pushError = useCalculatorStore((s) => s.pushError);
  const resetPush = useCalculatorStore((s) => s.resetPush);

  const handleSubmit = () => {
    if (!isValid || pushStatus === "submitting") return;
    const app = validation.success ? validation.data : null;
    if (!app) return;
    void push(app);
  };

  return (
    <section className="grid gap-6 lg:grid-cols-[1fr_360px]">
      <form
        className="space-y-6 rounded-2xl bg-[color:var(--color-surface)] p-6 shadow-lg shadow-[color:var(--shadow-color)] ring-1 ring-[color:var(--color-hairline)]"
        onSubmit={(e) => e.preventDefault()}
        noValidate
      >
        <div>
          <label
            htmlFor="fullName"
            className="mb-1 block text-sm font-medium uppercase tracking-[0.08em] text-[color:var(--color-muted)]"
          >
            ФИО
          </label>
          <input
            id="fullName"
            type="text"
            autoComplete="name"
            value={draft.fullName}
            onBlur={() => setTouched("fullName")}
            onChange={(e) => setField("fullName", e.target.value)}
            placeholder="Иванов Иван Иванович"
            className="w-full rounded-xl border border-[color:var(--color-hairline)] bg-[color:var(--color-surface)] px-3 py-2 text-[color:var(--color-text)] focus:border-[color:var(--color-text)] focus:outline-none"
          />
          {showError("fullName") && (
            <p className="mt-1 text-xs text-[color:var(--color-error)]">{errors.fullName}</p>
          )}
        </div>

        <AmountSlider
          value={draft.amount}
          min={product.minAmount}
          max={product.maxAmount}
          error={showError("amount")}
          onChange={(amount) => {
            setTouched("amount");
            setField("amount", amount);
          }}
        />

        <TermSlider
          value={draft.termMonths}
          min={product.minTermMonths}
          max={product.maxTermMonths}
          error={showError("termMonths")}
          onChange={(termMonths) => {
            setTouched("termMonths");
            setField("termMonths", termMonths);
          }}
        />

        <div>
          <h2 className="mb-2 text-sm font-medium text-[color:var(--color-text)]">Тип кредита</h2>
          <LoanTypeChips
            value={draft.loanType}
            onChange={(loanType) => {
              const p = LOAN_PRODUCTS[loanType];
              setField("loanType", loanType);
              setField("amount", clamp(draft.amount, p.minAmount, p.maxAmount));
              setField("termMonths", clamp(draft.termMonths, p.minTermMonths, p.maxTermMonths));
            }}
          />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label
              htmlFor="income"
              className="mb-1 block text-sm font-medium uppercase tracking-[0.08em] text-[color:var(--color-muted)]"
            >
              Ежемесячный доход, ₽
            </label>
            <input
              id="income"
              type="text"
              inputMode="numeric"
              autoComplete="off"
              value={draft.monthlyIncome === 0 ? "" : formatNumber(draft.monthlyIncome)}
              onBlur={() => setTouched("monthlyIncome")}
              onChange={(e) => {
                const parsed = parseFormattedNumber(e.target.value);
                setField("monthlyIncome", Number.isNaN(parsed) ? 0 : parsed);
              }}
              placeholder="100 000"
              className="w-full rounded-xl border border-[color:var(--color-hairline)] bg-[color:var(--color-surface)] px-3 py-2 text-right text-[color:var(--color-text)] focus:border-[color:var(--color-text)] focus:outline-none"
            />
            {showError("monthlyIncome") && (
              <p className="mt-1 text-xs text-[color:var(--color-error)]">{errors.monthlyIncome}</p>
            )}
            {touched.monthlyIncome && draft.monthlyIncome > 0 && !incomeSufficient && (
              <p className="mt-1 text-xs text-[color:var(--color-warning)]">
                Доход недостаточен: нужно не менее {formatCurrency(result.requiredIncome)}
              </p>
            )}
          </div>
        </div>

        <div className="space-y-3">
          <label className="flex items-start gap-3 text-sm text-[color:var(--color-text)]">
            <input
              type="checkbox"
              checked={draft.hasCitizenship}
              onChange={(e) => setField("hasCitizenship", e.target.checked)}
              className="mt-0.5 h-4 w-4 rounded border-[color:var(--color-hairline)] accent-[color:var(--color-text)]"
            />
            <span>
              Я являюсь гражданином РФ
              {showError("hasCitizenship") && (
                <span className="block text-xs text-[color:var(--color-error)]">
                  {errors.hasCitizenship}
                </span>
              )}
            </span>
          </label>

          <label className="flex items-start gap-3 text-sm text-[color:var(--color-text)]">
            <input
              type="checkbox"
              checked={draft.consentGiven}
              onChange={(e) => setField("consentGiven", e.target.checked)}
              className="mt-0.5 h-4 w-4 rounded border-[color:var(--color-hairline)] accent-[color:var(--color-text)]"
            />
            <span>
              Я даю согласие на обработку персональных данных в соответствии с{" "}
              <a
                href="/privacy-policy.pdf"
                className="underline hover:opacity-80"
                target="_blank"
                rel="noreferrer noopener"
              >
                политикой конфиденциальности
              </a>{" "}
              (152-ФЗ)
              {showError("consentGiven") && (
                <span className="block text-xs text-[color:var(--color-error)]">
                  {errors.consentGiven}
                </span>
              )}
            </span>
          </label>
        </div>
      </form>

      <aside className="h-fit space-y-4 rounded-2xl bg-[color:var(--color-surface)] p-6 text-[color:var(--color-text)] shadow-lg shadow-[color:var(--shadow-color)] ring-1 ring-[color:var(--color-hairline)]">
        <h2 className="text-sm font-medium uppercase tracking-[0.08em] text-[color:var(--color-muted)]">
          Расчёт по продукту «{product.title}»
        </h2>

        <div>
          <p className="text-sm text-[color:var(--color-muted)]">Ежемесячный платёж</p>
          <p
            className="tabular-nums text-3xl font-bold"
            data-testid="monthly-payment"
          >
            {formatCurrency(result.monthlyPayment)}
          </p>
        </div>

        <dl className="tabular-nums space-y-2 text-sm">
          <div className="flex justify-between">
            <dt className="text-[color:var(--color-muted)]">Количество платежей</dt>
            <dd className="font-semibold">{formatNumber(result.paymentsCount)}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-[color:var(--color-muted)]">Ставка</dt>
            <dd className="font-semibold">
              {(product.annualRate * 100).toLocaleString("ru-RU")}%
            </dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-[color:var(--color-muted)]">Требуемая зарплата</dt>
            <dd className="font-semibold" data-testid="required-income">
              от {formatCurrency(result.requiredIncome)}
            </dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-[color:var(--color-muted)]">Общая сумма выплат</dt>
            <dd className="font-semibold">{formatCurrency(result.totalRepaid)}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-[color:var(--color-muted)]">Переплата</dt>
            <dd className="font-semibold">{formatCurrency(result.totalInterest)}</dd>
          </div>
        </dl>

        <SubmitButton
          disabled={!isValid || pushStatus === "submitting"}
          onClick={handleSubmit}
        />
        {!isValid && (
          <p className="text-center text-xs text-[color:var(--color-muted)]">
            Заполните все поля и подтвердите согласия, чтобы отправить заявку
          </p>
        )}
        {pushStatus === "submitting" && (
          <p
            className="text-center text-xs text-[color:var(--color-muted)]"
            data-testid="push-status"
          >
            Отправляем заявку…
          </p>
        )}
        {pushStatus === "success" && (
          <div
            className="bg-[color-mix(in_srgb,var(--color-success)_15%,transparent)] rounded-lg p-3 text-center text-sm text-[color:var(--color-success)]"
            data-testid="push-status"
          >
            Заявка на «{product.title}» принята. Мы свяжемся с вами после рассмотрения.
          </div>
        )}
        {pushStatus === "error" && (
          <div
            className="bg-[color-mix(in_srgb,var(--color-error)_15%,transparent)] rounded-lg p-3 text-center text-sm text-[color:var(--color-error)]"
            data-testid="push-status"
          >
            {pushError}
            <button type="button" onClick={resetPush} className="ml-2 underline">
              Повторить
            </button>
          </div>
        )}
      </aside>
    </section>
  );
}

function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), max);
}
