<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;

/**
 * GET /api/health — проверка живости (§4.1).
 *
 * Контракт эндпоинта реализован целиком: 200 {"ok":true}, БД не трогаем,
 * без авторизации.
 */
final class HealthController
{
    public function health(Request $request, array $vars): void
    {
        Response::json(200, ['ok' => true])->send();
    }
}
