<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase\RegisterRunner;

/**
 * Результат успешной регистрации раннера (контракт v2).
 *
 * `taskQueue` всегда строго равен `runnerId`: контракт
 * `plans/runner-multi-runner.md` §2.1/§4 запрещает префиксы и нормализацию.
 *
 * `secret` вычисляется детерминированно и отдаётся раннеру ровно один раз;
 * он НЕ сохраняется в БД и не логируется.
 */
final readonly class RegisterRunnerResult
{
    /**
     * @param array{api: int, temporal: int, registry: int} $ports
     */
    public function __construct(
        public string $runnerId,
        public string $taskQueue,
        public string $secret,
        public string $registryHost,
        public array $ports,
    ) {
    }
}
