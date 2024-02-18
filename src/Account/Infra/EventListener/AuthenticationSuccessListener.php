<?php

namespace App\Account\Infra\EventListener;

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

        if (!$user instanceof JWTUserInterface) {
            return;
        }

        $data['user'] = array(
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        );

        $event->setData($data);
    }
}
