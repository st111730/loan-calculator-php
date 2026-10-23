<?php

declare(strict_types=1);

namespace App\Loan;

use InvalidArgumentException;

/**
 * Серверный расчёт кредита — зеркало server/src/lib/loan.js (docs/backend-api.md §6).
 *
 * Конфигурация продуктов и аннуитетная формула — копия с фронтенда
 * (src/shared/config/loanProducts.ts, src/entities/loan/model/credit.ts).
 * Сервер считает независимо и не доверяет клиентским числам.
 * Дублирование ставок/лимитов на три стороны — наблюдаемое намерение (§7, п. 1):
 * правки вносятся во все копии вручную.
 */
final class LoanCalculator
{
    /**
     * Продукты: ставка — доля единицы за год (0.18 = 18 %), суммы — ₽, сроки — месяцы.
     *
     * @var array<string, array{title: string, annualRate: float, minAmount: int, maxAmount: int, minTermMonths: int, maxTermMonths: int}>
     */
    private const LOAN_PRODUCTS = [
        'mortgage' => [
            'title' => 'Ипотека',
            'annualRate' => 0.18,
            'minAmount' => 300000,
            'maxAmount' => 30000000,
            'minTermMonths' => 12,
            'maxTermMonths' => 360,
        ],
        'auto' => [
            'title' => 'Автокредит',
            'annualRate' => 0.235,
            'minAmount' => 100000,
            'maxAmount' => 5000000,
            'minTermMonths' => 12,
            'maxTermMonths' => 84,
        ],
        'consumer' => [
            'title' => 'Потребительский',
            'annualRate' => 0.222,
            'minAmount' => 50000,
            'maxAmount' => 5000000,
            'minTermMonths' => 6,
            'maxTermMonths' => 84,
        ],
        'payday' => [
            'title' => 'Деньги до зарплаты',
            'annualRate' => 0.5,
            'minAmount' => 5000,
            'maxAmount' => 100000,
            'minTermMonths' => 1,
            'maxTermMonths' => 12,
        ],
    ];

    /**
     * Полная таблица продуктов (§6.1) для валидатора (App\Validation\ApplicationValidator):
     * названия и продуктовые диапазоны читаются отсюда, чтобы внутри PHP-бэкенда
     * лимиты и ставки жили в одной копии. В Node-версии этот список продублирован
     * (LOAN_PRODUCTS в loan.js и z.enum в validate.js) — при добавлении продукта
     * здесь правится только LoanCalculator.
     *
     * @return array<string, array{title: string, annualRate: float, minAmount: int, maxAmount: int, minTermMonths: int, maxTermMonths: int}>
     */
    public static function loanProducts(): array
    {
        return self::LOAN_PRODUCTS;
    }

    /**
     * Аннуитетный платёж (§6.2): P = S · i · (1 + i)ⁿ / ((1 + i)ⁿ − 1),
     * где i = annualRate / 12 — простое деление, без сложного процента.
     *
     * Порядок округлений критичен для бит-в-бит совпадения с историческими
     * записями и Node-версией (§6.2): сначала округляется monthlyPayment,
     * затем totalRepaid считается от уже округлённого платежа, затем
     * totalInterest — из totalRepaid.
     *
     * Продуктовые диапазоны (§5) здесь не проверяются — их проверяет валидатор
     * перед расчётом; отсекаются только предусловия, ломающие саму формулу.
     *
     * @param int|float $amount Сумма кредита, ₽
     *
     * @return array{monthlyPayment: float, totalRepaid: float, totalInterest: float}
     */
    public function calculate(string $loanType, int|float $amount, int $termMonths): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Сумма кредита должна быть положительной');
        }

        if ($termMonths < 1) {
            throw new InvalidArgumentException('Срок кредита должен быть больше нуля');
        }

        $annualRate = $this->getLoanProduct($loanType)['annualRate'];
        $monthlyRate = $annualRate / 12;
        $factor = pow(1 + $monthlyRate, $termMonths);

        $monthlyPayment = self::round2($amount * $monthlyRate * $factor / ($factor - 1));
        $totalRepaid = self::round2($monthlyPayment * $termMonths);

        return [
            'monthlyPayment' => $monthlyPayment,
            'totalRepaid' => $totalRepaid,
            'totalInterest' => self::round2($totalRepaid - $amount),
        ];
    }

    /**
     * Округление до копеек «половинка вверх» — аналог JS round2: Math.round(v × 100) / 100.
     *
     * Встроенный round() PHP не используется сознательно: он выполняет пре-раундинг
     * и на границах вида x.xx5 может дать другой цент (§6.3). Поэтому семантика
     * Math.round повторяется вручную: сдвиг до центов, и дробная часть сравнивается
     * с 0.5 без предварительного уточнения битов. Вычитание $shifted − floor($shifted)
     * точное (по лемме Стербенца для floor ≤ $shifted < floor + 1), то есть в $remainder
     * лежит настоящая дробная часть double — поведение совпадает с V8 вплоть до
     * спец-кейса 0.49999999999999994 (Math.round: 0, тогда как floor(x + 0.5): 1).
     */
    public static function round2(float $value): float
    {
        $shifted = $value * 100;
        $floorPart = floor($shifted);
        $remainder = $shifted - $floorPart;

        $cents = $remainder >= 0.5 ? $floorPart + 1 : $floorPart;

        return $cents / 100;
    }

    /**
     * Продукт по идентификатору из заявки (§6.1).
     *
     * @return array{title: string, annualRate: float, minAmount: int, maxAmount: int, minTermMonths: int, maxTermMonths: int}
     */
    private function getLoanProduct(string $loanType): array
    {
        return self::LOAN_PRODUCTS[$loanType]
            ?? throw new InvalidArgumentException('Неизвестный тип кредита: ' . $loanType);
    }
}
