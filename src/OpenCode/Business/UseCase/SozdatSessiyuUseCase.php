<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\SozdatSessiyu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;

/**
 * Создание сессии на выбранных реквизитах OpenCode.
 *
 * Реквизиты проверяются по владельцу до записи: чужой credentialId не должен
 * становиться частью чужой сессии, иначе утечка ключа произошла бы косвенно.
 */
final class SozdatSessiyuUseCase
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UuidGenerator $uuidGenerator,
    ) {
    }

    public function handle(SozdatSessiyu $command): string
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $this->credentials->trebovatVladelca($accountId, $command->credentialId);

        $session = SessiyaOpenCode::create(
            $this->uuidGenerator->makeGuid(),
            $accountId,
            $command->credentialId,
            $command->title,
            time(),
        );

        $this->sessionRepository->save($session);

        return $session->getId();
    }
}