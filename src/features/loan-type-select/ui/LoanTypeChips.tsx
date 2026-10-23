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
            className={`rounded-none border px-4 py-2 text-sm transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent ${
              selected
                ? // Активный чип — инверсия: жёлтый фон, чёрный текст (§4)
                  "border-accent bg-accent font-semibold text-ink-on-accent hover:bg-accent-pressed"
                : // Неактивный: рамка Hairline; hover светлит её до Text, но не в жёлтый (§6)
                  "border-hairline bg-surface font-medium text-ink hover:border-ink"
            }`}
          >
            {product.title}
            <span
              className={`ml-2 text-xs font-normal ${selected ? "text-ink-on-accent opacity-70" : "text-muted"}`}
            >
              {(product.annualRate * 100).toLocaleString("ru-RU")}%
            </span>
          </button>
        );
      })}
    </div>
  );
}
