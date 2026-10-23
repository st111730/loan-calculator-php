<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;

/**
 * GET /admin и POST /admin/{id}/status — админка (HTML, не JSON) за HTTP Basic. Заглушки.
 *
 * При переносе учесть:
 *  - Basic-auth (§4.3): realm="admin", ADMIN_USER/ADMIN_PASSWORD, сравнение пароля
 *    через hash_equals (постоянное время — §7, п. 7); без/с неверными кредами —
 *    401 с заголовком WWW-Authenticate: Basic realm="admin";
 *  - GET: HTML-таблица «Заявки на кредиты», фильтр ?status= (неизвестное значение
 *    не даёт ошибки — просто все заявки), сортировка по created_at (новые сверху);
 *  - POST /admin/{id}/status (§4.4): в Node-версии расхождение — форма шлёт
 *    urlencoded, а парсер принимает только JSON, поэтому форма фактически всегда
 *    падает в 400. Зафиксировать одно поведение: JSON {"status": ...} либо парсер
 *    форм. Успех — 302 на /admin, ошибка — 400 «Некорректные данные» (не JSON).
 */
final class AdminController
{
    public function index(Request $request, array $vars): void
    {
        // TODO: Basic-auth §4.3 → выборка заявок (опционально фильтр ?status=) → HTML-рендер
        Response::json(501, ['error' => 'not_implemented'])->send();
    }

    public function changeStatus(Request $request, array $vars): void
    {
        // TODO: Basic-auth §4.3 → проверка $vars['id'] (целое) и body.status
        // (одно из new|in_review|approved|rejected) → UPDATE → 302 на /admin (§4.4)
        Response::json(501, ['error' => 'not_implemented'])->send();
    }
}
