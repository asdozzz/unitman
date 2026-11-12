<?php

namespace App\Account\Tests\UseCase;

use App\Account\Business\Command\BlockByAdmin;
use App\Account\Business\Command\ObnovitToken;
use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Model\Account\RefreshToken;
use App\Account\Business\Model\Account\Role;
use App\Account\Business\Port\CanGenerateRefreshToken;
use App\Account\Business\Port\RefreshTokenRepository;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\UseCase\BlockByAdminUseCase;
use App\Account\Business\UseCase\ObnovitTokenUseCase;
use App\Account\Business\UseCase\RegisterAccountUseCase;
use App\Account\Business\UseCase\VipustitRefreshTokenUseCase;
use App\Account\Infra\Repository\JWTUserRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use PHPUnit\Framework\Attributes\Test;

final class RefreshTokenTest extends AbstractTestCaseWithTransactionWrapper
{
    private function registerAccount(string $email): string
    {
        $securityService = $this->getMockBuilder(SecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);

        self::$container->set(SecurityService::class, $securityService);

        $sut = self::$container->get(RegisterAccountUseCase::class);
        /** @var RegisterAccountUseCase $sut */
        $accountId = $sut->handle(
            new RegisterAccount($email, 'asd', Role::ROLE_USER->value, 'ru')
        );

        $this->assertNotEmpty($accountId);

        return $accountId;
    }

    private function issueRefreshToken(string $accountId): string
    {
        $sut = self::$container->get(VipustitRefreshTokenUseCase::class);
        /** @var VipustitRefreshTokenUseCase $sut */
        $refreshToken = $sut->handle($accountId);

        $this->assertNotEmpty($refreshToken);

        return $refreshToken;
    }

    #[Test]
    public function refresh_token_was_issued_for_account(): void
    {
        $accountId = $this->registerAccount('refresh_issued@asd.ru');

        $refreshTokenValue = $this->issueRefreshToken($accountId);

        $generator = self::$container->get(CanGenerateRefreshToken::class);
        /** @var CanGenerateRefreshToken $generator */
        $repository = self::$container->get(RefreshTokenRepository::class);
        /** @var RefreshTokenRepository $repository */

        $storedToken = $repository->findByHash($generator->hash($refreshTokenValue));

        $this->assertNotNull($storedToken, 'Refresh-токен не сохранён');
        $this->assertEquals($accountId, $storedToken->getAccountId());
        $this->assertFalse($storedToken->isRevoked());
        $this->assertFalse($storedToken->isExpired(time()));
        $this->assertNotEquals($refreshTokenValue, $storedToken->getTokenHash(), 'В БД не должен попадать сам токен');
    }

    #[Test]
    public function token_pair_was_refreshed_and_old_token_was_revoked(): void
    {
        $accountId = $this->registerAccount('refresh_pair@asd.ru');
        $refreshTokenValue = $this->issueRefreshToken($accountId);

        $sut = self::$container->get(ObnovitTokenUseCase::class);
        /** @var ObnovitTokenUseCase $sut */
        $result = $sut->handle(new ObnovitToken($refreshTokenValue));

        $this->assertNotEmpty($result->token);
        $this->assertNotEmpty($result->refreshToken);
        $this->assertNotEquals($refreshTokenValue, $result->refreshToken, 'Refresh-токен должен ротироваться');

        $generator = self::$container->get(CanGenerateRefreshToken::class);
        /** @var CanGenerateRefreshToken $generator */
        $repository = self::$container->get(RefreshTokenRepository::class);
        /** @var RefreshTokenRepository $repository */

        $oldToken = $repository->findByHash($generator->hash($refreshTokenValue));
        $this->assertNotNull($oldToken);
        $this->assertTrue($oldToken->isRevoked(), 'Старый refresh-токен должен быть отозван');

        $newToken = $repository->findByHash($generator->hash($result->refreshToken));
        $this->assertNotNull($newToken, 'Новый refresh-токен не сохранён');
        $this->assertFalse($newToken->isRevoked());
        $this->assertEquals($accountId, $newToken->getAccountId());
    }

    #[Test]
    public function revoked_token_cannot_be_used_again(): void
    {
        $accountId = $this->registerAccount('refresh_reuse@asd.ru');
        $refreshTokenValue = $this->issueRefreshToken($accountId);

        $sut = self::$container->get(ObnovitTokenUseCase::class);
        /** @var ObnovitTokenUseCase $sut */
        $sut->handle(new ObnovitToken($refreshTokenValue));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.refresh_token.revoked');

        $sut->handle(new ObnovitToken($refreshTokenValue));
    }

    #[Test]
    public function unknown_token_is_rejected(): void
    {
        $sut = self::$container->get(ObnovitTokenUseCase::class);
        /** @var ObnovitTokenUseCase $sut */

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.refresh_token.not_found');

        $sut->handle(new ObnovitToken('unknown-refresh-token-value'));
    }

    #[Test]
    public function empty_token_is_rejected(): void
    {
        $sut = self::$container->get(ObnovitTokenUseCase::class);
        /** @var ObnovitTokenUseCase $sut */

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.refresh_token.empty');

        $sut->handle(new ObnovitToken(''));
    }

    #[Test]
    public function expired_token_is_rejected(): void
    {
        $accountId = $this->registerAccount('refresh_expired@asd.ru');

        $generator = self::$container->get(CanGenerateRefreshToken::class);
        /** @var CanGenerateRefreshToken $generator */
        $repository = self::$container->get(RefreshTokenRepository::class);
        /** @var RefreshTokenRepository $repository */

        $value = $generator->generate();
        $repository->save(RefreshToken::issue($generator->hash($value), $accountId, time() - 10));

        $sut = self::$container->get(ObnovitTokenUseCase::class);
        /** @var ObnovitTokenUseCase $sut */

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.refresh_token.expired');

        $sut->handle(new ObnovitToken($value));
    }

    #[Test]
    public function token_cannot_be_refreshed_for_blocked_account(): void
    {
        $accountId = $this->registerAccount('refresh_blocked@asd.ru');
        $refreshTokenValue = $this->issueRefreshToken($accountId);

        $repository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $repository */
        $user = $repository->getById($accountId);
        $user->block();
        $repository->update($user);

        $sut = self::$container->get(ObnovitTokenUseCase::class);
        /** @var ObnovitTokenUseCase $sut */

        $this->expectException(\DomainException::class);

        $sut->handle(new ObnovitToken($refreshTokenValue));
    }

    #[Test]
    public function blocking_account_revokes_all_refresh_tokens(): void
    {
        $accountId = $this->registerAccount('refresh_block_revoke@asd.ru');
        $refreshTokenValue = $this->issueRefreshToken($accountId);

        $sut = self::$container->get(BlockByAdminUseCase::class);
        /** @var BlockByAdminUseCase $sut */
        $sut->handle(new BlockByAdmin($accountId, 'test block'));

        $generator = self::$container->get(CanGenerateRefreshToken::class);
        /** @var CanGenerateRefreshToken $generator */
        $repository = self::$container->get(RefreshTokenRepository::class);
        /** @var RefreshTokenRepository $repository */

        $storedToken = $repository->findByHash($generator->hash($refreshTokenValue));

        $this->assertNotNull($storedToken);
        $this->assertTrue($storedToken->isRevoked(), 'Все refresh-токены должны отзываться при блокировке');
    }
}
