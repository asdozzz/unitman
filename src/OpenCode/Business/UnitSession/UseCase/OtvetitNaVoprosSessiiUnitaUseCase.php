<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\UseCase;

use App\OpenCode\Business\AgentMode\Utils\OpredelitelRezhimaAgentaHoda;

use App\OpenCode\Business\Command\OtvetNaVopros;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SostoyanieUnita;
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
use App\OpenCode\Business\Utils\OtvetyNaVoprosyOpenCode;

final class OtvetitNaVoprosSessiiUnitaUseCase
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

    /**
     * @param list<mixed> $answers сырые ответы из тела запроса, валидируются ниже
     */
    public function handle(
        string $turnId,
        array $answers,
        ?string $model = null,
        ?string $credentialId = null,
        ?string $agentModeId = null,
    ): RezultatZapuskaHoda
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $turn = $this->turns->getById($turnId);
        $session = $this->sessions->getById($turn->getSessionId());
        $this->dostup->proverit($session, $accountId);
        $session->trebovatAktivnuyu();

        if (!$turn->zhdetOtveta()) {
            throw new \DomainException('opencode.turn.not_awaiting_answer');
        }

        $questions = $turn->getResult()?->questions ?? [];
        $otvety = [];

        foreach ($answers as $answer) {
            if (!is_array($answer)) {
                continue;
            }

            $otvety[] = new OtvetNaVopros(
                is_string($answer['QuestionId'] ?? null) ? $answer['QuestionId'] : '',
                is_string($answer['OptionId'] ?? null) ? $answer['OptionId'] : null,
                is_string($answer['Text'] ?? null) ? $answer['Text'] : null,
            );
        }

        $normalizovannie = OtvetyNaVoprosyOpenCode::sobrat($questions, $otvety);
        $prompt = IstoriyaDialogaOpenCode::otvetyVTekst($questions, $normalizovannie);

        if (trim($prompt) === '') {
            throw new \DomainException('opencode.turn.answers_required');
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
        $novyiHod = HodOpenCode::create(
            $this->uuidGenerator->makeGuid(),
            $session->getId(),
            $this->turns->sleduyushiiSeq($session->getId()),
            $this->uuidGenerator->makeGuid(),
            $prompt,
            $normalizovannie,
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
            $novyiHod->getWorkflowId(),
            $accountId,
            $credentialId,
            $session->getExternalSessionId(),
            [PrivyazkaKUnitu::izZnacheniy($session->getUnitId(), $session->getUnitProjectId())],
            $prompt,
            $normalizovannie,
            $history,
            null,
            $readOnly,
            $token,
            model: $razreshennayaModel,
            rezhimAgenta: $rezhimAgenta,
        );

        $this->turns->save($novyiHod);
        $session->otmetitIzmenenie($now);
        $this->sessions->update($session);

        return new RezultatZapuskaHoda(
            $novyiHod->getId(),
            $novyiHod->getStatus()->value,
            $this->runner->kanalProgressa($novyiHod->getWorkflowId()),
        );
    }
}
