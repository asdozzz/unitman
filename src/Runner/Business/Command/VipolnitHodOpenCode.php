<?php

declare(strict_types=1);

namespace App\Runner\Business\Command;

/**
 * Входные данные активности `VipolnitHodOpenCode` (см. plans/runner-opencode-activity-task.md).
 *
 * `Units` — упорядоченный список юнитов сессии (multi-root workspace): первый
 * юнит задаёт рабочий каталог хода, остальные передаются OpenCode как
 * дополнительные корни. `ProjectId`/`UnitId` — снимок первого юнита для
 * обратной совместимости контракта с раннером.
 */
final class VipolnitHodOpenCode
{
    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $Answers
     * @param list<array{Role: string, Text: string}> $History
     * @param list<array{ProjectId: string, UnitId: string}> $Units
     * @param list<array{ProjectId: string, ProjectName: string, ProjectCode: string, CanPush: bool}> $WorkspaceProjects
     */
    public function __construct(
        public readonly ?string $SessionId,
        public readonly string $Prompt,
        public readonly array $Answers,
        public readonly array $History,
        public readonly string $Url,
        public readonly string $Model,
        public readonly string $ApiKey,
        public readonly ?string $ProjectId = null,
        public readonly ?string $UnitId = null,
        public readonly array $Units = [],
        public readonly ?string $WorkspaceId = null,
        public readonly bool $WorkspaceReadOnly = false,
        public readonly array $WorkspaceProjects = [],
    )
    {
    }
}
