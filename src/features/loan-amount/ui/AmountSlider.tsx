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
      <label htmlFor={id} className="mb-1 block text-sm font-medium text-slate-300">
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
          className="w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-right font-semibold focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
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
          className="range-filled h-2 w-full cursor-pointer appearance-none rounded-full accent-blue-600"
          aria-label="Сумма кредита слайдером"
        />
      </div>
      <p className="mt-1 text-xs text-slate-400">
        От {formatCurrency(min)} до {formatCurrency(max)}
      </p>
      {error && <p className="mt-1 text-xs text-red-400">{error}</p>}
    </div>
  );
}
