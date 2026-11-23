<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Utils\OpredelitelModeliHodaOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Приоритет модели хода: запрос -> модель сессии -> дефолт реквизитов.
 *
 * Резолвер не хранит собственных значений: единственный источник истины
 * о допустимых моделях — порт реквизитов.
 */
final class OpredelitelModeliHodaOpenCodeTest extends TestCase
{
    #[Test]
    public function model_iz_zaprosa_perekryvaet_model_sessii(): void
    {
        $poluchennayaModel = null;

        $opredelitel = $this->opredelitel(
            static function (string $accountId, string $credentialId, ?string $preferred) use (&$poluchennayaModel): string {
                $poluchennayaModel = $preferred;

                return (string) $preferred;
            },
        );

        $result = $opredelitel->opredelit('acc-1', 'cred-1', 'request-model', 'session-model');

        self::assertSame('request-model', $result);
        self::assertSame('request-model', $poluchennayaModel);
    }

    #[Test]
    public function pri_pustom_zaprose_beretsya_model_sessii(): void
    {
        $poluchennayaModel = null;

        $opredelitel = $this->opredelitel(
            static function (string $accountId, string $credentialId, ?string $preferred) use (&$poluchennayaModel): string {
                $poluchennayaModel = $preferred;

                return (string) $preferred;
            },
        );

        $opredelitel->opredelit('acc-1', 'cred-1', '', 'session-model');

        self::assertSame('session-model', $poluchennayaModel);
    }

    #[Test]
    public function bez_zaprosa_i_bez_modeli_sessii_peredayotsya_null_dlya_defaulta(): void
    {
        $poluchennayaModel = 'sentinel';

        $opredelitel = $this->opredelitel(
            static function (string $accountId, string $credentialId, ?string $preferred) use (&$poluchennayaModel): string {
                $poluchennayaModel = $preferred;

                return 'default-model';
            },
        );

        $result = $opredelitel->opredelit('acc-1', 'cred-1', null, null);

        self::assertNull($poluchennayaModel);
        self::assertSame('default-model', $result);
    }

    #[Test]
    public function nedopustimaya_model_probrasyvaet_oshibku_rekvizitov(): void
    {
        $opredelitel = $this->opredelitel(
            static function (string $accountId, string $credentialId, ?string $preferred): string {
                if ($preferred === 'unknown-model') {
                    throw new \DomainException('account.opencode_credential.model_not_available');
                }

                return (string) $preferred;
            },
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.model_not_available');

        $opredelitel->opredelit('acc-1', 'cred-1', 'unknown-model', 'session-model');
    }

    /**
     * @param callable(string, string, ?string): string $razreshit
     */
    private function opredelitel(callable $razreshit): OpredelitelModeliHodaOpenCode
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('razreshitModel')->willReturnCallback($razreshit);

        return new OpredelitelModeliHodaOpenCode($credentials);
    }
}
