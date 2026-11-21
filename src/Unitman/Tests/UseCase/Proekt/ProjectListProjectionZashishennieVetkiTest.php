<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Model\Project\Event\ProjectWasAdded;
use App\Unitman\Business\Model\Project\Event\ZashishennieVetkiProektaUstanovleni;
use App\Unitman\Infra\Projection\ProjectListProjection;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class ProjectListProjectionZashishennieVetkiTest extends AbstractProjectUseCase
{
    #[Test]
    public function stariy_event_bez_zashishennih_vetok_daet_pustoy_spisok(): void
    {
        $projectId = Uuid::uuid7()->toString();
        $repoId = Uuid::uuid7()->toString();

        $projection = self::$container->get(ProjectListProjection::class);
        \assert($projection instanceof ProjectListProjection);

        // Событие «старого формата»: zashishennieVetki не передаётся,
        // используется значение по умолчанию (пустой список).
        $projection->handleProjectWasAdded(new ProjectWasAdded(
            $projectId,
            $repoId,
            'unitman-tests/' . $projectId,
            'Project-' . $projectId,
            'main',
        ));

        $repository = self::$container->get(SqlProjectListRepository::class);
        \assert($repository instanceof SqlProjectListRepository);

        self::assertSame([], $repository->getById($projectId)->zashishennieVetki);
    }

    #[Test]
    public function proekciya_primenyaet_sobytie_ustanovki_vetok(): void
    {
        $projectId = Uuid::uuid7()->toString();
        $repoId = Uuid::uuid7()->toString();

        $projection = self::$container->get(ProjectListProjection::class);
        \assert($projection instanceof ProjectListProjection);

        $projection->handleProjectWasAdded(new ProjectWasAdded(
            $projectId,
            $repoId,
            'unitman-tests/' . $projectId,
            'Project-' . $projectId,
            'main',
        ));

        $projection->handleZashishennieVetkiProektaUstanovleni(
            new ZashishennieVetkiProektaUstanovleni($projectId, ['main', 'release/*'])
        );

        $repository = self::$container->get(SqlProjectListRepository::class);
        \assert($repository instanceof SqlProjectListRepository);

        self::assertSame(['main', 'release/*'], $repository->getById($projectId)->zashishennieVetki);
    }
}
