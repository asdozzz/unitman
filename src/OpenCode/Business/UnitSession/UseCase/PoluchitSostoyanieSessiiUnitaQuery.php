<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\UseCase;

use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;
use App\OpenCode\Business\UnitSession\Utils\ProverkaDostupaKSessiiUnita;

final class PoluchitSostoyanieSessiiUnitaQuery
{
    public function __construct(
        private UnitSessionRepository $sessions,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatUnitami $unitami,
        private ProverkaDostupaKSessiiUnita $dostup,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(string $sessionId): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $session = $this->sessions->getById($sessionId);
        $this->dostup->proverit($session, $accountId);

        $sostoyanie = $this->unitami->poluchitSostoyanieUnita($session->getUnitId());
        $mozhnoRedaktirovat = $sostoyanie->exists
            && $sostoyanie->yavlyaetsyaRedaktorom($accountId)
            && $sostoyanie->redaktirovanieVklucheno
            && !$sostoyanie->vetkaZashishena;

        $readOnlyReason = null;

        if (!$sostoyanie->yavlyaetsyaRedaktorom($accountId)) {
            $readOnlyReason = 'not_editor';
        } elseif ($sostoyanie->vetkaZashishena) {
            $readOnlyReason = 'protected_branch';
        } elseif (!$sostoyanie->redaktirovanieVklucheno) {
            $readOnlyReason = 'edit_disabled';
        }

        return [
            'sessionId' => $session->getId(),
            'unitId' => $session->getUnitId(),
            'unitProjectId' => $session->getUnitProjectId(),
            'archived' => $session->estArhiv(),
            'archiveReason' => $session->getArchiveReason(),
            'canAcceptMessages' => !$session->estArhiv()
                && $sostoyanie->group === SostoyanieUnita::GROUP_READY,
            'unit' => [
                'unitId' => $session->getUnitId(),
                'unitProjectId' => $session->getUnitProjectId(),
                'code' => $sostoyanie->code,
                'group' => $sostoyanie->group,
                'archived' => $sostoyanie->group === SostoyanieUnita::GROUP_ARCHIVED,
                'ready' => $sostoyanie->group === SostoyanieUnita::GROUP_READY,
            ],
            'redaktirovanieVklucheno' => $sostoyanie->redaktirovanieVklucheno,
            'vetkaZashishena' => $sostoyanie->vetkaZashishena,
            'mozhnoRedaktirovat' => $mozhnoRedaktirovat,
            'readOnlyReason' => $readOnlyReason,
        ];
    }
}
