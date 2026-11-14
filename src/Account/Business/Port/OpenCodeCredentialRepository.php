<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

use App\Account\Business\Model\OpenCode\OpenCodeCredential;

interface OpenCodeCredentialRepository
{
    public function save(OpenCodeCredential $credential): void;

    public function update(OpenCodeCredential $credential): void;

    /**
     * @throws \DomainException если запись не найдена
     */
    public function getById(string $id): OpenCodeCredential;

    public function findById(string $id): ?OpenCodeCredential;

    public function findByName(string $accountId, string $name): ?OpenCodeCredential;

    /**
     * @return list<OpenCodeCredential>
     */
    public function findByAccountId(string $accountId): array;

    public function delete(string $id): void;
}
