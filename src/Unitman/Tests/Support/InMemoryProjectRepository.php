<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Support;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Port\Project\ProjectRepository;
use DomainException;

/**
 * Репозиторий проектов в памяти: в тестах каталога агрегаты уже собраны вручную.
 */
final class InMemoryProjectRepository implements ProjectRepository
{
    /**
     * @var array<string, Project>
     */
    public array $projects = [];

    public function getById(string $id): Project
    {
        if (!array_key_exists($id, $this->projects)) {
            throw new DomainException('project.not_found');
        }

        return $this->projects[$id];
    }

    public function save(Project $project): void
    {
        $this->projects[$project->getId()] = $project;
    }
}
