<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\SozdatUnitiDlyaProektov;
use App\Unitman\Business\Command\Unit\SozdatUnitiDlyaProektov\ProektDlyaSozdaniyaUnita;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\CanParseYaml;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatKonfigProekta;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\UmeetSozdavatUnit;
use App\Unitman\Business\UseCase\Unit\SozdatUnitiDlyaProektovUseCase;
use App\Unitman\Tests\Support\SequenceGuidGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SozdatUnitiDlyaProektovUseCaseTest extends TestCase
{
    private const CONFIG_API = <<<'YAML'
variables:
  - id: ORDER_SERVICE
    label: Orders
    type: string
    helper:
      projectId: services/web
      service: web
      port: 8080
      type: http
      suffix: /api
    defaultValue: master
services:
  web:
    ports:
      - port: 80
        type: http
prepare:
  - echo prepare
reset_prepare:
  - echo reset
up:
  - echo up
down:
  - echo down
YAML;

    private const CONFIG_WEB = <<<'YAML'
variables:
  - id: API_SERVICE
    label: API
    type: string
    helper:
      projectId: services/api
      service: web
      port: 80
      type: http
    defaultValue: master
services:
  web:
    ports:
      - port: 80
        type: http
prepare:
  - echo prepare
reset_prepare:
  - echo reset
up:
  - echo up
down:
  - echo down
YAML;

    #[Test]
    public function sozdaet_uniti_i_svyazi_po_kodu_proekta(): void
    {
        $commands = [];
        $useCase = $this->makeUseCase($commands);

        $result = $useCase->handleDlyaPolzovatelya(
            new SozdatUnitiDlyaProektov('payments-refactor', [
                new ProektDlyaSozdaniyaUnita('project-1', 'feature/payments'),
                new ProektDlyaSozdaniyaUnita('project-2', 'feature/payments'),
            ]),
            'account-1',
        );

        self::assertCount(2, $result->units);
        self::assertCount(2, $commands);
        self::assertSame('unit-1', $result->units[0]->unitId);
        self::assertSame('unit-3', $result->units[1]->unitId);

        $apiValues = $commands[0]->znacheniePeremenoi;
        self::assertCount(1, $apiValues);
        self::assertSame('ORDER_SERVICE', $apiValues[0]->id);
        self::assertSame('unit-3', $apiValues[0]->targetUnitId);
        self::assertSame('https://8080.payments-refactor.web.proxy.example/api', $apiValues[0]->value);

        $webValues = $commands[1]->znacheniePeremenoi;
        self::assertCount(1, $webValues);
        self::assertSame('unit-1', $webValues[0]->targetUnitId);
        self::assertSame('https://80.payments-refactor.api.proxy.example', $webValues[0]->value);
    }

    #[Test]
    public function koliziya_imeni_ostanavlivaet_vsyu_operatsiyu(): void
    {
        $commands = [];
        $useCase = $this->makeUseCase($commands, duplicateProjectId: 'project-2');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.name_already_exists');

        try {
            $useCase->handleDlyaPolzovatelya(
                new SozdatUnitiDlyaProektov('payments-refactor', [
                    new ProektDlyaSozdaniyaUnita('project-1', 'feature/payments'),
                    new ProektDlyaSozdaniyaUnita('project-2', 'feature/payments'),
                ]),
                'account-1',
            );
        } finally {
            self::assertSame([], $commands);
        }
    }

    /**
     * @param SozdatUnit[] $commands
     */
    private function makeUseCase(array &$commands, ?string $duplicateProjectId = null): SozdatUnitiDlyaProektovUseCase
    {
        $projects = [
            'project-1' => $this->project('project-1', 'services/api', 'api'),
            'project-2' => $this->project('project-2', 'services/web', 'web'),
        ];

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->willReturnCallback(
            static fn(string $id): Project => $projects[$id],
        );

        $repoRepository = $this->createMock(RepoRepository::class);
        $repoRepository->method('getById')->willReturnCallback(
            static fn(string $id): Repo => Repo::addRepo($id, new AddRepo('GITLAB', 'repo', 'token', null), 'https://gitlab.example'),
        );

        $spisok = $this->createMock(UmeetPoluchatSpisokProektovDlyPolzovatelya::class);
        $spisok->method('poluchitSpisokProektovDlyPolzovatelya')->willReturn([
            ['id' => 'project-1'],
            ['id' => 'project-2'],
        ]);

        $konfig = $this->createMock(UmeetPoluchatKonfigProekta::class);
        $konfig->method('poluchitKonfigIzHranilisha')->willReturnCallback(
            static fn(Repo $repo, string $code): string => $code === 'services/api' ? self::CONFIG_API : self::CONFIG_WEB,
        );

        $yaml = $this->createMock(CanParseYaml::class);
        $yaml->method('parse')->willReturnCallback(
            static fn(?string $content): array => Yaml::parse((string) $content),
        );

        $double = $this->createMock(CanFindUnitDouble::class);
        $double->method('isExistsDoubleByName')->willReturnCallback(
            static fn(string $projectId): bool => $projectId === $duplicateProjectId,
        );

        $sozdatUnit = $this->createMock(UmeetSozdavatUnit::class);
        $sozdatUnit->method('sozdatDlyaPolzovatelya')->willReturnCallback(
            static function (SozdatUnit $command) use (&$commands): string {
                $commands[] = $command;

                return $command->unitName;
            },
        );

        /** @var CanGenerateGuid $generator */
        $generator = new SequenceGuidGenerator('unit-');

        return new SozdatUnitiDlyaProektovUseCase(
            $projectRepository,
            $repoRepository,
            $spisok,
            $konfig,
            $yaml,
            $double,
            $generator,
            $sozdatUnit,
        );
    }

    private function project(string $id, string $code, string $name): Project
    {
        $project = Project::addProject(
            $id,
            new AddProject('repo-1', $code, $name, 'master', 'https://proxy.example', 1024),
            'account-1',
        );

        // В реальном сценарии проект уже собран и включён; для юнит-теста
        // достаточно выставить флаг напрямую.
        $property = new \ReflectionProperty(Project::class, 'isActive');
        $property->setAccessible(true);
        $property->setValue($project, true);

        return $project;
    }
}
