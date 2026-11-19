<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Jobs\SobitiyaIzHranilisha;

use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\SobitiePayloadDecoder;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\SobitiePayloadException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Границы и ошибки единственного декодирования тела вебхука.
 *
 * Декодер — единственное место, где вызывается `json_decode`, поэтому именно
 * здесь проверяются лимит размера (до разбора) и стабильные коды ошибок.
 */
final class SobitiePayloadDecoderTest extends TestCase
{
    private const JSON_PREFIX = '{"padding":"';
    private const JSON_SUFFIX = '"}';

    #[Test]
    public function payload_rovno_na_granice_limita_dekodiruetsya(): void
    {
        $raw = $this->payloadRazmera(SobitiePayloadDecoder::MAX_EVENT_PAYLOAD_BYTES);

        self::assertSame(SobitiePayloadDecoder::MAX_EVENT_PAYLOAD_BYTES, strlen($raw));

        $payload = (new SobitiePayloadDecoder())->decode($raw);

        self::assertSame($raw, $payload->raw);
        self::assertSame(
            SobitiePayloadDecoder::MAX_EVENT_PAYLOAD_BYTES - strlen(self::JSON_PREFIX) - strlen(self::JSON_SUFFIX),
            strlen((string) $payload->data['padding']),
        );
    }

    #[Test]
    public function payload_na_bait_bolshe_limita_otklonyaetsya(): void
    {
        $raw = $this->payloadRazmera(SobitiePayloadDecoder::MAX_EVENT_PAYLOAD_BYTES + 1);

        self::assertSame(SobitiePayloadDecoder::MAX_EVENT_PAYLOAD_BYTES + 1, strlen($raw));

        $this->expectException(SobitiePayloadException::class);
        $this->expectExceptionMessage(SobitiePayloadDecoder::ERROR_TOO_LARGE);

        (new SobitiePayloadDecoder())->decode($raw);
    }

    #[Test]
    public function bityi_json_otklonyaetsya_stabilnim_kodom(): void
    {
        $this->expectException(SobitiePayloadException::class);
        $this->expectExceptionMessage(SobitiePayloadDecoder::ERROR_MALFORMED);

        (new SobitiePayloadDecoder())->decode('{"ref":');
    }

    #[Test]
    public function json_ne_massiv_otklonyaetsya_stabilnim_kodom(): void
    {
        $this->expectException(SobitiePayloadException::class);
        $this->expectExceptionMessage(SobitiePayloadDecoder::ERROR_MALFORMED);

        (new SobitiePayloadDecoder())->decode('"refs/heads/feature/123"');
    }

    #[Test]
    public function validnoe_push_sobitie_dekodiruetsya_v_massiv(): void
    {
        $payload = (new SobitiePayloadDecoder())->decode(
            '{"event_name":"push","ref":"refs/heads/feature/123","after":"bf2c3d4e"}'
        );

        self::assertSame('push', $payload->data['event_name']);
        self::assertSame('refs/heads/feature/123', $payload->data['ref']);
        self::assertSame('bf2c3d4e', $payload->data['after']);
    }

    /**
     * Собирает валидный JSON ровно заданного размера в байтах.
     */
    private function payloadRazmera(int $bytes): string
    {
        $padding = $bytes - strlen(self::JSON_PREFIX) - strlen(self::JSON_SUFFIX);

        self::assertGreaterThan(0, $padding);

        return self::JSON_PREFIX . str_repeat('a', $padding) . self::JSON_SUFFIX;
    }
}
