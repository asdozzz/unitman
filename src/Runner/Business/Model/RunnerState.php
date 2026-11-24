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
        private ?string $registryHost = null,
        private ?string $hostname = null,
        private ?int $lastRegisteredAt = null,
        // Публичный адрес раннера (подставляется в A2).
        private ?string $publicAddress = null,
        // Момент регистрации (unix). Дублирует lastRegisteredAt для нового
        // контракта v2; старые записи читаются через lastRegisteredAt.
        private ?int $registeredAt = null,
    ) {
    }

    public static function sozdatRunner(string $id, string $taskQueue): self
    {
        return new self($id, $taskQueue, true);
    }

    /**
     * Состояние только что зарегистрированного раннера (контракт v2).
     *
     * `taskQueue` намеренно совпадает с `runnerId` verbatim: контракт
     * `plans/runner-multi-runner.md` §2/§4 требует, чтобы зарегистрированный
     * раннер сразу был адресуемой Temporal-очередью без префиксов.
     *
     * Регистрация принимает только `{hostname, version}`: публичный адрес
     * задаётся администратором отдельно через `publicAddress`.
     */
    public static function sozdatZaregistrirovanniiRunnerV2(
        string $id,
        ?string $registryHost,
        ?string $hostname,
        int $lastRegisteredAt,
    ): self {
        return new self(
            $id,
            $id,
            true,
            registryHost: $registryHost,
            hostname: $hostname,
            lastRegisteredAt: $lastRegisteredAt,
            registeredAt: $lastRegisteredAt,
        );
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

    /**
     * Публичный адрес реестра, выданный backend-ом при регистрации.
     */
    public function getRegistryHost(): ?string
    {
        return $this->registryHost;
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    /**
     * Unix-время последней регистрации раннера. `null` — раннер ещё не
     * регистрировался (legacy payload).
     */
    public function getLastRegisteredAt(): ?int
    {
        return $this->lastRegisteredAt;
    }

    /**
     * Unix-время регистрации (контракт v2). Для записей, созданных до v2,
     * откатывается на `lastRegisteredAt`.
     */
    public function getRegisteredAt(): ?int
    {
        return $this->registeredAt ?? $this->lastRegisteredAt;
    }

    /**
     * Публичный адрес раннера. `null` — ещё не задан (A2).
     */
    public function getPublicAddress(): ?string
    {
        return $this->publicAddress;
    }

    /**
     * Задан ли публичный адрес. Раннер без адреса может присылать health,
     * но не участвует в автоматическом выборе для сборок/ссылок.
     */
    public function hasPublicAddress(): bool
    {
        return $this->publicAddress !== null && trim($this->publicAddress) !== '';
    }

    public function ustanovitPublicAddress(?string $publicAddress): void
    {
        $normalized = $publicAddress !== null ? trim($publicAddress) : null;

        $this->publicAddress = $normalized === '' ? null : $normalized;
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}
