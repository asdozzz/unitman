<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;

/**
 * Единая точка старта хода: новый промпт и ответ на вопрос запускаются одинаково.
 *
 * Порядок действий принципиален. Сначала вызывается раннер, и только после
 * успешного старта workflow ход и сессия сохраняются. Иначе отказ в доступе к
 * реквизитам или недоступность Temporal оставили бы в БД ход со статусом
 * RUNNING, который никогда не завершится, и сессия зависла бы навсегда.
 *
 * История собирается до сохранения нового хода: раннер получает контекст
 * предыдущих реплик без текущего промпта — текущий приходит отдельным полем.
 *
 * Юниты захватываются ДО старта workflow: пока ход выполняется, активити
 * Unitman над юнитами не запускаются. Если раннер не принял ход, захват
 * освобождается — иначе юнит остался бы занятым до истечения TTL.
 */
final class ZapuskatelHodaOpenCode
{
    public function __construct(
        private HodOpenCodeRepository $turnRepository,
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetVypolnyatHodOpenCode $runner,
        private UuidGenerator $uuidGenerator,
        private UmeetBronirovatUniti $bronirovanie,
    ) {
    }

    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $answers
     * @param list<PrivyazkaKUnitu> $units живые юниты, прошедшие гейт готовности; первый — cwd хода
     */
    public function zapustit(SessiyaOpenCode $session, string $prompt, array $answers, array $units): HodOpenCode
    {
        $now = time();

        $turn = HodOpenCode::create(
            $this->uuidGenerator->makeGuid(),
            $session->getId(),
            $this->turnRepository->sleduyushiiSeq($session->getId()),
            $this->uuidGenerator->makeGuid(),
            $prompt,
            $answers,
            $now,
        );

        $history = IstoriyaDialogaOpenCode::sobrat(
            $this->turnRepository->findBySessionId($session->getId()),
        );

        $vladelec = VladelecZanyatostiOpenCode::dlyaHoda($turn->getId());
        $unitIds = $this->sobratIdUnitov($units);
        $zahvacheno = false;

        try {
            if ($unitIds !== []) {
                $this->bronirovanie->zahvatit($unitIds, $vladelec);
                $zahvacheno = true;
            }

            $this->runner->nachatHod(
                $turn->getWorkflowId(),
                $session->getAccountId(),
                $session->getCredentialId(),
                $session->getExternalSessionId(),
                $units,
                $prompt,
                $answers,
                $history,
            );
        } catch (\Throwable $e) {
            if ($zahvacheno) {
                $this->bronirovanie->osvobodit($unitIds, $vladelec);
            }

            throw $e;
        }

        $this->turnRepository->save($turn);

        $session->podpisatPoPromptu($prompt, $now);
        $session->otmetitIzmenenie($now);

        $this->sessionRepository->update($session);

        return $turn;
    }

    /**
     * @param list<PrivyazkaKUnitu> $units
     * @return list<string>
     */
    private function sobratIdUnitov(array $units): array
    {
        $unitIds = [];

        foreach ($units as $privyazka) {
            $unitIds[] = $privyazka->unitId;
        }

        return $unitIds;
    }
}
