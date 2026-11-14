<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Model;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\RezultatHodaOpenCode;
use App\OpenCode\Business\Model\StatusHoda;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Стейт-машина хода линейна: RUNNING -> AWAITING_INPUT | ANSWERED | FAILED,
 * и после первого результата ход больше не меняется.
 */
final class HodOpenCodeTest extends TestCase
{
    private const NOW = 1_700_000_000;

    #[Test]
    public function novyi_hod_nachinaetsya_v_sostoyanii_running(): void
    {
        $hod = $this->sozdatHod();

        $this->assertSame(StatusHoda::RUNNING, $hod->getStatus());
        $this->assertTrue($hod->estVProzesse());
        $this->assertFalse($hod->zhdetOtveta());
        $this->assertNull($hod->getResult());
        $this->assertNull($hod->getErrorKey());
        $this->assertNull($hod->getFinishedAt());
        $this->assertSame(self::NOW, $hod->getCreatedAt());
        $this->assertSame('покажи логи', $hod->getRequestText());
        $this->assertSame(
            [['QuestionId' => 'q1', 'OptionId' => 'o1', 'Text' => null]],
            $hod->getAnswers(),
        );
    }

    #[Test]
    public function hod_pomnit_svoi_identifikatory(): void
    {
        $hod = $this->sozdatHod();

        $this->assertSame('hod-1', $hod->getId());
        $this->assertSame('session-1', $hod->getSessionId());
        $this->assertSame(3, $hod->getSeq());
        $this->assertSame('workflow-1', $hod->getWorkflowId());
        $this->assertTrue($hod->prinadlezhitSessii('session-1'));
        $this->assertFalse($hod->prinadlezhitSessii('session-2'));
    }

    #[Test]
    public function pustoi_prompt_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.turn.request_required');

        HodOpenCode::create('hod-1', 'session-1', 1, 'workflow-1', "  \n\t ", [], self::NOW);
    }

    #[Test]
    public function vopros_runnera_perevodit_hod_v_ozhidanie_otveta(): void
    {
        $hod = $this->sozdatHod();

        $hod->primenitRezultat($this->rezultatVopros(), self::NOW + 5);

        $this->assertSame(StatusHoda::AWAITING_INPUT, $hod->getStatus());
        $this->assertTrue($hod->zhdetOtveta());
        $this->assertFalse($hod->estVProzesse());
        $this->assertSame(self::NOW + 5, $hod->getFinishedAt());
        $this->assertNotNull($hod->getResult());
        $this->assertNull($hod->getErrorKey());
    }

    #[Test]
    public function otvet_runnera_zavershaet_hod(): void
    {
        $hod = $this->sozdatHod();

        $hod->primenitRezultat($this->rezultatOtvet(), self::NOW + 7);

        $this->assertSame(StatusHoda::ANSWERED, $hod->getStatus());
        $this->assertFalse($hod->zhdetOtveta());
        $this->assertSame(self::NOW + 7, $hod->getFinishedAt());
        $this->assertSame('Готово', $hod->getResult()?->answer);
    }

    #[Test]
    public function oshibka_runnera_zavershaet_hod_so_svoim_klyuchom(): void
    {
        $hod = $this->sozdatHod();

        $hod->primenitRezultat(
            RezultatHodaOpenCode::oshibka('opencode.turn.runner_unavailable'),
            self::NOW + 9,
        );

        $this->assertSame(StatusHoda::FAILED, $hod->getStatus());
        $this->assertSame('opencode.turn.runner_unavailable', $hod->getErrorKey());
        $this->assertSame(self::NOW + 9, $hod->getFinishedAt());
    }

    #[Test]
    public function oshibka_bez_klyucha_poluchaet_znachenie_po_umolchaniyu(): void
    {
        $hod = $this->sozdatHod();

        $hod->primenitRezultat(
            new RezultatHodaOpenCode(false, RezultatHodaOpenCode::KIND_ERROR, [], null, null, null),
            self::NOW + 9,
        );

        $this->assertSame(StatusHoda::FAILED, $hod->getStatus());
        $this->assertSame('opencode.turn.failed', $hod->getErrorKey());
    }

    #[Test]
    public function povtornyi_rezultat_ne_menyaet_zavershennyi_hod(): void
    {
        $hod = $this->sozdatHod();

        $hod->primenitRezultat($this->rezultatVopros(), self::NOW + 5);
        $hod->primenitRezultat($this->rezultatOtvet(), self::NOW + 10);

        $this->assertSame(StatusHoda::AWAITING_INPUT, $hod->getStatus());
        $this->assertSame(self::NOW + 5, $hod->getFinishedAt());
        $this->assertTrue($hod->getResult()?->estVoprosom() ?? false);
    }

    #[Test]
    public function neizvestnyi_kind_zavershaet_hod_oshibkoi_a_ne_otvetom(): void
    {
        $hod = $this->sozdatHod();

        $hod->primenitRezultat(
            RezultatHodaOpenCode::fromArray([
                'Success' => true,
                'Kind' => 'SOMETHING_ELSE',
                'Answer' => 'текст',
            ]),
            self::NOW + 8,
        );

        $this->assertSame(StatusHoda::FAILED, $hod->getStatus());
        $this->assertSame('opencode.turn.unexpected_kind', $hod->getErrorKey());
        $this->assertSame(self::NOW + 8, $hod->getFinishedAt());
    }

    #[Test]
    public function otmetit_oshibku_perevodit_hod_v_failed(): void
    {
        $hod = $this->sozdatHod();

        $hod->otmetitOshibku('opencode.turn.cancelled', self::NOW + 2);

        $this->assertSame(StatusHoda::FAILED, $hod->getStatus());
        $this->assertSame('opencode.turn.cancelled', $hod->getErrorKey());
        $this->assertSame(self::NOW + 2, $hod->getFinishedAt());
    }

    #[Test]
    public function otmetit_oshibku_ne_perezapisyvaet_zavershennyi_hod(): void
    {
        $hod = $this->sozdatHod();

        $hod->primenitRezultat($this->rezultatOtvet(), self::NOW + 3);
        $hod->otmetitOshibku('opencode.turn.cancelled', self::NOW + 4);

        $this->assertSame(StatusHoda::ANSWERED, $hod->getStatus());
        $this->assertNull($hod->getErrorKey());
        $this->assertSame(self::NOW + 3, $hod->getFinishedAt());
    }

    #[Test]
    public function restore_vosstanavlivaet_sostoyanie_iz_bd(): void
    {
        $hod = HodOpenCode::restore(
            'hod-1',
            'session-1',
            3,
            'workflow-1',
            'AWAITING_INPUT',
            'покажи логи',
            [['QuestionId' => 'q1', 'OptionId' => null, 'Text' => 'вручную']],
            $this->rezultatVopros(),
            null,
            self::NOW,
            self::NOW + 5,
        );

        $this->assertSame(StatusHoda::AWAITING_INPUT, $hod->getStatus());
        $this->assertTrue($hod->zhdetOtveta());
        $this->assertSame(self::NOW + 5, $hod->getFinishedAt());
        $this->assertSame(
            [['QuestionId' => 'q1', 'OptionId' => null, 'Text' => 'вручную']],
            $hod->getAnswers(),
        );
    }

    #[Test]
    public function to_array_seriaizuet_vse_polya(): void
    {
        $hod = $this->sozdatHod();
        $hod->primenitRezultat($this->rezultatOtvet(), self::NOW + 7);

        $array = $hod->toArray();

        $this->assertSame('hod-1', $array['id']);
        $this->assertSame('session-1', $array['sessionId']);
        $this->assertSame(3, $array['seq']);
        $this->assertSame('workflow-1', $array['workflowId']);
        $this->assertSame('ANSWERED', $array['status']);
        $this->assertSame('покажи логи', $array['requestText']);
        $this->assertSame(self::NOW + 7, $array['finishedAt']);
        $this->assertNull($array['errorKey']);
        $this->assertIsArray($array['result']);
    }

    private function sozdatHod(): HodOpenCode
    {
        return HodOpenCode::create(
            'hod-1',
            'session-1',
            3,
            'workflow-1',
            'покажи логи',
            [['QuestionId' => 'q1', 'OptionId' => 'o1', 'Text' => null]],
            self::NOW,
        );
    }

    private function rezultatVopros(): RezultatHodaOpenCode
    {
        return RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => RezultatHodaOpenCode::KIND_QUESTION,
            'Questions' => [
                [
                    'Id' => 'q1',
                    'Text' => 'Какой вариант?',
                    'AllowFreeText' => false,
                    'Options' => [
                        ['Id' => 'o1', 'Label' => 'Первый', 'Description' => null],
                        ['Id' => 'o2', 'Label' => 'Второй', 'Description' => ''],
                    ],
                ],
            ],
        ]);
    }

    private function rezultatOtvet(): RezultatHodaOpenCode
    {
        return RezultatHodaOpenCode::fromArray([
            'Success' => true,
            'Kind' => RezultatHodaOpenCode::KIND_ANSWER,
            'Answer' => 'Готово',
            'ExternalSessionId' => 'ext-1',
        ]);
    }
}
