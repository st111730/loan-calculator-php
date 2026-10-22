import { formatCurrency, formatNumber, parseFormattedNumber } from "@/shared/lib/format";

describe("formatNumber", () => {
  it.each([
    [0, "0"],
    [1000, "1\u00A0000"],
    [1_000_000, "1\u00A0000\u00A0000"],
    [12_345_678, "12\u00A0345\u00A0678"],
  ])("%i -> %s", (value, expected) => {
    expect(formatNumber(value)).toBe(expected);
  });

  it("не использует HTML/JS-опасные символы", () => {
    const formatted = formatNumber(1_000_000);
    expect(formatted).not.toMatch(/[<>"'&]/);
  });
});

describe("parseFormattedNumber", () => {
  it.each([
    ["1000000", 1_000_000],
    ["1 000 000", 1_000_000], // обычные пробелы
    ["1\u00A0000\u00A0000", 1_000_000], // неразрывные пробелы
    ["50 000", 50_000],
    ["7", 7],
  ])("%s -> %i", (input, expected) => {
    expect(parseFormattedNumber(input)).toBe(expected);
  });

  it("вырезает запрещённые символы (защита от инъекций)", () => {
    expect(parseFormattedNumber("<script>100</script>")).toBe(100);
    expect(parseFormattedNumber("100abc")).toBe(100);
    expect(parseFormattedNumber("1'000")).toBe(1000);
  });

  it("возвращает NaN для пустой строки", () => {
    expect(parseFormattedNumber("")).toBeNaN();
    expect(parseFormattedNumber("  ")).toBeNaN();
  });
});

describe("formatCurrency", () => {
  it("добавляет знак рубля", () => {
    expect(formatCurrency(25_393)).toBe("25\u00A0393\u00A0₽");
  });
});
