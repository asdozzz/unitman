<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\OstanovitUnit;
use App\Unitman\Business\Command\Unit\PodgotovitUnitKZapusku;
use App\Unitman\Business\Command\Unit\SbrositPodgotovkuUnita;
use App\Unitman\Business\Command\Unit\SobratUnit;
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
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Model\Runner\ResultatObnovleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatOstanovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSborkiUnita;
use App\Unitman\Business\Model\Runner\ResultatSbrosaPodgotovkiUnita;
use App\Unitman\Business\Model\Runner\ResultatUdaleniyaUnita;
use App\Unitman\Business\Model\Runner\ResultatZapuskaUnita;
use App\Unitman\Business\Port\RunnerService;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\ObnovitKodUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\OstanovitUnitUseCase;
use App\Unitman\Business\UseCase\Unit\PodgotovitUnitKZapuskuUseCase;
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
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Adapter\MemoryRunnerService;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Ramsey\Uuid\Uuid;

final class UnitUspeshnoOstanovlenIUdalenTest extends AbstractUnitUseCase
{
    function test()
    {
        $unitId = Uuid::uuid7()->toString();
        $this->sozdatUnit($unitId);

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, new JobId('SBORKA_UNITA'));
        $configText = file_get_contents(__DIR__.'/data/config_1.yaml');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBORKI, new ResultatSborkiUnita(true, 'text_ot_runnera' ,$configText));
        $memoryRunner->addResponse(MemoryRunnerService::PODGOTOVKA_UNITA, new JobId('PODGOTOVKA_UNITA'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_PODGOTOVKI, new ResultatPodgotovkiUnita(false, 'text_ot_runnera_oshibka_podgotovka'));
        $memoryRunner->addResponse(MemoryRunnerService::OBNOVLENIE_UNITA, new JobId('OBNOVLENIE_UNITA'));
        $configText2 = file_get_contents(__DIR__.'/data/config_2.yaml');
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_OBNOVLENIYA, new ResultatObnovleniyaUnita(true, 'text_ot_runnera_uspeh_obnovlenie', $configText2));
        $memoryRunner->addResponse(MemoryRunnerService::PODGOTOVKA_UNITA, new JobId('PODGOTOVKA_UNITA'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_PODGOTOVKI, new ResultatPodgotovkiUnita(true,'text_ot_runnera_uspeh_podgotovki'));
        $memoryRunner->addResponse(MemoryRunnerService::SBROS_PODGOTOVKI_UNITA, new JobId('SBROS_PODGOTOVKI_UNITA'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBROSA_PODGOTOVKI, new ResultatSbrosaPodgotovkiUnita(false,'text_ot_runnera_oshibka_sbrosa'));
        $memoryRunner->addResponse(MemoryRunnerService::OBNOVLENIE_UNITA, new JobId('OBNOVLENIE_UNITA'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_OBNOVLENIYA, new ResultatObnovleniyaUnita(true, 'text_ot_runnera_uspeh_obnovlenie', $configText2));
        $memoryRunner->addResponse(MemoryRunnerService::SBROS_PODGOTOVKI_UNITA, new JobId('SBROS_PODGOTOVKI_UNITA'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_SBROSA_PODGOTOVKI, new ResultatSbrosaPodgotovkiUnita(true,'text_ot_runnera_uspeh_sbrosa'));
        $memoryRunner->addResponse(MemoryRunnerService::PODGOTOVKA_UNITA, new JobId('PODGOTOVKA_UNITA'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_PODGOTOVKI, new ResultatPodgotovkiUnita(true,'text_ot_runnera_uspeh_podgotovki'));
        $memoryRunner->addResponse(MemoryRunnerService::ZAPUSK_UNITA, new JobId('ZAPUSK_UNITA'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_ZAPUSKA, new ResultatZapuskaUnita(true,'text_ot_runnera_uspeh_zapuska'));
        $memoryRunner->addResponse(MemoryRunnerService::OSTANOVKA_UNITA, new JobId('OSTANOVKA_UNITA'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_OSTANOVKI, new ResultatOstanovkiUnita(true,'text_ot_runnera_uspeh_ostanovki'));
        $memoryRunner->addResponse(MemoryRunnerService::UDALENIE_UNITA, new JobId('UDALENIE_UNITA'));
        $memoryRunner->addResponse(MemoryRunnerService::RESULTAT_UDALENIYA, new ResultatUdaleniyaUnita(false,'text_ot_runnera_oshibka_udaleniya'));
        self::$container->set(RunnerService::class, $memoryRunner);


        $useCase = self::$container->get(SobratUnitUseCase::class);
        $useCase->handle(new SobratUnit($unitId));

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */
        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, 'JDET_RESULTATI_SBORKI');

        $useCase = self::$container->get(UstanovitResultatSborkiUnitaUseCase::class);
        $ustanovitResultatSborkiUnita = new UstanovitResultatSborkiUnita($unitId);
        $useCase->handle($ustanovitResultatSborkiUnita);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/

        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, 'USPESHNO_SOBRAN');

        $useCase = self::$container->get(ZapolnitPeremenieUnitaUseCase::class);
        $useCase->handle(new ZapolnitPeremenieUnita($unitId, [
            'AUTH_TOKEN' => 'token_access',
            'DICTIONARY_SERVICE' => 'http://v2.dict.ru',
            'ORDER_SERVICE' => 'master'
        ]));

        $useCase = self::$container->get(PodgotovitUnitKZapuskuUseCase::class);
        $useCase->handle(new PodgotovitUnitKZapusku($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, 'JDET_RESULTATI_PODGOTOVKI');

        $useCase = self::$container->get(UstanovitResultatPodgotovkiUnitaUseCase::class);
        $useCase->handle(new UstanovitResultatPodgotovkiUnita($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, 'OSHIBKA_PODGOTOVKI');

        $useCase = self::$container->get(ObnovitKodUnitaUseCase::class);
        $useCase->handle(new ObnovitKodUnita($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, 'JDET_RESULTATI_OBNOVLENIYA');


        $useCase = self::$container->get(UstanovitResultatObnovleniyaUnitaUseCase::class);
        $useCase->handle(new UstanovitResultatObnovleniyaUnita($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, 'USPESHNO_SOBRAN');

        $useCase = self::$container->get(ZapolnitPeremenieUnitaUseCase::class);
        $useCase->handle(new ZapolnitPeremenieUnita($unitId, [
            'DICTIONARY_SERVICE' => 'http://v3.dict.ru',
            'DICTIONARY_USER' => 'asdozzz'
        ]));

        $useCase = self::$container->get(PodgotovitUnitKZapuskuUseCase::class);
        $useCase->handle(new PodgotovitUnitKZapusku($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, 'JDET_RESULTATI_PODGOTOVKI');

        $useCase = self::$container->get(UstanovitResultatPodgotovkiUnitaUseCase::class);
        $useCase->handle(new UstanovitResultatPodgotovkiUnita($unitId));
        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, 'USPESHNO_PODGOTOVLEN_K_ZAPUSKU');

        $useCase = self::$container->get(SbrositPodgotovkuUnitaUseCase::class);
        $useCase->handle(new SbrositPodgotovkuUnita($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, 'JDET_RESULTATI_SBROSA_PODGOTOVKI');

        $useCase = self::$container->get(UstanovitResultatSbrosaPodgotovkiUseCase::class);
        $useCase->handle(new UstanovitResultatSbrosaPodgotovki($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, 'OSHIBKA_SBROSA_PODGOTOVKI');

        $useCase = self::$container->get(ObnovitKodUnitaUseCase::class);
        $useCase->handle(new ObnovitKodUnita($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, 'JDET_RESULTATI_OBNOVLENIYA');

        $useCase = self::$container->get(UstanovitResultatObnovleniyaUnitaUseCase::class);
        $useCase->handle(new UstanovitResultatObnovleniyaUnita($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, 'USPESHNO_PODGOTOVLEN_K_ZAPUSKU');


        $useCase = self::$container->get(SbrositPodgotovkuUnitaUseCase::class);
        $useCase->handle(new SbrositPodgotovkuUnita($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, 'JDET_RESULTATI_SBROSA_PODGOTOVKI');

        $useCase = self::$container->get(UstanovitResultatSbrosaPodgotovkiUseCase::class);
        $useCase->handle(new UstanovitResultatSbrosaPodgotovki($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, 'USPESHNO_SOBRAN');

        $useCase = self::$container->get(ZapolnitPeremenieUnitaUseCase::class);
        $useCase->handle(new ZapolnitPeremenieUnita($unitId, [
            'DICTIONARY_SERVICE' => 'http://v2.dict.ru',
            'DICTIONARY_USER' => 'asd'
        ]));

        $useCase = self::$container->get(PodgotovitUnitKZapuskuUseCase::class);
        $useCase->handle(new PodgotovitUnitKZapusku($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, 'JDET_RESULTATI_PODGOTOVKI');

        $useCase = self::$container->get(UstanovitResultatPodgotovkiUnitaUseCase::class);
        $useCase->handle(new UstanovitResultatPodgotovkiUnita($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, 'USPESHNO_PODGOTOVLEN_K_ZAPUSKU');

        $useCase = self::$container->get(ZapustitUnitUseCase::class);
        $useCase->handle(new ZapustitUnit($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, 'JDET_RESULTATI_ZAPUSKA');

        $useCase = self::$container->get(UstanovitResultatZapuskaUseCase::class);
        $useCase->handle(new UstanovitResultatZapuska($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, 'USPESHNO_ZAPUSHEN');
        $this->assertEquals($spisokUnitovReadModel->url, 'https://task-123.uwin.testcase.ru');

        $useCase = self::$container->get(OstanovitUnitUseCase::class);
        $useCase->handle(new OstanovitUnit($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, 'JDET_RESULTATI_OSTANOVKI');

        $useCase = self::$container->get(UstanovitResultatOstanovkiUnitaUseCase::class);
        $useCase->handle(new UstanovitResultatOstanovkiUnita($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->url, null);
        $this->assertEquals($spisokUnitovReadModel->state, 'USPESHNO_PODGOTOVLEN_K_ZAPUSKU');

        $useCase = self::$container->get(UdalitUnitUseCase::class);
        $useCase->handle(new UdalitUnit($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, 'JDET_RESULTAT_UDALENIYA');

        $useCase = self::$container->get(UstanovitResultatUdaleniyaUseCase::class);
        $useCase->handle(new UstanovitResultatUdaleniya($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, 'SLOMAN');

        $useCase = self::$container->get(UdalitSlomaniyUnitUseCase::class);
        $useCase->handle(new UdalitSlomaniyUnit($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->findById($unitId);
        $this->assertTrue(empty($spisokUnitovReadModel));
    }
}
