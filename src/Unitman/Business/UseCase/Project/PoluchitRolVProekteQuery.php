<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Port\Project\ProjectRepository;

/**
 * Роль пользователя в проекте.
 *
 * Нужна внешним модулям (Skill) для проверки прав без доступа к агрегату
 * `Project` и его внутренним моделям. Неизвестный проект или пользователь,
 * не являющийся участником, дают `null` — вызывающий код сам выбирает
 * политику (скрыть существование либо отказать).
 */
final class PoluchitRolVProekteQuery
{
    public function __construct(private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @return 'ADMIN'|'USER'|null
     */
    public function handle(string $projectId, string $accountId): ?string
    {
        if (trim($projectId) === '' || trim($accountId) === '') {
            return null;
        }

        try {
            $project = $this->projectRepository->getById($projectId);
        } catch (\DomainException) {
            return null;
        }

        try {
            return $project->getProjectUserById($accountId)->userRole->value;
        } catch (\DomainException) {
            return null;
        }
    }
}
