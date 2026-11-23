<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\UseCase;

use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\UnitSession\Model\UnitSession;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;

final class SozdatSessiyuUnitaUseCase
{
    public function __construct(
        private UnitSessionRepository $sessions,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatUnitami $unitami,
        private UuidGenerator $uuidGenerator,
    ) {
    }

    public function handle(
        string $credentialId,
        ?string $title,
        string $unitId,
        ?string $model = null,
    ): string {
        $accountId = $this->credentials->tekuschiiAccountId();
        $this->credentials->trebovatVladelca($accountId, $credentialId);

        $sostoyanie = $this->unitami->poluchitSostoyanieUnita($unitId);

        if (!$sostoyanie->exists || $sostoyanie->projectId === null) {
            throw new \DomainException('opencode.session.unit_not_found');
        }

        if ($sostoyanie->group === SostoyanieUnita::GROUP_ARCHIVED) {
            throw new \DomainException('opencode.session.unit_not_found');
        }

        if (!$sostoyanie->yavlyaetsyaRedaktorom($accountId)) {
            throw new \DomainException('opencode.session.unit_not_editor');
        }

        if ($this->sessions->findAktivnayaPoUnitu($unitId) !== null) {
            throw new \DomainException('opencode.session.unit_busy');
        }

        $razreshennayaModel = $this->credentials->razreshitModel($accountId, trim($credentialId), $model);

        $session = UnitSession::create(
            $this->uuidGenerator->makeGuid(),
            $accountId,
            trim($credentialId),
            $title,
            $unitId,
            $sostoyanie->projectId,
            time(),
            $razreshennayaModel === '' ? null : $razreshennayaModel,
        );

        $this->sessions->save($session);

        return $session->getId();
    }
}
