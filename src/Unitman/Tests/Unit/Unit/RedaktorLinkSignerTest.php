<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Infra\Adapter\HmacRedaktorLinkSigner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * HMAC-подпись персональной ссылки редактора.
 */
final class RedaktorLinkSignerTest extends TestCase
{
    private const SECRET = 'test-secret';

    #[Test]
    public function podpisannaya_ssylka_prohodit_proverku(): void
    {
        $signer = $this->signer();
        $ssylka = $signer->podpisat('unit-1', 'user-1', 'edit', 3600);

        $payload = $signer->proverit($ssylka);

        self::assertSame('unit-1', $payload['unitId']);
        self::assertSame('user-1', $payload['userId']);
        self::assertSame('edit', $payload['mode']);
        self::assertGreaterThan(time(), $payload['exp']);
    }

    #[Test]
    public function podmenennaya_podpis_otklonyaetsya(): void
    {
        $signer = $this->signer();
        $ssylka = $signer->podpisat('unit-1', 'user-1', 'view', 3600);
        $tampered = $ssylka . 'x';

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.ssylka_nevalidna');

        $signer->proverit($tampered);
    }

    #[Test]
    public function podmenennii_payload_otklonyaetsya(): void
    {
        $signer = $this->signer();
        $ssylka = $signer->podpisat('unit-1', 'user-1', 'view', 3600);
        [$payload, $signature] = explode('.', $ssylka);
        $decoded = json_decode($this->decode($payload), true, 512, JSON_THROW_ON_ERROR);
        $decoded['mode'] = 'edit';
        $tamperedPayload = $this->encode((string) json_encode($decoded, JSON_THROW_ON_ERROR));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.ssylka_nevalidna');

        $signer->proverit($tamperedPayload . '.' . $signature);
    }

    #[Test]
    public function istekshaya_ssylka_otklonyaetsya(): void
    {
        $signer = $this->signer();
        $payload = $this->encode((string) json_encode([
            'unitId' => 'unit-1',
            'userId' => 'user-1',
            'mode' => 'view',
            'exp' => time() - 10,
        ], JSON_THROW_ON_ERROR));
        $signature = $this->encode(hash_hmac('sha256', $payload, self::SECRET, true));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.ssylka_istekla');

        $signer->proverit($payload . '.' . $signature);
    }

    #[Test]
    public function neizvestnii_rezhim_otklonyaetsya_pri_podpisi(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.ssylka_nevalidna');

        $this->signer()->podpisat('unit-1', 'user-1', 'admin', 600);
    }

    private function signer(): HmacRedaktorLinkSigner
    {
        return new HmacRedaktorLinkSigner(self::SECRET);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
