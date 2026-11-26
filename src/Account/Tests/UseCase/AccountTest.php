<?php

namespace App\Account\Tests\UseCase;

use App\Account\Business\Command\ActivateAccountByAdmin;
use App\Account\Business\Command\BlockByAdmin;
use App\Account\Business\Command\ChangeEmailByAdmin;
use App\Account\Business\Command\ChangeMyNickname;
use App\Account\Business\Command\ChangeMyPassword;
use App\Account\Business\Command\ChangeNicknameByAdmin;
use App\Account\Business\Command\ChangePasswordByAdmin;
use App\Account\Business\Command\ChangeRoleByAdmin;
use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Command\RegisterPendingAccount;
use App\Account\Business\Command\UnblockByAdmin;
use App\Account\Business\Command\VerifyEmail;
use App\Account\Business\Model\Account\Role;
use App\Account\Business\Port\AccountRepository;
use App\Account\Business\Port\CanFindDouble;
use App\Account\Business\Port\CanGenerateEmailVerificationCode;
use App\Account\Business\Port\CanHashPassword;
use App\Account\Business\Port\CanSendEmailVerificationCode;
use App\Account\Business\Port\EmailVerificationCodeRepository;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\Port\UmeetOpoveshatOZaproseAktivacii;
use App\Account\Business\Port\SocialIdentityRepository;
use App\Account\Business\Port\UuidGenerator;
use App\Account\Business\ReadModel\OAuthUserReadModel;
use App\Account\Business\UseCase\ActivateAccountByAdminUseCase;
use App\Account\Business\UseCase\AuthenticateWithOAuthUseCase;
use App\Account\Business\UseCase\BlockByAdminUseCase;
use App\Account\Business\UseCase\ChangeEmailByAdminUseCase;
use App\Account\Business\UseCase\ChangeMyNicknameUseCase;
use App\Account\Business\UseCase\ChangeMyPasswordUseCase;
use App\Account\Business\UseCase\ChangeNicknameByAdminUseCase;
use App\Account\Business\UseCase\ChangePasswordByAdminUseCase;
use App\Account\Business\UseCase\ChangeRoleByAdminUseCase;
use App\Account\Business\UseCase\RegisterAccountUseCase;
use App\Account\Business\UseCase\RegisterPendingAccountUseCase;
use App\Account\Business\UseCase\RegisterSystemAccountUseCase;
use App\Account\Business\UseCase\UnblockByAdminUseCase;
use App\Account\Business\UseCase\VerifyEmailUseCase;
use App\Account\Infra\Adapter\PasswordEmailVerificationCodeHasher;
use App\Account\Infra\Adapter\SymfonySecurityService;
use App\Account\Infra\Repository\JWTUserRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class AccountTest extends AbstractTestCaseWithTransactionWrapper
{

    /**
     * @param string $email
     * @return array|false|mixed[]
     * @throws \Doctrine\DBAL\Exception
     */
    public function registerAccount(string $email, string $nickname = ""): array|false
    {
        $securityService = $this->getMockBuilder(SecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);

        self::$container->set(SecurityService::class, $securityService);

        $command = new RegisterAccount(
            $email,
            Role::ROLE_USER->value,
            'ru',
            $nickname
        );

        $sut = self::$container->get(RegisterAccountUseCase::class);
        /** @var RegisterAccountUseCase $sut */
        $sut->handle($command);

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $jwtRepository */
        $row = $jwtRepository->findUserByEmail($email);

        $this->assertTrue(!empty($row), 'Пользователь не найден');

        $this->assertEquals($email, $row['email']);
        $this->assertNotEquals('asd', $row['password']);
        $this->assertEquals(Role::ROLE_USER->value, $row['roles']);
        $this->assertEquals(0, $row['is_blocked']);
        $this->assertEquals('ru', $row['locale']);
        return $row;
    }

    #[Test]
    public function system_account_was_created(): void
    {
        $jwtRepository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $jwtRepository */
        $jwtRepository->truncate();

        $sut = self::$container->get(RegisterSystemAccountUseCase::class);
        /** @var RegisterSystemAccountUseCase $sut */
        $sut->handle();

        $user = $jwtRepository->getSystemAccount();

        $this->assertEquals('system@system.com', $user->getEmail());
        $this->assertEquals([Role::ROLE_SYSTEM->value], $user->getRoles());
        $this->assertEquals(false, $user->isBlocked());
    }

    #[Test]
    function password_was_changed_by_admin()
    {
        $row = $this->registerAccount('asd123@asd.ru');

        $id = $row['id'];
        $password = $row['password'];

        $command = new ChangePasswordByAdmin($id, 'www');

        $sut = self::$container->get(ChangePasswordByAdminUseCase::class);
        /** @var ChangePasswordByAdminUseCase $sut */
        $sut->handle($command);

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $jwtRepository */
        $jwtUser = $jwtRepository->getActiveById($id);

        $this->assertNotEquals($password, $jwtUser->getPassword());
    }

    #[Test]
    function password_was_changed()
    {
        $userId = Uuid::uuid7()->toString();
        $securityService = $this->getMockBuilder(SecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($userId);


        $uuidGenerator = $this->getMockBuilder(UuidGenerator::class)->getMock();
        $uuidGenerator->expects($this->any())->method('makeGuid')->willReturn($userId);
        self::$container->set(UuidGenerator::class, $uuidGenerator);

        self::$container->set(SecurityService::class, $securityService);

        $email = 'asd123123123@asd.ru';
        $command = new RegisterAccount(
            $email,
            Role::ROLE_USER->value,
            'ru'
        );

        $sut = self::$container->get(RegisterAccountUseCase::class);
        /** @var RegisterAccountUseCase $sut */
        $sut->handle($command);

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $jwtRepository */
        $row = $jwtRepository->findUserByEmail($email);

        $id = $row['id'];
        $password = $row['password'];

        $command = new ChangeMyPassword( 'www');

        $sut = self::$container->get(ChangeMyPasswordUseCase::class);
        /** @var ChangeMyPasswordUseCase $sut */
        $sut->handle($command);

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $jwtRepository */
        $jwtUser = $jwtRepository->getActiveById($id);

        $this->assertNotEquals($password, $jwtUser->getPassword());
    }

    #[Test]
    function nickname_was_changed_by_admin()
    {
        $row = $this->registerAccount('asd123@asd.ru', 'first');
        $this->assertEquals($row['nickname'], 'first');

        $command = new ChangeNicknameByAdmin($row['id'], 'second');

        $sut = self::$container->get(ChangeNicknameByAdminUseCase::class);
        /** @var ChangeNicknameByAdminUseCase $sut */
        $sut->handle($command);

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $jwtRepository */
        $jwtUser = $jwtRepository->getActiveById($row['id']);
        $this->assertEquals('second', $jwtUser->getNickname());
    }

    function nickname_was_changed()
    {
        $userId = Uuid::uuid7()->toString();
        $securityService = $this->getMockBuilder(SecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($userId);

        $uuidGenerator = $this->getMockBuilder(UuidGenerator::class)->getMock();
        $uuidGenerator->expects($this->any())->method('makeGuid')->willReturn($userId);
        self::$container->set(UuidGenerator::class, $uuidGenerator);

        self::$container->set(SecurityService::class, $securityService);

        $email = 'asd123123123@asd.ru';
        $command = new RegisterAccount(
            $email,
            Role::ROLE_USER->value,
            'ru',
            'first'
        );

        $sut = self::$container->get(RegisterAccountUseCase::class);
        /** @var RegisterAccountUseCase $sut */
        $sut->handle($command);

        $command = new ChangeMyNickname( 'second');

        $sut = self::$container->get(ChangeMyNicknameUseCase::class);
        /** @var ChangeMyNicknameUseCase $sut */
        $sut->handle($command);

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $jwtRepository */
        $jwtUser = $jwtRepository->getActiveById($userId);

        $this->assertEquals('second', $jwtUser->getNickname());
    }

    #[Test]
    function email_was_changed()
    {
        $row = $this->registerAccount('asd123@asd.ru');

        $id = $row['id'];

        $command = new ChangeEmailByAdmin($id, 'www123@www.ru');

        $sut = self::$container->get(ChangeEmailByAdminUseCase::class);
        /** @var ChangeEmailByAdminUseCase $sut */
        $sut->handle($command);

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $jwtRepository */
        $jwtUser = $jwtRepository->getActiveById($id);

        $this->assertEquals('www123@www.ru', $jwtUser->getEmail());
    }
    #[Test]
    function account_was_unblocked()
    {
        $row = $this->registerAccount('asd123@asd.ru');

        $id = $row['id'];

        $blockCommand = new BlockByAdmin($id, 'reason_stub');

        $sut = self::$container->get(BlockByAdminUseCase::class);
        /** @var BlockByAdminUseCase $sut */
        $sut->handle($blockCommand);

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $jwtRepository */
        $row = $jwtRepository->findRowById($id);

        $this->assertEquals(1, $row['is_blocked']);


        $unblockCommand = new UnblockByAdmin($id, 'reason_stub');

        $sut2 = self::$container->get(UnblockByAdminUseCase::class);
        /** @var UnblockByAdminUseCase $sut */
        $sut2->handle($unblockCommand);

        $row = $jwtRepository->findRowById($id);

        $this->assertEquals(0, $row['is_blocked']);
    }
    #[Test]
    function role_was_changed_by_admin()
    {
        $row = $this->registerAccount('asd123@asd.ru', 'first');
        $this->assertEquals($row['nickname'], 'first');

        $command = new ChangeRoleByAdmin($row['id'], Role::ROLE_ADMIN->value);

        $sut = self::$container->get(ChangeRoleByAdminUseCase::class);
        /** @var ChangeRoleByAdminUseCase $sut */
        $sut->handle($command);

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $jwtRepository */
        $jwtUser = $jwtRepository->getActiveById($row['id']);
        $this->assertEquals([Role::ROLE_ADMIN->value], $jwtUser->getRoles());

        $command = new ChangeRoleByAdmin($row['id'], Role::ROLE_ADMIN->value);

        $this->expectExceptionMessage('account.old_role_equal_new_role');
        $sut = self::$container->get(ChangeRoleByAdminUseCase::class);
        /** @var ChangeRoleByAdminUseCase $sut */
        $sut->handle($command);
    }

    #[Test]
    public function samostoyatelnaya_registraciya_sozdaet_ozhidayushchiy_akkaunt(): void
    {
        $userId = Uuid::uuid7()->toString();
        $uuidGenerator = $this->getMockBuilder(UuidGenerator::class)->getMock();
        $uuidGenerator->expects($this->any())->method('makeGuid')->willReturn($userId);
        self::$container->set(UuidGenerator::class, $uuidGenerator);

        $sut = self::$container->get(RegisterPendingAccountUseCase::class);
        \assert($sut instanceof RegisterPendingAccountUseCase);
        $sut->handle(new RegisterPendingAccount(
            uniqid('pending_', true) . '@asd.ru',
            'secret123',
            'ru',
            'pending',
        ));

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        \assert($jwtRepository instanceof JWTUserRepository);
        $row = $jwtRepository->findRowById($userId);

        $this->assertNotFalse($row);
        $this->assertSame(Role::ROLE_PENDING->value, $row['roles']);
        $this->assertTrue((bool) $row['is_pending_activation']);
        $this->assertFalse((bool) $row['is_email_verified']);
    }

    #[Test]
    public function admin_aktiviraet_ozhidayushchiy_akkaunt(): void
    {
        $userId = Uuid::uuid7()->toString();
        $uuidGenerator = $this->getMockBuilder(UuidGenerator::class)->getMock();
        $uuidGenerator->expects($this->any())->method('makeGuid')->willReturn($userId);
        self::$container->set(UuidGenerator::class, $uuidGenerator);

        $registration = self::$container->get(RegisterPendingAccountUseCase::class);
        \assert($registration instanceof RegisterPendingAccountUseCase);
        $registration->handle(new RegisterPendingAccount(
            uniqid('pending_', true) . '@asd.ru',
            'secret123',
            'ru',
            'pending',
        ));

        $accountRepository = self::$container->get(AccountRepository::class);
        \assert($accountRepository instanceof AccountRepository);
        $account = $accountRepository->getBy($userId);
        $account->confirmEmail();
        $accountRepository->save($account);

        $securityService = $this->getMockBuilder(SecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        self::$container->set(SecurityService::class, $securityService);

        $sut = self::$container->get(ActivateAccountByAdminUseCase::class);
        \assert($sut instanceof ActivateAccountByAdminUseCase);
        $sut->handle(new ActivateAccountByAdmin($userId));

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        \assert($jwtRepository instanceof JWTUserRepository);
        $row = $jwtRepository->findRowById($userId);

        $this->assertNotFalse($row);
        $this->assertSame(Role::ROLE_USER->value, $row['roles']);
        $this->assertFalse((bool) $row['is_pending_activation']);
        $this->assertTrue((bool) $row['is_email_verified']);
    }

    #[Test]
    public function kod_podtverzhdaet_email_i_ostavlyaet_akkaunt_na_aktivacii(): void
    {
        $userId = Uuid::uuid7()->toString();
        $uuidGenerator = $this->getMockBuilder(UuidGenerator::class)->getMock();
        $uuidGenerator->expects($this->any())->method('makeGuid')->willReturn($userId);
        self::$container->set(UuidGenerator::class, $uuidGenerator);

        $codeGenerator = $this->getMockBuilder(CanGenerateEmailVerificationCode::class)->getMock();
        $codeGenerator->expects($this->any())->method('generate')->willReturn('123456');

        $sender = $this->getMockBuilder(CanSendEmailVerificationCode::class)->getMock();
        $sender->expects($this->once())->method('otpravit');

        $codeHasher = new PasswordEmailVerificationCodeHasher();

        $email = uniqid('verify_', true) . '@asd.ru';
        $registration = new RegisterPendingAccountUseCase(
            self::$container->get(AccountRepository::class),
            self::$container->get(CanHashPassword::class),
            self::$container->get(CanFindDouble::class),
            self::$container->get(UuidGenerator::class),
            $codeGenerator,
            $codeHasher,
            self::$container->get(EmailVerificationCodeRepository::class),
            $sender,
            900,
        );
        $registration->handle(new RegisterPendingAccount($email, 'secret123', 'ru', 'verify'));

        $sut = new VerifyEmailUseCase(
            self::$container->get(EmailVerificationCodeRepository::class),
            $codeHasher,
            self::$container->get(AccountRepository::class),
            self::$container->get(UmeetOpoveshatOZaproseAktivacii::class),
            5,
        );
        $sut->handle(new VerifyEmail($email, '123456'));

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        \assert($jwtRepository instanceof JWTUserRepository);
        $row = $jwtRepository->findUserByEmail($email);

        $this->assertNotFalse($row);
        $this->assertTrue((bool) $row['is_email_verified']);
        $this->assertTrue((bool) $row['is_pending_activation']);
        $this->assertSame(Role::ROLE_PENDING->value, $row['roles']);
    }

    #[Test]
    public function aktivaciya_zapreshchena_ne_adminu(): void
    {
        $userId = Uuid::uuid7()->toString();
        $uuidGenerator = $this->getMockBuilder(UuidGenerator::class)->getMock();
        $uuidGenerator->expects($this->any())->method('makeGuid')->willReturn($userId);
        self::$container->set(UuidGenerator::class, $uuidGenerator);

        $registration = self::$container->get(RegisterPendingAccountUseCase::class);
        \assert($registration instanceof RegisterPendingAccountUseCase);
        $registration->handle(new RegisterPendingAccount(
            uniqid('pending_', true) . '@asd.ru',
            'secret123',
            'ru',
            'pending',
        ));

        $securityService = $this->getMockBuilder(SecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(false);
        self::$container->set(SecurityService::class, $securityService);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('security.access_denied');

        $sut = self::$container->get(ActivateAccountByAdminUseCase::class);
        \assert($sut instanceof ActivateAccountByAdminUseCase);
        $sut->handle(new ActivateAccountByAdmin($userId));
    }

    #[Test]
    public function admin_ne_aktiviraet_nepodtverzhdennyy_email(): void
    {
        $userId = Uuid::uuid7()->toString();
        $uuidGenerator = $this->getMockBuilder(UuidGenerator::class)->getMock();
        $uuidGenerator->expects($this->any())->method('makeGuid')->willReturn($userId);
        self::$container->set(UuidGenerator::class, $uuidGenerator);

        $registration = self::$container->get(RegisterPendingAccountUseCase::class);
        \assert($registration instanceof RegisterPendingAccountUseCase);
        $registration->handle(new RegisterPendingAccount(
            uniqid('pending_', true) . '@asd.ru',
            'secret123',
            'ru',
            'pending',
        ));

        $securityService = $this->getMockBuilder(SecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        self::$container->set(SecurityService::class, $securityService);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.email.not_verified');

        $sut = self::$container->get(ActivateAccountByAdminUseCase::class);
        \assert($sut instanceof ActivateAccountByAdminUseCase);
        $sut->handle(new ActivateAccountByAdmin($userId));
    }

    #[Test]
    public function oauth_sozdaet_podtverzhdennyy_ozhidayushchiy_akkaunt(): void
    {
        $email = uniqid('oauth_', true) . '@asd.ru';
        $providerUserId = 'google-' . $email;

        $sut = self::$container->get(AuthenticateWithOAuthUseCase::class);
        \assert($sut instanceof AuthenticateWithOAuthUseCase);
        $accountId = $sut->handle(
            'google',
            new OAuthUserReadModel($providerUserId, $email, 'OAuth User'),
            'ru',
        );

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        \assert($jwtRepository instanceof JWTUserRepository);
        $row = $jwtRepository->findRowById($accountId);

        $this->assertNotFalse($row);
        $this->assertSame(Role::ROLE_PENDING->value, $row['roles']);
        $this->assertTrue((bool) $row['is_pending_activation']);
        $this->assertTrue((bool) $row['is_email_verified']);

        $identityRepository = self::$container->get(SocialIdentityRepository::class);
        \assert($identityRepository instanceof SocialIdentityRepository);
        $this->assertNotNull($identityRepository->findByProviderAndUserId('google', $providerUserId));
    }

    #[Test]
    public function oauth_privyazyvaetsya_k_sushchestvuyushchemu_akkauntu_po_email(): void
    {
        $email = uniqid('oauth_link_', true) . '@asd.ru';
        $providerUserId = 'github-' . $email;
        $row = $this->registerAccount($email, 'existing');

        $sut = self::$container->get(AuthenticateWithOAuthUseCase::class);
        \assert($sut instanceof AuthenticateWithOAuthUseCase);
        $accountId = $sut->handle(
            'github',
            new OAuthUserReadModel($providerUserId, $email, 'GitHub User'),
            'ru',
        );

        $this->assertSame($row['id'], $accountId);

        $jwtRepository = self::$container->get(JWTUserRepository::class);
        \assert($jwtRepository instanceof JWTUserRepository);
        $linked = $jwtRepository->findRowById($accountId);

        $this->assertNotFalse($linked);
        $this->assertSame(Role::ROLE_USER->value, $linked['roles']);
        $this->assertTrue((bool) $linked['is_email_verified']);

        $identityRepository = self::$container->get(SocialIdentityRepository::class);
        \assert($identityRepository instanceof SocialIdentityRepository);
        $this->assertNotNull($identityRepository->findByProviderAndUserId('github', $providerUserId));
    }
}
