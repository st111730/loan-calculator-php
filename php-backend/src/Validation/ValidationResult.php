<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Результат валидации заявки — семантика safeParse из zod:
 * либо нормализованные данные, либо список проблем для тела ошибки §4.2.
 */
final class ValidationResult
{
    /**
     * Нормализованные данные заявки, null при провале валидации.
     *
     * amount/termMonths — int (дробные отсекает проверка int §5); monthlyIncome
     * может быть дробным; fullName уже обрезан по краям (zod .trim()); булевы
     * флаги по контракту всегда true.
     *
     * @var array{loanType: string, amount: int, termMonths: int, fullName: string, monthlyIncome: int|float, hasCitizenship: true, consentGiven: true}|null
     */
    public readonly ?array $data;

    /**
     * Проблемы для {"error":"validation_error"} (§4.2): path — имя поля
     * (склеенное точками; тут всегда один уровень, поля верхнего уровня),
     * message — текст §5: русские продуктовые, английские zod-дефолты.
     *
     * @var list<array{path: string, message: string}>
     */
    public readonly array $issues;

    /**
     * @param array{loanType: string, amount: int, termMonths: int, fullName: string, monthlyIncome: int|float, hasCitizenship: true, consentGiven: true}|null $data
     * @param list<array{path: string, message: string}> $issues
     */
    public function __construct(?array $data, array $issues)
    {
        $this->data = $data;
        $this->issues = $issues;
    }

    /**
     * @param array{loanType: string, amount: int, termMonths: int, fullName: string, monthlyIncome: int|float, hasCitizenship: true, consentGiven: true} $data
     */
    public static function success(array $data): self
    {
        return new self($data, []);
    }

    /**
     * @param list<array{path: string, message: string}> $issues
     */
    public static function failure(array $issues): self
    {
        return new self(null, $issues);
    }

    public function isSuccess(): bool
    {
        return $this->issues === [];
    }
}
