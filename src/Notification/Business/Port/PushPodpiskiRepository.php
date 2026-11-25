<?php

declare(strict_types=1);

namespace App\Notification\Business\Port;

use App\Notification\Business\Model\PushPodpiska;

interface PushPodpiskiRepository
{
    public function save(PushPodpiska $podpiska): void;

    public function findByEndpoint(string $endpoint): ?PushPodpiska;

    public function deleteByEndpoint(string $accountId, string $endpoint): void;

    public function deleteById(string $id): void;

    /**
     * @param list<string> $accountIds
     *
     * @return list<PushPodpiska>
     */
    public function findDlyaAccounts(array $accountIds): array;
}
