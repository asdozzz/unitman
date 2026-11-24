<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase\RegisterRunner;

/**
 * Тело регистрации не является JSON-объектом.
 *
 * Контракт v2 принимает только `{hostname, version}`; адрес раннера
 * задаётся администратором отдельно через `publicAddress`.
 *
 * HTTP-код: 400, код ошибки: `runner.registration.invalid_payload`.
 */
final class RunnerRegistrationInvalidPayloadException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('runner.registration.invalid_payload');
    }
}
