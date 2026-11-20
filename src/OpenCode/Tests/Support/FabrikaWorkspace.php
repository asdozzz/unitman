<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Support;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;

/**
 * Фикстуры workspace для доменных тестов.
 */
final class FabrikaWorkspace
{
    public const NOW = 1_700_000_000;

    public static function proekt(
        string $projectId = 'project-1',
        string $name = 'api',
        string $code = 'services/api',
        bool $canPush = true,
        int $position = 0,
    ): ProektWorkspace {
        return new ProektWorkspace($projectId, $name, $code, $canPush, $position);
    }

    /**
     * @param list<ProektWorkspace>|null $projects
     */
    public static function sozdanie(
        string $id = 'workspace-1',
        string $name = 'payments-refactor',
        ?array $projects = null,
        bool $canPush = true,
    ): SessiyaOpenCode {
        return SessiyaOpenCode::createWorkspace(
            $id,
            'account-1',
            'credential-1',
            $name,
            'feature/payments',
            $projects ?? [self::proekt(canPush: $canPush)],
            'Первый промпт',
            self::NOW,
        );
    }

    public static function vStatuse(
        StatusWorkspace $status,
        string $id = 'workspace-1',
        string $name = 'payments-refactor',
        bool $canPush = true,
    ): SessiyaOpenCode {
        $session = self::sozdanie($id, $name, canPush: $canPush);
        $workspace = $session->getWorkspace();
        \assert($workspace !== null);

        $guard = 0;

        while ($workspace->getStatus() !== $status) {
            if (++$guard > 10) {
                throw new \LogicException('Не удалось привести workspace к статусу ' . $status->value);
            }

            $workspace->perevestiV(self::sleduyushchii($workspace->getStatus(), $status), self::NOW);
        }

        return $session;
    }

    private static function sleduyushchii(StatusWorkspace $current, StatusWorkspace $target): StatusWorkspace
    {
        return match ($current) {
            StatusWorkspace::CREATING => $target === StatusWorkspace::ERROR
                ? StatusWorkspace::ERROR
                : StatusWorkspace::RUNNING,
            StatusWorkspace::RUNNING => $target === StatusWorkspace::DELETING
                ? StatusWorkspace::DELETING
                : StatusWorkspace::ARCHIVING,
            StatusWorkspace::ARCHIVING => $target === StatusWorkspace::ERROR
                ? StatusWorkspace::ERROR
                : StatusWorkspace::ARCHIVED,
            StatusWorkspace::ARCHIVED => $target === StatusWorkspace::DELETING
                ? StatusWorkspace::DELETING
                : StatusWorkspace::STARTING,
            StatusWorkspace::STARTING => $target === StatusWorkspace::ERROR
                ? StatusWorkspace::ERROR
                : StatusWorkspace::RUNNING,
            StatusWorkspace::ERROR => $target === StatusWorkspace::DELETING
                ? StatusWorkspace::DELETING
                : StatusWorkspace::STARTING,
            StatusWorkspace::DELETING => StatusWorkspace::DELETING,
        };
    }
}
