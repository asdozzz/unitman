<?php

declare(strict_types=1);

namespace App\OpenCode\Tests\Acl;

use App\Account\Api\AccountApi;
use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\OpenCode\Acl\AccountAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * AccountAdapter разрешает модель через AccountApi: пустая — дефолт,
 * непустая обязана входить в набор моделей реквизитов.
 */
final class AccountAdapterTest extends TestCase
{
    #[Test]
    public function pystaya_model_razreshaetsya_v_default(): void
    {
        $adapter = $this->adapter($this->credential(['gpt-4o', 'gpt-4o-mini'], 'gpt-4o-mini'));

        self::assertSame('gpt-4o-mini', $adapter->razreshitModel('acc-1', 'cred-1', null));
        self::assertSame('gpt-4o-mini', $adapter->razreshitModel('acc-1', 'cred-1', '   '));
    }

    #[Test]
    public function model_iz_nabora_vozvrashchaetsya_kak_est(): void
    {
        $adapter = $this->adapter($this->credential(['gpt-4o', 'gpt-4o-mini'], 'gpt-4o-mini'));

        self::assertSame('gpt-4o', $adapter->razreshitModel('acc-1', 'cred-1', 'gpt-4o'));
    }

    #[Test]
    public function model_vne_nabora_dayot_oshibku(): void
    {
        $adapter = $this->adapter($this->credential(['gpt-4o'], 'gpt-4o'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.model_not_available');

        $adapter->razreshitModel('acc-1', 'cred-1', 'unknown-model');
    }

    #[Test]
    public function poluchit_modeli_otdayot_default_i_nabor(): void
    {
        $adapter = $this->adapter($this->credential(['a', 'b'], 'b'));

        $models = $adapter->poluchitModeli('acc-1', 'cred-1');

        self::assertSame('b', $models->defaultModel);
        self::assertSame(['a', 'b'], $models->models);
    }

    /**
     * @param list<string> $models
     */
    private function credential(array $models, string $defaultModel): OpenCodeCredential
    {
        return OpenCodeCredential::restore(
            'cred-1',
            'acc-1',
            'Основные',
            'openai',
            'https://api.openai.com/v1',
            $models,
            $defaultModel,
            'cipher',
            '****1234',
            1,
            1_700_000_000,
            1_700_000_000,
        );
    }

    private function adapter(OpenCodeCredential $credential): AccountAdapter
    {
        $repository = $this->createMock(OpenCodeCredentialRepository::class);
        $repository->method('getById')->willReturn($credential);

        $accountApi = (new \ReflectionClass(AccountApi::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(AccountApi::class, 'openCodeCredentialRepository');
        $property->setValue($accountApi, $repository);

        return new AccountAdapter($accountApi);
    }
}
