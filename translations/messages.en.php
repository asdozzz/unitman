<?php

declare(strict_types=1);

/**
 * CANONICAL dictionary.
 *
 * This file is the source of truth: every exception key used in the code MUST
 * be present here. Other locales (e.g. messages.ru.php) are translations of
 * this file and may omit keys — the translator then falls back to this canon.
 *
 * Key convention: <module>.<object>.<condition>, lowercase ASCII, snake_case.
 * See docs/translations.md.
 */
return [
    // ─── Account ─────────────────────────────────────────────────────────────
    'account' => [
        'as_system_not_allowed' => 'The system account cannot add users',
        'not_authenticated' => 'The account is not authenticated',
        'not_found' => 'The account was not found',
        'already_blocked' => 'The account is already blocked',
        'is_not_blocked' => 'The account is not blocked',
        'login_with_system_account_denied' => 'Signing in with a system account is not allowed',
        'settings_not_found' => 'The account settings were not found',
        'system_account_already_exists' => 'A system account already exists',
        'system_account_not_found' => 'The system account was not found',
        'locale_not_supported' => 'The requested locale is not supported',
        'nickname_invalid' => 'The nickname is invalid',
        'nickname_is_empty' => 'The nickname must not be empty',
        'old_email_equal_new_email' => 'The new email matches the current one',
        'old_locale_equal_new_locale' => 'The new locale matches the current one',
        'old_nickname_equal_new_nickname' => 'The new nickname matches the current one',
        'old_password_equal_new_password' => 'The new password matches the current one',
        'old_role_equal_new_role' => 'The new role matches the current one',
        'email' => [
            'double' => 'An account with this email already exists',
        ],
        'nickname' => [
            'double' => 'An account with this nickname already exists',
        ],
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
        'opencode_credential' => [
            'decrypt_failed' => 'Unable to decrypt the OpenCode API key',
            'empty_id' => 'The credential ID must not be empty',
            'empty_account_id' => 'The credential account is missing',
            'empty_api_key' => 'The OpenCode API key must not be empty',
            'empty_name' => 'The credential name must not be empty',
            'empty_url' => 'The OpenCode URL must not be empty',
            'empty_model' => 'The OpenCode model must not be empty',
            'empty_models' => 'The OpenCode model list must not be empty',
            'empty_provider' => 'The OpenCode provider must not be empty',
            'encrypt_failed' => 'Unable to encrypt the OpenCode API key',
            'duplicate_model' => 'The OpenCode model is listed more than once',
            'invalid_key' => 'The OpenCode encryption key is invalid',
            'key_not_configured' => 'The OpenCode encryption key is not configured',
            'model_not_available' => 'The selected OpenCode model is not available for this credential',
            'model_too_long' => 'The OpenCode model name is too long',
            'name_double' => 'A credential with that name already exists',
            'name_too_long' => 'The credential name is too long',
            'not_found' => 'The OpenCode credential was not found',
            'provider_empty_response' => 'The OpenCode provider returned no models',
            'provider_error' => 'The OpenCode provider returned an error',
            'provider_invalid_response' => 'The OpenCode provider response is invalid',
            'provider_list_not_supported' => 'The provider does not support retrieving the model list',
            'provider_response_too_large' => 'The OpenCode provider response is too large',
            'provider_unavailable' => 'The OpenCode provider is unavailable',
            'too_many_models' => 'Too many OpenCode models',
            'unknown_provider' => 'Unknown OpenCode provider',
            'url_too_long' => 'The OpenCode URL is too long',
        ],
    ],

    // ─── Shared ──────────────────────────────────────────────────────────────
    'common' => [
        'internal_error' => 'An internal error occurred',
    ],

    // ─── Shared value objects ────────────────────────────────────────────────
    'email' => [
        'invalid' => 'The email is invalid',
        'is' => [
            'empty' => 'The email must not be empty',
        ],
    ],
    'password' => [
        'empty' => 'The password must not be empty',
    ],

    // ─── Background jobs ─────────────────────────────────────────────────────
    'background_job' => [
        'group_unknown' => 'The background job group is unknown',
    ],
    'job' => [
        'not_found_by_name' => 'The job was not found by name',
    ],

    // ─── Mediateka ───────────────────────────────────────────────────────────
    'mediateka' => [
        'unit_id_is_empty' => 'The unit ID must not be empty',
        'bind' => [
            'body_format_invalid' => 'The request body format is invalid',
            'file_belongs_to_another_unit' => 'The file belongs to another unit',
            'file_id_is_empty' => 'The file ID must not be empty',
            'file_ids_format_invalid' => 'The file IDs format is invalid',
            'file_not_found' => 'The file was not found',
            'unit_id_is_empty' => 'The unit ID must not be empty',
        ],
        'file' => [
            'id_is_empty' => 'The file ID must not be empty',
            'is_linked' => 'The file is already linked',
            'is_project_file' => 'The file belongs to the project and cannot be modified',
            'not_found' => 'The file was not found',
            'owner_is_ambiguous' => 'The file owner is ambiguous',
            'project_id_is_empty' => 'The project ID must not be empty',
            'size_is_negative' => 'The file size must not be negative',
            'storage_key_is_empty' => 'The storage key must not be empty',
            'unit_id_is_empty' => 'The unit ID must not be empty',
        ],
        'upload' => [
            'file_is_empty' => 'The uploaded file is empty',
            'file_is_missing' => 'No file was uploaded',
            'file_is_not_zip' => 'The uploaded file must be a ZIP archive',
            'file_is_too_large' => 'The uploaded file is too large',
            'file_is_unreadable' => 'The uploaded file cannot be read',
            'name_is_empty' => 'The file name must not be empty',
            'name_is_too_long' => 'The file name is too long',
            'owner_is_ambiguous' => 'The file owner is ambiguous',
            'presign_ttl_invalid' => 'The presigned upload TTL is invalid',
            'size_mismatch' => 'The uploaded file size does not match the declared size',
            'token_expired' => 'The upload token has expired',
            'token_invalid' => 'The upload token is invalid',
        ],
    ],

    // ─── Upload token ────────────────────────────────────────────────────────
    'token' => [
        'content_type_is_empty' => 'The upload token content type must not be empty',
        'expires_at_is_invalid' => 'The upload token expiration is invalid',
        'file_id_is_empty' => 'The upload token file ID must not be empty',
        'name_is_empty' => 'The upload token file name must not be empty',
        'owner_is_ambiguous' => 'The upload token owner is ambiguous',
        'project_id_is_empty' => 'The upload token project ID must not be empty',
        'size_is_invalid' => 'The upload token file size is invalid',
        'storage_key_is_empty' => 'The upload token storage key must not be empty',
        'unit_id_is_empty' => 'The upload token unit ID must not be empty',
        'uploaded_by_is_empty' => 'The upload token author must not be empty',
    ],

    // ─── OpenCode ────────────────────────────────────────────────────────────
    'opencode' => [
        'notification' => [
            'ids_required' => 'The notification IDs must not be empty',
            'too_many_ids' => 'Too many notifications: at most 100 per request',
        ],
        'session' => [
            'account_required' => 'The session owner is missing',
            'archive_reason_required' => 'The archive reason of the session is missing',
            'archived' => 'The session is archived: new turns are not allowed',
            'credential_required' => 'An OpenCode credential must be selected',
            'id_required' => 'The session ID must not be empty',
            'not_found' => 'The OpenCode session was not found',
            'too_many_units' => 'A session can be attached to at most 10 units',
            'unit_busy' => 'An active session already exists for this unit',
            'unit_not_editor' => 'Only a unit editor can start a session for this unit',
            'unit_not_found' => 'The unit was not found or is not accessible',
            'unit_not_ready' => 'One or more units are still building or starting — wait for them to finish',
            'unit_project_conflict' => 'Only one unit per project can be attached to a session',
            'unit_project_required' => 'The project ID of the unit is missing',
            'unit_required' => 'The unit ID must not be empty',
            'unit_source_conflict' => 'A unit and a new unit cannot be specified at the same time',
        ],
        'workspace' => [
            'archived' => 'The workspace is archived',
            'runner_not_bound' => 'The workspace is not bound to a runner',
            'runner_public_address_missing' => 'The runner bound to the workspace has no public address',
            'branch_protected' => 'The branch is protected in one of the projects and cannot be used for a workspace',
            'branch_required' => 'The workspace branch must not be empty',
            'branch_too_long' => 'The workspace branch is too long',
            'confirmation_required' => 'The workspace has uncommitted changes: confirm the operation',
            'delete_in_progress' => 'The workspace is already being deleted',
            'initial_prompt_failed' => 'The initial workspace prompt failed to start',
            'invalid_port' => 'The port must be between 1 and 65535',
            'invalid_status' => 'The stored workspace status is invalid',
            'invalid_transition' => 'The workspace status transition is not allowed',
            'link_unavailable' => 'The workspace link is unavailable: the runner public address is not configured',
            'name_already_exists' => 'A workspace with that name already exists',
            'name_required' => 'The workspace name must not be empty',
            'name_too_long' => 'The workspace name is too long',
            'not_found' => 'The workspace was not found',
            'not_running' => 'The workspace is not running',
            'operation_failed' => 'The workspace operation failed',
            'project_branch_not_found' => 'The project branch was not found',
            'project_clone_failed' => 'Failed to clone a project into the workspace',
            'project_conflict' => 'The same project is specified more than once',
            'project_not_found' => 'The project was not found or is not accessible',
            'projects_required' => 'At least one project is required',
            'prompt_required' => 'The initial prompt must not be empty',
            'unit_conflict' => 'The same unit is specified more than once',
            'unit_invalid' => 'The workspace unit snapshot is invalid',
            'units_already_created' => 'Units have already been created for this workspace',
            'read_only_push_forbidden' => 'The workspace is read-only: push is forbidden',
            'too_many_projects' => 'A workspace can contain at most 20 projects',
            'uncommitted_changes' => 'The workspace has uncommitted changes',
        ],
        'turn' => [
            'answers_required' => 'At least one answer is required',
            'awaiting_answer' => 'The turn is already waiting for an answer',
            'failed' => 'The OpenCode turn failed',
            'invalid_status' => 'The stored turn status is invalid',
            'not_awaiting_answer' => 'The turn is not waiting for an answer',
            'not_found' => 'The OpenCode turn was not found',
            'option_required' => 'An answer option must be selected',
            'request_required' => 'The prompt must not be empty',
            'unexpected_kind' => 'The runner returned an unknown result kind',
            'unit_directory_not_found' => 'The unit directory was not found on the runner',
            'unknown_option' => 'The selected answer option does not exist',
            'unknown_question' => 'The answered question does not exist',
        ],
    ],

    // ─── Projects ────────────────────────────────────────────────────────────
    'project' => [
        'already_built' => 'The project is already built',
        'already_disabled' => 'The project is already disabled',
        'already_enabled' => 'The project is already enabled',
        'already_in_queue' => 'The project is already queued',
        'branch_bez_prav' => 'The personal token has no rights to create a project branch',
        'branch_exists' => 'The project branch already exists',
        'branch_invalid' => 'The project branch name is invalid',
        'building_already_execute' => 'A build is already running',
        'code_is_empty' => 'The project code must not be empty',
        'double' => 'A project with that name already exists',
        'eshe_ne_sobran' => 'The project has not been built yet',
        'host_invalid' => 'The project host is invalid',
        'host_is_empty' => 'The project host must not be empty',
        'id_is_empty' => 'The project ID must not be empty',
        'in_pending_for_build' => 'The project is pending a build',
        'length_name_invalid' => 'The project name length is invalid',
        'name_invalid' => 'The project name is invalid',
        'name_is_empty' => 'The project name must not be empty',
        'net_lichnogo_tokena' => 'The user has no personal token for the project repository',
        'not_found' => 'The project was not found',
        'not_found_data_about_building' => 'No build data was found for the project',
        'not_found_data_about_removing' => 'No removal data was found for the project',
        'ochistka_ne_nachalas' => 'The project cleanup has not started',
        'ochistka_uge_nachalas' => 'The project cleanup has already started',
        'old_name_equal_new_name' => 'The new name matches the current one',
        'pending_building' => 'The project is pending a build',
        'project_already_remove' => 'The project has already been removed',
        'removed' => 'The project has been removed',
        'removing' => 'The project is being removed',
        'removing_already_execute' => 'Removal is already running',
        'resultat_ochistki_uge_ustanovlen' => 'The cleanup result is already set',
        'runner_job_not_found' => 'The runner job was not found',
        'user_already_exist' => 'The user already belongs to the project',
        'user_has_system_role' => 'The system account cannot be added to a project',
        'user_not_found' => 'The user was not found',
        'user_not_found_in_project' => 'The user is not a member of the project',
        'zashishennie_vetki' => [
            'pusta_stroka' => 'A protected branch pattern must not be empty',
            'slishkom_dlinnaya' => 'A protected branch pattern is too long',
            'dublikat' => 'The protected branch pattern is duplicated',
            'slishkom_mnogo' => 'Too many protected branch patterns',
        ],
        'variable' => [
            'code_is_empty' => 'The variable code must not be empty',
            'duplicate' => 'The variable is already defined',
            'new_value_equal_old_value' => 'The new value matches the current one',
            'not_found' => 'The variable was not found',
            'value_is_empty' => 'The variable value must not be empty',
        ],
        'webhook' => [
            'id_is_empty' => 'The webhook ID must not be empty',
            'projectId_is_empty' => 'The webhook project ID must not be empty',
            'new_url_equals_old_url' => 'The new URL matches the current one',
            'not_found_by_id' => 'The webhook was not found',
            'url_invalid' => 'The webhook URL is invalid',
            'url_is_empty' => 'The webhook URL must not be empty',
        ],
        'websocket_project_event' => [
            'not_found_id' => 'The websocket project event was not found',
        ],
    ],

    // Legacy spelling kept because it is used in code and tests.
    'proekt' => [
        'webhook' => [
            'double' => 'The webhook already exists',
        ],
    ],

    // ─── Repos ───────────────────────────────────────────────────────────────
    'repo' => [
        'already_deleted' => 'The repository has already been deleted',
        'deleted' => 'The repository has been deleted',
        'id_is_empty' => 'The repository ID must not be empty',
        'length_name_invalid' => 'The repository name length is invalid',
        'name_is_empty' => 'The repository name must not be empty',
        'new_credentials_equals_old' => 'The new credentials match the current ones',
        'not_found' => 'The repository was not found',
        'url_is_empty' => 'The repository URL must not be empty',
        'credentials' => [
            'token_is_empty' => 'The access token must not be empty',
            'url_invalid' => 'The repository URL is invalid',
            'url_is_empty' => 'The repository URL must not be empty',
        ],
        'delete' => [
            'est_proekti' => 'The repository cannot be deleted while projects use it',
        ],
        'name' => [
            'double' => 'A repository with that name already exists',
        ],
    ],

    // ─── Runner ──────────────────────────────────────────────────────────────
    'runner' => [
        'deistvie_ne_zakonchena' => 'The action has not finished yet',
        'health_payload_too_large' => 'Runner health payload is too large.',
        'health_runner_id_empty' => 'Runner id is empty.',
        'inactive' => 'The runner is inactive',
        'invalid_signature' => 'The runner request signature is invalid',
        'memory_free_is_to_small' => 'Not enough free memory to start the unit',
        'not_found' => 'The runner was not found',
        'obnovlenie_eshe_ne_zakoncheno' => 'The update has not finished yet',
        'oshibka_proverki_unita' => 'The unit check failed',
        'ostanovka_eshe_ne_zakonchena' => 'The stop operation has not finished yet',
        'podgotovka_eshe_ne_zakonchena' => 'The preparation has not finished yet',
        'public_address' => [
            'invalid' => 'The runner public address must be an absolute http(s) URL with a host and without userinfo',
        ],
        'sborka_eshe_ne_zakonchena' => 'The build has not finished yet',
        'sborka_proekta_eshe_ne_zakonchena' => 'The project build has not finished yet',
        'sbros_podgotovki_eshe_ne_zakonchen' => 'The preparation reset has not finished yet',
        'step_too_large' => 'The runner step response is too large',
        'steps_malformed' => 'The runner steps payload is not a valid JSON array',
        'steps_too_large' => 'The runner steps payload is too large',
        'steps_too_many' => 'The runner steps payload contains too many steps',
        'udalenie_eshe_ne_zakoncheno' => 'The removal has not finished yet',
        'udalenie_proekta_eshe_ne_zakonchena' => 'The project removal has not finished yet',
        'unit_state_payload_empty' => 'Container state notifications list is empty.',
        'unit_state_payload_invalid' => 'A container state notification has an invalid format.',
        'unit_state_payload_too_large' => 'Container state notifications payload is too large.',
        'unknown_error_when_make_result' => 'An unknown error occurred while building the result',
        'unknown_error_when_make_result_with_config' => 'An unknown error occurred while building the result with the config',
        'unknown_progress_token' => 'The runner progress token is not registered',
        'workflow_unit_not_found' => 'The unit workflow was not found',
        'zapusk_eshe_ne_zakonchen' => 'The start operation has not finished yet',
        'resources' => [
            'blocker' => 'Server resources are exhausted: building, starting and updating units and workspaces is temporarily blocked. Free up disk or memory and try again.',
        ],
        'state' => [
            'is_non_active' => 'The unit is not active',
            'not_found' => 'The runner state was not found',
        ],
    ],

    // ─── Security ────────────────────────────────────────────────────────────
    'security' => [
        'access_denied' => 'Access denied',
    ],

    // ─── Personal storage tokens ─────────────────────────────────────────────
    'lichniy_token' => [
        'ne_nayden' => 'The personal token was not found',
        'chuzhoy' => 'The personal token belongs to another user',
        'pustoy_token' => 'The token must not be empty',
        'ne_validen' => 'The token is invalid for the selected repository',
        'dublikat' => 'The same token is already added for this repository',
        'id_is_empty' => 'The personal token ID must not be empty',
        'vladelec_ne_ukazan' => 'The personal token owner is not specified',
        'repo_ne_ukazan' => 'The personal token repository is not specified',
        'pustoe_nazvanie' => 'The personal token name must not be empty',
        'key_not_configured' => 'Personal token encryption key is not configured',
        'invalid_key' => 'Invalid personal token encryption key',
        'encrypt_failed' => 'Failed to encrypt the personal token',
        'decrypt_failed' => 'Failed to decrypt the personal token',
    ],

    // ─── Units ───────────────────────────────────────────────────────────────
    'unit' => [
        'author_must_have_system_role' => 'Only the system account can create a unit on behalf of the system',
        'avtosozdanie_viklucheno' => 'Automatic unit creation is disabled',
        'branch_is_empty' => 'The branch must not be empty',
        'branch_not_found' => 'The branch was not found',
        'config_is_empty' => 'The unit config must not be empty',
        'config_not_found' => 'The unit config was not found',
        'config_unita_ne_opredelen' => 'The unit config is not defined',
        'authorId_is_empty' => 'The unit author ID must not be empty',
        'double' => 'A unit with that name already exists',
        'id_is_empty' => 'The unit ID must not be empty',
        'runner_not_bound' => 'The unit is not bound to a runner',
        'runner_public_address_missing' => 'The runner bound to the unit has no public address',
        'projectId_is_empty' => 'The unit project ID must not be empty',
        'job_not_found' => 'The unit job was not found',
        'length_name_invalid' => 'The unit name length is invalid',
        'memory_limit_min_300' => 'The memory limit must be at least 300 MB',
        'name_already_exists' => 'A unit with that name already exists',
        'name_invalid' => 'The unit name is invalid',
        'name_is_empty' => 'The unit name must not be empty',
        'name_not_found' => 'The unit name was not found',
        'ne_hvataet_prav' => 'Insufficient permissions',
        'ne_zapushen' => 'The unit is not running',
        'not_found' => 'The unit was not found',
        'podgotovka_ne_nachalas' => 'The preparation has not started',
        'podgotovka_uge_bila' => 'The unit has already been prepared',
        'project_is_disabled' => 'The project is disabled',
        'project_not_found' => 'The project was not found',
        'projects_conflict' => 'The same project is specified more than once',
        'projects_invalid' => 'The project list contains an invalid item',
        'projects_required' => 'At least one project is required',
        'projects_too_many' => 'Too many projects for a single operation',
        'prozes_ochistki_ne_naiden' => 'The cleanup process was not found',
        'prozes_sborki_ne_naiden' => 'The build process was not found',
        'prozes_udaleniya_ne_naiden' => 'The removal process was not found',
        'resultat_deistviya_uge_ustanovlen' => 'The action result is already set',
        'runner_job_not_found' => 'The runner job was not found',
        'sborka' => [
            'jobId_is_empty' => 'The build job ID must not be empty',
        ],
        'sborka_ne_nachalas' => 'The build has not started',
        'state_not_found' => 'The unit state was not found',
        'udalen' => 'The unit has been removed',
        'uge_sobran' => 'The unit has already been built',
        'uge_udalen' => 'The unit has already been removed',
        'vipolnenieDeistviya_ne_nachalas' => 'The action execution has not started',
        'wait_another_proccess' => 'Another process is still running',
        'wait_runner' => 'The runner is still busy',
        'zanyat' => 'The unit is busy',
        'zanyat_hodom_opencode' => 'The unit is busy with an OpenCode turn',
        'zanyatost' => [
            'tip_neizvesten' => 'Unknown unit occupancy type',
        ],
        'zapushen' => 'The unit is already running',
        'redaktor' => [
            'net_lichnogo_tokena' => 'The user has no personal token for the unit repository',
            'token_bez_prav_na_proekt' => 'The personal token has no rights on the unit project',
            'ne_avtor' => 'Only the unit author or an admin with a personal token can manage unit editors',
            'vetka_zashishena' => 'Editing is forbidden for the protected branch of the unit',
            'redaktor_uzhe_prikreplen' => 'The user is already attached as an editor of the unit',
            'redaktor_ne_nayden' => 'The user to attach as an editor was not found',
            'redaktor_bez_tokena' => 'The user has no personal token with rights on the unit project',
            'net_dostupa_k_proektu' => 'The user has no access to the unit project',
            'ssylka_nevalidna' => 'The editor link is invalid',
            'ssylka_istekla' => 'The editor link has expired',
        ],
        'config' => [
            'prepare_is_empty' => 'The "prepare" block must not be empty',
            'resetPrepare_is_empty' => 'The "reset_prepare" block must not be empty',
            'up_is_empty' => 'The "up" block must not be empty',
            'down_is_empty' => 'The "down" block must not be empty',
            'services_is_empty' => 'The "services" block must not be empty',
            'type_for_variable_not_found' => 'The variable type was not found',
            'values_format_invalid' => 'The variable values format is invalid',
            'action' => [
                'id_not_defined' => 'The action ID is not defined',
                'name_not_defined' => 'The action name is not defined',
                'commands_not_defined' => 'The action commands are not defined',
                'commands_is_not_array' => 'The action commands must be an array',
                'variables_is_not_array' => 'The action variables must be an array',
            ],
            'port' => [
                'invalid' => 'The port is invalid',
                'type' => [
                    'is_empty' => 'The port type must not be empty',
                ],
            ],
            'pomoschnik' => [
                'port_invalid' => 'The helper port is invalid',
                'projectId_is_empty' => 'The helper project ID must not be empty',
                'service_is_empty' => 'The helper service must not be empty',
                'type_invalid' => 'The helper type is invalid',
            ],
            'variable' => [
                'id_is_empty' => 'The variable ID must not be empty',
                'label_is_empty' => 'The variable label must not be empty',
                'label_invalid' => 'The variable label is invalid',
                'length_label_invalid' => 'The variable label length is invalid',
                'collection_options_is_empty' => 'The variable collection options must not be empty',
            ],
            'volumes' => [
                'dir_is_empty' => 'The volume directory must not be empty',
                'dir_is_not_unique' => 'The volume directory must be unique',
                'invalid_item' => 'The volume item is invalid',
                'name_is_empty' => 'The volume name must not be empty',
            ],
        ],
        'config_variable' => [
            'collection_option' => [
                'id_is_empty' => 'The collection option ID must not be empty',
                'name_is_empty' => 'The collection option name must not be empty',
            ],
        ],
        'config_variable_factory' => [
            'type_is_empty' => 'The variable type must not be empty',
            'type_invalid' => 'The variable type is invalid',
        ],
        'files' => [
            'not_found' => 'The unit files were not found',
        ],
        'konfig' => [
            'actions' => 'The unit actions are not defined',
            'deistvie' => [
                'variable_not_found_by_id' => 'The action variable was not found',
            ],
        ],
        'konfigServisa' => [
            'name_is_empty' => 'The service name must not be empty',
        ],
        'mapAction' => [
            'not_found' => 'The unit action was not found',
        ],
        'ochered_unitov' => [
            'not_found' => 'The unit queue was not found',
        ],
        'obrabotka_prozesa' => [
            'tip_ne_opredelen' => 'The process type is not defined',
        ],
        'otlozhennoe_deistvie' => [
            'tip_ne_podderzhan' => 'This deferred action type is not supported',
        ],
        'pomoschnik' => [
            'servis_ne_naiden' => 'The helper service was not found',
        ],
        'process' => [
            'already_finish' => 'The process has already finished',
        ],
        'prozess' => [
            'not_found' => 'The process was not found',
            'not_found_by_job_id' => 'The process was not found by job ID',
        ],
        'runner_service' => [
            'job_id_empty' => 'The runner job ID must not be empty',
        ],
        'spisok_unitov' => [
            'unit_not_found' => 'The unit was not found in the list',
        ],
        'state_factory' => [
            'state_not_found' => 'The unit state was not found',
        ],
        'svyaz' => [
            'invalid_type' => 'The link type is invalid',
            'self_link_is_forbidden' => 'A unit cannot be linked to itself',
            'target_unit_id_is_empty' => 'The target unit ID must not be empty',
            'target_unit_project_is_not_available' => 'The target unit project is not available',
            'variable_id_is_empty' => 'The variable ID must not be empty',
            'variable_is_not_unique' => 'The variable must be unique',
        ],
        'variable_value' => [
            'id_is_empty' => 'The variable value ID must not be empty',
            'type_is_empty' => 'The variable value type must not be empty',
            'value_is_empty' => 'The variable value must not be empty',
        ],
        'volume_type' => [
            'code_is_empty' => 'The volume type code must not be empty',
            'code_is_not_unique' => 'The volume type code must be unique',
            'code_is_too_long' => 'The volume type code is too long',
            'file_already_linked' => 'The file is already linked to the volume type',
            'file_belongs_to_unit' => 'The file belongs to a unit',
            'file_id_is_empty' => 'The file ID must not be empty',
            'file_not_found' => 'The file was not found',
            'file_not_linked' => 'The file is not linked to the volume type',
            'has_files' => 'The volume type still has files',
            'id_is_empty' => 'The volume type ID must not be empty',
            'name_is_empty' => 'The volume type name must not be empty',
            'name_is_too_long' => 'The volume type name is too long',
            'not_found' => 'The volume type was not found',
            'project_id_is_empty' => 'The project ID must not be empty',
        ],
        'volume_type_file' => [
            'file_id_is_empty' => 'The file ID must not be empty',
            'project_id_is_empty' => 'The project ID must not be empty',
            'type_id_is_empty' => 'The volume type ID must not be empty',
        ],
        'volume_value' => [
            'dir_is_empty' => 'The volume directory must not be empty',
            'file_id_is_empty' => 'The file ID must not be empty',
            'name_is_empty' => 'The volume name must not be empty',
        ],
        'volumes' => [
            'dir_is_empty' => 'The volume directory must not be empty',
            'dir_is_not_unique' => 'The volume directory must be unique',
            'dir_not_found' => 'The volume directory was not found',
            'file_not_found' => 'The volume file was not found',
            'file_not_in_catalog' => 'The file is not in the catalog',
            'file_type_mismatch' => 'The file type does not match',
        ],
        'webhook' => [
            'not_found_by_id' => 'The webhook was not found',
        ],
        'websocket_unit_event' => [
            'not_found_id' => 'The websocket unit event was not found',
        ],
    ],

    // ─── Storage events ──────────────────────────────────────────────────────
    'sobitie_iz_hranilisha' => [
        'commits_not_found' => 'No commits were found in the repository',
        'job_payload_too_large' => 'The webhook job payload is too large',
        'payload_malformed' => 'The webhook payload is not a valid JSON object',
        'payload_too_large' => 'The webhook payload is too large',
    ],

    // ─── Infrastructure / utils ──────────────────────────────────────────────
    'projections_manager' => [
        'rebuild_not_allowed' => 'Rebuilding this projection is not allowed',
    ],
    'utils' => [
        'projection_not_found_by_name' => 'The projection was not found',
    ],
];
