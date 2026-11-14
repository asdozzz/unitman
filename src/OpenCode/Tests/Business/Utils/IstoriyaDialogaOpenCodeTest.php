<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Utils\IstoriyaDialogaOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * История диалога — то, что уходит в каждый ход, потому что раннер stateless.
 * Ошибка в сборке истории означает, что OpenCode «забудет» разговор.
 */
final class IstoriyaDialogaOpenCodeTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function pustaya_istoriya_daet_pustoi_massiv(): void
    {
        $this->assertSame([], IstoriyaDialogaOpenCode::sobrat([]));
    }

    #[Test]
    public function hod_bez_rezultata_daet_tolko_promt(): void
    {
        $hod = $this->hod('Покажи статус');

        $this->assertSame(
            [['Role' => 'user', 'Text' => 'Покажи статус']],
            IstoriyaDialogaOpenCode::sobrat([$hod]),
        );
    }

    #[Test]
    public function otvet_dobavlyaet_repliku_assistenta(): void
    {
        $hod = $this->hod('Покажи статус');
        $hod->primenitRezultat($this->otvet('Всё зелёное'), self::NOW + 1);

        $this->assertSame(
            [
                ['Role' => 'user', 'Text' => 'Покажи статус'],
                ['Role' => 'assistant', 'Text' => 'Всё зелёное'],
            ],
            IstoriyaDialogaOpenCode::sobrat([$hod]),
        );
    }

    #[Test]
    public function vopros_i_otvet_prevrashchayutsya_v_paru_replik(): void
    {
        $hod = HodOpenCode::create(
            'hod-1',
            's-1',
            1,
            'wf-1',
            'Выбери окружение',
            [['QuestionId' => 'q1', 'OptionId' => 'o1', 'Text' => null]],
            self::NOW,
        );
        $hod->primenitRezultat($this->vopros(), self::NOW + 1);

        $this->assertSame(
            [
                ['Role' => 'user', 'Text' => 'Выбери окружение'],
                ['Role' => 'assistant', 'Text' => 'Какое окружение использовать?'],
                ['Role' => 'user', 'Text' => 'Какое окружение использовать?: stage'],
            ],
            IstoriyaDialogaOpenCode::sobrat([$hod]),
        );
    }

    #[Test]
    public function vopros_bez_otvetov_daet_tolko_repliku_assistenta(): void
    {
        $hod = $this->hod('Выбери окружение');
        $hod->primenitRezultat($this->vopros(), self::NOW + 1);

        $this->assertSame(
            [
                ['Role' => 'user', 'Text' => 'Выбери окружение'],
                ['Role' => 'assistant', 'Text' => 'Какое окружение использовать?'],
            ],
            IstoriyaDialogaOpenCode::sobrat([$hod]),
        );
    }

    #[Test]
    public function oshibka_ne_popadaet_v_istoriyu(): void
    {
        $hod = $this->hod('Покажи статус');
        $hod->primenitRezultat(RezultatHodaOpenCode::oshibka('opencode.turn.failed'), self::NOW + 1);

        $this->assertSame(
            [['Role' => 'user', 'Text' => 'Покажи статус']],
            IstoriyaDialogaOpenCode::sobrat([$hod]),
        );
    }

    #[Test]
    public function pystyi_otvet_ne_dobavlyaet_repliku(): void
    {
        $hod = $this->hod('Покажи статус');
        $hod->primenitRezultat($this->otvet('   '), self::NOW + 1);

        $this->assertSame(
            [['Role' => 'user', 'Text' => 'Покажи статус']],
            IstoriyaDialogaOpenCode::sobrat([$hod]),
        );
    }

    #[Test]
    public function neskolko_hodov_idut_v_estestvennom_poryadke(): void
    {
        $pervyi = $this->hod('Первый');
        $pervyi->primenitRezultat($this->otvet('Ответ первый'), self::NOW + 1);

        $vtoroi = HodOpenCode::create('hod-2', 's-1', 2, 'wf-2', 'Второй', [], self::NOW + 2);
        $vtoroi->primenitRezultat($this->otvet('Ответ второй'), self::NOW + 3);

        $this->assertSame(
            [
                ['Role' => 'user', 'Text' => 'Первый'],
                ['Role' => 'assistant', 'Text' => 'Ответ первый'],
                ['Role' => 'user', 'Text' => 'Второй'],
                ['Role' => 'assistant', 'Text' => 'Ответ второй'],
            ],
            IstoriyaDialogaOpenCode::sobrat([$pervyi, $vtoroi]),
        );
    }

    #[Test]
    public function voprosy_v_tekst_ispolzuyut_tekst_a_pri_otsutstvii_id(): void
    {
        $this->assertSame(
            "Какое окружение использовать?\nq2",
            IstoriyaDialogaOpenCode::voprosyVTekst([
                ['Id' => 'q1', 'Text' => 'Какое окружение использовать?', 'AllowFreeText' => false, 'Options' => []],
                ['Id' => 'q2', 'Text' => '   ', 'AllowFreeText' => false, 'Options' => []],
            ]),
        );
    }

    #[Test]
    public function otvety_v_tekst_ispolzuyut_podpis_varianta_a_ne_id(): void
    {
        $tekst = IstoriyaDialogaOpenCode::otvetyVTekst(
            [
                [
                    'Id' => 'q1',
                    'Text' => 'Окружение',
                    'AllowFreeText' => true,
                    'Options' => [['Id' => 'o1', 'Label' => 'stage', 'Description' => null]],
                ],
            ],
            [['QuestionId' => 'q1', 'OptionId' => 'o1', 'Text' => 'и побыстрее']],
        );

        $this->assertSame('Окружение: stage — и побыстрее', $tekst);
    }

    #[Test]
    public function otvet_na_neizvestnyi_vopros_pokazyvaet_id(): void
    {
        $tekst = IstoriyaDialogaOpenCode::otvetyVTekst(
            [],
            [['QuestionId' => 'q-lost', 'OptionId' => 'o1', 'Text' => null]],
        );

        $this->assertSame('q-lost: o1', $tekst);
    }

    #[Test]
    public function pystye_otvety_ne_dayut_strok(): void
    {
        $this->assertSame(
            '',
            IstoriyaDialogaOpenCode::otvetyVTekst(
                [['Id' => 'q1', 'Text' => 'Вопрос', 'AllowFreeText' => true, 'Options' => []]],
                [['QuestionId' => 'q1', 'OptionId' => null, 'Text' => '   ']],
            ),
        );
    }

    private function hod(string $prompt): HodOpenCode
    {
        return HodOpenCode::create('hod-1', 's-1', 1, 'wf-1', $prompt, [], self::NOW);
    }

    /**
     * @return list<array{
     *     Id: string,
     *     Text: string,
     *     AllowFreeText: bool,
     *     Options: list<array{Id: string, Label: string, Description: string|null}>
     * }>
     */
    private function voprosy(): array
    {
        return [
            [
                'Id' => 'q1',
                'Text' => 'Какое окружение использовать?',
                'AllowFreeText' => false,
                'Options' => [['Id' => 'o1', 'Label' => 'stage', 'Description' => null]],
            ],
        ];
    }

    private function vopros(): RezultatHodaOpenCode
    {
        return new RezultatHodaOpenCode(
            true,
            RezultatHodaOpenCode::KIND_QUESTION,
            $this->voprosy(),
            null,
            null,
            null,
        );
    }

    private function otvet(string $text): RezultatHodaOpenCode
    {
        return new RezultatHodaOpenCode(
            true,
            RezultatHodaOpenCode::KIND_ANSWER,
            [],
            $text,
            null,
            null,
        );
    }
}
