<?php

namespace App\Account\Infra\Adapter;

use App\Account\Business\Model\Account\Role;
use App\Account\Business\Model\JWTUser;
use App\Account\Business\Port\SecurityService;
use App\Utils\Exception\TranslatorKeyword;
use Symfony\Bundle\SecurityBundle\Security;

final class SymfonySecurityService implements SecurityService
{
    public function __construct(
        private Security $security
    )
    {
    }

    public function isAdmin(): bool
    {
        return $this->security->isGranted(Role::ROLE_ADMIN->value);
    }

    public function getCurrentUserId(): string
    {
        $user = $this->security->getUser();
        /** @var JWTUser|null $user*/
        if (empty($user)) {
            throw new \DomainException(TranslatorKeyword::ACCOUNT_NOT_AUTHENTICATED->value);
        }

        return $user->getId();
    }
}
