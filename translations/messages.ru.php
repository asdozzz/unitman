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
        'not_pending_activation' => 'Аккаунт не ожидает активации',
        'email' => [
            'double' => 'Учетная запись с таким email уже существует',
            'already_verified' => 'Email уже подтверждён',
            'not_verified' => 'Сначала подтвердите email',
            'send_failed' => 'Не удалось отправить код подтверждения. Запросите код повторно.',
            'code_invalid' => 'Неверный код подтверждения',
            'code_expired' => 'Срок действия кода подтверждения истёк',
            'too_many_attempts' => 'Слишком много неверных попыток. Запросите новый код.',
            'resend_too_often' => 'Код уже отправлен. Подождите перед повторной отправкой.',
            'too_many_resends' => 'Слишком много запросов кода. Попробуйте позже.',
        ],
        'nickname' => [
            'double' => 'Учетная запись с таким никнеймом уже существует',
        ],
        'refresh_token' => [
            'empty' => 'Refresh-токен не передан',
            'missing' => 'Refresh-сессия отсутствует',
            'invalid' => 'Refresh-сессия недействительна или истекла',
            'empty_hash' => 'Пустой хеш refresh-токена',
            'empty_account_id' => 'Не указан аккаунт для refresh-токена',
            'invalid_expires_at' => 'Некорректное время жизни refresh-токена',
            'not_found' => 'Refresh-токен не найден',
            'revoked' => 'Refresh-токен отозван',
            'already_revoked' => 'Refresh-токен уже отозван',
            'expired' => 'Refresh-токен истёк',
        ],
        'cookie_auth' => [
            'header_required' => 'В запросе отсутствует обязательный заголовок X-Requested-With',
            'origin_denied' => 'Origin запроса не разрешён',
        ],
        'access_token' => [
            'invalid_payload' => 'Не удалось выпустить access-токен',
        ],
        'opencode_credential' => [
            'decrypt_failed' => 'Не удалось расшифровать API-ключ OpenCode',
            'empty_id' => 'Идентификатор реквизитов не может быть пустым',
            'empty_account_id' => 'Не указан владелец реквизитов',
            'empty_api_key' => 'API-ключ OpenCode не может быть пустым',
            'empty_name' => 'Имя реквизитов не может быть пустым',
            'empty_url' => 'URL OpenCode не может быть пустым',
            'empty_model' => 'Модель OpenCode не может быть пустой',
            'empty_models' => 'Список моделей OpenCode не может быть пустым',
            'empty_provider' => 'Провайдер OpenCode не может быть пустым',
            'encrypt_failed' => 'Не удалось зашифровать API-ключ OpenCode',
            'duplicate_model' => 'Модель OpenCode указана несколько раз',
            'invalid_key' => 'Некорректный ключ шифрования OpenCode',
            'key_not_configured' => 'Ключ шифрования OpenCode не задан',
            'model_not_available' => 'Выбранная модель OpenCode недоступна для этих реквизитов',
            'model_too_long' => 'Название модели OpenCode слишком длинное',
            'name_double' => 'Реквизиты с таким именем уже существуют',
            'name_too_long' => 'Имя реквизитов слишком длинное',
            'not_found' => 'Реквизиты OpenCode не найдены',
            'provider_empty_response' => 'Провайдер OpenCode не вернул ни одной модели',
            'provider_error' => 'Провайдер OpenCode вернул ошибку',
            'provider_invalid_response' => 'Некорректный ответ провайдера OpenCode',
            'provider_list_not_supported' => 'Провайдер не поддерживает получение списка моделей',
            'provider_response_too_large' => 'Ответ провайдера OpenCode слишком большой',
            'provider_unavailable' => 'Провайдер OpenCode недоступен',
            'too_many_models' => 'Слишком много моделей OpenCode',
            'unknown_provider' => 'Неизвестный провайдер OpenCode',
            'url_too_long' => 'URL OpenCode слишком длинный',
        ],
    ],

    // ─── Storage providers ───────────────────────────────────────────────────
    'bitbucket' => [
        'config_not_found' => 'Учётные данные Bitbucket не найдены',
        'project_code_invalid' => 'Некорректный код проекта Bitbucket',
        'request_failed' => 'Запрос к Bitbucket не удался',
    ],
    'gitea' => [
        'config_not_found' => 'Учётные данные Gitea не найдены',
        'project_code_invalid' => 'Некорректный код проекта Gitea',
        'request_failed' => 'Запрос к Gitea не удался',
        'url_invalid' => 'Некорректный URL Gitea',
    ],
    'gitflic' => [
        'commit_not_found' => 'Коммит GitFlic не найден',
        'config_not_found' => 'Учётные данные GitFlic не найдены',
        'project_code_invalid' => 'Некорректный код проекта GitFlic',
        'request_failed' => 'Запрос к GitFlic не удался',
        'url_invalid' => 'Некорректный URL GitFlic',
    ],
    'gitverse' => [
        'config_decode_failed' => 'Не удалось декодировать учётные данные GitVerse',
        'config_not_found' => 'Учётные данные GitVerse не найдены',
        'project_code_invalid' => 'Некорректный код проекта GitVerse',
        'request_failed' => 'Запрос к GitVerse не удался',
        'url_invalid' => 'Некорректный URL GitVerse',
    ],

    // ─── OAuth ───────────────────────────────────────────────────────────────
    'oauth' => [
        'provider' => [
            'not_configured' => 'Вход через этого провайдера не настроен',
            'error' => 'Провайдер вернул некорректные данные',
        ],
        'email' => [
            'not_verified' => 'Провайдер не подтвердил email',
        ],
        'account' => [
            'blocked' => 'Аккаунт заблокирован',
            'system' => 'Вход в системный аккаунт запрещён',
        ],
        'state' => [
            'invalid' => 'Некорректное состояние OAuth-входа',
            'expired' => 'Сессия OAuth-входа истекла',
        ],
        'exchange' => [
            'failed' => 'Не удалось завершить OAuth-вход',
        ],
        'code' => [
            'invalid' => 'Одноразовый OAuth-код недействителен',
        ],
    ],

    // ─── AI chat ─────────────────────────────────────────────────────────────
    'ai' => [
        'chat' => [
            'invalid_payload' => 'Некорректный запрос к AI-чату',
            'credential_required' => 'Для AI-чата нужны реквизиты OpenCode',
            'session_not_found' => 'Сессия AI-чата не найдена',
            'session_archived' => 'Сессия AI-чата в архиве и доступна только для просмотра',
            'session_expired' => 'Срок жизни сессии AI-чата истёк',
            'session_not_archived' => 'Удалить можно только сессию AI-чата в архиве',
            'session_message_limit' => 'В сессии AI-чата достигнут лимит сообщений',
            'provider_not_supported' => 'Провайдер не поддерживает AI-чат',
            'provider_session_transport_unknown' => 'Транспорт provider-сессии не настроен',
            'credential_deleted' => 'Реквизиты сессии AI-чата удалены',
            'invalid_limit' => 'Некорректный размер страницы списка сессий',
            'invalid_offset' => 'Некорректное смещение списка сессий',
            'upstream_not_configured' => 'URL API OpenCode не настроен',
            'upstream_unavailable' => 'API OpenCode недоступен',
            'upstream_invalid_response' => 'API OpenCode вернул некорректный ответ',
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
    'background_job' => [
        'group_unknown' => 'Неизвестная группа фоновых задач',
    ],
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
            'content_is_invalid' => 'Содержимое файла пустое или не является корректным UTF-8',
            'id_is_empty' => 'Идентификатор файла не может быть пустым',
            'is_linked' => 'Файл уже привязан',
            'is_not_unit_draft' => 'Файл не является черновиком юнита',
            'is_not_workspace_draft' => 'Файл не является черновиком workspace',
            'is_not_workspace_file' => 'Файл не принадлежит workspace',
            'is_project_file' => 'Файл принадлежит проекту и не может быть изменен',
            'name_is_invalid' => 'Некорректное имя файла',
            'not_found' => 'Файл не найден',
            'opencode_turn_id_is_empty' => 'Идентификатор хода OpenCode не может быть пустым',
            'owner_is_ambiguous' => 'Владелец файла определен неоднозначно',
            'project_id_is_empty' => 'Идентификатор проекта не может быть пустым',
            'size_is_negative' => 'Размер файла не может быть отрицательным',
            'storage_key_is_empty' => 'Ключ хранилища не может быть пустым',
            'too_large' => 'Файл слишком большой',
            'turn_without_workspace' => 'У хода OpenCode не указан workspace',
            'type_is_forbidden' => 'Тип файла запрещён',
            'unit_id_is_empty' => 'Идентификатор юнита не может быть пустым',
            'workspace_id_is_empty' => 'Идентификатор workspace не может быть пустым',
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
            'presign_ttl_invalid' => 'Некорректное время жизни ссылки на загрузку',
            'size_mismatch' => 'Размер загруженного файла не совпадает с заявленным',
            'token_expired' => 'Срок действия токена загрузки истёк',
            'token_invalid' => 'Токен загрузки недействителен',
        ],
        'workspace' => [
            'file_is_binary' => 'Файл workspace содержит двоичные данные',
            'file_is_empty' => 'Файл workspace пуст',
            'file_is_not_utf8' => 'Текстовый файл workspace должен быть в кодировке UTF-8',
            'file_is_too_large' => 'Файл workspace слишком большой',
            'file_is_unreadable' => 'Файл workspace не читается',
            'file_signature_mismatch' => 'Содержимое файла не соответствует заявленному типу изображения',
            'file_type_forbidden' => 'Этот тип файла нельзя прикрепить к workspace',
            'name_is_empty' => 'Имя файла workspace не может быть пустым',
            'name_is_invalid' => 'Имя файла workspace содержит недопустимые символы',
            'name_is_reserved' => 'Имя файла workspace зарезервировано',
            'name_is_too_long' => 'Имя файла workspace слишком длинное',
            'name_without_extension' => 'Имя файла workspace должно содержать расширение',
        ],
    ],

    // ─── Upload token ────────────────────────────────────────────────────────
    'token' => [
        'content_type_is_empty' => 'В токене загрузки не указан MIME-тип',
        'expires_at_is_invalid' => 'В токене загрузки некорректный срок действия',
        'file_id_is_empty' => 'В токене загрузки не указан идентификатор файла',
        'name_is_empty' => 'В токене загрузки не указано имя файла',
        'owner_is_ambiguous' => 'Владелец файла в токене загрузки определён неоднозначно',
        'project_id_is_empty' => 'В токене загрузки не указан идентификатор проекта',
        'size_is_invalid' => 'В токене загрузки некорректный размер файла',
        'storage_key_is_empty' => 'В токене загрузки не указан ключ хранилища',
        'unit_id_is_empty' => 'В токене загрузки не указан идентификатор юнита',
        'uploaded_by_is_empty' => 'В токене загрузки не указан автор',
    ],

    // ─── OpenCode ────────────────────────────────────────────────────────────
    'opencode' => [
        'artifact' => [
            'turn_not_found' => 'Ход OpenCode для загрузки артефакта не найден',
            'turn_not_running' => 'Ход OpenCode уже не выполняется: загрузка артефактов недоступна',
            'workspace_required' => 'Артефакты можно загружать только для workspace-сессии',
            'workspace_mismatch' => 'Workspace хода не совпадает с запрошенным workspace',
            'workspace_archived' => 'Workspace в архиве: загрузка артефактов недоступна',
            'name_is_invalid' => 'Некорректное имя файла-артефакта',
            'file_type_forbidden' => 'Тип файла-артефакта запрещён',
            'content_invalid' => 'Содержимое артефакта пустое или не является корректным UTF-8',
            'too_many_files' => 'Достигнут лимит файлов-артефактов хода',
            'total_is_too_large' => 'Суммарный размер файлов-артефактов хода превышает лимит',
            'file_is_too_large' => 'Файл-артефакт слишком большой',
            'preview_unsupported' => 'Этот тип артефакта нельзя показать в preview',
            'preview_too_large' => 'Артефакт слишком большой для preview',
            'not_found' => 'Файл-артефакт не найден или недоступен',
        ],
        'notification' => [
            'ids_required' => 'Не указаны идентификаторы уведомлений',
            'too_many_ids' => 'Слишком много уведомлений: не более 100 за запрос',
        ],
        'session' => [
            'account_required' => 'Не указан владелец сессии',
            'archive_reason_required' => 'Не указана причина архивации сессии',
            'archived' => 'Сессия в архиве: новые ходы недоступны',
            'credential_required' => 'Нужно выбрать реквизиты OpenCode',
            'id_required' => 'Не указан идентификатор сессии',
            'not_found' => 'Сессия OpenCode не найдена',
            'too_many_units' => 'К сессии можно привязать не более 10 юнитов',
            'unit_busy' => 'Для этого юнита уже существует активная сессия',
            'unit_not_editor' => 'Запустить сессию по этому юниту может только его редактор',
            'unit_not_found' => 'Юнит не найден или недоступен',
            'unit_not_ready' => 'Один или несколько юнитов ещё собираются или запускаются — дождитесь завершения',
            'unit_project_conflict' => 'К сессии можно привязать только один юнит в рамках одного проекта',
            'unit_project_required' => 'Не указан проект юнита',
            'unit_required' => 'Не указан идентификатор юнита',
            'unit_source_conflict' => 'Нельзя одновременно указать юнит и создать новый',
        ],
        'workspace' => [
            'archived' => 'Workspace в архиве',
            'runner_not_bound' => 'Workspace не привязан к раннеру',
            'runner_public_address_missing' => 'У раннера, к которому привязан workspace, нет публичного адреса',
            'branch_protected' => 'Ветка входит в список защищённых веток проекта и не может использоваться для workspace',
            'branch_required' => 'Не указана ветка workspace',
            'branch_too_long' => 'Название ветки workspace слишком длинное',
            'confirmation_required' => 'В workspace есть незакоммиченные изменения: подтвердите операцию',
            'delete_in_progress' => 'Workspace уже удаляется',
            'file_limit_exceeded' => 'Превышен лимит файлов workspace',
            'file_name_conflict' => 'Файл с таким именем уже прикреплён к workspace',
            'file_not_found' => 'Файл workspace не найден или недоступен',
            'files_delete_failed' => 'Не удалось удалить файлы workspace',
            'initial_prompt_failed' => 'Не удалось запустить первый промпт workspace',
            'invalid_port' => 'Порт должен быть в диапазоне от 1 до 65535',
            'invalid_status' => 'Некорректный статус сохранённого workspace',
            'invalid_transition' => 'Недопустимый переход статуса workspace',
            'link_unavailable' => 'Ссылка на workspace недоступна: не задан публичный адрес раннера',
            'name_already_exists' => 'Workspace с таким названием уже существует',
            'name_required' => 'Название workspace не может быть пустым',
            'name_too_long' => 'Название workspace слишком длинное',
            'not_found' => 'Workspace не найден',
            'not_running' => 'Workspace не запущен',
            'operation_failed' => 'Операция с workspace завершилась ошибкой',
            'project_branch_not_found' => 'Ветка проекта не найдена',
            'project_clone_failed' => 'Не удалось склонировать проект в workspace',
            'project_conflict' => 'Один и тот же проект указан несколько раз',
            'project_invalid' => 'Некорректный проект в запросе',
            'project_not_found' => 'Проект не найден или недоступен',
            'projects_required' => 'Нужен хотя бы один проект',
            'prompt_required' => 'Первый промпт не может быть пустым',
            'unit_conflict' => 'Один и тот же юнит указан несколько раз',
            'unit_invalid' => 'Некорректный снимок юнита workspace',
            'units_already_created' => 'Для этого workspace юниты уже созданы',
            'read_only_push_forbidden' => 'Workspace доступен только для чтения: push запрещён',
            'too_many_projects' => 'В workspace можно включить не более 20 проектов',
            'uncommitted_changes' => 'В workspace есть незакоммиченные изменения',
            'update_in_progress' => 'Обновление workspace уже выполняется',
        ],
        'turn' => [
            'answers_required' => 'Нужен хотя бы один ответ',
            'awaiting_answer' => 'Ход уже ожидает ответа',
            'failed' => 'Ход OpenCode завершился ошибкой',
            'invalid_status' => 'Некорректный статус сохранённого хода',
            'not_awaiting_answer' => 'Ход не ожидает ответа',
            'not_found' => 'Ход OpenCode не найден',
            'option_required' => 'Нужно выбрать вариант ответа',
            'request_required' => 'Промпт не может быть пустым',
            'unexpected_kind' => 'Раннер вернул результат неизвестного типа',
            'unit_directory_not_found' => 'Каталог юнита не найден на раннере',
            'unknown_option' => 'Выбранного варианта ответа не существует',
            'unknown_question' => 'Вопроса, на который дан ответ, не существует',
        ],
    ],

    // ─── Projects ────────────────────────────────────────────────────────────
    'project' => [
        'already_built' => 'Проект уже собран',
        'already_disabled' => 'Проект уже отключен',
        'already_enabled' => 'Проект уже включен',
        'already_in_queue' => 'Проект уже в очереди',
        'branch_bez_prav' => 'У личного токена нет прав на создание ветки проекта',
        'branch_exists' => 'Ветка проекта уже существует',
        'branch_invalid' => 'Некорректное название ветки проекта',
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
        'net_lichnogo_tokena' => 'У пользователя нет личного токена для репозитория проекта',
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
        'zashishennie_vetki' => [
            'pusta_stroka' => 'Маска защищённой ветки не может быть пустой',
            'slishkom_dlinnaya' => 'Маска защищённой ветки слишком длинная',
            'dublikat' => 'Маска защищённой ветки дублируется',
            'slishkom_mnogo' => 'Слишком много масок защищённых веток',
        ],
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
        'websocket_project_event' => [
            'not_found_id' => 'Событие websocket проекта не найдено',
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
        'token_upcast_missing_repo_id' => 'Не удалось обновить legacy-токен репозитория: не указан идентификатор репозитория',
        'url_is_empty' => 'URL репозитория не может быть пустым',
        'credentials' => [
            'denied' => 'Доступ к учётным данным репозитория запрещён',
            'invalid_request' => 'Некорректный запрос учётных данных репозитория',
            'token_is_empty' => 'Токен доступа не может быть пустым',
            'unavailable' => 'Учётные данные репозитория временно недоступны',
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

    // ─── Repository token encryption (WS-2) ──────────────────────────────────
    'repo_token' => [
        'key_not_configured' => 'Ключ шифрования токенов репозиториев не задан',
        'invalid_key' => 'Некорректный ключ шифрования токенов репозиториев',
        'encrypt_failed' => 'Не удалось зашифровать токен репозитория',
        'decrypt_failed' => 'Не удалось расшифровать токен репозитория',
    ],

    // ─── Skills ──────────────────────────────────────────────────────────────
    'skill' => [
        'id' => [
            'empty' => 'Идентификатор навыка не может быть пустым',
            'too_long' => 'Идентификатор навыка слишком длинный',
        ],
        'slug' => [
            'invalid' => 'Некорректный slug навыка',
            'taken' => 'Глобальный навык с таким slug уже существует',
        ],
        'name' => [
            'empty' => 'Название навыка не может быть пустым',
            'invalid_utf8' => 'Название навыка не является корректной строкой UTF-8',
            'contains_nul' => 'Название навыка не должно содержать NUL-байты',
            'too_long' => 'Название навыка слишком длинное',
        ],
        'description' => [
            'invalid_utf8' => 'Описание навыка не является корректной строкой UTF-8',
            'contains_nul' => 'Описание навыка не должно содержать NUL-байты',
            'too_long' => 'Описание навыка слишком длинное',
        ],
        'content' => [
            'invalid_utf8' => 'Содержимое навыка не является корректной строкой UTF-8',
            'contains_nul' => 'Содержимое навыка не должно содержать NUL-байты',
            'too_long' => 'Содержимое навыка слишком большое',
        ],
        'project' => [
            'required' => 'Проектный навык должен быть привязан хотя бы к одному проекту',
            'invalid' => 'Некорректный идентификатор проекта',
            'too_long' => 'Идентификатор проекта слишком длинный',
            'not_attached' => 'Навык не привязан к этому проекту',
        ],
        'scope' => [
            'invalid' => 'Некорректная область навыка',
            'projects_forbidden' => 'Глобальный навык нельзя привязать к проектам',
            'global_cannot_attach' => 'Глобальный навык уже доступен во всех проектах',
            'global_cannot_detach' => 'Глобальный навык нельзя открепить от проекта',
        ],
        'forbidden' => 'Недостаточно прав для работы с навыком',
        'not_found' => 'Навык не найден',
        'resolve' => [
            'invalid_request' => 'Некорректный запрос набора навыков',
            'unsupported_format' => 'Неподдерживаемая версия формата манифеста навыков',
        ],
    ],

    // ─── Runner ──────────────────────────────────────────────────────────────
    'runner' => [
        'deistvie_ne_zakonchena' => 'Действие еще не завершено',
        'health_payload_too_large' => 'Слишком большой объём данных health-запроса раннера.',
        'health_runner_id_empty' => 'Не указан идентификатор раннера.',
        'inactive' => 'Раннер неактивен',
        'invalid_signature' => 'Неверная подпись запроса runner\'а',
        'memory_free_is_to_small' => 'Недостаточно свободной памяти для запуска юнита',
        'not_found' => 'Раннер не найден',
        'obnovlenie_eshe_ne_zakoncheno' => 'Обновление еще не завершено',
        'oshibka_proverki_unita' => 'Ошибка проверки юнита',
        'ostanovka_eshe_ne_zakonchena' => 'Остановка еще не завершена',
        'podgotovka_eshe_ne_zakonchena' => 'Подготовка еще не завершена',
        'public_address' => [
            'invalid' => 'Публичный адрес раннера должен быть абсолютным http(s) URL с непустым host и без userinfo',
        ],
        'sborka_eshe_ne_zakonchena' => 'Сборка еще не завершена',
        'sborka_proekta_eshe_ne_zakonchena' => 'Сборка проекта еще не завершена',
        'sbros_podgotovki_eshe_ne_zakonchen' => 'Сброс подготовки еще не завершен',
        'step_too_large' => 'Поле Response одного шага runner\'а слишком велико',
        'steps_malformed' => 'Полезная нагрузка шагов runner\'а не является корректным JSON-массивом',
        'steps_too_large' => 'Полезная нагрузка шагов runner\'а слишком велика',
        'steps_too_many' => 'Полезная нагрузка шагов runner\'а содержит слишком много шагов',
        'udalenie_eshe_ne_zakoncheno' => 'Удаление еще не завершено',
        'udalenie_proekta_eshe_ne_zakonchena' => 'Удаление проекта еще не завершено',
        'unit_state_payload_empty' => 'Список уведомлений о состоянии контейнеров пуст.',
        'unit_state_payload_invalid' => 'Уведомление о состоянии контейнера имеет неверный формат.',
        'unit_state_payload_too_large' => 'Слишком большой объём данных уведомлений о состоянии контейнеров.',
        'unknown_error_when_make_result' => 'Неизвестная ошибка при формировании результата',
        'unknown_error_when_make_result_with_config' => 'Неизвестная ошибка при формировании результата с конфигом',
        'unknown_progress_token' => 'Токен прогресса runner\'а не зарегистрирован',
        'workflow_unit_not_found' => 'Workflow юнита не найден',
        'zapusk_eshe_ne_zakonchen' => 'Запуск еще не завершен',
        'resources' => [
            'blocker' => 'Ресурсы сервера на пределе: сборка, запуск и обновление юнитов и workspace временно заблокированы. Освободите диск или память и повторите попытку.',
        ],
        'state' => [
            'is_non_active' => 'Юнит не активен',
            'not_found' => 'Состояние runner не найдено',
        ],
    ],

    // ─── Runner secret ───────────────────────────────────────────────────────
    'runner_secret' => [
        'invalid_master_key' => 'Неверный формат мастер-ключа секретов раннера',
        'master_key_not_configured' => 'Мастер-ключ секретов раннера не задан',
    ],

    // ─── Security ────────────────────────────────────────────────────────────
    'security' => [
        'access_denied' => 'Доступ запрещен',
    ],

    // ─── Personal storage tokens ─────────────────────────────────────────────
    'lichniy_token' => [
        'ne_nayden' => 'Личный токен не найден',
        'chuzhoy' => 'Личный токен принадлежит другому пользователю',
        'pustoy_token' => 'Токен не может быть пустым',
        'ne_validen' => 'Токен невалиден для выбранного репозитория',
        'dublikat' => 'Такой токен уже добавлен для этого репозитория',
        'id_is_empty' => 'Идентификатор личного токена не может быть пустым',
        'vladelec_ne_ukazan' => 'Не указан владелец личного токена',
        'repo_ne_ukazan' => 'Не указан репозиторий личного токена',
        'pustoe_nazvanie' => 'Название личного токена не может быть пустым',
        'key_not_configured' => 'Ключ шифрования личных токенов не задан',
        'invalid_key' => 'Некорректный ключ шифрования личных токенов',
        'encrypt_failed' => 'Не удалось зашифровать личный токен',
        'decrypt_failed' => 'Не удалось расшифровать личный токен',
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
        'runner_not_bound' => 'Юнит не привязан к раннеру',
        'runner_public_address_missing' => 'У раннера, к которому привязан юнит, нет публичного адреса',
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
        'projects_conflict' => 'Один и тот же проект указан несколько раз',
        'projects_invalid' => 'Список проектов содержит некорректный элемент',
        'projects_required' => 'Нужен хотя бы один проект',
        'projects_too_many' => 'Слишком много проектов для одной операции',
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
        'zanyat' => 'Юнит занят',
        'zanyat_hodom_opencode' => 'Юнит занят ходом OpenCode',
        'zanyatost' => [
            'tip_neizvesten' => 'Неизвестный тип занятости юнита',
        ],
        'zapushen' => 'Юнит уже запущен',
        'redaktor' => [
            'net_lichnogo_tokena' => 'У пользователя нет личного токена для репозитория юнита',
            'token_bez_prav_na_proekt' => 'У личного токена нет прав на проект юнита',
            'ne_avtor' => 'Управлять редакторами юнита может только автор или админ с личным токеном',
            'vetka_zashishena' => 'Редактирование запрещено для защищённой ветки юнита',
            'redaktor_uzhe_prikreplen' => 'Пользователь уже прикреплён редактором к юниту',
            'redaktor_ne_nayden' => 'Пользователь для прикрепления редактором не найден',
            'redaktor_bez_tokena' => 'У пользователя нет личного токена с правами на проект юнита',
            'net_dostupa_k_proektu' => 'У пользователя нет доступа к проекту юнита',
            'ssylka_nevalidna' => 'Ссылка редактора недействительна',
            'ssylka_istekla' => 'Срок действия ссылки редактора истёк',
        ],
        'variables' => [
            'encrypt_failed' => 'Не удалось зашифровать hidden-переменную юнита',
            'decrypt_failed' => 'Не удалось расшифровать hidden-переменную юнита',
            'runner_id_empty' => 'Для hidden-переменной юнита не указан раннер',
            'master_key_not_configured' => 'Мастер-ключ секретов раннеров не настроен',
            'invalid_master_key' => 'Мастер-ключ секретов раннеров имеет неверный формат',
            'hidden_requires_runner' => 'Для hidden-переменной юнита нужен конкретный раннер',
        ],
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
        'otlozhennoe_deistvie' => [
            'tip_ne_podderzhan' => 'Такой тип отложенного действия не поддерживается',
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
        'job_payload_too_large' => 'Полезная нагрузка задания вебхука слишком велика',
        'payload_malformed' => 'Тело вебхука не является корректным JSON-объектом',
        'payload_too_large' => 'Тело вебхука слишком велико',
    ],

    // ─── Notifications ───────────────────────────────────────────────────────
    'notification' => [
        'not_found' => 'Уведомление не найдено',
        'type_not_found' => 'Неизвестный тип уведомления',
        'preference' => [
            'invalid_payload' => 'Некорректное тело настройки уведомлений',
            'system_type' => 'Системные уведомления нельзя отключить',
        ],
        'push' => [
            'endpoint_invalid' => 'Некорректный адрес push-подписки',
            'keys_invalid' => 'Некорректные ключи push-подписки',
        ],
        'runner_threat' => [
            'push_title' => 'Критическая угроза раннера',
            'push_body' => 'Раннер %hostname%: уровень %level%, память %memory%.',
        ],
        'unit_started' => [
            'push_title' => 'Юнит запущен',
            'push_body' => 'Юнит %unitName% успешно запущен.',
        ],
        'account_activation_request' => [
            'push_title' => 'Заявка на активацию аккаунта',
            'push_body' => 'Пользователь %email% ожидает активации.',
        ],
    ],

    // ─── Email ───────────────────────────────────────────────────────────────
    'email' => [
        'invalid' => 'Некорректный email',
        'is' => [
            'empty' => 'Email не может быть пустым',
        ],
        'temporary_password' => [
            'subject' => 'Unitman: пароль для входа',
            'greeting' => 'Здравствуйте, %name%!',
            'intro' => 'Администратор создал для вас аккаунт в Unitman. Для входа используйте временный пароль:',
            'password_label' => 'Временный пароль:',
            'outro' => 'После входа рекомендуем сменить пароль в настройках аккаунта.',
        ],
        'email_verification' => [
            'subject' => 'Unitman: код подтверждения email',
            'greeting' => 'Здравствуйте, %name%!',
            'intro' => 'Для подтверждения email введите код:',
            'code_label' => 'Код подтверждения:',
            'expires' => 'Код действует %minutes% минут.',
            'outro' => 'Если вы не регистрировались в Unitman, просто проигнорируйте это письмо.',
        ],
    ],

    // ─── Infrastructure / utils ──────────────────────────────────────────────
    'projections_manager' => [
        'rebuild_not_allowed' => 'Пересборка этой проекции запрещена',
    ],
    'utils' => [
        'projection_not_found_by_name' => 'Проекция не найдена',
    ],
];
