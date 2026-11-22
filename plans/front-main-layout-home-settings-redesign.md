# Редизайн основного макета, «Главной» и «Настроек» — UX-спецификация (Primer)

Статус: проект, ждёт согласования → реализация в Code-режиме.
Стек: Vue 3 + TypeScript + Quasar 2, дизайн-система GitHub Primer.
Затронуто: `unitman-front/src/App.vue`, `unitman-front/src/modules/app/view/Home.vue`,
`unitman-front/src/modules/account/view/AccountSettings.vue`, `unitman-front/src/components/ui/*`.

---

## 1. Контекст и проблемы текущего решения

**Макет (`src/App.vue`)**
- Material-хедер (`q-header elevated bg-primary`, синий #2f6fb0, тени, ripple) — выбивается из
  Primer-эстетики, уже реализованной в ui-kit (`Gh*`-компоненты + `src/styles/primer-tokens.scss`).
- Язык — два `q-radio` прямо в хедере (шумно, не по Primer).
- Сайдбар: Material-иконки, нет группировки «обычные / администрирование», нет счётчиков,
  нет сворачивания до иконок.
- Пункт меню «Настройки» ведёт на `/init` (инициализация системы), а реальные настройки
  аккаунта (`/account/settings`) доступны только через аватар — путаница.
- Нет глобального поиска и клавиатурной навигации.

**«Главная» (`src/modules/app/view/Home.vue`)**
- Карточки с цветными Material-заголовками; статистика total/active/deleted без иерархии и действий.
- Loading — голый спиннер (CLS, нет скелетонов), ошибка рендерится через `v-html`
  (риск XSS, нет кнопки «Повторить»).

**«Настройки» (`src/modules/account/view/AccountSettings.vue`)**
- Плоский список карточек без навигации: пароль, ник, токены, OpenCode — всё на одной длинной странице.
- Ошибки — «голые» div, успех никак не подтверждается, нет защиты от потери правок.

---

## 2. Дизайн-принципы

1. **Primer-эстетика:** плоские поверхности, hairline-бордеры `1px solid var(--borderColor-default)`,
   radius 6 (контролы) / 12 (диалоги, меню), тени только у всплывающих слоёв.
2. **Иерархия действий:** primary (зелёный `GhButton variant="primary"`) — одно главное действие
   экрана; `default` — остальные; `danger` — только с подтверждением.
3. **Keyboard-first:** `/` и `Cmd/Ctrl+K` — поиск/палитра, `g`-комбо для разделов, `?` — шпаргалка;
   подсказки `<kbd>` в тултипах и плейсхолдерах.
4. **Прогрессивное раскрытие:** детали — в тултипы, `GhActionMenu`, `GhDialog`; на первом плане —
   статус, автор/владелец, счётчики.
5. **Все состояния:** loading (скелетоны, повторяющие геометрию, CLS=0), error (`GhBanner` + retry),
   empty (`GhEmptyState` с действием), extreme (ellipsis + `q-tooltip`).
6. **Оптимистичные микро-действия:** тема и язык применяются мгновенно, сохранение — фоном.

---

## 3. Информационная архитектура навигации

Сайдбар, две группы (состав повторяет текущее меню `App.vue`, исправлена путаница с «Настройками»):

| Группа | Пункт | Маршрут | Видимость |
|---|---|---|---|
| — | Главная | `/` | все |
| — | Юниты | `/unit/list` | все |
| — | ИИ-помощник ▸ Сессии | `/opencode/sessions` | все |
| — | ИИ-помощник ▸ Workspaces | `/opencode/workspaces` | все |
| — | Настройки | `/account/settings` | все |
| Администрирование | Аккаунты | `/account/list` | ROLE_ADMIN |
| Администрирование | Репозитории | `/unit/repositories` | ROLE_ADMIN |
| Администрирование | Проекты | `/unit/projects` | ROLE_ADMIN |
| Администрирование | Инициализация | `/init` | ROLE_ADMIN |

Изменения против текущего меню:
- «Настройки» больше не указывает на `/init`; `/init` переименован в «Инициализация» и уходит
  в admin-группу.
- «Настройки» (`/account/settings`) — отдельный пункт для всех пользователей.

---

## 4. Основной макет (AppShell)

### 4.1 Wireframe

```
┌──────────────────────────────────────────────────────────────────────────┐
│ HEADER 48px, bg --bgColor-default, border-bottom 1px --borderColor-muted │
│ [☰]  ⚗ Unitman   [ 🔍 Поиск…                                    / ]     │
│                     [☾] [RU|EN] [🔔 ³] │ [ (U) ▾ ]                      │
├────────────────┬─────────────────────────────────────────────────────────┤
│ SIDEBAR 232px  │  CONTENT: .gh-page (max-width 1280px, центр, pad 24/16) │
│                │                                                         │
│  🏠 Главная    │   Заголовок страницы ─────────────────── [Действия]     │
│  🚀 Юниты      │   ─────────────────────────────────────────────────     │
│  🤖 ИИ-помощник│                                                         │
│    ├ Сессии    │                    <router-view/>                       │
│    └ Workspaces│                                                         │
│  ⚙ Настройки   │                                                         │
│  ────────────  │                                                         │
│  АДМИНИСТРИРОВАНИЕ (caption 12px muted)                                 │
│  👥 Аккаунты   │                                                         │
│  📥 Репозитории│                                                         │
│  📁 Проекты    │                                                         │
│  🛠 Инициализация                                                       │
│                │                                                         │
│  [«] Свернуть  │                                                         │
└────────────────┴─────────────────────────────────────────────────────────┘
```

### 4.2 Хедер (48px)

| Зона | Элемент | Реализация |
|---|---|---|
| Слева | ☰ (только <768px) | `GhButton variant="invisible" icon="three-bars"` — открыть drawer |
| Слева | Логотип: `beaker` + «Unitman» | `GhIcon` 16 + текст semibold 16, клик → `/` |
| Центр | Кнопка поиска (стиль инпута, ширина ≤320px): иконка `search`, текст muted «Поиск…», справа `GhKbd` `/` | открывает `GhCommandPalette` (этап 2); до его реализации — фокус на фильтр текущей страницы |
| Справа | Тема | `GhButton invisible` icon `moon`/`sun`, tooltip «Тёмная тема \`, подсказка kbd» |
| Справа | Язык | `GhSegmentedControl` [RU, EN] size sm; на время `loaderIzmemeniyaYazika` — disabled |
| Справа | Уведомления | `GhButton invisible icon="bell"` + `GhCounter` (непрочитанные); клик — панель `UvedomleniyaOpenCode` |
| Справа | Аккаунт | `GhAvatar 32` + `triangle-down`; меню: шапка (email mono + `GhLabel` роли), разделитель, «Настройки» `gear`, «Помощь» `question`, разделитель, «Выйти» `sign-out` |

Хедер — theme-aware (светлый в светлой теме, `#010409` в тёмной), никаких `elevated`-теней.

### 4.3 Сайдбар (232px, сворачивается до 48px)

- Фон `--bgColor-default`, граница справа `--borderColor-muted`.
- Пункты: высота 32px, radius 6, иконка 16 + подпись 14; hover `--bgColor-neutral-muted`.
- Активный пункт: фон `--bgColor-neutral-muted`, текст semibold, левый акцент 2px
  `--gh-accent-coral` (перекликается с активной вкладкой `GhUnderlineNav`).
- Группа «Администрирование»: caption 12px `--fgColor-muted`, semibold, без uppercase.
- Счётчики (`GhCounter`) — этап 2 (нужен API: активные юниты и т.п.).
- Сворачивание: кнопка внизу `[«]`; в свёрнутом виде — только иконки + `q-tooltip` с названием
  и `GhKbd` `\`. Состояние — в `appSettings` (расширить: `sidebarCollapsed`).
- Мобильный (<768px): `q-drawer` overlay с бэкдропом, закрытие по клику на фон/Esc/navigации.

### 4.4 Контентная область

- `.primer-theme` переносится с демо-страницы `/ui-kit` на корень `#app` — Primer-палитра
  применяется ко всему приложению (токены аддитивны, старые страницы не ломаются).
- Контейнер страницы `.gh-page`: max-width 1280px, центр, padding 24px (16px на мобильных).
- Паттерн «шапка страницы» — компонент `GhPageHeader` (см. §7.1).
- Skip-link «Перейти к содержимому» (visually-hidden, фокус по Tab) — a11y.
- `q-page-scroller` — вместо Material fab: `GhButton default icon="arrow-up"`.

### 4.5 Горячие клавиши (глобальные)

Композабл `src/utils/useHotkeys.ts`; игнорировать ввод в `input/textarea/[contenteditable]` и зажатые модификаторы.

| Клавиши | Действие |
|---|---|
| `/` | Поиск/палитра (фокус фильтра страницы до этапа 2) |
| `Cmd/Ctrl + K` | Палитра команд (этап 2) |
| `g h` | Главная |
| `g u` | Юниты |
| `g p` | Проекты (admin) |
| `g a` | Аккаунты (admin) |
| `g r` | Репозитории (admin) |
| `g s` | Настройки |
| `\` | Свернуть/развернуть сайдбар |
| `?` | Диалог «Горячие клавиши» (шпаргалка: действие + `GhKbd`) |
| `Esc` | Закрыть меню/диалоги (нативно Quasar) |

### 4.6 Состояния макета

- **Загрузка сессии:** на месте аватара `GhSkeleton variant="circle" 32`.
- **Мобильный drawer:** бэкдроп `rgba(1,4,9,0.5)`, фокус в первый пункт меню, `focus-trap` силами `q-drawer`.
- **Ошибка роутинга/403/404:** существующие `Unauthorized.vue` / `NotFound.vue` — переверстить
  на `GhEmptyState` (иконка, заголовок, действие «На главную») — вне объёма, но совместимо.

### 4.7 Маппинг макета на компоненты

| Зона | Компонент |
|---|---|
| Каркас | `q-layout` / `q-drawer` / `q-page-container` (скелет Quasar, стили Primer) |
| Хедер | новый `src/modules/app/view/AppHeader.vue` (композиция Gh-компонентов) |
| Сайдбар | новый `GhSideNav` + обёртка `src/modules/app/view/AppSidebar.vue` |
| Аватар | новый `GhAvatar` |
| Меню аккаунта | `q-menu` + `GhActionMenu`-стилистика (items + шапка через slot) |
| Язык/тема | новые `GhSegmentedControl`, `GhButton invisible` |
| Счётчики | существующий `GhCounter` |
| Уведомления | существующий `UvedomleniyaOpenCode` (триггер — колокольчик) |
| Шпаргалка клавиш | `GhDialog` + `GhKbd` |

---

## 5. Страница «Главная» (`/`, `src/modules/app/view/Home.vue`)

Данные: `storeDashborda` (`DashboardStore.ts`) — `statistikaPoProektam: ProjectStatistics[]`
(`projectName`, `total`, `active`, `deleted`), геттер `obshayaStata`, флаги загрузки/ошибки.

### 5.1 Wireframe

```
Главная                                                [ ⟳ Обновить  R ]
Сводка по юнитам и проектам

┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│ ⚙ Всего  │ │ ▶ Активн.│ │ 🗑 Удалён│ │ 📁 Проек.│   ← 4 плитки (GhBox)
│   128    │ │   96     │ │   12     │ │   24     │      кликабельны
└──────────┘ └──────────┘ └──────────┘ └──────────┘

┌──────────────────────────────────────────────────────────┐
│ Проекты (24)                        [ 🔍 Фильтр…  / ]    │
├──────────────────────────────────────────────────────────┤
│ Проект     Всего   Активные   Удалённые   Активность  ⋯ │
│ frontend     32       28          4       ▓▓▓▓░ 88%   ⋯ │
│ payments     12        3          9       ▓░░░░ 25%   ⋯ │
│ …                                                        │
├──────────────────────────────────────────────────────────┤
│ Обновлено 14:32                                          │
└──────────────────────────────────────────────────────────┘
```

### 5.2 Структура

1. **`GhPageHeader`** — заголовок «Главная», описание «Сводка по юнитам и проектам»;
   actions: `GhButton default icon="sync"` «Обновить» (`loading` = флаг стора, tooltip с `GhKbd` `R`).
2. **Плитки-показатели** (grid: 4 колонки → 2 → 1), каждая — `GhBox padding="sm"`, кликабельна:
   | Плитка | Иконка | Значение | Переход |
   |---|---|---|---|
   | Всего юнитов | `package` (neutral) | `obshayaStata.total` | `/unit/list` |
   | Активные | `play` (success) | `obshayaStata.active` | `/unit/list` + фильтр статуса (утилита `unitFiltersUrl.ts`) |
   | Удалённые | `trash` (danger) | `obshayaStata.deleted` | `/unit/list` + фильтр статуса |
   | Проекты | `project` (accent) | кол-во проектов | `/unit/projects` (admin; не-admin — плитка скрыта) |
   Число — 24px semibold, класс `.gh-mono`; подпись — 12px muted.
3. **Таблица проектов** — `GhBox` (header: «Проекты» + `GhCounter` + `GhInput`-фильтр size sm
   icon `search`, клиентский фильтр по названию) → внутри `GhTable`:
   - Колонки: Проект (semibold, `repo` 16px muted), Всего / Активные / Удалённые (right, mono,
     сортируемые), Активность (мини progress-bar 60px + `%` mono), действия (`GhActionMenu`:
     «Открыть юниты» `rocket`, «Открыть проект» `gear` — admin).
   - Клик по строке → `/unit/list` с фильтром по проекту.
   - Длинные названия — `ellipsis` + `q-tooltip` с полным именем.

### 5.3 Состояния

| Состояние | Отображение |
|---|---|
| Loading | 4 × `GhSkeleton` плиток (высота итоговой плитки) + `GhBox` с 5 скелетон-строками; CLS=0 |
| Error | `GhBanner variant="danger"`: «Не удалось загрузить статистику» + причина + в actions `GhButton` «Повторить» (заменяет текущий `v-html` — XSS-safe); плитки показывают `—` |
| Empty (нет проектов) | `GhEmptyState icon="project"` «Пока нет проектов» + primary «Создать проект» → `/unit/projects` + secondary «Перейти к юнитам» |
| Extreme | >50 проектов — таблица без пагинации со скроллом страницы; ellipsis имён |

### 5.4 Горячие клавиши страницы

| Клавиши | Действие |
|---|---|
| `R` | Обновить статистику |
| `/` | Фокус в фильтр таблицы |

---

## 6. Страница «Настройки» (`/account/settings`)

Паттерн GitHub Settings: заголовок + горизонтальные табы `GhUnderlineNav`, активный таб —
в query `?tab=profile|security|tokens|opencode|appearance` (глубокие ссылки, кнопки Back/Forward).

### 6.1 Wireframe

```
Настройки
Управление учётной записью и доступами

[ 👤 Профиль | 🛡 Безопасность | 🔑 Токены | 🤖 OpenCode | 🎨 Внешний вид ]
──────────────────────────────────────────────────────────────────

ПРОФИЛЬ (активный таб)
┌──────────────────────────────────────────────┐
│ Ник                                          │
│ [ leo ________________________ ]             │
│ Email: leo@example.com   (mono, read-only)   │
│                                              │
│              [ Отменить ]  [ Сохранить ]     │  ← primary, disabled пока нет правок
└──────────────────────────────────────────────┘
```

### 6.2 Табы

| Таб | Иконка | Содержимое |
|---|---|---|
| Профиль | `person` | Ник (форма), Email (read-only, mono) |
| Безопасность | `shield` | Смена пароля |
| Токены | `key` | Личные токены (`SpisokLichnihTokenov`) |
| OpenCode | `cpu` | Реквизиты OpenCode (`SpisokRekvizitovOpenCode`) |
| Внешний вид | `paintbrush` | Тема, язык |

### 6.3 Секции

**Профиль** — `GhBox` (header «Ник»):
- `GhFormField` label «Ник» → `GhInput`; ошибка бэкенда — `GhBanner danger` внутри бокса
  (замена текущего текстового div).
- Email: строка «Email» + значение `.gh-mono` + `GhLabel variant="neutral"` «read-only».
- Footer: `GhButton primary «Сохранить»` (disabled до изменений, `loading`), `GhButton invisible «Отменить»` (сброс).
- Успех: `GhBanner variant="success"` «Изменения сохранены», автоскрытие 4с.

**Безопасность** — `GhBox` «Смена пароля»:
- `GhInput type="password"` + новый слот `#append` — переключатель видимости (`eye`/`eye-closed`),
  `autocomplete="new-password"`.
- Caption-подсказка требований к паролю (12px muted).
- Ошибка — `GhBanner danger`; успех — `GhBanner success` + очистка поля + внимание:
  «Используйте новый пароль при следующем входе» (`GhBanner attention`).

**Токены** — `GhBox` «Личные токены доступа»:
- Header-actions: `GhButton primary icon="plus" «Новый токен»` → `GhDialog` с существующей
  `FormaDobavleniyaLichnogoTokena`.
- `GhTable`: имя (mono), создан, истекает, действия `GhActionMenu`: «Копировать» `copy`,
  «Удалить» `trash` (danger).
- Удаление — `GhDialog`-подтверждение с вводом имени токена (mistake-proofing), кнопка
  `GhButton variant="danger"`, пока имя не совпало — disabled.
- После создания: показать значение один раз — `GhBanner success` + mono-блок + «Копировать»
  (классический UX GitHub PAT).

**OpenCode** — `GhBox` «Реквизиты OpenCode»: существующие списки/формы на Gh-компонентах
(`GhTable` + `GhDialog` + `GhActionMenu`), без изменения логики сторов.

**Внешний вид** — `GhBox`:
- Тема: `GhSegmentedControl` [☀ Светлая, ☾ Тёмная] — применяется мгновенно
  (`$q.dark.set` + `saveAppSettings`), оптимистично.
- Язык: `GhSegmentedControl` [RU, EN] — `authStore.izmeniyYazik`; на время загрузки — disabled.
- Caption: «Настройки применяются к текущему браузеру».

### 6.4 Состояния

| Состояние | Отображение |
|---|---|
| Первичная загрузка | `GhSkeleton` боксов, повторяющих геометрию активного таба |
| Ошибка загрузки настроек | `GhBanner danger` + «Повторить» |
| Несохранённые правки | «Сохранить» disabled → enabled; при смене таба с несохранёнными правками — подтверждение `GhDialog` «Отменить изменения?» |
| Ошибка сохранения | `GhBanner danger` с текстом от бэкенда (замена `oshibkaOtBackenda`-div) |

### 6.5 Маппинг

| Элемент | Компонент |
|---|---|
| Табы | существующий `GhUnderlineNav` (items с иконками) |
| Карточки секций | существующий `GhBox` (header/body/footer) |
| Поля | существующий `GhInput` + новый `GhFormField` |
| Сообщения | новый `GhBanner` |
| Таблицы токенов/реквизитов | существующий `GhTable` + `GhActionMenu` |
| Диалоги | существующий `GhDialog` |
| Переключатели темы/языка | новый `GhSegmentedControl` |

---

## 7. Новые компоненты ui-kit (`src/components/ui/`)

Все компоненты: корневой класс `primer`, токены из `primer-tokens.scss`, поддержка тёмной темы,
экспорт в `ui/index.ts`, типы в `ui/types.ts`, демо в `src/pages/UiKitPreview.vue`.

### 7.1 GhPageHeader
Шапка страницы: заголовок + описание + действия.
- **Props:** `title: string`, `description?: string`
- **Slots:** `actions` (справа), `default` (под шапкой)
- **Геометрия:** h1 20px semibold; description 14px muted; отступ снизу 16px; flex, actions справа.
- **A11y:** `h1` (на страницах без собственного h1).

### 7.2 GhAvatar
- **Props:** `src?: string`, `name: string`, `size?: 16|20|24|32|40` (default 24), `square?: boolean`
- **Состояния:** ошибка загрузки/нет `src` → инициалы (первые 2 символа, uppercase), фон
  `--bgColor-accent-muted`, текст `--fgColor-accent`; пока грузится — фон `--bgColor-muted`.
- **A11y:** `role="img"`, `aria-label=name`.

### 7.3 GhSideNav
Вертикальная навигация сайдбара.
- **Types:**
  ```ts
  type GhSideNavItem = { id: string; label: string; icon?: OcticonName; to?: string;
                         counter?: number | null; disabled?: boolean };
  type GhSideNavGroup = { id: string; label?: string; items: GhSideNavItem[] };
  ```
- **Props:** `groups: GhSideNavGroup[]`, `modelValue: string` (id активного), `collapsed?: boolean`, `ariaLabel?: string`
- **Emits:** `update:modelValue`, `select(item)`
- **Поведение:** активный — фон neutral-muted + semibold + левый акцент 2px `--gh-accent-coral`;
  `collapsed` → только иконки + `q-tooltip`; заголовок группы — caption 12px muted.
- **A11y:** `nav` + `aria-orientation="vertical"`, `aria-current="true"` на активном пункте.
- **База:** `q-list`/`q-item` + стили Primer.

### 7.4 GhSelect
- **Props:** `modelValue: string|number|null`, `options: {value, label, description?}[]`,
  `label?`, `placeholder?`, `size?: GhControlSize`, `disabled?`, `clearable?`, `error?`, `errorMessage?`
- **Emits:** `update:modelValue`
- **База:** `q-select outlined dense` + меню в стиле `GhActionMenu` (radius 12, тень floating).
- **A11y:** нативная combobox-семантика q-select + `aria-label`.

### 7.5 GhSegmentedControl
- **Props:** `modelValue: string`, `options: {value, label?, icon?}[]`, `size?: "sm"|"md"`, `ariaLabel?`
- **Emits:** `update:modelValue`
- **Геометрия:** контейнер border 1px + radius 6 + padding 2; сегмент h 24(sm)/28(md), radius 4;
  выбранный — фон `--bgColor-default`, border `--borderColor-default`, тень small, font-medium.
- **A11y:** `role="radiogroup"`, сегменты `role="radio"` + `aria-checked`, стрелки ←/→.

### 7.6 GhBanner
- **Props:** `variant?: "accent"|"success"|"attention"|"danger"` (default `accent`), `title?: string`,
  `dismissible?: boolean`, `icon?: OcticonName` (по умолчанию: `info`/`check-circle`/`alert`/`stop`)
- **Emits:** `dismiss`
- **Slots:** `default` (текст), `actions` (кнопки справа)
- **Геометрия:** radius 6, bg `--bgColor-*-muted`, border `--borderColor-*-muted`, иконка и текст
  `--fgColor-*`; padding 8×16.
- **A11y:** `role="alert"` для danger/attention, `role="status"` для остальных.

### 7.7 GhSkeleton
- **Props:** `variant?: "text"|"circle"|"rect"` (default `text`), `width?`, `height?`, `animation?: "wave"|"pulse"|"none"`
- **База:** обёртка над `q-skeleton` с цветом Primer (`--bgColor-neutral-muted`).
- **A11y:** `aria-hidden="true"`; родителю — `aria-busy="true"`.

### 7.8 GhEmptyState
- **Props:** `icon?: OcticonName` (default `inbox`), `title: string`, `description?: string`
- **Slots:** `actions`
- **Геометрия:** по центру, padding 40px 16px; иконка 40px muted; title 16 semibold;
  description 14 muted; actions — строка кнопок по центру.

### 7.9 GhSwitch
- **Props:** `modelValue: boolean`, `label?`, `description?`, `disabled?`, `ariaLabel?`
- **Emits:** `update:modelValue`
- **Геометрия:** трек 32×16, radius full; off — `--bgColor-neutral-muted` + border; on —
  `--bgColor-success-emphasis`; thumb 12/16 белый.
- **База:** `q-toggle` dense + рестайл. **A11y:** `role="switch"`, `aria-checked`.

### 7.10 GhKbd
- **Slot:** текст клавиши. Рендерит `<kbd class="gh-kbd">`: mono 11px, padding 1px 4px,
  border `--borderColor-default`, radius 4, bg `--bgColor-muted`, inset-тень снизу.
- Используется в тултипах, плейсхолдерах, шпаргалке.

### 7.11 GhFormField
- **Props:** `label: string`, `caption?: string`, `error?: string`, `required?: boolean`, `controlId?: string`
- **Slots:** `default` (контрол)
- **Геометрия:** label 14 semibold; caption 12 muted; error 12 danger + иконка `alert` 12.
- **A11y:** `label[for=controlId]`, на контроле `aria-invalid`, `aria-describedby`.

### 7.12 Дополнение GhInput
- Новый слот `#append` (рендер в `q-field__append`) — для переключателя видимости пароля.
- Обратная совместимость: существующий API не меняется.

### 7.13 GhCommandPalette (этап 2, отдельная задача)
- Оверлей по `/` и `Cmd/Ctrl+K`: инпут + фильтруемый список (разделы, действия), группы,
  навигация ↑/↓/Enter, недавние элементы (localStorage), футер с подсказками `GhKbd`.
- **Props:** `modelValue: boolean`, `commands: {id, label, group?, icon?, to?, action?}[]`
- **Emits:** `update:modelValue`, `select(command)`

---

## 8. Глобальные изменения

1. **Тема:** `.primer-theme` — на корневой `#app` в `App.vue` (сейчас только `/ui-kit`).
2. **Бренд Quasar (`src/main.ts`)** — привести к Primer, чтобы Notify/диалоги старых страниц
   не выбивались: `primary #0969da`, `secondary #57606a`, `accent #bf8700`, `positive #1a7f37`,
   `negative #cf222e`, `info #0969da`, `warning #9a6700`, `dark #0d1117`.
3. **Утилитарные классы** (`primer-tokens.scss`): `.gh-page` (контейнер 1280px), `.gh-mono`
   (`--fontStack-monospace` — счётчики, email, токены), `.gh-visually-hidden` (skip-link).
4. **`appSettings`** — добавить `sidebarCollapsed: boolean` (персистентность сайдбара).
5. **i18n** — новые ключи `app.header.*`, `app.sidebar.*`, `dashboard.*`, `settings.tabs.*`,
   `settings.appearance.*`, `common.*` (ru/en), проверка `yarn check:i18n`.
6. **Хоткеи** — композабл `src/utils/useHotkeys.ts` + карта клавиш; диалог-шпаргалка
   `src/modules/app/view/HotkeysDialog.vue`.
7. **Структура:** хедер и сайдбар вынести из `App.vue` в `src/modules/app/view/AppHeader.vue`
   и `AppSidebar.vue`; `App.vue` — только композиция + глобальные хоткеи.

---

## 9. План внедрения

| Этап | Объём |
|---|---|
| 1. UI-kit | 11 новых компонентов (§7.1–7.11) + слот `#append` в `GhInput` + утилитарные классы; демо в `UiKitPreview.vue` |
| 2. AppShell | `App.vue` → `AppHeader` + `AppSidebar` + `GhSideNav`, глобальные хоткеи, `.primer-theme` на всё приложение, brand-цвета |
| 3. Главная | `Home.vue`: плитки + `GhTable` + все состояния |
| 4. Настройки | `AccountSettings.vue`: табы + 5 секций на Gh-компонентах, подтверждения, баннеры |
| 5. Этап 2 (опционально) | `GhCommandPalette`, счётчики в сайдбаре, счётчик уведомлений в колокольчике |

Проверка после каждого этапа: `yarn build` (vue-tsc), `yarn check:i18n`, ручной прогон
светлой/тёмной темы, клавиатурная навигация, мобильная ширина 375px.
