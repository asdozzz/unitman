<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Model;

use App\OpenCode\Business\Model\SessiyaOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Сессия не хранит секрет: только ссылку на реквизиты Account, поэтому
 * удаление реквизитов не ломает историю диалога.
 */
final class SessiyaOpenCodeTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function novaya_sessiya_nachinaetsya_bez_vneshnego_id(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, self::NOW);

        $this->assertSame('s-1', $sessiya->getId());
        $this->assertSame('acc-1', $sessiya->getAccountId());
        $this->assertSame('cred-1', $sessiya->getCredentialId());
        $this->assertSame('', $sessiya->getTitle());
        $this->assertNull($sessiya->getExternalSessionId());
        $this->assertSame(self::NOW, $sessiya->getCreatedAt());
        $this->assertSame(self::NOW, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function bez_account_sessiya_ne_sozdaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.account_required');

        SessiyaOpenCode::create('s-1', '   ', 'cred-1', null, self::NOW);
    }

    #[Test]
    public function bez_rekvizitov_sessiya_ne_sozdaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.credential_required');

        SessiyaOpenCode::create('s-1', 'acc-1', '', null, self::NOW);
    }

    #[Test]
    public function zagolovok_oberezaetsya_po_granice(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            str_repeat('я', 300),
            self::NOW,
        );

        $this->assertSame(256, mb_strlen($sessiya->getTitle()));
    }

    #[Test]
    public function zagolovok_iz_probelynogo_prompta_ostaetsya_pustym(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', "  \n ", self::NOW);

        $this->assertSame('', $sessiya->getTitle());
    }

    #[Test]
    public function vladelec_opredelyaetsya_po_account(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, self::NOW);

        $this->assertTrue($sessiya->prinadlezhit('acc-1'));
        $this->assertFalse($sessiya->prinadlezhit('acc-2'));
    }

    #[Test]
    public function chuzhaya_sessiya_skryvaetsya_kak_otsutstvuyushchaya(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, self::NOW);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.not_found');

        $sessiya->trebovatVladelca('acc-2');
    }

    #[Test]
    public function svoj_vladelec_propuskaetsya(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, self::NOW);

        $sessiya->trebovatVladelca('acc-1');

        $this->assertSame('acc-1', $sessiya->getAccountId());
    }

    #[Test]
    public function podpis_po_promptu_srabatyvaet_odin_raz(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, self::NOW);

        $sessiya->podpisatPoPromptu('Первый промпт', self::NOW + 10);
        $this->assertSame('Первый промпт', $sessiya->getTitle());
        $this->assertSame(self::NOW + 10, $sessiya->getUpdatedAt());

        $sessiya->podpisatPoPromptu('Второй промпт', self::NOW + 20);
        $this->assertSame('Первый промпт', $sessiya->getTitle());
        $this->assertSame(self::NOW + 10, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function explicitnyi_zagolovok_ostanavlivaet_avtopodpis(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, self::NOW);

        $sessiya->zadatZagolovok('Ручной заголовок', self::NOW + 5);
        $sessiya->podpisatPoPromptu('Промпт', self::NOW + 9);

        $this->assertSame('Ручной заголовок', $sessiya->getTitle());
        $this->assertSame(self::NOW + 5, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function pustoi_zagolovok_ne_menyaet_sostoyanie(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', 'Исходный', self::NOW);

        $sessiya->zadatZagolovok('   ', self::NOW + 5);

        $this->assertSame('Исходный', $sessiya->getTitle());
        $this->assertSame(self::NOW, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function vneshnyaya_sessiya_zapominaetsya_odnazhdy_i_perezaписывается(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, self::NOW);

        $sessiya->zapomnitVneshnyuyuSessiyu('ext-1', self::NOW + 3);
        $this->assertSame('ext-1', $sessiya->getExternalSessionId());
        $this->assertSame(self::NOW + 3, $sessiya->getUpdatedAt());

        $sessiya->zapomnitVneshnyuyuSessiyu('ext-2', self::NOW + 6);
        $this->assertSame('ext-2', $sessiya->getExternalSessionId());
        $this->assertSame(self::NOW + 6, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function otmetit_izmenenie_dvigaet_updated_at(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', 'Заголовок', self::NOW);

        $sessiya->otmetitIzmenenie(self::NOW + 100);

        $this->assertSame(self::NOW + 100, $sessiya->getUpdatedAt());
        $this->assertSame(self::NOW, $sessiya->getCreatedAt());
    }

    #[Test]
    public function restore_vosstanavlivaet_vneshnyuyu_sessiyu(): void
    {
        $sessiya = SessiyaOpenCode::restore('s-1', 'acc-1', 'cred-1', 'Заголовок', 'ext-1', self::NOW, self::NOW + 5);

        $this->assertSame('ext-1', $sessiya->getExternalSessionId());
        $this->assertSame(self::NOW, $sessiya->getCreatedAt());
        $this->assertSame(self::NOW + 5, $sessiya->getUpdatedAt());
    }
}
