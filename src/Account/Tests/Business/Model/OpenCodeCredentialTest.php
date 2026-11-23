<?php

declare(strict_types=1);

namespace App\Account\Tests\Business\Model;

use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Доменные инварианты реквизитов OpenCode: набор моделей, дефолт, дубликаты,
 * границы и неизменность `getModel()` как синонима дефолтной модели.
 */
final class OpenCodeCredentialTest extends TestCase
{
    /**
     * @param list<string> $models
     */
    private function sozdat(array $models, string $defaultModel = '', string $provider = 'openai'): OpenCodeCredential
    {
        return OpenCodeCredential::create(
            'cred-1',
            'acc-1',
            'main',
            $provider,
            'https://api.openai.com/v1',
            $models,
            $defaultModel,
            'cipher',
            '****1234',
            1000,
        );
    }

    #[Test]
    public function neskolko_modelei_i_pustoi_defolt_beret_pervuyu(): void
    {
        $credential = $this->sozdat(['gpt-4o', 'gpt-4o-mini', 'o3']);

        $this->assertSame(['gpt-4o', 'gpt-4o-mini', 'o3'], $credential->getModels());
        $this->assertSame('gpt-4o', $credential->getDefaultModel());
        // Легаси-потребители читают getModel() — это должен быть дефолт.
        $this->assertSame('gpt-4o', $credential->getModel());
    }

    #[Test]
    public function yavnyi_defolt_sokhranyaetsya(): void
    {
        $credential = $this->sozdat(['gpt-4o', 'gpt-4o-mini'], 'gpt-4o-mini');

        $this->assertSame('gpt-4o-mini', $credential->getDefaultModel());
        $this->assertSame('gpt-4o-mini', $credential->getModel());
    }

    #[Test]
    public function pustoi_nabor_modelei_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.empty_models');

        $this->sozdat([]);
    }

    #[Test]
    public function bolshe_pyatidesyati_modelei_zapreshcheno(): void
    {
        $models = array_map(static fn (int $i): string => 'model-' . $i, range(1, 51));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.too_many_models');

        $this->sozdat($models);
    }

    #[Test]
    public function rovno_pyatidesyat_modelei_razresheno(): void
    {
        $models = array_map(static fn (int $i): string => 'model-' . $i, range(1, 50));

        $credential = $this->sozdat($models);

        $this->assertCount(50, $credential->getModels());
    }

    #[Test]
    public function dublikaty_modelei_zapreshcheny(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.duplicate_model');

        $this->sozdat(['gpt-4o', 'gpt-4o']);
    }

    #[Test]
    public function pustaya_model_v_nabore_zapreshchena(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.empty_model');

        $this->sozdat(['gpt-4o', '   ']);
    }

    #[Test]
    public function slishkom_dlinnaya_model_zapreshchena(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.model_too_long');

        $this->sozdat([str_repeat('m', 129)]);
    }

    #[Test]
    public function defolt_vne_nabora_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.model_not_available');

        $this->sozdat(['gpt-4o'], 'gpt-4o-mini');
    }

    #[Test]
    public function neizvestnyi_provajder_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.unknown_provider');

        $this->sozdat(['gpt-4o'], '', 'no-such-provider');
    }

    #[Test]
    public function pustoi_provajder_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.empty_provider');

        $this->sozdat(['gpt-4o'], '', '   ');
    }

    #[Test]
    public function custom_provajder_razreshen(): void
    {
        $credential = $this->sozdat(['my-model'], '', 'custom');

        $this->assertSame('custom', $credential->getProvider());
    }

    #[Test]
    public function izmenenie_nabora_perekluchaet_defolt_na_pervuyu(): void
    {
        $credential = $this->sozdat(['gpt-4o', 'gpt-4o-mini'], 'gpt-4o-mini');

        $credential->izmenit(null, null, null, ['o3', 'o4-mini'], null, null, null, 2000);

        $this->assertSame(['o3', 'o4-mini'], $credential->getModels());
        $this->assertSame('o3', $credential->getDefaultModel());
        $this->assertSame(2000, $credential->getUpdatedAt());
    }

    #[Test]
    public function izmenenie_null_ne_trogaet_nabor_i_defolt(): void
    {
        $credential = $this->sozdat(['gpt-4o', 'gpt-4o-mini'], 'gpt-4o-mini');

        $credential->izmenit('renamed', null, null, null, null, null, null, 2000);

        $this->assertSame('renamed', $credential->getName());
        $this->assertSame(['gpt-4o', 'gpt-4o-mini'], $credential->getModels());
        $this->assertSame('gpt-4o-mini', $credential->getDefaultModel());
    }

    #[Test]
    public function izmenenie_yavnogo_defolta_vne_nabora_zapreshcheno(): void
    {
        $credential = $this->sozdat(['gpt-4o'], 'gpt-4o');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.model_not_available');

        $credential->izmenit(null, null, null, null, 'o3', null, null, 2000);
    }

    #[Test]
    public function pustoi_yavnyi_defolt_beret_pervuyu_pri_izmenenii(): void
    {
        $credential = $this->sozdat(['gpt-4o', 'gpt-4o-mini'], 'gpt-4o-mini');

        $credential->izmenit(null, null, null, null, '', null, null, 2000);

        $this->assertSame('gpt-4o', $credential->getDefaultModel());
    }
}
