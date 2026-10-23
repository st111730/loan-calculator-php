<?php

declare(strict_types=1);

namespace App\Tests\Loan;

use App\Loan\LoanCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Тесты расчёта кредита (docs/backend-api.md §6).
 *
 * Эталонные значения сняты с server/src/lib/loan.js (Node-реализация):
 * PHP-версия обязана совпасть бит-в-бит — не только в формуле, но и в порядке
 * округлений: monthlyPayment округляется до 2 знаков, totalRepaid считается
 * от уже округлённого платежа, totalInterest — из totalRepaid (§6.2).
 *
 * @coversDefaultClass \App\Loan\LoanCalculator
 */
final class LoanCalculatorTest extends TestCase
{
    private LoanCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new LoanCalculator();
    }

    /**
     * Эталонные примеры §6.3 — по одному на каждый из четырёх продуктов.
     *
     * @return array<string, array{string, int, int, float, float, float}>
     */
    public static function referenceExamplesProvider(): array
    {
        return [
            'Ипотека: 300 000 ₽ / 12 мес' => ['mortgage', 300000, 12, 27504.00, 330048.00, 30048.00],
            'Автокредит: 1 000 000 ₽ / 36 мес' => ['auto', 1000000, 36, 38970.81, 1402949.16, 402949.16],
            'Потребительский: 500 000 ₽ / 24 мес' => ['consumer', 500000, 24, 25988.49, 623723.76, 123723.76],
            'Деньги до зарплаты: 50 000 ₽ / 6 мес' => ['payday', 50000, 6, 9589.91, 57539.46, 7539.46],
        ];
    }

    /**
     * @covers ::calculate
     * @dataProvider referenceExamplesProvider
     */
    public function testAnnuityMatchesNodeVersion(
        string $loanType,
        int $amount,
        int $termMonths,
        float $expectedMonthlyPayment,
        float $expectedTotalRepaid,
        float $expectedTotalInterest,
    ): void {
        $result = $this->calculator->calculate($loanType, $amount, $termMonths);

        self::assertSame($expectedMonthlyPayment, $result['monthlyPayment']);
        self::assertSame($expectedTotalRepaid, $result['totalRepaid']);
        self::assertSame($expectedTotalInterest, $result['totalInterest']);
    }

    /**
     * Дробная сумма (валидатор §5 такие суммы по amount отсекает, но
     * NUMERIC(14,2) и формула дробные допускают): значения сняты с той же
     * JS-реализации server/src/lib/loan.js при той же семантике округления.
     *
     * @return array<string, array{string, float, int, float, float, float}>
     */
    public static function fractionalExamplesProvider(): array
    {
        return [
            'Потребительский: 150 025.55 ₽ / 21 мес' => ['consumer', 150025.55, 21, 8686.51, 182416.71, 32391.16],
            'Деньги до зарплаты: 12 500.75 ₽ / 4 мес' => ['payday', 12500.75, 4, 3457.37, 13829.48, 1328.73],
        ];
    }


    /**
     * @covers ::calculate
     * @dataProvider fractionalExamplesProvider
     */
    public function testAnnuityWithFractionalAmountMatchesNodeVersion(
        string $loanType,
        float $amount,
        int $termMonths,
        float $expectedMonthlyPayment,
        float $expectedTotalRepaid,
        float $expectedTotalInterest,
    ): void {
        $result = $this->calculator->calculate($loanType, $amount, $termMonths);

        self::assertSame($expectedMonthlyPayment, $result['monthlyPayment']);
        self::assertSame($expectedTotalRepaid, $result['totalRepaid']);
        self::assertSame($expectedTotalInterest, $result['totalInterest']);
    }

    /**
     * Округление до копеек — «половинка вверх», как Math.round в JS (§6.2).
     *
     * Встроенный round() PHP тут не годится: он делает пре-раундинг и на
     * границах вида x.xx5 может дать другой цент (§6.3), поэтому семантика
     * Math.round эмулируется вручную. Ниже — точные половинки (вверх),
     * значения, у которых в double дробная часть чуть ниже половинки (вниз),
     * и спец-кейс V8: 0.004999999999999999 → 0.0, потому что дробная часть
     * v*100 = 0.49999999999999994 < 0.5, хотя floor(v*100 + 0.5) уже даёт 1.
     *
     * @return array<string, array{float, float}>
     */
    public static function roundingProvider(): array
    {
        return [
            'типичный цент не меняется' => [1402949.16, 1402949.16],
            'истинная половинка 12.5 → вверх' => [0.125, 0.13],
            'истинная половинка 30.5 → вверх' => [0.305, 0.31],
            'истинная половинка 1237.5 → вверх' => [12.375, 12.38],
            'истинная половинка 9999.5 → вверх' => [99.995, 100.0],
            'граница double: 0.285 в виде 28.499999999999996 ниже половинки → вниз' => [0.285, 0.28],
            '1.005 в double ниже 100.5 → вниз' => [1.005, 1.0],
            'спец-кейс V8: floor(x + 0.5) уводит в 1.0, а Math.round оставляет 0' => [0.004999999999999999, 0.0],
            'отрицательная половинка округляется вверх, к +∞ (Math.round(-12.5) = -12)' => [-0.125, -0.12],
            'отрицательная половинка на границе нуля уходит в 0 (Math.round(-0.5) = -0 ≈ 0)' => [-0.005, 0.0],
        ];
    }

    /**
     * @covers ::round2
     * @dataProvider roundingProvider
     */
    public function testRound2EmulatesJavaScriptMathRound(float $value, float $expected): void
    {
        self::assertSame($expected, LoanCalculator::round2($value));
    }

    /**
     * @covers ::calculate
     */
    public function testUnknownLoanTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Неизвестный тип кредита: student');

        $this->calculator->calculate('student', 100000, 12);
    }

    /**
     * Диапазоны продуктов (§5) здесь не проверяются — это работа валидатора.
     * Но нулевая/отрицательная сумма ломает саму формулу, поэтому отсекается.
     */
    public function testNonPositiveAmountThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Сумма кредита должна быть положительной');

        $this->calculator->calculate('consumer', 0, 12);
    }

    /**
     * Срок 0 даёт (1+i)ⁿ = 1 и деление на ноль, срок < 0 — бессмысленное
     * «отрицательное переплачивание», поэтому оба отсекаются.
     */
    public function testNonPositiveTermThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Срок кредита должен быть больше нуля');

        $this->calculator->calculate('consumer', 500000, 0);
    }
}
