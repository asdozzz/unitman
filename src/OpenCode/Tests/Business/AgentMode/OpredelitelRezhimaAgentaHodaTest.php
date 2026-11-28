<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\AgentMode;

use App\OpenCode\Business\AgentMode\Model\AgentMode;
use App\OpenCode\Business\AgentMode\Utils\OpredelitelRezhimaAgentaHoda;
use App\OpenCode\Tests\Support\InMemoryAgentModeRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpredelitelRezhimaAgentaHodaTest extends TestCase
{
    #[Test]
    public function zaproshennyy_globalnyy_mod_vybiraetsya_po_id(): void
    {
        $repo = new InMemoryAgentModeRepository();
        $mode = $this->mode('mode-build', 'build', 'Build', true, null);
        $repo->save($mode);

        $rezultat = (new OpredelitelRezhimaAgentaHoda($repo))->opredelit(
            'acc-1',
            OpredelitelRezhimaAgentaHoda::CHAT_UNIT,
            'mode-build',
        );

        self::assertSame('mode-build', $rezultat->modeId);
        self::assertSame('unitman-build', $rezultat->agentName);
        self::assertSame('prompt', $rezultat->prompt);
        self::assertFalse($rezultat->denyEdits);
    }

    #[Test]
    public function mod_plan_zapreshchaet_pravki(): void
    {
        $repo = new InMemoryAgentModeRepository();
        $repo->save($this->mode('mode-plan', 'plan', 'Plan', true, null, AgentMode::ACCESS_PLAN));

        $rezultat = (new OpredelitelRezhimaAgentaHoda($repo))->opredelit(
            'acc-1',
            OpredelitelRezhimaAgentaHoda::CHAT_UNIT,
            'mode-plan',
        );

        self::assertTrue($rezultat->denyEdits);
    }

    #[Test]
    public function chuzhoy_personalnyy_mod_neotlichim_ot_nesushchestvuyushchego(): void
    {
        $repo = new InMemoryAgentModeRepository();
        $repo->save($this->mode('mode-1', 'my', 'My', false, 'acc-2'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.agent_mode.not_found');

        (new OpredelitelRezhimaAgentaHoda($repo))->opredelit(
            'acc-1',
            OpredelitelRezhimaAgentaHoda::CHAT_UNIT,
            'mode-1',
        );
    }

    #[Test]
    public function pri_otsutstvii_zaprosa_beretsya_default_tipa_chata(): void
    {
        $repo = new InMemoryAgentModeRepository();
        $repo->save($this->mode('mode-build', 'build', 'Build', true, null));
        $repo->save($this->mode('mode-plan', 'plan', 'Plan', true, null, AgentMode::ACCESS_PLAN));
        $repo->saveDefault('acc-1', OpredelitelRezhimaAgentaHoda::CHAT_WORKSPACE, 'mode-plan');

        $rezultat = (new OpredelitelRezhimaAgentaHoda($repo))->opredelit(
            'acc-1',
            OpredelitelRezhimaAgentaHoda::CHAT_WORKSPACE,
        );

        self::assertSame('mode-plan', $rezultat->modeId);
    }

    #[Test]
    public function bez_defaulta_beretsya_pervyy_vklyuchennyy_mod(): void
    {
        $repo = new InMemoryAgentModeRepository();
        $repo->save($this->mode('mode-build', 'build', 'Build', true, null));

        $rezultat = (new OpredelitelRezhimaAgentaHoda($repo))->opredelit(
            'acc-1',
            OpredelitelRezhimaAgentaHoda::CHAT_UNIT,
        );

        self::assertSame('mode-build', $rezultat->modeId);
    }

    #[Test]
    public function pustoy_katalog_daet_legacy_rezhim(): void
    {
        $rezultat = (new OpredelitelRezhimaAgentaHoda(new InMemoryAgentModeRepository()))->opredelit(
            'acc-1',
            OpredelitelRezhimaAgentaHoda::CHAT_UNIT,
        );

        self::assertNull($rezultat->modeId);
        self::assertFalse($rezultat->estRezhim());
    }

    private function mode(
        string $id,
        string $slug,
        string $name,
        bool $isGlobal,
        ?string $ownerId,
        string $accessMode = AgentMode::ACCESS_BUILD,
    ): AgentMode {
        return AgentMode::create(
            $id,
            $slug,
            $name,
            '',
            'prompt',
            $accessMode,
            $isGlobal,
            $ownerId,
            'system',
            1730000000,
        );
    }
}
