<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Support;

use App\AiChat\Business\Model\SoobshenieAiChata;
use App\AiChat\Business\Port\SoobshenieAiChataRepository;

/**
 * In-memory история сообщений AI-чата для тестов.
 *
 * Сообщения хранятся в порядке добавления, поэтому `vse()` возвращает их
 * по возрастанию `seq`, как и SQL-репозиторий.
 */
final class InMemorySoobshenieAiChataRepository implements SoobshenieAiChataRepository
{
    /**
     * @var array<string, list<SoobshenieAiChata>>
     */
    public array $soobsheniya = [];

    public function dobavit(SoobshenieAiChata ...$soobsheniya): void
    {
        foreach ($soobsheniya as $soobshenie) {
            $this->soobsheniya[$soobshenie->sessionId][] = $soobshenie;
        }
    }

    public function poslednie(string $sessionId, int $limit): array
    {
        $all = $this->soobsheniya[$sessionId] ?? [];

        return array_slice($all, max(0, \count($all) - $limit));
    }

    public function vse(string $sessionId): array
    {
        return $this->soobsheniya[$sessionId] ?? [];
    }

    public function kolichestvo(string $sessionId): int
    {
        return \count($this->soobsheniya[$sessionId] ?? []);
    }

    public function pervoe(string $sessionId): ?SoobshenieAiChata
    {
        return $this->soobsheniya[$sessionId][0] ?? null;
    }

    public function udalitPoSessii(string $sessionId): void
    {
        $this->soobsheniya[$sessionId] = [];
    }
}
