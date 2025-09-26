<?php

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Unit\GetMyUnits;
use App\Unitman\Business\Command\Unit\GetUnitList;
use App\Unitman\Business\Command\Unit\GetUnitReadModelById;
use App\Unitman\Business\Command\Unit\GetUnitRunnerJobs;
use App\Unitman\Business\Command\Unit\PoluchitShagiZadachiUnita;
use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\OstanovitUnit;
use App\Unitman\Business\Command\Unit\PeresobratModelSpisokUnitov;
use App\Unitman\Business\Command\Unit\PodgotovitUnitKZapusku;
use App\Unitman\Business\Command\Unit\PoluchitKonfigIzHranilisha;
use App\Unitman\Business\Command\Unit\PoluchitKonfigUnita;
use App\Unitman\Business\Command\Unit\PoluchitPeremenieUnita;
use App\Unitman\Business\Command\Unit\ProveritKonteinerUnita;
use App\Unitman\Business\Command\Unit\SbrositPodgotovkuUnita;
use App\Unitman\Business\Command\Unit\SobratUnit;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\UdalitSlomaniyUnit;
use App\Unitman\Business\Command\Unit\UdalitUnit;
use App\Unitman\Business\Command\Unit\VipolnitDeistviye;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;
use App\Unitman\Business\Command\Unit\ZapustitUnit;
use App\Unitman\Business\UseCase\Unit\DobavitProzesObnovleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesOstanovkiUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesPodgotovkiUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesSborkiUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesSbrosaPodgotovkiUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesVipolneniyaDeistviyaUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\GetMyUnitsQuery;
use App\Unitman\Business\UseCase\Unit\GetUnitByIdQuery;
use App\Unitman\Business\UseCase\Unit\GetUnitListQuery;
use App\Unitman\Business\UseCase\Unit\GetUnitRunnerJobsQuery;
use App\Unitman\Business\UseCase\Unit\GetUnitRunnerJobStepsQuery;
use App\Unitman\Business\UseCase\Unit\PoluchitKonfigIzHranilishaUseCase;
use App\Unitman\Business\UseCase\Unit\PoluchitKonfigUnitaQuery;
use App\Unitman\Business\UseCase\Unit\PoluchitPeremenieUnitaQuery;
use App\Unitman\Business\UseCase\Unit\ProveritKonteinerUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitSlomaniyUnitUseCase;
use App\Unitman\Business\UseCase\Unit\ZapolnitPeremenieUnitaUseCase;
use App\Unitman\Infra\Projection\SpisokUnitovProjection;
use App\Unitman\Infra\Service\RebuildService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/unit')]
final class UnitConroller extends AbstractController
{
    #[Route('/sozdat', methods: ['POST'])]
    public function sozdat(SozdatUnit $command, SozdatUnitUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
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
    public function sobrat(SobratUnit $command, DobavitProzesSborkiUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/obnovitKodUnita', methods: ['POST'])]
    public function obnovitKodUnita(ObnovitKodUnita $command, DobavitProzesObnovleniyaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
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

    #[Route('/podgotovit', methods: ['POST'])]
    public function podgotovit(PodgotovitUnitKZapusku $command, DobavitProzesPodgotovkiUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/sbrositPodgotovku', methods: ['POST'])]
    public function sbrositPodgotovku(SbrositPodgotovkuUnita $command, DobavitProzesSbrosaPodgotovkiUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/zapustit', methods: ['POST'])]
    public function zapustit(ZapustitUnit $command, DobavitProzesZapuskaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/ostanovit', methods: ['POST'])]
    public function ostanovit(OstanovitUnit $command, DobavitProzesOstanovkiUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/udalit', methods: ['POST'])]
    public function udalit(UdalitUnit $command, DobavitProzesUdaleniyaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command->id);
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

    #[Route('/poluchitVipolnenieZadachiRunnera', methods: ['POST'])]
    public function poluchitVipolnenieZadachiRunnera(GetUnitRunnerJobs $command, GetUnitRunnerJobsQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/poluchitShagiZadachiRunnera', methods: ['POST'])]
    public function poluchitShagiZadachiRunnera(PoluchitShagiZadachiUnita $command, GetUnitRunnerJobStepsQuery $query): Response
    {
        try {
            $data = $query->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/vipolnitDeistviye', methods: ['POST'])]
    public function vipolnitDeistviye(VipolnitDeistviye $command, DobavitProzesVipolneniyaDeistviyaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/proveritKonteinerUnita', methods: ['POST'])]
    public function proveritKonteinerUnita(ProveritKonteinerUnita $command, ProveritKonteinerUnitaUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/poluchitKonfigIzHranilisha', methods: ['POST'])]
    public function poluchitKonfigIzHranilisha(PoluchitKonfigIzHranilisha $command, PoluchitKonfigIzHranilishaUseCase $useCase): Response
    {
        try {
            $data = $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::success($data));
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }

    #[Route('/rebuildSpisokUnitov', methods: ['POST'])]
    public function rebuildSpisokUnitov(PeresobratModelSpisokUnitov $command, RebuildService $service): Response
    {
        try {
            $service->handle(SpisokUnitovProjection::CODE, $command->id);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Throwable $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }
    }
}
