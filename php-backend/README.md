# PHP-бэкенд (каркас)

Каркас нового PHP-бэкенда для миграции с Node.js/Express. Контракт API лежит в `docs/backend-api.md`; бизнес-логика частично перенесена: расчёт аннуитета (§6) в `App\Loan\LoanCalculator` и валидация заявок (§5) в `App\Validation\ApplicationValidator`, обе покрыты PHPUnit-тестами. Ещё не перенесены: БД (§3) и админка (§4.3–4.4) — `POST /api/applications` пока не пишет заявки в Postgres, а возвращает результаты расчёта (см. «Текущее состояние маршрутов»).

## Структура

```
php-backend/
├── composer.json          # только для тестов: require-dev: phpunit + PSR-4 (App\ → src/); на рантайме не используется
├── phpunit.xml            # конфиг PHPUnit (bootstrap vendor/autoload.php, testsuite unit)
├── .env.example           # переменные окружения (§2)
├── public/
│   └── index.php          # единая точка входа (аналог server/src/index.js)
├── src/
│   ├── routes.php         # таблица маршрутов
│   ├── Routing/Router.php # собственный роутер (без внешних зависимостей)
│   ├── Http/              # Request / Response / InvalidJsonException
│   ├── Loan/
│   │   └── LoanCalculator.php # продукты (§6.1) + аннуитет (§6.2), зеркало server/src/lib/loan.js
│   ├── Validation/
│   │   ├── ApplicationValidator.php # валидация заявки (§5), зеркало server/src/lib/validate.js: порядок и тексты issues
│   │   └── ValidationResult.php     # данные или issues (семантика zod safeParse) для тела ошибки §4.2
│   └── Controller/        # HealthController, ApplicationController, AdminController
└── tests/
    ├── Loan/
    │   └── LoanCalculatorTest.php # эталоны §6.3 + граничные случаи округления (autoload-dev: App\Tests\)
    └── Validation/
        └── ApplicationValidatorTest.php # порядок и тексты ошибок §5 (base: дефолты zod, продуктовые: русские)
```

## Зависимости

На рантайме внешних зависимостей **нет** — код не обращается ни к `vendor/`, ни к Composer:

- роутинг — собственный `App\Routing\Router` (ранее предполагался `nikic/fast-route`);
- чтение `.env` — встроенный `parse_ini_file` в `public/index.php` (ранее предполагался `vlucas/phpdotenv`);
- автозагрузка классов — явные `require` в `public/index.php` (PSR-4 из `composer.json` остался только для PHPUnit-тестов).

Библиотеку валидации сознательно не подключаем: тексты ошибок (русские) и порядок `issues` (§5) проще повторить вручную по zod-семантике, чем настраивать под них `symfony/validator`.

## Запуск

```bash
cd php-backend
php -S 0.0.0.0:4000 -t public public/index.php   # эквивалент composer start — скрипт из composer.json
```

Внешних зависимостей нет (`vendor/` и Composer для запуска не нужны) — достаточно PHP 8.1+.
`composer install` остаётся нужным только для `composer test` (PHPUnit).

Тесты расчёта (PHPUnit требует установленного PHP 8.1+ и `composer install`):

```bash
composer test           # phpunit — расчёт §6.3 + граничные случаи округления §6.2, порядок/тексты ошибок валидации §5
```

Порт 4000 закреплён как у Node-версии, поэтому dev-прокси Vite (по умолчанию `http://localhost:4000`, см. `vite.config.ts`) продолжит работать без правок.

Проверка живости:

```bash
curl http://localhost:4000/api/health   # {"ok":true}
```

Проверка приёма заявки (расчёт без записи в БД; эталон §6.3 — потребительский, 500 000 ₽ / 24 мес):

```bash
curl -X POST http://localhost:4000/api/applications \
  -H "Content-Type: application/json" \
  -d '{"loanType":"consumer","amount":500000,"termMonths":24,"fullName":"Иванов Иван Иванович","monthlyIncome":50000,"hasCitizenship":true,"consentGiven":true}'
# {"monthlyPayment":"25988.49","totalRepaid":"623723.76","totalInterest":"123723.76"}

# Ошибка валидации 400 (issues[0].message читает фронтенд, §7):
curl -X POST http://localhost:4000/api/applications \
  -H "Content-Type: application/json" \
  -d '{"loanType":"consumer","amount":300000,"termMonths":24,"fullName":"Иванов Иван Иванович","monthlyIncome":50000,"hasCitizenship":true,"consentGiven":true}'
# {"error":"validation_error","issues":[{"path":"amount","message":"Сумма для «Потребительский»: от 50000 до 5000000 ₽"}]}
```

Переменные окружения — см. `.env.example`. `PORT` учитывается при самостоятельном запуске; в composer-скрипте зафиксирован 4000. В проде nginx (схема `docker-compose.prod.yml`) так же проксирует `/api` и `/admin` на API — деплой-модель не меняется (§1), CORS не нужен.

## Текущее состояние маршрутов

| Маршрут | Метод | Состояние |
|---|---|---|
| `/api/health` | GET | реализован: `200 {"ok":true}` (§4.1) |
| `/api/applications` | POST | реализован расчётно-валидационный конвейер: валидация §5 + расчёт §6 → `200` с расчётными полями; **201 + строка из БД — после переноса §3** (см. «Решения» ниже) |
| `/admin` | GET | заглушка `501` (HTML + Basic-auth — при переносе, §4.3) |
| `/admin/{id}/status` | POST | заглушка `501` (§4.4) |

## Решения, зафиксированные каркасом

- Несуществующие пути → `404 {"error":"not_found"}`, неподходящий метод → `405 {"error":"method_not_allowed"}` (в Node-версии это была штатная HTML-страница Express без спецификации — §4.5).
- Битое/пустое JSON-тело → `400 {"error":"invalid_json"}` (Node отдавал 500; в §7, п. 6 это отмечено как дефект — 400/500 разделены).
- Успех `POST /api/applications` — промежуточный: `200 {"monthlyPayment","totalRepaid","totalInterest"}` деньгами-строками (2 знака, как NUMERIC в §4.2); после переноса §3 станет `201` со строкой `applications`. Фронтенд при 2xx тело не читает (§7), контракт UI не страдает.
- Валидация заявок (§5) без библиотек валидации: базовые проверки — англоязычные дефолты zod v3 en-локали (как в Node-версии, «Required», «Expected number, received string», …), продуктовые диапазоны — русские тексты; порядок `issues`: базовые по порядку полей схемы, дальше `amount`, затем `termMonths`.
- Любое другое необработанное исключение → `500 {"error":"internal_error"}` + запись в лог (§4.5).
- `Content-Type: application/json; charset=utf-8`, `JSON_UNESCAPED_UNICODE` — русские тексты отдаются как есть.

## Порядок переноса логики

1. Валидация заявки с русскими текстами и порядком `issues` (§5) — **перенесено**: `App\Validation\ApplicationValidator` (+ `ValidationResult`) и `tests/Validation/ApplicationValidatorTest.php`; базовые тексты — дефолты zod v3 en-локали (как в Node), продуктовые — общие русские тексты фронта и Node; порядок superRefine (базовые по полям схемы → amount → termMonths) воспроизведён (§7, п. 8).
2. Продукты + аннуитет с порядком округлений из §6 (округление «половинка вверх» как в JS `Math.round`, сверить с эталонными примерами §6.3) — **перенесено**: `App\Loan\LoanCalculator` + `tests/Loan/LoanCalculatorTest.php` (эталоны §6.3 и округление совпадают с `server/src/lib/loan.js` бит-в-бит; округление эмулировано вручную, встроенный `round()` PHP не используется из-за пре-раундинга).
3. БД: клиент Postgres + решение по миграциям (сейчас только идемпотентный `CREATE IF NOT EXISTS` — §3); после переноса успех `POST /api/applications` заменяется на `201` со строкой заявки (§4.2).
4. Админка: Basic-auth через `hash_equals`, HTML-таблица, эндпоинт смены статуса — и окончательное решение по JSON vs urlencoded (§4.4).
