<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Infra\BackgroundJob;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetPeredavatUvedomleniyaOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Port\UvedomleniyaOpenCodeRepository;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\Utils\ZavershenieHodaOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceNotificationsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ZavershenieHodaWorkspace;
use App\OpenCode\Infra\BackgroundJob\ZavershenieHodovOpenCode\ZavershenieHodovOpenCodeBackgroundJob;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Watchdog видит RUNNING-ходы из двух репозиториев и не завершает один и тот
 * же ход дважды (дедупликация по id).
 */
final class ZavershenieHodovOpenCodeBackgroundJobWorkspaceTest extends TestCase
{
    #[Test]
    public function zavershaet_hod_iz_novogo_agregata_i_ne_duobliruet_legasi(): void
    {
        $legacyTurn = $this->turn('turn-legacy', 'workspace-1', 'wf-legacy');
        $duplicate = $this->turn('turn-legacy', 'workspace-1', 'wf-legacy');
        $novyi = $this->turn('turn-new', 'workspace-1', 'wf-new');

        $legacyRepo = $this->createMock(HodOpenCodeRepository::class);
        $legacyRepo->method('findVProzesse')->willReturn([$legacyTurn]);

        // Легаси-завершение возвращает null (ход ещё идёт) и не пишет.
        $legacyZavershenie = new ZavershenieHodaOpenCode(
            $legacyRepo,
            $this->createMock(\App\OpenCode\Business\Port\SessiyaOpenCodeRepository::class),
            $this->runner(null),
            $this->createMock(UmeetBronirovatUniti::class),
            $this->createMock(UvedomleniyaOpenCodeRepository::class),
            $this->createMock(UmeetPeredavatUvedomleniyaOpenCode::class),
            $this->createMock(UuidGenerator::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(UchastnikiSessiiRepository::class),
        );

        $updates = [];

        $workspaceTurns = $this->createMock(WorkspaceTurnRepository::class);
        $workspaceTurns->method('findVProzesse')->willReturn([$duplicate, $novyi]);
        $workspaceTurns->method('update')->willReturnCallback(
            static function (HodOpenCode $turn) use (&$updates): void {
                $updates[] = $turn->getId();
            }
        );

        $workspaces = $this->createMock(WorkspaceSessionRepository::class);
        $workspaces->method('getById')->willReturn(FabrikaWorkspace::workspaceSession());

        $participants = $this->createMock(WorkspaceParticipantsRepository::class);
        $participants->method('findByWorkspaceId')->willReturn([]);

        $workspaceZavershenie = new ZavershenieHodaWorkspace(
            $workspaceTurns,
            $workspaces,
            $this->runner(new RezultatHodaOpenCode(true, RezultatHodaOpenCode::KIND_ANSWER, [], 'ok', null, null)),
            $this->createMock(WorkspaceNotificationsRepository::class),
            $this->createMock(UmeetPeredavatUvedomleniyaOpenCode::class),
            $this->createMock(UuidGenerator::class),
            $this->createMock(LoggerInterface::class),
            $participants,
        );

        $job = new ZavershenieHodovOpenCodeBackgroundJob(
            $legacyRepo,
            $legacyZavershenie,
            $this->createMock(LoggerInterface::class),
            $workspaceTurns,
            $workspaceZavershenie,
        );

        self::assertTrue($job->run());
        self::assertSame(['turn-new'], $updates);
    }

    private function turn(string $id, string $workspaceId, string $workflowId): HodOpenCode
    {
        return HodOpenCode::create(
            $id,
            $workspaceId,
            1,
            $workflowId,
            'Промпт',
            [],
            FabrikaWorkspace::NOW,
        );
    }

    private function runner(?RezultatHodaOpenCode $result): UmeetVypolnyatHodOpenCode
    {
        $runner = $this->createMock(UmeetVypolnyatHodOpenCode::class);
        $runner->method('poluchitResultatHoda')->willReturn($result);

        return $runner;
    }
}
