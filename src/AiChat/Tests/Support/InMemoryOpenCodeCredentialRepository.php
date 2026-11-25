<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Support;

use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Port\OpenCodeCredentialRepository;

/**
 * In-memory реализация порта Account для тестов AI-чата.
 *
 * Заменяет БД: контроллер и AccountApi работают с настоящей логикой
 * расшифровки и проверки владения, а не с моками.
 */
final class InMemoryOpenCodeCredentialRepository implements OpenCodeCredentialRepository
{
    /**
     * @var array<string, OpenCodeCredential>
     */
    private array $credentials = [];

    public function save(OpenCodeCredential $credential): void
    {
        $this->credentials[$credential->getId()] = $credential;
    }

    public function update(OpenCodeCredential $credential): void
    {
        $this->credentials[$credential->getId()] = $credential;
    }

    public function getById(string $id): OpenCodeCredential
    {
        if (!isset($this->credentials[$id])) {
            throw new \DomainException('account.opencode_credential.not_found');
        }

        return $this->credentials[$id];
    }

    public function findById(string $id): ?OpenCodeCredential
    {
        return $this->credentials[$id] ?? null;
    }

    public function findByName(string $accountId, string $name): ?OpenCodeCredential
    {
        foreach ($this->credentials as $credential) {
            if ($credential->getAccountId() === $accountId && $credential->getName() === $name) {
                return $credential;
            }
        }

        return null;
    }

    /**
     * @return list<OpenCodeCredential>
     */
    public function findByAccountId(string $accountId): array
    {
        return array_values(array_filter(
            $this->credentials,
            static fn (OpenCodeCredential $credential): bool => $credential->getAccountId() === $accountId,
        ));
    }

    public function delete(string $id): void
    {
        unset($this->credentials[$id]);
    }
}
