import { useId } from "react";
import { formatCurrency, formatNumber, parseFormattedNumber } from "@/shared/lib/format";

interface AmountSliderProps {
  value: number;
  min: number;
  max: number;
  step?: number;
  error?: string;
  onChange: (amount: number) => void;
}

/** Поле ввода суммы: range + input с форматированием "1 000 000" */
export function AmountSlider({ value, min, max, step = 10_000, error, onChange }: AmountSliderProps) {
  const id = useId();
  const clamped = Math.min(Math.max(value, min), max);
  const fillPercent = max > min ? ((clamped - min) / (max - min)) * 100 : 0;

  return (
    <div>
      <label htmlFor={id} className="mb-2 block text-[13px] font-medium uppercase tracking-[0.08em] text-muted">
        Сумма кредита
      </label>
      <div className="flex flex-col gap-3">
        <input
          id={id}
          type="text"
          inputMode="numeric"
          autoComplete="off"
          value={formatNumber(value)}
          onChange={(e) => {
            const parsed = parseFormattedNumber(e.target.value);
            if (!Number.isNaN(parsed)) onChange(parsed);
            else if (e.target.value.trim() === "") onChange(0);
          }}
          className="h-14 w-full rounded-none border-b-2 border-hairline bg-transparent px-1 text-right text-lg font-semibold text-ink tabular-nums outline-none transition-colors hover:border-ink focus:border-accent"
        />
        <input
          type="range"
          min={min}
          max={max}
          step={step}
          value={clamped}
          onChange={(e) => onChange(Number(e.target.value))}
          style={{
            background: `linear-gradient(to right, rgb(var(--slider-fill)) ${fillPercent}%, rgb(var(--slider-track)) ${fillPercent}%)`,
          }}
          data-fill-percent={fillPercent}
          className="range-filled h-2 w-full cursor-pointer appearance-none rounded-full"
          aria-label="Сумма кредита слайдером"
        />
      </div>
      <p className="mt-2 text-xs text-muted">
        От {formatCurrency(min)} до {formatCurrency(max)}
      </p>
      {error && <p className="mt-1 text-xs text-error">{error}</p>}
    </div>
  );
}
