<?php

declare(strict_types=1);

/**
 * Russian translation of the canonical dictionary (messages.en.php).
 *
 * Keys missing here fall back to the canonical locale (en) automatically.
 * Do not add keys that are absent from the canon — the audit command and the
 * dictionary consistency test will report them as "extra".
 */
return [
    // ─── Account ─────────────────────────────────────────────────────────────
    'account' => [
        'as_system_not_allowed' => 'Системная учетная запись не может добавлять пользователей',
        'not_authenticated' => 'Учетная запись не аутентифицирована',
        'not_found' => 'Учетная запись не найдена',
        'already_blocked' => 'Учетная запись уже заблокирована',
        'is_not_blocked' => 'Учетная запись не заблокирована',
        'login_with_system_account_denied' => 'Вход под системной учетной записью запрещен',
        'settings_not_found' => 'Настройки учетной записи не найдены',
        'system_account_already_exists' => 'Системная учетная запись уже существует',
        'system_account_not_found' => 'Системная учетная запись не найдена',
        'locale_not_supported' => 'Запрошенный язык не поддерживается',
        'nickname_invalid' => 'Некорректный никнейм',
        'nickname_is_empty' => 'Никнейм не может быть пустым',
        'old_email_equal_new_email' => 'Новый email совпадает с текущим',
        'old_locale_equal_new_locale' => 'Новый язык совпадает с текущим',
        'old_nickname_equal_new_nickname' => 'Новый никнейм совпадает с текущим',
        'old_password_equal_new_password' => 'Новый пароль совпадает с текущим',
        'old_role_equal_new_role' => 'Новая роль совпадает с текущей',
        'email' => [
            'double' => 'Учетная запись с таким email уже существует',
        ],
        'nickname' => [
            'double' => 'Учетная запись с таким никнеймом уже существует',
        ],
        'refresh_token' => [
            'empty' => 'Refresh-токен не передан',
            'empty_hash' => 'Пустой хеш refresh-токена',
            'empty_account_id' => 'Не указан аккаунт для refresh-токена',
            'invalid_expires_at' => 'Некорректное время жизни refresh-токена',
            'not_found' => 'Refresh-токен не найден',
            'revoked' => 'Refresh-токен отозван',
            'already_revoked' => 'Refresh-токен уже отозван',
            'expired' => 'Refresh-токен истёк',
        ],
        'access_token' => [
            'invalid_payload' => 'Не удалось выпустить access-токен',
        ],
    ],

    // ─── Shared ──────────────────────────────────────────────────────────────
    'common' => [
        'internal_error' => 'Внутренняя ошибка',
    ],

    // ─── Shared value objects ────────────────────────────────────────────────
    'email' => [
        'invalid' => 'Некорректный email',
        'is' => [
            'empty' => 'Email не может быть пустым',
        ],
    ],
    'password' => [
        'empty' => 'Пароль не может быть пустым',
    ],

    // ─── Background jobs ─────────────────────────────────────────────────────
    'job' => [
        'not_found_by_name' => 'Задача с таким именем не найдена',
    ],

    // ─── Mediateka ───────────────────────────────────────────────────────────
    'mediateka' => [
        'unit_id_is_empty' => 'Идентификатор юнита не может быть пустым',
        'bind' => [
            'body_format_invalid' => 'Некорректный формат тела запроса',
            'file_belongs_to_another_unit' => 'Файл принадлежит другому юниту',
            'file_id_is_empty' => 'Идентификатор файла не может быть пустым',
            'file_ids_format_invalid' => 'Некорректный формат списка идентификаторов файлов',
            'file_not_found' => 'Файл не найден',
            'unit_id_is_empty' => 'Идентификатор юнита не может быть пустым',
        ],
        'file' => [
            'id_is_empty' => 'Идентификатор файла не может быть пустым',
            'is_linked' => 'Файл уже привязан',
            'is_project_file' => 'Файл принадлежит проекту и не может быть изменен',
            'not_found' => 'Файл не найден',
            'owner_is_ambiguous' => 'Владелец файла определен неоднозначно',
            'project_id_is_empty' => 'Идентификатор проекта не может быть пустым',
            'size_is_negative' => 'Размер файла не может быть отрицательным',
            'storage_key_is_empty' => 'Ключ хранилища не может быть пустым',
            'unit_id_is_empty' => 'Идентификатор юнита не может быть пустым',
        ],
        'upload' => [
            'file_is_empty' => 'Загруженный файл пуст',
            'file_is_missing' => 'Файл не был загружен',
            'file_is_not_zip' => 'Загруженный файл должен быть ZIP-архивом',
            'file_is_too_large' => 'Загруженный файл слишком большой',
            'file_is_unreadable' => 'Загруженный файл не читается',
            'name_is_empty' => 'Имя файла не может быть пустым',
            'name_is_too_long' => 'Имя файла слишком длинное',
            'owner_is_ambiguous' => 'Владелец файла определен неоднозначно',
        ],
    ],

    // ─── Projects ────────────────────────────────────────────────────────────
    'project' => [
        'already_built' => 'Проект уже собран',
        'already_disabled' => 'Проект уже отключен',
        'already_enabled' => 'Проект уже включен',
        'already_in_queue' => 'Проект уже в очереди',
        'building_already_execute' => 'Сборка уже выполняется',
        'code_is_empty' => 'Код проекта не может быть пустым',
        'double' => 'Проект с таким названием уже существует',
        'eshe_ne_sobran' => 'Проект еще не собран',
        'host_invalid' => 'Некорректный хост проекта',
        'host_is_empty' => 'Хост проекта не может быть пустым',
        'id_is_empty' => 'Идентификатор проекта не может быть пустым',
        'in_pending_for_build' => 'Проект ожидает сборки',
        'length_name_invalid' => 'Некорректная длина названия проекта',
        'name_invalid' => 'Некорректное название проекта',
        'name_is_empty' => 'Название проекта не может быть пустым',
        'not_found' => 'Проект не найден',
        'not_found_data_about_building' => 'Данные о сборке проекта не найдены',
        'not_found_data_about_removing' => 'Данные об удалении проекта не найдены',
        'ochistka_ne_nachalas' => 'Очистка проекта не начата',
        'ochistka_uge_nachalas' => 'Очистка проекта уже начата',
        'old_name_equal_new_name' => 'Новое название совпадает с текущим',
        'pending_building' => 'Проект ожидает сборки',
        'project_already_remove' => 'Проект уже удален',
        'removed' => 'Проект удален',
        'removing' => 'Проект удаляется',
        'removing_already_execute' => 'Удаление уже выполняется',
        'resultat_ochistki_uge_ustanovlen' => 'Результат очистки уже установлен',
        'runner_job_not_found' => 'Задача runner не найдена',
        'user_already_exist' => 'Пользователь уже добавлен в проект',
        'user_has_system_role' => 'Системную учетную запись нельзя добавить в проект',
        'user_not_found' => 'Пользователь не найден',
        'user_not_found_in_project' => 'Пользователь не состоит в проекте',
        'variable' => [
            'code_is_empty' => 'Код переменной не может быть пустым',
            'duplicate' => 'Переменная уже определена',
            'new_value_equal_old_value' => 'Новое значение совпадает с текущим',
            'not_found' => 'Переменная не найдена',
            'value_is_empty' => 'Значение переменной не может быть пустым',
        ],
        'webhook' => [
            'id_is_empty' => 'Идентификатор webhook не может быть пустым',
            'projectId_is_empty' => 'Идентификатор проекта webhook не может быть пустым',
            'new_url_equals_old_url' => 'Новый URL совпадает с текущим',
            'not_found_by_id' => 'Webhook не найден',
            'url_invalid' => 'Некорректный URL webhook',
            'url_is_empty' => 'URL webhook не может быть пустым',
        ],
    ],

    'proekt' => [
        'webhook' => [
            'double' => 'Webhook уже существует',
        ],
    ],

    // ─── Repos ───────────────────────────────────────────────────────────────
    'repo' => [
        'already_deleted' => 'Репозиторий уже удален',
        'deleted' => 'Репозиторий удален',
        'id_is_empty' => 'Идентификатор репозитория не может быть пустым',
        'length_name_invalid' => 'Некорректная длина названия репозитория',
        'name_is_empty' => 'Название репозитория не может быть пустым',
        'new_credentials_equals_old' => 'Новые учетные данные совпадают с текущими',
        'not_found' => 'Репозиторий не найден',
        'url_is_empty' => 'URL репозитория не может быть пустым',
        'credentials' => [
            'token_is_empty' => 'Токен доступа не может быть пустым',
            'url_invalid' => 'Некорректный URL репозитория',
            'url_is_empty' => 'URL репозитория не может быть пустым',
        ],
        'delete' => [
            'est_proekti' => 'Нельзя удалить репозиторий, пока его используют проекты',
        ],
        'name' => [
            'double' => 'Репозиторий с таким названием уже существует',
        ],
    ],

    // ─── Runner ──────────────────────────────────────────────────────────────
    'runner' => [
        'deistvie_ne_zakonchena' => 'Действие еще не завершено',
        'invalid_signature' => 'Неверная подпись запроса runner\'а',
        'memory_free_is_to_small' => 'Недостаточно свободной памяти для запуска юнита',
        'obnovlenie_eshe_ne_zakoncheno' => 'Обновление еще не завершено',
        'oshibka_proverki_unita' => 'Ошибка проверки юнита',
        'ostanovka_eshe_ne_zakonchena' => 'Остановка еще не завершена',
        'podgotovka_eshe_ne_zakonchena' => 'Подготовка еще не завершена',
        'sborka_eshe_ne_zakonchena' => 'Сборка еще не завершена',
        'sborka_proekta_eshe_ne_zakonchena' => 'Сборка проекта еще не завершена',
        'sbros_podgotovki_eshe_ne_zakonchen' => 'Сброс подготовки еще не завершен',
        'steps_malformed' => 'Полезная нагрузка шагов runner\'а не является корректным JSON-массивом',
        'steps_too_large' => 'Полезная нагрузка шагов runner\'а слишком велика',
        'udalenie_eshe_ne_zakoncheno' => 'Удаление еще не завершено',
        'udalenie_proekta_eshe_ne_zakonchena' => 'Удаление проекта еще не завершено',
        'unknown_error_when_make_result' => 'Неизвестная ошибка при формировании результата',
        'unknown_error_when_make_result_with_config' => 'Неизвестная ошибка при формировании результата с конфигом',
        'unknown_progress_token' => 'Токен прогресса runner\'а не зарегистрирован',
        'workflow_unit_not_found' => 'Workflow юнита не найден',
        'zapusk_eshe_ne_zakonchen' => 'Запуск еще не завершен',
        'state' => [
            'is_non_active' => 'Юнит не активен',
            'not_found' => 'Состояние runner не найдено',
        ],
    ],

    // ─── Security ────────────────────────────────────────────────────────────
    'security' => [
        'access_denied' => 'Доступ запрещен',
    ],

    // ─── Units ───────────────────────────────────────────────────────────────
    'unit' => [
        'author_must_have_system_role' => 'Создавать юнит от имени системы может только системная учетная запись',
        'avtosozdanie_viklucheno' => 'Автосоздание юнитов отключено',
        'branch_is_empty' => 'Ветка не может быть пустой',
        'branch_not_found' => 'Ветка не найдена',
        'config_is_empty' => 'Конфиг юнита не может быть пустым',
        'config_not_found' => 'Конфиг юнита не найден',
        'config_unita_ne_opredelen' => 'Конфиг юнита не определен',
        'authorId_is_empty' => 'Идентификатор автора юнита не может быть пустым',
        'double' => 'Юнит с таким именем уже существует',
        'id_is_empty' => 'Идентификатор юнита не может быть пустым',
        'invalid_proxy_host' => 'Некорректный proxy host',
        'projectId_is_empty' => 'Идентификатор проекта юнита не может быть пустым',
        'job_not_found' => 'Задача юнита не найдена',
        'length_name_invalid' => 'Некорректная длина имени юнита',
        'memory_limit_min_300' => 'Лимит памяти должен быть не меньше 300 МБ',
        'name_already_exists' => 'Юнит с таким именем уже существует',
        'name_invalid' => 'Некорректное имя юнита',
        'name_is_empty' => 'Имя юнита не может быть пустым',
        'name_not_found' => 'Имя юнита не найдено',
        'ne_hvataet_prav' => 'Недостаточно прав',
        'ne_zapushen' => 'Юнит не запущен',
        'not_found' => 'Юнит не найден',
        'podgotovka_ne_nachalas' => 'Подготовка не начата',
        'podgotovka_uge_bila' => 'Юнит уже был подготовлен',
        'project_is_disabled' => 'Проект отключен',
        'project_not_found' => 'Проект не найден',
        'prozes_ochistki_ne_naiden' => 'Процесс очистки не найден',
        'prozes_sborki_ne_naiden' => 'Процесс сборки не найден',
        'prozes_udaleniya_ne_naiden' => 'Процесс удаления не найден',
        'resultat_deistviya_uge_ustanovlen' => 'Результат действия уже установлен',
        'runner_job_not_found' => 'Задача runner не найдена',
        'sborka' => [
            'jobId_is_empty' => 'Идентификатор задачи сборки не может быть пустым',
        ],
        'sborka_ne_nachalas' => 'Сборка не начата',
        'state_not_found' => 'Состояние юнита не найдено',
        'udalen' => 'Юнит удален',
        'uge_sobran' => 'Юнит уже собран',
        'uge_udalen' => 'Юнит уже удален',
        'vipolnenieDeistviya_ne_nachalas' => 'Выполнение действия не начато',
        'wait_another_proccess' => 'Еще выполняется другой процесс',
        'wait_runner' => 'Runner еще занят',
        'zapushen' => 'Юнит уже запущен',
        'config' => [
            'prepare_is_empty' => 'Блок "prepare" не может быть пустым',
            'resetPrepare_is_empty' => 'Блок "reset_prepare" не может быть пустым',
            'up_is_empty' => 'Блок "up" не может быть пустым',
            'down_is_empty' => 'Блок "down" не может быть пустым',
            'services_is_empty' => 'Блок "services" не может быть пустым',
            'type_for_variable_not_found' => 'Тип переменной не найден',
            'values_format_invalid' => 'Некорректный формат значений переменных',
            'action' => [
                'id_not_defined' => 'У действия не указан id',
                'name_not_defined' => 'У действия не указано имя',
                'commands_not_defined' => 'У действия не указаны команды',
                'commands_is_not_array' => 'Команды действия должны быть массивом',
                'variables_is_not_array' => 'Переменные действия должны быть массивом',
            ],
            'port' => [
                'invalid' => 'Некорректный порт',
                'type' => [
                    'is_empty' => 'Тип порта не может быть пустым',
                ],
            ],
            'pomoschnik' => [
                'port_invalid' => 'Некорректный порт хелпера',
                'projectId_is_empty' => 'Идентификатор проекта хелпера не может быть пустым',
                'service_is_empty' => 'Сервис хелпера не может быть пустым',
                'type_invalid' => 'Некорректный тип хелпера',
            ],
            'variable' => [
                'id_is_empty' => 'Идентификатор переменной не может быть пустым',
                'label_is_empty' => 'Подпись переменной не может быть пустой',
                'label_invalid' => 'Некорректная подпись переменной',
                'length_label_invalid' => 'Некорректная длина подписи переменной',
                'collection_options_is_empty' => 'Опции коллекции переменной не могут быть пустыми',
            ],
            'volumes' => [
                'dir_is_empty' => 'Директория тома не может быть пустой',
                'dir_is_not_unique' => 'Директория тома должна быть уникальной',
                'invalid_item' => 'Некорректный элемент тома',
                'name_is_empty' => 'Имя тома не может быть пустым',
            ],
        ],
        'config_variable' => [
            'collection_option' => [
                'id_is_empty' => 'Идентификатор опции коллекции не может быть пустым',
                'name_is_empty' => 'Имя опции коллекции не может быть пустым',
            ],
        ],
        'config_variable_factory' => [
            'type_is_empty' => 'Тип переменной не может быть пустым',
            'type_invalid' => 'Некорректный тип переменной',
        ],
        'files' => [
            'not_found' => 'Файлы юнита не найдены',
        ],
        'konfig' => [
            'actions' => 'Действия юнита не определены',
            'deistvie' => [
                'variable_not_found_by_id' => 'Переменная действия не найдена',
            ],
        ],
        'konfigServisa' => [
            'name_is_empty' => 'Имя сервиса не может быть пустым',
        ],
        'mapAction' => [
            'not_found' => 'Действие юнита не найдено',
        ],
        'ochered_unitov' => [
            'not_found' => 'Очередь юнитов не найдена',
        ],
        'obrabotka_prozesa' => [
            'tip_ne_opredelen' => 'Тип процесса не определен',
        ],
        'pomoschnik' => [
            'servis_ne_naiden' => 'Сервис хелпера не найден',
        ],
        'process' => [
            'already_finish' => 'Процесс уже завершен',
        ],
        'prozess' => [
            'not_found' => 'Процесс не найден',
            'not_found_by_job_id' => 'Процесс не найден по идентификатору задачи',
        ],
        'runner_service' => [
            'job_id_empty' => 'Идентификатор задачи runner не может быть пустым',
        ],
        'spisok_unitov' => [
            'unit_not_found' => 'Юнит не найден в списке',
        ],
        'state_factory' => [
            'state_not_found' => 'Состояние юнита не найдено',
        ],
        'svyaz' => [
            'invalid_type' => 'Некорректный тип связи',
            'self_link_is_forbidden' => 'Нельзя направить юнит на самого себя',
            'target_unit_id_is_empty' => 'Идентификатор целевого юнита не может быть пустым',
            'target_unit_project_is_not_available' => 'Проект целевого юнита недоступен',
            'variable_id_is_empty' => 'Идентификатор переменной не может быть пустым',
            'variable_is_not_unique' => 'Переменная должна быть уникальной',
        ],
        'variable_value' => [
            'id_is_empty' => 'Идентификатор значения переменной не может быть пустым',
            'type_is_empty' => 'Тип значения переменной не может быть пустым',
            'value_is_empty' => 'Значение переменной не может быть пустым',
        ],
        'volume_type' => [
            'code_is_empty' => 'Код типа тома не может быть пустым',
            'code_is_not_unique' => 'Код типа тома должен быть уникальным',
            'code_is_too_long' => 'Код типа тома слишком длинный',
            'file_already_linked' => 'Файл уже привязан к типу тома',
            'file_belongs_to_unit' => 'Файл принадлежит юниту',
            'file_id_is_empty' => 'Идентификатор файла не может быть пустым',
            'file_not_found' => 'Файл не найден',
            'file_not_linked' => 'Файл не привязан к типу тома',
            'has_files' => 'У типа тома еще есть файлы',
            'id_is_empty' => 'Идентификатор типа тома не может быть пустым',
            'name_is_empty' => 'Имя типа тома не может быть пустым',
            'name_is_too_long' => 'Имя типа тома слишком длинное',
            'not_found' => 'Тип тома не найден',
            'project_id_is_empty' => 'Идентификатор проекта не может быть пустым',
        ],
        'volume_type_file' => [
            'file_id_is_empty' => 'Идентификатор файла не может быть пустым',
            'project_id_is_empty' => 'Идентификатор проекта не может быть пустым',
            'type_id_is_empty' => 'Идентификатор типа тома не может быть пустым',
        ],
        'volume_value' => [
            'dir_is_empty' => 'Директория тома не может быть пустой',
            'file_id_is_empty' => 'Идентификатор файла не может быть пустым',
            'name_is_empty' => 'Имя тома не может быть пустым',
        ],
        'volumes' => [
            'dir_is_empty' => 'Директория тома не может быть пустой',
            'dir_is_not_unique' => 'Директория тома должна быть уникальной',
            'dir_not_found' => 'Директория тома не найдена',
            'file_not_found' => 'Файл тома не найден',
            'file_not_in_catalog' => 'Файл отсутствует в каталоге',
            'file_type_mismatch' => 'Тип файла не соответствует',
        ],
        'webhook' => [
            'not_found_by_id' => 'Webhook не найден',
        ],
        'websocket_unit_event' => [
            'not_found_id' => 'Событие websocket юнита не найдено',
        ],
    ],

    // ─── Storage events ──────────────────────────────────────────────────────
    'sobitie_iz_hranilisha' => [
        'commits_not_found' => 'Коммиты в репозитории не найдены',
    ],

    // ─── Infrastructure / utils ──────────────────────────────────────────────
    'projections_manager' => [
        'rebuild_not_allowed' => 'Пересборка этой проекции запрещена',
    ],
    'utils' => [
        'projection_not_found_by_name' => 'Проекция не найдена',
    ],
];
