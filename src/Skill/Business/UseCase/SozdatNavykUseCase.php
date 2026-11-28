<?php

declare(strict_types=1);

namespace App\Skill\Business\UseCase;

use App\Skill\Business\Command\SozdatNavyk;
use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Business\Port\NavykRepository;
use App\Skill\Business\Port\UuidGenerator;
use App\Skill\Business\Utils\GeneraciyaSlugNavyka;
use App\Skill\Business\Utils\ProverkaNavyka;
use App\Skill\Business\Utils\ProverkaPravNavykov;

/**
 * Создание глобального или проектного навыка.
 *
 * Глобальный навык создаёт только `ROLE_ADMIN`, проектный — администратор
 * каждого выбранного проекта. Slug глобального навыка уникален среди
 * неархивных глобальных навыков; при отсутствии slug он генерируется из name.
 */
final class SozdatNavykUseCase
{
    public function __construct(
        private readonly NavykRepository $repository,
        private readonly UuidGenerator $uuidGenerator,
        private readonly ProverkaPravNavykov $prava,
    ) {
    }

    /**
     * @return array{id: string, slug: string}
     */
    public function handle(SozdatNavyk $command): array
    {
        if (trim($command->name) === '') {
            throw new \DomainException('skill.name.empty');
        }

        $isGlobal = $this->globalnost($command->scope);
        $projectIds = ProverkaNavyka::normalizovatProekty($command->projectIds);

        if ($isGlobal) {
            $actor = $this->prava->trebovatGlobalnogoAdministratora();

            if ($projectIds !== []) {
                throw new \DomainException('skill.scope.projects_forbidden');
            }
        } else {
            ProverkaNavyka::proveritOblast(false, $projectIds);
            $actor = $this->prava->trebovatAdministratoraProektov($projectIds);
        }

        $slug = $this->podobratSlug($command->slug, $command->name, $isGlobal);

        $navyk = NavykOpenCode::create(
            $this->uuidGenerator->makeGuid(),
            $slug,
            $command->name,
            $command->description,
            $command->content,
            $isGlobal,
            $projectIds,
            $actor,
            time(),
        );

        $this->repository->create($navyk);

        return ['id' => $navyk->getId(), 'slug' => $navyk->getSlug()];
    }

    private function globalnost(string $scope): bool
    {
        return match (strtolower(trim($scope))) {
            'global' => true,
            'project' => false,
            default => throw new \DomainException('skill.scope.invalid'),
        };
    }

    private function podobratSlug(?string $slug, string $name, bool $isGlobal): string
    {
        if ($slug !== null && trim($slug) !== '') {
            $slug = trim($slug);
            ProverkaNavyka::proveritSlug($slug);

            if ($isGlobal && $this->repository->isGlobalSlugTaken($slug)) {
                throw new \DomainException('skill.slug.taken');
            }

            return $slug;
        }

        $base = GeneraciyaSlugNavyka::izNazvaniya($name);

        if (!$isGlobal) {
            return $base;
        }

        $candidate = $base;
        $nomer = 2;

        while ($this->repository->isGlobalSlugTaken($candidate)) {
            if ($nomer > 1000) {
                throw new \DomainException('skill.slug.taken');
            }

            $candidate = GeneraciyaSlugNavyka::sSufiksom($base, $nomer);
            ++$nomer;
        }

        return $candidate;
    }
}
