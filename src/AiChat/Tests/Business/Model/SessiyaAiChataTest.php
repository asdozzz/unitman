<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Business\Model;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;
use App\AiChat\Business\Model\PrichinaArhivaciiSessiiAiChata;
use App\AiChat\Business\Model\RolSoobsheniyaAiChata;
use App\AiChat\Business\Model\SessiyaAiChata;
use App\AiChat\Business\Model\SoobshenieAiChata;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Доменные тесты AI-сессии: расчёт TTL, архивация, владелец и заголовок.
 */
final class SessiyaAiChataTest extends TestCase
{
    private const NOW = 1_000_000;
    private const CHAT_TTL = 86_400;
    private const PROVIDER_MAX_TTL = 300;

    #[Test]
    public function create_schitaet_istechenie_polzovatelskoi_sessii(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::none(300));

        $this->assertSame(self::NOW + self::CHAT_TTL, $session->expiresAt());
        $this->assertSame(self::NOW, $session->createdAt());
        $this->assertSame(self::NOW, $session->updatedAt());
        $this->assertSame('', $session->title());
        $this->assertFalse($session->arhivirovana());
    }

    #[Test]
    public function create_s_header_transportom_zapolnyaet_provider_session(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::header('x-opencode-session', 300));

        $this->assertSame('provider-session-1', $session->providerSessionId());
        $this->assertSame(self::NOW + 300, $session->providerSessionExpiresAt());
    }

    #[Test]
    public function create_bez_provider_session_ostavlyaet_polya_null(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::none(300));

        $this->assertNull($session->providerSessionId());
        $this->assertNull($session->providerSessionExpiresAt());
    }

    #[Test]
    public function create_primenyaet_min_ttl_i_max_ttl(): void
    {
        $policyTtlBigger = $this->createSession(ProviderSessionPolicy::header('x', 600));
        $this->assertSame(self::NOW + self::PROVIDER_MAX_TTL, $policyTtlBigger->providerSessionExpiresAt());

        $policyTtlSmaller = $this->createSession(ProviderSessionPolicy::header('x', 100));
        $this->assertSame(self::NOW + 100, $policyTtlSmaller->providerSessionExpiresAt());
    }

    #[Test]
    public function arhivirovat_pri_istechenii_na_granice_i_idempotentno(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::none(300));

        $this->assertFalse($session->arhivirovatPriIstechenii($session->expiresAt() - 1));
        $this->assertFalse($session->arhivirovana());

        $this->assertTrue($session->arhivirovatPriIstechenii($session->expiresAt()));
        $this->assertTrue($session->arhivirovana());
        $this->assertSame(PrichinaArhivaciiSessiiAiChata::Expired->value, $session->archiveReason());
        $this->assertSame($session->expiresAt(), $session->archivedAt());

        $this->assertFalse($session->arhivirovatPriIstechenii($session->expiresAt() + 10));
        $this->assertSame($session->expiresAt(), $session->archivedAt());
    }

    #[Test]
    public function provider_session_istekla_na_granice(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::header('x', 300));

        $this->assertFalse($session->providerSessionIstekla(self::NOW + 299));
        $this->assertTrue($session->providerSessionIstekla(self::NOW + 300));
        $this->assertTrue($session->providerSessionIstekla(self::NOW + 301));
    }

    #[Test]
    public function provider_session_bez_transporta_ne_istekaet(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::none(300));

        $this->assertFalse($session->providerSessionIstekla(self::NOW + 1_000_000));
    }

    #[Test]
    public function naznachit_zagolovok_trim_i_obrezka_do_256(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::none(300));

        $session->naznachitZagolovokIzPervogoSoobsheniya('  Privet  ', self::NOW + 1);
        $this->assertSame('Privet', $session->title());
        $this->assertSame(self::NOW + 1, $session->updatedAt());

        $long = $this->createSession(ProviderSessionPolicy::none(300));
        $long->naznachitZagolovokIzPervogoSoobsheniya(str_repeat('ж', 300), self::NOW + 1);
        $this->assertSame(256, mb_strlen($long->title()));
        $this->assertSame(SessiyaAiChata::TITLE_MAX_LENGTH, mb_strlen($long->title()));
    }

    #[Test]
    public function naznachit_zagolovok_tolko_odin_raz(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::none(300));

        $session->naznachitZagolovokIzPervogoSoobsheniya('Pervoe', self::NOW + 1);
        $session->naznachitZagolovokIzPervogoSoobsheniya('Vtoroe', self::NOW + 2);

        $this->assertSame('Pervoe', $session->title());
    }

    #[Test]
    public function trebovat_vladelca_otklonyaet_chuzhogo(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::none(300));

        $session->trebovatVladelca('account-1');
        $this->addToAssertionCount(1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ai.chat.session_not_found');
        $session->trebovatVladelca('account-2');
    }

    #[Test]
    public function trebovat_aktivnuyu_arhivnaya_daet_session_archived(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::none(300));
        $session->arhivirovat(PrichinaArhivaciiSessiiAiChata::Manual->value, self::NOW + 1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ai.chat.session_archived');
        $session->trebovatAktivnuyu(self::NOW + 1);
    }

    #[Test]
    public function trebovat_aktivnuyu_istekshaya_daet_session_expired(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::none(300));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ai.chat.session_expired');
        $session->trebovatAktivnuyu($session->expiresAt());
    }

    #[Test]
    public function otvyazat_rekvizity_obnulyaet_i_arhiviruet(): void
    {
        $session = $this->createSession(ProviderSessionPolicy::none(300), 'credential-1');

        $session->otvyazatRekvizity(self::NOW + 5);

        $this->assertNull($session->credentialId());
        $this->assertTrue($session->arhivirovana());
        $this->assertSame(PrichinaArhivaciiSessiiAiChata::CredentialDeleted->value, $session->archiveReason());
        $this->assertSame(self::NOW + 5, $session->archivedAt());
    }

    #[Test]
    public function soobshenie_polzovatelya_v_formate_openai(): void
    {
        $message = SoobshenieAiChata::polzovatelya('m1', 's1', 1, 'Privet', self::NOW);

        $this->assertSame(
            ['role' => 'user', 'content' => 'Privet'],
            $message->vFormatOpenAi(),
        );
        $this->assertSame(RolSoobsheniyaAiChata::User, $message->role);
    }

    #[Test]
    public function soobshenie_assistenta_peredayot_tool_calls(): void
    {
        $toolCalls = [[
            'id' => 'call_1',
            'type' => 'function',
            'function' => ['name' => 'search', 'arguments' => '{}'],
        ]];

        $message = SoobshenieAiChata::assistenta('m2', 's1', 2, null, $toolCalls, self::NOW);

        $this->assertSame(
            [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => $toolCalls,
            ],
            $message->vFormatOpenAi(),
        );
    }

    #[Test]
    public function soobshenie_instrumenta_soderzhit_tool_call_id_i_name(): void
    {
        $message = SoobshenieAiChata::instrumenta('m3', 's1', 3, 'call_1', '{"ok":true}', 'search', self::NOW);

        $this->assertSame(
            [
                'role' => 'tool',
                'content' => '{"ok":true}',
                'tool_call_id' => 'call_1',
                'name' => 'search',
            ],
            $message->vFormatOpenAi(),
        );
    }

    private function createSession(
        ProviderSessionPolicy $policy,
        ?string $credentialId = null,
    ): SessiyaAiChata {
        return SessiyaAiChata::create(
            'session-1',
            'account-1',
            'openai',
            $credentialId,
            'gpt-4o-mini',
            self::NOW,
            $policy,
            self::CHAT_TTL,
            self::PROVIDER_MAX_TTL,
            'provider-session-1',
        );
    }
}
