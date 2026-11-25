<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Infra\Service\Transport;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;
use App\AiChat\Infra\Service\Transport\BodySessionTransport;
use App\AiChat\Infra\Service\Transport\HeaderSessionTransport;
use App\AiChat\Infra\Service\Transport\NoSessionTransport;
use App\AiChat\Infra\Service\Transport\ProviderSessionTransportUnknownException;
use App\AiChat\Infra\Service\Transport\QuerySessionTransport;
use App\AiChat\Infra\Service\Transport\UnknownSessionTransport;
use PHPUnit\Framework\TestCase;

/**
 * Каждый applier мутирует ровно свою «корзину» запроса и ровно один раз.
 */
final class SessionTransportApplierTest extends TestCase
{
    public function test_header_menyaet_tolko_headers(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $body = ['model' => 'model-a'];
        $query = ['api-version' => '1'];

        (new HeaderSessionTransport())->apply(
            ProviderSessionPolicy::header('x-opencode-session', 300),
            'session-1',
            $headers,
            $body,
            $query,
        );

        self::assertSame(
            ['Content-Type' => 'application/json', 'x-opencode-session' => 'session-1'],
            $headers,
        );
        self::assertSame(['model' => 'model-a'], $body);
        self::assertSame(['api-version' => '1'], $query);
    }

    public function test_body_menyaet_tolko_body(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $body = ['model' => 'model-a'];
        $query = ['api-version' => '1'];

        (new BodySessionTransport())->apply(
            ProviderSessionPolicy::body('session_id', 300),
            'session-1',
            $headers,
            $body,
            $query,
        );

        self::assertSame(['model' => 'model-a', 'session_id' => 'session-1'], $body);
        self::assertSame(['Content-Type' => 'application/json'], $headers);
        self::assertSame(['api-version' => '1'], $query);
    }

    public function test_query_menyaet_tolko_query(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $body = ['model' => 'model-a'];
        $query = ['api-version' => '1'];

        (new QuerySessionTransport())->apply(
            ProviderSessionPolicy::query('session', 300),
            'session-1',
            $headers,
            $body,
            $query,
        );

        self::assertSame(['api-version' => '1', 'session' => 'session-1'], $query);
        self::assertSame(['Content-Type' => 'application/json'], $headers);
        self::assertSame(['model' => 'model-a'], $body);
    }

    public function test_none_ne_menyaet_nichego(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $body = ['model' => 'model-a'];
        $query = ['api-version' => '1'];

        (new NoSessionTransport())->apply(
            ProviderSessionPolicy::none(300),
            'session-1',
            $headers,
            $body,
            $query,
        );

        self::assertSame(['Content-Type' => 'application/json'], $headers);
        self::assertSame(['model' => 'model-a'], $body);
        self::assertSame(['api-version' => '1'], $query);
    }

    public function test_unknown_brosaet_domernoe_isklyuchenie(): void
    {
        $headers = [];
        $body = [];
        $query = [];

        $this->expectException(ProviderSessionTransportUnknownException::class);
        $this->expectExceptionMessage('ai.chat.provider_session_transport_unknown');

        (new UnknownSessionTransport())->apply(
            ProviderSessionPolicy::unknown(),
            'session-1',
            $headers,
            $body,
            $query,
        );
    }
}
