<?php

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Command\Repo\ChangeCredentialsOfRepo;
use App\Unitman\Business\Command\Repo\CheckAccessToRepo;
use App\Unitman\Business\Command\Repo\DeleteRepo;
use App\Unitman\Business\Command\Repo\GetActiveRepoList;
use App\Unitman\Business\Command\Repo\GetRepoList;
use App\Unitman\Business\Command\Repo\GetRepoTypeList;
use App\Unitman\Business\UseCase\Repo\AddRepoUseCase;
use App\Unitman\Business\UseCase\Repo\ChangeCredentialsOfRepoUseCase;
use App\Unitman\Business\UseCase\Repo\CheckAccessToRepoUseCase;
use App\Unitman\Business\UseCase\Repo\DeleteRepoUseCase;
use App\Unitman\Business\UseCase\Repo\GetActiveRepoListQuery;
use App\Unitman\Business\UseCase\Repo\GetRepoListQuery;
use App\Unitman\Business\UseCase\Repo\GetRepoTypeListQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/repo')]
final class RepoController extends AbstractController
{
    #[Route('/add', methods: ['POST'])]
    public function add(AddRepo $command, AddRepoUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/list', methods: ['POST'])]
    public function list(GetRepoList $command, GetRepoListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/activeList', methods: ['POST'])]
    public function activeList(GetActiveRepoList $command, GetActiveRepoListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/type/list', methods: ['POST'])]
    public function typeList(GetRepoTypeList $command, GetRepoTypeListQuery $query): Response
    {
        $data = $query->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success($data));
    }

    #[Route('/delete', methods: ['POST'])]
    public function delete(DeleteRepo $command, DeleteRepoUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/changeCredentials', methods: ['POST'])]
    public function changeCredentials(ChangeCredentialsOfRepo $command, ChangeCredentialsOfRepoUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/confirm', methods: ['POST'])]
    public function checkAccess(CheckAccessToRepo $command, CheckAccessToRepoUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }
}
