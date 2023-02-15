<?php

namespace App\Account\Infra\Controller;
use App\Account\Business\Command\RegisterAccount;
use Ecotone\Modelling\CommandBus;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class TestController
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[Route('/account')]
    function check(): Response
    {
        try {
            $this->commandBus->send(new RegisterAccount('111', 'asd@asd.ru'));
            return new Response("<pre>" . print_r('OK', true) . "</pre>");
        } catch (\Exception $e) {
            return new Response("<pre>" . print_r($e->getMessage(), true) . "</pre>");
        }

    }
}
