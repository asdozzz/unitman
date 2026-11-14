<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitSsylkuPoPomoschniku;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\Utils\SsylkaServisaUtil;

final class PoluchitSsylkuPoPomoschnikuQuery
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CanGetUnitReadModelById $canGetUnitReadModelById,
        private UnitmanSecurityService $securityService
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
     * Для `http` ссылка строится через proxy host проекта, для остальных протоколов
     * (например `ws`) — напрямую на юнит: `<type>://<unit>.<project>:<port>`.
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
            (string)$targetProject->getProxyHost()
        );

        if ($suffix !== null && $suffix !== '') {
            $link .= str_starts_with($suffix, '/') ? $suffix : '/'.$suffix;
        }

        return $link;
    }
}
