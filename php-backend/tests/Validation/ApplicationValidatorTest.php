<?php

declare(strict_types=1);

namespace App\Tests\Validation;

use App\Validation\ApplicationValidator;
use PHPUnit\Framework\TestCase;

/**
 * Тесты валидации заявки (docs/backend-api.md §5).
 *
 * Тексты сняты с server/src/lib/validate.js и фронтовой
 * src/entities/applicant/model/schema.ts: базовые — англоязычные дефолты
 * zod v3, продуктовые — русские; фронтенд показывает issues[0].message (§7, п. 8).
 * Порядок: базовые — по порядку полей схемы (loanType → amount → termMonths →
 * fullName → monthlyIncome → hasCitizenship → consentGiven); продуктовые —
 * сначала amount, затем termMonths; при провале базовых продуктовых проверок
 * вообще нет.
 *
 * @coversDefaultClass \App\Validation\ApplicationValidator
 */
final class ApplicationValidatorTest extends TestCase
{
    private ApplicationValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ApplicationValidator();
    }

    /**
     * Валидный payload (продукт «Потребительский»: 50000–5000000 ₽ / 6–84 мес).
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function validBody(array $overrides = []): array
    {
        return $overrides + [
            'loanType' => 'consumer',
            'amount' => 300000,
            'termMonths' => 12,
            'fullName' => 'Иванов Иван Иванович',
            'monthlyIncome' => 50000,
            'hasCitizenship' => true,
            'consentGiven' => true,
        ];
    }

    /**
     * Валидный payload: нормализация — int для целых чисел, trim для ФИО,
     * неизвестное поле отброшено.
     *
     * @covers ::validate
     */
    public function testValidApplicationIsNormalized(): void
    {
        $result = $this->validator->validate(self::validBody([
            'amount' => 300000.0,                              // Number.isInteger проходит и для дробных целых
            'termMonths' => 12.0,
            'fullName' => "  Иванов Иван Иванович  ",           // zod .trim()
            'cookie' => 'dropped',                             // неизвестное поле не попадает в данные
        ]));

        self::assertTrue($result->isSuccess());
        self::assertSame([
            'loanType' => 'consumer',
            'amount' => 300000,             // int, как в Node-версии
            'termMonths' => 12,             // int
            'fullName' => 'Иванов Иван Иванович',
            'monthlyIncome' => 50000,
            'hasCitizenship' => true,
            'consentGiven' => true,
        ], $result->data);
    }

    /**
     * monthlyIncome может быть дробным: целостность требуется только
     * у amount/termMonths (§5).
     *
     * @covers ::validate
     */
    public function testFractionalMonthlyIncomeIsValid(): void
    {
        $result = $this->validator->validate(self::validBody(['monthlyIncome' => 50000.55]));

        self::assertTrue($result->isSuccess());
        self::assertSame(50000.55, $result->data['monthlyIncome']);
    }

    /**
     * Пустое тело: порядок базовых ошибок — по порядку полей схемы (§5).
     * Отсутствующее поле у zod — «Required» (invalid_type c received undefined),
     * у enum — дефолт c received 'undefined', у literal — свой дефолт.
     *
     * @covers ::validate
     */
    public function testBaseIssuesFollowSchemaFieldOrder(): void
    {
        $result = $this->validator->validate([]);

        self::assertSame([
            ['path' => 'loanType', 'message' => "Invalid enum value. Expected 'mortgage' | 'auto' | 'consumer' | 'payday', received 'undefined'"],
            ['path' => 'amount', 'message' => 'Required'],
            ['path' => 'termMonths', 'message' => 'Required'],
            ['path' => 'fullName', 'message' => 'Required'],
            ['path' => 'monthlyIncome', 'message' => 'Required'],
            ['path' => 'hasCitizenship', 'message' => 'Invalid literal value, expected true'],
            ['path' => 'consentGiven', 'message' => 'Invalid literal value, expected true'],
        ], $result->issues);
    }

    /**
     * Продуктовые диапазоны §5: соседние вне диапазона значения дают русский
     * текст с подставленным названием продукта (границы — отдельный тест).
     * Два последних столбца — базовые amount/termMonths внутри диапазона
     * продукта, чтобы вторая продуктовая ошибка не замусорила issues.
     *
     * @return array<string, array{0: string, 1: string, 2: int, 3: string, 4: int, 5: int}>
     */
    public static function productRangeFailuresProvider(): array
    {
        return [
            'Ипотека: сумма ниже' => ['mortgage', 'amount', 299999, 'Сумма для «Ипотека»: от 300000 до 30000000 ₽', 300000, 12],
            'Ипотека: сумма выше' => ['mortgage', 'amount', 30000001, 'Сумма для «Ипотека»: от 300000 до 30000000 ₽', 300000, 12],
            'Ипотека: срок ниже' => ['mortgage', 'termMonths', 11, 'Срок для «Ипотека»: от 12 до 360 мес', 300000, 12],
            'Ипотека: срок выше' => ['mortgage', 'termMonths', 361, 'Срок для «Ипотека»: от 12 до 360 мес', 300000, 12],
            'Автокредит: сумма ниже' => ['auto', 'amount', 99999, 'Сумма для «Автокредит»: от 100000 до 5000000 ₽', 100000, 12],
            'Автокредит: сумма выше' => ['auto', 'amount', 5000001, 'Сумма для «Автокредит»: от 100000 до 5000000 ₽', 100000, 12],
            'Автокредит: срок ниже' => ['auto', 'termMonths', 11, 'Срок для «Автокредит»: от 12 до 84 мес', 100000, 12],
            'Автокредит: срок выше' => ['auto', 'termMonths', 85, 'Срок для «Автокредит»: от 12 до 84 мес', 100000, 12],
            'Потребительский: сумма ниже' => ['consumer', 'amount', 49999, 'Сумма для «Потребительский»: от 50000 до 5000000 ₽', 50000, 6],
            'Потребительский: сумма выше' => ['consumer', 'amount', 5000001, 'Сумма для «Потребительский»: от 50000 до 5000000 ₽', 50000, 6],
            'Потребительский: срок ниже' => ['consumer', 'termMonths', 5, 'Срок для «Потребительский»: от 6 до 84 мес', 50000, 6],
            'Потребительский: срок выше' => ['consumer', 'termMonths', 85, 'Срок для «Потребительский»: от 6 до 84 мес', 50000, 6],
            'Деньги до зарплаты: сумма ниже' => ['payday', 'amount', 4999, 'Сумма для «Деньги до зарплаты»: от 5000 до 100000 ₽', 5000, 1],
            'Деньги до зарплаты: сумма выше' => ['payday', 'amount', 100001, 'Сумма для «Деньги до зарплаты»: от 5000 до 100000 ₽', 5000, 1],
            // Нижняя граница срока (0) отсекается базовой проверкой positive,
            // а не продуктовым диапазоном — поэтому только верхний выход
            'Деньги до зарплаты: срок выше' => ['payday', 'termMonths', 13, 'Срок для «Деньги до зарплаты»: от 1 до 12 мес', 5000, 1],
        ];
    }

    /**
     * @covers ::validate
     * @dataProvider productRangeFailuresProvider
     */
    public function testProductRangeFailures(
        string $loanType,
        string $field,
        int $value,
        string $expectedMessage,
        int $baseAmount,
        int $baseTerm,
    ): void {
        $result = $this->validator->validate([
            'loanType' => $loanType,
            'amount' => $baseAmount,
            'termMonths' => $baseTerm,
            $field => $value,
        ] + self::validBody());

        self::assertFalse($result->isSuccess());
        self::assertSame([['path' => $field, 'message' => $expectedMessage]], $result->issues);
    }

    /**
     * Границы продуктовых диапазонов min/max проходят (§5, п. 2–3).
     *
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    public static function productBoundariesProvider(): array
    {
        return [
            'Ипотека: оба min' => ['mortgage', 300000, 12],
            'Ипотека: оба max' => ['mortgage', 30000000, 360],
            'Автокредит: оба min' => ['auto', 100000, 12],
            'Автокредит: оба max' => ['auto', 5000000, 84],
            'Потребительский: оба min' => ['consumer', 50000, 6],
            'Потребительский: оба max' => ['consumer', 5000000, 84],
            'Деньги до зарплаты: оба min' => ['payday', 5000, 1],
            'Деньги до зарплаты: оба max' => ['payday', 100000, 12],
        ];
    }

    /**
     * @covers ::validate
     * @dataProvider productBoundariesProvider
     */
    public function testProductBoundariesAreAccepted(string $loanType, int $amount, int $termMonths): void
    {
        $result = $this->validator->validate([
            'loanType' => $loanType,
            'amount' => $amount,
            'termMonths' => $termMonths,
        ] + self::validBody());

        self::assertTrue($result->isSuccess(), json_encode($result->issues, JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * Провалены обе продуктовые проверки: сначала amount, затем termMonths (§5).
     *
     * @covers ::validate
     */
    public function testRangeIssuesOrderedAmountThenTerm(): void
    {
        $result = $this->validator->validate(self::validBody(['amount' => 100, 'termMonths' => 999]));

        self::assertSame([
            ['path' => 'amount', 'message' => 'Сумма для «Потребительский»: от 50000 до 5000000 ₽'],
            ['path' => 'termMonths', 'message' => 'Срок для «Потребительский»: от 6 до 84 мес'],
        ], $result->issues);
    }

    /**
     * Базовая ошибка приостанавливает продуктовые проверки (§5): невалидный
     * monthlyIncome при сумме вне диапазона — единственная issue.
     *
     * @covers ::validate
     */
    public function testBaseIssueSuppressesProductChecks(): void
    {
        $result = $this->validator->validate(self::validBody(['amount' => 100, 'monthlyIncome' => 0]));

        self::assertSame([['path' => 'monthlyIncome', 'message' => 'Number must be greater than 0']], $result->issues);
    }

    /**
     * Базовые ошибки чисел — тип не number / нецелое / неположительное;
     * отрицательная сумма отсекается продуктовым диапазоном, а не базовой
     * проверкой (§4.2).
     *
     * @return array<string, array{0: string, 1: mixed, 2: array{path: string, message: string}}>
     */
    public static function numberFieldErrorsProvider(): array
    {
        return [
            'amount: строка' => ['amount', '300000', ['path' => 'amount', 'message' => 'Expected number, received string']],
            'amount: bool' => ['amount', true, ['path' => 'amount', 'message' => 'Expected number, received boolean']],
            'amount: null' => ['amount', null, ['path' => 'amount', 'message' => 'Expected number, received null']],
            'amount: массив' => ['amount', [1, 2], ['path' => 'amount', 'message' => 'Expected number, received array']],
            'amount: объект' => ['amount', ['x' => 1], ['path' => 'amount', 'message' => 'Expected number, received object']],
            'amount: дробь' => ['amount', 300000.5, ['path' => 'amount', 'message' => 'Expected integer, received float']],
            'amount: отрицательный — продуктовый диапазон' => ['amount', -300000, ['path' => 'amount', 'message' => 'Сумма для «Потребительский»: от 50000 до 5000000 ₽']],
            'termMonths: дробь' => ['termMonths', 1.5, ['path' => 'termMonths', 'message' => 'Expected integer, received float']],
            'termMonths: ноль' => ['termMonths', 0, ['path' => 'termMonths', 'message' => 'Number must be greater than 0']],
            'monthlyIncome: ноль' => ['monthlyIncome', 0, ['path' => 'monthlyIncome', 'message' => 'Number must be greater than 0']],
            'monthlyIncome: отрицательная дробь' => ['monthlyIncome', -1.5, ['path' => 'monthlyIncome', 'message' => 'Number must be greater than 0']],
        ];
    }

    /**
     * @covers ::validate
     * @dataProvider numberFieldErrorsProvider
     */
    public function testNumberFieldErrors(string $field, mixed $value, array $expectedIssue): void
    {
        $result = $this->validator->validate(self::validBody([$field => $value]));

        self::assertSame([$expectedIssue], $result->issues);
    }

    /**
     * Бесконечность в JSON возможна через «1e999» (json_decode отдаёт INF).
     * Проверки идут по порядку цепочки validate.js: amount — int → finite
     * (обе ошибки), termMonths — int → finite, monthlyIncome — только finite
     * (INF > 0, positive не проваливается), как и в zod.
     *
     * @covers ::validate
     */
    public function testInfinityIssuesFollowChainOrder(): void
    {
        $amount = $this->validator->validate(self::validBody(['amount' => INF]));

        self::assertSame([
            ['path' => 'amount', 'message' => 'Expected integer, received float'],
            ['path' => 'amount', 'message' => 'Number must be finite'],
        ], $amount->issues);

        $term = $this->validator->validate(self::validBody(['termMonths' => INF]));

        self::assertSame([
            ['path' => 'termMonths', 'message' => 'Expected integer, received float'],
            ['path' => 'termMonths', 'message' => 'Number must be finite'],
        ], $term->issues);

        $income = $this->validator->validate(self::validBody(['monthlyIncome' => INF]));

        self::assertSame([['path' => 'monthlyIncome', 'message' => 'Number must be finite']], $income->issues);
    }

    /**
     * ФИО: проваленные проверки выписываются по порядку цепочки zod
     * (min → max → regex); эмодзи считаются за 2 UTF-16-единицы, как value.length
     * в JS (§7, п. 9), поэтому «😀» проходит по длине и отсекается только regex.
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function fullNameErrorsProvider(): array
    {
        return [
            'пустая строка: и длина, и regex' => ['', ['String must contain at least 2 character(s)', 'Invalid']],
            'один символ: и длина, и regex' => ['Ж', ['String must contain at least 2 character(s)', 'Invalid']],
            '121 символ: и длина, и regex' => [str_repeat('а', 121), ['String must contain at most 120 character(s)', 'Invalid']],
            'цифры запрещены' => ['Иванов 123', ['Invalid']],
            'эмодзи: 2 UTF-16-единицы, отсекает regex' => ['😀', ['Invalid']],
        ];
    }

    /**
     * @covers ::validate
     * @dataProvider fullNameErrorsProvider
     */
    public function testFullNameIssues(string $fullName, array $messages): void
    {
        $result = $this->validator->validate(self::validBody(['fullName' => $fullName]));

        self::assertSame($messages, array_column($result->issues, 'message'));
        self::assertSame(['fullName'], array_unique(array_column($result->issues, 'path')));
    }

    /**
     * ФИО: допустимые символы — кириллица (Ё/ё), латиница, пробелы (включая
     * NBSP и перевод строки), апостроф, дефис; края обрезаются как в JS.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function fullNameValidProvider(): array
    {
        return [
            'обрезка краёв' => ['  Иванов Иван  ', 'Иванов Иван'],
            'дефис' => ['Иванов-Петров', 'Иванов-Петров'],
            'апостроф' => ["О'Коннор", "О'Коннор"],
            'Ё/ё и латиница' => ['Ёлкин Silver', 'Ёлкин Silver'],
            'перевод строки внутри' => ["Иванов\nИван", "Иванов\nИван"],
            'NBSP внутри (JS \s, не ASCII \s)' => ["Иванов\u{00A0}Иван", "Иванов\u{00A0}Иван"],
        ];
    }

    /**
     * @covers ::validate
     * @dataProvider fullNameValidProvider
     */
    public function testFullNameAllowedValues(string $fullName, string $normalized): void
    {
        $result = $this->validator->validate(self::validBody(['fullName' => $fullName]));

        self::assertTrue($result->isSuccess(), json_encode($result->issues, JSON_UNESCAPED_UNICODE) ?: '');
        self::assertSame($normalized, $result->data['fullName']);
    }

    /**
     * z.literal(true): ни 1, ни «true», ни false не допускаются (§4.2).
     *
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function consentFlagsProvider(): array
    {
        return [
            ['hasCitizenship', false],
            ['hasCitizenship', 1],
            ['hasCitizenship', 'true'],
            ['hasCitizenship', null],
            ['consentGiven', false],
            ['consentGiven', 1],
            ['consentGiven', null],
        ];
    }

    /**
     * @covers ::validate
     * @dataProvider consentFlagsProvider
     */
    public function testConsentFlagsRequireExactlyTrue(string $field, mixed $value): void
    {
        $result = $this->validator->validate(self::validBody([$field => $value]));

        self::assertSame([['path' => $field, 'message' => 'Invalid literal value, expected true']], $result->issues);
    }

    /**
     * loanType: значение интерполируется в тексте по правилам JS — см. jsCast().
     *
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function loanTypeEnumProvider(): array
    {
        return [
            'строка не из списка' => ['student', "Invalid enum value. Expected 'mortgage' | 'auto' | 'consumer' | 'payday', received 'student'"],
            'число' => [123, "Invalid enum value. Expected 'mortgage' | 'auto' | 'consumer' | 'payday', received '123'"],
            'bool' => [true, "Invalid enum value. Expected 'mortgage' | 'auto' | 'consumer' | 'payday', received 'true'"],
        ];
    }

    /**
     * @covers ::validate
     * @dataProvider loanTypeEnumProvider
     */
    public function testLoanTypeEnumIssues(mixed $loanType, string $expectedMessage): void
    {
        $result = $this->validator->validate(self::validBody(['loanType' => $loanType]));

        self::assertSame([['path' => 'loanType', 'message' => $expectedMessage]], $result->issues);
    }
}
