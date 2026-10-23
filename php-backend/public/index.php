<?php

declare(strict_types=1);

/**
 * Точка входа PHP-бэкенда — аналог server/src/index.js.
 *
 * Порядок инициализации повторяет Node-версию (backend-api.md, §1):
 * .env → регистрация маршрутов → обработка запроса.
 * Автоприменение migrate.sql на старте (§3) в каркас не входит: БД пока не подключается.
 *
 * Внешние зависимости (FastRoute, Dotenv, автозагрузчик Composer) полностью убраны:
 * роутинг — собственный, в App\Routing\Router; переменные окружения — parse_ini_file.
 */

// index.php объявлен без namespace (глобальный), поэтому внутренние классы
// подключаются require-ми и резолвятся через use — иначе PHP ищет их в
// глобальном пространстве и падает с "Class ... not found".
use App\Http\InvalidJsonException;
use App\Http\Request;
use App\Http\Response;
use App\Routing\Router;

$projectRoot = dirname(__DIR__);

// --- Классы проекта (бывший "autoload": {"psr-4": {"App\\": "src/"}}) ---
require $projectRoot . '/src/Http/InvalidJsonException.php';
require $projectRoot . '/src/Http/Request.php';
require $projectRoot . '/src/Http/Response.php';
require $projectRoot . '/src/Routing/Router.php';
require $projectRoot . '/src/Loan/LoanCalculator.php';
require $projectRoot . '/src/Validation/ValidationResult.php';
require $projectRoot . '/src/Validation/ApplicationValidator.php';
require $projectRoot . '/src/Controller/HealthController.php';
require $projectRoot . '/src/Controller/AdminController.php';
require $projectRoot . '/src/Controller/ApplicationController.php';

// .env кладётся рядом с composer.json; в проде/Compose переменные задаёт окружение (§2).
// Без Dotenv читаем встроенным parse_ini_file: разбирает простые KEY=VALUE (в т.ч. в кавычках),
// комментарии ; и # игнорируются. Значения не перезаписывают реально заданные env.
$envFile = $projectRoot . '/.env';
if (is_file($envFile)) {
    foreach ((array) parse_ini_file($envFile, false, INI_SCANNER_NORMAL) as $key => $value) {
        if (getenv((string) $key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Сквозной обработчик ошибок (§4.5): любое необработанное исключение
// → запись в лог + 500 {"error":"internal_error"}
set_exception_handler(static function (Throwable $e): void {
    error_log('[internal_error] ' . $e->getMessage());
    Response::json(500, ['error' => 'internal_error'])->send();
});

$router = new Router();
require __DIR__ . '/../src/routes.php';

try {
    $router->dispatch(Request::fromGlobals());
} catch (InvalidJsonException) {
    // Битое JSON-тело: Node-версия отдавала на него 500, здесь осознанно 400 (§7, п. 6)
    Response::json(400, ['error' => 'invalid_json'])->send();
}
