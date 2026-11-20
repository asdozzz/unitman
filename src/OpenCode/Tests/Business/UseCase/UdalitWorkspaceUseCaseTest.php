<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\UdalitWorkspace;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\UseCase\UdalitWorkspaceUseCase;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UdalitWorkspaceUseCaseTest extends TestCase
{
    #[Test]
    public function rabotayushchii_workspace_perevoditsya_v_deleting_i_udalyaetsya_v_runner(): void
    {
        $running = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $deleting = FabrikaWorkspace::vStatuse(StatusWorkspace::DELETING);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturnOnConsecutiveCalls($running, $deleting);
        $repository->expects($this->once())
            ->method('perevestiStatusWorkspace')
            ->with(
                'workspace-1',
                [StatusWorkspace::RUNNING, StatusWorkspace::ARCHIVED, StatusWorkspace::ERROR],
                StatusWorkspace::DELETING,
            )
            ->willReturn(true);
        $repository->expects($this->once())->method('update')->with($deleting);

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->expects($this->once())->method('udalit')->with($deleting);

        $this->useCase($repository, $manager)->handle(new UdalitWorkspace('workspace-1'));
    }

    #[Test]
    public function povtornii_vyzov_pri_deleting_nichego_ne_zapuskaet(): void
    {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn(FabrikaWorkspace::vStatuse(StatusWorkspace::DELETING));
        $repository->expects($this->never())->method('perevestiStatusWorkspace');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->expects($this->never())->method('udalit');

        $this->useCase($repository, $manager)->handle(new UdalitWorkspace('workspace-1'));
    }

    #[Test]
    public function oshibka_starta_udaleniya_perevodit_workspace_v_error(): void
    {
        $running = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $deleting = FabrikaWorkspace::vStatuse(StatusWorkspace::DELETING);
        $error = FabrikaWorkspace::vStatuse(StatusWorkspace::ERROR);

        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturnOnConsecutiveCalls($running, $deleting, $error);
        $repository->expects($this->exactly(2))
            ->method('perevestiStatusWorkspace')
            ->willReturnCallback(
                static function (string $id, array $from, StatusWorkspace $to): bool {
                    self::assertSame('workspace-1', $id);

                    if ($to === StatusWorkspace::DELETING) {
                        return true;
                    }

                    self::assertSame([StatusWorkspace::DELETING], $from);
                    self::assertSame(StatusWorkspace::ERROR, $to);

                    return true;
                }
            );
        $repository->expects($this->once())->method('update');

        $manager = $this->createMock(UmeetUpravlyatWorkspace::class);
        $manager->method('udalit')->willThrowException(new \RuntimeException('runner unavailable'));

        $this->expectException(\RuntimeException::class);

        try {
            $this->useCase($repository, $manager)->handle(new UdalitWorkspace('workspace-1'));
        } finally {
            $this->assertSame(
                'opencode.workspace.operation_failed',
                $error->getWorkspace()?->getErrorKey(),
            );
        }
    }

    private function useCase(
        SessiyaOpenCodeRepository $repository,
        UmeetUpravlyatWorkspace $manager,
    ): UdalitWorkspaceUseCase {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');

        return new UdalitWorkspaceUseCase($repository, $credentials, $manager);
    }
}
