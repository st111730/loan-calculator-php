import { LOAN_PRODUCTS, LOAN_TYPE_IDS, type LoanTypeId } from "@/shared/config/loanProducts";

interface LoanTypeChipsProps {
  value: LoanTypeId;
  onChange: (type: LoanTypeId) => void;
}

export function LoanTypeChips({ value, onChange }: LoanTypeChipsProps) {
  return (
    <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Тип кредита">
      {LOAN_TYPE_IDS.map((id) => {
        const product = LOAN_PRODUCTS[id];
        const selected = id === value;
        return (
          <button
            key={id}
            type="button"
            role="radio"
            aria-checked={selected}
            onClick={() => onChange(id)}
            className={`rounded-full border px-4 py-2 text-sm font-medium transition-colors ${
              selected
                ? "border-blue-600 bg-blue-600 text-white hover:bg-blue-500"
                : "border-slate-600 bg-slate-800 text-slate-300 hover:border-blue-400"
            }`}
          >
            {product.title}
            <span className={`ml-2 text-xs ${selected ? "text-blue-100" : "text-slate-400"}`}>
              {(product.annualRate * 100).toLocaleString("ru-RU")}%
            </span>
          </button>
        );
      })}
    </div>
  );
}
