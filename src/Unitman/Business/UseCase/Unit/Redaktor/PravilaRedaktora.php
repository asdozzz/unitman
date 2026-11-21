<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit\Redaktor;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Project\UmeetProveryatPushTokenom;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\UmeetPoluchatTokenRedaktirovaniya;
use App\Unitman\Business\Port\UnitmanSecurityService;

/**
 * Правила прав редактора юнита (plan §3, §15.4-б, §15.5-а, §15.8-б).
 *
 * Управлять режимом и редакторами может автор либо админ; админ — только
 * имея личный токен с правами на проект. Живая перепроверка конкретного
 * проекта выполняется в критичный момент через storage API.
 */
final class PravilaRedaktora
{
    public function __construct(
        private UnitmanSecurityService $securityService,
        private RepoRepository $repoRepository,
        private UmeetPoluchatTokenRedaktirovaniya $tokenResolver,
        private UmeetProveryatPushTokenom $proverkaPusha,
        private UmeetPoluchatSpisokProektovDlyPolzovatelya $spisokProektov,
    )
    {
    }

    public function tekushiyPolzovatel(): string
    {
        return trim($this->securityService->getCurrentUserId());
    }

    /**
     * Автор либо админ. Наличие токена у админа проверяется живой проверкой
     * отдельно — в момент операции.
     */
    public function trebovatPravoUpravleniya(Unit $unit, Project $project): string
    {
        $userId = $this->tekushiyPolzovatel();

        if ($userId === '') {
            throw new \DomainException('security.access_denied');
        }

        if ($unit->getAuthorId() === $userId) {
            return $userId;
        }

        if (!$this->securityService->isAdmin()) {
            throw new \DomainException('unit.redaktor.ne_avtor');
        }

        return $userId;
    }

    /**
     * Живая проверка личного токена пользователя на конкретный проект.
     *
     * @return string расшифрованный токен
     * @throws \DomainException unit.redaktor.net_lichnogo_tokena|unit.redaktor.token_bez_prav_na_proekt
     */
    public function proveritZhivoyToken(string $unitId, string $userId, Project $project): string
    {
        $token = $this->tokenResolver->poluchitToken($unitId, $userId);
        $repo = $this->repoRepository->getById($project->getRepoId());

        if (!$this->proverkaPusha->canPushTokenom($token, $repo, $project)) {
            throw new \DomainException('unit.redaktor.token_bez_prav_na_proekt');
        }

        return $token;
    }

    /**
     * Доступ пользователя к проекту (15.5-а): прикреплять можно только
     * участника проекта.
     */
    public function proveritDostupKProektu(string $userId, string $projectId): void
    {
        foreach ($this->spisokProektov->poluchitSpisokProektovDlyPolzovatelya($userId) as $project) {
            if (($project['id'] ?? null) === $projectId) {
                return;
            }
        }

        throw new \DomainException('unit.redaktor.net_dostupa_k_proektu');
    }
}
