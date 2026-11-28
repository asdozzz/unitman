<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Workspace;

use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Utils\OpredelitelRekvizitovHodaOpenCode;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpredelitelRekvizitovHodaWorkspaceTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function writer_trait_svoi_rekvizity_po_umolchaniyu(): void
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->expects($this->once())
            ->method('trebovatVladelca')
            ->with('acc-2', 'cred-writer');
        $credentials->expects($this->never())->method('poluchitPervieRekvizityAvtora');

        $istochnik = (new OpredelitelRekvizitovHodaOpenCode($credentials))->opredelitDlyaWorkspace(
            $this->workspace(),
            'acc-2',
            $this->uchastnik('acc-2', 'cred-writer', 'model-writer'),
            null,
        );

        self::assertSame('acc-2', $istochnik->accountId);
        self::assertSame('cred-writer', $istochnik->credentialId);
        self::assertSame('model-writer', $istochnik->modelPoUmolchaniyu);
    }

    #[Test]
    public function vladelec_mozhet_ispolzovat_rekvizity_sessii(): void
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->expects($this->once())
            ->method('trebovatVladelca')
            ->with('owner-1', 'cred-owner');

        $istochnik = (new OpredelitelRekvizitovHodaOpenCode($credentials))->opredelitDlyaWorkspace(
            $this->workspace(),
            'owner-1',
            $this->uchastnik('owner-1', null, null),
            null,
        );

        self::assertSame('cred-owner', $istochnik->credentialId);
    }

    #[Test]
    public function ne_vladelec_bez_uchastnika_beret_pervye_rekvizity_akkaunta(): void
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->expects($this->once())
            ->method('poluchitPervieRekvizityAvtora')
            ->with('acc-2')
            ->willReturn('cred-first');
        $credentials->expects($this->once())
            ->method('trebovatVladelca')
            ->with('acc-2', 'cred-first');

        $istochnik = (new OpredelitelRekvizitovHodaOpenCode($credentials))->opredelitDlyaWorkspace(
            $this->workspace(),
            'acc-2',
            null,
            null,
        );

        self::assertSame('cred-first', $istochnik->credentialId);
        self::assertNull($istochnik->modelPoUmolchaniyu);
    }

    #[Test]
    public function zapros_imeet_prioritet(): void
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->expects($this->once())
            ->method('trebovatVladelca')
            ->with('acc-2', 'cred-request');

        $istochnik = (new OpredelitelRekvizitovHodaOpenCode($credentials))->opredelitDlyaWorkspace(
            $this->workspace(),
            'acc-2',
            $this->uchastnik('acc-2', 'cred-writer', 'model-writer'),
            'cred-request',
        );

        self::assertSame('cred-request', $istochnik->credentialId);
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

    private function uchastnik(string $accountId, ?string $credentialId, ?string $model): UchastnikSessii
    {
        return UchastnikSessii::create(
            'workspace-1',
            $accountId,
            \App\OpenCode\Business\Model\RolUchastnikaSessii::WRITER,
            $credentialId,
            $model,
            'owner-1',
            self::NOW,
        );
    }
}
