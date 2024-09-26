<?php

namespace App\Account\Infra\EventListener;

use App\Account\Business\Model\Account\Role;
use App\Account\Business\Model\JWTUser;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_authentication_success')]
final class AuthenticationSuccessListener
{
    function __invoke(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof JWTUser) {
            return;
        }

        if (in_array(Role::ROLE_SYSTEM->value, $user->getRoles())) {
            throw new \DomainException('account.login_with_system_account_denied');
        }

        $data['user'] = array(
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        );

        $event->setData($data);
    }
}
