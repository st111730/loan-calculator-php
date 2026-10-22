# План редизайна: постоянная тёмная тема + Zustand

Контекст: фронтенд — Vite + React 18 + TypeScript + Tailwind 3.4, структура FSD (`src/shared → entities → features → widgets → pages`).
Решения, принятые ранее: тёмная тема — **постоянная** (без переключателя), админка `server/src/routes/admin.js` — **не трогается**.
Поскольку переключателя нет, `darkMode` в `tailwind.config.js` не нужен — конфиг остаётся без изменений.

---

## Часть 1. Перевод на тёмную тему (выполнено в коде)

Отмечено `[x]` — уже внесено в `src/`, раздел служит документацией того, что менялось.

### 1. Глобальная инфраструктура

- [x] 1.1 `index.html` — добавить `<meta name="color-scheme" content="dark">` и `<meta name="theme-color" content="#020617">`: нативные контролы браузера (скроллбары, инпуты) становятся тёмными, тёмная окраска в интерфейсе ОС.
- [x] 1.2 `src/index.css` — `body`: текст `text-slate-100` вместо `text-slate-900`; фон — однотонный `bg-slate-100` → лёгкий градиент `#020617 → #0f172a → #020617` (slate-950/slate-900).
- [x] 1.3 `src/index.css` — ввести CSS-переменные `--slider-fill: 37 99 235` (blue-600) и `--slider-track: 51 65 85` (slate-700): единая точка правки цветов ползунков.
- [x] 1.4 `src/index.css` — бегунок `.range-filled`: белая рамка `#fff` → рамка `rgb(15 23 42)` (slate-900, в цвет карточки), тень мягче `0 1px 2px black/0.4`.

### 2. Каркас страницы

- [x] 2.1 `src/pages/calculator/ui/CalculatorPage.tsx` — заголовок `text-slate-900` → `text-slate-100`.

### 3. Форма и панель результата (`src/widgets/loan-calculator/ui/LoanCalculator.tsx`)

- [x] 3.1 Карточка формы: `bg-white shadow-sm ring-slate-200` → `bg-slate-900 shadow-lg shadow-black/20 ring-white/10` (радиус `rounded-2xl` сохранён).
- [x] 3.2 Лейблы и заголовки блоков: `text-slate-700` → `text-slate-300` (5 мест).
- [x] 3.3 Текстовые инпуты (ФИО, доход): `rounded-lg border-slate-300` → `rounded-xl border-slate-700 bg-slate-800`; фокус-кольцо `focus:ring-blue-200` → `focus:ring-blue-500/30` (светлое кольцо blue-200 не видно на тёмном).
- [x] 3.4 Ошибки валидации: `text-red-600` → `text-red-400` (4 места) — на тёмном фоне 600-е тона слишком тёмные.
- [x] 3.5 Предупреждение о недостаточном доходе: `text-amber-600` → `text-amber-400`.
- [x] 3.6 Чекбоксы: `border-slate-300 accent-blue-600` → `border-slate-600 accent-blue-500` (2 места).
- [x] 3.7 Ссылка «политики конфиденциальности»: `text-blue-600 hover:text-blue-700` → `text-blue-400 hover:text-blue-300`.
- [x] 3.8 Панель результата (aside): была тёмной `bg-slate-900` на светлом фоне; на тёмном фоне страницы она бы слилась — поэтому `bg-slate-900` → `bg-slate-800` + `ring-1 ring-white/10 shadow-lg shadow-black/20`. Подписи `text-slate-400` и статусы доставок (`bg-emerald-500/15 text-emerald-300`, `bg-red-500/15 text-red-300`) — уже тём-совместимы, оставлены.

### 4. Переиспользуемые контролы (features)

- [x] 4.1 `src/features/loan-type-select/ui/LoanTypeChips.tsx` — неактивный чип: `bg-white text-slate-700 border-slate-300` → `bg-slate-800 text-slate-300 border-slate-600`; активному добавлен `hover:bg-blue-500`; ставка в неактивном `text-slate-500` → `text-slate-400`.
- [x] 4.2 `src/features/loan-amount/ui/AmountSlider.tsx` — лейбл → `text-slate-300`; инпут → `rounded-xl bg-slate-800 border-slate-700 focus:ring-blue-500/30`; **инлайн-градиент трека** `rgb(37 99 235) / rgb(226 232 240)` → `rgb(var(--slider-fill)) / rgb(var(--slider-track))`. Важно: инлайн-`style` не реагирует на Tailwind-классы, поэтому цвет трека — только через CSS-переменные из `index.css`. Подсказка → `text-slate-400`, ошибка → `text-red-400`.
- [x] 4.3 `src/features/loan-term/ui/TermSlider.tsx` — те же замены, плюс бейдж «мес» `text-slate-500` → `text-slate-400`.
- [x] 4.4 `src/features/submit-application/ui/SubmitButton.tsx` — `disabled:bg-slate-300` → `disabled:bg-slate-700 disabled:text-slate-500 disabled:shadow-none`; hover `bg-blue-700` → `bg-blue-500`; добавлена мягкая тень `shadow-lg shadow-blue-600/20`.

### 5. Проверка

- [ ] 5.1 `npm test` — тесты (`credit.test.ts`, `format.test.ts`, `schema.test.ts`) проверяют только логику, стили не касаются.
- [ ] 5.2 `npm run build` — `tsc -b` + vite-сборка (это и есть typecheck, отдельного lint-степа в проекте нет).
- [ ] 5.3 Визуальная проверка: `npm run dev` (Vite на :5173, прокси `/api` → :4000).

---

## Часть 2. Внедрение Zustand вместо локального useState (выполнено)

### Текущее состояние

В `src/widgets/loan-calculator/ui/LoanCalculator.tsx` в одном компоненте смешано всё: черновик заявки (`FormDraft`, 7 полей) в `useState`, словарь touched, синхронная валидация через zod-схему (`src/entities/applicant/model/schema.ts`), расчёты в `useMemo`, статус отправки сведён в хук `src/features/loan-application/model/useLoanCalculatorPush.ts` (`useState` idle/submitting/success/error). Всё это — в 288-строчном файле, и любое расширение (шаги заявки, новые поля) раздует его дальше.

### Почему Zustand

- стейт находится вне React: не нужен Provider/Context и прокидывание пропсов;
- точечные селекторы: перерендер только тех компонентов, чей кусок стейта поменялся;
- экшены (setField, setTouched) описываются декларативно через одну set-функцию;
- опциональные middleware: devtools (отладка), persist (сохранение).

### Атомарные шаги внедрения

- [x] 6.1 `npm install zustand` — `zustand` ^5.0.16 в `package.json` (типы входят в пакет).
- [x] 6.2 Стор создан как `src/store/useCalculatorStore.ts` (вне FSD-слоёв, см. docs/architecture.md): стейт `draft`, `touched`; экшены `setField(field, value)`, `setTouched(field)`. `setLoanType`/`resetDraft` не потребовались: клэмп amount/termMonths при смене продукта остался в JSX-обработчике виджета, сброс черновика в этом спринте не нужен. Тип переименован в `CalculatorDraft` и живёт в файле стора.
- [x] 6.3 `LoanCalculator.tsx`: `useState` черновика и touched убраны, стейт читается через селекторы, экшены стора вызываются в обработчиках. Синхронная zod-валидация и `useMemo`-расчёт остались в виджете без изменений.
- [x] 6.4 `pushStatus`/`pushError`/`resetPush` перенесены в стор; статус в панели результата читается селекторами виджета (SubmitButton остался props-компонентом согласно 6.5). Сам fetch (POST `/api/applications`, чтение `issues[0].message`) остался в `useLoanCalculatorPush` — статус он меняет через `useCalculatorStore.getState()`, т.к. обновления происходят вне рендера.
- [x] 6.5 Презентационные компоненты (`LoanTypeChips`, `AmountSlider`, `TermSlider`, `SubmitButton`) **не** подключены к стору — они принимают props от widget; FSD-границы и переиспользование сохранены.
- [x] 6.6 Персональные данные: `persist` middleware не включён — ФИО/доход не сохраняются в localStorage (152-ФЗ).
- [ ] 6.7 Опционально: подключить `devtools` middleware для отладки потока заявки.

### Альтернатива (если стейт нужен именно под форму)

`react-hook-form` + `zodResolver`: заточен под формы (uncontrolled поля, встроенный touched/errors), а zod-схема applicant'а уже есть — подключается почти без дублирования. Zustand выбран как более универсальный вариант: он заодно закроет статус отправки, в то время как react-hook-form требует ещё решения для серверного состояния.

---

## Часть 3. Вне скоупа (по решению)

- Админка `server/src/routes/admin.js` (server-rendered HTML со встроенным inline-CSS) остаётся светлой.
- Сервер (`server/src/*`) не затрагивается — расчёты дублируются намеренно (фронт: `src/entities/loan/model/credit.ts`, бэк: `server/src/lib/loan.js`), менять формулы не требуется.
