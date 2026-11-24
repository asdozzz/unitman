<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;
use App\Unitman\Business\UseCase\Unit\ProverkaFaylovVolumov;
use App\Unitman\Business\UseCase\Unit\ProverkaSvyazeyPeremenih;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Tests\Support\FakeUnitmanSecurityService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ответ создания юнита несёт информационный признак защищённой ветки (7.3).
 */
final class SozdatUnitVetkaZashishenaTest extends TestCase
{
    private const PROJECT_ID = 'project-1';

    #[Test]
    public function sozdanie_na_zashishennoi_vetke_vozvrashaet_true(): void
    {
        $result = $this->useCase(['release/*'])->handleSVetkoy(
            new SozdatUnit(self::PROJECT_ID, 'unit-name', 'release/1.2', [], 3000),
        );

        self::assertSame('unit-1', $result['id']);
        self::assertTrue($result['vetkaZashishena']);
    }

    #[Test]
    public function sozdanie_na_obychnoi_vetke_vozvrashaet_false(): void
    {
        $result = $this->useCase(['release/*'])->handleSVetkoy(
            new SozdatUnit(self::PROJECT_ID, 'unit-name', 'feature/1', [], 3000),
        );

        self::assertSame('unit-1', $result['id']);
        self::assertFalse($result['vetkaZashishena']);
    }

    /**
     * @param string[] $zashishennieVetki
     */
    private function useCase(array $zashishennieVetki): SozdatUnitUseCase
    {
        $project = Project::addProject(
            self::PROJECT_ID,
            new AddProject('repo-1', 'group/project', 'Project', 'main', 300, $zashishennieVetki),
            'author-1',
        );
        (new \ReflectionProperty(Project::class, 'isActive'))->setValue($project, true);

        $canFindUnitDouble = $this->createMock(CanFindUnitDouble::class);
        $canFindUnitDouble->method('isExistsDoubleByName')->willReturn(false);

        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('getById')->willReturn($project);

        $guid = $this->createMock(CanGenerateGuid::class);
        $guid->method('makeGuid')->willReturnOnConsecutiveCalls('unit-1', 'prozes-1');

        $units = $this->createMock(UnitRepository::class);
        $units->method('save')->willReturnCallback(
            static function (Unit $unit): void {
                // Юнит проходит доменную валидацию создания.
            },
        );

        $volumes = new ProverkaFaylovVolumov(
            $this->createMock(MediatekaService::class),
            $this->createMock(VolumeTypeFileRepository::class),
        );

        $svyazi = new ProverkaSvyazeyPeremenih(
            $this->createMock(CanGetUnitReadModelById::class),
        );

        $userProjects = $this->createMock(UmeetPoluchatSpisokProektovDlyPolzovatelya::class);
        $userProjects->method('poluchitSpisokProektovDlyPolzovatelya')->willReturn([['id' => self::PROJECT_ID]]);

        return new SozdatUnitUseCase(
            $canFindUnitDouble,
            $projects,
            new FakeUnitmanSecurityService('author-1', false),
            $guid,
            $units,
            $this->createMock(MediatekaService::class),
            $volumes,
            $svyazi,
            $userProjects,
        );
    }
}
