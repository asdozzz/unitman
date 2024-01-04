<?php

namespace App\Account\Infra\EventListener;

use App\Account\Business\Model\JWTUser;
use App\Account\Infra\Repository\JWTUserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_decoded')]
final class JWTDecodedListener
{
    public function __construct(private JWTUserRepository $userRepository)
    {
    }

    public function __invoke(JWTDecodedEvent $event)
    {
        $payload = $event->getPayload();
        $user = $this->userRepository->loadUserByIdentifier($payload['username']);
        /** @var JWTUser $user*/
        $payload['id'] = $user->getId();
        $payload['roles'] = $user->getRoles();
        $payload['password'] = $user->getPassword();

        $event->setPayload($payload);
    }

}
