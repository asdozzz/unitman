<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Workspace;

use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProverkaDostupaWorkspaceSessiiTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function vladelec_bez_stroki_uchastnika_est_nevyavnyi_owner(): void
    {
        $dostup = $this->dostup(null);

        self::assertNull(
            $dostup->trebovat($this->workspace(), 'owner-1', RolUchastnikaSessii::WRITER),
        );
        self::assertSame(RolUchastnikaSessii::OWNER, $dostup->rol($this->workspace(), 'owner-1'));
    }

    #[Test]
    public function neuchastnik_ne_otlichim_ot_otsutstvuyushego_workspace(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.not_found');

        $this->dostup(null)->trebovat($this->workspace(), 'acc-3', RolUchastnikaSessii::VIEWER);
    }

    #[Test]
    public function viewer_chitaet_no_ne_pishet(): void
    {
        $dostup = $this->dostup($this->uchastnik('acc-2', RolUchastnikaSessii::VIEWER));

        $uchastnik = $dostup->trebovat($this->workspace(), 'acc-2', RolUchastnikaSessii::VIEWER);
        self::assertSame(RolUchastnikaSessii::VIEWER, $uchastnik?->getRol());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.forbidden');

        $dostup->trebovat($this->workspace(), 'acc-2', RolUchastnikaSessii::WRITER);
    }

    #[Test]
    public function writer_pishet(): void
    {
        $uchastnik = $this->dostup($this->uchastnik('acc-2', RolUchastnikaSessii::WRITER))
            ->trebovat($this->workspace(), 'acc-2', RolUchastnikaSessii::WRITER);

        self::assertSame('acc-2', $uchastnik?->getAccountId());
    }

    private function dostup(?UchastnikSessii $uchastnik): ProverkaDostupaWorkspaceSessii
    {
        $repository = $this->createMock(WorkspaceParticipantsRepository::class);
        $repository->method('find')->willReturn($uchastnik);

        return new ProverkaDostupaWorkspaceSessii($repository);
    }

    private function workspace(): WorkspaceSession
    {
        return WorkspaceSession::create(
            'workspace-1',
            'owner-1',
            'credential-owner',
            'payments-refactor',
            'main',
            [FabrikaWorkspace::proekt()],
            'Первый промпт',
            self::NOW,
        );
    }

    private function uchastnik(string $accountId, RolUchastnikaSessii $rol): UchastnikSessii
    {
        return UchastnikSessii::create('workspace-1', $accountId, $rol, null, null, 'owner-1', self::NOW);
    }
}
