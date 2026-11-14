<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase\OpenCode;

use App\Account\Business\Command\IzmenitRekvizityOpenCode;
use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\Port\UmeetShifrovatSekrety;

/**
 * Изменение реквизитов OpenCode текущего пользователя.
 *
 * Ключ перешифровывается только если он действительно передан: пустая строка
 * и `null` означают «оставить прежний». Это позволяет менять URL/модель,
 * не заставляя пользователя заново вводить секрет.
 */
final class IzmenitRekvizityOpenCodeUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private OpenCodeCredentialRepository $credentialRepository,
        private UmeetShifrovatSekrety $umeetShifrovatSekrety,
    ) {
    }

    public function handle(IzmenitRekvizityOpenCode $command): void
    {
        $accountId = $this->securityService->getCurrentUserId();

        if (trim($command->id) === '') {
            throw new \DomainException('account.opencode_credential.empty_id');
        }

        $credential = $this->credentialRepository->getById(trim($command->id));
        $credential->trebovatVladelca($accountId);

        if ($command->name !== null) {
            $this->trebovatSvobodnoeImya($accountId, $command->name, $credential->getId());
        }

        $apiKeyCipher = null;
        $apiKeyHint = null;

        if ($command->apiKey !== null && $command->apiKey !== '') {
            $apiKeyCipher = $this->umeetShifrovatSekrety->zashifrovat(
                $command->apiKey,
                OpenCodeCredential::makeAad($accountId, $credential->getId()),
            );
            $apiKeyHint = OpenCodeCredential::makeHint($command->apiKey);
        }

        $credential->izmenit(
            $command->name,
            $command->url,
            $command->model,
            $apiKeyCipher,
            $apiKeyHint,
            time(),
        );

        $this->credentialRepository->update($credential);
    }

    private function trebovatSvobodnoeImya(string $accountId, string $name, string $currentId): void
    {
        $existing = $this->credentialRepository->findByName($accountId, trim($name));

        if ($existing !== null && $existing->getId() !== $currentId) {
            throw new \DomainException('account.opencode_credential.name_double');
        }
    }
}
