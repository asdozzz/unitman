<?php

declare(strict_types=1);

namespace App\Runner\Tests\Business\Command;

use App\Runner\Business\Command\ImageConfig;
use App\Runner\Business\Command\IzmenitRezhimRedaktoraUnita;
use App\Runner\Business\Command\NachatHodOpenCode;
use App\Runner\Business\Command\NachatPodgotovkuUnita;
use App\Runner\Business\Command\NachatPodgotovkuUnita\ContainerSettings;
use App\Runner\Business\Command\PodgotovitSessiyuRedaktora;
use App\Runner\Business\Command\VipolnitHodOpenCode;
use App\Runner\Business\Model\RezhimRedaktora;
use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\JsonConverter;

/**
 * Контракт PHP-стороны с Go-раннером (plan §8).
 *
 * Проверяются плоские имена полей на проводе, значения режима и обратная
 * совместимость: новые поля необязательны, старые вызовы получают `view`.
 */
final class RedaktorCommandsTest extends TestCase
{
    public function test_rezhim_redaktora_dopustimie_znacheniya_i_normalizaciya(): void
    {
        self::assertTrue(RezhimRedaktora::isValid('view'));
        self::assertTrue(RezhimRedaktora::isValid('edit'));
        self::assertFalse(RezhimRedaktora::isValid('locked'));
        self::assertFalse(RezhimRedaktora::isValid(''));

        self::assertSame('view', RezhimRedaktora::normalize(null));
        self::assertSame('view', RezhimRedaktora::normalize(''));
        self::assertSame('view', RezhimRedaktora::normalize('unknown'));
        self::assertSame('view', RezhimRedaktora::normalize('view'));
        self::assertSame('edit', RezhimRedaktora::normalize('edit'));
    }

    public function test_nachat_podgotovku_bez_rezhima_ostayotsya_sovmestimym(): void
    {
        $legacy = $this->podgotovka();

        self::assertSame('view', $legacy->RezhimRedaktora);
    }

    public function test_nachat_podgotovku_peredayot_rezhim_edit(): void
    {
        $command = $this->podgotovka('edit');

        self::assertSame('edit', $command->RezhimRedaktora);
    }

    public function test_izmenit_rezhim_redaktora_unita_po_umolchaniyu_view(): void
    {
        $command = new IzmenitRezhimRedaktoraUnita('unit-1');

        self::assertSame('unit-1', $command->UnitId);
        self::assertSame('view', $command->Mode);
    }

    public function test_podgotovit_sessiyu_redaktora_peredayot_vse_polya(): void
    {
        $command = new PodgotovitSessiyuRedaktora('unit-1', 'account-1', 'edit', 'secret');

        self::assertSame('unit-1', $command->UnitId);
        self::assertSame('account-1', $command->UserId);
        self::assertSame('edit', $command->Mode);
        self::assertSame('secret', $command->Token);
    }

    public function test_hod_opencode_po_umolchaniyu_rw_bez_tokena_dlya_obratnoy_sovmestimosti(): void
    {
        $command = new NachatHodOpenCode('account-1', 'credential-1', null, 'prompt');

        self::assertFalse($command->ReadOnly);
        self::assertNull($command->Token);
        self::assertNull($command->Model);
    }

    public function test_hod_opencode_peredayot_model(): void
    {
        $command = new NachatHodOpenCode(
            'account-1',
            'credential-1',
            null,
            'prompt',
            Model: 'gpt-4o-mini',
        );

        self::assertSame('gpt-4o-mini', $command->Model);
    }

    public function test_hod_opencode_peredayot_readonly_i_token(): void
    {
        $command = new NachatHodOpenCode(
            'account-1',
            'credential-1',
            null,
            'prompt',
            ReadOnly: true,
            Token: 'secret',
        );

        self::assertTrue($command->ReadOnly);
        self::assertSame('secret', $command->Token);
    }

    public function test_vipolnit_hod_opencode_po_umolchaniyu_ne_soderzhit_novyh_poley(): void
    {
        $command = new VipolnitHodOpenCode(null, 'prompt', [], [], 'url', 'model', 'key');

        self::assertFalse($command->ReadOnly);
        self::assertNull($command->Token);
    }

    public function test_json_na_provode_soderzhit_ploskie_imena_i_strochniy_rezhim(): void
    {
        $converter = new JsonConverter();

        $podgotovka = json_decode(
            $converter->toPayload($this->podgotovka('edit'))->getData(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame('edit', $podgotovka['RezhimRedaktora']);

        $izmenit = json_decode(
            $converter->toPayload(new IzmenitRezhimRedaktoraUnita('unit-1', 'edit'))->getData(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame('unit-1', $izmenit['UnitId']);
        self::assertSame('edit', $izmenit['Mode']);

        $sessiya = json_decode(
            $converter->toPayload(new PodgotovitSessiyuRedaktora('unit-1', 'account-1', 'edit', 'secret'))->getData(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame('unit-1', $sessiya['UnitId']);
        self::assertSame('account-1', $sessiya['UserId']);
        self::assertSame('edit', $sessiya['Mode']);
        self::assertSame('secret', $sessiya['Token']);

        $hod = json_decode(
            $converter->toPayload(new VipolnitHodOpenCode(
                null,
                'prompt',
                [],
                [],
                'url',
                'model',
                'key',
                ReadOnly: true,
                Token: 'secret',
            ))->getData(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertTrue($hod['ReadOnly']);
        self::assertSame('secret', $hod['Token']);
    }

    private function podgotovka(string $rezhim = 'view'): NachatPodgotovkuUnita
    {
        return new NachatPodgotovkuUnita(
            'project-1',
            'project',
            'unit-1',
            'unit',
            'https://storage.example/repo.git',
            [],
            [],
            [],
            new ContainerSettings(),
            'main',
            new ImageConfig('image', []),
            [],
            $rezhim,
        );
    }
}
