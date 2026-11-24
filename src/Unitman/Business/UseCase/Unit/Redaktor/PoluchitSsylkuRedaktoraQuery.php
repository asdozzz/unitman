<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit\Redaktor;

use App\Unitman\Business\Command\Unit\Redaktor\PoluchitSsylkuRedaktora;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Redaktor\UmeetPodpisivatSsylkuRedaktora;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\SsylkaRedaktoraReadModel;

/**
 * Персональная подписанная ссылка редактора (решение 15.1-а).
 *
 * В ссылке нет личного токена: только `unitId`, `userId`, `mode` и `exp`.
 * Для режима `edit` дополнительно запускается подготовка сессии редактора на
 * раннере с личным токеном пользователя; для `view` сессия не готовится.
 */
final class PoluchitSsylkuRedaktoraQuery
{
    /**
     * Время жизни подписи — один час.
     */
    private const TTL_SECONDS = 3600;

    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private CanGetUnitReadModelById $unitReadModelRepository,
        private OpredelitelRezhimaRedaktora $opredelitelRezhima,
        private PravilaRedaktora $pravilaRedaktora,
        private UmeetPodpisivatSsylkuRedaktora $signer,
        private RunnerService $runnerService,
        private UnitmanSecurityService $securityService,
    )
    {
    }

    public function handle(PoluchitSsylkuRedaktora $command): SsylkaRedaktoraReadModel
    {
        $unit = $this->unitRepository->getById($command->unitId);
        $project = $this->projectRepository->getById($unit->getProjectId());

        $userId = $this->pravilaRedaktora->tekushiyPolzovatel();
        $tokeny = $this->opredelitelRezhima->zagruzitTokeny($userId);

        $vetkaZashishena = $project->soderzhitZashishennuyuVetku($unit->getBranch());
        $isEditor = $userId !== ''
            && ($userId === $unit->getAuthorId() || in_array($userId, $unit->getRedaktory(), true));

        $mode = !$vetkaZashishena
            && !$unit->isSozdanSystemoi()
            && $isEditor
            && $unit->getRedaktirovanieVklucheno()
            && $this->opredelitelRezhima->pokryvaetProekt($tokeny, $project)
                ? 'edit'
                : 'view';

        $plainToken = null;

        if ($mode === 'edit') {
            try {
                // Живая перепроверка перед подготовкой RW-сессии (15.4-б).
                $plainToken = $this->pravilaRedaktora->proveritZhivoyToken($unit->getId(), $userId, $project);
            } catch (\DomainException) {
                $mode = 'view';
            }
        }

        $signed = $this->signer->podpisat($unit->getId(), $userId, $mode, self::TTL_SECONDS);

        // IDE живёт в compose-профиле и стартует по требованию. Поэтому запрос
        // ссылки редактора запускает подготовку сессии и для view: иначе первое
        // открытие read-only редактора упиралось бы в ещё не запущенный сайдкар.
        $userName = null;
        $userEmail = null;

        if ($mode === 'edit' && $plainToken !== null) {
            $account = $this->securityService->getUserById($userId);
            $userName = $this->securityService->getEmailOrNicknameByUserId($userId);
            $userEmail = $account->email;
        }

        // Для view IDE поднимаем только у уже подготовленного юнита: у
        // неподготовленного нет контейнера и ссылки редактирования.
        $nuzhnoPodgotovit = ($mode === 'edit' && $plainToken !== null)
            || ($mode === 'view' && $unit->esliPodgotovlen());

        if ($nuzhnoPodgotovit) {
            $this->runnerService->podgotovitSessiyuRedaktora(
                $unit->getId(),
                $userId,
                $mode,
                $plainToken ?? '',
                $userName,
                $userEmail,
            );
        }

        $base = $this->unitReadModelRepository->getById($unit->getId())->redaktorLink;
        $ssylka = $base === null
            ? $signed
            : $base . (str_contains($base, '?') ? '&' : '?') . 'redaktorToken=' . rawurlencode($signed);

        return new SsylkaRedaktoraReadModel(
            $ssylka,
            $mode,
            time() + self::TTL_SECONDS,
        );
    }
}
