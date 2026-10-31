<?php

return [
    'account' => [
        'as_system_not_allowed' => 'The system account cannot add users',
        'not_authenticated' => 'The account is not authenticated',
        'refresh_token' => [
            'empty' => 'The refresh token is missing',
            'empty_hash' => 'The refresh token hash is empty',
            'empty_account_id' => 'The refresh token account is missing',
            'invalid_expires_at' => 'The refresh token lifetime is invalid',
            'not_found' => 'The refresh token was not found',
            'revoked' => 'The refresh token was revoked',
            'already_revoked' => 'The refresh token is already revoked',
            'expired' => 'The refresh token has expired',
        ],
        'access_token' => [
            'invalid_payload' => 'Unable to issue an access token',
        ],
    ],
    'unit' => [
        'name_already_exists' => 'A unit with that name already exists'
    ]
];
