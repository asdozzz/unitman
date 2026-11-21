<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Project;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\UstanovitZashishennieVetkiProekta;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Project\Event\ZashishennieVetkiProektaUstanovleni;
use App\Unitman\Business\UseCase\Project\UstanovitZashishennieVetkiProektaUseCase;
use App\Unitman\Tests\Support\FakeUnitmanSecurityService;
use App\Unitman\Tests\Support\InMemoryProjectRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UstanovitZashishennieVetkiProektaUseCaseTest extends TestCase
{
    private const PROJECT_ID = 'project-1';

    #[Test]
    public function admin_ustanavlivaet_zashishennie_vetki(): void
    {
        $repository = $this->repository();
        $useCase = new UstanovitZashishennieVetkiProektaUseCase(
            $repository,
            new FakeUnitmanSecurityService('admin-1', true),
        );

        $useCase->handle(new UstanovitZashishennieVetkiProekta(self::PROJECT_ID, ['main', 'release/*']));

        $project = $repository->getById(self::PROJECT_ID);
        self::assertSame(['main', 'release/*'], $project->poluchitZashishennieVetki());
        self::assertTrue($project->soderzhitZashishennuyuVetku('release/2.0'));

        $events = $project->releaseEvents();
        self::assertCount(3, $events);
        self::assertInstanceOf(ZashishennieVetkiProektaUstanovleni::class, $events[2]);
        self::assertSame(['main', 'release/*'], $events[2]->zashishennieVetki);
    }

    #[Test]
    public function ne_admin_ne_mozhet_ustanovit_vetki(): void
    {
        $repository = $this->repository();
        $useCase = new UstanovitZashishennieVetkiProektaUseCase(
            $repository,
            new FakeUnitmanSecurityService('user-1', false),
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('security.access_denied');

        $useCase->handle(new UstanovitZashishennieVetkiProekta(self::PROJECT_ID, ['*']));
    }

    #[Test]
    public function ne_validnie_vetki_ne_sohranyayutsya(): void
    {
        $repository = $this->repository();
        $useCase = new UstanovitZashishennieVetkiProektaUseCase(
            $repository,
            new FakeUnitmanSecurityService('admin-1', true),
        );

        try {
            $useCase->handle(new UstanovitZashishennieVetkiProekta(self::PROJECT_ID, ['main', 'main']));
            self::fail('Ожидалась ошибка дубликата защищённой ветки');
        } catch (\DomainException $exception) {
            self::assertSame('project.zashishennie_vetki.dublikat', $exception->getMessage());
        }

        self::assertSame([], $repository->getById(self::PROJECT_ID)->poluchitZashishennieVetki());
    }

    private function repository(): InMemoryProjectRepository
    {
        $repository = new InMemoryProjectRepository();
        $repository->save(Project::addProject(
            self::PROJECT_ID,
            new AddProject('repo-1', 'group/project', 'Project', 'main', 'https://proxy.local', 300),
            'admin-1',
        ));

        return $repository;
    }
}
