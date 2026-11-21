<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit\Redaktor;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenListRepository;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenList;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;

/**
 * Вычисление режима редактирования юнита для текущего пользователя (plan §3).
 *
 * `moguRedaktirovat` = пользователь автор или в редакторах И его личный токен
 * покрывает проект И редактирование включено И ветка не защищена (15.7-а).
 */
final class OpredelitelRezhimaRedaktora
{
    public function __construct(
        private LichniyTokenListRepository $tokenListRepository,
        private ProjectRepository $projectRepository,
    )
    {
    }

    /**
     * Обогащает строки списка юнитов вычисляемыми полями режима.
     *
     * Проекты кешируются в пределах запроса; состояние в сервисе не хранится.
     *
     * @param array<int, array<string, mixed>> $units
     * @return array<int, array<string, mixed>>
     */
    public function obogatitSpisok(array $units, string $userId): array
    {
        $tokeny = $this->zagruzitTokeny($userId);
        $projects = [];

        foreach ($units as $index => $unit) {
            $projectId = (string) ($unit['projectId'] ?? '');

            if (!array_key_exists($projectId, $projects)) {
                try {
                    $projects[$projectId] = $this->projectRepository->getById($projectId);
                } catch (\DomainException) {
                    $projects[$projectId] = null;
                }
            }

            $project = $projects[$projectId];

            if (!$project instanceof Project) {
                $units[$index]['redaktorMode'] = 'view';
                $units[$index]['moguRedaktirovat'] = false;
                $units[$index]['vetkaZashishena'] = false;
                $units[$index]['prichinaZapreta'] = null;

                continue;
            }

            $data = $this->opredelit(
                $project,
                (string) ($unit['branch'] ?? ''),
                (string) ($unit['authorId'] ?? ''),
                array_values(array_map(static fn (mixed $id): string => (string) $id, (array) ($unit['redaktory'] ?? []))),
                (bool) ($unit['redaktirovanieVklucheno'] ?? false),
                (bool) ($unit['unitSozdanSystemoi'] ?? false),
                $userId,
                $tokeny,
            );

            $units[$index] = array_merge($unit, $data);
        }

        return $units;
    }

    public function obogatitUnicu(SpisokUnitovReadModel $unit, string $userId): SpisokUnitovReadModel
    {
        $project = $this->projectRepository->getById($unit->projectId);
        $data = $this->opredelit(
            $project,
            $unit->branch,
            $unit->authorId,
            array_values(array_map(static fn (string $id): string => $id, $unit->redaktory)),
            $unit->redaktirovanieVklucheno,
            $unit->unitSozdanSystemoi,
            $userId,
            $this->zagruzitTokeny($userId),
        );

        return $unit->copyAndUpdateData($data);
    }

    /**
     * Токены пользователя загружаются один раз на список/карточку.
     *
     * @return LichniyTokenList[]
     */
    public function zagruzitTokeny(string $userId): array
    {
        if (trim($userId) === '') {
            return [];
        }

        return $this->tokenListRepository->findByAccountId($userId);
    }

    /**
     * @param string[] $redaktory
     * @param LichniyTokenList[] $tokeny
     * @return array{vetkaZashishena: bool, redaktorMode: string, moguRedaktirovat: bool, prichinaZapreta: string|null}
     */
    public function opredelit(
        Project $project,
        string $branch,
        string $authorId,
        array $redaktory,
        bool $redaktirovanieVklucheno,
        bool $unitSozdanSystemoi,
        string $userId,
        array $tokeny,
    ): array {
        $vetkaZashishena = $project->soderzhitZashishennuyuVetku($branch);

        if ($unitSozdanSystemoi) {
            return [
                'vetkaZashishena' => $vetkaZashishena,
                'redaktorMode' => 'view',
                'moguRedaktirovat' => false,
                'prichinaZapreta' => $vetkaZashishena ? 'protected_branch' : null,
            ];
        }

        $isEditor = $userId !== '' && ($userId === $authorId || in_array($userId, $redaktory, true));
        $pokryvaet = $this->pokryvaetProekt($tokeny, $project);
        $mogu = $isEditor && $pokryvaet && $redaktirovanieVklucheno && !$vetkaZashishena;

        if ($vetkaZashishena) {
            $mode = 'locked';
            $prichina = 'protected_branch';
        } elseif ($mogu) {
            $mode = 'edit';
            $prichina = null;
        } else {
            $mode = 'view';
            $prichina = $isEditor && $redaktirovanieVklucheno && !$pokryvaet
                ? 'net_tokena'
                : null;
        }

        return [
            'vetkaZashishena' => $vetkaZashishena,
            'redaktorMode' => $mode,
            'moguRedaktirovat' => $mogu,
            'prichinaZapreta' => $prichina,
        ];
    }

    /**
     * @param LichniyTokenList[] $tokeny
     */
    public function pokryvaetProekt(array $tokeny, Project $project): bool
    {
        foreach ($tokeny as $token) {
            if ($token->deleted) {
                continue;
            }

            if ($token->repoId !== $project->getRepoId()) {
                continue;
            }

            if ($token->pokryvaetProekt($project->getId())) {
                return true;
            }
        }

        return false;
    }
}
