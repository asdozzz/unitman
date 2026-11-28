<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Utils\OpredelitelRekvizitovHodaOpenCode;
use App\OpenCode\Tests\Support\FabrikaWorkspace;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Бюджет хода: реквизиты выбираются у автора, а реквизиты сессии — только
 * легаси-фолбэк для владельца.
 */
final class OpredelitelRekvizitovHodaOpenCodeTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function zapros_imeet_prioritet_nad_defaultom_uchastnika(): void
    {
        $resolver = $this->resolver();

        $istochnik = $resolver->opredelit(
            $this->sessiya('owner-1', 'credential-owner'),
            'acc-2',
            $this->uchastnik('acc-2', defaultCredentialId: 'cred-default'),
            'cred-request',
        );

        $this->assertSame('acc-2', $istochnik->accountId);
        $this->assertSame('cred-request', $istochnik->credentialId);
    }

    #[Test]
    public function bez_zaprosa_berutsya_rekvizity_uchastnika(): void
    {
        $resolver = $this->resolver();

        $istochnik = $resolver->opredelit(
            $this->sessiya('owner-1', 'credential-owner'),
            'acc-2',
            $this->uchastnik('acc-2', defaultCredentialId: 'cred-default'),
            null,
        );

        $this->assertSame('cred-default', $istochnik->credentialId);
        $this->assertSame('model-default', $istochnik->modelPoUmolchaniyu);
    }

    #[Test]
    public function bez_defaulta_uchastnik_poluchaet_pervye_svoi_rekvizity(): void
    {
        $credentials = $this->credentials(firstCredentialId: 'cred-first');

        $istochnik = $this->resolver($credentials)->opredelit(
            $this->sessiya('owner-1', 'credential-owner'),
            'acc-2',
            $this->uchastnik('acc-2'),
            null,
        );

        $this->assertSame('cred-first', $istochnik->credentialId);
    }

    #[Test]
    public function uchastnik_ne_beret_rekvizity_sessii(): void
    {
        $credentials = $this->credentials(firstCredentialId: null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.credential_required');

        $this->resolver($credentials)->opredelit(
            $this->sessiya('owner-1', 'credential-owner'),
            'acc-2',
            $this->uchastnik('acc-2'),
            null,
        );
    }

    #[Test]
    public function vladelec_bez_defaulta_beret_rekvizity_sessii(): void
    {
        $istochnik = $this->resolver()->opredelit(
            $this->sessiya('owner-1', 'credential-owner', 'model-session'),
            'owner-1',
            null,
            null,
        );

        $this->assertSame('credential-owner', $istochnik->credentialId);
        $this->assertSame('model-session', $istochnik->modelPoUmolchaniyu);
    }

    #[Test]
    public function model_uchastnika_prioritetnee_modeli_sessii(): void
    {
        $istochnik = $this->resolver()->opredelit(
            $this->sessiya('owner-1', 'credential-owner', 'model-session'),
            'acc-2',
            $this->uchastnik('acc-2', defaultCredentialId: 'cred-default', defaultModel: 'model-uchastnika'),
            null,
        );

        $this->assertSame('model-uchastnika', $istochnik->modelPoUmolchaniyu);
    }

    #[Test]
    public function vladenie_rekvizitami_proveryaetsya_do_starta_hoda(): void
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->expects($this->once())
            ->method('trebovatVladelca')
            ->with('acc-2', 'cred-chuzhoy')
            ->willThrowException(new \DomainException('account.opencode_credential.not_found'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.not_found');

        $this->resolver($credentials)->opredelit(
            $this->sessiya('owner-1', 'credential-owner'),
            'acc-2',
            $this->uchastnik('acc-2'),
            'cred-chuzhoy',
        );
    }

    private function resolver(?UmeetProveryatRekvizityOpenCode $credentials = null): OpredelitelRekvizitovHodaOpenCode
    {
        return new OpredelitelRekvizitovHodaOpenCode($credentials ?? $this->credentials());
    }

    private function credentials(?string $firstCredentialId = 'cred-first'): UmeetProveryatRekvizityOpenCode
    {
        $credentials = $this->createMock(UmeetProveryatRekvizityOpenCode::class);
        $credentials->method('poluchitPervieRekvizityAvtora')->willReturn($firstCredentialId);
        $credentials->method('trebovatVladelca')->willReturnCallback(
            static function (string $accountId, string $credentialId): void {
                if (str_starts_with($credentialId, 'cred-chuzhoy')) {
                    throw new \DomainException('account.opencode_credential.not_found');
                }
            }
        );

        return $credentials;
    }

    private function sessiya(
        string $accountId,
        string $credentialId,
        ?string $model = null,
    ): SessiyaOpenCode {
        return SessiyaOpenCode::createWorkspace(
            'workspace-1',
            $accountId,
            $credentialId,
            'payments-refactor',
            'main',
            [FabrikaWorkspace::proekt()],
            'Первый промпт',
            self::NOW,
            $model,
        );
    }

    private function uchastnik(
        string $accountId,
        ?string $defaultCredentialId = null,
        ?string $defaultModel = null,
    ): UchastnikSessii {
        return UchastnikSessii::create(
            'workspace-1',
            $accountId,
            RolUchastnikaSessii::WRITER,
            $defaultCredentialId,
            $defaultModel ?? 'model-default',
            'owner-1',
            self::NOW,
        );
    }
}
