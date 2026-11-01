# План: отвязать тома (`unitman_volumes`) от переменных конфига

> **Статус: реализовано.** Все пункты разделов 2–8 выполнены, проверки (Psalm, PHPUnit 127 тестов,
> deptrac по всем модулям, `check-openapi`) — зелёные, [`docs/openapi.json`](docs/openapi.json) перегенерирован.
> Фактические имена методов отличаются от черновых: [`makeVolumeCollectionFromZapros()`](src/Unitman/Business/Model/Unit.php:557)
> и [`proveritFaylyVolumes()`](src/Unitman/Business/UseCase/Unit/ZapolnitPeremenieUnitaUseCase.php:50);
> отдельный `poluchitVolumeKonfig()` не понадобился — `RunnerAdapter` берёт состав томов
> из `VolumeValue` юнита, которые уже собраны по конфигу.

## 1. Цель и принципы

Тома и переменные — **разные сущности**:

- `variables` (`VariableValue`) — значения параметров конфига, передаются в Runner как список `{Id, Value, Type}`.
- `unitman_volumes` (`VolumeValue`) — каталоги контейнера, в которые монтируется zip-архив из медиатеки.

`dir` — **уникальный ключ тома** внутри юнита. Клиент загружает архив в медиатеку и присылает
`{dir, fileId}`; имя тома берётся из конфига, а `fileId` уходит в activity раннера.

Итог: убрать `variable` из `unitman_volumes` и любые вычисления `fileId` из `variableValues`.
Выбранный вариант API — поле `volumes` в существующей команде `POST /api/unit/zapolnitPeremenie`
(переиспользуем событие `PeremenieUnitaZapolneni`), `dir` обязан присутствовать в конфиге юнита.

## 2. Что меняется в контракте

### 2.1 `unitman_volumes` в конфиге

```yaml
unitman_volumes:
  - dir: /data/db
    name: "БД"
  - dir: /var/www/web
    name: "Фронт"
```

`variable` больше не поддерживается. `dir` и `name` обязательны, `dir` уникален.

### 2.2 `POST /api/unit/zapolnitPeremenie`

```json
{
  "id": "uuid",
  "values": [{"id": "APP_TITLE", "value": "unit", "type": "string"}],
  "volumes": [
    {"dir": "/data/db", "fileId": "uuid-архива"},
    {"dir": "/var/www/web", "fileId": null}
  ]
}
```

- `volumes` опционально; отсутствие поля = тома не трогаем.
- `dir` обязан быть объявлен в `unitman_volumes` юнита (`unit.volumes.dir_not_found`).
- `fileId` = `null` явно очищает файл тома; отсутствие ключа `fileId` = оставить текущее значение.
- `name` в запросе не принимается — берётся из конфига.

### 2.3 Ответы `poluchitPeremenieUnita` / `poluchitKonfigIzHranilisha`

Схема `volumes[]`: `{dir, name, fileId}`. Поле `variable` удаляется.

## 3. Домен

### 3.1 `VolumeConfig`

[`VolumeConfig`](src/Unitman/Business/Model/Unit/ConfigUnita/VolumeConfig.php:10):
удалить `$variable`, `getVariable()`, ветку `variable` в [`fromArray()`](src/Unitman/Business/Model/Unit/ConfigUnita/VolumeConfig.php:42)
и [`toArray()`](src/Unitman/Business/Model/Unit/ConfigUnita/VolumeConfig.php:51). Ошибка
`unit.config.volumes.variable_is_empty` удаляется.

### 3.2 `ConfigUnita`

Удалить [`validateVolumesVariables()`](src/Unitman/Business/Model/Unit/ConfigUnita.php:108) и её вызов
в [`__construct()`](src/Unitman/Business/Model/Unit/ConfigUnita.php:102) — тома больше не зависят
от `variables`. Проверка уникальности `dir` остаётся.

### 3.3 Агрегат `Unit`

- Удалить `makeVolumeCollectionByArray()` (сборка томов из значений переменных).
- Новый метод [`makeVolumeCollectionFromZapros(array $volumes, array $currentVolumes): array`](src/Unitman/Business/Model/Unit.php:557):

  1. `$configVolumes` — map `dir => VolumeConfig` из [`ConfigUnita::getVolumes()`](src/Unitman/Business/Model/Unit/ConfigUnita.php:279).
  2. каждый `dir` из запроса обязан быть в этом map → иначе `DomainException('unit.volumes.dir_not_found')`;
     повтор `dir` в запросе → `unit.volumes.dir_is_not_unique`.
  3. результат — по всем томам конфига в его порядке: `fileId` = явно переданное значение,
     иначе текущее из `$current[dir]`, иначе `null`; `name` = из конфига.
- Сигнатура [`zapolnitPeremenie()`](src/Unitman/Business/Model/Unit.php:741):
  `zapolnitPeremenie(array $values, array $volumes = [])`. Если `$values` пуст — в map
  подставляется текущий `$this->variableValues` (переменные сохраняются), обновляются только тома.
- В событие [`PeremenieUnitaZapolneni`](src/Unitman/Business/Model/Unit/Event/PeremenieUnitaZapolneni.php:5)
  всегда пишется **полный снапшот**: map значений переменных и полный список `volumes`
  (в формате `VolumeValue::toArray()`). Проекции и replay не меняются — [`applyPeremenieUnitaZapolneni()`](src/Unitman/Business/Model/Unit.php:793)
  уже восстанавливает состояние из события.
- Отдельный геттер конфига томов не добавлялся: состав и имена томов к этому моменту уже
  нормализованы в `VolumeValue` по конфигу, поэтому [`RunnerAdapter::makeVolumesListFromUnit()`](src/Unitman/Acl/RunnerAdapter.php:267)
  работает с `poluchitVolumeUnita()`.

### 3.4 `VolumeValue`

Без изменений: `{dir, name, fileId}` ([`VolumeValue`](src/Unitman/Business/Model/Unit/VolumeValue.php:11)).
`variable` здесь никогда не было — сохраняем это свойство.

## 4. Команда и use case

- Новый DTO [`ZapolnitPeremenieUnita\VolumeZapros`](src/Unitman/Business/Command/Unit/ZapolnitPeremenieUnita/VolumeZapros.php):
  `public readonly string $dir = ''`, `public readonly ?string $fileId = null`.
  Не переиспользуем [`SozdatUnit\VolumeZapros`](src/Unitman/Business/Command/Unit/SozdatUnit/VolumeZapros.php:15),
  т.к. там есть `name`, которого в этой команде нет.
- [`ZapolnitPeremenieUnita`](src/Unitman/Business/Command/Unit/ZapolnitPeremenieUnita.php:7):
  добавить `public readonly array $volumes` с `@var VolumeZapros[]` (гидрация сериализатором
  из `JsonBodySerializableConverter`, по образцу [`SozdatUnit`](src/Unitman/Business/Command/Unit/SozdatUnit.php:16)).
- [`ZapolnitPeremenieUnitaUseCase`](src/Unitman/Business/UseCase/Unit/ZapolnitPeremenieUnitaUseCase.php:27):
  - старая версия `proveritFaylyVolumes()` (поиск `fileId` в значениях переменных) удалена;
  - новая проверка [`proveritFaylyVolumes()`](src/Unitman/Business/UseCase/Unit/ZapolnitPeremenieUnitaUseCase.php:50):
    для всех непустых `fileId` из **запроса** вызывается
    `mediatekaService->vseFaylyPrinadlezhatUnitu($fileIds, $unit->getId())`, иначе
    `DomainException('unit.volumes.file_not_found')`;
  - вызов `$unit->zapolnitPeremenie($command->values, $command->volumes)`.

## 5. Read-модели

- [`UnitVolume`](src/Unitman/Business/ReadModel/Unit/UnitVolume.php:15): поля `dir`, `name`, `fileId`;
  `fromConfig(VolumeConfig $config, array $volumeValuesByDir = [])` — только поиск по `dir`,
  без fallback через `valuesVariableMap`.
- [`PoluchitPeremenieUnitaQuery`](src/Unitman/Business/UseCase/Unit/PoluchitPeremenieUnitaQuery.php:58):
  убрать третий аргумент `$znacheniePeremenihMap`.
- [`PoluchitKonfigIzHranilishaUseCase`](src/Unitman/Business/UseCase/Unit/PoluchitKonfigIzHranilishaUseCase.php:53):
  без изменений в вызове, но `variable` в ответе исчезает автоматически.
- [`SpisokUnitovProjection::handlePeremenieUnitaZapolneni()`](src/Unitman/Infra/Projection/SpisokUnitovProjection.php:254):
  не меняется — событие по-прежнему содержит полный `volumes`.

## 6. Runner

- [`Volume`](src/Runner/Business/Command/NachatPodgotovkuUnita/Volume.php:12): добавлен пятый параметр
  `public readonly ?string $FileId = null` — ид файла архива медиатеки. Порядок фактических
  параметров: `Dir, Name, Url, FileName, FileId` (новое поле добавлено в конец, чтобы не менять
  существующие позиционные аргументы; JSON в activity читается по именам).
- [`RunnerAdapter::makeVolumesListFromUnit()`](src/Unitman/Acl/RunnerAdapter.php:267): источник —
  `poluchitVolumeUnita()` (`VolumeValue`, уже собранные по конфигу); `FileId` передаётся явно;
  `Url`/`FileName` — через `MediatekaService::poluchitFaylDlyaVoluma()`. Для тома без файла —
  `Volume` только с `Dir`/`Name`.

## 7. OpenAPI

[`config/packages/nelmio_api_doc.yaml`](config/packages/nelmio_api_doc.yaml:1):

- `UnitVolume` ([строки 905–927](config/packages/nelmio_api_doc.yaml:905)): убрать `variable` из `required` и `properties`.
- `UnitVolumeConfig` ([строки 928–942](config/packages/nelmio_api_doc.yaml:928)): убрать `variable`, поправить описание.
- `ZapolnitPeremenieUnita` ([строка 400](config/packages/nelmio_api_doc.yaml:400)): добавить `volumes`
  (`array` → `$ref: '#/components/schemas/ZapolnitPeremenieUnitaVolume'`), описать merge-семантику.
- Новый `ZapolnitPeremenieUnitaVolume`: `required: [dir]`, `dir` (string), `fileId` (uuid, nullable).
- `unitman_volumes` в `UnitConfig` ([строка 789](config/packages/nelmio_api_doc.yaml:789)): описание —
  «том с `dir` как уникальным ключом; архив загружается в медиатеку и его `fileId` передаётся
  в `volumes` запроса `zapolnitPeremenie`».
- Перегенерировать [`docs/openapi.json`](docs/openapi.json) (`composer openapi-dump`) и проверить
  `composer check-openapi`.

## 8. Тесты

- [`ConfigUnitTest`](src/Unitman/Tests/Unit/Unit/ConfigUnitTest.php:262): убрать проверки `variable`
  (в т.ч. `unit.config.volumes.variable_not_found`), оставить необязательность блока, уникальность
  `dir`, невалидный элемент, пустые `dir`/`name`.
- Переписать [`ZapolnitPeremenieUnitaVolumesTest`](src/Unitman/Tests/Unit/Unit/ZapolnitPeremenieUnitaVolumesTest.php:18):
  - тома приходят из запроса: `['dir' => 'db', 'fileId' => 'file-1']`;
  - имя берётся из конфига;
  - `dir_not_found`;
  - `dir_is_not_unique` в запросе;
  - тома не в запросе сохраняют текущий `fileId`;
  - явный `fileId: null` очищает файл;
  - пустой `values` обновляет только тома;
  - без блока `unitman_volumes` тома пусты.
- [`SozdanieUnitaVolumesTest`](src/Unitman/Tests/Unit/Unit/SozdanieUnitaVolumesTest.php:18) — без изменений
  (создание юнита по-прежнему принимает `{dir, name, fileId}`; конфига на этом шаге ещё нет).
- `ZapolnitPeremenieUnitaVolumesTest` покрывает домен: заполнение по `dir`, имя из конфига,
  отсутствие запроса, полный снимок в событии, повторное заполнение, сохранение файла
  не переданного тома, очистку через `fileId: null`, неизвестный `dir`, дубликат `dir`,
  конфиг без блока `unitman_volumes`.
- Сценарий проверки принадлежности файлов юниту (`unit.volumes.file_not_found`) в use case
  отдельным тестом не покрыт — требует инфраструктурного тест-кейса с фейковым `MediatekaService`
  (техдолг).
- `GetUnitListQueryTest` / любые проверки ответа с `volumes` — без поля `variable`.

## 9. Что удаляется из кода/контракта

| Удаляется | Где |
|---|---|
| `VolumeConfig::$variable`, `getVariable()`, `variable` в `toArray()`/`fromArray()` | [`VolumeConfig`](src/Unitman/Business/Model/Unit/ConfigUnita/VolumeConfig.php:14) |
| `ConfigUnita::validateVolumesVariables()` | [`ConfigUnita`](src/Unitman/Business/Model/Unit/ConfigUnita.php:108) |
| `Unit::makeVolumeCollectionByArray()` | [`Unit`](src/Unitman/Business/Model/Unit.php:552) |
| `UnitVolume::$variable` и fallback через `valuesVariableMap` | [`UnitVolume`](src/Unitman/Business/ReadModel/Unit/UnitVolume.php:20) |
| `ZapolnitPeremenieUnitaUseCase::proveritFaylyVolumes()` | [`UseCase`](src/Unitman/Business/UseCase/Unit/ZapolnitPeremenieUnitaUseCase.php:50) |
| Ошибки `unit.config.volumes.variable_is_empty`, `unit.config.volumes.variable_not_found`, `unit.volumes.file_not_set` | — |
| Новые ошибки | `unit.volumes.dir_not_found` |

## 10. Порядок работ

1. Домен: `VolumeConfig`, `ConfigUnita`, `Unit::zapolnitPeremenie()` + `makeVolumeCollectionFromZapros()`.
2. Команда/use case: новый DTO, `volumes` в `ZapolnitPeremenieUnita`, проверка файлов по юниту.
3. Read-модели: `UnitVolume`, `PoluchitPeremenieUnitaQuery`.
4. Runner: `Volume::$FileId`, `RunnerAdapter::makeVolumesListFromUnit()`.
5. OpenAPI: yaml + `docs/openapi.json`.
6. Тесты.
7. Проверки.

## 11. Проверки

```bash
docker compose exec web php bin/console cache:clear
docker compose exec web rr reset
docker compose exec web php vendor/bin/phpunit --testsuite unitman
docker compose exec web composer run all-checks
docker compose exec web composer openapi-dump
docker compose exec web composer check-openapi
```

## 12. Открытые вопросы

| # | Вопрос | Рекомендация |
|---|---|---|
| D1 | Порядок полей в Go-структуре `Volume` | `FileId` добавлен пятым параметром после `Url`/`FileName`; JSON читается по именам, activity нужно обновить полем `FileId` |
| D2 | Убирать ли `name` из `POST /api/unit/sozdat` | Да, отдельной итерацией: `dir` — ключ, `name` — из конфига. В этом плане не трогаем, чтобы не менять контракт создания |
| D3 | Обязательно ли передавать `values` при обновлении только томов | Нет: пустой `values` = переменные не меняются |
| D4 | Можно ли удалить архив тома | Да: `fileId: null` в элементе `volumes` (сам файл в медиатеке остаётся, удаляется при удалении юнита) |
