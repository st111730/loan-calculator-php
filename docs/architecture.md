# Архитектура

Кредитный калькулятор: фронтенд — **Vite + React 18 + TypeScript + Tailwind CSS 3.4**, структура **FSD** (`src/shared → entities → features → widgets → pages`); сервер — отдельный пакет `server/` (Express + `pg`, plain ESM JavaScript, без TypeScript и тестов).

Документ описывает состояние после редизайна: **постоянная тёмная тема** и **миграция стейта на Zustand** (см. [Plan.md](../Plan.md)).

## Слои фронтенда

Импорты направлены только вниз по слоям; точка входа — `main.tsx → App.tsx → pages/calculator → widgets/loan-calculator`. Алиас `@/ → src/` настроен в трёх местах, которые нужно держать синхронными: `tsconfig.json` (`paths`), `vite.config.ts` (`resolve.alias`), `jest.config.cjs` (`moduleNameMapper`).

| Слой | Содержимое |
| --- | --- |
| `src/shared` | `config/loanProducts.ts` — кредитные продукты (ставки, лимиты, сроки); `lib/format.ts` — форматирование чисел и валют |
| `src/entities` | `loan` — аннуитетный расчёт (`model/credit.ts`); `applicant` — zod-схема заявки (`model/schema.ts`) |
| `src/features` | Контролы формы: `loan-type-select`, `loan-amount`, `loan-term`, `submit-application`; `loan-application/model/useLoanCalculatorPush.ts` — fetch отправки заявки |
| `src/widgets` | `loan-calculator/ui/LoanCalculator.tsx` — форма и панель результата |
| `src/pages` | `calculator/ui/CalculatorPage.tsx` |
| `src/store` | Глобальный стейт приложения `useCalculatorStore.ts`. Вне FSD-иерархии: не принадлежит ни одному слою, подключается по точечным селекторам |

## Глобальный стейт: useCalculatorStore

Раньше черновик заявки и статус отправки жили в `useState` виджета `LoanCalculator` и хука `useLoanCalculatorPush`. После миграции состояние вынесено в zustand-стор — `src/store/useCalculatorStore.ts` (`zustand ^5.0.16`, типы `CalculatorDraft`, `CalculatorTouched`, `PushStatus` объявлены там же).

Почему zustand: стейт живёт вне React (не нужен Provider и проп-дриллинг), обновления точечные по селекторам, экшены описываются декларативно через одну `set`-функцию. Middleware `persist` **сознательно не подключён**: персональные данные нельзя хранить в localStorage (152-ФЗ).

### Состояние

| Поле | Тип | Назначение |
| --- | --- | --- |
| `draft` | `CalculatorDraft` | черновик заявки: `loanType`, `amount`, `termMonths`, `fullName`, `monthlyIncome`, `hasCitizenship`, `consentGiven`. Стартовые значения — экспорт `initialDraft` |
| `touched` | `CalculatorTouched` | какие поля пользователь трогал; вместе с zod-валидацией управляет показом ошибок после blur/change |
| `pushStatus` | `PushStatus` | статус отправки: `idle \| submitting \| success \| error` |
| `pushError` | `string \| undefined` | сообщение об ошибке отправки |

### Экшены

| Экшен | Что делает |
| --- | --- |
| `setField(field, value)` | обновляет одно поле черновика |
| `setTouched(field)` | помечает поле «тронутым» |
| `startedPush()` | `pushStatus → submitting`, очищает `pushError` |
| `pushSucceeded()` | `pushStatus → success` |
| `pushFailed(error)` | `pushStatus → error`, записывает `pushError` |
| `resetPush()` | возвращает `pushStatus → idle` (кнопка «Повторить» после ошибки) |

### Кто использует стор

- **`src/widgets/loan-calculator/ui/LoanCalculator.tsx`** — читает селекторами `draft`, `touched`, `pushStatus`, `pushError` и экшены `setField` / `setTouched` / `resetPush`. Локально в виджете остаются: синхронная валидация (`loanApplicationSchema.safeParse(draft)`), клэмп `amount`/`termMonths` под лимиты продукта при смене типа кредита и `useMemo`-расчёт платежа.
- **`src/features/loan-application/model/useLoanCalculatorPush.ts`** — сохраняет за собой только fetch (`POST /api/applications` с разбором `issues[0].message` из ответа сервера), но не стейт: статус меняет через `useCalculatorStore.getState()` (`startedPush` → `pushSucceeded` / `pushFailed`) — обновления происходят вне рендера, поэтому хук не нужен.
- **`src/store/useCalculatorStore.test.ts`** — тесты экшенов и начального состояния.

**Не подключены к стору** — презентационные контролы `LoanTypeChips`, `AmountSlider`, `TermSlider`, `SubmitButton`: они получают данные и колбэки через props от виджета, что сохраняет переиспользование и FSD-границы.

## Тёмная тема

Тема **постоянная**, без переключателя: вся палитра тёмная «из коробки», переключателя нет, поэтому `darkMode` в `tailwind.config.js` не требуется, а классы `dark:` не используются.

- [index.html](../index.html): `<meta name="color-scheme" content="dark">` и `<meta name="theme-color" content="#020617">` — нативные контролы браузера тоже тёмные.
- [src/index.css](../src/index.css): `:root { color-scheme: dark }` и CSS-переменные — единые точки правки палитры:
  - `--bg-top` / `--bg-mid` — градиент фона страницы (slate-950 → slate-900);
  - `--slider-fill` / `--slider-track` — цвета трека ползунков. Трек заливается инлайн-стилем, который не реагирует на Tailwind-классы, поэтому цвет задаётся только через переменные CSS;
  - `.range-filled::-webkit-slider-thumb` / `::-moz-range-thumb` — бегунок с рамкой в цвет карточки и мягкой тенью.
- Палитра компонентов (`LoanCalculator.tsx` и features): карточки `bg-slate-900` / `bg-slate-800` + `ring-1 ring-white/10`, текст `text-slate-100/300/400`, ошибки `text-red-400`, предупреждение «доход недостаточен» `text-amber-400`, чекбоксы `border-slate-600 accent-blue-500`, акцент `blue-*` (кнопка и активный чип `bg-blue-600 hover:bg-blue-500`, фокус-кольца `ring-blue-500/30`).
- Админка (`server/src/routes/admin.js`, server-rendered HTML с inline-CSS) вне скоупа редизайна — остаётся светлой.

## Инвариант: дублирование кредитной логики

Ставки/лимиты продуктов и аннуитетная формула существуют в **двух сознательно синхронизируемых вручную копиях**:

- фронтенд: `src/shared/config/loanProducts.ts` + `src/entities/loan/model/credit.ts`;
- сервер: `server/src/lib/loan.js` — сервер пересчитывает заявку и не доверяет клиентским числам.

Валидация зеркалится так же: `src/entities/applicant/model/schema.ts` ↔ `server/src/lib/validate.js`. Сервер возвращает `issues[0].message`, который фронтенд показывает пользователю. Изменили ставку, лимит или формулу — правьте обе стороны.

## Запуск

Фронтенд (после `npm install`):

| Команда | Что делает |
| --- | --- |
| `npm run dev` | Vite на :5173, прокси `/api` → `http://localhost:4000` (переопределяется `VITE_API_PROXY`) |
| `npm test` | Jest + jsdom; один файл — `npm test -- credit.test` |
| `npm run build` | `tsc -b && vite build`; это и есть typecheck (отдельного lint-степа в проекте нет) |

Сервер (`server/`): `npm install`, затем `npm run dev` (`node --watch src/index.js`). Требуется PostgreSQL (переменные `PG*`, см. `.env.example`; для Docker Compose — `docker-compose.yml` для разработки и `docker-compose.prod.yml` для прода). Схема БД накатывается автоматически при старте из `server/src/migrate.sql` (идемпотентные `CREATE ... IF NOT EXISTS`; созданные ранее БД команды `ALTER` не получают).

API: `POST /api/applications` (публичный), `GET /api/health`, `/admin` — server-rendered HTML за HTTP Basic (`ADMIN_USER` / `ADMIN_PASSWORD`).
