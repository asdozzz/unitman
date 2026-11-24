<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Jobs\SobitiyaIzHranilisha;

use App\Unitman\Business\Command\Project\ObnovitNastroikiHuka;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Model\Unit\OtlozhennoeDeistvie;
use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Model\Unit\UnitProcess\UnitProcessType;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\SobitiePayloadDecoder;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\SobitiyaIzHranilishaActivity;
use App\Unitman\Infra\Repository\Unit\ProzesUnitaRepository;
use App\Unitman\Tests\UseCase\Unit\AbstractUnitUseCase;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

/**
 * Поведение обработчика Git-вебхука по отношению к занятости юнита.
 *
 * Проверяется ровно то, что обещает план: занятый юнит не теряет событие, а
 * получает строку в `unit_otlozhennoe_deistvie`; свободный юнит обрабатывается
 * немедленно; удаление ветки вытесняет отложенное обновление кода.
 *
 * Тест идёт через контейнер, потому что все участники сценария объявлены
 * `final` и не могут быть замоканы.
 */
final class SobitiyaIzHranilishaActivityTest extends AbstractUnitUseCase
{
    private const EMPTY_HASH = '0000000000000000000000000000000000000000';
    private const VETKA = 'feature/123';
    private const VLADELEC = 'opencode:turn-1';

    #[Test]
    public function zanyatii_unit_poluchaet_otlozhennoe_udalenie_vetki(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $this->zahvatit($unitId);

        $result = $this->activity()->obrabotatZadachu($this->sobitieUdaleniyaVetki($projectId));

        self::assertSame(SobitiyaIzHranilishaActivity::DEFERRED, $result);

        $deistviya = $this->otlozhennie()->poluchitGotovieDlyaUnita($unitId, $this->neskolkoMinutVpered());

        self::assertCount(1, $deistviya);

        $deistvie = reset($deistviya);
        self::assertInstanceOf(OtlozhennoeDeistvie::class, $deistvie);
        self::assertSame(TipSobitiya::VETKA_UDALENA, $deistvie->tipSobitiya);
        self::assertSame($projectId, $deistvie->projectId);
        self::assertSame(self::VETKA, $deistvie->payload['vetka']);

        self::assertFalse(
            $this->estProzesUdaleniya($unitId),
            'пока юнит занят, процесс удаления запускаться не должен',
        );
    }

    #[Test]
    public function svobodnii_unit_zapuskaet_prozes_udaleniya_srazu(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $result = $this->activity()->obrabotatZadachu($this->sobitieUdaleniyaVetki($projectId));

        self::assertSame(SobitiyaIzHranilishaActivity::OK, $result);
        self::assertSame(
            [],
            $this->otlozhennie()->poluchitGotovieDlyaUnita($unitId, $this->neskolkoMinutVpered()),
            'свободный юнит не должен порождать отложенных намерений',
        );
        self::assertTrue($this->estProzesUdaleniya($unitId));
    }

    #[Test]
    public function udalenie_nevstrechennoi_vetki_ne_trogaet_units(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $result = $this->activity()->obrabotatZadachu(
            $this->sobitieUdaleniyaVetki($projectId, 'feature/no-such-branch')
        );

        self::assertSame(SobitiyaIzHranilishaActivity::EMPTY_IDS, $result);
        self::assertSame(
            [],
            $this->otlozhennie()->poluchitGotovieDlyaUnita($unitId, $this->neskolkoMinutVpered()),
        );
        self::assertFalse($this->estProzesUdaleniya($unitId));
    }

    /**
     * Несобранный юнит не откладываем: такое состояние не транзиентное, и
     * намерение висело бы вечно. Сохраняется прежняя ошибка статуса.
     *
     * Захват OpenCode здесь не эмулируется: занятость проверяется раньше
     * статуса сборки, поэтому захваченный юнит штатно уходит в `deferred`.
     */
    #[Test]
    public function nesobrannii_unit_ne_otkladyvaet_obnovlenie_koda(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $result = $this->activity()->obrabotatZadachu($this->sobitieObnovleniyaKoda($projectId));

        self::assertSame('invalid status', $result);
        self::assertSame(
            [],
            $this->otlozhennie()->poluchitGotovieDlyaUnita($unitId, $this->neskolkoMinutVpered()),
            'несобранный свободный юнит не должен попадать в отложенные намерения',
        );
    }

    #[Test]
    public function udalenie_vetki_vitesnyaet_otlozhennoe_obnovlenie_koda(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $this->otlozhennie()->otlozhit(
            $unitId,
            $projectId,
            TipSobitiya::KOD_OBNOVLEN,
            ['vetka' => self::VETKA, 'unixtime' => 1_760_000_000, 'tip_sobitiya' => TipSobitiya::KOD_OBNOVLEN->value],
            new \DateTimeImmutable(),
        );

        $this->zahvatit($unitId);

        $result = $this->activity()->obrabotatZadachu($this->sobitieUdaleniyaVetki($projectId));

        self::assertSame(SobitiyaIzHranilishaActivity::DEFERRED, $result);

        $deistviya = $this->otlozhennie()->poluchitGotovieDlyaUnita($unitId, $this->neskolkoMinutVpered());

        self::assertCount(1, $deistviya, 'удаление ветки должно вытеснить отложенное обновление кода');

        $deistvie = reset($deistviya);
        self::assertInstanceOf(OtlozhennoeDeistvie::class, $deistvie);
        self::assertSame(TipSobitiya::VETKA_UDALENA, $deistvie->tipSobitiya);
    }

    #[Test]
    public function svobodnii_unit_pri_merzhe_v_main_zapuskaet_udalenie(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $result = $this->activity()->obrabotatZadachu($this->sobitieMergeRequesta($projectId));

        self::assertSame(SobitiyaIzHranilishaActivity::OK, $result);
        self::assertTrue($this->estProzesUdaleniya($unitId));
        self::assertSame(
            [],
            $this->otlozhennie()->poluchitGotovieDlyaUnita($unitId, $this->neskolkoMinutVpered()),
            'свободный юнит не должен порождать отложенных намерений',
        );
    }

    #[Test]
    public function zanyatii_unit_pri_merzhe_poluchaet_otlozhennoe_udalenie(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $this->zahvatit($unitId);

        $result = $this->activity()->obrabotatZadachu($this->sobitieMergeRequesta($projectId));

        self::assertSame(SobitiyaIzHranilishaActivity::DEFERRED, $result);

        $deistviya = $this->otlozhennie()->poluchitGotovieDlyaUnita($unitId, $this->neskolkoMinutVpered());

        self::assertCount(1, $deistviya);

        $deistvie = reset($deistviya);
        self::assertInstanceOf(OtlozhennoeDeistvie::class, $deistvie);
        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $deistvie->tipSobitiya);
        self::assertSame(self::VETKA, $deistvie->payload['vetka']);

        self::assertFalse(
            $this->estProzesUdaleniya($unitId),
            'пока юнит занят, процесс удаления запускаться не должен',
        );
    }

    #[Test]
    public function merzh_ne_v_main_ne_udaliayet_unit(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $result = $this->activity()->obrabotatZadachu(
            $this->sobitieMergeRequesta($projectId, self::VETKA, 'develop')
        );

        self::assertSame(SobitiyaIzHranilishaActivity::NOT_MAIN_BRANCH, $result);
        self::assertFalse($this->estProzesUdaleniya($unitId));
        self::assertSame(
            [],
            $this->otlozhennie()->poluchitGotovieDlyaUnita($unitId, $this->neskolkoMinutVpered()),
        );
    }

    #[Test]
    public function push_v_main_s_merge_kommitom_zapuskaet_udalenie(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $result = $this->activity()->obrabotatZadachu($this->sobitiePushSMergeKommitom($projectId));

        self::assertSame(SobitiyaIzHranilishaActivity::OK, $result);
        self::assertTrue($this->estProzesUdaleniya($unitId));
    }

    #[Test]
    public function push_v_feature_s_merge_kommitom_ne_zapuskaet_udalenie(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $this->activity()->obrabotatZadachu(
            $this->sobitiePushSMergeKommitom($projectId, self::VETKA, 'feature/inner')
        );

        self::assertFalse($this->estProzesUdaleniya($unitId));
    }

    #[Test]
    public function merzh_pri_vikluchennom_avtoudalenii_ne_zapuskaet_udalenie(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        /** @var ProjectRepository $projectRepository */
        $projectRepository = self::$container->get(ProjectRepository::class);
        $project = $projectRepository->getById($projectId);
        $project->obnovitNastrokiHuka(new ObnovitNastroikiHuka($projectId, true, true, false, false));
        $projectRepository->save($project);

        $result = $this->activity()->obrabotatZadachu($this->sobitieMergeRequesta($projectId));

        self::assertSame(SobitiyaIzHranilishaActivity::OK, $result);
        self::assertFalse($this->estProzesUdaleniya($unitId));
    }

    #[Test]
    public function merzh_vitesnyaet_otlozhennoe_obnovlenie_koda(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $this->otlozhennie()->otlozhit(
            $unitId,
            $projectId,
            TipSobitiya::KOD_OBNOVLEN,
            ['vetka' => self::VETKA, 'unixtime' => 1_760_000_000, 'tip_sobitiya' => TipSobitiya::KOD_OBNOVLEN->value],
            new \DateTimeImmutable(),
        );

        $this->zahvatit($unitId);

        $result = $this->activity()->obrabotatZadachu($this->sobitieMergeRequesta($projectId));

        self::assertSame(SobitiyaIzHranilishaActivity::DEFERRED, $result);

        $deistviya = $this->otlozhennie()->poluchitGotovieDlyaUnita($unitId, $this->neskolkoMinutVpered());

        self::assertCount(1, $deistviya, 'слияние должно вытеснить отложенное обновление кода');

        $deistvie = reset($deistviya);
        self::assertInstanceOf(OtlozhennoeDeistvie::class, $deistvie);
        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $deistvie->tipSobitiya);
    }

    #[Test]
    public function merzh_zameshaet_otlozhennoe_udalenie_vetki(): void
    {
        [$projectId, $unitId] = $this->podgotovka();

        $this->otlozhennie()->otlozhit(
            $unitId,
            $projectId,
            TipSobitiya::VETKA_UDALENA,
            ['vetka' => self::VETKA, 'unixtime' => 1_760_000_000, 'tip_sobitiya' => TipSobitiya::VETKA_UDALENA->value],
            new \DateTimeImmutable(),
        );

        $this->zahvatit($unitId);

        $result = $this->activity()->obrabotatZadachu($this->sobitieMergeRequesta($projectId));

        self::assertSame(SobitiyaIzHranilishaActivity::DEFERRED, $result);

        $deistviya = $this->otlozhennie()->poluchitGotovieDlyaUnita($unitId, $this->neskolkoMinutVpered());

        self::assertCount(1, $deistviya, 'два намерения удаления не должны сосуществовать');

        $deistvie = reset($deistviya);
        self::assertInstanceOf(OtlozhennoeDeistvie::class, $deistvie);
        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $deistvie->tipSobitiya);
    }

    #[Test]
    public function pull_request_v_main_zapuskaet_udalenie_dlya_github(): void
    {
        [$projectId, $unitId] = $this->podgotovka(RepoType::GITHUB);

        $result = $this->activity()->obrabotatZadachu($this->sobitiePullRequesta($projectId));

        self::assertSame(SobitiyaIzHranilishaActivity::OK, $result);
        self::assertTrue($this->estProzesUdaleniya($unitId));
    }

    /**
     * Тело вебхука больше лимита отклоняется до разбора и не бросает исключение:
     * иначе задание RoadRunner ушло бы в retry-цикл.
     */
    #[Test]
    public function slishkom_bolshoi_payload_vozvrashchaet_stabilnii_kod(): void
    {
        [$projectId] = $this->podgotovka();

        $result = $this->activity()->obrabotatZadachu(new SobitieIzHranilisha(
            'sobitie-bolshoi',
            $projectId,
            str_repeat('a', SobitiePayloadDecoder::MAX_EVENT_PAYLOAD_BYTES + 1),
        ));

        self::assertSame(SobitiePayloadDecoder::ERROR_TOO_LARGE, $result);
    }

    #[Test]
    public function bityi_json_vozvrashchaet_stabilnii_kod(): void
    {
        [$projectId] = $this->podgotovka();

        $result = $this->activity()->obrabotatZadachu(
            new SobitieIzHranilisha('sobitie-bitaya', $projectId, '{"ref":')
        );

        self::assertSame(SobitiePayloadDecoder::ERROR_MALFORMED, $result);
    }

    #[Test]
    public function json_ne_obekt_vozvrashchaet_stabilnii_kod(): void
    {
        [$projectId] = $this->podgotovka();

        $result = $this->activity()->obrabotatZadachu(
            new SobitieIzHranilisha('sobitie-ne-obekt', $projectId, '"refs/heads/feature/123"')
        );

        self::assertSame(SobitiePayloadDecoder::ERROR_MALFORMED, $result);
    }

    /**
     * Создаёт проект с включёнными хуками и юнит на ветке {@see self::VETKA}.
     *
     * @return array{0: string, 1: string} идентификаторы проекта и юнита
     */
    private function podgotovka(RepoType $repoType = RepoType::GITLAB): array
    {
        $userId = Uuid::uuid7()->toString();
        $unitId = Uuid::uuid7()->toString();
        $prozesSbrokiId = Uuid::uuid7()->toString();
        $prozesDopolnitelnyhId = Uuid::uuid7()->toString();

        $this->mokaemUspehSecurity($userId);

        $guidGenerator = new MemoryGuidGenerator([$unitId, $prozesSbrokiId, $prozesDopolnitelnyhId]);
        self::$container->set(CanGenerateGuid::class, $guidGenerator);

        $repoId = Uuid::uuid7()->toString();
        $repo = $this->stubRepo($repoId, $repoType, 'repoName', 'http://repoUrl');
        $repo->accessConfirm();

        /** @var RepoRepository $repoRepository */
        $repoRepository = self::$container->get(RepoRepository::class);
        $repoRepository->save($repo);

        $projectId = Uuid::uuid7()->toString();
        $project = $this->stubProekta($projectId, $userId, 'testcase', $repoId);

        /** @var ProjectRepository $projectRepository */
        $projectRepository = self::$container->get(ProjectRepository::class);
        $projectRepository->save($project);

        /** @var SozdatUnitUseCase $sozdatUnit */
        $sozdatUnit = self::$container->get(SozdatUnitUseCase::class);
        $sozdanniiId = $sozdatUnit->handle(new SozdatUnit($projectId, 'unit-1', self::VETKA, [], 3000));

        self::assertSame($unitId, $sozdanniiId, 'порядок выдачи guid в MemoryGuidGenerator сломался');

        return [$projectId, $unitId];
    }

    private function activity(): SobitiyaIzHranilishaActivity
    {
        /** @var SobitiyaIzHranilishaActivity $activity */
        $activity = self::$container->get(SobitiyaIzHranilishaActivity::class);

        return $activity;
    }

    private function otlozhennie(): OtlozhennieDeistviyaRepository
    {
        /** @var OtlozhennieDeistviyaRepository $repository */
        $repository = self::$container->get(OtlozhennieDeistviyaRepository::class);

        return $repository;
    }

    /**
     * Захватывает юнит так же, как это делает идущий ход OpenCode.
     */
    private function zahvatit(string $unitId): void
    {
        /** @var ZanyatostUnitaRepository $zanyatostRepository */
        $zanyatostRepository = self::$container->get(ZanyatostUnitaRepository::class);

        $zahvacheno = $zanyatostRepository->zahvatit(
            $unitId,
            self::VLADELEC,
            TipZanyatosti::OPENCODE_HOD,
            (new \DateTimeImmutable())->modify('+20 minutes'),
        );

        self::assertTrue($zahvacheno);
    }

    private function estProzesUdaleniya(string $unitId): bool
    {
        /** @var ProzesUnitaRepository $prozesiRepository */
        $prozesiRepository = self::$container->get(ProzesUnitaRepository::class);

        foreach ($prozesiRepository->poluchitProzesiPoIdUnita($unitId) as $prozes) {
            if ($prozes->type === UnitProcessType::UDALENIE->value) {
                return true;
            }
        }

        return false;
    }

    private function neskolkoMinutVpered(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->modify('+1 minute');
    }

    /**
     * Push-событие об удалении ветки: `after` — нулевой хеш.
     */
    private function sobitieUdaleniyaVetki(string $projectId, string $vetka = self::VETKA): SobitieIzHranilisha
    {
        return new SobitieIzHranilisha('sobitie-udalenie', $projectId, (string)json_encode([
            'event_name' => 'push',
            'ref' => 'refs/heads/' . $vetka,
            'before' => 'ae1b2c3d',
            'after' => self::EMPTY_HASH,
        ]));
    }

    /**
     * Push-событие с новыми коммитами: `after` не пустой, есть `commits`.
     */
    private function sobitieObnovleniyaKoda(string $projectId, string $vetka = self::VETKA): SobitieIzHranilisha
    {
        return new SobitieIzHranilisha('sobitie-obnovlenie', $projectId, (string)json_encode([
            'event_name' => 'push',
            'ref' => 'refs/heads/' . $vetka,
            'before' => 'ae1b2c3d',
            'after' => 'bf2c3d4e',
            'commits' => [
                ['timestamp' => '2025-10-09T10:00:00+00:00'],
            ],
        ]));
    }

    /**
     * Событие GitLab о слиянии merge request в основную ветку проекта.
     */
    private function sobitieMergeRequesta(
        string $projectId,
        string $vetka = self::VETKA,
        string $celVetka = 'master',
    ): SobitieIzHranilisha {
        return new SobitieIzHranilisha('sobitie-merge', $projectId, (string)json_encode([
            'object_kind' => 'merge_request',
            'object_attributes' => [
                'action' => 'merge',
                'state' => 'merged',
                'source_branch' => $vetka,
                'target_branch' => $celVetka,
                'merged_at' => '2026-11-20T10:00:00+00:00',
            ],
        ]));
    }

    /**
     * Push-событие с merge-коммитом: цель берётся из ref, источник — из сообщения.
     */
    private function sobitiePushSMergeKommitom(
        string $projectId,
        string $refVetka = 'master',
        string $vetkaIstochnika = self::VETKA,
    ): SobitieIzHranilisha {
        return new SobitieIzHranilisha('sobitie-push-merge', $projectId, (string)json_encode([
            'event_name' => 'push',
            'ref' => 'refs/heads/' . $refVetka,
            'before' => 'ae1b2c3d',
            'after' => 'bf2c3d4e',
            'commits' => [
                [
                    'message' => "Merge branch '" . $vetkaIstochnika . "' into '" . $refVetka . "'",
                    'timestamp' => '2026-11-20T10:00:00+00:00',
                ],
            ],
        ]));
    }

    /**
     * Событие GitHub о слиянии pull request.
     */
    private function sobitiePullRequesta(
        string $projectId,
        string $vetka = self::VETKA,
        string $celVetka = 'master',
    ): SobitieIzHranilisha {
        return new SobitieIzHranilisha('sobitie-pull-request', $projectId, (string)json_encode([
            'action' => 'closed',
            'pull_request' => [
                'merged' => true,
                'merged_at' => '2026-11-20T10:00:00+00:00',
                'head' => ['ref' => $vetka],
                'base' => ['ref' => $celVetka],
            ],
        ]));
    }
}
