<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Repo;

use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Command\Repo\ChangeCredentialsOfRepo;
use App\Unitman\Business\Model\Repo\Event\RepoWasAdded;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\UseCase\Repo\AddRepoUseCase;
use App\Unitman\Business\UseCase\Repo\ChangeCredentialsOfRepoUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Repo\RepoListRepository;
use App\Unitman\Infra\Service\RepoTokenEncryptionMigrator;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

/**
 * Интеграционные проверки WS-2: шифрование токенов в event store и repo_list,
 * write-only контракт изменения учётных данных, upcast legacy-событий и
 * идемпотентная opt-in миграция.
 *
 * Значения синтетические, покрыты allowlist WS-10.
 */
final class RepoTokenAtRestTest extends AbstractTestCaseWithTransactionWrapper
{
    private const TOKEN = 'TESTTOKENEXAMPLEVALUE';

    private const NEW_TOKEN = 'TESTTOKENNEWEXAMPLEZZZZ';

    private UnitmanSecurityService $security;

    public function setUp(): void
    {
        parent::setUp();

        $this->security = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $this->security->method('isAdmin')->willReturn(true);
        self::$container->set(UnitmanSecurityService::class, $this->security);
    }

    #[Test]
    public function add_and_change_never_store_plaintext_and_omit_keeps_cipher(): void
    {
        $repoId = $this->addRepo('repo-ws2-at-rest');

        $eventPayload = $this->eventPayload($repoId);
        self::assertStringNotContainsString(self::TOKEN, $eventPayload);
        self::assertStringContainsString('token_cipher', $eventPayload);
        self::assertStringContainsString('token_hint', $eventPayload);

        $repoList = $this->repoListRepository()->getById($repoId);
        self::assertNotSame(self::TOKEN, $repoList->tokenCipher);
        self::assertSame('ALUE', $repoList->tokenHint);
        self::assertSame(self::TOKEN, $this->shifr()->rasshifrovat($repoList->tokenCipher, $repoId));

        $projectionPayload = $this->projectionPayload($repoId);
        self::assertStringNotContainsString(self::TOKEN, $projectionPayload);
        self::assertStringNotContainsString('"token"', $projectionPayload);
        self::assertStringContainsString('"tokenCipher"', $projectionPayload);
        self::assertStringContainsString('"tokenHint"', $projectionPayload);

        $cipherBefore = $repoList->tokenCipher;
        $eventsBefore = $this->eventCount($repoId);

        // Пустой token и тот же URL — no-op: ни события, ни изменения cipher.
        $this->changeCredentials(new ChangeCredentialsOfRepo($repoId));
        self::assertSame($eventsBefore, $this->eventCount($repoId));
        self::assertSame($cipherBefore, $this->repoListRepository()->getById($repoId)->tokenCipher);

        // Пустой token, другой URL — cipher сохраняется, URL обновляется.
        $this->changeCredentials(new ChangeCredentialsOfRepo($repoId, null, 'https://gitlab2.example.test'));
        $afterUrl = $this->repoListRepository()->getById($repoId);
        self::assertSame($cipherBefore, $afterUrl->tokenCipher);
        self::assertSame('https://gitlab2.example.test', $afterUrl->repoUrl);
        self::assertSame(self::TOKEN, $this->shifr()->rasshifrovat($afterUrl->tokenCipher, $repoId));

        // Новый token — cipher меняется, hint обновляется.
        $this->changeCredentials(new ChangeCredentialsOfRepo($repoId, self::NEW_TOKEN));
        $afterNewToken = $this->repoListRepository()->getById($repoId);
        self::assertNotSame($cipherBefore, $afterNewToken->tokenCipher);
        self::assertSame('ZZZZ', $afterNewToken->tokenHint);
        self::assertSame(self::NEW_TOKEN, $this->shifr()->rasshifrovat($afterNewToken->tokenCipher, $repoId));

        self::assertStringNotContainsString(self::NEW_TOKEN, $this->eventPayload($repoId));
        self::assertStringNotContainsString(self::NEW_TOKEN, $this->projectionPayload($repoId));
    }

    #[Test]
    public function legacy_event_is_upcast_on_read_without_rewriting_storage(): void
    {
        $repoId = Uuid::uuid7()->toString();
        $this->insertLegacyEvent($repoId);

        $rawBefore = $this->eventPayload($repoId);
        self::assertStringContainsString('"token"', $rawBefore);

        $repo = self::$container->get(RepoRepository::class)->getById($repoId);
        self::assertSame(self::TOKEN, $repo->rasshifrovatToken($this->shifr()));
        self::assertSame('ALUE', $repo->getTokenHint());

        // Upcast не переписывает сохранённый payload.
        self::assertSame($rawBefore, $this->eventPayload($repoId));

        $message = json_decode($rawBefore, true, 512, JSON_THROW_ON_ERROR);
        $serializer = self::$container->get('unitman.event_serializer');
        $event = $serializer->unserializePayload($message)->payload();

        self::assertInstanceOf(RepoWasAdded::class, $event);
        self::assertNotSame('', $event->tokenCipher);
        self::assertSame('ALUE', $event->tokenHint);
    }

    #[Test]
    public function migration_is_idempotent_and_preserves_repo_url(): void
    {
        $repoId = Uuid::uuid7()->toString();
        $this->insertLegacyEvent($repoId);

        $this->repoListRepository()->init();
        self::$db->executeStatement(
            "insert into repo_list (id, payload) values (:id, cast(:payload as jsonb))",
            [
                'id' => $repoId,
                'payload' => json_encode([
                    'id' => $repoId,
                    'type' => RepoType::GITLAB->value,
                    'name' => 'repo-ws2-legacy',
                    'repoUrl' => 'https://gitlab.example.test/legacy',
                    'token' => self::TOKEN,
                    'confirmed' => true,
                ], JSON_THROW_ON_ERROR),
            ],
        );

        /** @var RepoTokenEncryptionMigrator $migrator */
        $migrator = self::$container->get(RepoTokenEncryptionMigrator::class);

        $plan = $migrator->plan();
        self::assertGreaterThanOrEqual(1, $plan['eventStore']);
        self::assertGreaterThanOrEqual(1, $plan['repoList']);

        $first = $migrator->apply();
        self::assertGreaterThanOrEqual(1, $first['eventStore']);
        self::assertGreaterThanOrEqual(1, $first['repoList']);

        $eventPayload = $this->eventPayload($repoId);
        self::assertStringNotContainsString(self::TOKEN, $eventPayload);
        self::assertStringNotContainsString('"token"', $eventPayload);
        self::assertStringContainsString('token_cipher', $eventPayload);
        self::assertStringContainsString('repo_url', $eventPayload); // repo_url сохранён

        $projectionPayload = $this->projectionPayload($repoId);
        self::assertStringNotContainsString(self::TOKEN, $projectionPayload);
        self::assertStringNotContainsString('"token"', $projectionPayload);
        self::assertStringContainsString('"tokenCipher"', $projectionPayload);
        self::assertStringContainsString('"tokenHint"', $projectionPayload);
        self::assertStringContainsString('"repoUrl"', $projectionPayload);
        self::assertStringContainsString('https://gitlab.example.test/legacy', $projectionPayload);

        $event = json_decode($eventPayload, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            self::TOKEN,
            $this->shifr()->rasshifrovat($event['payload']['token_cipher'], $repoId),
        );

        $payload = json_decode($projectionPayload, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(
            self::TOKEN,
            $this->shifr()->rasshifrovat($payload['tokenCipher'], $repoId),
        );

        $cipherAfterFirst = $payload['tokenCipher'];

        $second = $migrator->apply();
        self::assertSame(0, $second['eventStore']);
        self::assertSame(0, $second['repoList']);
        self::assertSame($cipherAfterFirst, $this->repoListRepository()->getById($repoId)->tokenCipher);
    }

    private function addRepo(string $name): string
    {
        $repoId = Uuid::uuid7()->toString();
        self::$container->set(CanGenerateGuid::class, new MemoryGuidGenerator([$repoId]));

        $useCase = self::$container->get(AddRepoUseCase::class);
        $useCase->handle(new AddRepo(
            RepoType::GITLAB->value,
            $name,
            self::TOKEN,
            'https://gitlab.example.test',
        ));

        return $repoId;
    }

    private function changeCredentials(ChangeCredentialsOfRepo $command): void
    {
        self::$container->get(ChangeCredentialsOfRepoUseCase::class)->handle($command);
    }

    private function repoListRepository(): RepoListRepository
    {
        return self::$container->get(RepoListRepository::class);
    }

    private function shifr(): UmeetShifrovatSekrety
    {
        return self::$container->get(UmeetShifrovatSekrety::class);
    }

    private function eventPayload(string $repoId): string
    {
        $raw = self::$db->fetchOne(
            'select payload::text from unitman_event_store where aggregate_root_id = ? order by id desc limit 1',
            [$repoId],
        );

        self::assertIsString($raw);

        return $raw;
    }

    private function projectionPayload(string $repoId): string
    {
        $raw = self::$db->fetchOne(
            'select payload::text from repo_list where id = ?',
            [$repoId],
        );

        self::assertIsString($raw);

        return $raw;
    }

    private function eventCount(string $repoId): int
    {
        return (int)self::$db->fetchOne(
            'select count(*) from unitman_event_store where aggregate_root_id = ?',
            [$repoId],
        );
    }

    private function insertLegacyEvent(string $repoId): void
    {
        $message = [
            'headers' => [
                '__event_type' => 'RepoWasAdded',
                '__aggregate_root_id' => $repoId,
                '__aggregate_root_type' => 'Repo',
                '__aggregate_root_version' => 1,
            ],
            'payload' => [
                'repo_id' => $repoId,
                'repo_type' => RepoType::GITLAB->value,
                'repo_name' => 'repo-ws2-legacy',
                'repo_url' => 'https://gitlab.example.test/legacy',
                'token' => self::TOKEN,
            ],
        ];

        self::$db->insert('unitman_event_store', [
            'event_id' => Uuid::uuid7()->toString(),
            'aggregate_root_id' => $repoId,
            'version' => 1,
            'payload' => json_encode($message, JSON_THROW_ON_ERROR),
        ]);
    }
}
