<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\LichniyToken;

use App\Unitman\Business\Model\LichniyToken;
use App\Unitman\Business\Model\LichniyToken\ObyomPrav;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LichniyTokenTest extends TestCase
{
    #[Test]
    public function token_s_vse_pokryvaet_lyuboy_proekt(): void
    {
        $token = $this->token(ObyomPrav::Vse, []);

        self::assertTrue($token->pokryvaetProekt('project-1'));
        self::assertTrue($token->pokryvaetProekt('project-any'));
        self::assertSame('****1234', $token->getTokenHint());
        self::assertSame(ObyomPrav::Vse, $token->getObyomPrav());
    }

    #[Test]
    public function token_so_spiskom_pokryvaet_tolko_ukazannie_proekty(): void
    {
        $token = $this->token(ObyomPrav::Spisok, ['project-1', 'project-1', 'project-2']);

        self::assertTrue($token->pokryvaetProekt('project-1'));
        self::assertTrue($token->pokryvaetProekt('project-2'));
        self::assertFalse($token->pokryvaetProekt('project-3'));
        self::assertSame(['project-1', 'project-2'], $token->getProektySPushom());
    }

    #[Test]
    public function udalenie_idempotentno_i_zapreshchaet_dostup(): void
    {
        $token = $this->token(ObyomPrav::Vse, []);

        $token->udalit();
        $token->udalit();

        self::assertTrue($token->isDeleted());
        self::assertFalse($token->pokryvaetProekt('project-1'));
    }

    #[Test]
    public function obnovlenie_prav_menyaet_snimok(): void
    {
        $token = $this->token(ObyomPrav::Vse, []);

        $token->obnovitPrava(ObyomPrav::Spisok, ['project-2'], 2000);

        self::assertSame(ObyomPrav::Spisok, $token->getObyomPrav());
        self::assertSame(['project-2'], $token->getProektySPushom());
        self::assertSame(2000, $token->getProverenoV());
        self::assertFalse($token->pokryvaetProekt('project-1'));
        self::assertTrue($token->pokryvaetProekt('project-2'));
    }

    #[Test]
    public function chuzhoy_vladelec_otklonyaetsya(): void
    {
        $token = $this->token(ObyomPrav::Vse, []);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('lichniy_token.chuzhoy');

        $token->trebovatVladelca('other-account');
    }

    private function token(ObyomPrav $obyomPrav, array $proekty): LichniyToken
    {
        return LichniyToken::dobavit(
            'token-1',
            'account-1',
            'repo-1',
            'Мой токен',
            'cipher-blob',
            LichniyToken::makeHint('secret1234'),
            $obyomPrav,
            $proekty,
            1000,
        );
    }
}
