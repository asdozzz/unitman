<?php

namespace App\Initialization\Infra\Controller;

use App\Initialization\Business\Command\ObnovitDefoltnyiProxyHost;
use App\Initialization\Business\Port\InitializationRepository;
use App\Initialization\Business\UseCase\ObnovitDefoltnyiProxyHostUseCase;
use App\Initialization\Infra\Repository\SqlInitializationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/initialization')]
final class InitializationController extends AbstractController
{
    #[Route('/proxy_host', methods: ['PUT'])]
    public function updateProxyHost(#[MapRequestPayload] ObnovitDefoltnyiProxyHost $command, ObnovitDefoltnyiProxyHostUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Throwable $e) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($e->getMessage()));
        }
    }

    #[Route('/list', methods: ['GET'])]
    public function list(InitializationRepository $repository): JsonResponse
    {
        try {
            return $this->json(\App\Utils\Model\Reponse\Response::success($repository->getAll()));
        } catch (\Throwable $e) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($e->getMessage()));
        }
    }
}
