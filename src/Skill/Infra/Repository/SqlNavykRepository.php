<?php

declare(strict_types=1);

namespace App\Skill\Infra\Repository;

use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Business\Model\VersiyaNavyka;
use App\Skill\Business\Port\NavykRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * SQL-хранилище навыков OpenCode (Doctrine DBAL, без ORM).
 *
 * Таблицы и привязки описаны в `App\Skill\Migrations\Version20261128120000`.
 * История версий пишется только при росте `version`: модель поднимает версию
 * при изменении name/description/content. Мультистрочные операции выполняются
 * в транзакции.
 *
 * ВАЖНО: FK на проекты нет — проекты живут в event-sourcing и удаляются
 * асинхронно. Привязки к несуществующему проекту не считаются ошибкой.
 *
 * Уникальность slug глобальных навыков проверяется здесь, в коде: частичный
 * уникальный индекс (`where archived_at is null`) не переносим между
 * Postgres, MariaDB и MySQL, поэтому в миграции только обычный индекс.
 * Гонка двух параллельных create остаётся теоретически возможной — жёсткую
 * гарантию можно добавить отдельной задачей (например, отдельная таблица
 * занятых slug).
 */
final class SqlNavykRepository implements NavykRepository
{
    private const TABLE = 'opencode_skill';
    private const PROJECT_TABLE = 'opencode_skill_project';
    private const VERSION_TABLE = 'opencode_skill_version';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(NavykOpenCode $navyk): void
    {
        $this->connection->transactional(function () use ($navyk): void {
            if ($navyk->isGlobal() && $this->isGlobalSlugTaken($navyk->getSlug(), $navyk->getId())) {
                throw new \DomainException('skill.slug.taken');
            }

            $this->connection->insert(self::TABLE, $this->skillData($navyk), $this->skillTypes());
            $this->insertVersion($navyk, $navyk->getCreatedBy(), $navyk->getCreatedAt());
            $this->attachToProjectsInternal(
                $navyk->getId(),
                $navyk->getProjectIds(),
                $navyk->getCreatedBy(),
                $navyk->getCreatedAt(),
            );
        });
    }

    public function update(NavykOpenCode $navyk): void
    {
        $this->connection->transactional(function () use ($navyk): void {
            if ($navyk->isGlobal() && $this->isGlobalSlugTaken($navyk->getSlug(), $navyk->getId())) {
                throw new \DomainException('skill.slug.taken');
            }

            $currentVersion = $this->connection->fetchOne(
                'select version from ' . self::TABLE . ' where id = :id',
                ['id' => $navyk->getId()],
            );

            if ($currentVersion === false) {
                throw new \DomainException('skill.not_found');
            }

            $this->connection->update(
                self::TABLE,
                $this->skillData($navyk),
                ['id' => $navyk->getId()],
                $this->skillTypes() + ['id' => ParameterType::STRING],
            );

            if ($navyk->getVersion() > (int) $currentVersion) {
                $uzheEst = (int) $this->connection->fetchOne(
                    'select count(*) from ' . self::VERSION_TABLE . ' where skill_id = :id and version = :version',
                    ['id' => $navyk->getId(), 'version' => $navyk->getVersion()],
                );

                if ($uzheEst === 0) {
                    $this->insertVersion($navyk, $navyk->getUpdatedBy(), $navyk->getUpdatedAt());
                }
            }
        });
    }

    public function archive(NavykOpenCode $navyk): void
    {
        if (!$navyk->isArchived()) {
            return;
        }

        $this->connection->update(
            self::TABLE,
            [
                'archived_at' => $navyk->getArchivedAt(),
                'updated_by' => $navyk->getUpdatedBy(),
                'updated_at' => $navyk->getUpdatedAt(),
            ],
            ['id' => $navyk->getId()],
            [
                'archived_at' => ParameterType::INTEGER,
                'updated_by' => ParameterType::STRING,
                'updated_at' => ParameterType::INTEGER,
                'id' => ParameterType::STRING,
            ],
        );
    }

    public function findById(string $id): ?NavykOpenCode
    {
        $row = $this->connection->fetchAssociative(
            'select * from ' . self::TABLE . ' where id = :id',
            ['id' => $id],
        );

        if ($row === false) {
            return null;
        }

        $projects = $this->fetchProjectIds([(string) $row['id']]);

        return $this->makeNavyk($row, $projects[(string) $row['id']] ?? []);
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'select * from ' . self::TABLE . ' where id in (:ids) order by name, id',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::STRING],
        );

        if ($rows === []) {
            return [];
        }

        $skillIds = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $projects = $this->fetchProjectIds($skillIds);

        $rezultat = [];

        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $rezultat[] = $this->makeNavyk($row, $projects[$id] ?? []);
        }

        return $rezultat;
    }

    public function listGlobal(bool $includeArchived = false): array
    {
        $sql = 'select * from ' . self::TABLE . ' where is_global = true';

        if (!$includeArchived) {
            $sql .= ' and archived_at is null';
        }

        $sql .= ' order by name, id';

        $rows = $this->connection->fetchAllAssociative($sql);

        return $this->makeNavki($rows);
    }

    public function listForProjects(array $projectIds, bool $includeArchived = false): array
    {
        if ($projectIds === []) {
            return [];
        }

        // EXISTS вместо DISTINCT+JOIN: Postgres запрещает ORDER BY по
        // колонкам, отсутствующим в SELECT DISTINCT, а EXISTS переносим
        // между Postgres, MariaDB и MySQL без изменений.
        $sql = 'select s.id as id from ' . self::TABLE . ' s where ';
        $sql .= $includeArchived ? 'true' : 's.archived_at is null';
        $sql .= ' and exists ('
            . '   select 1 from ' . self::PROJECT_TABLE . ' sp'
            . '   where sp.skill_id = s.id and sp.project_id in (:ids)'
            . ' )'
            . ' order by s.name, s.id';

        $rows = $this->connection->fetchAllAssociative(
            $sql,
            ['ids' => $projectIds],
            ['ids' => ArrayParameterType::STRING],
        );

        $orderedIds = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $byId = [];

        foreach ($this->findByIds($orderedIds) as $navyk) {
            $byId[$navyk->getId()] = $navyk;
        }

        $rezultat = [];

        foreach ($orderedIds as $id) {
            if (isset($byId[$id])) {
                $rezultat[] = $byId[$id];
            }
        }

        return $rezultat;
    }

    public function attachToProjects(string $skillId, array $projectIds, string $addedBy, int $now): void
    {
        $this->connection->transactional(function () use ($skillId, $projectIds, $addedBy, $now): void {
            $this->attachToProjectsInternal($skillId, $projectIds, $addedBy, $now);
        });
    }

    public function detachFromProject(string $skillId, string $projectId): void
    {
        $this->connection->delete(
            self::PROJECT_TABLE,
            ['skill_id' => $skillId, 'project_id' => $projectId],
            ['skill_id' => ParameterType::STRING, 'project_id' => ParameterType::STRING],
        );
    }

    public function listVersions(string $skillId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'select * from ' . self::VERSION_TABLE
            . ' where skill_id = :id order by version desc',
            ['id' => $skillId],
        );

        $rezultat = [];

        foreach ($rows as $row) {
            $rezultat[] = VersiyaNavyka::restore(
                (string) $row['skill_id'],
                (int) $row['version'],
                (string) $row['name'],
                (string) $row['description'],
                (string) $row['content'],
                (string) $row['content_hash'],
                (string) $row['created_by'],
                (int) $row['created_at'],
            );
        }

        return $rezultat;
    }

    public function resolveEffectiveForProjects(array $projectIds): array
    {
        $unikalnye = [];

        foreach ($projectIds as $projectId) {
            if (!\in_array($projectId, $unikalnye, true)) {
                $unikalnye[] = $projectId;
            }
        }

        $globalnye = $this->listGlobal();
        $proektnye = $this->listForProjects($projectIds);
        $rezultat = [];

        foreach ($unikalnye as $projectId) {
            $effektivnye = $globalnye;

            foreach ($proektnye as $navyk) {
                if (\in_array($projectId, $navyk->getProjectIds(), true)) {
                    $effektivnye[] = $navyk;
                }
            }

            usort(
                $effektivnye,
                static fn (NavykOpenCode $a, NavykOpenCode $b): int
                    => [$a->getName(), $a->getSlug(), $a->getId()]
                    <=> [$b->getName(), $b->getSlug(), $b->getId()],
            );

            $rezultat[$projectId] = $effektivnye;
        }

        return $rezultat;
    }

    public function isGlobalSlugTaken(string $slug, ?string $excludeSkillId = null): bool
    {
        $sql = 'select count(*) from ' . self::TABLE
            . ' where is_global = true and slug = :slug and archived_at is null';

        $params = ['slug' => $slug];

        if ($excludeSkillId !== null) {
            $sql .= ' and id <> :exclude';
            $params['exclude'] = $excludeSkillId;
        }

        return (int) $this->connection->fetchOne($sql, $params) > 0;
    }

    public function findGlobalBySlug(string $slug): ?NavykOpenCode
    {
        $row = $this->connection->fetchAssociative(
            'select * from ' . self::TABLE
            . ' where is_global = true and slug = :slug and archived_at is null'
            . ' order by created_at, id limit 1',
            ['slug' => $slug],
        );

        if ($row === false) {
            return null;
        }

        $projects = $this->fetchProjectIds([(string) $row['id']]);

        return $this->makeNavyk($row, $projects[(string) $row['id']] ?? []);
    }

    /**
     * @param list<string> $projectIds
     */
    private function attachToProjectsInternal(string $skillId, array $projectIds, string $addedBy, int $now): void
    {
        if ($projectIds === []) {
            return;
        }

        $uzhePrivyazany = $this->connection->fetchFirstColumn(
            'select project_id from ' . self::PROJECT_TABLE . ' where skill_id = :id and project_id in (:ids)',
            ['id' => $skillId, 'ids' => $projectIds],
            ['ids' => ArrayParameterType::STRING],
        );

        $privyazany = [];

        foreach ($uzhePrivyazany as $projectId) {
            $privyazany[(string) $projectId] = true;
        }

        foreach ($projectIds as $projectId) {
            if (isset($privyazany[$projectId])) {
                continue;
            }

            $this->connection->insert(
                self::PROJECT_TABLE,
                [
                    'skill_id' => $skillId,
                    'project_id' => $projectId,
                    'added_by' => $addedBy,
                    'added_at' => $now,
                ],
                [
                    'skill_id' => ParameterType::STRING,
                    'project_id' => ParameterType::STRING,
                    'added_by' => ParameterType::STRING,
                    'added_at' => ParameterType::INTEGER,
                ],
            );
        }
    }

    /**
     * @param list<string> $skillIds
     *
     * @return array<string, list<string>>
     */
    private function fetchProjectIds(array $skillIds): array
    {
        if ($skillIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'select skill_id, project_id from ' . self::PROJECT_TABLE
            . ' where skill_id in (:ids) order by skill_id, project_id',
            ['ids' => $skillIds],
            ['ids' => ArrayParameterType::STRING],
        );

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(string) $row['skill_id']][] = (string) $row['project_id'];
        }

        return $grouped;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<NavykOpenCode>
     */
    private function makeNavki(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $skillIds = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $projects = $this->fetchProjectIds($skillIds);

        $rezultat = [];

        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $rezultat[] = $this->makeNavyk($row, $projects[$id] ?? []);
        }

        return $rezultat;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $projectIds
     */
    private function makeNavyk(array $row, array $projectIds): NavykOpenCode
    {
        return NavykOpenCode::restore(
            (string) $row['id'],
            (string) $row['slug'],
            (string) $row['name'],
            (string) $row['description'],
            (string) $row['content'],
            (string) $row['content_hash'],
            (int) $row['version'],
            self::toBool($row['is_global']),
            $projectIds,
            (string) $row['created_by'],
            (string) $row['updated_by'],
            (int) $row['created_at'],
            (int) $row['updated_at'],
            $row['archived_at'] === null ? null : (int) $row['archived_at'],
        );
    }

    private function insertVersion(NavykOpenCode $navyk, string $actor, int $at): void
    {
        $this->connection->insert(
            self::VERSION_TABLE,
            [
                'skill_id' => $navyk->getId(),
                'version' => $navyk->getVersion(),
                'name' => $navyk->getName(),
                'description' => $navyk->getDescription(),
                'content' => $navyk->getContent(),
                'content_hash' => $navyk->getContentHash(),
                'created_by' => $actor,
                'created_at' => $at,
            ],
            [
                'skill_id' => ParameterType::STRING,
                'version' => ParameterType::INTEGER,
                'name' => ParameterType::STRING,
                'description' => ParameterType::STRING,
                'content' => ParameterType::STRING,
                'content_hash' => ParameterType::STRING,
                'created_by' => ParameterType::STRING,
                'created_at' => ParameterType::INTEGER,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function skillData(NavykOpenCode $navyk): array
    {
        return [
            'id' => $navyk->getId(),
            'slug' => $navyk->getSlug(),
            'name' => $navyk->getName(),
            'description' => $navyk->getDescription(),
            'content' => $navyk->getContent(),
            'content_hash' => $navyk->getContentHash(),
            'version' => $navyk->getVersion(),
            'is_global' => $navyk->isGlobal(),
            'created_by' => $navyk->getCreatedBy(),
            'updated_by' => $navyk->getUpdatedBy(),
            'created_at' => $navyk->getCreatedAt(),
            'updated_at' => $navyk->getUpdatedAt(),
            'archived_at' => $navyk->getArchivedAt(),
        ];
    }

    /**
     * @return array<string, ParameterType>
     */
    private function skillTypes(): array
    {
        return [
            'id' => ParameterType::STRING,
            'slug' => ParameterType::STRING,
            'name' => ParameterType::STRING,
            'description' => ParameterType::STRING,
            'content' => ParameterType::STRING,
            'content_hash' => ParameterType::STRING,
            'version' => ParameterType::INTEGER,
            'is_global' => ParameterType::BOOLEAN,
            'created_by' => ParameterType::STRING,
            'updated_by' => ParameterType::STRING,
            'created_at' => ParameterType::INTEGER,
            'updated_at' => ParameterType::INTEGER,
            'archived_at' => ParameterType::INTEGER,
        ];
    }

    private static function toBool(mixed $value): bool
    {
        // PDO-драйверы возвращают boolean по-разному: bool, 0/1, '0'/'1',
        // 't'/'f'. Приводим к bool без ложноположительного `(bool) 'f'`.
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true';
    }
}
