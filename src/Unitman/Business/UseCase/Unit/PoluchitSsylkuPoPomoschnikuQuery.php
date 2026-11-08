<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitSsylkuPoPomoschniku;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;

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

        /**
         * @var array{service: string, port: int|string, protocol: string} $unitLink
         */
        foreach ($targetUnit->links as $unitLink) {
            if ($unitLink['service'] === $command->service
                && $unitLink['protocol'] === 'http'
                && (int)$unitLink['port'] === $command->port
            ) {
                return ['link' => $this->sformirovatSsylku($targetUnit, $command->port, $command->suffix)];
            }
        }

        throw new \DomainException('unit.pomoschnik.servis_ne_naiden');
    }

    /**
     * Ссылка формируется заново по данным проекта и юнита: startUri из конфига
     * не используется, вместо него подставляется suffix, переданный извне.
     */
    private function sformirovatSsylku(SpisokUnitovReadModel $targetUnit, int $port, ?string $suffix): string
    {
        $targetProject = $this->projectRepository->getById($targetUnit->projectId);
        $pathinfo = parse_url((string)$targetProject->getProxyHost());
        /** @var array|false $pathinfo*/

        if ($pathinfo === false || empty($pathinfo['scheme']) || empty($pathinfo['host'])) {
            throw new \DomainException('unit.invalid_proxy_host');
        }

        $link = $pathinfo['scheme'].'://'.$port.'.'.$targetUnit->name.'.'.$targetProject->getName().'.'.$pathinfo['host'];
        if (!empty($pathinfo['port'])) {
            $link .= ':'.$pathinfo['port'];
        }

        if ($suffix !== null && $suffix !== '') {
            $link .= str_starts_with($suffix, '/') ? $suffix : '/'.$suffix;
        }

        return $link;
    }
}
