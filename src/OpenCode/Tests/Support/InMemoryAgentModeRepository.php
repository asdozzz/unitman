<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Support;

use App\OpenCode\Business\AgentMode\Model\AgentMode;
use App\OpenCode\Business\AgentMode\Model\NastroykaRezhimaAgenta;
use App\OpenCode\Business\AgentMode\Port\AgentModeRepository;

final class InMemoryAgentModeRepository implements AgentModeRepository
{
    /**
     * @var array<string, AgentMode>
     */
    public array $modes = [];

    /**
     * @var array<string, NastroykaRezhimaAgenta>
     */
    public array $nastroyki = [];

    /**
     * @var array<string, string>
     */
    public array $defaulty = [];

    public function findById(string $id): ?AgentMode
    {
        return $this->modes[$id] ?? null;
    }

    public function findDostupnye(string $accountId): array
    {
        return array_values(array_filter(
            $this->modes,
            static fn (AgentMode $mode): bool => $mode->dostupen($accountId),
        ));
    }

    public function findGlobal(bool $includeArchived = false): array
    {
        return array_values(array_filter(
            $this->modes,
            static fn (AgentMode $mode): bool => $mode->isGlobal()
                && ($includeArchived || !$mode->isArchived()),
        ));
    }

    public function findGlobalBySlug(string $slug): ?AgentMode
    {
        foreach ($this->modes as $mode) {
            if ($mode->isGlobal() && $mode->getSlug() === $slug && !$mode->isArchived()) {
                return $mode;
            }
        }

        return null;
    }

    public function findPersonalBySlug(string $accountId, string $slug): ?AgentMode
    {
        foreach ($this->modes as $mode) {
            if (!$mode->isGlobal()
                && $mode->getOwnerAccountId() === $accountId
                && $mode->getSlug() === $slug
                && !$mode->isArchived()
            ) {
                return $mode;
            }
        }

        return null;
    }

    public function save(AgentMode $mode): void
    {
        $this->modes[$mode->getId()] = $mode;
    }

    public function update(AgentMode $mode): void
    {
        $this->modes[$mode->getId()] = $mode;
    }

    public function findNastroyka(string $accountId, string $modeId): ?NastroykaRezhimaAgenta
    {
        return $this->nastroyki[$accountId . ':' . $modeId] ?? null;
    }

    public function findNastroyki(string $accountId): array
    {
        $result = [];

        foreach ($this->nastroyki as $key => $nastroyka) {
            if (str_starts_with($key, $accountId . ':')) {
                $result[$nastroyka->modeId] = $nastroyka;
            }
        }

        return $result;
    }

    public function saveNastroyka(NastroykaRezhimaAgenta $nastroyka): void
    {
        $this->nastroyki[$nastroyka->accountId . ':' . $nastroyka->modeId] = $nastroyka;
    }

    public function findDefaulty(string $accountId): array
    {
        $result = [];

        foreach ($this->defaulty as $key => $modeId) {
            if (str_starts_with($key, $accountId . ':')) {
                $result[substr($key, strlen($accountId) + 1)] = $modeId;
            }
        }

        return $result;
    }

    public function saveDefault(string $accountId, string $chatType, string $modeId, int $now): void
    {
        $this->defaulty[$accountId . ':' . $chatType] = $modeId;
    }
}
