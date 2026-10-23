<?php

declare(strict_types=1);

namespace App\Validation;

use App\Loan\LoanCalculator;

/**
 * Валидация заявки — зеркало server/src/lib/validate.js (docs/backend-api.md, §5).
 *
 * Повторяем семантику zod-схемы Node-версии, чтобы issues[0].message, который
 * фронтенд показывает пользователю, не изменился (§7, п. 8):
 *
 *  - продуктовые проверки диапазонов — русские тексты, зашитые одинаково в обеих
 *    zod-схемах («Сумма для «Ипотека»: от 300000 до 30000000 ₽», §5, п. 2–3);
 *  - проверки типов — дефолтные англоязычные тексты zod v3 (en-локаль, zod
 *    ^3.24.1), именно их отдавала Node-версия: «Required»,
 *    «Expected number, received string», «Expected integer, received float»,
 *    «Number must be finite», «Number must be greater than 0»,
 *    «String must contain at least 2 character(s)», «Invalid» (regex) и т.д.;
 *  - базовые проверки идут в порядке полей схемы: loanType → amount →
 *    termMonths → fullName → monthlyIncome → hasCitizenship → consentGiven;
 *  - пока хоть одна базовая не прошла, продуктовых проверок диапазонов вообще
 *    нет (superRefine в zod срабатывает только при успешном базовом разборе);
 *  - при успехе базовых продуктовые идут «сначала amount, затем termMonths»;
 *  - все проваленные проверки поля выписываются по порядку цепочки validate.js
 *    (у amount — int → finite, у termMonths — int → finite → positive);
 *  - неизвестные поля тела молча отбрасываются (валидатор не в strict-режиме §4.2).
 *
 * Библиотека валидации сознательно не подключена (README): тексты и порядок
 * issues проще повторить вручную, чем настраивать symfony/validator под них.
 */
final class ApplicationValidator
{
    /**
     * Пробельные символы JS: \s в ECMAScript покрывает не только ASCII-whitespace
     * (TAB/LF/VT/FF/CR/пробел), но и U+00A0, U+1680, U+2000–200A, U+2028, U+2029,
     * U+202F, U+205F, U+3000 и BOM U+FEFF. \s в PCRE без UCP остаётся ASCII-only,
     * а \v в PCRE — это класс «вертикальные пробелы», а не одиночный U+000B,
     * поэтому набор выписан явно (иначе расхождение с Node на ФИО вида
     * «Иван{NBSP}Иванов», §7, п. 9: «допускает любые пробельные символы»).
     *
     * Константа — фрагмент PCRE-класса (дальше обрамляется /u-шаблонами).
     */
    private const JS_WHITESPACE = '\t\n\x{b}\f\r \x{a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}\x{feff}';

    /**
     * Допустимые символы ФИО после обрезки краёв (§5, п. 4): кириллица
     * (вкл. Ё/ё), латиница, whitespace, апостроф и дефис; длина 2–120 символ(ов).
     */
    private const FULL_NAME_PATTERN = '/^[А-Яа-яЁёA-Za-z' . self::JS_WHITESPACE . '\'-]{2,120}$/u';

    /** Обрезка краёв — String.prototype.trim из JS: набор тот же, что у \s в JS. */
    private const TRIM_PATTERN = '/^[' . self::JS_WHITESPACE . ']+|[' . self::JS_WHITESPACE . ']+$/u';

    /** Символы за пределами BMP: в JS-длине каждый занимает 2 UTF-16-единицы. */
    private const ASTRAL_PATTERN = '/[\x{10000}-\x{10ffff}]/u';

    /** Number.MAX_SAFE_INTEGER из JS: выше — целость float-значения не гарантирована. */
    private const MAX_SAFE_INTEGER = 9007199254740991;

    /**
     * Провалидировать распарсенное JSON-тело (App\Http\Request::json()).
     *
     * @param array<string, mixed> $body
     */
    public function validate(array $body): ValidationResult
    {
        // Базовые проверки — строго в порядке полей схемы validate.js (§5)
        $issues = [
            ...$this->validateLoanType(
                array_key_exists('loanType', $body),
                $body['loanType'] ?? null,
            ),
            ...$this->validateNumber(
                'amount',
                array_key_exists('amount', $body),
                $body['amount'] ?? null,
                ['int', 'finite'],
            ),
            ...$this->validateNumber(
                'termMonths',
                array_key_exists('termMonths', $body),
                $body['termMonths'] ?? null,
                ['int', 'finite', 'positive'],
            ),
            ...$this->validateFullName(
                array_key_exists('fullName', $body),
                $body['fullName'] ?? null,
            ),
            ...$this->validateNumber(
                'monthlyIncome',
                array_key_exists('monthlyIncome', $body),
                $body['monthlyIncome'] ?? null,
                ['finite', 'positive'],
            ),
            ...$this->validateLiteralTrue(
                'hasCitizenship',
                array_key_exists('hasCitizenship', $body),
                $body['hasCitizenship'] ?? null,
            ),
            ...$this->validateLiteralTrue(
                'consentGiven',
                array_key_exists('consentGiven', $body),
                $body['consentGiven'] ?? null,
            ),
        ];

        if ($issues !== []) {
            // Базовые проверки не прошли — продуктовые диапазоны не проверяются (§5)
            return ValidationResult::failure($issues);
        }

        $loanType = $body['loanType'];
        $amount = $body['amount'];
        $termMonths = $body['termMonths'];
        // $loanType — string, $amount/$termMonths — int|float после базовых проверок
        $product = LoanCalculator::loanProducts()[$loanType];

        // Продуктовые диапазоны: сначала amount, затем termMonths (§5)
        if ($amount < $product['minAmount'] || $amount > $product['maxAmount']) {
            $issues[] = [
                'path' => 'amount',
                'message' => sprintf('Сумма для «%s»: от %s до %s ₽', $product['title'], $product['minAmount'], $product['maxAmount']),
            ];
        }

        if ($termMonths < $product['minTermMonths'] || $termMonths > $product['maxTermMonths']) {
            $issues[] = [
                'path' => 'termMonths',
                'message' => sprintf('Срок для «%s»: от %s до %s мес', $product['title'], $product['minTermMonths'], $product['maxTermMonths']),
            ];
        }

        if ($issues !== []) {
            return ValidationResult::failure($issues);
        }

        return ValidationResult::success([
            'loanType' => $loanType,
            // Целые JSON-числа нормализуем в int; дробные отсекает проверка int (§5)
            'amount' => self::normalizeInt($amount),
            'termMonths' => self::normalizeInt($termMonths),
            // zod .trim() обрезает края до проверок — тут уже нормализованное значение
            'fullName' => self::trimJs($body['fullName']),
            'monthlyIncome' => $body['monthlyIncome'],
            'hasCitizenship' => true,
            'consentGiven' => true,
        ]);
    }

    /**
     * loanType — только 4 допустимых значения (§5, п. 1). Список продуктов берётся
     * из LoanCalculator (§6.1), чтобы внутри PHP-бэкенда он существовал в одной
     * копии — порядок совпадает с z.enum в validate.js.
     *
     * @return list<array{path: string, message: string}>
     */
    private function validateLoanType(bool $present, mixed $value): array
    {
        if (
            $present
            && is_string($value)
            && in_array($value, array_keys(LoanCalculator::loanProducts()), true)
        ) {
            return [];
        }

        $options = implode(' | ', array_map(
            static fn (string $loanType): string => '\'' . $loanType . '\'',
            array_keys(LoanCalculator::loanProducts()),
        ));

        // Дефолт invalid_enum_value (en-локаль zod v3): received интерполируется
        // по правилам JS — см. jsCast()
        return [[
            'path' => 'loanType',
            'message' => sprintf('Invalid enum value. Expected %s, received \'%s\'', $options, self::jsCast($value, $present)),
        ]];
    }

    /**
     * Числовое поле: обязан быть number, далее цепочка проверок validate.js —
     * каждая проваленная добавляет отдельную issue по порядку (zod не
     * останавливается на первой).
     *
     * @param list<'int'|'finite'|'positive'> $checks Порядок из validate.js:
     *   amount — int → finite; termMonths — int → finite → positive;
     *   monthlyIncome — finite → positive.
     *
     * @return list<array{path: string, message: string}>
     */
    private function validateNumber(string $path, bool $present, mixed $value, array $checks): array
    {
        if (!$present || (!is_int($value) && !is_float($value))) {
            // invalid_type (en-локаль zod v3): отсутствующее поле (undefined)
            // → «Required», любой другой тип → «Expected number, received …»
            return [[
                'path' => $path,
                'message' => !$present
                    ? 'Required'
                    : 'Expected number, received ' . self::parsedType($value),
            ]];
        }

        $issues = [];

        foreach ($checks as $check) {
            $failed = match ($check) {
                'int' => !self::isJsInteger($value),
                'finite' => !is_finite($value),
                'positive' => $value <= 0,
            };

            if (!$failed) {
                continue;
            }

            $issues[] = [
                'path' => $path,
                'message' => match ($check) {
                    // invalid_type(expected: integer, received: float)
                    'int' => 'Expected integer, received float',
                    // not_finite
                    'finite' => 'Number must be finite',
                    // too_small (inclusive: false)
                    'positive' => 'Number must be greater than 0',
                },
            ];
        }

        return $issues;
    }

    /**
     * fullName — строка; после zod .trim() идут проверки: min(2) → max(120) →
     * regex (§5, п. 4). Все проваленные выписываются по порядку.
     *
     * @return list<array{path: string, message: string}>
     */
    private function validateFullName(bool $present, mixed $value): array
    {
        if (!$present || !is_string($value)) {
            return [[
                'path' => 'fullName',
                'message' => !$present
                    ? 'Required'
                    : 'Expected string, received ' . self::parsedType($value),
            ]];
        }

        $value = self::trimJs($value);

        // Длина в UTF-16-кодовых единицах — как value.length в JS (§7, п. 9):
        // каждый астральный символ (U+10000+) занимает 2 единицы.
        // Некорректная UTF-8 (одинокие суррогаты из JSON-эскейпов \ud83d без
        // пары) здесь даёт расхождение с JS на единицу длины — регексп ниже
        // такие строки всё равно отсекает, вывод issues[0] не меняется.
        $astralCount = preg_match_all(self::ASTRAL_PATTERN, $value);
        $length = mb_strlen($value, 'UTF-8') + (is_int($astralCount) ? $astralCount : 0);

        $issues = [];

        if ($length < 2) {
            $issues[] = ['path' => 'fullName', 'message' => 'String must contain at least 2 character(s)'];
        }

        if ($length > 120) {
            $issues[] = ['path' => 'fullName', 'message' => 'String must contain at most 120 character(s)'];
        }

        // invalid_string(regex): дефолт zod — просто «Invalid»;
        // невалидная UTF-8 тоже не пройдёт (preg_match вернёт false)
        if (!preg_match(self::FULL_NAME_PATTERN, $value)) {
            $issues[] = ['path' => 'fullName', 'message' => 'Invalid'];
        }

        return $issues;
    }

    /**
     * hasCitizenship / consentGiven — строго boolean true (§5, п. 6–7):
     * ни 1, ни строка «true», ни false не подходят (§4.2). Отсутствующее поле
     * (undefined в zod) в literal-проверке даёт тот же дефолт, что и false.
     *
     * @return list<array{path: string, message: string}>
     */
    private function validateLiteralTrue(string $path, bool $present, mixed $value): array
    {
        if ($present && $value === true) {
            return [];
        }

        // invalid_literal
        return [['path' => $path, 'message' => 'Invalid literal value, expected true']];
    }

    /**
     * Number.isInteger из JS (в zod util.isInteger требует и конечность):
     * число конечное и без дробной части. Infinity сюда попадает и отсекается,
     * как в Node-версии.
     */
    private static function isJsInteger(float|int $value): bool
    {
        return is_int($value) || (is_finite($value) && floor($value) === $value);
    }

    /**
     * Целое значение → int для расчёта и будущей записи в БД (§3). До приведения
     * доходят только значения, прошедшие продуктовый диапазон (§5), так что
     * переполнения float→int здесь не бывает; страховка — MAX_SAFE_INTEGER.
     */
    private static function normalizeInt(float|int $value): float|int
    {
        if (is_int($value) || $value > self::MAX_SAFE_INTEGER) {
            return $value;
        }

        return (int) $value;
    }

    /**
     * String.prototype.trim из JS: пробельный набор тот же, что у \s в JS —
     * значительно шире trim() в PHP (NBSP, узкие пробелы, BOM и пр.).
     * Невалидная UTF-8 обнуляет строку — дальше сработает «String must contain
     * at least 2 character(s)», тот же первый текст, что и в Node.
     */
    private static function trimJs(string $value): string
    {
        $trimmed = preg_replace(self::TRIM_PATTERN, '', $value, 2);

        return is_string($trimmed) ? $trimmed : '';
    }

    /**
     * Имя типа в терминологии zod ParsedType (en-локаль) для
     * «Expected …, received …». В JSON-декоде возможны string/null/boolean/array:
     * zod видит [] как «array», а {} как «object», но в PHP оба декодируются как
     * пустой массив и неразличимы — уходим в «array» как в более вероятный.
     */
    private static function parsedType(mixed $value): string
    {
        if (is_array($value)) {
            return array_is_list($value) ? 'array' : 'object';
        }

        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_string($value) => 'string',
            default => 'unknown',
        };
    }

    /**
     * Строковая презентация полученного значения — как ${issue.received}
     * интерполируется в дефолте invalid_enum_value (en-локаль zod v3):
     * строки — без экранирования, booleans — true/false, null → null,
     * undefined → undefined, массивы — как Array.prototype.toString
     * (join(','), вложенные через toString, объекты → [object Object]).
     * Числа — JSON-презентацией PHP; расхождения возможны только у
     * экспоненциальных форм (JS «1e+21» против PHP «1.0E+21») и в контракт
     * ситуации issues[0] не попадают.
     */
    private static function jsCast(mixed $value, bool $present): string
    {
        if (!$present) {
            return 'undefined';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (is_infinite($value)) {
                return $value > 0 ? 'Infinity' : '-Infinity';
            }

            return (string) json_encode($value);
        }

        if (is_array($value)) {
            $parts = [];

            foreach ($value as $element) {
                // null/undefined-элементы в Array.prototype.toString пусты
                $parts[] = $element === null ? '' : self::jsCast($element, true);
            }

            return implode(',', $parts);
        }

        return '[object Object]';
    }
}
