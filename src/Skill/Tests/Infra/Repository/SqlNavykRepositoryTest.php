<?php

declare(strict_types=1);

namespace App\Skill\Tests\Infra\Repository;

use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Infra\Repository\SqlNavykRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use PHPUnit\Framework\Attributes\Test;

/**
 * SQL-хранилище навыков: маппинг строк, история версий, привязки к проектам и
 * эффективный набор (глобальные + проектные).
 *
 * Тест интеграционный и требует применённых миграций (`opencode_skill*`).
 * Транзакция откатывается в tearDown, поэтому данные не накапливаются.
 */
final class SqlNavykRepositoryTest extends AbstractTestCaseWithTransactionWrapper
{
    private const NOW = 1_700_000_000;

    private function repository(): SqlNavykRepository
    {
        \assert(self::$db !== null);

        return new SqlNavykRepository(self::$db);
    }

    /**
     * @param list<string> $projectIds
     */
    private function sozdat(
        string $id,
        string $slug,
        bool $isGlobal = true,
        array $projectIds = [],
    ): NavykOpenCode {
        return NavykOpenCode::create(
            $id,
            $slug,
            'Навык ' . $id,
            'Описание ' . $id,
            'Содержимое ' . $id,
            $isGlobal,
            $projectIds,
            'account-1',
            self::NOW,
        );
    }

    #[Test]
    public function sozdanie_i_chtenie_globalnogo_navyka(): void
    {
        $id = 'skill-' . bin2hex(random_bytes(4));
        $slug = 'slug-' . bin2hex(random_bytes(4));
        $repository = $this->repository();

        $repository->create($this->sozdat($id, $slug));

        $loaded = $repository->findById($id);

        $this->assertNotNull($loaded);
        $this->assertSame($slug, $loaded->getSlug());
        $this->assertSame('Навык ' . $id, $loaded->getName());
        $this->assertSame('Описание ' . $id, $loaded->getDescription());
        $this->assertSame('Содержимое ' . $id, $loaded->getContent());
        $this->assertSame(1, $loaded->getVersion());
        $this->assertTrue($loaded->isGlobal());
        $this->assertFalse($loaded->isArchived());
        $this->assertSame([], $loaded->getProjectIds());

        $versions = $repository->listVersions($id);
        $this->assertCount(1, $versions);
        $this->assertSame(1, $versions[0]->getVersion());
        $this->assertSame('account-1', $versions[0]->getCreatedBy());
    }

    #[Test]
    public function obnovlenie_podnimaet_versiyu_i_pishet_istoriyu(): void
    {
        $id = 'skill-' . bin2hex(random_bytes(4));
        $slug = 'slug-' . bin2hex(random_bytes(4));
        $repository = $this->repository();
        $navyk = $this->sozdat($id, $slug);

        $repository->create($navyk);

        $izmenilos = $navyk->izmenit('account-2', self::NOW + 100, content: 'Новое содержимое');
        $this->assertTrue($izmenilos);
        $repository->update($navyk);

        $loaded = $repository->findById($id);
        $this->assertNotNull($loaded);
        $this->assertSame(2, $loaded->getVersion());
        $this->assertSame('Новое содержимое', $loaded->getContent());
        $this->assertSame('account-2', $loaded->getUpdatedBy());

        $versions = $repository->listVersions($id);
        $this->assertCount(2, $versions);
        $this->assertSame([2, 1], array_map(static fn ($v): int => $v->getVersion(), $versions));
    }

    #[Test]
    public function privyazka_k_proektam_i_effektivnyi_nabor(): void
    {
        $globalId = 'skill-' . bin2hex(random_bytes(4));
        $projectSkillId = 'skill-' . bin2hex(random_bytes(4));
        $projectId = 'project-' . bin2hex(random_bytes(4));
        $otherProjectId = 'project-' . bin2hex(random_bytes(4));
        $repository = $this->repository();

        $repository->create($this->sozdat($globalId, 'slug-' . bin2hex(random_bytes(4))));
        $repository->create($this->sozdat(
            $projectSkillId,
            'slug-' . bin2hex(random_bytes(4)),
            false,
            [$projectId],
        ));

        $repository->attachToProjects($projectSkillId, [$projectId, $otherProjectId], 'account-2', self::NOW + 1);

        $ozhidaemyeProekty = [$projectId, $otherProjectId];
        sort($ozhidaemyeProekty);

        $loaded = $repository->findById($projectSkillId);
        $this->assertNotNull($loaded);
        // При чтении идентификаторы проектов отсортированы по project_id.
        $this->assertSame($ozhidaemyeProekty, $loaded->getProjectIds());

        $forProject = $repository->listForProjects([$projectId]);
        $this->assertCount(1, $forProject);
        $this->assertSame($projectSkillId, $forProject[0]->getId());

        $effective = $repository->resolveEffectiveForProjects([$projectId]);
        $this->assertArrayHasKey($projectId, $effective);
        $ids = array_map(static fn (NavykOpenCode $n): string => $n->getId(), $effective[$projectId]);
        $this->assertContains($globalId, $ids);
        $this->assertContains($projectSkillId, $ids);
        $this->assertCount(2, $ids);

        $repository->detachFromProject($projectSkillId, $otherProjectId);

        $loaded = $repository->findById($projectSkillId);
        $this->assertNotNull($loaded);
        $this->assertSame([$projectId], $loaded->getProjectIds());
    }

    #[Test]
    public function globalnyi_slug_unikalen_sredi_nearhivnyh(): void
    {
        $slug = 'slug-' . bin2hex(random_bytes(4));
        $repository = $this->repository();

        $repository->create($this->sozdat('skill-' . bin2hex(random_bytes(4)), $slug));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.slug.taken');

        $repository->create($this->sozdat('skill-' . bin2hex(random_bytes(4)), $slug));
    }

    #[Test]
    public function arhivirovanie_isklyuchaet_iz_spiskov(): void
    {
        $id = 'skill-' . bin2hex(random_bytes(4));
        $slug = 'slug-' . bin2hex(random_bytes(4));
        $repository = $this->repository();
        $navyk = $this->sozdat($id, $slug);

        $repository->create($navyk);
        $navyk->arhivirovat('account-2', self::NOW + 100);
        $repository->archive($navyk);

        $this->assertNull($repository->findGlobalBySlug($slug));

        $ids = array_map(
            static fn (NavykOpenCode $n): string => $n->getId(),
            $repository->listGlobal(),
        );
        $this->assertNotContains($id, $ids);

        $loaded = $repository->findById($id);
        $this->assertNotNull($loaded);
        $this->assertTrue($loaded->isArchived());
        $this->assertSame(self::NOW + 100, $loaded->getArchivedAt());
    }
}
