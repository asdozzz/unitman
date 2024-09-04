<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\DobavitPeremenuyuVProekt;
use App\Unitman\Business\Command\Project\IzmenitZnacheniePeremenoiProekta;
use App\Unitman\Business\Command\Project\UdalitPeremenuyuIzProekta;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListVariable;
use App\Unitman\Business\UseCase\Project\ProzessDobavleniyaPeremenoiVProekt;
use App\Unitman\Business\UseCase\Project\ProzessIzmeneniyaZnacheniyaPeremenoiProekta;
use App\Unitman\Business\UseCase\Project\ProzessUdaleniyaPeremenoiIzProekta;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use Ramsey\Uuid\Uuid;

final class ProzessDobavleniyaPeremenoiVProektTest extends AbstractProjectUseCase
{
    /**
     * @test
     * */
    function test()
    {

        $repoId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su', $adminId);

        $command = new DobavitPeremenuyuVProekt($projectId, 'hidden', 'secretKey', '1231231');
        $useCase = self::$container->get(ProzessDobavleniyaPeremenoiVProekt::class);
        /** @var $useCase ProzessDobavleniyaPeremenoiVProekt*/
        $useCase->handle($command);

        try {
            $command = new DobavitPeremenuyuVProekt($projectId, 'hidden', 'secretKey', '1231231');
            $useCase = self::$container->get(ProzessDobavleniyaPeremenoiVProekt::class);
            /** @var $useCase ProzessDobavleniyaPeremenoiVProekt*/
            $useCase->handle($command);
            $this->assertTrue(false, 'exception not found');
        } catch (\Exception $e) {
            $this->assertEquals('project.variable.duplicate', $e->getMessage());
        }

        try {
            $command = new DobavitPeremenuyuVProekt($projectId, 'default', 'someVariable', '');
            $useCase = self::$container->get(ProzessDobavleniyaPeremenoiVProekt::class);
            /** @var $useCase ProzessDobavleniyaPeremenoiVProekt*/
            $useCase->handle($command);
            $this->assertTrue(false, 'exception not found');
        } catch (\Exception $e) {
            $this->assertEquals('project.variable.value_is_empty', $e->getMessage());
        }


        $command = new DobavitPeremenuyuVProekt($projectId, 'default', 'someVariable', '444');
        $useCase = self::$container->get(ProzessDobavleniyaPeremenoiVProekt::class);
        /** @var $useCase ProzessDobavleniyaPeremenoiVProekt*/
        $useCase->handle($command);

        $projectRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectRepo SqlProjectListRepository*/
        $project = $projectRepo->getById($projectId);

        $this->assertCount(2, $project->variables);
        $this->assertEquals(new ProjectListVariable('hidden', 'secretKey', ''), $project->variables[0]);
        $this->assertEquals(new ProjectListVariable('default', 'someVariable', '444'), $project->variables[1]);

        try {
            $command = new IzmenitZnacheniePeremenoiProekta($projectId, 'someVariable', '444');
            $useCase = self::$container->get(ProzessIzmeneniyaZnacheniyaPeremenoiProekta::class);
            /** @var $useCase ProzessIzmeneniyaZnacheniyaPeremenoiProekta*/
            $useCase->handle($command);

            $this->assertTrue(false, 'exception not found');
        } catch (\Exception $e) {
            $this->assertEquals('project.variable.new_value_equal_old_value', $e->getMessage());
        }

        $command = new IzmenitZnacheniePeremenoiProekta($projectId, 'someVariable', '555');
        $useCase = self::$container->get(ProzessIzmeneniyaZnacheniyaPeremenoiProekta::class);
        /** @var $useCase ProzessIzmeneniyaZnacheniyaPeremenoiProekta*/
        $useCase->handle($command);

        $projectRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectRepo SqlProjectListRepository*/
        $project = $projectRepo->getById($projectId);

        $this->assertEquals(new ProjectListVariable('default', 'someVariable', '555'), $project->variables[1]);

        $command = new UdalitPeremenuyuIzProekta($projectId, 'secretKey');
        $useCase = self::$container->get(ProzessUdaleniyaPeremenoiIzProekta::class);
        /** @var $useCase ProzessUdaleniyaPeremenoiIzProekta*/
        $useCase->handle($command);

        $projectRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectRepo SqlProjectListRepository*/
        $project = $projectRepo->getById($projectId);

        $this->assertCount(1, $project->variables);
        $this->assertEquals(new ProjectListVariable('default', 'someVariable', '555'), $project->variables[0]);
    }
}
