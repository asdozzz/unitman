<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSpisokWorkspace;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\UseCase\PoluchitSpisokWorkspaceQuery;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use App\OpenCode\Tests\Support\InMemoryUchastnikiSessiiRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Список workspace: роль текущего пользователя, владелец и признак «общий».
 */
final class PoluchitSpisokWorkspaceQueryTest extends TestCase
{
    #[Test]
    public function vladelec_bez_uchastnikov_poluchaet_owner_i_ne_shared(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);

        $items = $this->query('account-1', [$session], new InMemoryUchastnikiSessiiRepository())
            ->handle(new PoluchitSpisokWorkspace());

        $this->assertCount(1, $items);
        $this->assertSame(RolUchastnikaSessii::OWNER->value, $items[0]->myRole);
        $this->assertSame('account-1', $items[0]->ownerAccountId);
        $this->assertSame(1, $items[0]->participantsCount);
        $this->assertFalse($items[0]->shared);
    }

    #[Test]
    public function uchastnik_vidit_svoyu_rol_i_shared(): void
    {
        $session = FabrikaWorkspace::vStatuse(StatusWorkspace::RUNNING);
        $uchastniki = new InMemoryUchastnikiSessiiRepository();
        $uchastniki->save(UchastnikSessii::create(
            'workspace-1',
            'account-2',
            RolUchastnikaSessii::WRITER,
            null,
            null,
            'account-1',
            FabrikaWorkspace::NOW,
        ));

        $items = $this->query('account-2', [], $uchastniki, $session)
            ->handle(new PoluchitSpisokWorkspace());

        $this->assertCount(1, $items);
        $this->assertSame(RolUchastnikaSessii::WRITER->value, $items[0]->myRole);
        $this->assertSame('account-1', $items[0]->ownerAccountId);
        $this->assertSame(2, $items[0]->participantsCount);
        $this->assertTrue($items[0]->shared);
    }

    /**
     * @param list<SessiyaOpenCode> $ownSessions
     */
    private function query(
        string $accountId,
        array $ownSessions,
        UchastnikiSessiiRepository $uchastniki,
        ?SessiyaOpenCode $sessionsById = null,
    ): PoluchitSpisokWorkspaceQuery {
        $repository = $this->createMock(SessiyaOpenCodeRepository::class);
        $repository->method('findByAccountId')->willReturn($ownSessions);
        $repository->method('findById')->willReturn($sessionsById);
        $repository->method('findWorkspaceUpdate')->willReturn(null);

        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('tekuschiiAccountId')->willReturn($accountId);

        $projectami = $this->createMock(UmeetUpravlyatProektamiWorkspace::class);
        $projectami->method('poluchitSsylkuRedaktora')->willReturn(null);

        return new PoluchitSpisokWorkspaceQuery(
            $repository,
            $credentials,
            $projectami,
            null,
            $uchastniki,
        );
    }
}
