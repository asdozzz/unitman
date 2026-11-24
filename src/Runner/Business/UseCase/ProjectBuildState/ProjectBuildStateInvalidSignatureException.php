<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase\ProjectBuildState;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Подпись callback-запроса отсутствует или не совпадает.
 */
final class ProjectBuildStateInvalidSignatureException extends UnauthorizedHttpException
{
    public function __construct()
    {
        parent::__construct('RunnerSignature', 'runner.invalid_signature');
    }
}
