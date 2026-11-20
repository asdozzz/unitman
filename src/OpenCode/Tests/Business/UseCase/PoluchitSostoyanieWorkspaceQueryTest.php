<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSostoyanieWorkspace;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\UseCase\PoluchitSostoyanieWorkspaceQuery;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PoluchitSostoyanieWorkspaceQueryTest extends TestCase
{
    #[Test]
    public function rabotayushchii_workspace_bez_hodov_prinimaet_soobshcheniya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $state = $this->query($session, null)->handle(new PoluchitSostoyanieWorkspace('workspace-1'));

        $this->assertSame('workspace-1', $state->id);
        $this->assertSame('payments-refactor', $state->name);
        $this->assertSame(StatusWorkspace::RUNNING->value, $state->status);
        $this->assertFalse($state->archived);
        $this->assertTrue($state->canAcceptMessages);
        $this->assertNull($state->lastTurnId);
        $this->assertNull($state->lastTurnStatus);
        $this->assertSame('https://47000.payments-refactor-1a2b3c4d.workspaces.example.test', $state->ideLink);
        $this->assertCount(1, $state->projects);
        $this->assertSame('project-1', $state->projects[0]->projectId);
    }

    #[Test]
    public function vypolnyayushchiisya_hod_blokiruet_novye_soobshcheniya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $turn = HodOpenCode::create('turn-1', 'workspace-1', 1, 'workflow-1', 'Привет', [], FabrikaWorkspace::NOW);

        $state = $this->query($session, $turn)->handle(new PoluchitSostoyanieWorkspace('workspace-1'));

        $this->assertFalse($state->canAcceptMessages);
        $this->assertSame('turn-1', $state->lastTurnId);
        $this->assertSame('RUNNING', $state->lastTurnStatus);
    }

    #[Test]
    public function arhivnii_workspace_ne_prinimaet_soobshcheniya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::ARCHIVED);
        $session->arhivirovat('manual', FabrikaWorkspace::NOW + 1);

        $state = $this->query($session, null)->handle(new PoluchitSostoyanieWorkspace('workspace-1'));

        $this->assertTrue($state->archived);
        $this->assertFalse($state->canAcceptMessages);
        $this->assertSame(StatusWorkspace::ARCHIVED->value, $state->status);
    }

    private function query(
        \App\OpenCode\Business\Model\SessiyaOpenCode $session,
        ?HodOpenCode $lastTurn,
    ): PoluchitSostoyanieWorkspaceQuery {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);

        $turns = $this->createMock(HodOpenCodeRepository::class);
        $turns->method('findPosledniiPoSessii')->willReturn($lastTurn);

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');

        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->method('poluchitSsylkuRedaktora')
            ->willReturn('https://47000.payments-refactor-1a2b3c4d.workspaces.example.test');

        return new PoluchitSostoyanieWorkspaceQuery($repository, $turns, $credentials, $projectami);
    }
}
