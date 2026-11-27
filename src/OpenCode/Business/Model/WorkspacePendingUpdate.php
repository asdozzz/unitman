<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Ожидающее обновление workspace.
 *
 * Хранится отдельно от сессии: при успехе проекты добавляются в снимок
 * workspace, файлы отмечаются применёнными; при ошибке изменения откатываются.
 */
final class WorkspacePendingUpdate
{
    public const KIND_PROJECT = 'project';
    public const KIND_FILES = 'files';
    public const KIND_PROJECT_AND_FILES = 'project_and_files';
    public const KIND_FILES_DELETE = 'files_delete';

    /**
     * @param list<ProektWorkspace> $projects
     * @param list<array{fileId: string, name: string}> $files
     * @param list<array{fileId: string, name: string}> $filesToDelete
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $projects,
        public readonly array $files,
        public readonly array $filesToDelete,
        public readonly bool $readOnly,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'projects' => array_map(
                static fn (ProektWorkspace $project): array => [
                    'projectId' => $project->projectId,
                    'projectName' => $project->projectName,
                    'projectCode' => $project->projectCode,
                    'canPush' => $project->canPush,
                    'positionIndex' => $project->positionIndex,
                    'mainBranch' => $project->mainBranch,
                    'branch' => $project->branch,
                ],
                $this->projects,
            ),
            'files' => $this->files,
            'filesToDelete' => $this->filesToDelete,
            'readOnly' => $this->readOnly,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $projects = [];
        foreach ((array) ($payload['projects'] ?? []) as $project) {
            if (!is_array($project)) {
                continue;
            }

            $projects[] = new ProektWorkspace(
                (string) ($project['projectId'] ?? ''),
                (string) ($project['projectName'] ?? ''),
                (string) ($project['projectCode'] ?? ''),
                (bool) ($project['canPush'] ?? false),
                (int) ($project['positionIndex'] ?? 0),
                (string) ($project['mainBranch'] ?? ''),
                (string) ($project['branch'] ?? ''),
            );
        }

        $files = [];
        foreach ((array) ($payload['files'] ?? []) as $file) {
            if (!is_array($file) || !isset($file['fileId'], $file['name'])) {
                continue;
            }

            $files[] = [
                'fileId' => (string) $file['fileId'],
                'name' => (string) $file['name'],
            ];
        }

        $filesToDelete = [];
        foreach ((array) ($payload['filesToDelete'] ?? []) as $file) {
            if (!is_array($file) || !isset($file['fileId'], $file['name'])) {
                continue;
            }

            $filesToDelete[] = [
                'fileId' => (string) $file['fileId'],
                'name' => (string) $file['name'],
            ];
        }

        return new self(
            (string) ($payload['kind'] ?? self::KIND_PROJECT),
            $projects,
            $files,
            $filesToDelete,
            (bool) ($payload['readOnly'] ?? false),
        );
    }
}
