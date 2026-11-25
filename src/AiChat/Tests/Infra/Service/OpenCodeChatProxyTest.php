<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Infra\Service;

use App\Account\Business\Model\OpenCode\ProvayderOpenCode;
use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;
use App\AiChat\Infra\Service\OpenCodeChatProxy;
use App\AiChat\Infra\Service\Transport\ApplierTransportaPoPolitike;
use App\AiChat\Infra\Service\Transport\ProviderSessionTransportUnknownException;
use App\AiChat\Tests\Support\RecordingHttpClient;
use App\AiChat\Tests\Support\RecordingLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Транспорт provider-сессии применяется по политике, а не по коду
 * провайдера. Прокси не генерирует UUID и не добавляет session-поля,
 * когда сессия не задана.
 */
final class OpenCodeChatProxyTest extends TestCase
{
    private const BASE_URL = 'https://upstream.example/v1';
    private const TIMEOUT = 7.5;

    public function test_opencode_kladet_sessiyu_v_zagolovok(): void
    {
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $proxy = $this->sozdatProxy($client);

        $proxy->otpravit(
            self::BASE_URL,
            'sk-test',
            'model-a',
            [['role' => 'user', 'content' => 'hi']],
            [],
            ProvayderOpenCode::OPENCODE->value,
            ProvayderOpenCode::OPENCODE->politikaSessii(),
            'session-1',
        );

        $request = $client->requests[0];
        self::assertSame('Bearer sk-test', $request['options']['headers']['Authorization']);
        self::assertSame('session-1', $request['options']['headers']['x-opencode-session']);
        self::assertSame(self::BASE_URL.'/chat/completions', $request['url']);
        self::assertArrayNotHasKey('session_id', $request['options']['json']);
    }

    public function test_opencode_go_kladet_sessiyu_v_tot_zhe_zagolovok(): void
    {
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $proxy = $this->sozdatProxy($client);

        $proxy->otpravit(
            self::BASE_URL,
            'sk-test',
            'model-a',
            [['role' => 'user', 'content' => 'hi']],
            [],
            ProvayderOpenCode::OPENCODE_GO->value,
            ProvayderOpenCode::OPENCODE_GO->politikaSessii(),
            'session-1',
        );

        $request = $client->requests[0];
        self::assertSame('session-1', $request['options']['headers'][ProvayderOpenCode::OPENCODE_SESSION_HEADER]);
        self::assertArrayNotHasKey('session_id', $request['options']['json']);
    }

    public function test_openai_ne_peredayot_sessiyu(): void
    {
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $proxy = $this->sozdatProxy($client);

        $proxy->otpravit(
            self::BASE_URL,
            'sk-test',
            'model-a',
            [['role' => 'user', 'content' => 'hi']],
            [],
            ProvayderOpenCode::OPENAI->value,
            ProvayderOpenCode::OPENAI->politikaSessii(),
            'session-1',
        );

        $request = $client->requests[0];
        self::assertArrayNotHasKey('x-opencode-session', $request['options']['headers']);
        self::assertArrayNotHasKey('session_id', $request['options']['json']);
        self::assertSame(self::BASE_URL.'/chat/completions', $request['url']);
    }

    public function test_google_ne_peredayot_sessiyu(): void
    {
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $proxy = $this->sozdatProxy($client);

        $proxy->otpravit(
            self::BASE_URL,
            'sk-test',
            'model-a',
            [['role' => 'user', 'content' => 'hi']],
            [],
            ProvayderOpenCode::GOOGLE->value,
            ProvayderOpenCode::GOOGLE->politikaSessii(),
            'session-1',
        );

        $request = $client->requests[0];
        self::assertArrayNotHasKey('x-opencode-session', $request['options']['headers']);
        self::assertSame(self::BASE_URL.'/chat/completions', $request['url']);
    }

    public function test_body_politika_kladet_sessiyu_v_payload(): void
    {
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $proxy = $this->sozdatProxy($client);

        $proxy->otpravit(
            self::BASE_URL,
            'sk-test',
            'model-a',
            [['role' => 'user', 'content' => 'hi']],
            [],
            'custom-provider',
            ProviderSessionPolicy::body('session_id', 300),
            'session-1',
        );

        $request = $client->requests[0];
        self::assertSame('session-1', $request['options']['json']['session_id']);
        self::assertArrayNotHasKey('x-opencode-session', $request['options']['headers']);
    }

    public function test_query_politika_dobavlyaet_parametr_k_url(): void
    {
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $proxy = $this->sozdatProxy($client);

        $proxy->otpravit(
            self::BASE_URL.'?api-version=1',
            'sk-test',
            'model-a',
            [['role' => 'user', 'content' => 'hi']],
            [],
            'custom-provider',
            ProviderSessionPolicy::query('session', 300),
            'session-1',
        );

        // Существующие query-параметры не теряются.
        self::assertSame(
            self::BASE_URL.'/chat/completions?api-version=1&session=session-1',
            $client->requests[0]['url'],
        );
    }

    public function test_bez_session_id_sessiya_ne_podstavlyaetsya(): void
    {
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $proxy = $this->sozdatProxy($client);

        $proxy->otpravit(
            self::BASE_URL,
            'sk-test',
            'model-a',
            [['role' => 'user', 'content' => 'hi']],
            [],
            ProvayderOpenCode::OPENCODE->value,
            ProvayderOpenCode::OPENCODE->politikaSessii(),
            null,
        );

        self::assertArrayNotHasKey('x-opencode-session', $client->requests[0]['options']['headers']);
    }

    public function test_unknown_transport_brosaet_oshibku(): void
    {
        $client = new RecordingHttpClient(new MockResponse('{"choices":[]}'));
        $proxy = $this->sozdatProxy($client);

        $this->expectException(ProviderSessionTransportUnknownException::class);

        $proxy->otpravit(
            self::BASE_URL,
            'sk-test',
            'model-a',
            [['role' => 'user', 'content' => 'hi']],
            [],
            'future-provider',
            ProviderSessionPolicy::unknown(),
            'session-1',
        );
    }

    private function sozdatProxy(RecordingHttpClient $client): OpenCodeChatProxy
    {
        return new OpenCodeChatProxy(
            $client,
            new RecordingLogger(),
            self::TIMEOUT,
            new ApplierTransportaPoPolitike(),
        );
    }
}
