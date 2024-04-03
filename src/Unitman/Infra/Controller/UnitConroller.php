<?php

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Unit\GetMyUnits;
use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Command\Unit\GetUnitReadModelById;
use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\OstanovitUnit;
use App\Unitman\Business\Command\Unit\PodgotovitUnitKZapusku;
use App\Unitman\Business\Command\Unit\PoluchitKonfigUnita;
use App\Unitman\Business\Command\Unit\PoluchitPeremenieUnita;
use App\Unitman\Business\Command\Unit\SbrositPodgotovkuUnita;
use App\Unitman\Business\Command\Unit\SobratUnit;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\UdalitSlomaniyUnit;
use App\Unitman\Business\Command\Unit\UdalitUnit;
use App\Unitman\Business\Command\Unit\UstanovitResultatObnovleniyaUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatPodgotovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatSborkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatSbrosaPodgotovki;
use App\Unitman\Business\Command\Unit\UstanovitResultatUdaleniya;
use App\Unitman\Business\Command\Unit\UstanovitResultatZapuska;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;
use App\Unitman\Business\Command\Unit\ZapustitUnit;
use App\Unitman\Business\UseCase\Unit\GetMyUnitsQuery;
use App\Unitman\Business\UseCase\Unit\GetUnitByIdQuery;
use App\Unitman\Business\UseCase\Unit\GetUnitListQuery;
use App\Unitman\Business\UseCase\Unit\ObnovitKodUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\OstanovitUnitUseCase;
use App\Unitman\Business\UseCase\Unit\PodgotovitUnitKZapuskuUseCase;
use App\Unitman\Business\UseCase\Unit\PoluchitKonfigUnitaQuery;
use App\Unitman\Business\UseCase\Unit\PoluchitPeremenieUnitaQuery;
use App\Unitman\Business\UseCase\Unit\SbrositPodgotovkuUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\SobratUnitUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitSlomaniyUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatObnovleniyaUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatOstanovkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatPodgotovkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatSborkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatSbrosaPodgotovkiUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\ZapolnitPeremenieUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\ZapustitUnitUseCase;
use App\Unitman\Infra\Temporal\Workflow\OcheredUnitovWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Temporal\Client\WorkflowClient;

#[Route('/api/unit')]
final class UnitConroller extends AbstractController
{
    #[Route('/sozdat', methods: ['POST'])]
    public function sozdat(SozdatUnit $command, SozdatUnitUseCase $useCase, SobratUnitUseCase $sobratUnitUseCase): Response
    {
        try {
            $unitId = $useCase->handle($command);
            //TODO плохо, переделать на proccess manager
            $sobratUnitUseCase->handle(new SobratUnit($unitId));
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/list', methods: ['POST'])]
    public function list(GetUnitList $command, GetUnitListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/obnovit', methods: ['POST'])]
    public function read(GetUnitReadModelById $command, GetUnitByIdQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/moi', methods: ['POST'])]
    public function moi(GetMyUnits $command, GetMyUnitsQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/sobrat', methods: ['POST'])]
    public function sobrat(SobratUnit $command, SobratUnitUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/ustanovitResultatSborki', methods: ['POST'])]
    public function ustanovitResultatSborki(UstanovitResultatSborkiUnita $command, UstanovitResultatSborkiUnitaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/obnovitKodUnita', methods: ['POST'])]
    public function obnovitKodUnita(ObnovitKodUnita $command, ObnovitKodUnitaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/zapolnitPeremenie', methods: ['POST'])]
    public function zapolnitPeremenie(ZapolnitPeremenieUnita $command, ZapolnitPeremenieUnitaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/ustanovitResultatObnovleniya', methods: ['POST'])]
    public function ustanovitResultatObnovleniya(UstanovitResultatObnovleniyaUnita $command, UstanovitResultatObnovleniyaUnitaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/podgotovit', methods: ['POST'])]
    public function podgotovit(PodgotovitUnitKZapusku $command, PodgotovitUnitKZapuskuUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/ustanovitResultatPodgotovki', methods: ['POST'])]
    public function ustanovitResultatPodgotovki(UstanovitResultatPodgotovkiUnita $command, UstanovitResultatPodgotovkiUnitaUseCase $useCase, ZapustitUnitUseCase $zapustitUnitUseCase): Response
    {
        try {
            $isSuccess = $useCase->handle($command);
            //TODO плохо, переделать на proccess manager
            if ($isSuccess) {
                $zapustitUnitUseCase->handle(new ZapustitUnit($command->id));
            }
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/sbrositPodgotovku', methods: ['POST'])]
    public function sbrositPodgotovku(SbrositPodgotovkuUnita $command, SbrositPodgotovkuUnitaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/ustanovitResultatSbrosaPodgotovki', methods: ['POST'])]
    public function ustanovitResultatSbrosaPodgotovki(UstanovitResultatSbrosaPodgotovki $command, UstanovitResultatSbrosaPodgotovkiUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/zapustit', methods: ['POST'])]
    public function zapustit(ZapustitUnit $command, ZapustitUnitUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/ustanovitResultatZapuska', methods: ['POST'])]
    public function ustanovitResultatZapuska(UstanovitResultatZapuska $command, UstanovitResultatZapuskaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/ostanovit', methods: ['POST'])]
    public function ostanovit(OstanovitUnit $command, OstanovitUnitUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/ustanovitResultatOstanovki', methods: ['POST'])]
    public function ustanovitResultatOstanovki(UstanovitResultatOstanovkiUnita $command, UstanovitResultatOstanovkiUnitaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/udalit', methods: ['POST'])]
    public function udalit(UdalitUnit $command, UdalitUnitUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/ustanovitResultatUdaleniya', methods: ['POST'])]
    public function ustanovitResultatUdaleniya(UstanovitResultatUdaleniya $command, UstanovitResultatUdaleniyaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/udalitSlomaniyUnit', methods: ['POST'])]
    public function udalitSlomaniyUnit(UdalitSlomaniyUnit $command, UdalitSlomaniyUnitUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/poluchitKonfigUnita', methods: ['POST'])]
    public function poluchitKonfigUnita(PoluchitKonfigUnita $command, PoluchitKonfigUnitaQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/poluchitPeremenieUnita', methods: ['POST'])]
    public function poluchitPeremenieUnita(PoluchitPeremenieUnita $command, PoluchitPeremenieUnitaQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }
}
