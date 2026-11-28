<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Workspace;

use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkspaceFeatureFlagsTest extends TestCase
{
    #[Test]
    public function po_umolchaniyu_vse_vyklyucheno(): void
    {
        $flags = new WorkspaceFeatureFlags();

        self::assertFalse($flags->chitatIzNovogoAgregata());
        self::assertFalse($flags->pisatVNoviyAgregat());
        self::assertFalse($flags->zerkaloVklyucheno());
    }

    #[Test]
    public function chtenie_bez_zapisi_trebuet_zerkala(): void
    {
        $flags = new WorkspaceFeatureFlags(read: true, write: false, mirror: true);

        self::assertTrue($flags->chitatIzNovogoAgregata());
        self::assertFalse($flags->pisatVNoviyAgregat());
    }

    #[Test]
    public function chtenie_bez_zapisi_i_bez_zerkala_otklonyaetsya(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('opencode.workspace.flags.read_requires_mirror');

        new WorkspaceFeatureFlags(read: true, write: false, mirror: false);
    }

    #[Test]
    public function zapis_bez_chteniya_otklonyaetsya(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('opencode.workspace.flags.write_requires_read');

        new WorkspaceFeatureFlags(read: false, write: true, mirror: true);
    }

    #[Test]
    public function chtenie_i_zapis_vmeste_dopustimy(): void
    {
        $flags = new WorkspaceFeatureFlags(read: true, write: true, mirror: false);

        self::assertTrue($flags->chitatIzNovogoAgregata());
        self::assertTrue($flags->pisatVNoviyAgregat());
    }
}
