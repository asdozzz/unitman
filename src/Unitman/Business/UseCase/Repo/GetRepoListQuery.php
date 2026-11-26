<?php

namespace App\Unitman\Business\UseCase\Repo;

use App\Unitman\Business\Command\Repo\GetRepoList;
use App\Unitman\Business\Port\Repo\CanGetRepoList;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\RepoList;
use App\Unitman\Business\ReadModel\RepoListItem;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class GetRepoListQuery
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private readonly CanGetRepoList $repoList
    )
    {
    }

    function handle(GetRepoList $query): array
    {
        // Fail closed: проверка прав выполняется ДО чтения данных репозитория.
        if (!$this->securityService->isAdmin()) {
            throw new AccessDeniedHttpException('security.access_denied');
        }

        $list = $this->repoList->getList($query);

        return array_map(
            static fn(RepoList $repo): RepoListItem => RepoListItem::fromRepoList($repo),
            $list
        );
    }
}
