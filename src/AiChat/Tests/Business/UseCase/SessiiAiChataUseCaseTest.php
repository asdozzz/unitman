<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Business\UseCase;

use App\Account\Business\ReadModel\OpenCodeCredentialSecretReadModel;
use App\Account\Business\ReadModel\ProviderSessionPolicyReadModel;
use App\AiChat\Business\Command\SozdatSessiyuAiChata;
use App\AiChat\Business\Config\NastroikiAiChata;
use App\AiChat\Business\Model\FiltrSessiyAiChata;
use App\AiChat\Business\Model\PrichinaArhivaciiSessiiAiChata;
use App\AiChat\Business\Model\SessiyaAiChata;
use App\AiChat\Business\Model\SoobshenieAiChata;
use App\AiChat\Business\ReadModel\SessiyaAiChataReadModel;
use App\AiChat\Business\ReadModel\SpisokSessiyAiChataReadModel;
use App\AiChat\Business\UseCase\ArhivirovatSessiyuAiChataUseCase;
use App\AiChat\Business\UseCase\PoluchitSessiyuAiChataQuery;
use App\AiChat\Business\UseCase\PoluchitSpisokSessiyAiChataQuery;
use App\AiChat\Business\UseCase\SozdatSessiyuAiChataUseCase;
use App\AiChat\Business\UseCase\UdalitSessiyuAiChataUseCase;
use App\AiChat\Tests\Support\FakeRekvizityDlyaSessiiAiChata;
use App\AiChat\Tests\Support\FixedUuidGenerator;
use App\AiChat\Tests\Support\InMemorySessiyaAiChataRepository;
use App\AiChat\Tests\Support\InMemorySoobshenieAiChataRepository;
use App\Utils\Service\TestClockService;
use PHPUnit\Framework\Attributes\Test;
use Psr\Clock\ClockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Тесты use case сессий AI-чата на in-memory фейках.
 */
final class SessiiAiChataUseCaseTest extends TestCase
{
    private const CHAT_TTL = 86_400;
    private const PROVIDER_MAX_TTL = 300;

    // --- Создание ---

    #[Test]
    public function sozdanie_bez_credential_beret_pervye_rekvizity(): void
    {
        $rekvizity = $this->rekvizity();
        $rekvizity->credentialIds = ['cred-1', 'cred-2'];
        $rekvizity->credentials['cred-1'] = $this->credential('cred-1');
        $rekvizity->credentials['cred-2'] = $this->credential('cred-2');
        $rekvizity->policies['openai'] = $this->policy('openai');

        $repository = new InMemorySessiyaAiChataRepository();

        $readModel = $this->createUseCase($repository, $rekvizity)->vypolnit(new SozdatSessiyuAiChata());

        $this->assertSame('sess-1', $readModel->id);
        $this->assertSame('cred-1', $repository->sessii['sess-1']->credentialId());
        $this->assertSame(1, $repository->sohraneniy);
    }

    #[Test]
    public function sozdanie_bez_rekvizitov_daet_credential_required(): void
    {
        $rekvizity = $this->rekvizity();
        $rekvizity->credentialIds = [];

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ai.chat.credential_required');

        $this->createUseCase(new InMemorySessiyaAiChataRepository(), $rekvizity)
            ->vypolnit(new SozdatSessiyuAiChata());
    }

    #[Test]
    public function sozdanie_unknown_provider_daet_provider_not_supported(): void
    {
        $rekvizity = $this->rekvizity();
        $rekvizity->credentialIds = ['cred-1'];
        $rekvizity->credentials['cred-1'] = $this->credential('cred-1');
        $rekvizity->policyErrors['openai'] = 'account.opencode_credential.unknown_provider';

        $repository = new InMemorySessiyaAiChataRepository();

        try {
            $this->createUseCase($repository, $rekvizity)->vypolnit(new SozdatSessiyuAiChata());
            $this->fail('Ожидалась ошибка ai.chat.provider_not_supported');
        } catch (\DomainException $exception) {
            $this->assertSame('ai.chat.provider_not_supported', $exception->getMessage());
        }

        $this->assertSame(0, $repository->sohraneniy);
    }

    #[Test]
    public function sozdanie_blokiruetsya_gate_supports_ai_chat(): void
    {
        $rekvizity = $this->rekvizity();
        $rekvizity->credentialIds = ['cred-1'];
        $rekvizity->credentials['cred-1'] = $this->credential('cred-1');
        $rekvizity->policies['openai'] = $this->policy('openai', 'none', false);

        $repository = new InMemorySessiyaAiChataRepository();

        try {
            $this->createUseCase($repository, $rekvizity)->vypolnit(new SozdatSessiyuAiChata());
            $this->fail('Ожидалась ошибка ai.chat.provider_not_supported');
        } catch (\DomainException $exception) {
            $this->assertSame('ai.chat.provider_not_supported', $exception->getMessage());
        }

        $this->assertSame(0, $repository->sohraneniy);
    }

    #[Test]
    public function sozdanie_ispolzuet_model_po_umolchaniyu(): void
    {
        $rekvizity = $this->rekvizity();
        $rekvizity->credentialIds = ['cred-1'];
        $rekvizity->credentials['cred-1'] = $this->credential('cred-1', 'openai', 'model-default');
        $rekvizity->policies['openai'] = $this->policy('openai');

        $readModel = $this->createUseCase(new InMemorySessiyaAiChataRepository(), $rekvizity)
            ->vypolnit(new SozdatSessiyuAiChata());

        $this->assertSame('model-default', $readModel->model);
    }

    #[Test]
    public function sozdanie_s_header_politikoi_zapolnyaet_provider_session(): void
    {
        $rekvizity = $this->rekvizity();
        $rekvizity->credentialIds = ['cred-1'];
        $rekvizity->credentials['cred-1'] = $this->credential('cred-1', 'opencode');
        $rekvizity->policies['opencode'] = $this->policy(
            'opencode',
            'header',
            true,
            300,
            'x-opencode-session',
        );

        $repository = new InMemorySessiyaAiChataRepository();

        $this->createUseCase($repository, $rekvizity)->vypolnit(new SozdatSessiyuAiChata());

        $session = $repository->sessii['sess-1'];
        $this->assertSame('prov-1', $session->providerSessionId());
        $this->assertNotNull($session->providerSessionExpiresAt());
    }

    #[Test]
    public function sozdanie_s_politikoi_none_ne_zapolnyaet_provider_session(): void
    {
        $rekvizity = $this->rekvizity();
        $rekvizity->credentialIds = ['cred-1'];
        $rekvizity->credentials['cred-1'] = $this->credential('cred-1', 'openai');
        $rekvizity->policies['openai'] = $this->policy('openai');

        $repository = new InMemorySessiyaAiChataRepository();

        $this->createUseCase($repository, $rekvizity)->vypolnit(new SozdatSessiyuAiChata());

        $session = $repository->sessii['sess-1'];
        $this->assertNull($session->providerSessionId());
        $this->assertNull($session->providerSessionExpiresAt());
    }

    #[Test]
    public function sozdanie_primenyaet_ttl_iz_nastroek(): void
    {
        $rekvizity = $this->rekvizity();
        $rekvizity->credentialIds = ['cred-1'];
        $rekvizity->credentials['cred-1'] = $this->credential('cred-1', 'opencode');
        $rekvizity->policies['opencode'] = $this->policy(
            'opencode',
            'header',
            true,
            600,
            'x-opencode-session',
        );

        $repository = new InMemorySessiyaAiChataRepository();
        $nastroiki = new NastroikiAiChata(1_000, 300, 100);
        $useCase = new SozdatSessiyuAiChataUseCase(
            $repository,
            $rekvizity,
            new FixedUuidGenerator('sess-1', 'prov-1'),
            $nastroiki,
            new TestClockService(),
        );

        $before = time();
        $useCase->vypolnit(new SozdatSessiyuAiChata());
        $after = time();

        $session = $repository->sessii['sess-1'];
        $this->assertGreaterThanOrEqual($before + 1_000, $session->expiresAt());
        $this->assertLessThanOrEqual($after + 1_000, $session->expiresAt());
        $this->assertSame($session->createdAt() + 300, $session->providerSessionExpiresAt());
    }

    #[Test]
    public function sozdanie_obrezaet_title_do_256(): void
    {
        $rekvizity = $this->rekvizity();
        $rekvizity->credentialIds = ['cred-1'];
        $rekvizity->credentials['cred-1'] = $this->credential('cred-1');
        $rekvizity->policies['openai'] = $this->policy('openai');

        $readModel = $this->createUseCase(new InMemorySessiyaAiChataRepository(), $rekvizity)
            ->vypolnit(new SozdatSessiyuAiChata(title: '  '.str_repeat('ж', 300).'  '));

        $this->assertSame(SessiyaAiChata::TITLE_MAX_LENGTH, mb_strlen($readModel->title));
    }

    // --- Список ---

    #[Test]
    public function spisok_arhiviruet_istekshie_do_vyborki_i_probrasyvaet_filtr(): void
    {
        $repository = new InMemorySessiyaAiChataRepository();
        $spisok = new SpisokSessiyAiChataReadModel([$this->readModel('sess-1')], 10, 5, 1);
        $repository->spisokRezultat = $spisok;

        $filtr = new FiltrSessiyAiChata(includeArchived: true, search: 'q', limit: 10, offset: 5);

        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $result = (new PoluchitSpisokSessiyAiChataQuery($repository, new TestClockService($now)))
            ->vypolnit('account-1', $filtr);

        $this->assertSame(['arhivirovatIstekchie', 'spisok'], $repository->vyzovy);
        $this->assertSame('account-1', $repository->arhivirovatIstekchieAccountId);
        $this->assertSame($now->getTimestamp(), $repository->arhivirovatIstekchieNow);
        $this->assertSame($filtr, $repository->spisokFiltr);
        $this->assertSame('account-1', $repository->spisokAccountId);
        $this->assertSame($spisok, $result);
        $this->assertSame(1, $result->total);
        $this->assertFalse($result->hasMore());
    }

    #[Test]
    public function spisok_vozvrashchaet_metadannye_paginacii_i_has_more(): void
    {
        $repository = new InMemorySessiyaAiChataRepository();
        $repository->spisokRezultat = new SpisokSessiyAiChataReadModel(
            [$this->readModel('sess-1')],
            1,
            0,
            2,
        );

        $result = (new PoluchitSpisokSessiyAiChataQuery($repository, new TestClockService()))
            ->vypolnit('account-1', new FiltrSessiyAiChata(limit: 1, offset: 0));

        $this->assertSame(1, $result->limit);
        $this->assertSame(0, $result->offset);
        $this->assertSame(2, $result->total);
        $this->assertTrue($result->hasMore());
        $this->assertSame('sess-1', $result->items[0]->id);
    }

    // --- Получение ---

    #[Test]
    public function poluchenie_chuzhoi_sessii_daet_not_found(): void
    {
        $repository = new InMemorySessiyaAiChataRepository();
        $repository->sessii['sess-1'] = $this->session(accountId: 'account-2');

        $rekvizity = $this->rekvizity();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ai.chat.session_not_found');

        $this->getQuery($repository, $rekvizity)->vypolnit('sess-1');
    }

    #[Test]
    public function poluchenie_otsutstvuyushchei_sessii_daet_not_found(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ai.chat.session_not_found');

        $this->getQuery(new InMemorySessiyaAiChataRepository(), $this->rekvizity())->vypolnit('nope');
    }

    #[Test]
    public function avtoarhivaciya_na_granice_24_chasov_s_fiksirovannymi_chasami(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $expiresAt = $now->getTimestamp() + self::CHAT_TTL;

        $repository = new InMemorySessiyaAiChataRepository();
        $repository->sessii['sess-1'] = $this->session(expiresAt: $expiresAt);

        $soobsheniya = new InMemorySoobshenieAiChataRepository();
        $soobsheniya->dobavit(
            SoobshenieAiChata::polzovatelya('m1', 'sess-1', 1, 'pervyi', $now->getTimestamp()),
        );

        $clock = new TestClockService($now->modify('-1 second'));
        $query = new PoluchitSessiyuAiChataQuery(
            $repository,
            $soobsheniya,
            $this->rekvizity(),
            $clock,
        );

        $do_granicy = $query->vypolnit('sess-1');
        $this->assertFalse($do_granicy->sessiya->archived);
        $this->assertSame(0, $repository->sohraneniy);

        $clock->setNow($now->modify('+'.self::CHAT_TTL.' seconds'));
        $na_granice = $query->vypolnit('sess-1');

        $this->assertTrue($na_granice->sessiya->archived);
        $this->assertSame(PrichinaArhivaciiSessiiAiChata::Expired->value, $na_granice->sessiya->archiveReason);
        $this->assertCount(1, $na_granice->soobsheniya, 'история сохраняется при архивации');
        $this->assertSame('pervyi', $na_granice->sessiya->preview);
    }

    #[Test]
    public function poluchenie_istekshei_sessii_avtoarhiviruet_i_vozvrashchaet_istoriyu(): void
    {
        $repository = new InMemorySessiyaAiChataRepository();
        $repository->sessii['sess-1'] = $this->session(
            expiresAt: time() - 100,
            archivedAt: null,
        );

        $soobsheniya = new InMemorySoobshenieAiChataRepository();
        $soobsheniya->dobavit(
            SoobshenieAiChata::polzovatelya('m1', 'sess-1', 1, '  pervyi  ', time()),
            SoobshenieAiChata::assistenta('m2', 'sess-1', 2, 'vtoroy', [], time()),
        );

        $result = (new PoluchitSessiyuAiChataQuery(
            $repository,
            $soobsheniya,
            $this->rekvizity(),
            new TestClockService(),
        ))->vypolnit('sess-1');

        $this->assertTrue($result->sessiya->archived);
        $this->assertSame(PrichinaArhivaciiSessiiAiChata::Expired->value, $result->sessiya->archiveReason);
        $this->assertSame(1, $repository->sohraneniy);
        $this->assertSame(2, $result->sessiya->messageCount);
        $this->assertSame('pervyi', $result->sessiya->preview);
        $this->assertSame([1, 2], array_map(
            static fn ($message): int => $message->seq,
            $result->soobsheniya,
        ));
    }

    // --- Архивация ---

    #[Test]
    public function arhivaciya_vruchnuyu_stavit_manual_i_sohranyaet(): void
    {
        $repository = new InMemorySessiyaAiChataRepository();
        $repository->sessii['sess-1'] = $this->session(archivedAt: null);

        $result = $this->archiveUseCase($repository, $this->rekvizity())->vypolnit('sess-1');

        $this->assertTrue($result->archived);
        $this->assertSame(PrichinaArhivaciiSessiiAiChata::Manual->value, $result->archiveReason);
        $this->assertSame(1, $repository->sohraneniy);
    }

    #[Test]
    public function arhivaciya_idempotentna_dlya_uzhe_arhivnoi(): void
    {
        $repository = new InMemorySessiyaAiChataRepository();
        $repository->sessii['sess-1'] = $this->session(
            archivedAt: 123,
            archiveReason: PrichinaArhivaciiSessiiAiChata::Expired->value,
        );

        $result = $this->archiveUseCase($repository, $this->rekvizity())->vypolnit('sess-1');

        $this->assertTrue($result->archived);
        $this->assertSame(PrichinaArhivaciiSessiiAiChata::Expired->value, $result->archiveReason);
        $this->assertSame(0, $repository->sohraneniy);
    }

    #[Test]
    public function arhivaciya_chuzhoi_sessii_daet_not_found(): void
    {
        $repository = new InMemorySessiyaAiChataRepository();
        $repository->sessii['sess-1'] = $this->session(accountId: 'account-2', archivedAt: null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ai.chat.session_not_found');

        $this->archiveUseCase($repository, $this->rekvizity())->vypolnit('sess-1');
    }

    // --- Удаление ---

    #[Test]
    public function udalenie_aktivnoi_sessii_zapreshcheno(): void
    {
        $repository = new InMemorySessiyaAiChataRepository();
        $repository->sessii['sess-1'] = $this->session(archivedAt: null);

        try {
            $this->deleteUseCase($repository, $this->rekvizity())->vypolnit('sess-1');
            $this->fail('Ожидалась ошибка ai.chat.session_not_archived');
        } catch (\DomainException $exception) {
            $this->assertSame('ai.chat.session_not_archived', $exception->getMessage());
        }

        $this->assertNull($repository->udalenna);
    }

    #[Test]
    public function udalenie_arhivnoi_sessii_udaliyaet(): void
    {
        $repository = new InMemorySessiyaAiChataRepository();
        $repository->sessii['sess-1'] = $this->session(archivedAt: 100);

        $this->deleteUseCase($repository, $this->rekvizity())->vypolnit('sess-1');

        $this->assertNotNull($repository->udalenna);
        $this->assertArrayNotHasKey('sess-1', $repository->sessii);
    }

    #[Test]
    public function udalenie_chuzhoi_sessii_daet_not_found(): void
    {
        $repository = new InMemorySessiyaAiChataRepository();
        $repository->sessii['sess-1'] = $this->session(accountId: 'account-2', archivedAt: 100);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('ai.chat.session_not_found');

        $this->deleteUseCase($repository, $this->rekvizity())->vypolnit('sess-1');
    }

    // --- Фабрики ---

    private function createUseCase(
        InMemorySessiyaAiChataRepository $repository,
        FakeRekvizityDlyaSessiiAiChata $rekvizity,
        ?ClockInterface $clock = null,
    ): SozdatSessiyuAiChataUseCase {
        return new SozdatSessiyuAiChataUseCase(
            $repository,
            $rekvizity,
            new FixedUuidGenerator('sess-1', 'prov-1'),
            new NastroikiAiChata(self::CHAT_TTL, self::PROVIDER_MAX_TTL, 100),
            $clock ?? new TestClockService(),
        );
    }

    private function getQuery(
        InMemorySessiyaAiChataRepository $repository,
        FakeRekvizityDlyaSessiiAiChata $rekvizity,
        ?ClockInterface $clock = null,
    ): PoluchitSessiyuAiChataQuery {
        return new PoluchitSessiyuAiChataQuery(
            $repository,
            new InMemorySoobshenieAiChataRepository(),
            $rekvizity,
            $clock ?? new TestClockService(),
        );
    }

    private function archiveUseCase(
        InMemorySessiyaAiChataRepository $repository,
        FakeRekvizityDlyaSessiiAiChata $rekvizity,
        ?ClockInterface $clock = null,
    ): ArhivirovatSessiyuAiChataUseCase {
        return new ArhivirovatSessiyuAiChataUseCase(
            $repository,
            new InMemorySoobshenieAiChataRepository(),
            $rekvizity,
            $clock ?? new TestClockService(),
        );
    }

    private function deleteUseCase(
        InMemorySessiyaAiChataRepository $repository,
        FakeRekvizityDlyaSessiiAiChata $rekvizity,
    ): UdalitSessiyuAiChataUseCase {
        return new UdalitSessiyuAiChataUseCase($repository, $rekvizity);
    }

    private function rekvizity(string $accountId = 'account-1'): FakeRekvizityDlyaSessiiAiChata
    {
        return new FakeRekvizityDlyaSessiiAiChata($accountId);
    }

    private function credential(
        string $id = 'cred-1',
        string $provider = 'openai',
        string $model = 'gpt-4o-mini',
        string $accountId = 'account-1',
    ): OpenCodeCredentialSecretReadModel {
        return new OpenCodeCredentialSecretReadModel(
            $id,
            $accountId,
            $provider,
            'https://api.example.test',
            [$model],
            $model,
            $model,
            'secret-key',
        );
    }

    private function policy(
        string $provider,
        string $transport = 'none',
        bool $supportsAiChat = true,
        int $ttlSeconds = 300,
        ?string $key = null,
    ): ProviderSessionPolicyReadModel {
        return new ProviderSessionPolicyReadModel(
            $provider,
            $transport,
            $key,
            $ttlSeconds,
            $supportsAiChat,
        );
    }

    private function session(
        string $accountId = 'account-1',
        int $expiresAt = 2_000_000,
        ?int $archivedAt = null,
        ?string $archiveReason = null,
    ): SessiyaAiChata {
        return SessiyaAiChata::restore(
            'sess-1',
            $accountId,
            'Заголовок',
            'openai',
            'cred-1',
            'gpt-4o-mini',
            null,
            null,
            $expiresAt,
            $archivedAt,
            $archiveReason,
            1_000_000,
            1_000_000,
        );
    }

    private function readModel(string $id): SessiyaAiChataReadModel
    {
        return new SessiyaAiChataReadModel(
            $id,
            'Заголовок',
            'openai',
            'gpt-4o-mini',
            false,
            null,
            false,
            'preview',
            1_000_000,
            1_000_000,
            2_000_000,
            1,
        );
    }
}
