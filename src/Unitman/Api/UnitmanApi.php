<?php

namespace App\Unitman\Api;

use App\Unitman\Business\Command\Unit\ObnovitSostoyanieKonteyneraUnita;
use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;
use App\Unitman\Business\Command\Unit\PoluchitSostoyanieUnita;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\ReadModel\Unit\SostoyanieUnitaReadModel;
use App\Unitman\Business\ReadModel\Unit\ZanyatostUnitaReadModel;
use App\Unitman\Business\UseCase\Unit\ObnovitSostoyanieKonteyneraUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\ObnovitStatistikuPoKonteineruUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\OsvoboditZanyatostUnitovUseCase;
use App\Unitman\Business\UseCase\Unit\PoluchitSostoyanieUnitaQuery;
use App\Unitman\Business\UseCase\Unit\PoluchitZanyatostUnitaQuery;
use App\Unitman\Business\UseCase\Unit\ProdvinutCiklUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\ProveritDostupKUnituUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Business\UseCase\Unit\ZahvatitZanyatostUnitovUseCase;
use App\Unitman\Infra\Service\ServiceProverkiProxyHost;

final class UnitmanApi
{
    /**
     * Значение `tip` для захвата под ход OpenCode. Отдаётся строкой, чтобы
     * внешний модуль не зависел от внутреннего enum.
     */
    public const TIP_OPENCODE_HOD = 'opencode_hod';

    public function __construct(
        private ObnovitSostoyanieKonteyneraUnitaUseCase $obnovitSostoyanieKonteyneraUnitaUseCase,
        private ObnovitStatistikuPoKonteineruUnitaUseCase $obnovitStatistikuPoKonteineruUnitaUseCase,
        private ServiceProverkiProxyHost $serviceProverkiProxyHost,
        private PoluchitSostoyanieUnitaQuery $poluchitSostoyanieUnitaQuery,
        private ProveritDostupKUnituUseCase $proveritDostupKUnituUseCase,
        private SozdatUnitUseCase $sozdatUnitUseCase,
        private ProdvinutCiklUnitaUseCase $prodvinutCiklUnitaUseCase,
        private ZahvatitZanyatostUnitovUseCase $zahvatitZanyatostUnitovUseCase,
        private OsvoboditZanyatostUnitovUseCase $osvoboditZanyatostUnitovUseCase,
        private PoluchitZanyatostUnitaQuery $poluchitZanyatostUnitaQuery,
    )
    {
    }

    function obnovitSostoyanieKonteyneraUnita(ObnovitSostoyanieKonteyneraUnita $command): void
    {
        $this->obnovitSostoyanieKonteyneraUnitaUseCase->handle($command);
    }

    function obnovitStatistikuUnita(ObnovitStatistikuPoKontaineruUnita $command): void
    {
        $this->obnovitStatistikuPoKonteineruUnitaUseCase->handle($command);
    }

    function proveritProxyHost(?string $url): bool
    {
        return $this->serviceProverkiProxyHost->proverit($url);
    }

    /**
     * Состояние юнита для внешних модулей. Юнит может быть уже удалён —
     * тогда возвращается read-модель с `exists = false`.
     */
    function poluchitSostoyanieUnita(string $unitId): SostoyanieUnitaReadModel
    {
        return $this->poluchitSostoyanieUnitaQuery->handle(new PoluchitSostoyanieUnita($unitId));
    }

    /**
     * Доступ пользователя к юниту проверяется по доступу к проекту юнита.
     */
    function proveritDostupKUnitu(string $unitId, string $accountId): bool
    {
        return $this->proveritDostupKUnituUseCase->handle($unitId, $accountId);
    }

    /**
     * Создание юнита от имени пользователя, а не от текущего запроса.
     */
    function sozdatUnitDlyaPolzovatelya(SozdatUnit $command, string $accountId): string
    {
        return $this->sozdatUnitUseCase->handleDlyaPolzovatelya($command, $accountId);
    }

    /**
     * Продвижение конвейера юнита на один шаг, если он готов к переходу.
     */
    function prodvinutCiklUnita(string $unitId, string $accountId): void
    {
        $this->prodvinutCiklUnitaUseCase->handle($unitId, $accountId);
    }

    /**
     * Захват юнитов под внешнюю операцию. Контракт «всё или ничего»:
     * при отказе по одному юниту уже захваченные освобождаются.
     *
     * @param string[] $unitIds
     * @throws \DomainException `unit.zanyat`, если хотя бы один юнит занят
     */
    function zahvatitZanyatostUnitov(array $unitIds, string $vladelec, string $tip): void
    {
        $this->zahvatitZanyatostUnitovUseCase->handle($unitIds, $this->prevratitTip($tip), $vladelec);
    }

    /**
     * Освобождение юнитов. Идемпотентно; сразу после освобождения
     * применяются отложенные действия из вебхука.
     *
     * @param string[] $unitIds
     */
    function osvoboditZanyatostUnitov(array $unitIds, string $vladelec): void
    {
        $this->osvoboditZanyatostUnitovUseCase->handle($unitIds, $vladelec);
    }

    /**
     * Занятость юнита: внешний захват или незавершённые процессы.
     */
    function poluchitZanyatostUnita(string $unitId): ZanyatostUnitaReadModel
    {
        return $this->poluchitZanyatostUnitaQuery->handle($unitId);
    }

    /**
     * @throws \DomainException при неизвестном типе занятости
     */
    private function prevratitTip(string $tip): TipZanyatosti
    {
        return TipZanyatosti::tryFrom($tip)
            ?? throw new \DomainException('unit.zanyatost.tip_neizvesten');
    }
}
