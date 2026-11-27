<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Repo;

use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Command\Repo\ChangeCredentialsOfRepo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\UseCase\Repo\AddRepoUseCase;
use App\Unitman\Business\UseCase\Repo\ChangeCredentialsOfRepoUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Repo\RepoListRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

/**
 * Интеграционные проверки WS-2: шифрование токенов в event store и repo_list
 * и write-only контракт изменения учётных данных.
 *
 * Legacy-формат и миграция удалены: БД создаётся с нуля, открытого `token`
 * в событиях и проекции больше не существует.
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

        // После пересоздания тестовой БД проекция repo_list создаётся не
        // миграцией, а инициализацией проекции.
        self::$container->get(RepoListRepository::class)->init();

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

    private function eventCount(string $repoId): int
    {
        return (int)self::$db->fetchOne(
            'select count(*) from unitman_event_store where aggregate_root_id = ?',
            [$repoId],
        );
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

}
