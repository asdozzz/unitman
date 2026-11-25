<?php

declare(strict_types=1);

namespace App\Account\Tests\Api;

use App\Account\Api\AccountApi;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Публичный API политики provider-сессии.
 *
 * Метод {@see AccountApi::poluchitPolitikuSessiiProvaydera()} не использует
 * зависимости конструктора: он только разрешает код провайдера и отдаёт
 * read-model. Поэтому объект создаётся без конструктора, а тест проверяет
 * именно контракт AccountApi, который потребляет модуль AiChat.
 */
final class AccountApiTest extends TestCase
{
    #[Test]
    public function opencode_go_otdayot_header_sessiyu(): void
    {
        $policy = $this->api()->poluchitPolitikuSessiiProvaydera('opencode-go');

        $this->assertSame('opencode-go', $policy->provider);
        $this->assertSame('header', $policy->transport);
        $this->assertSame('x-opencode-session', $policy->key);
        $this->assertSame(300, $policy->ttlSeconds);
        $this->assertTrue($policy->supportsAiChat);
    }

    #[Test]
    public function opencode_otdayot_header_sessiyu(): void
    {
        $policy = $this->api()->poluchitPolitikuSessiiProvaydera('opencode');

        $this->assertSame('opencode', $policy->provider);
        $this->assertSame('header', $policy->transport);
        $this->assertSame('x-opencode-session', $policy->key);
        $this->assertTrue($policy->supportsAiChat);
    }

    #[Test]
    public function openai_otdayot_transport_none(): void
    {
        $policy = $this->api()->poluchitPolitikuSessiiProvaydera('openai');

        $this->assertSame('none', $policy->transport);
        $this->assertNull($policy->key);
        $this->assertTrue($policy->supportsAiChat);
    }

    #[Test]
    public function neizvestnyi_provayder_daet_tipizirovannuyu_oshibku(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.unknown_provider');

        $this->api()->poluchitPolitikuSessiiProvaydera('future-provider');
    }

    #[Test]
    public function pustoi_provayder_daet_tipizirovannuyu_oshibku(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.empty_provider');

        $this->api()->poluchitPolitikuSessiiProvaydera('   ');
    }

    private function api(): AccountApi
    {
        /** @var AccountApi $api */
        $api = (new \ReflectionClass(AccountApi::class))->newInstanceWithoutConstructor();

        return $api;
    }
}
