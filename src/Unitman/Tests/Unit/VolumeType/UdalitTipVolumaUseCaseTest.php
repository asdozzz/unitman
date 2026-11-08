<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\VolumeType;

use App\Unitman\Business\Command\VolumeType\UdalitTipVoluma;
use App\Unitman\Business\UseCase\VolumeType\UdalitTipVolumaUseCase;
use DomainException;

/**
 * Удаление типа тома из каталога проекта.
 */
final class UdalitTipVolumaUseCaseTest extends AbstractKatalogUseCase
{
    private UdalitTipVolumaUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useCase = new UdalitTipVolumaUseCase($this->proverkaDostupa, $this->typeRepository);
    }

    /**
     * @test
     */
    public function tip_bez_faylov_udalyayetsya(): void
    {
        $this->dobavitTip('logs');

        $this->useCase->handle(new UdalitTipVoluma(self::PROJECT_ID, 'logs'));

        $this->assertNull($this->typeRepository->findByProjectAndCode(self::PROJECT_ID, 'logs'));
    }

    /**
     * @test
     */
    public function tip_s_faylami_otklonyaetsya(): void
    {
        $this->tipSFaylom('logs', 'file-1');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.has_files');

        $this->useCase->handle(new UdalitTipVoluma(self::PROJECT_ID, 'logs'));
    }

    /**
     * @test
     */
    public function tip_s_faylami_ostayotsya_v_kataloge(): void
    {
        $this->tipSFaylom('logs', 'file-1');

        try {
            $this->useCase->handle(new UdalitTipVoluma(self::PROJECT_ID, 'logs'));
            $this->fail('Ожидалось исключение unit.volume_type.has_files');
        } catch (DomainException $e) {
            $this->assertSame('unit.volume_type.has_files', $e->getMessage());
        }

        $this->assertNotNull($this->typeRepository->findByProjectAndCode(self::PROJECT_ID, 'logs'));
    }

    /**
     * @test
     */
    public function neizvestnyi_tip_otklonyaetsya(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.not_found');

        $this->useCase->handle(new UdalitTipVoluma(self::PROJECT_ID, 'logs'));
    }

    /**
     * @test
     */
    public function ne_admin_otklonyaetsya(): void
    {
        $this->dobavitTip('logs');
        $this->dobavitUchastnika(self::USER_ID);
        $this->securityService->ustanovitPolzovatelya(self::USER_ID, false);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('security.access_denied');

        $this->useCase->handle(new UdalitTipVoluma(self::PROJECT_ID, 'logs'));
    }

    /**
     * @test
     */
    public function tip_chuzhogo_proekta_ne_udalyayetsya(): void
    {
        $this->dobavitTip('logs', 'Логи', self::OTHER_PROJECT_ID);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('unit.volume_type.not_found');

        $this->useCase->handle(new UdalitTipVoluma(self::PROJECT_ID, 'logs'));
    }
}
