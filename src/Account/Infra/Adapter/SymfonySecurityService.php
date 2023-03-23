<?php

namespace App\Account\Infra\Adapter;

use App\Account\Business\Model\Account\Role;
use App\Account\Business\Port\SecurityService;
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

        if (empty($user)) {
            throw new \DomainException('security.need_auth');
        }

        return $user->getId();
    }
}
