<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSostoyanieSessii;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\ReadModel\SostoyanieSessiiReadModel;
use App\OpenCode\Business\ReadModel\SostoyanieUnitaSessiiReadModel;

/**
 * Состояние сессии для интерфейса.
 *
 * Нужен, чтобы фронтенд показал «юниты собираются» вместо ошибки после
 * неудачного промпта. Правило `canAcceptMessages` совпадает с гейтом
 * `PropuskHodaOpenCode`: сессия принимает сообщения, если она не в архиве,
 * у неё есть хотя бы один не удалённый юнит и все такие юниты в группе
 * готовности `ready`. Свободная сессия (без юнитов) принимает сообщения,
 * пока не архивирована.
 *
 * Запрос ничего не меняет: архивация по удалённым юнитам выполняется гейтом
 * хода и фоновой задачей.
 */
final class PoluchitSostoyanieSessiiQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatUnitami $unitami,
    ) {
    }

    /**
     * @throws \DomainException если сессия не найдена или принадлежит другому пользователю
     */
    public function handle(PoluchitSostoyanieSessii $command): SostoyanieSessiiReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $sessionId = trim($command->sessionId);

        if ($sessionId === '') {
            throw new \DomainException('opencode.session.id_required');
        }

        $session = $this->sessionRepository->getById($sessionId);
        $session->trebovatVladelca($accountId);

        $privyazki = $session->getUnits();
        $arhiv = $session->estArhiv();

        if ($privyazki === []) {
            return new SostoyanieSessiiReadModel(
                $session->getId(),
                [],
                null,
                null,
                $arhiv,
                $session->getArchiveReason(),
                null,
                !$arhiv,
            );
        }

        /** @var list<SostoyanieUnitaSessiiReadModel> $sostoyaniya */
        $sostoyaniya = [];
        $pervyiBlokiruyushchiiKod = null;
        $pervyiKod = null;
        $estZhivoi = false;
        $vseZhivyeGotovy = true;

        foreach ($privyazki as $privyazka) {
            $sostoyanie = $this->unitami->poluchitSostoyanieUnita($privyazka->unitId);
            $udalen = $sostoyanie->group === SostoyanieUnita::GROUP_ARCHIVED;
            $gotov = !$udalen && $sostoyanie->group === SostoyanieUnita::GROUP_READY;

            $sostoyaniya[] = new SostoyanieUnitaSessiiReadModel(
                $privyazka->unitId,
                $privyazka->unitProjectId,
                $sostoyanie->code,
                $sostoyanie->group,
                $udalen,
                $gotov,
            );

            $pervyiKod ??= $sostoyanie->code;

            if ($udalen) {
                continue;
            }

            $estZhivoi = true;

            if (!$gotov) {
                $vseZhivyeGotovy = false;
                $pervyiBlokiruyushchiiKod ??= $sostoyanie->code;
            }
        }

        $pervyi = $privyazki[0];

        return new SostoyanieSessiiReadModel(
            $session->getId(),
            $sostoyaniya,
            $pervyi->unitId,
            $pervyi->unitProjectId,
            $arhiv,
            $session->getArchiveReason(),
            $pervyiBlokiruyushchiiKod ?? $pervyiKod,
            !$arhiv && $estZhivoi && $vseZhivyeGotovy,
        );
    }
}
