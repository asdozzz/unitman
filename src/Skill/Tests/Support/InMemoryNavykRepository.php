<?php

declare(strict_types=1);

namespace App\Skill\Tests\Support;

use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Business\Model\VersiyaNavyka;
use App\Skill\Business\Port\NavykRepository;

/**
 * In-memory реализация `NavykRepository` для UseCase/резолвер-тестов.
 */
final class InMemoryNavykRepository implements NavykRepository
{
    /** @var array<string, NavykOpenCode> */
    private array $navyki = [];

    /** @var array<string, list<string>> */
    private array $privyazki = [];

    /** @var array<string, list<VersiyaNavyka>> */
    private array $versii = [];

    public function create(NavykOpenCode $navyk): void
    {
        if ($navyk->isGlobal() && $this->isGlobalSlugTaken($navyk->getSlug(), $navyk->getId())) {
            throw new \DomainException('skill.slug.taken');
        }

        $this->navyki[$navyk->getId()] = $navyk;
        $this->privyazki[$navyk->getId()] = $navyk->getProjectIds();
        $this->versii[$navyk->getId()] = [$this->snimok($navyk)];
    }

    public function update(NavykOpenCode $navyk): void
    {
        if ($navyk->isGlobal() && $this->isGlobalSlugTaken($navyk->getSlug(), $navyk->getId())) {
            throw new \DomainException('skill.slug.taken');
        }

        if (!isset($this->navyki[$navyk->getId()])) {
            throw new \DomainException('skill.not_found');
        }

        $this->navyki[$navyk->getId()] = $navyk;

        $uzheEst = array_filter(
            $this->versii[$navyk->getId()] ?? [],
            static fn (VersiyaNavyka $v): bool => $v->getVersion() === $navyk->getVersion(),
        );

        if ($uzheEst === []) {
            $this->versii[$navyk->getId()][] = $this->snimok($navyk);
        }
    }

    public function archive(NavykOpenCode $navyk): void
    {
        $this->navyki[$navyk->getId()] = $navyk;
    }

    public function findById(string $id): ?NavykOpenCode
    {
        return isset($this->navyki[$id]) ? $this->materialize($this->navyki[$id]) : null;
    }

    public function findByIds(array $ids): array
    {
        $rezultat = [];

        foreach ($ids as $id) {
            if (isset($this->navyki[$id])) {
                $rezultat[] = $this->materialize($this->navyki[$id]);
            }
        }

        return $rezultat;
    }

    public function listGlobal(bool $includeArchived = false): array
    {
        $rezultat = [];

        foreach ($this->navyki as $navyk) {
            if (!$navyk->isGlobal()) {
                continue;
            }

            if (!$includeArchived && $navyk->isArchived()) {
                continue;
            }

            $rezultat[] = $this->materialize($navyk);
        }

        return $this->sort($rezultat);
    }

    public function listForProjects(array $projectIds, bool $includeArchived = false): array
    {
        $rezultat = [];

        foreach ($this->navyki as $id => $navyk) {
            if (!$includeArchived && $navyk->isArchived()) {
                continue;
            }

            foreach ($this->privyazki[$id] ?? [] as $privyazka) {
                if (\in_array($privyazka, $projectIds, true)) {
                    $rezultat[] = $this->materialize($navyk);
                    break;
                }
            }
        }

        return $this->sort($rezultat);
    }

    public function attachToProjects(string $skillId, array $projectIds, string $addedBy, int $now): void
    {
        $tekushie = $this->privyazki[$skillId] ?? [];

        foreach ($projectIds as $projectId) {
            if (!\in_array($projectId, $tekushie, true)) {
                $tekushie[] = $projectId;
            }
        }

        $this->privyazki[$skillId] = $tekushie;
    }

    public function detachFromProject(string $skillId, string $projectId): void
    {
        $this->privyazki[$skillId] = array_values(array_filter(
            $this->privyazki[$skillId] ?? [],
            static fn (string $id): bool => $id !== $projectId,
        ));
    }

    public function listVersions(string $skillId): array
    {
        $versii = $this->versii[$skillId] ?? [];
        usort($versii, static fn (VersiyaNavyka $a, VersiyaNavyka $b): int => $b->getVersion() <=> $a->getVersion());

        return $versii;
    }

    public function resolveEffectiveForProjects(array $projectIds): array
    {
        $rezultat = [];

        foreach ($projectIds as $projectId) {
            $effektivnye = $this->listGlobal();

            foreach ($this->listForProjects([$projectId]) as $navyk) {
                $effektivnye[] = $navyk;
            }

            $rezultat[$projectId] = $effektivnye;
        }

        return $rezultat;
    }

    public function isGlobalSlugTaken(string $slug, ?string $excludeSkillId = null): bool
    {
        foreach ($this->navyki as $navyk) {
            if (!$navyk->isGlobal() || $navyk->isArchived() || $navyk->getSlug() !== $slug) {
                continue;
            }

            if ($excludeSkillId !== null && $navyk->getId() === $excludeSkillId) {
                continue;
            }

            return true;
        }

        return false;
    }

    public function findGlobalBySlug(string $slug): ?NavykOpenCode
    {
        foreach ($this->navyki as $navyk) {
            if ($navyk->isGlobal() && !$navyk->isArchived() && $navyk->getSlug() === $slug) {
                return $this->materialize($navyk);
            }
        }

        return null;
    }

    private function materialize(NavykOpenCode $navyk): NavykOpenCode
    {
        return NavykOpenCode::restore(
            $navyk->getId(),
            $navyk->getSlug(),
            $navyk->getName(),
            $navyk->getDescription(),
            $navyk->getContent(),
            $navyk->getContentHash(),
            $navyk->getVersion(),
            $navyk->isGlobal(),
            $this->privyazki[$navyk->getId()] ?? $navyk->getProjectIds(),
            $navyk->getCreatedBy(),
            $navyk->getUpdatedBy(),
            $navyk->getCreatedAt(),
            $navyk->getUpdatedAt(),
            $navyk->getArchivedAt(),
        );
    }

    private function snimok(NavykOpenCode $navyk): VersiyaNavyka
    {
        return VersiyaNavyka::restore(
            $navyk->getId(),
            $navyk->getVersion(),
            $navyk->getName(),
            $navyk->getDescription(),
            $navyk->getContent(),
            $navyk->getContentHash(),
            $navyk->getUpdatedBy(),
            $navyk->getUpdatedAt(),
        );
    }

    /**
     * @param list<NavykOpenCode> $navyki
     *
     * @return list<NavykOpenCode>
     */
    private function sort(array $navyki): array
    {
        usort(
            $navyki,
            static fn (NavykOpenCode $a, NavykOpenCode $b): int
                => [$a->getName(), $a->getSlug(), $a->getId()]
                <=> [$b->getName(), $b->getSlug(), $b->getId()],
        );

        return $navyki;
    }
}
