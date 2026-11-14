<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Port;

use App\OpenCode\Business\Model\SessiyaOpenCode;

interface SessiyaOpenCodeRepository
{
    public function save(SessiyaOpenCode $session): void;

    public function update(SessiyaOpenCode $session): void;

    /**
     * @throws \DomainException если сессия не найдена
     */
    public function getById(string $id): SessiyaOpenCode;

    public function findById(string $id): ?SessiyaOpenCode;

    /**
     * @return list<SessiyaOpenCode>
     */
    public function findByAccountId(string $accountId): array;

    /**
     * @return list<SessiyaOpenCode>
     */
    public function findByCredentialId(string $credentialId): array;

    public function delete(string $id): void;
}