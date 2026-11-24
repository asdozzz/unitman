<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Command\Unit\Redaktor\PrikrepitRedaktoraKUnitu;
use App\Unitman\Business\Command\Unit\Redaktor\VklyuchitRedaktirovanieUnita;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Project\UmeetProveryatPushTokenom;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\Port\Unit\UmeetPoluchatTokenRedaktirovaniya;
use App\Unitman\Business\UseCase\Unit\Redaktor\PravilaRedaktora;
use App\Unitman\Business\UseCase\Unit\Redaktor\PrikrepitRedaktoraKUnituUseCase;
use App\Unitman\Business\UseCase\Unit\Redaktor\VklyuchitRedaktirovanieUnitaUseCase;
use App\Unitman\Tests\Support\FakeUnitmanSecurityService;
use App\Unitman\Tests\Support\InMemoryProjectRepository;
use App\Unitman\Tests\Support\InMemoryUnitRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Матрица прав включения редактирования и прикрепления редактора.
 */
final class RedaktorUseCaseTest extends TestCase
{
    private const UNIT_ID = 'unit-1';
    private const PROJECT_ID = 'project-1';
    private const AUTHOR_ID = 'author-1';

    #[Test]
    public function avtor_s_tokenom_vklyuchaet_redaktirovanie(): void
    {
        $unit = $this->unit();
        $units = $this->units($unit);
        $security = new FakeUnitmanSecurityService(self::AUTHOR_ID, false);

        $useCase = new VklyuchitRedaktirovanieUnitaUseCase(
            $units,
            $this->projects(),
            $this->pravila($security, ['author-1' => 'personal-token']),
            $this->createMock(RunnerService::class),
        );

        $useCase->handle(new VklyuchitRedaktirovanieUnita(self::UNIT_ID));

        self::assertTrue($units->getById(self::UNIT_ID)->getRedaktirovanieVklucheno());
    }

    #[Test]
    public function avtor_bez_tokena_ne_vklyuchaet_redaktirovanie(): void
    {
        $unit = $this->unit();
        $security = new FakeUnitmanSecurityService(self::AUTHOR_ID, false);

        $useCase = new VklyuchitRedaktirovanieUnitaUseCase(
            $this->units($unit),
            $this->projects(),
            $this->pravila($security, []),
            $this->createMock(RunnerService::class),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.net_lichnogo_tokena');

        $useCase->handle(new VklyuchitRedaktirovanieUnita(self::UNIT_ID));
    }

    #[Test]
    public function postoronnii_ne_vklyuchaet_redaktirovanie(): void
    {
        $unit = $this->unit();
        $security = new FakeUnitmanSecurityService('stranger-1', false);

        $useCase = new VklyuchitRedaktirovanieUnitaUseCase(
            $this->units($unit),
            $this->projects(),
            $this->pravila($security, ['stranger-1' => 'personal-token']),
            $this->createMock(RunnerService::class),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.ne_avtor');

        $useCase->handle(new VklyuchitRedaktirovanieUnita(self::UNIT_ID));
    }

    #[Test]
    public function admin_s_tokenom_vklyuchaet_redaktirovanie_chuzhogo_unita(): void
    {
        $unit = $this->unit();
        $units = $this->units($unit);
        $security = new FakeUnitmanSecurityService('admin-1', true);

        $useCase = new VklyuchitRedaktirovanieUnitaUseCase(
            $units,
            $this->projects(),
            $this->pravila($security, ['admin-1' => 'personal-token']),
            $this->createMock(RunnerService::class),
        );

        $useCase->handle(new VklyuchitRedaktirovanieUnita(self::UNIT_ID));

        self::assertTrue($units->getById(self::UNIT_ID)->getRedaktirovanieVklucheno());
    }

    #[Test]
    public function povtornoe_vklyuchenie_idempotentno(): void
    {
        $unit = $this->unit();
        $unit->vklyuchitRedaktirovanie($this->project());
        $units = $this->units($unit);
        $security = new FakeUnitmanSecurityService(self::AUTHOR_ID, false);

        $useCase = new VklyuchitRedaktirovanieUnitaUseCase(
            $units,
            $this->projects(),
            $this->pravila($security, []),
            $this->createMock(RunnerService::class),
        );

        // Токен уже не нужен: повторное включение — no-op (15.10-а).
        $useCase->handle(new VklyuchitRedaktirovanieUnita(self::UNIT_ID));

        self::assertTrue($units->getById(self::UNIT_ID)->getRedaktirovanieVklucheno());
    }

    #[Test]
    public function avtor_prikrepliaet_redaktora_s_dostupom_i_tokenom(): void
    {
        $unit = $this->unit();
        $units = $this->units($unit);
        $security = new FakeUnitmanSecurityService(self::AUTHOR_ID, false);

        $useCase = new PrikrepitRedaktoraKUnituUseCase(
            $units,
            $this->projects(),
            $this->pravila($security, ['editor-1' => 'personal-token'], ['editor-1']),
            $security,
        );

        $useCase->handle(new PrikrepitRedaktoraKUnitu(self::UNIT_ID, 'editor-1'));

        self::assertSame(['editor-1'], $units->getById(self::UNIT_ID)->getRedaktory());
    }

    #[Test]
    public function prikreplenie_bez_dostupa_k_proektu_otklonyaetsya(): void
    {
        $unit = $this->unit();
        $security = new FakeUnitmanSecurityService(self::AUTHOR_ID, false);

        $useCase = new PrikrepitRedaktoraKUnituUseCase(
            $this->units($unit),
            $this->projects(),
            $this->pravila($security, ['editor-1' => 'personal-token'], []),
            $security,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.net_dostupa_k_proektu');

        $useCase->handle(new PrikrepitRedaktoraKUnitu(self::UNIT_ID, 'editor-1'));
    }

    #[Test]
    public function prikreplenie_polzovatelya_bez_tokena_otklonyaetsya(): void
    {
        $unit = $this->unit();
        $security = new FakeUnitmanSecurityService(self::AUTHOR_ID, false);

        $useCase = new PrikrepitRedaktoraKUnituUseCase(
            $this->units($unit),
            $this->projects(),
            $this->pravila($security, [], ['editor-1']),
            $security,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.redaktor_bez_tokena');

        $useCase->handle(new PrikrepitRedaktoraKUnitu(self::UNIT_ID, 'editor-1'));
    }

    /**
     * @param array<string, string> $tokens
     * @param string[] $projectsWithAccess
     */
    private function pravila(
        FakeUnitmanSecurityService $security,
        array $tokens,
        array $projectsWithAccess = ['author-1', 'admin-1', 'editor-1'],
    ): PravilaRedaktora {
        $tokenResolver = $this->createMock(UmeetPoluchatTokenRedaktirovaniya::class);
        $tokenResolver->method('poluchitToken')->willReturnCallback(
            static function (string $unitId, string $accountId) use ($tokens): string {
                if (!isset($tokens[$accountId])) {
                    throw new \DomainException('unit.redaktor.net_lichnogo_tokena');
                }

                return $tokens[$accountId];
            },
        );

        $repoRepository = $this->createMock(RepoRepository::class);
        $repoRepository->method('getById')->willReturn($this->repo());

        $pushCheck = $this->createMock(UmeetProveryatPushTokenom::class);
        $pushCheck->method('canPushTokenom')->willReturn(true);

        $projects = $this->createMock(UmeetPoluchatSpisokProektovDlyPolzovatelya::class);
        $projects->method('poluchitSpisokProektovDlyPolzovatelya')->willReturnCallback(
            static function (string $userId) use ($projectsWithAccess): array {
                return in_array($userId, $projectsWithAccess, true)
                    ? [['id' => self::PROJECT_ID]]
                    : [];
            },
        );

        return new PravilaRedaktora($security, $repoRepository, $tokenResolver, $pushCheck, $projects);
    }

    private function repo(): Repo
    {
        return Repo::addRepo(
            'repo-1',
            new AddRepo('GITLAB', 'group/repo', 'admin-token', 'https://git.local'),
            'https://git.local/group/repo.git',
        );
    }

    private function unit(): Unit
    {
        $unit = Unit::sozdatUnit(
            self::UNIT_ID,
            self::AUTHOR_ID,
            'prozes-1',
            new SozdatUnit(self::PROJECT_ID, 'unit-name', 'feature/1', [], 3000),
        );
        $unit->releaseEvents();

        return $unit;
    }

    private function units(Unit $unit): InMemoryUnitRepository
    {
        $repository = new InMemoryUnitRepository();
        $repository->save($unit);

        return $repository;
    }

    private function project(): Project
    {
        return Project::addProject(
            self::PROJECT_ID,
            new AddProject('repo-1', 'group/project', 'Project', 'main',
                300,
                [],
            ),
            self::AUTHOR_ID,
        );
    }

    private function projects(): ProjectRepository
    {
        $repository = new InMemoryProjectRepository();
        $repository->projects[self::PROJECT_ID] = $this->project();

        return $repository;
    }
}
