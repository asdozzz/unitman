<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\ArhivirovatWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\ReadModel\RezultatProverkiIzmeneniyWorkspace;
use App\OpenCode\Business\UseCase\ArhivirovatWorkspaceUseCase;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArhivirovatWorkspaceUseCaseTest extends TestCase
{
    #[Test]
    public function chistyi_workspace_perevoditsya_v_archiving_i_ostanavlivaetsya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $fresh = FabrikaWorkspace::vStatuse(StatusWorkspace::ARCHIVING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturnOnConsecutiveCalls($session, $fresh);
        $repository->expects($this->once())
            ->method('perevestiStatusWorkspace')
            ->with('workspace-1', [StatusWorkspace::RUNNING, StatusWorkspace::ERROR], StatusWorkspace::ARCHIVING)
            ->willReturn(true);
        $repository->expects($this->once())->method('update')->with($fresh);

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitIzmeneniya')
            ->willReturn(new RezultatProverkiIzmeneniyWorkspace(true, []));
        $manager->expects($this->once())->method('ostanovit')->with($fresh);

        $result = $this->useCase($repository, $manager)->handle(new ArhivirovatWorkspace('workspace-1'));

        $this->assertFalse($result->requiresConfirmation);
        $this->assertSame([], $result->dirtyProjects);
        $this->assertSame(StatusWorkspace::ARCHIVING->value, $result->status);
    }

    #[Test]
    public function gryaznyi_workspace_bez_force_trebuet_podtverzhdeniya(): void
    {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn(FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING));
        $repository->expects($this->never())->method('perevestiStatusWorkspace');
        $repository->expects($this->never())->method('update');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('poluchitIzmeneniya')->willReturn(new RezultatProverkiIzmeneniyWorkspace(true, [
            ['projectId' => 'project-1', 'name' => 'api', 'dirty' => true, 'files' => [' M src/Foo.php']],
            ['projectId' => 'project-2', 'name' => 'web', 'dirty' => false, 'files' => []],
        ]));
        $manager->expects($this->never())->method('ostanovit');

        $result = $this->useCase($repository, $manager)->handle(new ArhivirovatWorkspace('workspace-1'));

        $this->assertTrue($result->requiresConfirmation);
        $this->assertSame([
            ['projectId' => 'project-1', 'name' => 'api', 'files' => [' M src/Foo.php']],
        ], $result->dirtyProjects);
        $this->assertSame(StatusWorkspace::RUNNING->value, $result->status);
    }

    #[Test]
    public function force_propuskaet_proverku_izmeneniy(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $fresh = FabrikaWorkspace::vStatuse(StatusWorkspace::ARCHIVING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturnOnConsecutiveCalls($session, $fresh);
        $repository->expects($this->once())->method('perevestiStatusWorkspace')->willReturn(true);
        $repository->expects($this->once())->method('update');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->expects($this->never())->method('poluchitIzmeneniya');
        $manager->expects($this->once())->method('ostanovit');

        $result = $this->useCase($repository, $manager)->handle(new ArhivirovatWorkspace('workspace-1', true));

        $this->assertFalse($result->requiresConfirmation);
        $this->assertSame(StatusWorkspace::ARCHIVING->value, $result->status);
    }

    #[Test]
    public function povtornaya_arhivaciya_idempotentna(): void
    {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn(FabrikaWorkspace::vStatuse(StatusWorkspace::ARCHIVED));
        $repository->expects($this->never())->method('perevestiStatusWorkspace');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->expects($this->never())->method('ostanovit');

        $result = $this->useCase($repository, $manager)->handle(new ArhivirovatWorkspace('workspace-1'));

        $this->assertFalse($result->requiresConfirmation);
        $this->assertSame(StatusWorkspace::ARCHIVED->value, $result->status);
    }

    private function useCase(
        SessiyaOpenCodeRepository $repository,
        UmeetUpravlyatWorkspace $manager,
    ): ArhivirovatWorkspaceUseCase {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');

        return new ArhivirovatWorkspaceUseCase($repository, $credentials, $manager);
    }

    private function session(): SessiyaOpenCode
    {
        return FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
    }
}
