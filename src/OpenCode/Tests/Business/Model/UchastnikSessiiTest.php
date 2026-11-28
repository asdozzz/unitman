<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Model;

use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Участник workspace-сессии: роль и личные реквизиты ходов.
 *
 * Владелец не понижается и не удаляется обычным редактированием — иначе
 * сессия осталась бы без управляющего. Реквизиты участника влияют только на
 * его ходы: бюджет всегда списывается с автора.
 */
final class UchastnikSessiiTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function vladelec_sozdaetsya_bez_rekvizitov_po_umolchaniyu(): void
    {
        $uchastnik = $this->uchastnik(RolUchastnikaSessii::OWNER);

        $this->assertSame('s-1', $uchastnik->getSessionId());
        $this->assertSame('acc-1', $uchastnik->getAccountId());
        $this->assertSame(RolUchastnikaSessii::OWNER, $uchastnik->getRol());
        $this->assertNull($uchastnik->getDefaultCredentialId());
        $this->assertNull($uchastnik->getDefaultModel());
        $this->assertNull($uchastnik->getInvitedBy());
        $this->assertSame(self::NOW, $uchastnik->getCreatedAt());
        $this->assertSame(self::NOW, $uchastnik->getUpdatedAt());
        $this->assertTrue($uchastnik->estVladelec());
        $this->assertTrue($uchastnik->mozhetPisat());
        $this->assertTrue($uchastnik->mozhetUpravlyat());
    }

    #[Test]
    public function bez_session_id_ne_sozdaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.session_required');

        UchastnikSessii::create('  ', 'acc-1', RolUchastnikaSessii::VIEWER, null, null, null, self::NOW);
    }

    #[Test]
    public function bez_account_id_ne_sozdaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.account_required');

        UchastnikSessii::create('s-1', '  ', RolUchastnikaSessii::VIEWER, null, null, null, self::NOW);
    }

    #[Test]
    public function slishkom_dlinniy_account_id_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.account_too_long');

        UchastnikSessii::create(
            's-1',
            str_repeat('a', 129),
            RolUchastnikaSessii::VIEWER,
            null,
            null,
            null,
            self::NOW,
        );
    }

    #[Test]
    public function pustye_rekvizity_stanovyatsya_null_i_obrezayutsya(): void
    {
        $uchastnik = UchastnikSessii::create(
            ' s-1 ',
            ' acc-1 ',
            RolUchastnikaSessii::WRITER,
            ' cred-1 ',
            ' gpt-4o ',
            ' owner-1 ',
            self::NOW,
        );

        $this->assertSame('s-1', $uchastnik->getSessionId());
        $this->assertSame('acc-1', $uchastnik->getAccountId());
        $this->assertSame('cred-1', $uchastnik->getDefaultCredentialId());
        $this->assertSame('gpt-4o', $uchastnik->getDefaultModel());
        $this->assertSame('owner-1', $uchastnik->getInvitedBy());

        $pustoi = UchastnikSessii::create(
            's-1',
            'acc-2',
            RolUchastnikaSessii::VIEWER,
            '   ',
            '',
            '   ',
            self::NOW,
        );

        $this->assertNull($pustoi->getDefaultCredentialId());
        $this->assertNull($pustoi->getDefaultModel());
        $this->assertNull($pustoi->getInvitedBy());
    }

    #[Test]
    public function slishkom_dlinniy_credential_id_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.credential_too_long');

        UchastnikSessii::create(
            's-1',
            'acc-1',
            RolUchastnikaSessii::WRITER,
            str_repeat('c', 65),
            null,
            null,
            self::NOW,
        );
    }

    #[Test]
    public function slishkom_dlinnaya_model_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.model_too_long');

        UchastnikSessii::create(
            's-1',
            'acc-1',
            RolUchastnikaSessii::WRITER,
            null,
            str_repeat('m', 129),
            null,
            self::NOW,
        );
    }

    #[Test]
    public function vladelca_nelzya_ponizit(): void
    {
        $uchastnik = $this->uchastnik(RolUchastnikaSessii::OWNER);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.owner_role_immutable');

        $uchastnik->izmenitRol(RolUchastnikaSessii::WRITER, self::NOW + 1);
    }

    #[Test]
    public function nikogo_nelzya_naznachit_vladelcem_cherez_redaktirovanie(): void
    {
        $uchastnik = $this->uchastnik(RolUchastnikaSessii::WRITER);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.owner_role_immutable');

        $uchastnik->izmenitRol(RolUchastnikaSessii::OWNER, self::NOW + 1);
    }

    #[Test]
    public function writer_menyaet_rol_na_viewer_i_obnovlyaet_vremya(): void
    {
        $uchastnik = $this->uchastnik(RolUchastnikaSessii::WRITER);

        $uchastnik->izmenitRol(RolUchastnikaSessii::VIEWER, self::NOW + 5);

        $this->assertSame(RolUchastnikaSessii::VIEWER, $uchastnik->getRol());
        $this->assertFalse($uchastnik->mozhetPisat());
        $this->assertSame(self::NOW + 5, $uchastnik->getUpdatedAt());
    }

    #[Test]
    public function povtornaya_ustanovka_toy_zhe_roli_nichego_ne_menyaet(): void
    {
        $uchastnik = $this->uchastnik(RolUchastnikaSessii::WRITER);

        $uchastnik->izmenitRol(RolUchastnikaSessii::WRITER, self::NOW + 5);

        $this->assertSame(self::NOW, $uchastnik->getUpdatedAt());
    }

    #[Test]
    public function rekvizity_uchastnika_menyayutsya_i_normalizuyutsya(): void
    {
        $uchastnik = $this->uchastnik(RolUchastnikaSessii::WRITER);

        $uchastnik->izmenitRekvizity(' cred-2 ', ' gpt-4o-mini ', self::NOW + 7);

        $this->assertSame('cred-2', $uchastnik->getDefaultCredentialId());
        $this->assertSame('gpt-4o-mini', $uchastnik->getDefaultModel());
        $this->assertSame(self::NOW + 7, $uchastnik->getUpdatedAt());

        $uchastnik->izmenitRekvizity('  ', null, self::NOW + 8);

        $this->assertNull($uchastnik->getDefaultCredentialId());
        $this->assertNull($uchastnik->getDefaultModel());
    }

    #[Test]
    public function vladelca_nelzya_udalit(): void
    {
        $vladelec = $this->uchastnik(RolUchastnikaSessii::OWNER);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.owner_cannot_be_removed');

        $vladelec->trebovatMozhnoUdalit();
    }

    #[Test]
    public function writer_i_viewer_udalit_mozhno(): void
    {
        $this->uchastnik(RolUchastnikaSessii::WRITER)->trebovatMozhnoUdalit();
        $this->uchastnik(RolUchastnikaSessii::VIEWER)->trebovatMozhnoUdalit();

        $this->addToAssertionCount(2);
    }

    #[Test]
    public function restore_vosstanavlivaet_vse_polya(): void
    {
        $uchastnik = UchastnikSessii::restore(
            's-1',
            'acc-1',
            RolUchastnikaSessii::VIEWER,
            'cred-1',
            'gpt-4o',
            'owner-1',
            self::NOW - 10,
            self::NOW - 5,
        );

        $this->assertSame('s-1', $uchastnik->getSessionId());
        $this->assertSame('acc-1', $uchastnik->getAccountId());
        $this->assertSame(RolUchastnikaSessii::VIEWER, $uchastnik->getRol());
        $this->assertSame('cred-1', $uchastnik->getDefaultCredentialId());
        $this->assertSame('gpt-4o', $uchastnik->getDefaultModel());
        $this->assertSame('owner-1', $uchastnik->getInvitedBy());
        $this->assertSame(self::NOW - 10, $uchastnik->getCreatedAt());
        $this->assertSame(self::NOW - 5, $uchastnik->getUpdatedAt());
        $this->assertFalse($uchastnik->mozhetPisat());
        $this->assertFalse($uchastnik->mozhetUpravlyat());
    }

    #[Test]
    public function ierarhiya_rolej_strogaya(): void
    {
        $owner = RolUchastnikaSessii::OWNER;
        $writer = RolUchastnikaSessii::WRITER;
        $viewer = RolUchastnikaSessii::VIEWER;

        $this->assertTrue($owner->dostatochnoDlya($owner));
        $this->assertTrue($owner->dostatochnoDlya($writer));
        $this->assertTrue($owner->dostatochnoDlya($viewer));

        $this->assertFalse($writer->dostatochnoDlya($owner));
        $this->assertTrue($writer->dostatochnoDlya($writer));
        $this->assertTrue($writer->dostatochnoDlya($viewer));

        $this->assertFalse($viewer->dostatochnoDlya($owner));
        $this->assertFalse($viewer->dostatochnoDlya($writer));
        $this->assertTrue($viewer->dostatochnoDlya($viewer));

        $this->assertFalse($viewer->mozhetPisat());
        $this->assertFalse($viewer->mozhetUpravlyat());
        $this->assertTrue($writer->mozhetPisat());
        $this->assertFalse($writer->mozhetUpravlyat());
    }

    #[Test]
    public function neizvestnaya_rol_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.participant.invalid_role');

        RolUchastnikaSessii::izStroki('superadmin');
    }

    private function uchastnik(RolUchastnikaSessii $rol): UchastnikSessii
    {
        return UchastnikSessii::create('s-1', 'acc-1', $rol, null, null, null, self::NOW);
    }
}
