# Миграция остальных страниц на Primer — UX-спецификация (по аналогии с редизайном макета)

Статус: проект → реализация оркестратором в субагентах.
База: [`front-main-layout-home-settings-redesign.md`](front-main-layout-home-settings-redesign.md) — макет, «Главная», «Настройки» и UI-kit (+11 Gh-компонентов) уже сделаны.
Проект: `/home/leo/www/unitman-front`. Эта спецификация описывает ОБЩИЕ паттерны миграции и инвентарь страниц; детальный UX каждой страницы не меняется — меняется визуальный слой и состояния.

---

## 1. Цель и рамки

Перевести все оставшиеся страницы с Material/`--app-*`-стилей на дизайн-систему Primer
(Gh-компоненты + токены `src/styles/primer-tokens.scss`) **без изменения бизнес-логики**:
сторы (`src/modules/*/store/**`), сервисы (`src/modules/*/services/**`), websocket-подписки
(`src/modules/*/model/websocket/**`), утилиты URL-фильтров — не трогать. Рефакторится только
слой `view/**` (+ при необходимости локальные вычисляемые данные в самой view).

Вне рамок: `src/pages/UiKitPreview.vue` (уже Primer), `AccountSettings/**` и `Home.vue` (сделаны), `App.vue`/`AppHeader`/`AppSidebar` (сделаны).

---

## 2. Общие паттерны миграции

### P1. Страница-список (юниты, проекты, хранилища, аккаунты, сессии, workspaces)
```
GhPageHeader (title + description)
  actions: [GhCounter количества] [GhButton default icon="sync" «Обновить»] [GhButton primary icon="plus" «Создать»]
Фильтр-бар: GhInput (search, size sm) + GhSelect (проект/статус/роль) + GhButton default «Фильтры»
Фильтр-чипы: активные фильтры → GhLabel variant="neutral" + клик-крестик снятия
Контент: карточки (P2) или GhTable
Состояния: loading → GhSkeleton (геометрия карточек, CLS=0); error → GhBanner danger + «Повторить»;
           empty → GhEmptyState (icon, title, описание, primary-действие «Создать …»)
```
- URL-синхронизация фильтров (существующие `*FiltersUrl.ts`) — сохранить как есть.
- Хоткеи страницы: `/` — фокус в поиск, `c` — «Создать», `R` — обновить (через `useHotkeys.ts`, учитывать конфликт с `g`-последовательностью — флаг `ozhidaetsyaVtorayaKlavishaPosledovatelnosti()`).

### P2. Карточка элемента (UnitCard/ProektCard/HranilishaCard/PolzovatelCard/WorkspaceCard)
- Основа: `GhBox` (header = имя + статус-бейдж + `GhActionMenu`; body = метаданные; footer = действия).
- Шапка: имя semibold 14–16, ellipsis + `q-tooltip`; вторичный текст `--fgColor-muted` 12.
- Метаданные: пары «подпись: значение» — подпись muted 12, значение 14; id/ветки/пути — `.gh-mono`.
- Меню карточки: `GhActionMenu` (items с octicon; деструктивные — `danger: true` + `separatorBefore`).
- Ссылки на связанные сущности — `--fgColor-accent`, hover underline.

### P3. Формы (в `GhDialog` или на странице)
- Поля: `GhFormField` (label/caption/error) + `GhInput`/`GhSelect`/`GhSwitch`.
- Футер: `GhButton default «Отмена»` + `GhButton primary «Сохранить»` (`loading`, disabled до dirty).
- Ошибка валидации/бэкенда: `GhBanner danger` внутри формы (не Notify).
- Успех: `GhBanner success` (автоскрытие 4с) или закрытие диалога + баннер на странице.
- `Esc` — закрыть диалог (нативно), фокус возвращается на триггер.

### P4. Деструктивные действия
- `GhDialog` подтверждения: текст последствия + `GhButton danger`.
- Критичные (удаление проекта/хранилища/токена, сброс подготовки юнита): ввод имени сущности,
  кнопка disabled до точного совпадения (как уже сделано в токенах).

### P5. Статусные бейджи → `GhLabel` (icon + variant)
| Статус | GhLabel variant | Octicon |
|---|---|---|
| running / активный / healthy | `success` | `play` |
| busy / сборка-запуск / in-progress | `accent` | `sync` |
| prepared / готов | `neutral` | `check` |
| stopped / paused / архив | `neutral` | `pause` |
| error / broken / unhealthy | `danger` | `x-circle` |
| warning / degraded / просрочка | `attention` | `alert` |
Прогресс задачи (busy) — `q-tooltip` с деталями, как в текущем `UnitStatusBadge`. `role="status"` сохранить.

### P6. Иконки: Material → Octicon (типовые замены)
`menu→three-bars`, `home→home`, `groups→people`, `inbox→repo`, `list→list-unordered`,
`star→rocket`, `smart_toy→cpu`, `forum→comment-discussion`, `dashboard→rows`, `settings→gear`,
`sync/refresh→sync`, `add→plus`, `edit→pencil`, `delete→trash`, `copy→copy`, `visibility→eye`,
`visibility_off→eye-closed`, `close→x`, `check_circle→check-circle`, `error→x-circle`,
`warning→alert`, `arrow_upward→arrow-up`, `more_vert→kebab-horizontal`, `search→search`,
`person→person`, `key→key`, `shield→shield`, `link→link`, `open_in_new→external-link`,
`play_arrow→play`, `pause→pause`, `stop→square`, `terminal→terminal`, `cloud→cloud`,
`database→database`, `webhook→webhook` (нет — использовать `broadcast`), `build→tools`,
`save→download`, `logout→sign-out`.
Все иконки — только через `GhIcon` (тип `OcticonName`, ловится на `vue-tsc`).

### P7. Токены: `--app-*` → Primer (таблица замен в стилях view)
| Было (`--app-*`) | Стало (Primer) |
|---|---|
| `--app-surface` | `--bgColor-default` |
| `--app-surface-muted` | `--bgColor-muted` |
| `--app-text` | `--fgColor-default` |
| `--app-text-secondary` | `--fgColor-muted` |
| `--app-primary` | `--fgColor-accent` |
| `--app-positive` | `--fgColor-success` |
| `--app-negative` | `--fgColor-danger` |
| `--app-warning` | `--fgColor-attention` |
| `--app-border` | `--borderColor-default` |
| `--app-radius-*` | `--borderRadius-small/medium/large/full` |
| `--app-font-size-*` | `--fontSize-caption/body/title` |
| `--app-space-*` | 4/8/12/16px (или готовые паддинги GhBox) |
Точный список — сверять с `src/styles/_tokens.scss`. Хардкод-цвета запрещены (проверка grep-ом).
`color-mix(...)` для фонов статусов → готовые `--bgColor-*-muted`.

### P8. Уведомления
- `Notify.create` → по возможности inline `GhBanner` в контексте формы/страницы.
- Глобальные события (успешная команда юнита и т.п.) — допустим `$q.notify` с Primer-цветами
  (brand уже обновлён), но это временно: помечать комментарием `// TODO(primer): GhBanner`.

---

## 3. Инвентарь страниц по подзадачам (файлы не пересекаются)

### M1. Модуль account (кроме сделанного AccountSettings)
- `src/modules/account/view/Login.vue`, `LoginLayout.vue` (проверить использование; если не используется — не трогать, отметить)
- `src/modules/account/view/SpisokPolzovateleiDlyAdministrirovaniya.vue`
- `src/modules/account/view/SpisokPolzovateleiDlyAdmininstrirovaniya/**` (EditPage, Forma* ×6, Spisok/* ×7)
Особенности: Login — центрированный `GhBox` 25rem, `GhInput` email/password, `GhButton primary block`,
ошибка — `GhBanner danger` в карточке (не Notify); «уже вошёл» — отдельный `GhBox` с `GhButton danger` «Выйти».
Список пользователей — P1/P2; формы редактирования — P3; смена роли — `GhSelect`; удаление — P4.

### M2. Модуль unit: юниты
- `src/modules/unit/view/SpisokUnitov.vue`
- `src/modules/unit/view/Unit/**` (SpisokUnitov/* ×15, Konfig/* ×3, Forma* ×3)
Особенности: режимы списка (cards/details/workspace) и `UnitDetailsPane` — сохранить логику
(`SpisokUnitovViewStore` не трогать); тулбар фильтров → P1; карточки → P2; статус-бейджи → P5;
`OknoVipolnenihZadachRunnera`/`PanelShagovHoda`-подобные окна прогресса — `GhDialog` + список шагов
(иконка статуса шага по P5, время `.gh-mono`); `FormaSozdaniyaUnita`/`FormaZapolneniyaPeremenihUnita`/
`FormaDeistviyUnita` — P3; `Konfig/*` — P3 + `.gh-mono` для путей/имён volume.

### M3. Модуль unit: проекты и хранилища
- `src/modules/unit/view/SpisokProektov.vue`, `src/modules/unit/view/Proekti/**` (SpisokProektov/* ×7 + разделы ×10)
- `src/modules/unit/view/SpisokHranilish.vue`, `src/modules/unit/view/Hranilisha/**` (×9)
Особенности: списки — P1/P2; `LayoutStraniziRedaktirovaniya` (страница проекта с табами) —
`GhPageHeader` + `GhUnderlineNav` (по аналогии с Настройками, deep-link `?tab=`); разделы проекта
(webhook-и, переменные, пользователи, защищённые ветки, типы томов) — P1/P3; `OknoVipolnenihZadachRunnera` —
диалог прогресса (см. M2); удаление проекта — P4 с вводом имени.

### M4. Модуль opencode
- `src/modules/opencode/view/**` (OpenCodeLayout, OpenCodeRazdelToggle, SpisokSessiyOpenCode,
  ChatOpenCode, PanelShagovHoda, UvedomleniyaOpenCode, Forma* ×3, Workspace/* ×3)
Особенности: `OpenCodeLayout` — `GhPageHeader` + `GhUnderlineNav` (Сессии/Workspaces) или сохранение
собственного переключателя в Primer-стиле; чат `ChatOpenCode` — пузыри сообщений на Primer-поверхностях
(user: `--bgColor-accent-muted`, assistant: `--bgColor-muted`), инпут — `GhInput` + `GhButton primary`
icon `arrow-up`; `PanelShagovHoda` — шаги по P5 + `.gh-mono`; `UvedomleniyaOpenCode` — панель на
`GhBox`/`GhBanner`; `WorkspaceStatusBadge` — P5; формы — P3. Realtime (centrifuge) не трогать.

### M5. Модуль app (прочее) + runner
- `src/modules/app/view/Initialization.vue` — `GhPageHeader` + `GhBox` с формой инициализации (P3),
  убрать `v-html` → `GhBanner`.
- `src/modules/app/view/NotFound.vue`, `Unauthorized.vue` — `GhEmptyState` (иконка `x-circle`/`shield`,
  заголовок, описание, `GhButton` «На главную»).
- `src/modules/runner/view/SostoyanieDefoltnogoRunnera.vue` — `GhPageHeader` + статус-карточка `GhBox`:
  бейдж P5 (healthy/unhealthy), опрос статуса (стор) не трогать, error → `GhBanner danger` + «Повторить».

---

## 4. Правила для всех субагентов

1. Прочитать §2 этой спецификации + §2 (принципы) базовой; соблюдать стиль существующих Gh-компонентов
   (`<script setup lang="ts">`, `withDefaults`, транслит внутренних имён, Primer-токены в CSS).
2. Бизнес-логику (сторы, сервисы, websocket, URL-фильтры) не менять; рефакторить только view-слой.
3. Все строки — через i18n-ключи (ru/en зеркально, `yarn check:i18n` должен проходить); существующие
   ключи переиспользовать, новые добавлять по namespace модуля (`unit.*`, `opencode.*`, `runner.*`, `account.*`).
4. Никаких новых npm-зависимостей и хардкод-цветов; иконки — только `GhIcon`/`OcticonName`.
5. Состояния обязательны: loading (GhSkeleton), error (GhBanner + retry), empty (GhEmptyState), extreme (ellipsis+tooltip).
6. A11y: `aria-label` у иконочных кнопок, `role="status"` у бейджей, фокус-ловушка диалогов (нативная), skip-link не ломать.
7. Хоткеи страниц — через `useHotkeys.ts`; не перехватывать ввод в полях и `g`-последовательности.
8. Тёмная тема: проверять каждый экран в `body--dark` (токены должны подтягиваться автоматически).

## 5. Порядок исполнения

- Волна 1 (параллельно): M1 + M2 + M3 + M4 + M5 — файлы не пересекаются.
- Финал: `yarn build` (vue-tsc), `yarn check:i18n`, grep на `--app-`/хардкод-цвета в изменённых файлах,
  прогон светлой/тёмной темы, клавиатуры, ширины 375px; точечные фиксы.
