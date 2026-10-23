interface SubmitButtonProps {
  disabled: boolean;
  onClick: () => void;
}

export function SubmitButton({ disabled, onClick }: SubmitButtonProps) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className="w-full rounded-none bg-accent px-6 py-3 font-semibold text-ink-on-accent shadow-lg shadow-[color:var(--shadow-color)] transition-colors hover:bg-accent-pressed focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:bg-surface disabled:text-muted disabled:shadow-none disabled:ring-1 disabled:ring-hairline"
    >
      Оставить заявку
    </button>
  );
}
