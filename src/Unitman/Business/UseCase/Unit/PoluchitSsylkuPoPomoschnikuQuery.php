<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitSsylkuPoPomoschniku;
use App\Unitman\Business\Model\Unit\ConfigUnita\PomoschnikSsylki;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\RunnerPublicAddressProvider;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\Port\Unit\UnitRunnerRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\Utils\SsylkaServisaUtil;

/**
 * Ссылка на сервис юнита по хелперу.
 *
 * Для `http`/`ws`/`wss` адрес строится только по `publicAddress` раннера, к
 * которому жёстко привязан юнит (`unit_runner`). Привязка обязательна: юнит
 * без активного раннера или раннер без `publicAddress` дают явную доменную
 * ошибку — fallback на устаревшие источники адреса запрещён
 * (plans/runner-multi-runner.md §7).
 */
final class PoluchitSsylkuPoPomoschnikuQuery
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CanGetUnitReadModelById $canGetUnitReadModelById,
        private UnitmanSecurityService $securityService,
        private UnitRunnerRepository $unitRunnerRepository,
        private RunnerPublicAddressProvider $runnerPublicAddressProvider,
    )
    {
    }

    function handle(PoluchitSsylkuPoPomoschniku $command): array
    {
        $project = $this->projectRepository->getById($command->projectId);
        $project->getProjectUserById($this->securityService->getCurrentUserId());

        $targetUnit = $this->canGetUnitReadModelById->getById($command->targetUnitId);

        $type = SsylkaServisaUtil::normalizovatTip($command->type);

        /**
         * @var array{service: string, port: int|string, protocol: string} $unitLink
         */
        foreach ($targetUnit->links as $unitLink) {
            if ($unitLink['service'] === $command->service
                && SsylkaServisaUtil::normalizovatTip($unitLink['protocol']) === $type
                && (int)$unitLink['port'] === $command->port
            ) {
                return ['link' => $this->sformirovatSsylku($targetUnit, $type, $command->port, $command->suffix)];
            }
        }

        throw new \DomainException('unit.pomoschnik.servis_ne_naiden');
    }

    /**
     * Для `http`, `ws` и `wss` ссылка строится по `publicAddress` привязанного
     * раннера; для `ws`/`wss` схема ссылки выбирается по схеме адреса
     * (`https` → `wss`, иначе `ws`).
     * Остальные протоколы (например `tcp`) — напрямую на юнит:
     * `<type>://<unit>.<project>:<port>`.
     *
     * startUri из конфига не используется, вместо него подставляется suffix, переданный извне.
     */
    private function sformirovatSsylku(SpisokUnitovReadModel $targetUnit, string $type, int $port, ?string $suffix): string
    {
        $targetProject = $this->projectRepository->getById($targetUnit->projectId);

        $link = SsylkaServisaUtil::sformirovat(
            $type,
            $port,
            $targetUnit->name,
            $targetProject->getName(),
            $this->poluchitPublicAddressDlyaUnita($targetUnit->id, $type),
        );

        if ($suffix !== null && $suffix !== '') {
            $link .= str_starts_with($suffix, '/') ? $suffix : '/'.$suffix;
        }

        return $link;
    }

    /**
     * Прямые протоколы (`tcp` и т.п.) не используют публичный адрес, поэтому привязка
     * раннера для них не требуется.
     *
     * @throws \DomainException `unit.runner_not_bound` | `unit.runner_public_address_missing`
     */
    private function poluchitPublicAddressDlyaUnita(string $unitId, string $type): ?string
    {
        if (!in_array($type, ['http', 'ws', 'wss'], true)) {
            return null;
        }

        $runnerId = $this->unitRunnerRepository->findRunnerIdByUnitId($unitId);

        if ($runnerId === null || $runnerId === '') {
            throw new \DomainException('unit.runner_not_bound');
        }

        $publicAddress = $this->runnerPublicAddressProvider->getPublicAddress($runnerId);

        if ($publicAddress === null || trim($publicAddress) === '') {
            throw new \DomainException('unit.runner_public_address_missing');
        }

        return $publicAddress;
    }
}
