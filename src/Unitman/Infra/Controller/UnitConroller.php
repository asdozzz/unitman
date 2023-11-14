<?php

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Command\Unit\SobratUnit;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\UstanovitResultatSborkiUnita;
use App\Unitman\Business\UseCase\Unit\GetUnitListQuery;
use App\Unitman\Business\UseCase\Unit\SobratUnitUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatSborkiUnitaUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/unit')]
final class UnitConroller extends AbstractController
{
    #[Route('/sozdat', methods: ['POST'])]
    public function sozdat(SozdatUnit $command, SozdatUnitUseCase $useCase)
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/list', methods: ['POST'])]
    public function list(GetUnitList $command, GetUnitListQuery $query)
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/sobrat', methods: ['POST'])]
    public function sobrat(SobratUnit $command, SobratUnitUseCase $useCase)
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/ustanovitResultatSborki', methods: ['POST'])]
    public function ustanovitResultatSborki(UstanovitResultatSborkiUnita $command, UstanovitResultatSborkiUnitaUseCase $useCase)
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }
}
