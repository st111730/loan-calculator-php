<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Loan\LoanCalculator;
use App\Validation\ApplicationValidator;

/**
 * POST /api/applications — приём кредитной заявки (docs/backend-api.md, §4.2).
 *
 * Конвейер Node-версии (server/src/routes/applications.js), где уже реализованы
 * первые два шага:
 *  1) валидация тела — App\Validation\ApplicationValidator (§5): порядок базовых
 *     и продуктовых проверок и тексты issues как в zod-схеме Node (§7, п. 8);
 *  2) расчёт аннуитета — App\Loan\LoanCalculator (§6): порядок округлений §6.2,
 *     клиентским числам не доверяем — платы/переплата пересчитываются только здесь.
 *
 * Промежуточный контракт до переноса БД (§3 — отдельный шаг):
 *  - 400 {"error":"validation_error","issues":[{"path":…,"message":…}]} — по любой
 *    проваленной проверке; issues[0].message — текст для фронтенда (§4.2);
 *  - 200 {"monthlyPayment":…,"totalRepaid":…,"totalInterest":…} — результаты
 *    расчёта; деньги — строки с двумя знаками, как NUMERIC-колонки в §4.2
 *    (сериализация node-postgres). После переноса §3 успех станет 201 со строкой
 *    applications (id, created_at, status). Фронтенд при 2xx тело не читает (§7),
 *    поэтому interim-тело не ломает контракт UI.
 *
 * InvalidJsonException (битое/пустое тело) перехватывает точка входа
 * public/index.php → 400 {"error":"invalid_json"} (§7, п. 6).
 */
final class ApplicationController
{
    public function __construct(
        private readonly ApplicationValidator $validator = new ApplicationValidator(),
        private readonly LoanCalculator $calculator = new LoanCalculator(),
    ) {
    }

    public function create(Request $request, array $vars): void
    {
        // Тело запроса доступно через $request->json(): битое тело выбрасывает
        // InvalidJsonException → 400 {"error":"invalid_json"} в точке входа.
        $result = $this->validator->validate($request->json());

        if (!$result->isSuccess()) {
            Response::json(400, [
                'error' => 'validation_error',
                'issues' => $result->issues,
            ])->send();

            return;
        }

        $data = $result->data;
        assert($data !== null, 'isSuccess() гарантирует заполненные данные');

        // Расчёт не доверяет числам клиента: всё считается заново (§6)
        $calc = $this->calculator->calculate($data['loanType'], $data['amount'], $data['termMonths']);

        // Деньги — строки с двумя знаками, как NUMERIC-колонки §4.2
        Response::json(200, [
            'monthlyPayment' => sprintf('%.2F', $calc['monthlyPayment']),
            'totalRepaid' => sprintf('%.2F', $calc['totalRepaid']),
            'totalInterest' => sprintf('%.2F', $calc['totalInterest']),
        ])->send();
    }
}
