<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Model\LichniyToken;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenListRepository;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use App\Unitman\Business\Port\Unit\UmeetPoluchatTokenRedaktirovaniya;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenList;

/**
 * Разрешение расшифрованного личного токена пользователя по юниту.
 *
 * Из нескольких подходящих токенов выбирается самый свежий по `proverenoV`.
 */
final class PersonalTokenResolver implements UmeetPoluchatTokenRedaktirovaniya
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private LichniyTokenListRepository $tokenListRepository,
        private UmeetShifrovatSekrety $umeetShifrovatSekrety,
    ) {
    }

    public function poluchitToken(string $unitId, string $accountId): string
    {
        $unitId = trim($unitId);
        $accountId = trim($accountId);

        if ($unitId === '' || $accountId === '') {
            throw new \DomainException('unit.redaktor.net_lichnogo_tokena');
        }

        try {
            $unit = $this->unitRepository->getById($unitId);
            $project = $this->projectRepository->getById($unit->getProjectId());
        } catch (\DomainException) {
            throw new \DomainException('unit.redaktor.token_bez_prav_na_proekt');
        }

        $projectId = $project->getId();
        $repoId = $project->getRepoId();

        $forRepo = array_values(array_filter(
            $this->tokenListRepository->findByAccountId($accountId),
            static fn (LichniyTokenList $token): bool => !$token->deleted && $token->repoId === $repoId,
        ));

        if ($forRepo === []) {
            throw new \DomainException('unit.redaktor.net_lichnogo_tokena');
        }

        $covering = array_values(array_filter(
            $forRepo,
            static fn (LichniyTokenList $token): bool => $token->pokryvaetProekt($projectId),
        ));

        if ($covering === []) {
            throw new \DomainException('unit.redaktor.token_bez_prav_na_proekt');
        }

        usort(
            $covering,
            static fn (LichniyTokenList $a, LichniyTokenList $b): int => $b->proverenoV <=> $a->proverenoV,
        );

        $token = $covering[0];

        return $this->umeetShifrovatSekrety->rasshifrovat(
            $token->tokenCipher,
            LichniyToken::makeAad($accountId, $token->id),
        );
    }
}
