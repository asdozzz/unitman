<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

use App\OpenCode\Business\Model\IstochnikRekvizitovOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;

/**
 * Выбирает реквизиты автора хода.
 *
 * Порядок для ключа: запрос -> личные реквизиты участника -> реквизиты сессии
 * (только для владельца) -> первые реквизиты автора.
 *
 * Реквизиты сессии — легаси-фолбэк: unit-сессии и старые workspace хранят ключ
 * создателя, и владелец должен продолжать работать без настройки. Для
 * не-владельца этот фолбэк запрещён: иначе ход чужого участника списался бы
 * с бюджета владельца.
 */
final class OpredelitelRekvizitovHodaOpenCode
{
    public function __construct(private readonly UmeetProveryatRekvizityOpenCode $credentials)
    {
    }

    /**
     * @throws \DomainException `opencode.session.credential_required`
     *                          или ошибка владения реквизитами
     */
    public function opredelit(
        SessiyaOpenCode $session,
        string $accountId,
        ?UchastnikSessii $uchastnik,
        ?string $credentialIdIzZaprosa,
    ): IstochnikRekvizitovOpenCode {
        return $this->vybrat(
            $session->getAccountId(),
            $session->getCredentialId(),
            $session->getModel(),
            $session->prinadlezhit($accountId),
            $accountId,
            $uchastnik,
            $credentialIdIzZaprosa,
        );
    }

    /**
     * То же правило для самостоятельного workspace-агрегата.
     */
    public function opredelitDlyaWorkspace(
        WorkspaceSession $workspace,
        string $accountId,
        ?UchastnikSessii $uchastnik,
        ?string $credentialIdIzZaprosa,
    ): IstochnikRekvizitovOpenCode {
        return $this->vybrat(
            $workspace->getAccountId(),
            $workspace->getCredentialId(),
            $workspace->getModel(),
            $workspace->prinadlezhit($accountId),
            $accountId,
            $uchastnik,
            $credentialIdIzZaprosa,
        );
    }

    private function vybrat(
        string $vladelesAccountId,
        string $credentialIdSessii,
        ?string $modelSessii,
        bool $vladelesSessii,
        string $accountId,
        ?UchastnikSessii $uchastnik,
        ?string $credentialIdIzZaprosa,
    ): IstochnikRekvizitovOpenCode {
        if (trim($vladelesAccountId) === '') {
            throw new \DomainException('opencode.session.not_found');
        }

        $credentialId = $this->pervyjNepustoj([
            $credentialIdIzZaprosa,
            $uchastnik?->getDefaultCredentialId(),
            $vladelesSessii ? $credentialIdSessii : null,
        ]);

        if ($credentialId === null) {
            $credentialId = $this->credentials->poluchitPervieRekvizityAvtora($accountId);
        }

        if ($credentialId === null) {
            throw new \DomainException('opencode.session.credential_required');
        }

        // Чужой ключ неотличим от несуществующего. Проверка выполняется до
        // старта хода, чтобы не оставить висящий workflow.
        $this->credentials->trebovatVladelca($accountId, $credentialId);

        $model = $this->pervyjNepustoj([
            $uchastnik?->getDefaultModel(),
            $vladelesSessii ? $modelSessii : null,
        ]);

        return new IstochnikRekvizitovOpenCode($accountId, $credentialId, $model);
    }

    /**
     * @param list<string|null> $kandidaty
     */
    private function pervyjNepustoj(array $kandidaty): ?string
    {
        foreach ($kandidaty as $kandidat) {
            if ($kandidat === null) {
                continue;
            }

            $kandidat = trim($kandidat);

            if ($kandidat !== '') {
                return $kandidat;
            }
        }

        return null;
    }
}
