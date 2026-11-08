<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\VolumeType;

use App\Unitman\Business\Command\Unit\SozdatUnit\VolumeZapros as VolumeZaprosSozdaniya;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita\VolumeZapros as VolumeZaprosZapolneniya;
use App\Unitman\Business\UseCase\Unit\ProverkaFaylovVolumov;
use DomainException;

/**
 * Проверка `fileId` в блоке `unitman_volumes` при создании юнита
 * и заполнении переменных.
 */
final class ProverkaFaylovVolumovTest extends AbstractKatalogUseCase
{
    private ProverkaFaylovVolumov $proverka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->proverka = new ProverkaFaylovVolumov($this->mediatekaService, $this->fileRepository);
    }

    /**
     * @test
     */
    public function bez_faylov_proverka_pusta(): void
    {
        $this->assertSame([], $this->proverka->proverit(self::PROJECT_ID, []));
        $this->assertSame(
            [],
            $this->proverka->proverit(self::PROJECT_ID, [new VolumeZaprosSozdaniya('logs', 'Логи', null)])
        );
    }

    /**
     * @test
     */
    public function chernovik_popadaet_v_spisok_na_prikreplenie(): void
    {
        $this->mediatekaService->vladelcy['file-1'] = ['unitId' => null, 'projectId' => null];

        $result = $this->proverka->proverit(
            self::PROJECT_ID,
            [new VolumeZaprosSozdaniya('logs', 'Логи', 'file-1')]
        );

        $this->assertSame(['file-1'], $result);
    }

    /**
     * @test
     */
    public function arhiv_kataloga_s_sovpadayushchim_dir_razreshen(): void
    {
        $this->tipSFaylom('logs', 'file-1');

        $result = $this->proverka->proverit(
            self::PROJECT_ID,
            [new VolumeZaprosSozdaniya('logs', 'Логи', 'file-1')]
        );

        $this->assertSame([], $result);
    }

    /**
     * @test
     */
    public function arhiv_kataloga_s_nesovpadayushchim_dir_otklonyaetsya(): void
    {
        $this->tipSFaylom('logs', 'file-1');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volumes.file_type_mismatch');

        $this->proverka->proverit(
            self::PROJECT_ID,
            [new VolumeZaprosSozdaniya('cache', 'Кэш', 'file-1')]
        );
    }

    /**
     * @test
     */
    public function proektnyi_fayl_bez_svyazi_s_tipom_otklonyaetsya(): void
    {
        $this->mediatekaService->vladelcy['file-1'] = ['unitId' => null, 'projectId' => self::PROJECT_ID];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volumes.file_not_in_catalog');

        $this->proverka->proverit(
            self::PROJECT_ID,
            [new VolumeZaprosSozdaniya('logs', 'Логи', 'file-1')]
        );
    }

    /**
     * @test
     */
    public function arhiv_chuzhogo_proekta_otklonyaetsya(): void
    {
        $this->mediatekaService->vladelcy['file-1'] = ['unitId' => null, 'projectId' => self::OTHER_PROJECT_ID];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.files.not_found');

        $this->proverka->proverit(
            self::PROJECT_ID,
            [new VolumeZaprosSozdaniya('logs', 'Логи', 'file-1')]
        );
    }

    /**
     * @test
     */
    public function neizvestnyi_fayl_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.files.not_found');

        $this->proverka->proverit(
            self::PROJECT_ID,
            [new VolumeZaprosSozdaniya('logs', 'Логи', 'file-404')]
        );
    }

    /**
     * @test
     */
    public function fayl_uzhe_privyazannyi_k_etomu_unitu_propuskaetsya(): void
    {
        $this->mediatekaService->vladelcy['file-1'] = ['unitId' => 'unit-1', 'projectId' => null];

        $result = $this->proverka->proverit(
            self::PROJECT_ID,
            [new VolumeZaprosZapolneniya('logs', 'file-1')],
            'unit-1'
        );

        $this->assertSame([], $result);
    }

    /**
     * @test
     */
    public function fayl_drugogo_unita_otklonyaetsya(): void
    {
        $this->mediatekaService->vladelcy['file-1'] = ['unitId' => 'unit-2', 'projectId' => null];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.files.not_found');

        $this->proverka->proverit(
            self::PROJECT_ID,
            [new VolumeZaprosZapolneniya('logs', 'file-1')],
            'unit-1'
        );
    }

    /**
     * @test
     */
    public function arhiv_kataloga_razreshen_i_pri_zapolnenii_peremennyh(): void
    {
        $this->tipSFaylom('logs', 'file-1');

        $result = $this->proverka->proverit(
            self::PROJECT_ID,
            [new VolumeZaprosZapolneniya('logs', 'file-1')],
            'unit-1'
        );

        $this->assertSame([], $result);
    }

    /**
     * @test
     */
    public function pustoi_file_id_ignoriruetsya(): void
    {
        $this->assertSame(
            [],
            $this->proverka->proverit(
                self::PROJECT_ID,
                [new VolumeZaprosSozdaniya('logs', 'Логи', '   ')]
            )
        );
    }

    /**
     * @test
     */
    public function odin_fayl_dlya_dvuh_tomov_proveryaetsya_po_kazhdoi_pare(): void
    {
        $this->tipSFaylom('logs', 'file-1');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volumes.file_type_mismatch');

        $this->proverka->proverit(
            self::PROJECT_ID,
            [
                new VolumeZaprosSozdaniya('logs', 'Логи', 'file-1'),
                new VolumeZaprosSozdaniya('cache', 'Кэш', 'file-1'),
            ]
        );
    }
}
