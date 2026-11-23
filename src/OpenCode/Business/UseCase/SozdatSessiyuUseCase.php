<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\SozdatSessiyu;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\Port\UuidGenerator;

/**
 * Создание сессии на выбранных реквизитах OpenCode.
 *
 * Реквизиты проверяются по владельцу до записи: чужой credentialId не должен
 * становиться частью чужой сессии, иначе утечка ключа произошла бы косвенно.
 *
 * Сессия может создаваться сразу с юнитами: либо привязкой к существующим
 * юнитам пользователя, либо созданием нового юнита от имени владельца.
 * Одновременно указать оба источника нельзя — это двусмысленность.
 */
final class SozdatSessiyuUseCase
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatUnitami $unitami,
        private UuidGenerator $uuidGenerator,
    ) {
    }

    /**
     * @throws \DomainException если реквизиты или юнит недоступны,
     *                          либо указаны оба источника юнита сразу
     */
    public function handle(SozdatSessiyu $command): string
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $this->credentials->trebovatVladelca($accountId, $command->credentialId);

        $units = $this->opredelitUnity($command, $accountId);

        $model = $this->credentials->razreshitModel($accountId, $command->credentialId, $command->model);

        $session = SessiyaOpenCode::create(
            $this->uuidGenerator->makeGuid(),
            $accountId,
            $command->credentialId,
            $command->title,
            $units,
            time(),
            $model === '' ? null : $model,
        );

        $this->sessionRepository->save($session);

        return $session->getId();
    }

    /**
     * @return list<PrivyazkaKUnitu>
     *
     * @throws \DomainException если указаны оба источника юнита
     *                          или юнит недоступен пользователю
     */
    private function opredelitUnity(SozdatSessiyu $command, string $accountId): array
    {
        if ($command->unitIds !== [] && $command->unit !== null) {
            throw new \DomainException('opencode.session.unit_source_conflict');
        }

        if ($command->unit !== null) {
            $sozdannyiUnitId = $this->unitami->sozdatUnit($command->unit, $accountId);

            return [PrivyazkaKUnitu::izZnacheniy($sozdannyiUnitId, $command->unit->projectId)];
        }

        $normalizedUnitIds = $this->normalizeUnitIds($command->unitIds);

        if ($normalizedUnitIds === []) {
            return [];
        }

        if (count($normalizedUnitIds) > SessiyaOpenCode::MAX_UNITS) {
            throw new \DomainException('opencode.session.too_many_units');
        }

        $units = [];

        foreach ($normalizedUnitIds as $unitId) {
            if (!$this->unitami->proveritDostupKUnitu($unitId, $accountId)) {
                throw new \DomainException('opencode.session.unit_not_found');
            }

            $sostoyanie = $this->unitami->poluchitSostoyanieUnita($unitId);

            if ($sostoyanie->group === SostoyanieUnita::GROUP_ARCHIVED || $sostoyanie->projectId === null) {
                throw new \DomainException('opencode.session.unit_not_found');
            }

            $units[] = PrivyazkaKUnitu::izZnacheniy($unitId, $sostoyanie->projectId);
        }

        return $units;
    }

    /**
     * Обрезает пробелы, отбрасывает пустые и повторяющиеся идентификаторы,
     * сохраняя порядок: первый юнит — рабочий каталог хода.
     *
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
