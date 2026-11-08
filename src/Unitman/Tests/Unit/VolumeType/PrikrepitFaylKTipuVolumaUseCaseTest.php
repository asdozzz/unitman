<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\VolumeType;

use App\Unitman\Business\Command\VolumeType\PrikrepitFaylKTipuVoluma;
use App\Unitman\Business\UseCase\VolumeType\PrikrepitFaylKTipuVolumaUseCase;
use DomainException;

/**
 * Привязка проектного архива медиатеки к типу тома.
 */
final class PrikrepitFaylKTipuVolumaUseCaseTest extends AbstractKatalogUseCase
{
    private PrikrepitFaylKTipuVolumaUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new PrikrepitFaylKTipuVolumaUseCase(
            $this->proverkaDostupa,
            $this->typeRepository,
            $this->fileRepository,
            $this->mediatekaService
        );
    }

    /**
     * @test
     */
    public function proektnyi_arhiv_privyazyvaetsya_k_tipu(): void
    {
        $type = $this->dobavitTip('logs');
        $this->mediatekaService->vladelcy['file-1'] = ['unitId' => null, 'projectId' => self::PROJECT_ID];

        $this->useCase->handle(new PrikrepitFaylKTipuVoluma(self::PROJECT_ID, 'logs', 'file-1'));

        $this->assertTrue($this->fileRepository->esliSvyazEst($type->id, 'file-1'));
        $this->assertCount(1, $this->fileRepository->najtiPoTipu($type->id));
    }

    /**
     * @test
     */
    public function k_odnomu_tipu_mozhno_privyazat_neskolko_faylov(): void
    {
        $type = $this->dobavitTip('logs');
        $this->mediatekaService->vladelcy['file-1'] = ['unitId' => null, 'projectId' => self::PROJECT_ID];
        $this->mediatekaService->vladelcy['file-2'] = ['unitId' => null, 'projectId' => self::PROJECT_ID];

        $this->useCase->handle(new PrikrepitFaylKTipuVoluma(self::PROJECT_ID, 'logs', 'file-1'));
        $this->useCase->handle(new PrikrepitFaylKTipuVoluma(self::PROJECT_ID, 'logs', 'file-2'));

        $this->assertCount(2, $this->fileRepository->najtiPoTipu($type->id));
    }

    /**
     * @test
     */
    public function pustoi_file_id_otklonyaetsya(): void
    {
        $this->dobavitTip('logs');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.file_id_is_empty');

        $this->useCase->handle(new PrikrepitFaylKTipuVoluma(self::PROJECT_ID, 'logs', '   '));
    }

    /**
     * @test
     */
    public function neizvestnyi_fayl_otklonyaetsya(): void
    {
        $this->dobavitTip('logs');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.file_not_found');

        $this->useCase->handle(new PrikrepitFaylKTipuVoluma(self::PROJECT_ID, 'logs', 'file-1'));
    }

    /**
     * @test
     */
    public function fayl_iunita_otklonyaetsya(): void
    {
        $this->dobavitTip('logs');
        $this->mediatekaService->vladelcy['file-1'] = ['unitId' => 'unit-1', 'projectId' => null];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.file_belongs_to_unit');

        $this->useCase->handle(new PrikrepitFaylKTipuVoluma(self::PROJECT_ID, 'logs', 'file-1'));
    }

    /**
     * @test
     */
    public function fayl_chuzhogo_proekta_otklonyaetsya(): void
    {
        $this->dobavitTip('logs');
        $this->mediatekaService->vladelcy['file-1'] = ['unitId' => null, 'projectId' => self::OTHER_PROJECT_ID];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.file_not_found');

        $this->useCase->handle(new PrikrepitFaylKTipuVoluma(self::PROJECT_ID, 'logs', 'file-1'));
    }

    /**
     * @test
     */
    public function fayl_privyazannyi_k_drugomu_tipu_otklonyaetsya(): void
    {
        $this->tipSFaylom('cache', 'file-1');
        $this->dobavitTip('logs');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.file_already_linked');

        $this->useCase->handle(new PrikrepitFaylKTipuVoluma(self::PROJECT_ID, 'logs', 'file-1'));
    }

    /**
     * @test
     */
    public function povtornaya_privyazka_k_tomu_zhe_tipu_idempotentna(): void
    {
        $type = $this->tipSFaylom('logs', 'file-1');

        $this->useCase->handle(new PrikrepitFaylKTipuVoluma(self::PROJECT_ID, 'logs', 'file-1'));

        $this->assertCount(1, $this->fileRepository->najtiPoTipu($type->id));
    }

    /**
     * @test
     */
    public function neizvestnyi_tip_otklonyaetsya(): void
    {
        $this->mediatekaService->vladelcy['file-1'] = ['unitId' => null, 'projectId' => self::PROJECT_ID];

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.not_found');

        $this->useCase->handle(new PrikrepitFaylKTipuVoluma(self::PROJECT_ID, 'logs', 'file-1'));
    }

    /**
     * @test
     */
    public function ne_admin_otklonyaetsya(): void
    {
        $this->dobavitTip('logs');
        $this->mediatekaService->vladelcy['file-1'] = ['unitId' => null, 'projectId' => self::PROJECT_ID];
        $this->dobavitUchastnika(self::USER_ID);
        $this->securityService->ustanovitPolzovatelya(self::USER_ID, false);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('security.access_denied');

        $this->useCase->handle(new PrikrepitFaylKTipuVoluma(self::PROJECT_ID, 'logs', 'file-1'));
    }
}
