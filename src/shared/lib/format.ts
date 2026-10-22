/**
 * Форматирование числа с разделителями-пробелами: 1000000 -> "1 000 000".
 * Работает только с целыми без знака; для ввода дробных не предназначено.
 */
export function formatNumber(value: number): string {
  if (!Number.isFinite(value)) return "";
  return Math.round(value)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, "\u00A0");
}

/**
 * Парсинг пользовательского ввода: убирает пробелы (в т.ч. неразрывные)
 * и неразрешённые символы. Возвращает целое число или NaN.
 */
export function parseFormattedNumber(input: string): number {
  const cleaned = input.replace(/[\s\u00A0]/g, "").replace(/[^\d]/g, "");
  if (cleaned === "") return NaN;
  return Number.parseInt(cleaned, 10);
}

/** Форматирование валюты для вывода: 25393.42 -> "25 393 ₽" */
export function formatCurrency(value: number): string {
  return `${formatNumber(value)}\u00A0₽`;
}
