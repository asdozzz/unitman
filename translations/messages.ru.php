<?php

return [
    'account' => [
        'as_system_not_allowed' => 'Системная учетная запись не может добавлять пользователей',
        'not_authenticated' => 'Учетная запись не аутентифицирован',
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
    'unit' => [
        'name_already_exists' => 'Юнит с таким именем уже существует'
    ]
];
