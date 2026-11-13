<?php

declare(strict_types=1);

namespace App\Runner\Infra\Adapter;

use App\Runner\Business\Model\RunnerProgress;
use App\Runner\Business\Port\UmeetChitatProgressZadachi;
use App\Runner\Business\Port\UmeetHranitProgressZadachi;
use App\Runner\Infra\Service\RedisService;

/**
 * Хранение прогресса задач раннера в RoadRunner KV (redis).
 *
 * Ключи:
 * - `runner.progress.<token>` — снапшот прогресса (TTL 2 часа);
 * - `runner.progress.token.<token>` — маркер регистрации токена (TTL 2 часа);
 * - `runner.progress.lastpub.<token>` — время последней публикации (TTL 1 секунда).
 *
 * TTL прогресса сознательно короткий: снапшот не является источником истины,
 * итоговое состояние задачи всё равно приходит через event sourcing.
 */
final class RunnerProgressKvStorage implements UmeetHranitProgressZadachi, UmeetChitatProgressZadachi
{
    private const KEY_PREFIX = 'runner.progress.';
    private const TOKEN_TTL_SECONDS = 2 * 60 * 60;
    private const PUBLISH_MARKER_TTL_SECONDS = 1;

    public function __construct(private readonly RedisService $redisService)
    {
    }

    public function zapisat(RunnerProgress $progress): void
    {
        $this->redisService->setWithTtl(
            $this->progressKey($progress->token),
            json_encode($progress->toArray(), JSON_THROW_ON_ERROR),
            self::TOKEN_TTL_SECONDS
        );
    }

    public function zapisatSyrieShagi(string $token, string $stepsContent): void
    {
        $this->redisService->setWithTtl($token, $stepsContent, self::TOKEN_TTL_SECONDS);
    }

    public function zaregistrirovat(string $token): void
    {
        $this->redisService->setWithTtl(
            $this->tokenKey($token),
            (string) time(),
            self::TOKEN_TTL_SECONDS
        );
    }

    public function estLiToken(string $token): bool
    {
        return $this->redisService->get($this->tokenKey($token)) !== null;
    }

    public function zapomnitVremyaPublikacii(string $token, int $unixtimeMs): void
    {
        $this->redisService->setWithTtl(
            $this->publishMarkerKey($token),
            (string) $unixtimeMs,
            self::PUBLISH_MARKER_TTL_SECONDS
        );
    }

    public function poluchitVremyaPublikacii(string $token): ?int
    {
        $value = $this->redisService->get($this->publishMarkerKey($token));

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function poluchit(string $token): ?RunnerProgress
    {
        $raw = $this->redisService->get($this->progressKey($token));

        if ($raw === null || $raw === '') {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $steps = $decoded['steps'] ?? [];
        /** @var list<array<array-key, mixed>> $normalizedSteps */
        $normalizedSteps = is_array($steps) ? array_values($steps) : [];

        return new RunnerProgress(
            $token,
            $normalizedSteps,
            (bool) ($decoded['finished'] ?? false),
            (bool) ($decoded['success'] ?? false),
            (int) ($decoded['unixtime'] ?? 0),
        );
    }

    private function progressKey(string $token): string
    {
        return self::KEY_PREFIX . $token;
    }

    private function tokenKey(string $token): string
    {
        return self::KEY_PREFIX . 'token.' . $token;
    }

    private function publishMarkerKey(string $token): string
    {
        return self::KEY_PREFIX . 'lastpub.' . $token;
    }
}
