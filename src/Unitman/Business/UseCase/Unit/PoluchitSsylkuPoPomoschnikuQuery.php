<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitSsylkuPoPomoschniku;
use App\Unitman\Business\Model\Unit\ConfigUnita\PomoschnikSsylki;
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

        $type = $this->normalizovatTip($command->type);

        /**
         * @var array{service: string, port: int|string, protocol: string} $unitLink
         */
        foreach ($targetUnit->links as $unitLink) {
            if ($unitLink['service'] === $command->service
                && $this->normalizovatTip($unitLink['protocol']) === $type
                && (int)$unitLink['port'] === $command->port
            ) {
                return ['link' => $this->sformirovatSsylku($targetUnit, $type, $command->port, $command->suffix)];
            }
        }

        throw new \DomainException('unit.pomoschnik.servis_ne_naiden');
    }

    /**
     * Пустой протокол трактуется как протокол по умолчанию (`http`).
     */
    private function normalizovatTip(?string $type): string
    {
        $type = strtolower(trim((string)$type));

        return $type === '' ? PomoschnikSsylki::DEFAULT_TYPE : $type;
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

        if ($type === PomoschnikSsylki::DEFAULT_TYPE) {
            $pathinfo = parse_url((string)$targetProject->getProxyHost());
            /** @var array|false $pathinfo*/

            if ($pathinfo === false || empty($pathinfo['scheme']) || empty($pathinfo['host'])) {
                throw new \DomainException('unit.invalid_proxy_host');
            }

            $link = $pathinfo['scheme'].'://'.$port.'.'.$targetUnit->name.'.'.$targetProject->getName().'.'.$pathinfo['host'];
            if (!empty($pathinfo['port'])) {
                $link .= ':'.$pathinfo['port'];
            }
        } else {
            $link = $type.'://'.$targetUnit->name.'.'.$targetProject->getName().':'.$port;
        }

        if ($suffix !== null && $suffix !== '') {
            $link .= str_starts_with($suffix, '/') ? $suffix : '/'.$suffix;
        }

        return $link;
    }
}
