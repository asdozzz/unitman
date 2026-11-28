<?php

declare(strict_types=1);

namespace App\Skill\Tests\Business\UseCase;

use App\Skill\Business\Command\IzmenitNavyk;
use App\Skill\Business\Command\PoluchitNavyk;
use App\Skill\Business\Command\PrikrepitNavykKProektam;
use App\Skill\Business\Command\SozdatNavyk;
use App\Skill\Business\Command\UdalitNavyk;
use App\Skill\Business\UseCase\IzmenitNavykUseCase;
use App\Skill\Business\UseCase\PoluchitNavykQuery;
use App\Skill\Business\UseCase\PrikrepitNavykKProektamUseCase;
use App\Skill\Business\UseCase\SozdatNavykUseCase;
use App\Skill\Business\UseCase\UdalitNavykUseCase;
use App\Skill\Business\Utils\ProverkaPravNavykov;
use App\Skill\Tests\Support\FakePravaNavykov;
use App\Skill\Tests\Support\FixedUuidGenerator;
use App\Skill\Tests\Support\InMemoryNavykRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NavykAuthorizationUseCaseTest extends TestCase
{
    private const PROJECT_A = 'project-a';
    private const PROJECT_B = 'project-b';

    private InMemoryNavykRepository $repo;
    private FakePravaNavykov $prava;
    private ProverkaPravNavykov $proverka;

    protected function setUp(): void
    {
        $this->repo = new InMemoryNavykRepository();
        $this->prava = new FakePravaNavykov();
        $this->proverka = new ProverkaPravNavykov($this->prava);
    }

    #[Test]
    public function global_create_is_denied_for_non_admin(): void
    {
        $useCase = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.forbidden');

        $useCase->handle(new SozdatNavyk(
            name: 'Global',
            description: '',
            content: 'c',
            scope: 'global',
        ));
    }

    #[Test]
    public function global_create_is_allowed_for_admin(): void
    {
        $this->prava->setAdmin(true);
        $useCase = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);

        $result = $useCase->handle(new SozdatNavyk(
            name: 'Global',
            description: '',
            content: 'c',
            scope: 'global',
        ));

        self::assertSame('skill-1', $result['id']);
        self::assertSame('global', $result['slug']);
        self::assertNotNull($this->repo->findById('skill-1'));
    }

    #[Test]
    public function project_create_is_denied_without_admin_role(): void
    {
        $this->prava->setRol(self::PROJECT_A, 'USER');
        $useCase = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.forbidden');

        $useCase->handle(new SozdatNavyk(
            name: 'Project',
            description: '',
            content: 'c',
            scope: 'project',
            projectIds: [self::PROJECT_A],
        ));
    }

    #[Test]
    public function project_create_is_allowed_for_project_admin(): void
    {
        $this->prava->setRol(self::PROJECT_A, 'ADMIN');
        $useCase = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);

        $result = $useCase->handle(new SozdatNavyk(
            name: 'Project',
            description: '',
            content: 'c',
            scope: 'project',
            projectIds: [self::PROJECT_A],
        ));

        self::assertSame('project', $result['slug']);
    }

    #[Test]
    public function project_read_is_denied_for_non_member(): void
    {
        $navykId = $this->createProjectSkill([self::PROJECT_A]);
        $this->prava->udalitRol(self::PROJECT_A);
        $query = new PoluchitNavykQuery($this->repo, $this->proverka);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.forbidden');

        $query->handle(new PoluchitNavyk($navykId));
    }

    #[Test]
    public function project_read_is_allowed_for_member(): void
    {
        $navykId = $this->createProjectSkill([self::PROJECT_A]);
        $this->prava->setRol(self::PROJECT_A, 'USER');
        $query = new PoluchitNavykQuery($this->repo, $this->proverka);

        $result = $query->handle(new PoluchitNavyk($navykId));

        self::assertSame($navykId, $result['id']);
        self::assertFalse($result['canEdit']);
    }

    #[Test]
    public function global_edit_is_denied_for_non_admin(): void
    {
        $this->prava->setAdmin(true);
        $navykId = $this->createGlobalSkill();
        $this->prava->setAdmin(false);

        $useCase = new IzmenitNavykUseCase($this->repo, $this->proverka);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.forbidden');

        $useCase->handle(new IzmenitNavyk($navykId, content: 'new'));
    }

    #[Test]
    public function project_edit_is_allowed_for_project_admin(): void
    {
        $navykId = $this->createProjectSkill([self::PROJECT_A]);
        $this->prava->setRol(self::PROJECT_A, 'ADMIN');

        $useCase = new IzmenitNavykUseCase($this->repo, $this->proverka);
        $result = $useCase->handle(new IzmenitNavyk($navykId, content: 'new-content'));

        self::assertSame($navykId, $result['id']);
        self::assertSame(2, $result['version']);
    }

    #[Test]
    public function archive_is_denied_for_non_owner(): void
    {
        $navykId = $this->createProjectSkill([self::PROJECT_A]);
        $this->prava->setRol(self::PROJECT_A, 'USER');

        $useCase = new UdalitNavykUseCase($this->repo, $this->proverka);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.forbidden');

        $useCase->handle(new UdalitNavyk($navykId));
    }

    #[Test]
    public function attach_requires_admin_role_for_every_project(): void
    {
        $navykId = $this->createProjectSkill([self::PROJECT_A]);
        $this->prava->setRol(self::PROJECT_A, 'ADMIN');
        $this->prava->setRol(self::PROJECT_B, 'USER');

        $useCase = new PrikrepitNavykKProektamUseCase($this->repo, $this->proverka);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.forbidden');

        $useCase->handle(new PrikrepitNavykKProektam($navykId, [self::PROJECT_B]));
    }

    private function createGlobalSkill(): string
    {
        $this->prava->setAdmin(true);
        $useCase = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);

        return $useCase->handle(new SozdatNavyk(
            name: 'Global',
            description: '',
            content: 'c',
            scope: 'global',
        ))['id'];
    }

    /**
     * @param list<string> $projectIds
     */
    private function createProjectSkill(array $projectIds): string
    {
        foreach ($projectIds as $projectId) {
            $this->prava->setRol($projectId, 'ADMIN');
        }

        $useCase = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);

        return $useCase->handle(new SozdatNavyk(
            name: 'Project',
            description: '',
            content: 'c',
            scope: 'project',
            projectIds: $projectIds,
        ))['id'];
    }
}
