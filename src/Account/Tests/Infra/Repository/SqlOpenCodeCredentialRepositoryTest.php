<?php

declare(strict_types=1);

namespace App\Account\Tests\Infra\Repository;

use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Infra\Repository\SqlOpenCodeCredentialRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use PHPUnit\Framework\Attributes\Test;

/**
 * Хранение реквизитов и набора моделей: транзакционная замена набора,
 * batch-чтение и восстановление из legacy-колонки `model`.
 */
final class SqlOpenCodeCredentialRepositoryTest extends AbstractTestCaseWithTransactionWrapper
{
    private function repository(): SqlOpenCodeCredentialRepository
    {
        \assert(self::$db !== null);

        return new SqlOpenCodeCredentialRepository(self::$db);
    }

    /**
     * @param list<string> $models
     */
    private function sozdat(
        string $id,
        string $accountId,
        array $models,
        string $defaultModel,
        string $provider = 'openai',
    ): OpenCodeCredential {
        return OpenCodeCredential::create(
            $id,
            $accountId,
            'main',
            $provider,
            'https://api.openai.com/v1',
            $models,
            $defaultModel,
            'cipher-' . $id,
            '****1234',
            1000,
        );
    }

    #[Test]
    public function sohranyaet_i_chitaet_nabor_modelei_i_defolt(): void
    {
        $accountId = 'acc-repo-' . uniqid();
        $repository = $this->repository();

        $repository->save($this->sozdat('cred-repo-1', $accountId, ['gpt-4o', 'o3'], 'o3', 'openai'));

        $loaded = $repository->findById('cred-repo-1');

        $this->assertNotNull($loaded);
        $this->assertSame('openai', $loaded->getProvider());
        $this->assertSame(['gpt-4o', 'o3'], $loaded->getModels());
        $this->assertSame('o3', $loaded->getDefaultModel());
        $this->assertSame('o3', $loaded->getModel(), 'legacy model = default');
    }

    #[Test]
    public function legacy_kolonna_model_ravna_defoltu(): void
    {
        $accountId = 'acc-repo-' . uniqid();
        $this->repository()->save($this->sozdat('cred-repo-2', $accountId, ['a', 'b'], 'b'));

        \assert(self::$db !== null);
        $model = self::$db->fetchOne('select model from opencode_credential where id = :id', ['id' => 'cred-repo-2']);

        $this->assertSame('b', $model);
    }

    #[Test]
    public function obnovlenie_zamenyaet_nabor_i_perekluchaet_defolt(): void
    {
        $accountId = 'acc-repo-' . uniqid();
        $repository = $this->repository();
        $credential = $this->sozdat('cred-repo-3', $accountId, ['a', 'b'], 'a');

        $repository->save($credential);
        $credential->izmenit(null, null, null, ['c', 'd'], 'd', null, null, 2000);
        $repository->update($credential);

        $loaded = $repository->getById('cred-repo-3');

        $this->assertSame(['c', 'd'], $loaded->getModels());
        $this->assertSame('d', $loaded->getDefaultModel());

        \assert(self::$db !== null);
        $defaultRows = self::$db->fetchOne(
            'select count(*) from opencode_credential_model where credential_id = :id and is_default',
            ['id' => 'cred-repo-3'],
        );
        $this->assertSame(1, (int) $defaultRows, 'частичный уникальный индекс допускает один дефолт');
    }

    #[Test]
    public function spisok_chitaet_modeli_batch_bez_n_plus_one(): void
    {
        $accountId = 'acc-repo-' . uniqid();
        $repository = $this->repository();

        $repository->save($this->sozdat('cred-repo-4', $accountId, ['a', 'b'], 'a'));
        $repository->save(OpenCodeCredential::create(
            'cred-repo-5',
            $accountId,
            'second',
            'custom',
            'https://custom.local',
            ['x'],
            'x',
            'cipher-5',
            '****9999',
            1000,
        ));

        $list = $repository->findByAccountId($accountId);

        $this->assertCount(2, $list);
        $byId = [];
        foreach ($list as $item) {
            $byId[$item->getId()] = $item;
        }

        $this->assertSame(['a', 'b'], $byId['cred-repo-4']->getModels());
        $this->assertSame('custom', $byId['cred-repo-5']->getProvider());
    }

    #[Test]
    public function udalenie_ubiraet_i_stroki_modelei(): void
    {
        $accountId = 'acc-repo-' . uniqid();
        $repository = $this->repository();
        $repository->save($this->sozdat('cred-repo-6', $accountId, ['a', 'b'], 'a'));

        $repository->delete('cred-repo-6');

        $this->assertNull($repository->findById('cred-repo-6'));

        \assert(self::$db !== null);
        $left = self::$db->fetchOne(
            'select count(*) from opencode_credential_model where credential_id = :id',
            ['id' => 'cred-repo-6'],
        );
        $this->assertSame(0, (int) $left);
    }

    #[Test]
    public function bez_strok_modelei_agregat_vosstanavlivaetsya_iz_legacy_kolonki(): void
    {
        $accountId = 'acc-repo-' . uniqid();

        \assert(self::$db !== null);
        self::$db->insert('opencode_credential', [
            'id' => 'cred-legacy-1',
            'account_id' => $accountId,
            'name' => 'legacy',
            'url' => 'https://legacy.local',
            'model' => 'legacy-model',
            'api_key_cipher' => 'cipher-legacy',
            'api_key_hint' => '****0000',
            'cipher_version' => 1,
            'created_at' => 1000,
            'updated_at' => 1000,
        ]);

        $loaded = $this->repository()->getById('cred-legacy-1');

        $this->assertSame(['legacy-model'], $loaded->getModels());
        $this->assertSame('legacy-model', $loaded->getDefaultModel());
        $this->assertSame('custom', $loaded->getProvider(), 'колонка provider по умолчанию custom');
    }
}
