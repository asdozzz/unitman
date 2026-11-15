<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PrivezatSessiyuKUnitu;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;

/**
 * Привязка сессии к юнитам.
 *
 * Юниты проверяются на доступ и на то, что они ещё существуют: привязать сессию
 * к удалённому юниту нельзя, иначе сессия сразу попала бы в архив.
 * Привязка аддитивна и идемпотентна: уже привязанные юниты пропускаются,
 * порядок юнитов сессии сохраняется.
 */
final class PrivezatSessiyuKUnituUseCase
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatUnitami $unitami,
    ) {
    }

    /**
     * @throws \DomainException если юнит недоступен, удалён,
     *                          а сессия отсутствует, в архиве или превышен лимит юнитов
     */
    public function handle(PrivezatSessiyuKUnitu $command): void
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $sessionId = trim($command->sessionId);

        if ($sessionId === '') {
            throw new \DomainException('opencode.session.id_required');
        }

        $privyazki = $this->sobratPrivyazki($command->unitIds, $accountId);

        $session = $this->sessionRepository->getById($sessionId);
        $session->trebovatVladelca($accountId);

        $session->privezatKUnitam($privyazki, time());

        $this->sessionRepository->update($session);
    }

    /**
     * @param list<string> $unitIds
     *
     * @return list<PrivyazkaKUnitu>
     */
    private function sobratPrivyazki(array $unitIds, string $accountId): array
    {
        $normalizedUnitIds = $this->normalizeUnitIds($unitIds);

        if ($normalizedUnitIds === []) {
            throw new \DomainException('opencode.session.unit_required');
        }

        $privyazki = [];

        foreach ($normalizedUnitIds as $unitId) {
            if (!$this->unitami->proveritDostupKUnitu($unitId, $accountId)) {
                throw new \DomainException('opencode.session.unit_not_found');
            }

            $sostoyanie = $this->unitami->poluchitSostoyanieUnita($unitId);

            if ($sostoyanie->group === SostoyanieUnita::GROUP_ARCHIVED || $sostoyanie->projectId === null) {
                throw new \DomainException('opencode.session.unit_not_found');
            }

            $privyazki[] = PrivyazkaKUnitu::izZnacheniy($unitId, $sostoyanie->projectId);
        }

        return $privyazki;
    }

    /**
     * @param list<string> $unitIds
     *
     * @return list<string>
     */
    private function normalizeUnitIds(array $unitIds): array
    {
        $unique = [];

        foreach ($unitIds as $unitId) {
            $normalized = trim($unitId);

            if ($normalized === '' || in_array($normalized, $unique, true)) {
                continue;
            }

            $unique[] = $normalized;
        }

        return $unique;
    }
}
