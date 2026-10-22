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
      className="w-full rounded-xl bg-blue-600 px-6 py-3 font-semibold text-white shadow-lg shadow-blue-600/20 transition-colors hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-500 disabled:shadow-none"
    >
      Оставить заявку
    </button>
  );
}
