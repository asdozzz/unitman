<?php

declare(strict_types=1);

namespace App\Account\Tests\Business\Model;

use App\Account\Business\Model\OpenCode\ProvayderOpenCode;
use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;
use App\Account\Business\Model\OpenCode\ProviderSessionTransport;
use App\Account\Business\ReadModel\ProviderSessionPolicyReadModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Gate-тесты политики provider-сессии: каждый провайдер обязан иметь
 * явную политику, а неизвестный транспорт блокирует AI-чат.
 */
final class ProviderSessionPolicyTest extends TestCase
{
    #[Test]
    public function kazhdyi_case_provajdera_imeet_politiku(): void
    {
        foreach (ProvayderOpenCode::cases() as $provider) {
            $policy = $provider->politikaSessii();

            $this->assertSame(
                $policy->transport !== ProviderSessionTransport::Unknown,
                $policy->supportsAiChat,
                \sprintf('supportsAiChat не согласован с транспортом у %s', $provider->value),
            );
            $this->assertGreaterThan(
                0,
                $policy->ttlSeconds,
                \sprintf('TTL должен быть положительным у %s', $provider->value),
            );
        }
    }

    #[Test]
    public function opencode_i_opencode_go_ispolzuyut_header_sessii(): void
    {
        foreach ([ProvayderOpenCode::OPENCODE, ProvayderOpenCode::OPENCODE_GO] as $provider) {
            $policy = $provider->politikaSessii();

            $this->assertSame(ProviderSessionTransport::Header, $policy->transport);
            $this->assertSame('x-opencode-session', $policy->key);
            $this->assertSame(300, $policy->ttlSeconds);
            $this->assertTrue($policy->supportsAiChat);
        }
    }

    #[Test]
    public function providery_bez_sessii_ispolzuyut_none_i_ttl_300(): void
    {
        $providers = [
            ProvayderOpenCode::OPENAI,
            ProvayderOpenCode::ANTHROPIC,
            ProvayderOpenCode::OPENROUTER,
            ProvayderOpenCode::DEEPSEEK,
            ProvayderOpenCode::MISTRAL,
            ProvayderOpenCode::GROQ,
            ProvayderOpenCode::XAI,
            ProvayderOpenCode::TOGETHER,
            ProvayderOpenCode::OLLAMA,
            ProvayderOpenCode::CUSTOM,
        ];

        foreach ($providers as $provider) {
            $policy = $provider->politikaSessii();

            $this->assertSame(ProviderSessionTransport::None, $policy->transport);
            $this->assertNull($policy->key);
            $this->assertSame(300, $policy->ttlSeconds);
            $this->assertTrue($policy->supportsAiChat);
        }
    }

    #[Test]
    public function google_ispolzuet_none_i_ttl_3600(): void
    {
        $policy = ProvayderOpenCode::GOOGLE->politikaSessii();

        $this->assertSame(ProviderSessionTransport::None, $policy->transport);
        $this->assertNull($policy->key);
        $this->assertSame(3600, $policy->ttlSeconds);
        $this->assertTrue($policy->supportsAiChat);
    }

    #[Test]
    public function katalog_soglasovan_s_politikoi_sessii(): void
    {
        $catalog = ProvayderOpenCode::katalog();
        $providers = ProvayderOpenCode::cases();

        $this->assertCount(\count($providers), $catalog);

        foreach ($providers as $index => $provider) {
            $policy = $provider->politikaSessii();
            $item = $catalog[$index];

            $this->assertSame($provider->value, $item->code);
            $this->assertSame($policy->transport->value, $item->sessionTransport);
            $this->assertSame($policy->key, $item->sessionKey);
            $this->assertSame($policy->ttlSeconds, $item->sessionTtlSeconds);
            $this->assertSame($policy->supportsAiChat, $item->supportsAiChat);
        }
    }

    #[Test]
    public function unknown_politika_blokiruet_ai_chat(): void
    {
        $policy = ProviderSessionPolicy::unknown();

        $this->assertSame(ProviderSessionTransport::Unknown, $policy->transport);
        $this->assertFalse($policy->supportsAiChat);
    }

    #[Test]
    public function ttl_po_umolchaniyu_imenovan_i_raven_300(): void
    {
        $this->assertSame(300, ProviderSessionPolicy::DEFAULT_TTL_SECONDS);
        $this->assertSame(
            ProviderSessionPolicy::DEFAULT_TTL_SECONDS,
            ProviderSessionPolicy::unknown()->ttlSeconds,
        );
        $this->assertSame(
            ProviderSessionPolicy::DEFAULT_TTL_SECONDS,
            ProvayderOpenCode::OPENCODE->politikaSessii()->ttlSeconds,
        );
        $this->assertSame(
            ProviderSessionPolicy::DEFAULT_TTL_SECONDS,
            ProvayderOpenCode::OPENCODE_GO->politikaSessii()->ttlSeconds,
        );
    }

    #[Test]
    public function unknown_politika_ne_imeet_klyucha_i_imeet_ttl(): void
    {
        $policy = ProviderSessionPolicy::unknown();

        $this->assertNull($policy->key);
        $this->assertGreaterThan(0, $policy->ttlSeconds);
        $this->assertFalse($policy->supportsAiChat);
    }

    #[Test]
    public function read_model_iz_politiki_peredaet_vse_polya(): void
    {
        $readModel = ProviderSessionPolicyReadModel::izPolitiki(
            'opencode-go',
            ProviderSessionPolicy::header('x-opencode-session', 300),
        );

        $this->assertSame('opencode-go', $readModel->provider);
        $this->assertSame('header', $readModel->transport);
        $this->assertSame('x-opencode-session', $readModel->key);
        $this->assertSame(300, $readModel->ttlSeconds);
        $this->assertTrue($readModel->supportsAiChat);
    }

    #[Test]
    public function read_model_dlya_unknown_blokiruet_ai_chat(): void
    {
        $readModel = ProviderSessionPolicyReadModel::izPolitiki(
            'future',
            ProviderSessionPolicy::unknown(),
        );

        $this->assertSame('future', $readModel->provider);
        $this->assertSame('unknown', $readModel->transport);
        $this->assertNull($readModel->key);
        $this->assertFalse($readModel->supportsAiChat);
    }
}
