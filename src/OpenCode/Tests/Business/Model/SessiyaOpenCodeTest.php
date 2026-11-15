<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Model;

use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Сессия не хранит секрет: только ссылку на реквизиты Account, поэтому
 * удаление реквизитов не ломает историю диалога.
 *
 * Юнитов может быть несколько: порядок привязки значим, потому что первый
 * юнит — рабочий каталог хода (cwd), остальные — дополнительные корни
 * workspace. Повторная привязка того же юнита идемпотентна, а архивация
 * запрещает новые ходы.
 */
final class SessiyaOpenCodeTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private const UNIT_ID = 'unit-1';

    private const UNIT_PROJECT_ID = 'project-1';

    #[Test]
    public function novaya_sessiya_nachinaetsya_bez_vneshnego_id(): void
    {
        $sessiya = self::svobodnaya();

        $this->assertSame('s-1', $sessiya->getId());
        $this->assertSame('acc-1', $sessiya->getAccountId());
        $this->assertSame('cred-1', $sessiya->getCredentialId());
        $this->assertSame('', $sessiya->getTitle());
        $this->assertNull($sessiya->getExternalSessionId());
        $this->assertNull($sessiya->getUnitId());
        $this->assertNull($sessiya->getUnitProjectId());
        $this->assertSame([], $sessiya->getUnits());
        $this->assertSame([], $sessiya->getUnitIds());
        $this->assertNull($sessiya->getPervyiUnit());
        $this->assertNull($sessiya->getArchivedAt());
        $this->assertNull($sessiya->getArchiveReason());
        $this->assertFalse($sessiya->estArhiv());
        $this->assertFalse($sessiya->privezanaKUnitu());
        $this->assertSame(self::NOW, $sessiya->getCreatedAt());
        $this->assertSame(self::NOW, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function bez_account_sessiya_ne_sozdaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.account_required');

        SessiyaOpenCode::create('s-1', '   ', 'cred-1', null, [], self::NOW);
    }

    #[Test]
    public function bez_rekvizitov_sessiya_ne_sozdaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.credential_required');

        SessiyaOpenCode::create('s-1', 'acc-1', '', null, [], self::NOW);
    }

    #[Test]
    public function zagolovok_oberezaetsya_po_granice(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            str_repeat('я', 300),
            [],
            self::NOW,
        );

        $this->assertSame(256, mb_strlen($sessiya->getTitle()));
    }

    #[Test]
    public function zagolovok_iz_probelynogo_prompta_ostaetsya_pustym(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', "  \n ", [], self::NOW);

        $this->assertSame('', $sessiya->getTitle());
    }

    #[Test]
    public function vladelec_opredelyaetsya_po_account(): void
    {
        $sessiya = self::svobodnaya();

        $this->assertTrue($sessiya->prinadlezhit('acc-1'));
        $this->assertFalse($sessiya->prinadlezhit('acc-2'));
    }

    #[Test]
    public function chuzhaya_sessiya_skryvaetsya_kak_otsutstvuyushchaya(): void
    {
        $sessiya = self::svobodnaya();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.not_found');

        $sessiya->trebovatVladelca('acc-2');
    }

    #[Test]
    public function svoj_vladelec_propuskaetsya(): void
    {
        $sessiya = self::svobodnaya();

        $sessiya->trebovatVladelca('acc-1');

        $this->assertSame('acc-1', $sessiya->getAccountId());
    }

    #[Test]
    public function podpis_po_promptu_srabatyvaet_odin_raz(): void
    {
        $sessiya = self::svobodnaya();

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
        $sessiya = self::svobodnaya();

        $sessiya->zadatZagolovok('Ручной заголовок', self::NOW + 5);
        $sessiya->podpisatPoPromptu('Промпт', self::NOW + 9);

        $this->assertSame('Ручной заголовок', $sessiya->getTitle());
        $this->assertSame(self::NOW + 5, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function pustoi_zagolovok_ne_menyaet_sostoyanie(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', 'Исходный', [], self::NOW);

        $sessiya->zadatZagolovok('   ', self::NOW + 5);

        $this->assertSame('Исходный', $sessiya->getTitle());
        $this->assertSame(self::NOW, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function vneshnyaya_sessiya_zapominaetsya_odnazhdy_i_perezaписывается(): void
    {
        $sessiya = self::svobodnaya();

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
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', 'Заголовок', [], self::NOW);

        $sessiya->otmetitIzmenenie(self::NOW + 100);

        $this->assertSame(self::NOW + 100, $sessiya->getUpdatedAt());
        $this->assertSame(self::NOW, $sessiya->getCreatedAt());
    }

    #[Test]
    public function restore_vosstanavlivaet_vneshnyuyu_sessiyu(): void
    {
        $sessiya = SessiyaOpenCode::restore(
            's-1',
            'acc-1',
            'cred-1',
            'Заголовок',
            'ext-1',
            [],
            null,
            null,
            self::NOW,
            self::NOW + 5,
        );

        $this->assertSame('ext-1', $sessiya->getExternalSessionId());
        $this->assertSame(self::NOW, $sessiya->getCreatedAt());
        $this->assertSame(self::NOW + 5, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function sessiya_sozdaetsya_srazu_privezannoj_k_unitu(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            null,
            [$this->privyazka(self::UNIT_ID, self::UNIT_PROJECT_ID)],
            self::NOW,
        );

        $this->assertSame(self::UNIT_ID, $sessiya->getUnitId());
        $this->assertSame(self::UNIT_PROJECT_ID, $sessiya->getUnitProjectId());
        $this->assertSame([self::UNIT_ID], $sessiya->getUnitIds());
        $this->assertTrue($sessiya->privezanaKUnitu());
        $this->assertTrue($sessiya->privezanaKUnituSId(self::UNIT_ID));
        $this->assertFalse($sessiya->privezanaKUnituSId('unit-2'));
    }

    #[Test]
    public function pystoj_spisok_unitov_daet_svobodnuyu_sessiyu(): void
    {
        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, [], self::NOW);

        $this->assertNull($sessiya->getUnitId());
        $this->assertNull($sessiya->getUnitProjectId());
        $this->assertFalse($sessiya->privezanaKUnitu());
    }

    #[Test]
    public function neskolko_unitov_sohranyayut_poryadok_privezki(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            null,
            [
                $this->privyazka('unit-a', 'project-a'),
                $this->privyazka('unit-b', 'project-b'),
                $this->privyazka('unit-c', 'project-c'),
            ],
            self::NOW,
        );

        $this->assertSame(['unit-a', 'unit-b', 'unit-c'], $sessiya->getUnitIds());
        $this->assertSame('unit-a', $sessiya->getPervyiUnit()?->unitId);
        // Первый юнит — cwd, поэтому «старые» аксессоры обязаны возвращать именно его.
        $this->assertSame('unit-a', $sessiya->getUnitId());
        $this->assertSame('project-a', $sessiya->getUnitProjectId());
    }

    #[Test]
    public function dublikaty_unitov_ubirautsya_pri_sozdanii(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            null,
            [
                $this->privyazka('unit-a', 'project-a'),
                $this->privyazka('unit-b', 'project-b'),
                $this->privyazka('unit-a', 'project-a'),
            ],
            self::NOW,
        );

        $this->assertSame(['unit-a', 'unit-b'], $sessiya->getUnitIds());
    }

    #[Test]
    public function prevyshenie_limita_unitov_otklonyaetsya(): void
    {
        $units = [];
        for ($i = 1; $i <= SessiyaOpenCode::MAX_UNITS + 1; ++$i) {
            $units[] = $this->privyazka('unit-'.$i, 'project-'.$i);
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.too_many_units');

        SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, $units, self::NOW);
    }

    #[Test]
    public function unit_bez_proekta_ne_prinimaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.unit_project_required');

        PrivyazkaKUnitu::izZnacheniy(self::UNIT_ID, null);
    }

    #[Test]
    public function privezat_k_unitu_zapolnyaet_snimok_proekta(): void
    {
        $sessiya = self::svobodnaya();

        $sessiya->privezatKUnitu(self::UNIT_ID, self::UNIT_PROJECT_ID, self::NOW + 10);

        $this->assertSame(self::UNIT_ID, $sessiya->getUnitId());
        $this->assertSame(self::UNIT_PROJECT_ID, $sessiya->getUnitProjectId());
        $this->assertSame(self::NOW + 10, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function privezatKUnitam_dobavlyaet_spisok_v_odnom_hode(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            null,
            [$this->privyazka('unit-a', 'project-a')],
            self::NOW,
        );

        $sessiya->privezatKUnitam(
            [
                $this->privyazka('unit-b', 'project-b'),
                $this->privyazka('unit-c', 'project-c'),
            ],
            self::NOW + 10,
        );

        $this->assertSame(['unit-a', 'unit-b', 'unit-c'], $sessiya->getUnitIds());
        $this->assertSame('unit-a', $sessiya->getUnitId());
        $this->assertSame(self::NOW + 10, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function privezatKUnitam_ne_dvigaet_updated_at_bez_novyh_unitov(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            null,
            [$this->privyazka('unit-a', 'project-a')],
            self::NOW,
        );

        $sessiya->privezatKUnitam([$this->privyazka('unit-a', 'project-a')], self::NOW + 10);

        $this->assertSame(self::NOW, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function povtornaya_privezka_togo_zhe_unita_idempotentna(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            null,
            [$this->privyazka(self::UNIT_ID, self::UNIT_PROJECT_ID)],
            self::NOW,
        );

        $sessiya->privezatKUnitu(self::UNIT_ID, self::UNIT_PROJECT_ID, self::NOW + 10);

        $this->assertSame([self::UNIT_ID], $sessiya->getUnitIds());
        $this->assertSame(self::NOW, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function privezanaKProektu_otvechaet_po_sostavu_sessii(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            null,
            [$this->privyazka('unit-a', 'project-a'), $this->privyazka('unit-b', 'project-b')],
            self::NOW,
        );

        $this->assertTrue($sessiya->privezanaKProektu('project-a'));
        $this->assertTrue($sessiya->privezanaKProektu('project-b'));
        $this->assertFalse($sessiya->privezanaKProektu('project-c'));
    }

    #[Test]
    public function vtoroj_unit_togo_zhe_proekta_ne_privyazyvaetsya(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            null,
            [$this->privyazka('unit-a', 'project-a')],
            self::NOW,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.unit_project_conflict');

        $sessiya->privezatKUnitu('unit-b', 'project-a', self::NOW + 10);
    }

    #[Test]
    public function privezka_spiska_s_konfliktom_proektov_ne_menyaet_sostav(): void
    {
        $sessiya = SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            null,
            [$this->privyazka('unit-a', 'project-a')],
            self::NOW,
        );

        try {
            $sessiya->privezatKUnitam(
                [
                    $this->privyazka('unit-b', 'project-b'),
                    $this->privyazka('unit-c', 'project-a'),
                ],
                self::NOW + 10,
            );
            $this->fail('Ожидалось исключение unit_project_conflict');
        } catch (\DomainException $e) {
            $this->assertSame('opencode.session.unit_project_conflict', $e->getMessage());
        }

        $this->assertSame(['unit-a'], $sessiya->getUnitIds(), 'Привязка списка атомарна');
        $this->assertSame(self::NOW, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function dva_unita_odnogo_proekta_v_odnom_spiske_privyazki_otklonyayutsya(): void
    {
        $sessiya = self::svobodnaya();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.unit_project_conflict');

        $sessiya->privezatKUnitam(
            [
                $this->privyazka('unit-a', 'project-a'),
                $this->privyazka('unit-b', 'project-a'),
            ],
            self::NOW + 10,
        );
    }

    #[Test]
    public function sozdanie_sessii_s_dvumya_unitami_odnogo_proekta_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.unit_project_conflict');

        SessiyaOpenCode::create(
            's-1',
            'acc-1',
            'cred-1',
            null,
            [
                $this->privyazka('unit-a', 'project-a'),
                $this->privyazka('unit-b', 'project-a'),
            ],
            self::NOW,
        );
    }

    #[Test]
    public function vosstanovlenie_otbrasyvaet_povtor_proekta(): void
    {
        $sessiya = SessiyaOpenCode::restore(
            's-1',
            'acc-1',
            'cred-1',
            '',
            null,
            [
                $this->privyazka('unit-a', 'project-a'),
                $this->privyazka('unit-b', 'project-a'),
                $this->privyazka('unit-c', 'project-c'),
            ],
            null,
            null,
            self::NOW,
            self::NOW,
        );

        $this->assertSame(
            ['unit-a', 'unit-c'],
            $sessiya->getUnitIds(),
            'Легаси-данные с дублем проекта остаются читаемыми',
        );
    }

    #[Test]
    public function privezka_prevyshayushchaya_limit_otklonyaetsya(): void
    {
        $units = [];
        for ($i = 1; $i <= SessiyaOpenCode::MAX_UNITS; ++$i) {
            $units[] = $this->privyazka('unit-'.$i, 'project-'.$i);
        }

        $sessiya = SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, $units, self::NOW);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.too_many_units');

        $sessiya->privezatKUnitu('unit-extra', 'project-extra', self::NOW + 10);
    }

    #[Test]
    public function privezka_bez_unit_id_otklonyaetsya(): void
    {
        $sessiya = self::svobodnaya();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.unit_required');

        $sessiya->privezatKUnitu('   ', self::UNIT_PROJECT_ID, self::NOW + 10);
    }

    #[Test]
    public function arhivnaya_sessiya_ne_privezyvaetsya(): void
    {
        $sessiya = self::svobodnaya();
        $sessiya->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_MANUAL, self::NOW + 1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.archived');

        $sessiya->privezatKUnitu(self::UNIT_ID, self::UNIT_PROJECT_ID, self::NOW + 10);
    }

    #[Test]
    public function arhivnaya_sessiya_ne_privezyvaetsya_k_spisku(): void
    {
        $sessiya = self::svobodnaya();
        $sessiya->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_MANUAL, self::NOW + 1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.archived');

        $sessiya->privezatKUnitam([$this->privyazka(self::UNIT_ID, self::UNIT_PROJECT_ID)], self::NOW + 10);
    }

    #[Test]
    public function arhivaciya_idempotentna(): void
    {
        $sessiya = self::svobodnaya();

        $sessiya->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_MANUAL, self::NOW + 10);
        $sessiya->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_UNIT_DELETED, self::NOW + 20);

        $this->assertTrue($sessiya->estArhiv());
        $this->assertSame(self::NOW + 10, $sessiya->getArchivedAt());
        $this->assertSame(SessiyaOpenCode::ARCHIVE_REASON_MANUAL, $sessiya->getArchiveReason());
        $this->assertSame(self::NOW + 10, $sessiya->getUpdatedAt());
    }

    #[Test]
    public function arhivaciya_bez_prichini_zapreshchena(): void
    {
        $sessiya = self::svobodnaya();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.archive_reason_required');

        $sessiya->arhivirovat('   ', self::NOW + 10);
    }

    #[Test]
    public function arhivnaya_sessiya_ne_prinimaet_hody(): void
    {
        $sessiya = self::svobodnaya();
        $sessiya->arhivirovat(SessiyaOpenCode::ARCHIVE_REASON_UNIT_DELETED, self::NOW + 1);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.archived');

        $sessiya->trebovatAktivnuyu();
    }

    #[Test]
    public function aktivnaya_sessiya_propuskaet_hody(): void
    {
        $sessiya = self::svobodnaya();

        $sessiya->trebovatAktivnuyu();

        $this->assertFalse($sessiya->estArhiv());
    }

    #[Test]
    public function restore_vosstanavlivaet_privyazku_i_arhiv(): void
    {
        $sessiya = SessiyaOpenCode::restore(
            's-1',
            'acc-1',
            'cred-1',
            'Заголовок',
            null,
            [
                $this->privyazka(self::UNIT_ID, self::UNIT_PROJECT_ID),
                $this->privyazka('unit-2', 'project-2'),
            ],
            self::NOW + 50,
            SessiyaOpenCode::ARCHIVE_REASON_UNIT_DELETED,
            self::NOW,
            self::NOW + 50,
        );

        $this->assertSame([self::UNIT_ID, 'unit-2'], $sessiya->getUnitIds());
        $this->assertSame(self::UNIT_ID, $sessiya->getUnitId());
        $this->assertSame(self::UNIT_PROJECT_ID, $sessiya->getUnitProjectId());
        $this->assertSame(self::NOW + 50, $sessiya->getArchivedAt());
        $this->assertSame(SessiyaOpenCode::ARCHIVE_REASON_UNIT_DELETED, $sessiya->getArchiveReason());
        $this->assertTrue($sessiya->estArhiv());
    }

    #[Test]
    public function restore_ne_ogranichivaet_kolichestvo_unitov(): void
    {
        $units = [];
        for ($i = 1; $i <= SessiyaOpenCode::MAX_UNITS + 5; ++$i) {
            $units[] = $this->privyazka('unit-'.$i, 'project-'.$i);
        }

        // Лимит проверяется на входе, а чтение из хранилища не должно падать:
        // иначе старые сессии стало бы невозможно открыть после снижения лимита.
        $sessiya = SessiyaOpenCode::restore(
            's-1',
            'acc-1',
            'cred-1',
            'Заголовок',
            null,
            $units,
            null,
            null,
            self::NOW,
            self::NOW,
        );

        $this->assertCount(SessiyaOpenCode::MAX_UNITS + 5, $sessiya->getUnits());
    }

    #[Test]
    public function restore_arhiva_bez_prichini_otklonyaetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.session.archive_reason_required');

        SessiyaOpenCode::restore(
            's-1',
            'acc-1',
            'cred-1',
            'Заголовок',
            null,
            [$this->privyazka(self::UNIT_ID, self::UNIT_PROJECT_ID)],
            self::NOW + 50,
            null,
            self::NOW,
            self::NOW + 50,
        );
    }

    private function svobodnaya(): SessiyaOpenCode
    {
        return SessiyaOpenCode::create('s-1', 'acc-1', 'cred-1', null, [], self::NOW);
    }

    private function privyazka(string $unitId, string $unitProjectId): PrivyazkaKUnitu
    {
        return PrivyazkaKUnitu::izZnacheniy($unitId, $unitProjectId);
    }
}
