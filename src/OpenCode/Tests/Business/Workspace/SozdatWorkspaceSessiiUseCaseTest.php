<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Workspace;

use App\OpenCode\Business\Command\SozdatWorkspace;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatWorkspace;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\UseCase\SozdatWorkspaceSessiiUseCase;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Создание workspace на новом агрегате: сессия сохраняется, владелец пишется
 * участником, стенд запускается через тот же порт раннера.
 */
final class SozdatWorkspaceSessiiUseCaseTest extends TestCase
{
    #[Test]
    public function sozdaet_sessiyu_vladelca_i_zapuskaet_stend(): void
    {
        $saved = null;
        $owner = null;
        $zapushchennaya = null;

        $workspaces = $this->createMock(WorkspaceSessionRepository::class);
        $workspaces->method('findByAccountId')->willReturn([]);
        $workspaces->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (WorkspaceSession $workspace) use (&$saved): void {
                $saved = $workspace;
            });
        $workspaces->expects($this->once())->method('update');

        $participants = $this->createMock(WorkspaceParticipantsRepository::class);
        $participants->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (UchastnikSessii $uchastnik) use (&$owner): void {
                $owner = $uchastnik;
            });

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');
        $credentials->method('razreshitModel')->willReturn('gpt');

        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->method('poluchitProekti')->willReturn([FabrikaWorkspace::proekt()]);

        $runner = $this->createMock(UmeetUpravlyatWorkspace::class);
        $runner->expects($this->once())
            ->method('zapustit')
            ->willReturnCallback(static function (WorkspaceSession $workspace) use (&$zapushchennaya): void {
                $zapushchennaya = $workspace;
            });

        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturn('workspace-1');

        $useCase = new SozdatWorkspaceSessiiUseCase(
            $workspaces,
            $participants,
            $credentials,
            $projectami,
            $runner,
            $uuid,
        );

        $id = $useCase->handle(new SozdatWorkspace(
            name: 'payments-refactor',
            branch: 'feature/payments',
            projectIds: ['project-1'],
            prompt: 'Первый промпт',
            credentialId: 'credential-1',
        ));

        self::assertSame('workspace-1', $id);
        self::assertInstanceOf(WorkspaceSession::class, $saved);
        self::assertSame('payments-refactor', $saved->getName());
        self::assertInstanceOf(WorkspaceSession::class, $zapushchennaya);
        self::assertInstanceOf(UchastnikSessii::class, $owner);
        self::assertSame('account-1', $owner->getAccountId());
        self::assertSame(RolUchastnikaSessii::OWNER, $owner->getRol());
    }
}
