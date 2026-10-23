import { LoanCalculator } from "@/widgets/loan-calculator/ui/LoanCalculator";

export function CalculatorPage() {
  return (
    <main className="mx-auto max-w-5xl bg-[color:var(--color-base)] px-4 py-10 text-[color:var(--color-text)]">
      <h1 className="mb-6 font-[family-name:var(--font-display)] text-3xl font-bold uppercase tracking-tight text-[color:var(--color-accent)]">
        Кредитный калькулятор
      </h1>
      <LoanCalculator />
    </main>
  );
}
