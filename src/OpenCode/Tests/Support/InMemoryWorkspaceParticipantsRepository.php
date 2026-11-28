<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Support;

use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;

/**
 * Участники нового агрегата в памяти для use case-тестов.
 *
 * Повторяет правила SQL-репозитория: владельца нельзя удалить, сохранение
 * перезаписывает строку по составному ключу `workspace_id + account_id`.
 */
final class InMemoryWorkspaceParticipantsRepository implements WorkspaceParticipantsRepository
{
    /**
     * @var array<string, UchastnikSessii>
     */
    public array $uchastniki = [];

    public ?UchastnikSessii $udalennyi = null;

    public function findByWorkspaceId(string $workspaceId): array
    {
        return array_values(array_filter(
            $this->uchastniki,
            static fn (UchastnikSessii $uchastnik): bool => $uchastnik->getSessionId() === $workspaceId,
        ));
    }

    public function findByWorkspaceIds(array $workspaceIds): array
    {
        $ids = array_flip($workspaceIds);
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

    public function find(string $workspaceId, string $accountId): ?UchastnikSessii
    {
        return $this->uchastniki[$this->key($workspaceId, $accountId)] ?? null;
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

    private function key(string $workspaceId, string $accountId): string
    {
        return $workspaceId . '|' . $accountId;
    }
}
