<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Project;

use App\Unitman\Business\Command\Project\SozdatVetkuProekta;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatTokenDlyaProekta;
use App\Unitman\Business\Port\Project\UmeetSozdavatVetkuProekta;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;

/**
 * Создание ветки проекта в хранилище от указанной базовой ветки.
 *
 * Токен пользователя разрешается по проекту, поэтому создаваемая ветка
 * появляется в хранилище от имени автора запроса.
 */
final class SozdatVetkuProektaUseCase
{
    private const MAX_BRANCH_LENGTH = 255;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly RepoRepository $repoRepository,
        private readonly UnitmanSecurityService $securityService,
        private readonly UmeetPoluchatTokenDlyaProekta $tokenResolver,
        private readonly UmeetSozdavatVetkuProekta $storage,
    ) {
    }

    public function handle(SozdatVetkuProekta $command): string
    {
        $projectId = trim($command->projectId);
        $nazvanie = trim($command->nazvanie);
        $otVetki = trim($command->otVetki);

        if ($projectId === '') {
            throw new \DomainException('project.id_is_empty');
        }

        if (!$this->etoKorrektnayaVetka($nazvanie) || !$this->etoKorrektnayaVetka($otVetki)) {
            throw new \DomainException('project.branch_invalid');
        }

        $accountId = trim($this->securityService->getCurrentUserId());

        if ($accountId === '') {
            throw new \DomainException('security.access_denied');
        }

        $project = $this->projectRepository->getById($projectId);
        $repo = $this->repoRepository->getById($project->getRepoId());

        $token = $this->poluchitToken($projectId, $accountId);

        try {
            $this->storage->sozdatVetkuVHranilishe($repo, $project->getCode(), $nazvanie, $otVetki, $token);
        } catch (\Throwable $exception) {
            throw $this->perevodOshibkiHranilisha($exception);
        }

        return $nazvanie;
    }

    private function poluchitToken(string $projectId, string $accountId): string
    {
        try {
            return $this->tokenResolver->poluchitTokenDlyProekta($projectId, $accountId);
        } catch (\DomainException $exception) {
            $key = match ($exception->getMessage()) {
                'unit.redaktor.net_lichnogo_tokena' => 'project.net_lichnogo_tokena',
                'unit.redaktor.token_bez_prav_na_proekt' => 'project.branch_bez_prav',
                default => null,
            };

            if ($key === null) {
                throw $exception;
            }

            throw new \DomainException($key, 0, $exception);
        }
    }

    /**
     * Правила соответствуют ограничениям git на имена ref.
     */
    private function etoKorrektnayaVetka(string $name): bool
    {
        if ($name === '' || strlen($name) > self::MAX_BRANCH_LENGTH) {
            return false;
        }

        if (preg_match('/\s/u', $name) === 1) {
            return false;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            return false;
        }

        if (str_contains($name, '..') || str_contains($name, '//') || str_contains($name, '@{')) {
            return false;
        }

        if (strpbrk($name, '~^:?*[\\') !== false) {
            return false;
        }

        if (str_starts_with($name, '/') || str_ends_with($name, '/')) {
            return false;
        }

        if (str_starts_with($name, '.') || str_ends_with($name, '.')) {
            return false;
        }

        if (str_ends_with($name, '.lock')) {
            return false;
        }

        foreach (explode('/', $name) as $segment) {
            if ($segment === '' || $segment === '@' || str_starts_with($segment, '.') || str_ends_with($segment, '.lock')) {
                return false;
            }
        }

        return true;
    }

    private function perevodOshibkiHranilisha(\Throwable $exception): \Throwable
    {
        $message = mb_strtolower($exception->getMessage());

        if (
            str_contains($message, 'already exists')
            || str_contains($message, 'already_exists')
            || str_contains($message, 'reference already exists')
            || str_contains($message, 'branch already exists')
        ) {
            return new \DomainException('project.branch_exists', 0, $exception);
        }

        if (
            str_contains($message, 'cannot resolve sha of base branch')
            || str_contains($message, 'base branch')
            || str_contains($message, 'branch not found')
        ) {
            return new \DomainException('project.branch_invalid', 0, $exception);
        }

        if (
            str_contains($message, '403')
            || str_contains($message, '401')
            || str_contains($message, 'forbidden')
            || str_contains($message, 'unauthorized')
            || str_contains($message, 'access denied')
            || str_contains($message, 'not allowed')
            || str_contains($message, 'permission')
            || str_contains($message, 'insufficient')
        ) {
            return new \DomainException('project.branch_bez_prav', 0, $exception);
        }

        return $exception;
    }
}
