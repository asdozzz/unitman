<?php

declare(strict_types=1);

namespace App\Skill\Tests\Business\Utils;

use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Business\ReadModel\RezultatEffektivnyhNavykov;
use App\Skill\Business\Utils\PostroenieEffektivnyhNavykov;
use App\Skill\Tests\Support\InMemoryNavykRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PostroenieEffektivnyhNavykovTest extends TestCase
{
    private const PROJECT_A = 'aaaaaaaa-1111-2222-3333-444444444444';
    private const PROJECT_B = 'bbbbbbbb-1111-2222-3333-444444444444';

    private InMemoryNavykRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new InMemoryNavykRepository();
    }

    #[Test]
    public function globals_come_first_and_order_is_deterministic(): void
    {
        $this->createSkill('g-zzz', 'zzz-global', 'ZZZ global', 'G', true, []);
        $this->createSkill('p-aaa', 'aaa-project', 'AAA project', 'P', false, [self::PROJECT_A]);

        $result = $this->postroit([self::PROJECT_A]);

        self::assertSame(
            ['ZZZ global', 'AAA project'],
            array_map(static fn ($navyk): string => $navyk->toArray()['name'], $result->getNavyki()),
        );
    }

    #[Test]
    public function container_names_and_project_ids_are_built_correctly(): void
    {
        $this->createSkill('g-1', 'global-skill', 'Global', 'G', true, []);
        $this->createSkill('p-1', 'project-skill', 'Project', 'P', false, [self::PROJECT_A]);

        $result = $this->postroit([self::PROJECT_A]);
        $items = array_map(static fn ($navyk): array => $navyk->toArray(), $result->getNavyki());

        self::assertSame('global-global-skill', $items[0]['containerName']);
        self::assertNull($items[0]['projectId']);
        self::assertSame('global', $items[0]['scope']);

        self::assertSame('paaaaaaaa-project-skill', $items[1]['containerName']);
        self::assertSame(self::PROJECT_A, $items[1]['projectId']);
        self::assertSame('project', $items[1]['scope']);
    }

    #[Test]
    public function project_skill_uses_first_requested_project(): void
    {
        $this->createSkill('p-1', 'shared', 'Shared', 'P', false, [self::PROJECT_A, self::PROJECT_B]);

        $result = $this->postroit([self::PROJECT_B, self::PROJECT_A]);
        $item = $result->getNavyki()[0]->toArray();

        self::assertSame(self::PROJECT_B, $item['projectId']);
        self::assertSame('pbbbbbbbb-shared', $item['containerName']);
    }

    #[Test]
    public function manifest_hash_matches_documented_format(): void
    {
        $this->createSkill('g-1', 'b-global', 'B global', 'BB');
        $this->createSkill('g-2', 'a-global', 'A global', 'A');
        $this->createSkill('p-1', 'project', 'Project', 'P', false, [self::PROJECT_A]);

        $result = $this->postroit([self::PROJECT_A]);

        $lines = [
            'global-a-global:1:' . hash('sha256', 'A global||A'),
            'global-b-global:1:' . hash('sha256', 'B global||BB'),
            'paaaaaaaa-project:1:' . hash('sha256', 'Project||P'),
        ];
        sort($lines, SORT_STRING);

        self::assertSame(hash('sha256', implode("\n", $lines)), $result->getManifestHash());
    }

    #[Test]
    public function manifest_hash_is_deterministic_between_calls(): void
    {
        $this->createSkill('g-1', 'global', 'Global', 'G');

        $first = $this->postroit([self::PROJECT_A]);
        $second = $this->postroit([self::PROJECT_A]);

        self::assertSame($first->getManifestHash(), $second->getManifestHash());
        self::assertSame($first->getTotalBytes(), $second->getTotalBytes());
    }

    #[Test]
    public function empty_manifest_hash_is_sha256_of_empty_string(): void
    {
        $result = $this->postroit([]);

        self::assertSame(hash('sha256', ''), $result->getManifestHash());
        self::assertSame([], $result->getNavyki());
        self::assertSame(0, $result->getTotalBytes());
        self::assertFalse($result->isTruncated());
    }

    #[Test]
    public function truncates_by_skill_count(): void
    {
        $this->createSkill('g-1', 'a', 'A', 'a');
        $this->createSkill('g-2', 'b', 'B', 'b');

        $result = $this->postroit([self::PROJECT_A], 1, PostroenieEffektivnyhNavykov::MAX_TOTAL_BYTES);

        self::assertCount(1, $result->getNavyki());
        self::assertTrue($result->isTruncated());
    }

    #[Test]
    public function truncates_by_total_bytes(): void
    {
        $this->createSkill('g-1', 'a', 'A', str_repeat('a', 10));
        $this->createSkill('g-2', 'b', 'B', str_repeat('b', 10));

        $result = $this->postroit([self::PROJECT_A], 50, 15);

        self::assertCount(1, $result->getNavyki());
        self::assertSame(10, $result->getTotalBytes());
        self::assertTrue($result->isTruncated());
    }

    /**
     * @param list<string> $projectIds
     *
     * @return RezultatEffektivnyhNavykov
     */
    private function postroit(
        array $projectIds,
        int $maxSkills = PostroenieEffektivnyhNavykov::MAX_SKILLS,
        int $maxBytes = PostroenieEffektivnyhNavykov::MAX_TOTAL_BYTES,
    ): RezultatEffektivnyhNavykov {
        return (new PostroenieEffektivnyhNavykov($this->repo))->postroit($projectIds, $maxSkills, $maxBytes);
    }

    /**
     * @param list<string> $projectIds
     */
    private function createSkill(
        string $id,
        string $slug,
        string $name,
        string $content,
        bool $isGlobal = true,
        array $projectIds = [],
    ): void {
        $this->repo->create(NavykOpenCode::create(
            $id,
            $slug,
            $name,
            '',
            $content,
            $isGlobal,
            $projectIds,
            'account-1',
            1000,
        ));
    }
}
