<?php

namespace App\Account\Infra\Controller;
use App\Account\Business\Command\BlockByAdmin;
use App\Account\Business\Command\ChangeEmailByAdmin;
use App\Account\Business\Command\ChangePasswordByAdmin;
use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Command\UnblockByAdmin;
use App\Account\Business\UseCase\BlockByAdminUseCase;
use App\Account\Business\UseCase\ChangeEmailByAdminUseCase;
use App\Account\Business\UseCase\ChangePasswordByAdminUseCase;
use App\Account\Business\UseCase\PoluchitSpisokVsehPolzovateleiDlyAdministrirovaniyaQuery;
use App\Account\Business\UseCase\PoluchitSpisokVsehPolzovateleiQuery;
use App\Account\Business\UseCase\RegisterAccountUseCase;
use App\Account\Business\UseCase\UnblockByAdminUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/account')]
final class AccountController extends AbstractController
{
    #[Route('/register')]
    function register(RegisterAccount $command, RegisterAccountUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/changePasswordByAdmin')]
    function changePassword(ChangePasswordByAdmin $command, ChangePasswordByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/changeEmailByAdmin')]
    function changeEmail(ChangeEmailByAdmin $command, ChangeEmailByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/blockByAdmin')]
    function block(BlockByAdmin $command, BlockByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/unblockByAdmin')]
    function unblock(UnblockByAdmin $command, UnblockByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/poluchitVsehPolzovatelei')]
    function poluchitVsehPolzovatelei(PoluchitSpisokVsehPolzovateleiQuery $useCase): Response
    {
        $result = $useCase->handle();
        return $this->json(\App\Utils\Model\Reponse\Response::success($result));
    }

    #[Route('/poluchitVsehPolzovateleiDlyAdministrorovaniya')]
    function poluchitVsehPolzovateleiDlyAdministrorovaniya(PoluchitSpisokVsehPolzovateleiDlyAdministrirovaniyaQuery $useCase): Response
    {
        $result = $useCase->handle();
        return $this->json(\App\Utils\Model\Reponse\Response::success($result));
    }
}
