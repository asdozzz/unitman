<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Support;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;

/**
 * Подмена Unitman-проектов для тестов workspace без БД и HTTP.
 *
 * Файл не оканчивается на `Test.php`, поэтому PHPUnit не собирает его как тест.
 */
final class ImitaciyaUpravleniyaProektamiWorkspace implements UmeetUpravlyatProektamiWorkspace
{
    /**
     * @var array<string, ProektWorkspace>
     */
    private array $proekty = [];

    /**
     * @var array<string, string|null>
     */
    private array $ssylki = [];

    /**
     * @param list<ProektWorkspace> $projects
     */
    public function zadatProekty(array $projects): void
    {
        foreach ($projects as $project) {
            $this->proekty[$project->projectId] = $project;
        }
    }

    public function zadatSsylkuRedaktora(string $projectId, ?string $link): void
    {
        $this->ssylki[$projectId] = $link;
    }

    /**
     * @param list<string>          $projectIds
     * @param array<string, string> $projectBranches
     *
     * @return list<ProektWorkspace>
     */
    public function poluchitProekti(
        array $projectIds,
        string $accountId,
        string $branch = '',
        array $projectBranches = [],
    ): array {
        $result = [];

        foreach ($projectIds as $position => $projectId) {
            $project = $this->proekty[$projectId] ?? null;

            if ($project === null) {
                throw new \DomainException('opencode.workspace.project_not_found');
            }

            $result[] = new ProektWorkspace(
                $project->projectId,
                $project->projectName,
                $project->projectCode,
                $project->canPush,
                $position,
                $project->mainBranch,
                $projectBranches[$projectId] ?? $branch,
            );
        }

        return $result;
    }

    public function poluchitSsylkuRedaktora(
        string $projectId,
        string $accountId,
        string $workspaceSlug,
    ): ?string {
        return $this->ssylki[$projectId] ?? null;
    }

    public function poluchitSsylkuPoPortu(
        string $projectId,
        string $accountId,
        string $workspaceSlug,
        int $port,
        ?string $type = null,
        ?string $suffix = null,
    ): ?string {
        if (($this->ssylki[$projectId] ?? null) === null) {
            return null;
        }

        $link = 'http://'.$port.'.'.$workspaceSlug.'.workspaces.example.test';

        if ($suffix !== null && $suffix !== '') {
            $link .= str_starts_with($suffix, '/') ? $suffix : '/'.$suffix;
        }

        return $link;
    }
}
