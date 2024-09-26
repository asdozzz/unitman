<?php
declare(strict_types=1);

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Project\BuildProject;
use App\Unitman\Business\Command\Project\DisableProject;
use App\Unitman\Business\Command\Project\DobavitPeremenuyuVProekt;
use App\Unitman\Business\Command\Project\DobavitSobitieIzHranilisha;
use App\Unitman\Business\Command\Project\EnableProject;
use App\Unitman\Business\Command\Project\ForceRemoveProject;
use App\Unitman\Business\Command\Project\GetActiveProjectList;
use App\Unitman\Business\Command\Project\GetProjectList;
use App\Unitman\Business\Command\Project\IzmenitZnacheniePeremenoiProekta;
use App\Unitman\Business\Command\Project\ObnovitNastroikiHuka;
use App\Unitman\Business\Command\Project\PoluchitMoiProekti;
use App\Unitman\Business\Command\Project\PoluchitSpisokPeremenihProekta;
use App\Unitman\Business\Command\Project\PoluchitSpisokPolzovateleiProekta;
use App\Unitman\Business\Command\Project\PoluchitSpisokVetokProekta;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaSborku;
use App\Unitman\Business\Command\Project\PostavitVOcheredNaUdalenie;
use App\Unitman\Business\Command\Project\RemoveProject;
use App\Unitman\Business\Command\Project\RemoveUserFromProject;
use App\Unitman\Business\Command\Project\UdalitPeremenuyuIzProekta;
use App\Unitman\Business\Command\Project\UpdateProjectData;
use App\Unitman\Business\UseCase\Project\AddProjectUseCase;
use App\Unitman\Business\UseCase\Project\AddUserToProjectUseCase;
use App\Unitman\Business\UseCase\Project\BuildProjectUseCase;
use App\Unitman\Business\UseCase\Project\DisableProjectUseCase;
use App\Unitman\Business\UseCase\Project\DobavitSobitieIzHranilishaUseCase;
use App\Unitman\Business\UseCase\Project\EnableProjectUseCase;
use App\Unitman\Business\UseCase\Project\ForceRemoveProjectUseCase;
use App\Unitman\Business\UseCase\Project\GetActiveProjectListQuery;
use App\Unitman\Business\UseCase\Project\GetProjectListQuery;
use App\Unitman\Business\UseCase\Project\ObnovitNastroikiHukaUseCase;
use App\Unitman\Business\UseCase\Project\PoluchitMoiProektiQuery;
use App\Unitman\Business\UseCase\Project\PoluchitSpisokPeremenihProektaQuery;
use App\Unitman\Business\UseCase\Project\PoluchitSpisokPolzovateleiProektaQuery;
use App\Unitman\Business\UseCase\Project\PoluchitSpisokVetokProektaQuery;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaSborkuUseCase;
use App\Unitman\Business\UseCase\Project\PostavitVOcheredNaUdalenieUseCase;
use App\Unitman\Business\UseCase\Project\ProzessDobavleniyaPeremenoiVProekt;
use App\Unitman\Business\UseCase\Project\ProzessIzmeneniyaZnacheniyaPeremenoiProekta;
use App\Unitman\Business\UseCase\Project\ProzessUdaleniyaPeremenoiIzProekta;
use App\Unitman\Business\UseCase\Project\RemoveProjectUseCase;
use App\Unitman\Business\UseCase\Project\RemoveUserFromProjectUseCase;
use App\Unitman\Business\UseCase\Project\UpdateProjectDataUseCase;
use App\Unitman\Infra\Repository\Unit\StatistikaPoProektuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/project')]
final class ProjectController extends AbstractController
{
    #[Route('/list', methods: ['POST'])]
    public function list(GetProjectList $command, GetProjectListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/activeList', methods: ['POST'])]
    public function activeList(GetActiveProjectList $command, GetActiveProjectListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/my', methods: ['POST'])]
    public function my(PoluchitMoiProekti $command, PoluchitMoiProektiQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/add', methods: ['POST'])]
    public function add(AddProject $command, AddProjectUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/updateData', methods: ['POST'])]
    public function updateData(UpdateProjectData $command, UpdateProjectDataUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/remove', methods: ['POST'])]
    public function remove(PostavitVOcheredNaUdalenie $command, PostavitVOcheredNaUdalenieUseCase $useCase): Response
    {
        try {
            $useCase->handle($command);
            return $this->json(\App\Utils\Model\Reponse\Response::successStub());
        } catch (\Error $error) {
            return new JsonResponse(\App\Utils\Model\Reponse\Response::error($error->getMessage()));
        }

    }

    #[Route('/forceRemove', methods: ['POST'])]
    public function forceRemove(ForceRemoveProject $command, ForceRemoveProjectUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/enable', methods: ['POST'])]
    public function enable(EnableProject $command, EnableProjectUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/disable', methods: ['POST'])]
    public function disable(DisableProject $command, DisableProjectUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/usersList', methods: ['POST'])]
    public function usersList(PoluchitSpisokPolzovateleiProekta $command, PoluchitSpisokPolzovateleiProektaQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/addUser', methods: ['POST'])]
    public function addUser(AddUserToProject $command, AddUserToProjectUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/removeUser', methods: ['POST'])]
    public function removeUser(RemoveUserFromProject $command, RemoveUserFromProjectUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/spisokPeremenihProekta', methods: ['POST'])]
    public function variablesList(PoluchitSpisokPeremenihProekta $command, PoluchitSpisokPeremenihProektaQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/dobavitPeremenuyu', methods: ['POST'])]
    public function addVariable(DobavitPeremenuyuVProekt $command, ProzessDobavleniyaPeremenoiVProekt $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/udalitPeremenuyu', methods: ['POST'])]
    public function removeVariable(UdalitPeremenuyuIzProekta $command, ProzessUdaleniyaPeremenoiIzProekta $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/izmenitPeremenuyu', methods: ['POST'])]
    public function izmenitPeremenuyu(IzmenitZnacheniePeremenoiProekta $command, ProzessIzmeneniyaZnacheniyaPeremenoiProekta $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/build', methods: ['POST'])]
    public function build(PostavitVOcheredNaSborku $command, PostavitVOcheredNaSborkuUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/obnovitNastroikiHuka', methods: ['POST'])]
    public function obnovitNastroikiHuka(ObnovitNastroikiHuka $command, ObnovitNastroikiHukaUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/branchesList', methods: ['POST'])]
    public function branchesList(PoluchitSpisokVetokProekta $command, PoluchitSpisokVetokProektaQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/{id}/hook', methods: ['POST'])]
    public function hook(string $id, Request $request, DobavitSobitieIzHranilishaUseCase $useCase): Response
    {
        $id = $useCase->handle(new DobavitSobitieIzHranilisha($id, $request->getContent()));
        return $this->json(\App\Utils\Model\Reponse\Response::success(['eventId' => $id]));
    }

    #[Route('/statistikaPoProektam', methods: ['POST'])]
    public function statistikaPoProektam(StatistikaPoProektuRepository $statistikaPoProektuRepository): Response
    {
        return $this->json(\App\Utils\Model\Reponse\Response::success($statistikaPoProektuRepository->getList()));
    }
}
