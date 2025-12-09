<?php

namespace App\Account\Tests\UseCase;

use App\Account\Business\Command\BlockByAdmin;
use App\Account\Business\Command\ChangeEmailByAdmin;
use App\Account\Business\Command\ChangeMyNickname;
use App\Account\Business\Command\ChangeMyPassword;
use App\Account\Business\Command\ChangeNicknameByAdmin;
use App\Account\Business\Command\ChangePasswordByAdmin;
use App\Account\Business\Command\ChangeRoleByAdmin;
use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Command\UnblockByAdmin;
use App\Account\Business\Model\Account\Role;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\Port\UuidGenerator;
use App\Account\Business\UseCase\BlockByAdminUseCase;
use App\Account\Business\UseCase\ChangeEmailByAdminUseCase;
use App\Account\Business\UseCase\ChangeMyNicknameUseCase;
use App\Account\Business\UseCase\ChangeMyPasswordUseCase;
use App\Account\Business\UseCase\ChangeNicknameByAdminUseCase;
use App\Account\Business\UseCase\ChangePasswordByAdminUseCase;
use App\Account\Business\UseCase\ChangeRoleByAdminUseCase;
use App\Account\Business\UseCase\RegisterAccountUseCase;
use App\Account\Business\UseCase\RegisterSystemAccountUseCase;
use App\Account\Business\UseCase\UnblockByAdminUseCase;
use App\Account\Infra\Adapter\SymfonySecurityService;
use App\Account\Infra\Repository\JWTUserRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
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
            'asd',
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

    /**
     * @test
     */
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

    /**
     * @test
     * */
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

    /**
     * @test
     * */
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
            'asd',
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

    /**
     * @test
     * */
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
            'asd',
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

    /**
     * @test
     * */
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
    /**
     * @test
     * */
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
    /**
     * @test
     * */
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
}
