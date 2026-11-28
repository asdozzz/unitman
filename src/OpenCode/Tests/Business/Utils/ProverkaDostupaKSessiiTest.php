<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProverkaDostupaKSessiiTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function vladelec_bez_stroki_uchastnika_dopushchen(): void
    {
        $dostup = $this->dostup(null);

        $this->assertNull(
            $dostup->trebovat($this->sessiya(), 'owner-1', RolUchastnikaSessii::WRITER),
        );
        $this->assertSame(RolUchastnikaSessii::OWNER, $dostup->rol($this->sessiya(), 'owner-1'));
    }

    #[Test]
    public function neuchastnik_ne_otlichim_ot_otsutstvuyushchey_sessii(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.not_found');

        $this->dostup(null)->trebovat($this->sessiya(), 'acc-3', RolUchastnikaSessii::VIEWER);
    }

    #[Test]
    public function viewer_mozhet_chitat_i_ne_mozhet_pisat(): void
    {
        $dostup = $this->dostup($this->uchastnik('acc-2', RolUchastnikaSessii::VIEWER));

        $uchastnik = $dostup->trebovat($this->sessiya(), 'acc-2', RolUchastnikaSessii::VIEWER);
        $this->assertNotNull($uchastnik);
        $this->assertSame(RolUchastnikaSessii::VIEWER, $uchastnik->getRol());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.forbidden');

        $dostup->trebovat($this->sessiya(), 'acc-2', RolUchastnikaSessii::WRITER);
    }

    #[Test]
    public function writer_mozhet_pisat(): void
    {
        $uchastnik = $this->dostup($this->uchastnik('acc-2', RolUchastnikaSessii::WRITER))
            ->trebovat($this->sessiya(), 'acc-2', RolUchastnikaSessii::WRITER);

        $this->assertNotNull($uchastnik);
        $this->assertSame('acc-2', $uchastnik->getAccountId());
        $this->assertSame(RolUchastnikaSessii::WRITER, $uchastnik->getRol());
    }

    private function dostup(?UchastnikSessii $uchastnik): ProverkaDostupaKSessii
    {
        $repository = $this->createMock(UchastnikiSessiiRepository::class);
        $repository->method('find')->willReturn($uchastnik);

        return new ProverkaDostupaKSessii($repository);
    }

    private function sessiya(): SessiyaOpenCode
    {
        return SessiyaOpenCode::createWorkspace(
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
