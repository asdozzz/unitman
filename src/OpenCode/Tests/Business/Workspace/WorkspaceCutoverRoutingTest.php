<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Workspace;

use App\OpenCode\Business\Command\PoluchitIstoriyuSessii;
use App\OpenCode\Business\Command\PoluchitProgressHoda;
use App\OpenCode\Business\Command\PoluchitSostoyanieWorkspace;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\UseCase\PoluchitIstoriyuSessiiQuery;
use App\OpenCode\Business\UseCase\PoluchitProgressHodaQuery;
use App\OpenCode\Business\UseCase\PoluchitSostoyanieWorkspaceQuery;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\UseCase\PoluchitIstoriyuWorkspaceQuery;
use App\OpenCode\Business\Workspace\UseCase\PoluchitProgressHodaWorkspaceQuery;
use App\OpenCode\Business\Workspace\UseCase\PoluchitSostoyanieWorkspaceSessiiQuery;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Роутинг чтений по типу сессии и флагу: unit-сессии не затрагиваются ни при
 * каких флагах, workspace уходят в новый агрегат только при READ=1.
 */
final class WorkspaceCutoverRoutingTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function istoriya_workspace_pri_read_uhodit_v_novyi_agregat(): void
    {
        $query = $this->istoriyaQuery(
            new WorkspaceFeatureFlags(read: true, write: false, mirror: true),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('workspace-read-route');

        $query->handle(new PoluchitIstoriyuSessii('workspace-1'));
    }

    #[Test]
    public function istoriya_unit_ne_zadevaet_novyi_agregat_nizhnimi_flagami(): void
    {
        $query = $this->istoriyaQuery(
            new WorkspaceFeatureFlags(read: true, write: false, mirror: true),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.not_found');

        $query->handle(new PoluchitIstoriyuSessii('unit-1'));
    }

    #[Test]
    public function istoriya_pri_vyklyuchennom_read_idet_v_legacy(): void
    {
        $sessionRepository = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessionRepository->method('getById')->willReturn($this->workspaceSession());

        $turns = $this->createMock(HodOpenCodeRepository::class);
        $turns->method('findBySessionId')->willReturn([]);

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('owner-1');

        $query = new PoluchitIstoriyuSessiiQuery(
            $sessionRepository,
            $turns,
            $credentials,
            $this->createMock(UmeetVypolnyatHodOpenCode::class),
            $this->legacyDostup(),
            new WorkspaceFeatureFlags(),
            $this->throwingWorkspaceHistoryQuery(),
        );

        self::assertSame([], $query->handle(new PoluchitIstoriyuSessii('workspace-1')));
    }

    #[Test]
    public function progress_workspace_pri_read_uhodit_v_novyi_agregat(): void
    {
        $query = $this->progressQuery(
            new WorkspaceFeatureFlags(read: true, write: false, mirror: true),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('workspace-read-route');

        $query->handle(new PoluchitProgressHoda('turn-1'));
    }

    #[Test]
    public function progress_unit_ne_zadevaet_novyi_agregat(): void
    {
        $query = $this->progressQuery(
            new WorkspaceFeatureFlags(read: true, write: false, mirror: true),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.not_found');

        $query->handle(new PoluchitProgressHoda('unit-turn-1'));
    }

    #[Test]
    public function sostoyanie_pri_read_uhodit_v_novyi_agregat(): void
    {
        $query = $this->sostoyanieQuery(
            new WorkspaceFeatureFlags(read: true, write: false, mirror: true),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('workspace-read-route');

        $query->handle(new PoluchitSostoyanieWorkspace('workspace-1'));
    }

    private function istoriyaQuery(WorkspaceFeatureFlags $flags): PoluchitIstoriyuSessiiQuery
    {
        $sessions = $this->createMock(SessiyaOpenCodeRepository::class);
        $sessions->method('getById')->willReturnCallback(
            fn (string $id): SessiyaOpenCode => $id === 'workspace-1'
                ? $this->workspaceSession()
                : $this->unitSession(),
        );

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('acc-2');

        return new PoluchitIstoriyuSessiiQuery(
            $sessions,
            $this->createMock(HodOpenCodeRepository::class),
            $credentials,
            $this->createMock(UmeetVypolnyatHodOpenCode::class),
            $this->legacyDostup(),
            $flags,
            $this->throwingWorkspaceHistoryQuery(),
        );
    }

    private function progressQuery(WorkspaceFeatureFlags $flags): PoluchitProgressHodaQuery
    {
        $sessions = $this->createMock(SessiyaOpenCodeRepository::class);
        $legacyTurns = $this->createMock(HodOpenCodeRepository::class);
        $legacyTurns->method('findById')->willReturnCallback(
            static fn (string $id): ?\App\OpenCode\Business\Model\HodOpenCode => $id === 'unit-turn-1'
                ? self::unitTurn()
                : null,
        );
        $legacyTurns->method('getById')->willReturn(self::unitTurn());
        $sessions->method('findById')->willReturn($this->unitSession());
        $sessions->method('getById')->willReturn($this->unitSession());

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('acc-2');

        return new PoluchitProgressHodaQuery(
            $sessions,
            $legacyTurns,
            $credentials,
            $this->createMock(UmeetVypolnyatHodOpenCode::class),
            $this->legacyDostup(),
            $flags,
            $this->throwingWorkspaceProgressQuery(),
        );
    }

    private function sostoyanieQuery(WorkspaceFeatureFlags $flags): PoluchitSostoyanieWorkspaceQuery
    {
        return new PoluchitSostoyanieWorkspaceQuery(
            $this->createMock(SessiyaOpenCodeRepository::class),
            $this->createMock(HodOpenCodeRepository::class),
            $this->createMock(UmeetProveryatRekvizityOpenCode::class),
            $this->createMock(UmeetUpravlyatProektamiWorkspace::class),
            $this->legacyDostup(),
            $this->createMock(UchastnikiSessiiRepository::class),
            null,
            $flags,
            $this->throwingWorkspaceSostoyanieQuery(),
        );
    }

    private function throwingWorkspaceHistoryQuery(): PoluchitIstoriyuWorkspaceQuery
    {
        return new PoluchitIstoriyuWorkspaceQuery(
            $this->workspacesThrowing(),
            $this->createMock(WorkspaceTurnRepository::class),
            $this->createMock(UmeetProveryatRekvizityOpenCode::class),
            $this->createMock(UmeetVypolnyatHodOpenCode::class),
            $this->newDostup(),
        );
    }

    private function throwingWorkspaceProgressQuery(): PoluchitProgressHodaWorkspaceQuery
    {
        $turns = $this->createMock(WorkspaceTurnRepository::class);
        $turns->method('getById')->willReturn(self::workspaceTurn());

        return new PoluchitProgressHodaWorkspaceQuery(
            $this->workspacesThrowing(),
            $turns,
            $this->createMock(UmeetProveryatRekvizityOpenCode::class),
            $this->createMock(UmeetVypolnyatHodOpenCode::class),
            $this->newDostup(),
        );
    }

    private function throwingWorkspaceSostoyanieQuery(): PoluchitSostoyanieWorkspaceSessiiQuery
    {
        return new PoluchitSostoyanieWorkspaceSessiiQuery(
            $this->workspacesThrowing(),
            $this->createMock(WorkspaceTurnRepository::class),
            $this->createMock(UmeetProveryatRekvizityOpenCode::class),
            $this->newDostup(),
            $this->createMock(WorkspaceParticipantsRepository::class),
            $this->createMock(UmeetUpravlyatProektamiWorkspace::class),
            null,
        );
    }

    private function workspacesThrowing(): WorkspaceSessionRepository
    {
        $workspaces = $this->createMock(WorkspaceSessionRepository::class);
        $workspaces->method('getById')->willThrowException(new \RuntimeException('workspace-read-route'));

        return $workspaces;
    }

    private function legacyDostup(): ProverkaDostupaKSessii
    {
        $participants = $this->createMock(UchastnikiSessiiRepository::class);
        $participants->method('find')->willReturn(null);

        return new ProverkaDostupaKSessii($participants);
    }

    private function newDostup(): ProverkaDostupaWorkspaceSessii
    {
        $participants = $this->createMock(WorkspaceParticipantsRepository::class);
        $participants->method('find')->willReturn(null);

        return new ProverkaDostupaWorkspaceSessii($participants);
    }

    private function workspaceSession(): SessiyaOpenCode
    {
        return SessiyaOpenCode::createWorkspace(
            'workspace-1',
            'owner-1',
            'cred-owner',
            'payments-refactor',
            'main',
            [FabrikaWorkspace::proekt()],
            'Первый промпт',
            self::NOW,
        );
    }

    private function unitSession(): SessiyaOpenCode
    {
        return SessiyaOpenCode::create('unit-1', 'owner-1', 'cred-1', null, [], self::NOW);
    }

    private static function unitTurn(): \App\OpenCode\Business\Model\HodOpenCode
    {
        return \App\OpenCode\Business\Model\HodOpenCode::create(
            'unit-turn-1',
            'unit-1',
            1,
            'workflow-unit-1',
            'Привет',
            [],
            self::NOW,
        );
    }

    private static function workspaceTurn(): \App\OpenCode\Business\Model\HodOpenCode
    {
        return \App\OpenCode\Business\Model\HodOpenCode::create(
            'turn-1',
            'workspace-1',
            1,
            'workflow-turn-1',
            'Привет',
            [],
            self::NOW,
        );
    }
}
