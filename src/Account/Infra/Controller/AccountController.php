<?php

namespace App\Account\Infra\Controller;
use App\Account\Business\Command\BlockByAdmin;
use App\Account\Business\Command\ChangeEmailByAdmin;
use App\Account\Business\Command\ChangeMyLocale;
use App\Account\Business\Command\ChangeMyNickname;
use App\Account\Business\Command\ChangeMyPassword;
use App\Account\Business\Command\ChangeNicknameByAdmin;
use App\Account\Business\Command\ChangePasswordByAdmin;
use App\Account\Business\Command\ChangeRoleByAdmin;
use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Command\UnblockByAdmin;
use App\Account\Business\UseCase\BlockByAdminUseCase;
use App\Account\Business\UseCase\ChangeEmailByAdminUseCase;
use App\Account\Business\UseCase\ChangeMyLocaleUseCase;
use App\Account\Business\UseCase\ChangeMyNicknameUseCase;
use App\Account\Business\UseCase\ChangeMyPasswordUseCase;
use App\Account\Business\UseCase\ChangeNicknameByAdminUseCase;
use App\Account\Business\UseCase\ChangePasswordByAdminUseCase;
use App\Account\Business\UseCase\ChangeRoleByAdminUseCase;
use App\Account\Business\UseCase\PoluchitNastroikiAccountaUseCase;
use App\Account\Business\UseCase\PoluchitSpisokVsehPolzovateleiDlyAdministrirovaniyaQuery;
use App\Account\Business\UseCase\PoluchitSpisokVsehPolzovateleiQuery;
use App\Account\Business\UseCase\RegisterAccountUseCase;
use App\Account\Business\UseCase\UnblockByAdminUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/account')]
final class AccountController extends AbstractController
{
    #[Route('/register')]
    function register(RegisterAccount $command, RegisterAccountUseCase $useCase): Response
    {
        $id = $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::success(['id' => $id]));
    }

    #[Route('/changePasswordByAdmin')]
    function changePasswordByAdmin(ChangePasswordByAdmin $command, ChangePasswordByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/changeMyPassword')]
    function changeMyPassword(ChangeMyPassword $command, ChangeMyPasswordUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/changeNicknameByAdmin')]
    function changeNicknameByAdmin(ChangeNicknameByAdmin $command, ChangeNicknameByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/changeRoleByAdmin')]
    function changeRoleByAdmin(ChangeRoleByAdmin $command, ChangeRoleByAdminUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/changeMyNickname')]
    function changeMyNickname(ChangeMyNickname $command, ChangeMyNicknameUseCase $useCase): Response
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

    #[Route('/changeMyLocale')]
    function changeMyLocale(ChangeMyLocale $command, ChangeMyLocaleUseCase $useCase): Response
    {
        $useCase->handle($command);
        return $this->json(\App\Utils\Model\Reponse\Response::successStub());
    }

    #[Route('/poluchitNastroikiPolzovatelya')]
    function poluchitNastroikiPolzovatelya(PoluchitNastroikiAccountaUseCase $useCase): Response
    {
        return $this->json(\App\Utils\Model\Reponse\Response::success($useCase->handle()));
    }
}
