<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\UseCase;

use App\OpenCode\Business\AgentMode\Utils\OpredelitelRezhimaAgentaHoda;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Port\UmeetPoluchatTokenRedaktirovaniyaUnita;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\ReadModel\RezultatZapuskaHoda;
use App\OpenCode\Business\UnitSession\Port\UnitSessionRepository;
use App\OpenCode\Business\UnitSession\Port\UnitSessionTurnRepository;
use App\OpenCode\Business\UnitSession\Utils\ProverkaDostupaKSessiiUnita;
use App\OpenCode\Business\Utils\IstoriyaDialogaOpenCode;
use App\OpenCode\Business\Utils\OpredelitelModeliHodaOpenCode;
use App\OpenCode\Business\Utils\OpredelitelRezhimaHodaOpenCode;

final class NachatPromptSessiiUnitaUseCase
{
    public function __construct(
        private UnitSessionRepository $sessions,
        private UnitSessionTurnRepository $turns,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatUnitami $unitami,
        private UmeetVypolnyatHodOpenCode $runner,
        private UmeetPoluchatTokenRedaktirovaniyaUnita $tokeny,
        private OpredelitelRezhimaHodaOpenCode $rezhim,
        private ProverkaDostupaKSessiiUnita $dostup,
        private UuidGenerator $uuidGenerator,
        private OpredelitelModeliHodaOpenCode $modeli,
        private OpredelitelRezhimaAgentaHoda $rezhimyAgentov,
    ) {
    }

    public function handle(
        string $sessionId,
        string $prompt,
        ?string $model = null,
        ?string $credentialId = null,
        ?string $agentModeId = null,
    ): RezultatZapuskaHoda
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $session = $this->sessions->getById($sessionId);
        $this->dostup->proverit($session, $accountId);
        $session->trebovatAktivnuyu();

        $poslednii = $this->turns->findPosledniiPoSessii($sessionId);

        if ($poslednii !== null && $poslednii->zhdetOtveta()) {
            throw new \DomainException('opencode.turn.awaiting_answer');
        }

        $sostoyanie = $this->unitami->poluchitSostoyanieUnita($session->getUnitId());

        if ($sostoyanie->group !== SostoyanieUnita::GROUP_READY) {
            throw new \DomainException('opencode.session.unit_not_ready');
        }

        $readOnly = $this->rezhim->readOnly($sostoyanie, $accountId);
        $token = null;

        if (!$readOnly) {
            try {
                $token = $this->tokeny->poluchitTokenDlyRedaktirovaniyaUnita(
                    $session->getUnitId(),
                    $accountId,
                );
            } catch (\DomainException) {
                // Нет личного токена — не отклоняем ход, а понижаем до read-only:
                // диагностика остаётся доступной, правки запрещены.
                $readOnly = true;
                $token = null;
            }
        }

        $rezhimAgenta = $this->rezhimyAgentov->opredelit(
            $accountId,
            OpredelitelRezhimaAgentaHoda::CHAT_UNIT,
            $agentModeId,
        );

        $credentialId = $credentialId !== null && trim($credentialId) !== ''
            ? trim($credentialId)
            : ($rezhimAgenta->credentialId ?? $session->getCredentialId());

        $razreshennayaModel = $this->modeli->opredelit(
            $accountId,
            $credentialId,
            $model,
            $rezhimAgenta->model ?? $session->getModel(),
        );

        $now = time();
        $turn = HodOpenCode::create(
            $this->uuidGenerator->makeGuid(),
            $session->getId(),
            $this->turns->sleduyushiiSeq($session->getId()),
            $this->uuidGenerator->makeGuid(),
            $prompt,
            [],
            $now,
            $razreshennayaModel,
            credentialId: $credentialId,
            agentModeId: $rezhimAgenta->modeId,
            agentModeName: $rezhimAgenta->modeName,
        );

        $history = IstoriyaDialogaOpenCode::sobrat(
            $this->turns->findBySessionId($session->getId()),
        );

        $this->runner->nachatHod(
            $turn->getWorkflowId(),
            $accountId,
            $credentialId,
            $session->getExternalSessionId(),
            [PrivyazkaKUnitu::izZnacheniy($session->getUnitId(), $session->getUnitProjectId())],
            $prompt,
            [],
            $history,
            null,
            $readOnly,
            $token,
            model: $razreshennayaModel,
            rezhimAgenta: $rezhimAgenta,
        );

        $this->turns->save($turn);
        $session->podpisatPoPromptu($prompt, $now);
        $session->otmetitIzmenenie($now);
        $this->sessions->update($session);

        return new RezultatZapuskaHoda(
            $turn->getId(),
            $turn->getStatus()->value,
            $this->runner->kanalProgressa($turn->getWorkflowId()),
        );
    }
}
