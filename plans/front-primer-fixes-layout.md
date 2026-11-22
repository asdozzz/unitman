# Фиксы вёрстки Primer — выравнивание контролов, отступы форм, чекбоксы

Статус: багфикс-спецификация по результатам приёмки миграции (9 замечаний).
Проект: `/home/leo/www/unitman-front`. База: [`front-pages-primer-migration.md`](front-pages-primer-migration.md), [`front-main-layout-home-settings-redesign.md`](front-main-layout-home-settings-redesign.md).
Бизнес-логику (сторы/сервисы/websocket) не трогать — только view/kit-слой и стили.

## 0. Карта замечаний → корневые причины

| # | Замечание | Причина |
|---|---|---|
| 1 | Проекты: позиционирование фильтров, отступы селекта сортировки | F1, F3, F5 |
| 2 | Репозитории: те же проблемы фильтров | F1, F3 |
| 3 | В формах разная высота текстовых полей и селектов | F1, F2 |
| 4 | Вертикальные формы: слишком большие отступы между полями | F1, F2 |
| 5 | Чекбокс: плывут отступы | F4 |
| 6 | Редактирование проекта: кнопки по горизонтали не соответствуют полям | F6 |
| 7 | Воркспейсы: чекбокс «только активные» не по горизонтали с полем | F1, F3, F4 |
| 8 | Форма сессии: огромные отступы шапки; кнопка рядом с «Опишите задачу» плывёт | F2, F7 |
| 9 | Аккаунты: плывут фильтры | F1, F3 |

## F1. Убрать резерв `q-field__bottom` в GhInput/GhSelect (корневая причина №1)

Quasar резервирует ~20–22px под сообщение ошибки даже когда его нет → каждый
[`GhInput`](../unitman-front/src/components/ui/GhInput.vue)/[`GhSelect`](../unitman-front/src/components/ui/GhSelect.vue)
выше номинала, контролы в строках и формах «плывут».

- `q-input`/`q-select` внутри GhInput/GhSelect: добавить `hide-bottom-space`.
- Ошибку показывать собственным элементом под контролом (как в GhFormField):
  `<p class="gh-input__error">` / `gh-select__error` — 12px, `--fgColor-danger`, иконка `alert` 12,
  `margin: 4px 0 0`; рендерить при непустом `errorMessage`.
- Итоговая высота контрола строго `--control-height-sm/md/lg` (28/32/40) без скрытых добавок.
- Совместимость: публичный API (props/emits) не меняется; ошибки в GhFormField продолжают работать
  (там свой рендер, дубли не возникают — у GhInput errorMessage в bottom больше не выводится).
- Проверить: высота GhInput == высота GhSelect == высота GhButton при одном `size`.

## F2. Стандарт вертикальных форм и диалогов

- Вертикальный гэп между `GhFormField` в форме: **16px** (не больше); между группами/секциями — 24px.
- `GhDialog`: body padding 16px; первый `GhFormField` вплотную к шапке (без доп. margin сверху);
  шапка диалога — padding 8px 8px 8px 16px (как сейчас), без дополнительных обёрток с вертикальными
  паддингами. «Огромные отступы шапки» на форме сессии — следствие F1 + двойных обёрток: после F1
  переаудировать [`FormaSozdaniyaSessii.vue`](../unitman-front/src/modules/opencode/view/FormaSozdaniyaSessii.vue).
- Raw-контролы внутри GhFormField запрещены: [`FormaSozdaniyaWorkspace.vue`](../unitman-front/src/modules/opencode/view/FormaSozdaniyaWorkspace.vue)
  использует `q-input type="textarea" autogrow` напрямую → расширить GhInput поддержкой
  `type="textarea"` (+ prop `autogrow?: boolean`, `rows?: number`) в стиле Primer
  (та же рамка/радиус/фокус-ринг; высота авто, min-height 64px), заменить raw q-input.
- `GhSwitch` в списке юнитов (форма сессии): высота строки 32px, gap списка 8px (сейчас 10px + padding 4px — выровнять).

## F3. Стандарт фильтр-бара (проекты, репозитории, аккаунты, воркспейсы, юниты)

Единые правила для всех `*FilterBar.vue`:
- Одна строка: `display:flex; flex-wrap:wrap; align-items:center; gap:8px;` — все контролы `size="sm"` (28px).
- Поиск: `flex: 1 1 220px; min-width: 160px`.
- Селект сортировки: ширина по содержимому `flex: 0 1 auto; min-width: 160px; max-width: 240px`;
  внутри `.q-field__native` — `display:flex; align-items:center;` (текст без зазора со стрелкой,
  стрелка `.q-field__append` вплотную: `padding: 0 8px; height: auto`).
- Переключатель «Только активные» — **GhCheckbox** (F4), не GhButton с иконкой check.
- Подпись «обновлено …» и счётчик «N проектов» — caption 12px muted, `align-items: center` со строкой.
- Строка статус-чипов: чипы `GhLabel` (высота 20px) + счётчики, вертикальное выравнивание по центру.

## F4. Новый kit-компонент GhCheckbox

Raw `q-checkbox` в формах/фильтрах даёт «плывущие» отступы (п.5, п.7).

- Файл: `src/components/ui/GhCheckbox.vue`, экспорт в `index.ts`, тип в `types.ts`, демо в `UiKitPreview.vue`.
- Props: `modelValue: boolean`, `label?: string`, `description?: string`, `disabled?: boolean`,
  `indeterminate?: boolean`, `ariaLabel?`; emits: `update:modelValue`.
- Геометрия: бокс 16×16, `border-radius: var(--borderRadius-small)`, рамка `--borderColor-default`;
  отмеченный — фон `--fgColor-accent` (Primer-синий), галочка белая; строка: min-height 32px,
  `display:flex; align-items:center; gap:8px;` label 14px; description 12px muted под label.
- База: `q-checkbox` dense + рестайл под Primer (без дефолтных Quasar-отступов: `padding: 0`).
- Заменить все raw `q-checkbox` в view-слое (воркспейсы «только активные», формы юнитов/проектов —
  grep `q-checkbox` по `src/modules`).

## F5. GhPageHeader: счётчик рядом с заголовком

На скриншоте «Проекты» бейдж «4» висит выше строки заголовка.

- Добавить в [`GhPageHeader.vue`](../unitman-front/src/components/ui/GhPageHeader.vue) prop
  `counter?: number | null` и/или слот `title-append`: рендер после h1 в строке
  `display:flex; align-items:center; gap:8px;` — счётчик `GhCounter` выровнен по центру заголовка.
- На страницах (проекты, репозитории, хранилища, аккаунты, юниты, сессии, воркспейсы) перенести
  счётчик из actions/произвольных мест в `counter`/`title-append`.
- `align-items` шапки оставить `flex-start` (кнопки actions — по верхней кромке заголовка).

## F6. Кнопки форм: единое выравнивание

- Форма внутри `GhBox`: действия рендерить в слоте `#footer` (muted-фон, border-top, padding 8px 16px,
  `justify-content: flex-end; gap: 8px`) — горизонтальные инсеты кнопок совпадают с полями (16px).
  Перевести [`EditProjectForm.vue`](../unitman-front/src/modules/unit/view/Proekti/EditProjectForm.vue)
  (сейчас `.edit-project__actions` с margin-top) и аналогичные формы на `#footer`.
- Форма без GhBox: `.form-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:16px; }`
  внутри того же контейнера паддингов, что и поля.
- Запретить смешение: в одной форме кнопки либо все в footer-баре, либо все в потоке — не по-разному.

## F7. Композер чата OpenCode

[`ChatOpenCode.vue`](../unitman-front/src/modules/opencode/view/ChatOpenCode.vue), `.opencode-chat__composer-row`:
- `align-items: flex-end` — кнопка отправки прижата к нижней кромке поля (как в GitHub), при
  однострочном вводе визуально по центру; gap 8px.
- Поле — GhInput без bottom-резерва (F1); кнопка 32px (`size="md"`).
- Шапка чата (название сессии): padding 8px 12px, без доп. вертикальных margin у title-row —
  аудит и устранение лишних отступов сверху/снизу.

## Файловые скоупы (не пересекаются)

- **K (kit):** `src/components/ui/**` (GhInput, GhSelect, GhCheckbox — новый, GhPageHeader, GhDialog-аудит), `src/pages/UiKitPreview.vue`.
- **P-unit:** `src/modules/unit/view/**` — фильтр-бары (Proekt/Hranilisha/Unit), формы (Edit/AddProjectForm, Forma* юнитов, Konfig/*), GhPageHeader-счётчики, GhCheckbox-замены.
- **P-account:** `src/modules/account/view/SpisokPolzovateleiDlyAdmininstrirovaniya/**` — фильтр-бар, формы, счётчик.
- **P-opencode:** `src/modules/opencode/view/**` — SpisokWorkspace (чекбокс), FormaSozdaniyaSessii/Workspace, FormaPrivyazkiUnitaKSessii, ChatOpenCode (композер + шапка), счётчики.
- **P-app-runner:** `src/modules/app/view/**`, `src/modules/runner/view/**` — счётчики/формы, если затронуты.

## Порядок

- Волна 1: K (kit) — всё остальное зависит от GhCheckbox/F1.
- Волна 2 (параллельно): P-unit + P-account + P-opencode + P-app-runner.
- Финал: `yarn build`, `yarn check:i18n`, grep `q-checkbox`/`hide-bottom-space`-проверка, скрин-прогон
  страниц из замечаний 1–9 в светлой/тёмной теме и на 375px.

## Критерии приёмки

1. На всех фильтр-барах поиск/чекбокс/селект/кнопки одной высоты (28px), выровнены по центру строки.
2. Селект сортировки: текст и стрелка без зазора, ширина по содержимому.
3. В формах GhInput, GhSelect, GhTextarea одной высоты (32px) на одном `size`; между полями 16px.
4. Чекбоксы — только GhCheckbox, строка 32px, бокс 16px, без «плывущих» отступов.
5. Кнопки форм — в footer-баре GhBox, инсеты совпадают с полями.
6. Счётчик страницы выровнен по центру заголовка.
7. Композер чата: кнопка по нижней кромке поля; шапка чата без лишних отступов.
