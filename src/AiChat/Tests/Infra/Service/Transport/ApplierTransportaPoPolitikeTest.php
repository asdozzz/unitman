<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Infra\Service\Transport;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;
use App\AiChat\Infra\Service\Transport\ApplierTransportaPoPolitike;
use App\AiChat\Infra\Service\Transport\BodySessionTransport;
use App\AiChat\Infra\Service\Transport\HeaderSessionTransport;
use App\AiChat\Infra\Service\Transport\NoSessionTransport;
use App\AiChat\Infra\Service\Transport\QuerySessionTransport;
use App\AiChat\Infra\Service\Transport\UnknownSessionTransport;
use PHPUnit\Framework\TestCase;

/**
 * Резолвер покрывает все пять значений enum транспорта без `default`.
 */
final class ApplierTransportaPoPolitikeTest extends TestCase
{
    public function test_none_daet_no_session_transport(): void
    {
        self::assertInstanceOf(
            NoSessionTransport::class,
            (new ApplierTransportaPoPolitike())->poluchit(ProviderSessionPolicy::none(300)),
        );
    }

    public function test_header_daet_header_session_transport(): void
    {
        self::assertInstanceOf(
            HeaderSessionTransport::class,
            (new ApplierTransportaPoPolitike())->poluchit(
                ProviderSessionPolicy::header('x-opencode-session', 300),
            ),
        );
    }

    public function test_body_daet_body_session_transport(): void
    {
        self::assertInstanceOf(
            BodySessionTransport::class,
            (new ApplierTransportaPoPolitike())->poluchit(
                ProviderSessionPolicy::body('session_id', 300),
            ),
        );
    }

    public function test_query_daet_query_session_transport(): void
    {
        self::assertInstanceOf(
            QuerySessionTransport::class,
            (new ApplierTransportaPoPolitike())->poluchit(
                ProviderSessionPolicy::query('session', 300),
            ),
        );
    }

    public function test_unknown_daet_unknown_session_transport(): void
    {
        self::assertInstanceOf(
            UnknownSessionTransport::class,
            (new ApplierTransportaPoPolitike())->poluchit(ProviderSessionPolicy::unknown()),
        );
    }
}
