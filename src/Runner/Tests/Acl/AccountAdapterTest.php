<?php

declare(strict_types=1);

namespace App\Runner\Tests\Acl;

use App\Account\Api\AccountApi;
use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\Account\Business\Port\UmeetShifrovatSekrety;
use App\Runner\Acl\AccountAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * AccountAdapter прокидывает модель в AccountApi: `null` — дефолтная модель
 * реквизитов, непустая — обязана входить в набор; в результат попадает уже
 * разрешённая модель.
 *
 * AccountApi и его инфраструктурные зависимости — final, поэтому реальный
 * объект собирается через reflection с подменой репозитория и дешифратора,
 * как в OpenCode\Tests\Acl\AccountAdapterTest.
 */
final class AccountAdapterTest extends TestCase
{
    #[Test]
    public function bez_modeli_vozvrashchaetsya_default_rekvizitov(): void
    {
        $adapter = $this->adapter($this->credential(['gpt-4o', 'gpt-4o-mini'], 'gpt-4o-mini'));

        $rekvizity = $adapter->poluchitRekvizity('acc-1', 'cred-1');

        self::assertSame('gpt-4o-mini', $rekvizity->model);
        self::assertSame('https://api.openai.com/v1', $rekvizity->url);
        self::assertSame('decrypted-key', $rekvizity->apiKey);
    }

    #[Test]
    public function peredannaya_model_iz_nabora_vozvrashchaetsya_kak_est(): void
    {
        $adapter = $this->adapter($this->credential(['gpt-4o', 'gpt-4o-mini'], 'gpt-4o-mini'));

        $rekvizity = $adapter->poluchitRekvizity('acc-1', 'cred-1', 'gpt-4o');

        self::assertSame('gpt-4o', $rekvizity->model);
    }

    #[Test]
    public function model_vne_nabora_dayot_oshibku(): void
    {
        $adapter = $this->adapter($this->credential(['gpt-4o'], 'gpt-4o'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.model_not_available');

        $adapter->poluchitRekvizity('acc-1', 'cred-1', 'unknown-model');
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

        $decryptor = $this->createMock(UmeetShifrovatSekrety::class);
        $decryptor->method('rasshifrovat')->willReturn('decrypted-key');

        $accountApi = (new \ReflectionClass(AccountApi::class))->newInstanceWithoutConstructor();

        $repositoryProperty = new \ReflectionProperty(AccountApi::class, 'openCodeCredentialRepository');
        $repositoryProperty->setValue($accountApi, $repository);

        $decryptorProperty = new \ReflectionProperty(AccountApi::class, 'umeetShifrovatSekrety');
        $decryptorProperty->setValue($accountApi, $decryptor);

        return new AccountAdapter($accountApi);
    }
}
