<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSostoyanieWorkspace;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\UseCase\PoluchitSostoyanieWorkspaceQuery;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\InMemoryUchastnikiSessiiRepository;
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
        $this->assertSame(RolUchastnikaSessii::OWNER->value, $state->myRole);
        $this->assertTrue($state->canManage);
        $this->assertCount(1, $state->participants);
        $this->assertSame('account-1', $state->participants[0]->accountId);
        $this->assertSame('credential-1', $state->myDefaultCredentialId);
    }

    #[Test]
    public function writer_ne_upravlyaet_i_poluchaet_svoi_rekvizity(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $uchastniki = new InMemoryUchastnikiSessiiRepository();
        $uchastniki->save(UchastnikSessii::create(
            'workspace-1',
            'account-1',
            RolUchastnikaSessii::WRITER,
            'cred-writer',
            'model-writer',
            null,
            FabrikaWorkspace::NOW,
        ));

        $state = $this->query($session, null, $uchastniki)
            ->handle(new PoluchitSostoyanieWorkspace('workspace-1'));

        $this->assertSame(RolUchastnikaSessii::WRITER->value, $state->myRole);
        $this->assertFalse($state->canManage);
        $this->assertTrue($state->canAcceptMessages);
        $this->assertSame('cred-writer', $state->myDefaultCredentialId);
        $this->assertSame('model-writer', $state->myDefaultModel);
        $this->assertSame('owner', $state->participants[0]->role);
        $this->assertSame('writer', $state->participants[1]->role);
    }

    #[Test]
    public function viewer_ne_prinimaet_soobshcheniya(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $uchastniki = new InMemoryUchastnikiSessiiRepository();
        $uchastniki->save(UchastnikSessii::create(
            'workspace-1',
            'account-1',
            RolUchastnikaSessii::VIEWER,
            null,
            null,
            null,
            FabrikaWorkspace::NOW,
        ));

        $state = $this->query($session, null, $uchastniki)
            ->handle(new PoluchitSostoyanieWorkspace('workspace-1'));

        $this->assertSame(RolUchastnikaSessii::VIEWER->value, $state->myRole);
        $this->assertFalse($state->canManage);
        $this->assertFalse($state->canAcceptMessages);
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
        ?UchastnikiSessiiRepository $uchastniki = null,
    ): PoluchitSostoyanieWorkspaceQuery {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('getById')->willReturn($session);

        $turns = $this->createMock(HodOpenCodeRepository::class);
        $turns->method('findPosledniiPoSessii')->willReturn($lastTurn);
        $turns->method('zanyata')->willReturn(false);

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn('account-1');

        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->method('poluchitSsylkuRedaktora')
            ->willReturn('https://47000.payments-refactor-1a2b3c4d.workspaces.example.test');

        if ($uchastniki === null) {
            $uchastniki = $this->createMock(UchastnikiSessiiRepository::class);
            $uchastniki->method('find')->willReturn(null);
            $uchastniki->method('findBySessionId')->willReturn([]);
        }

        return new PoluchitSostoyanieWorkspaceQuery(
            $repository,
            $turns,
            $credentials,
            $projectami,
            new ProverkaDostupaKSessii($uchastniki),
            $uchastniki,
        );
    }
}
