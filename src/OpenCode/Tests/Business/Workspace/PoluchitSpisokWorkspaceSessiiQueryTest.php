<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Workspace;

use App\OpenCode\Business\Command\PoluchitSpisokWorkspace;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\UseCase\PoluchitSpisokWorkspaceSessiiQuery;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Список на новом агрегате: участие, роли, pending, «общий» и ссылка IDE.
 */
final class PoluchitSpisokWorkspaceSessiiQueryTest extends TestCase
{
    #[Test]
    public function uchastnik_vidit_chuzhoy_workspace_s_rolyu_i_priznakom_obshchego(): void
    {
        $workspace = FabrikaWorkspace::workspaceSession();

        $workspaces = $this->createMock(WorkspaceSessionRepository::class);
        $workspaces->method('findByAccountIdWithParticipation')->willReturn([$workspace]);
        $workspaces->method('findPendingWorkspaceIds')->willReturn(['workspace-1' => true]);

        $participants = $this->createMock(WorkspaceParticipantsRepository::class);
        $participants->method('findByWorkspaceIds')->willReturn([
            'workspace-1' => [
                UchastnikSessii::create(
                    'workspace-1',
                    'account-2',
                    RolUchastnikaSessii::VIEWER,
                    null,
                    null,
                    null,
                    FabrikaWorkspace::NOW,
                ),
            ],
        ]);

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-2');

        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->method('poluchitSsylkuRedaktora')->willReturn('https://ide/workspace-1');

        $fayly = $this->createMock(UmeetUpravlyatFaylamiWorkspace::class);
        $fayly->method('poluchitFaylyPoImId')->willReturn(['workspace-1' => []]);

        $query = new PoluchitSpisokWorkspaceSessiiQuery(
            $workspaces,
            $participants,
            $credentials,
            $projectami,
            $fayly,
        );

        $result = $query->handle(new PoluchitSpisokWorkspace(false, null));

        self::assertCount(1, $result);
        $card = $result[0];
        self::assertSame('workspace-1', $card->id);
        self::assertSame('viewer', $card->myRole);
        self::assertSame('account-1', $card->ownerAccountId);
        self::assertSame(2, $card->participantsCount);
        self::assertTrue($card->shared);
        self::assertTrue($card->updating);
        self::assertSame('https://ide/workspace-1', $card->ideLink);
    }

    #[Test]
    public function vladelec_bez_stroki_uchastnika_ostayotsya_owner(): void
    {
        $workspace = FabrikaWorkspace::workspaceSession();

        $workspaces = $this->createMock(WorkspaceSessionRepository::class);
        $workspaces->method('findByAccountIdWithParticipation')->willReturn([$workspace]);
        $workspaces->method('findPendingWorkspaceIds')->willReturn([]);

        $participants = $this->createMock(WorkspaceParticipantsRepository::class);
        $participants->method('findByWorkspaceIds')->willReturn([]);

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');

        $query = new PoluchitSpisokWorkspaceSessiiQuery(
            $workspaces,
            $participants,
            $credentials,
            $this->createMock(UmeetUpravlyatProektamiWorkspace::class),
        );

        $card = $query->handle(new PoluchitSpisokWorkspace())[0];

        self::assertSame('owner', $card->myRole);
        self::assertSame(1, $card->participantsCount);
        self::assertFalse($card->shared);
        self::assertFalse($card->updating);
    }
}
