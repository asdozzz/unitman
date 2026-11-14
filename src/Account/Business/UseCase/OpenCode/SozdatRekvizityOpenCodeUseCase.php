<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase\OpenCode;

use App\Account\Business\Command\SozdatRekvizityOpenCode;
use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\Port\UmeetShifrovatSekrety;
use App\Account\Business\Port\UuidGenerator;

/**
 * Добавление реквизитов OpenCode текущего пользователя.
 *
 * Открытый API-ключ существует только внутри этого метода: в БД уходит
 * шифротекст, в ответ — идентификатор новой записи.
 */
final class SozdatRekvizityOpenCodeUseCase
{
    public function __construct(
        private SecurityService $securityService,
        private OpenCodeCredentialRepository $credentialRepository,
        private UmeetShifrovatSekrety $umeetShifrovatSekrety,
        private UuidGenerator $uuidGenerator,
    ) {
    }

    public function handle(SozdatRekvizityOpenCode $command): string
    {
        $accountId = $this->securityService->getCurrentUserId();
        $credentialId = $this->uuidGenerator->makeGuid();

        if (trim($command->apiKey) === '') {
            // Пустой ключ нельзя зашифровать осмысленно: запись сохранилась бы,
            // но раннер получил бы пустой секрет и падал бы уже на своём стороне.
            throw new \DomainException('account.opencode_credential.empty_api_key');
        }

        if ($this->credentialRepository->findByName($accountId, trim($command->name)) !== null) {
            throw new \DomainException('account.opencode_credential.name_double');
        }

        $credential = OpenCodeCredential::create(
            $credentialId,
            $accountId,
            $command->name,
            $command->url,
            $command->model,
            $this->umeetShifrovatSekrety->zashifrovat(
                $command->apiKey,
                OpenCodeCredential::makeAad($accountId, $credentialId),
            ),
            OpenCodeCredential::makeHint($command->apiKey),
            time(),
        );

        $this->credentialRepository->save($credential);

        return $credential->getId();
    }
}
