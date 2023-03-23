<?php

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Command\Repo\ChangeCredentialsOfRepo;
use App\Unitman\Business\Command\Repo\CheckAccessToRepo;
use App\Unitman\Business\Command\Repo\DeleteRepo;
use App\Unitman\Business\UseCase\Repo\AddRepoUseCase;
use App\Unitman\Business\UseCase\Repo\ChangeCredentialsOfRepoUseCase;
use App\Unitman\Business\UseCase\Repo\CheckAccessToRepoUseCase;
use App\Unitman\Business\UseCase\Repo\DeleteRepoUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/repo')]
final class RepoController extends AbstractController
{
    #[Route('/add', methods: ['POST'])]
    public function add(AddRepo $command, AddRepoUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
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

    #[Route('/checkAccess', methods: ['POST'])]
    public function checkAccess(CheckAccessToRepo $command, CheckAccessToRepoUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }
}
