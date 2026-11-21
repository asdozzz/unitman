<?php

declare(strict_types=1);

namespace App\Runner\Business\Command;

/**
 * Запуск хода OpenCode на стороне раннера.
 *
 * `Units` — упорядоченный список юнитов сессии (multi-root workspace): первый
 * юнит задаёт рабочий каталог хода (`<workdir>/<ProjectId>/<UnitId>`), остальные
 * раннер подключает к OpenCode как дополнительные корни.
 * `ProjectId`/`UnitId` продублированы для обратной совместимости — это первый юнит.
 */
final class NachatHodOpenCode
{
    /**
     * @param list<array{QuestionId: string, OptionId: string|null, Text: string|null}> $Answers
     * @param list<array{Role: string, Text: string}> $History
     * @param list<array{ProjectId: string, UnitId: string}> $Units
     * @param list<array{ProjectId: string, ProjectName: string, ProjectCode: string, CanPush: bool}> $WorkspaceProjects
     */
    public function __construct(
        public readonly string $AccountId,
        public readonly string $CredentialId,
        public readonly ?string $SessionId,
        public readonly string $Prompt,
        public readonly array $Answers = [],
        public readonly array $History = [],
        public readonly ?string $ProjectId = null,
        public readonly ?string $UnitId = null,
        public readonly array $Units = [],
        public readonly ?string $WorkspaceId = null,
        public readonly bool $WorkspaceReadOnly = false,
        public readonly array $WorkspaceProjects = [],
        public readonly bool $ReadOnly = false,
        public readonly ?string $Token = null,
    )
    {
    }
}
