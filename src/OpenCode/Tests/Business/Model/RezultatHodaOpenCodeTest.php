<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Model;

use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Результат хода — граница «раннер -> домен»: здесь нормализуется всё, что
 * пришло из Temporal, и отсекается мусор неизвестной формы.
 */
final class RezultatHodaOpenCodeTest extends TestCase
{
    #[Test]
    public function otvet_iz_ranнера_raspoznaetsya_kak_otvet(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => 'ANSWER',
            'Answer' => 'Проверка завершена',
            'ExternalSessionId' => 'ext-42',
        ]);

        $this->assertTrue($rezultat->estOtvetom());
        $this->assertFalse($rezultat->estVoprosom());
        $this->assertFalse($rezultat->estOshibkoi());
        $this->assertSame('Проверка завершена', $rezultat->answer);
        $this->assertSame('ext-42', $rezultat->externalSessionId);
        $this->assertSame([], $rezultat->questions);
    }

    #[Test]
    public function vopros_iz_ranнера_normaлизует_varianty(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => 'QUESTION',
            'Questions' => [
                [
                    'Id' => 'q1',
                    'Text' => 'Выберите окружение',
                    'AllowFreeText' => true,
                    'Options' => [
                        ['Id' => 'o1', 'Label' => 'stage', 'Description' => 'Стейджевый стенд'],
                        ['Id' => '', 'Label' => '', 'Description' => ''],
                    ],
                ],
                'мусор вместо вопроса',
                [
                    'Text' => 'Без идентификатора',
                ],
            ],
        ]);

        $this->assertTrue($rezultat->estVoprosom());
        $this->assertFalse($rezultat->estOtvetom());
        $this->assertCount(2, $rezultat->questions);

        $pervyi = $rezultat->questions[0];
        $this->assertSame('q1', $pervyi['Id']);
        $this->assertSame('Выберите окружение', $pervyi['Text']);
        $this->assertTrue($pervyi['AllowFreeText']);
        $this->assertCount(2, $pervyi['Options']);
        $this->assertSame(
            ['Id' => 'o1', 'Label' => 'stage', 'Description' => 'Стейджевый стенд'],
            $pervyi['Options'][0],
        );
        $this->assertSame(
            ['Id' => '', 'Label' => '', 'Description' => null],
            $pervyi['Options'][1],
        );

        $vtoroi = $rezultat->questions[1];
        $this->assertSame('', $vtoroi['Id']);
        $this->assertSame('Без идентификатора', $vtoroi['Text']);
        $this->assertFalse($vtoroi['AllowFreeText']);
        $this->assertSame([], $vtoroi['Options']);
    }

    #[Test]
    public function pустой_massiv_daet_oshibku_po_umolchaniyu(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([]);

        $this->assertFalse($rezultat->success);
        $this->assertSame(RezultatHodaOpenCode::KIND_ERROR, $rezultat->kind);
        $this->assertTrue($rezultat->estOshibkoi());
        $this->assertSame([], $rezultat->questions);
        $this->assertNull($rezultat->answer);
        $this->assertNull($rezultat->errorKey);
        $this->assertNull($rezultat->externalSessionId);
        $this->assertNull($rezultat->errorMessage);
    }

    /**
     * Неизвестный Kind — не ошибка раннера как таковая, но и не ответ:
     * закрывать такой ход как ANSWERED нельзя. Решение принимает
     * стейт-машина `HodOpenCode`, поэтому здесь фиксируем только
     * «не является ни ответом, ни вопросом».
     */
    #[Test]
    public function uspeh_s_neizvestnym_kind_ne_yavlyaetsya_ni_otvetom_ni_voprosom(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => 'SOMETHING_ELSE',
            'Answer' => 'текст',
        ]);

        $this->assertFalse($rezultat->estOshibkoi());
        $this->assertFalse($rezultat->estOtvetom());
        $this->assertFalse($rezultat->estVoprosom());
        $this->assertSame('текст', $rezultat->answer);
    }

    #[Test]
    public function priznak_uspeha_vazhnee_kind(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => false,
            'Kind' => 'ANSWER',
            'Answer' => 'текст',
            'ErrorKey' => 'opencode.turn.failed',
        ]);

        $this->assertTrue($rezultat->estOshibkoi());
        $this->assertFalse($rezultat->estOtvetom());
        $this->assertSame('opencode.turn.failed', $rezultat->errorKey);
    }

    #[Test]
    public function pustye_stroki_prevratcshayutsya_v_null(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => 'ANSWER',
            'Answer' => '',
            'ErrorKey' => '',
            'ExternalSessionId' => '',
            'ErrorMessage' => '',
        ]);

        $this->assertNull($rezultat->answer);
        $this->assertNull($rezultat->errorKey);
        $this->assertNull($rezultat->externalSessionId);
        $this->assertNull($rezultat->errorMessage);
    }

    /**
     * Реальный текст ошибки раннера не переводится: он должен дойти до UI
     * без искажений, иначе пользователь увидит только общий ErrorKey.
     */
    #[Test]
    public function soobshchenie_ob_oshibke_ranнера_sokhranyaetsya(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => false,
            'Kind' => 'ERROR',
            'ErrorKey' => 'opencode.turn.failed',
            'ErrorMessage' => 'opencode: exit status 1: connection refused',
        ]);

        $this->assertTrue($rezultat->estOshibkoi());
        $this->assertSame('opencode: exit status 1: connection refused', $rezultat->errorMessage);
    }

    #[Test]
    public function oshibka_sozdaet_ozhidаemyi_rezultat(): void
    {
        $rezultat = RezultatHodaOpenCode::oshibka('opencode.turn.runner_unavailable');

        $this->assertFalse($rezultat->success);
        $this->assertSame(RezultatHodaOpenCode::KIND_ERROR, $rezultat->kind);
        $this->assertSame('opencode.turn.runner_unavailable', $rezultat->errorKey);
        $this->assertTrue($rezultat->estOshibkoi());
    }

    /**
     * Раннер сам разрешает каталог юнита по `ProjectId`/`UnitId`; если каталога
     * нет, приходит этот ключ. PHP-сторона его не порождает, поэтому кодируем
     * контракт тестом: ключ обязан дойти до домена без искажений, иначе
     * пользователь увидит непереведённое сообщение.
     */
    #[Test]
    public function oshibka_ottsutstviya_kataloga_unita_prohodit_bez_iskazhenii(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => false,
            'Kind' => 'ERROR',
            'ErrorKey' => 'opencode.turn.unit_directory_not_found',
        ]);

        $this->assertTrue($rezultat->estOshibkoi());
        $this->assertSame('opencode.turn.unit_directory_not_found', $rezultat->errorKey);
    }

    #[Test]
    public function to_array_vozvrashchaet_konтракт_ranнера(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => 'QUESTION',
            'Questions' => [
                [
                    'Id' => 'q1',
                    'Text' => 'Вопрос',
                    'AllowFreeText' => false,
                    'Options' => [['Id' => 'o1', 'Label' => 'Да', 'Description' => null]],
                ],
            ],
            'ExternalSessionId' => 'ext-1',
        ]);

        $array = $rezultat->toArray();

        $this->assertSame(
            ['Success', 'Kind', 'Questions', 'Answer', 'ErrorKey', 'ExternalSessionId', 'ErrorMessage', 'Attachments'],
            array_keys($array),
        );
        $this->assertTrue($array['Success']);
        $this->assertSame('QUESTION', $array['Kind']);
        $this->assertNull($array['Answer']);
        $this->assertSame('ext-1', $array['ExternalSessionId']);
        $this->assertNull($array['ErrorMessage']);
        $this->assertSame([], $array['Attachments']);
    }

    #[Test]
    public function staryj_payload_bez_attachments_daet_pustoj_spisok(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => 'ANSWER',
            'Answer' => 'Готово',
        ]);

        $this->assertSame([], $rezultat->attachments);
    }

    #[Test]
    public function attachments_ravnye_null_daut_pustoj_spisok(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => 'ANSWER',
            'Answer' => 'Готово',
            'Attachments' => null,
        ]);

        $this->assertSame([], $rezultat->attachments);
    }

    #[Test]
    public function nekorrektnye_attachments_otbrasyvayutsya(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => 'ANSWER',
            'Answer' => 'Готово',
            'Attachments' => [
                'мусор',
                ['Name' => 'без-id.md', 'Size' => 10],
                ['FileId' => 'f-1', 'Name' => 'без-размера.md'],
                ['FileId' => 'f-2', 'Name' => 'нулевой.md', 'Size' => 0],
                ['FileId' => 'f-3', 'Name' => 'дробный.md', 'Size' => 1.5],
                ['FileId' => 'f-4', 'Name' => 'ok.md', 'Size' => 12],
            ],
        ]);

        $this->assertCount(1, $rezultat->attachments);
        $this->assertSame('f-4', $rezultat->attachments[0]['FileId']);
    }

    #[Test]
    public function previewable_zavisit_ot_rasshireniya(): void
    {
        $rezultat = RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => 'ANSWER',
            'Answer' => 'Готово',
            'Attachments' => [
                ['FileId' => 'f-md', 'Name' => 'plan.MD', 'Size' => 5],
                ['FileId' => 'f-yml', 'Name' => 'config.yml', 'Size' => 5],
                ['FileId' => 'f-png', 'Name' => 'screen.png', 'Size' => 5],
                ['FileId' => 'f-none', 'Name' => 'Makefile', 'Size' => 5],
            ],
        ]);

        $poImId = [];

        foreach ($rezultat->attachments as $attachment) {
            $poImId[$attachment['FileId']] = $attachment;
        }

        $this->assertTrue($poImId['f-md']['Previewable']);
        $this->assertTrue($poImId['f-yml']['Previewable']);
        $this->assertFalse($poImId['f-png']['Previewable']);
        $this->assertFalse($poImId['f-none']['Previewable']);
    }

    #[Test]
    public function to_array_vsegda_otdayot_attachments_spiskom(): void
    {
        $pustoj = RezultatHodaOpenCode::fromArray([]);
        $this->assertSame([], $pustoj->toArray()['Attachments']);

        $sVlozheniem = RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => 'ANSWER',
            'Answer' => 'Готово',
            'Attachments' => [
                [
                    'FileId' => 'f-1',
                    'Name' => 'plan.md',
                    'Size' => 10,
                    'ContentType' => 'text/markdown',
                ],
            ],
        ]);

        $array = $sVlozheniem->toArray();

        $this->assertIsList($array['Attachments']);
        $this->assertSame(
            [
                [
                    'FileId' => 'f-1',
                    'Name' => 'plan.md',
                    'Size' => 10,
                    'ContentType' => 'text/markdown',
                    'Previewable' => true,
                ],
            ],
            $array['Attachments'],
        );
    }

    #[Test]
    public function povtornaya_serializaciya_idempotentna(): void
    {
        $pervyi = RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => 'QUESTION',
            'Questions' => [
                [
                    'Id' => 'q1',
                    'Text' => 'Вопрос',
                    'AllowFreeText' => true,
                    'Options' => [['Id' => 'o1', 'Label' => 'Да', 'Description' => 'Описание']],
                ],
            ],
        ]);

        $vtoroi = RezultatHodaOpenCode::fromArray($pervyi->toArray());

        $this->assertSame($pervyi->toArray(), $vtoroi->toArray());
    }
}
