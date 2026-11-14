<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Business\Utils;

use App\OpenCode\Business\Command\OtvetNaVopros;
use App\OpenCode\Business\Utils\OtvetyNaVoprosyOpenCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Валидатор ответов — последний барьер перед раннером: он не должен
 * пропустить ответ на несуществующий вопрос или несуществующий вариант.
 */
final class OtvetyNaVoprosyOpenCodeTest extends TestCase
{
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
                'Text' => 'Окружение',
                'AllowFreeText' => false,
                'Options' => [
                    ['Id' => 'o1', 'Label' => 'stage', 'Description' => null],
                    ['Id' => 'o2', 'Label' => 'prod', 'Description' => 'Боевой'],
                ],
            ],
            [
                'Id' => 'q2',
                'Text' => 'Комментарий',
                'AllowFreeText' => true,
                'Options' => [],
            ],
        ];
    }

    #[Test]
    public function vybrannyi_variant_normalizuetsya(): void
    {
        $otvety = OtvetyNaVoprosyOpenCode::sobrat(
            $this->voprosy(),
            [new OtvetNaVopros('q1', 'o2', null)],
        );

        $this->assertSame(
            [['QuestionId' => 'q1', 'OptionId' => 'o2', 'Text' => null]],
            $otvety,
        );
    }

    #[Test]
    public function svobodnyi_tekst_dopuskaetsya_tolko_gde_razreshen(): void
    {
        $otvety = OtvetyNaVoprosyOpenCode::sobrat(
            $this->voprosy(),
            [new OtvetNaVopros('q2', null, '  нужен стенд  ')],
        );

        $this->assertSame(
            [['QuestionId' => 'q2', 'OptionId' => null, 'Text' => '  нужен стенд  ']],
            $otvety,
        );
    }

    #[Test]
    public function variant_i_kommentarii_mogut_idti_vmeste(): void
    {
        $voprosy = $this->voprosy();
        $voprosy[0]['AllowFreeText'] = true;

        $otvety = OtvetyNaVoprosyOpenCode::sobrat(
            $voprosy,
            [new OtvetNaVopros('q1', 'o1', 'уточнение')],
        );

        $this->assertSame(
            [['QuestionId' => 'q1', 'OptionId' => 'o1', 'Text' => 'уточнение']],
            $otvety,
        );
    }

    #[Test]
    public function pystye_otvety_otbrasyvayutsya(): void
    {
        $otvety = OtvetyNaVoprosyOpenCode::sobrat(
            $this->voprosy(),
            [
                new OtvetNaVopros('q2', null, '   '),
                new OtvetNaVopros('q1', 'o1', null),
            ],
        );

        $this->assertSame(
            [['QuestionId' => 'q1', 'OptionId' => 'o1', 'Text' => null]],
            $otvety,
        );
    }

    #[Test]
    public function polnostyu_pustye_otvety_daut_oshibku(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.turn.answers_required');

        OtvetyNaVoprosyOpenCode::sobrat($this->voprosy(), [new OtvetNaVopros('q1')]);
    }

    #[Test]
    public function otvet_na_neizvestnyi_vopros_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.turn.unknown_question');

        OtvetyNaVoprosyOpenCode::sobrat(
            $this->voprosy(),
            [new OtvetNaVopros('q999', 'o1', null)],
        );
    }

    #[Test]
    public function neizvestnyi_variant_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.turn.unknown_option');

        OtvetyNaVoprosyOpenCode::sobrat(
            $this->voprosy(),
            [new OtvetNaVopros('q1', 'o999', null)],
        );
    }

    #[Test]
    public function svobodnyi_tekst_tam_gde_zapreshchen_daet_oshibku(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.turn.option_required');

        OtvetyNaVoprosyOpenCode::sobrat(
            $this->voprosy(),
            [new OtvetNaVopros('q1', null, 'свободный текст')],
        );
    }

    #[Test]
    public function vopros_bez_identifikatora_ignoriruetsya(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.turn.unknown_question');

        OtvetyNaVoprosyOpenCode::sobrat(
            [
                ['Id' => '', 'Text' => 'Без ID', 'AllowFreeText' => false, 'Options' => []],
            ],
            [new OtvetNaVopros('', 'o1', null)],
        );
    }

    #[Test]
    public function otvet_na_vse_voprosy_sohranyaet_poryadok(): void
    {
        $voprosy = $this->voprosy();
        $voprosy[0]['AllowFreeText'] = true;

        $otvety = OtvetyNaVoprosyOpenCode::sobrat(
            $voprosy,
            [
                new OtvetNaVopros('q2', null, 'текст'),
                new OtvetNaVopros('q1', 'o1', null),
            ],
        );

        $this->assertSame(['q2', 'q1'], array_column($otvety, 'QuestionId'));
    }
}
