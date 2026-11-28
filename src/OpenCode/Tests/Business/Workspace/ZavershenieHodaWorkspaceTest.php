<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Workspace;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UmeetPeredavatUvedomleniyaOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\ReadModel\UvedomlenieONovomSoobsheniiOpenCode;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceNotificationsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ZavershenieHodaWorkspace;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ZavershenieHodaWorkspaceTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function fan_out_isklyuchaet_avtora_i_vklyuchaet_uchastnikov(): void
    {
        $turn = $this->turn();
        $workspace = $this->workspace();

        $turns = $this->createMock(WorkspaceTurnRepository::class);
        $turns->expects($this->once())->method('update');
        $turns->expects($this->once())->method('osvoboditSessiyu');

        $workspaces = $this->createMock(WorkspaceSessionRepository::class);
        $workspaces->method('getById')->willReturn($workspace);

        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->method('poluchitResultatHoda')->willReturn(
            new RezultatHodaOpenCode(true, RezultatHodaOpenCode::KIND_ANSWER, [], 'Готово', null, null),
        );

        $participants = $this->createMock(WorkspaceParticipantsRepository::class);
        $participants->method('findByWorkspaceId')->willReturn([
            UchastnikSessii::create('workspace-1', 'author-1', RolUchastnikaSessii::WRITER, null, null, null, self::NOW),
            UchastnikSessii::create('workspace-1', 'viewer-2', RolUchastnikaSessii::VIEWER, null, null, null, self::NOW),
        ]);

        $poluchateli = [];
        $notifications = $this->createMock(WorkspaceNotificationsRepository::class);
        $notifications->method('save')->willReturnCallback(
            static function (UvedomlenieONovomSoobsheniiOpenCode $notification) use (&$poluchateli): bool {
                $poluchateli[] = $notification->accountId;

                return true;
            },
        );

        $publisher = $this->createMock(UmeetPeredavatUvedomleniyaOpenCode::class);
        $publisher->expects($this->exactly(2))->method('publish');

        $uuid = $this->createMock(UuidGenerator::class);
        $uuid->method('makeGuid')->willReturn('notification-1');

        $zavershenie = new ZavershenieHodaWorkspace(
            $turns,
            $workspaces,
            $runner,
            $notifications,
            $publisher,
            $uuid,
            $this->createMock(LoggerInterface::class),
            $participants,
        );

        $zavershenie->zavershit($turn);

        sort($poluchateli);
        self::assertSame(['owner-1', 'viewer-2'], $poluchateli);
        self::assertNotContains('author-1', $poluchateli);
    }

    private function turn(): HodOpenCode
    {
        return HodOpenCode::create(
            'turn-1',
            'workspace-1',
            1,
            'workflow-1',
            'Привет',
            [],
            self::NOW,
            authorAccountId: 'author-1',
            credentialId: 'cred-author',
        );
    }

    private function workspace(): WorkspaceSession
    {
        return WorkspaceSession::create(
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
}
