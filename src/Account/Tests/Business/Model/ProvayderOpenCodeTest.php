<?php

declare(strict_types=1);

namespace App\Account\Tests\Business\Model;

use App\Account\Business\Model\OpenCode\ProvayderOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Каталог провайдеров: полнота, ключевые атрибуты и разрешение кода.
 */
final class ProvayderOpenCodeTest extends TestCase
{
    #[Test]
    public function katalog_soderzhit_vse_trinadcat_provajderov(): void
    {
        $catalog = ProvayderOpenCode::katalog();

        $this->assertCount(13, $catalog);
        $this->assertFalse(property_exists($catalog[0], 'models'));
        $this->assertSame(
            [
                'openai',
                'anthropic',
                'google',
                'openrouter',
                'deepseek',
                'mistral',
                'groq',
                'xai',
                'together',
                'ollama',
                'opencode',
                'opencode-go',
                'custom',
            ],
            array_map(static fn ($provider): string => $provider->code, $catalog),
        );
    }

    #[Test]
    public function openai_opisan_korrektno(): void
    {
        $provider = ProvayderOpenCode::OPENAI;

        $this->assertSame('OpenAI', $provider->nazvanie());
        $this->assertSame('https://api.openai.com/v1', $provider->baseUrl());
        $this->assertSame('bearer', $provider->authStyle());
        $this->assertTrue($provider->podderzhivaetSpisokModelei());
    }

    #[Test]
    public function anthropic_ispolzuet_x_api_key(): void
    {
        $provider = ProvayderOpenCode::ANTHROPIC;

        $this->assertSame('x-api-key', $provider->authStyle());
        $this->assertSame('https://api.anthropic.com/v1', $provider->baseUrl());
        $this->assertTrue($provider->podderzhivaetSpisokModelei());
    }

    #[Test]
    public function ollama_bez_klyucha_i_bez_live_spiska(): void
    {
        $provider = ProvayderOpenCode::OLLAMA;

        $this->assertSame('none', $provider->authStyle());
        $this->assertFalse($provider->podderzhivaetSpisokModelei());
    }

    #[Test]
    public function custom_ne_imeet_base_url(): void
    {
        $provider = ProvayderOpenCode::CUSTOM;

        $this->assertSame('', $provider->baseUrl());
        $this->assertFalse($provider->podderzhivaetSpisokModelei());
    }

    #[Test]
    public function iz_koda_razreshaet_izvestnogo(): void
    {
        $this->assertSame(ProvayderOpenCode::DEEPSEEK, ProvayderOpenCode::izKoda(' deepseek '));
    }

    #[Test]
    public function iz_koda_otklonyaet_pustoi(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.empty_provider');

        ProvayderOpenCode::izKoda('   ');
    }

    #[Test]
    public function iz_koda_otklonyaet_neizvestnyi(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.unknown_provider');

        ProvayderOpenCode::izKoda('nope');
    }
}
