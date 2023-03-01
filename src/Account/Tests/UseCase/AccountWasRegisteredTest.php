<?php

namespace App\Account\Tests\UseCase;

use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Model\Account\Role;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\UseCase\RegisterAccountUseCase;
use App\Account\Infra\Adapter\SymfonySecurityService;
use App\Account\Infra\Repository\JWTUserRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;

final class AccountWasRegisteredTest extends AbstractTestCaseWithTransactionWrapper
{
    function test()
    {
        $securityService = $this->getMockBuilder(SecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);

        self::$container->set(SecurityService::class, $securityService);

        $email = 'asd123@asd.ru';
        $command = new RegisterAccount(
            $email,
            'asd',
            Role::ROLE_USER->value
        );

        $sut = self::$container->get(RegisterAccountUseCase::class);
        /** @var RegisterAccountUseCase $sut*/
        $sut->handle($command);



        $jwtRepository = self::$container->get(JWTUserRepository::class);
        /** @var JWTUserRepository $jwtRepository*/
        $row = $jwtRepository->findUserByEmail($email);

        $this->assertEquals($email, $row['email']);
        $this->assertNotEquals('asd', $row['password']);
        $this->assertEquals(Role::ROLE_USER->value, $row['roles']);
        $this->assertEquals(0, $row['is_blocked']);
    }
}
