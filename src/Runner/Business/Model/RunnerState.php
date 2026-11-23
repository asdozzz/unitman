<?php

declare(strict_types=1);

namespace App\Runner\Business\Model;

use App\Runner\Business\Model\RunnerState\DockerContainerStats;
use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\Model\RunnerState\RunnerResources;

final class RunnerState implements \JsonSerializable
{
    /**
     * @param DockerContainerStats[] $dockerStats
     * */
    public function __construct(
        private string $id,
        private string $taskQueue,
        private bool $active,
        private array $dockerStats = [],
        private MemoryInfo $memoryInfo = new MemoryInfo(0, 0),
        private int $unixtimeLastHealth = 0,
        private ?RunnerResources $resources = null,
    ) {
    }

    public static function sozdatRunner(string $id, string $taskQueue): self
    {
        return new self($id, $taskQueue, true);
    }

    public static function sozdatDefoltniiRunner(string $id): self
    {
        return new self($id, 'unitman-runner-queue', false);
    }

    public function getTaskQueue(): string
    {
        return $this->taskQueue;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isHealthFresh(int $ttlSeconds = 180, ?int $now = null): bool
    {
        return $this->unixtimeLastHealth > 0
            && ($now ?? time()) - $this->unixtimeLastHealth <= $ttlSeconds;
    }

    public function getUnixtimeLastHealth(): int
    {
        return $this->unixtimeLastHealth;
    }

    /**
     * @param DockerContainerStats[] $dockerStats
     */
    public function ustanovitResultatRabotosposobnosti(
        bool $active,
        array $dockerStats,
        MemoryInfo $memoryInfo,
        ?int $unixtimeLastHealth = null,
        ?RunnerResources $resources = null,
    ): void {
        $this->active = $active;
        $this->dockerStats = $dockerStats;
        $this->memoryInfo = $memoryInfo;

        if ($resources !== null) {
            $this->resources = $resources;
        }

        if ($unixtimeLastHealth !== null) {
            $this->unixtimeLastHealth = $unixtimeLastHealth;
        }
    }

    /**
     * Достигнут ли уровень блокировки ресурсов: ресурсоёмкие операции
     * (сборка, подготовка, запуск, IDE, запуск workspace) запрещены.
     */
    public function isResourcesBlocked(): bool
    {
        return $this->resources?->isBlocker() ?? false;
    }

    /**
     * Последний снимок ресурсов сервера. `null` — раннер ещё не присылал
     * структурированные ресурсы (старая версия) или health не приходил.
     */
    public function getResources(): ?RunnerResources
    {
        return $this->resources;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDockerStats(): array
    {
        return $this->dockerStats;
    }

    public function getMemoryInfo(): MemoryInfo
    {
        return $this->memoryInfo;
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
