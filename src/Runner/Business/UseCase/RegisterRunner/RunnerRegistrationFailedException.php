<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase\RegisterRunner;

/**
 * Внутренняя ошибка сохранения состояния раннера.
 *
 * HTTP-код: 500, код ошибки: `runner.registration.failed`.
 */
final class RunnerRegistrationFailedException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('runner.registration.failed', 0, $previous);
    }
}
