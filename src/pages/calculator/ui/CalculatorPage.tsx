import { LoanCalculator } from "@/widgets/loan-calculator/ui/LoanCalculator";

export function CalculatorPage() {
  return (
    <main className="mx-auto max-w-5xl px-4 py-10">
      <h1 className="mb-6 text-2xl font-bold text-slate-100">Кредитный калькулятор</h1>
      <LoanCalculator />
    </main>
  );
}
