<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Workspace;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\IstochnikRekvizitovOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Utils\OpredelitelModeliHodaOpenCode;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ZapuskatelHodaWorkspace;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ZapuskatelHodaWorkspaceTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function zanyataya_sessiya_daet_busy_i_ne_zapuskaet_runner(): void
    {
        $turns = $this->createMock(WorkspaceTurnRepository::class);
        $turns->method('sleduyushiiSeq')->willReturn(1);
        $turns->method('findBySessionId')->willReturn([]);
        $turns->expects($this->once())
            ->method('zanyatSessiyu')
            ->willThrowException(new \DomainException('opencode.session.busy'));
        $turns->expects($this->never())->method('save');

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->never())->method('nachatHod');

        $launcher = $this->launcher($turns, $runner);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.busy');

        $launcher->zapustit($this->workspace(), 'Привет', [], null, $this->istochnik());
    }

    #[Test]
    public function otzyv_dostupa_k_proektu_blokiruet_hod_do_rezervacii(): void
    {
        $turns = $this->createMock(WorkspaceTurnRepository::class);
        $turns->expects($this->never())->method('zanyatSessiyu');
        $turns->expects($this->never())->method('save');

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->never())->method('nachatHod');

        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->method('estDostupKProektam')->willReturn(false);

        $launcher = $this->launcher($turns, $runner, $projectami);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.project_access_revoked');

        $launcher->zapustit($this->workspace(), 'Привет', [], null, $this->istochnik());
    }

    #[Test]
    public function hod_zapuskaetsya_i_avtor_s_reykvizitami_sokhranyaetsya(): void
    {
        $turns = $this->createMock(WorkspaceTurnRepository::class);
        $turns->method('sleduyushiiSeq')->willReturn(7);
        $turns->method('findBySessionId')->willReturn([]);
        $turns->expects($this->once())->method('zanyatSessiyu');

        $saved = null;
        $turns->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (HodOpenCode $turn) use (&$saved): void {
                $saved = $turn;
            });

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->expects($this->once())
            ->method('nachatHod')
            ->with(
                $this->anything(),
                'acc-2',
                'cred-writer',
                null,
                [],
                'Привет',
                [],
                $this->anything(),
                $this->anything(),
                false,
                null,
            )
            ->willReturn('workflow-1');
        $runner->method('kanalProgressa')->willReturn('progress-1');

        $workspaces = $this->createMock(WorkspaceSessionRepository::class);
        $workspaces->expects($this->once())->method('update');

        $launcher = $this->launcher($turns, $runner, null, $workspaces);
        $launcher->zapustit($this->workspace(), 'Привет', [], null, $this->istochnik());

        self::assertInstanceOf(HodOpenCode::class, $saved);
        self::assertSame('acc-2', $saved->getAuthorAccountId());
        self::assertSame('cred-writer', $saved->getCredentialId());
    }

    private function launcher(
        WorkspaceTurnRepository $turns,
        UmeetVypolnyatHodOpenCode $runner,
        ?UmeetUpravlyatProektamiWorkspace $projectami = null,
        ?WorkspaceSessionRepository $workspaces = null,
    ): ZapuskatelHodaWorkspace {
        $projectami ??= $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->method('estDostupKProektam')->willReturn(true);

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('razreshitModel')->willReturn('model-1');

        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturnOnConsecutiveCalls('turn-1', 'workflow-1');

        return new ZapuskatelHodaWorkspace(
            $turns,
            $workspaces ?? $this->createMock(WorkspaceSessionRepository::class),
            $runner,
            $uuid,
            new OpredelitelModeliHodaOpenCode($credentials),
            $projectami,
        );
    }

    private function istochnik(): IstochnikRekvizitovOpenCode
    {
        return new IstochnikRekvizitovOpenCode('acc-2', 'cred-writer', null);
    }

    private function workspace(): WorkspaceSession
    {
        $workspace = WorkspaceSession::create(
            'workspace-1',
            'owner-1',
            'cred-owner',
            'payments-refactor',
            'main',
            [FabrikaWorkspace::proekt()],
            'Первый промпт',
            self::NOW,
        );

        $workspace->getWorkspace()->perevestiV(StatusWorkspace::RUNNING, self::NOW);

        return $workspace;
    }
}
