<?php

namespace App\Initialization\Infra\Controller;

use App\Initialization\Business\Command\ObnovitDefoltnyiProxyHost;
use App\Initialization\Business\UseCase\ObnovitDefoltnyiProxyHostUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/initialization')]
final class InitializationController extends AbstractController
{
    #[Route('/proxy_host', methods: ['POST'])]
    public function updateProxyHost(#[MapRequestPayload] ObnovitDefoltnyiProxyHost $command, ObnovitDefoltnyiProxyHostUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Throwable $e) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($e->getMessage()));
        }
    }
}
