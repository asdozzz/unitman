<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Support;

use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;

/**
 * Участники в памяти для use case-тестов.
 *
 * Повторяет правила SQL-репозитория: владельца нельзя удалить, а сохранение
 * перезаписывает строку по составному ключу.
 */
final class InMemoryUchastnikiSessiiRepository implements UchastnikiSessiiRepository
{
    /**
     * @var array<string, UchastnikSessii>
     */
    public array $uchastniki = [];

    public ?UchastnikSessii $udalennyi = null;

    public function findBySessionId(string $sessionId): array
    {
        return array_values(array_filter(
            $this->uchastniki,
            static fn (UchastnikSessii $uchastnik): bool => $uchastnik->getSessionId() === $sessionId,
        ));
    }

    public function findBySessionIds(array $sessionIds): array
    {
        $ids = array_flip($sessionIds);
        $result = [];

        foreach ($this->uchastniki as $uchastnik) {
            if (isset($ids[$uchastnik->getSessionId()])) {
                $result[$uchastnik->getSessionId()][] = $uchastnik;
            }
        }

        return $result;
    }

    public function findByAccountId(string $accountId): array
    {
        return array_values(array_filter(
            $this->uchastniki,
            static fn (UchastnikSessii $uchastnik): bool => $uchastnik->getAccountId() === $accountId,
        ));
    }

    public function find(string $sessionId, string $accountId): ?UchastnikSessii
    {
        return $this->uchastniki[$this->key($sessionId, $accountId)] ?? null;
    }

    public function save(UchastnikSessii $uchastnik): void
    {
        $this->uchastniki[$this->key($uchastnik->getSessionId(), $uchastnik->getAccountId())] = $uchastnik;
    }

    public function udalit(UchastnikSessii $uchastnik): void
    {
        $uchastnik->trebovatMozhnoUdalit();

        unset($this->uchastniki[$this->key($uchastnik->getSessionId(), $uchastnik->getAccountId())]);
        $this->udalennyi = $uchastnik;
    }

    private function key(string $sessionId, string $accountId): string
    {
        return $sessionId . '|' . $accountId;
    }
}
