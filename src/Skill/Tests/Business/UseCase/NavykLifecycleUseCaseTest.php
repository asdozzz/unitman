<?php

declare(strict_types=1);

namespace App\Skill\Tests\Business\UseCase;

use App\Skill\Business\Command\IzmenitNavyk;
use App\Skill\Business\Command\OtkrepitNavykOtProekta;
use App\Skill\Business\Command\SozdatNavyk;
use App\Skill\Business\Command\UdalitNavyk;
use App\Skill\Business\UseCase\IzmenitNavykUseCase;
use App\Skill\Business\UseCase\OtkrepitNavykOtProektaUseCase;
use App\Skill\Business\UseCase\SozdatNavykUseCase;
use App\Skill\Business\UseCase\UdalitNavykUseCase;
use App\Skill\Business\Utils\ProverkaPravNavykov;
use App\Skill\Tests\Support\FakePravaNavykov;
use App\Skill\Tests\Support\FixedUuidGenerator;
use App\Skill\Tests\Support\InMemoryNavykRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NavykLifecycleUseCaseTest extends TestCase
{
    private InMemoryNavykRepository $repo;
    private FakePravaNavykov $prava;
    private ProverkaPravNavykov $proverka;

    protected function setUp(): void
    {
        $this->repo = new InMemoryNavykRepository();
        $this->prava = new FakePravaNavykov(admin: true);
        $this->proverka = new ProverkaPravNavykov($this->prava);
    }

    #[Test]
    public function slug_is_generated_from_name(): void
    {
        $useCase = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);

        $result = $useCase->handle(new SozdatNavyk(
            name: 'My First Skill',
            description: '',
            content: 'c',
            scope: 'global',
        ));

        self::assertSame('my-first-skill', $result['slug']);
    }

    #[Test]
    public function explicit_duplicate_global_slug_is_rejected(): void
    {
        $useCase = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);

        $useCase->handle(new SozdatNavyk(
            slug: 'same',
            name: 'First',
            description: '',
            content: 'c',
            scope: 'global',
        ));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.slug.taken');

        $useCase->handle(new SozdatNavyk(
            slug: 'same',
            name: 'Second',
            description: '',
            content: 'c',
            scope: 'global',
        ));
    }

    #[Test]
    public function generated_duplicate_slug_gets_suffix(): void
    {
        $useCase = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);

        $first = $useCase->handle(new SozdatNavyk(
            name: 'Same Name',
            description: '',
            content: 'c',
            scope: 'global',
        ));

        $second = $useCase->handle(new SozdatNavyk(
            name: 'Same Name',
            description: '',
            content: 'c',
            scope: 'global',
        ));

        self::assertSame('same-name', $first['slug']);
        self::assertSame('same-name-2', $second['slug']);
    }

    #[Test]
    public function version_is_bumped_only_when_content_changes(): void
    {
        $create = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);
        $id = $create->handle(new SozdatNavyk(
            name: 'Skill',
            description: 'd',
            content: 'c',
            scope: 'global',
        ))['id'];

        $izmenit = new IzmenitNavykUseCase($this->repo, $this->proverka);

        $same = $izmenit->handle(new IzmenitNavyk($id, name: 'Skill', description: 'd', content: 'c'));
        self::assertSame(1, $same['version']);

        $changed = $izmenit->handle(new IzmenitNavyk($id, content: 'new'));
        self::assertSame(2, $changed['version']);

        self::assertCount(2, $this->repo->listVersions($id));
    }

    #[Test]
    public function archive_marks_skill_archived(): void
    {
        $create = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);
        $id = $create->handle(new SozdatNavyk(
            name: 'Skill',
            description: '',
            content: 'c',
            scope: 'global',
        ))['id'];

        (new UdalitNavykUseCase($this->repo, $this->proverka))->handle(new UdalitNavyk($id));

        $navyk = $this->repo->findById($id);
        self::assertNotNull($navyk);
        self::assertTrue($navyk->isArchived());
        self::assertSame([], $this->repo->listGlobal());
    }

    #[Test]
    public function cannot_detach_last_project(): void
    {
        $this->prava->setRol('project-a', 'ADMIN');
        $create = new SozdatNavykUseCase($this->repo, new FixedUuidGenerator(), $this->proverka);
        $id = $create->handle(new SozdatNavyk(
            name: 'Project skill',
            description: '',
            content: 'c',
            scope: 'project',
            projectIds: ['project-a'],
        ))['id'];

        $detach = new OtkrepitNavykOtProektaUseCase($this->repo, $this->proverka);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('skill.project.required');

        $detach->handle(new OtkrepitNavykOtProekta($id, 'project-a'));
    }
}
