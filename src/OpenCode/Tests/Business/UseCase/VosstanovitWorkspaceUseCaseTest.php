<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\VosstanovitWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\UseCase\VosstanovitWorkspaceUseCase;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VosstanovitWorkspaceUseCaseTest extends TestCase
{
    #[Test]
    public function arhivnyi_workspace_perevoditsya_v_starting(): void
    {
        $archived = FabrikaWorkspace::vStatuse(StatusWorkspace::ARCHIVED);
        $starting = FabrikaWorkspace::vStatuse(StatusWorkspace::STARTING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturnOnConsecutiveCalls($archived, $starting);
        $repository->expects($this->once())
            ->method('perevestiStatusWorkspace')
            ->with('workspace-1', [StatusWorkspace::ARCHIVED, StatusWorkspace::ERROR], StatusWorkspace::STARTING)
            ->willReturn(true);
        $repository->expects($this->once())->method('update')->with($starting);

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->expects($this->once())->method('vosstanovit')->with($starting);

        $this->useCase($repository, $manager)->handle(new VosstanovitWorkspace('workspace-1'));
    }

    #[Test]
    public function rabotayushchii_workspace_nelzya_vosstanovit(): void
    {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn(FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING));
        $repository->expects($this->never())->method('perevestiStatusWorkspace');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->expects($this->never())->method('vosstanovit');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.not_running');

        $this->useCase($repository, $manager)->handle(new VosstanovitWorkspace('workspace-1'));
    }

    #[Test]
    public function povtornii_vyzov_vo_vremya_starting_nichego_ne_menyaet(): void
    {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn(FabrikaWorkspace::vStatuse(StatusWorkspace::STARTING));
        $repository->expects($this->never())->method('perevestiStatusWorkspace');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->expects($this->never())->method('vosstanovit');

        $this->useCase($repository, $manager)->handle(new VosstanovitWorkspace('workspace-1'));
    }

    private function useCase(
        SessiyaOpenCodeRepository $repository,
        UmeetUpravlyatWorkspace $manager,
    ): VosstanovitWorkspaceUseCase {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');

        return new VosstanovitWorkspaceUseCase($repository, $credentials, $manager);
    }
}
