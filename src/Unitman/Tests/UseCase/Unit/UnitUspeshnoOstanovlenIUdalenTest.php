<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\OstanovitUnit;
use App\Unitman\Business\Command\Unit\PodgotovitUnitKZapusku;
use App\Unitman\Business\Command\Unit\SbrositPodgotovkuUnita;
use App\Unitman\Business\Command\Unit\SobratUnit;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\UdalitSlomaniyUnit;
use App\Unitman\Business\Command\Unit\UdalitUnit;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuPriPodgotovkeUnita;
use App\Unitman\Business\Command\Unit\UstanovitOshibkuUdaleniya;
use App\Unitman\Business\Command\Unit\UstanovitUspehObnovleniyaUnita;
use App\Unitman\Business\Command\Unit\UstanovitUspehOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitUspehPodgotovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitUspehSborkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitUspehSbrosaPodgotovki;
use App\Unitman\Business\Command\Unit\UstanovitUspehZapuska;
use App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;
use App\Unitman\Business\Command\Unit\ZapustitUnit;
use App\Unitman\Business\Port\CanGeneateGuid;
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
use App\Unitman\Business\UseCase\Unit\UstanovitOshibkuPriPodgotovkeUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitOshibkuUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitUspehObnovleniyaUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitUspehOstanovkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitUspehPodgotovkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitUspehSborkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitUspehSbrosaPodgotovkiUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitUspehZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\ZapolnitPeremenieUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\ZapustitUnitUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Adapter\MemoryRunnerService;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Ramsey\Uuid\Uuid;

final class UnitUspeshnoOstanovlenIUdalenTest extends AbstractUnitUseCaseTest
{
    function test()
    {
        $unitId = Uuid::uuid7()->toString();
        $projectId = Uuid::uuid7()->toString();
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$unitId]));

        $memoryRunner = new MemoryRunnerService();
        $memoryRunner->addResponse(MemoryRunnerService::SBORKA_UNITA, 'SBORKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::PODGOTOVKA_UNITA, 'PODGOTOVKA_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::SBROS_PODGOTOVKI_UNITA, 'SBROS_PODGOTOVKI_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::OBNOVLENIE_UNITA, 'OBNOVLENIE_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::ZAPUSK_UNITA, 'ZAPUSK_UNITA');
        $memoryRunner->addResponse(MemoryRunnerService::OSTANOVKA_UNITA, 'OSTANOVKA_UNITA');
        self::$container->set(RunnerService::class, $memoryRunner);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo*/

        $useCase = self::$container->get(SozdatUnitUseCase::class);
        $useCase->handle(new SozdatUnit(
            $projectId,
            'task-123',
            'feature/123'
        ));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/

        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'SOZDAN',
            'commands' => ['nachatSborku', 'nachatUdalenie']
        ]));
        $this->assertEquals($spisokUnitovReadModel->name, 'task-123');
        $this->assertEquals($spisokUnitovReadModel->branch, 'feature/123');
        $this->assertEquals($spisokUnitovReadModel->projectId, $projectId);

        $useCase = self::$container->get(SobratUnitUseCase::class);
        $useCase->handle(new SobratUnit($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'JDET_RESULTATI_SBORKI',
            'commands' => []
        ]));


        $useCase = self::$container->get(UstanovitUspehSborkiUnitaUseCase::class);
        $configText = file_get_contents(__DIR__.'/data/config_1.yaml');
        $ustanovitUspehSborkiUnita = new UstanovitUspehSborkiUnita($unitId, 'text_ot_runnera' ,$configText);
        $useCase->handle($ustanovitUspehSborkiUnita);

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        /** @var SpisokUnitovReadModel $spisokUnitovReadModel*/

        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'USPESHNO_SOBRAN',
            'commands' => [
                'nachatUdalenie',
                'nachatObnovlenie',
                'zapolnitPeremenie',
                'nachatPodgotovku',
            ]
        ]));
        $this->assertEquals($spisokUnitovReadModel->textOtRunnera, 'text_ot_runnera');
        $cfg = [
            'variables' => [
                ['id' => 'AUTH_TOKEN', 'name' => 'Токен авторизации', 'type' => 'string', 'defaultValue' => 'asdasd'],
                ['id' => 'DICTIONARY_SERVICE', 'name' => 'Сервис справочников', 'type' => 'collection', 'defaultValue' => 'http://v1.dict.ru', 'options' => [
                    ['id' => 'http://v1.dict.ru', 'name' => 'Версия 1'],
                    ['id' => 'http://v2.dict.ru', 'name' => 'Версия 2'],
                ]],
                ['id' => 'ORDER_SERVICE', 'name' => 'Сервис заказов', 'type' => 'unit', 'defaultValue' => 'master', 'options' => [
                    'projectCode' => 'services/orders'
                ]]
            ],
            'prepare' => [
                'cp -f .env.runner .env',
                'mkdir -p config/jwt',
                'openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:my_password',
                'openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:my_password',
            ],
            'reset_prepare' => [
                'rm -rf config/jwt',
                'rm -f .env',
            ],
            'up' => [
                'docker-compose up -d --build',
                'docker-compose run web composer install --optimize-autoloader',
                'docker-compose run web php bin/console doctrine:migrations:migrate',
            ],
            'down' => [
                'docker-compose down'
            ]
        ];
        $this->assertEquals($spisokUnitovReadModel->config, json_encode($cfg));

        $useCase = self::$container->get(ZapolnitPeremenieUnitaUseCase::class);
        $useCase->handle(new ZapolnitPeremenieUnita($unitId, [
            'AUTH_TOKEN' => 'token_access',
            'DICTIONARY_SERVICE' => 'http://v2.dict.ru',
            'ORDER_SERVICE' => 'master'
        ]));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->configValues, json_encode([
            'AUTH_TOKEN' => 'token_access',
            'DICTIONARY_SERVICE' => 'http://v2.dict.ru',
            'ORDER_SERVICE' => 'master'
        ]));

        $useCase = self::$container->get(PodgotovitUnitKZapuskuUseCase::class);
        $useCase->handle(new PodgotovitUnitKZapusku($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'JDET_RESULTATI_PODGOTOVKI',
            'commands' => []
        ]));

        $useCase = self::$container->get(UstanovitOshibkuPriPodgotovkeUnitaUseCase::class);
        $useCase->handle(new UstanovitOshibkuPriPodgotovkeUnita($unitId, 'text_ot_runnera_oshibka_podgotovka'));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->textOtRunnera, 'text_ot_runnera_oshibka_podgotovka');
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'OSHIBKA_PODGOTOVKI',
            'commands' => [
                'nachatUdalenie',
                'nachatObnovlenie',
                'nachatPodgotovku',
                'zapolnitPeremenie',
            ]
        ]));

        $useCase = self::$container->get(ObnovitKodUnitaUseCase::class);
        $useCase->handle(new ObnovitKodUnita($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'JDET_RESULTATI_OBNOVLENIYA',
            'commands' => []
        ]));

        $configText = file_get_contents(__DIR__.'/data/config_2.yaml');
        $useCase = self::$container->get(UstanovitUspehObnovleniyaUnitaUseCase::class);
        $useCase->handle(new UstanovitUspehObnovleniyaUnita($unitId, 'text_ot_runnera_obnovlenie',$configText));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->textOtRunnera, 'text_ot_runnera_obnovlenie');
        $cfg = [
            'variables' => [
                ['id' => 'DICTIONARY_SERVICE', 'name' => 'Сервис справочников', 'type' => 'collection', 'defaultValue' => 'http://v1.dict.ru', 'options' => [
                    ['id' => 'http://v1.dict.ru', 'name' => 'Версия 1'],
                    ['id' => 'http://v2.dict.ru', 'name' => 'Версия 2'],
                ]],
                ['id' => 'DICTIONATY_USER', 'name' => 'Логин сервиса авторизации', 'type' => 'string', 'defaultValue' => 'qweqwe'],
            ],
            'prepare' => [
                'cp -f .env.runner .env',
                'mkdir -p config/jwt',
                'openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:my_password',
                'openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:my_password',
            ],
            'reset_prepare' => [
                'rm -rf config/jwt',
                'rm -f .env',
            ],
            'up' => [
                'docker-compose up -d --build',
                'docker-compose run web composer install --optimize-autoloader',
                'docker-compose run web php bin/console doctrine:migrations:migrate',
            ],
            'down' => [
                'docker-compose down'
            ]
        ];
        $this->assertEquals($spisokUnitovReadModel->config, json_encode($cfg));

        $useCase = self::$container->get(SbrositPodgotovkuUnitaUseCase::class);
        $useCase->handle(new SbrositPodgotovkuUnita($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'JDET_RESULTATI_SBROSA_PODGOTOVKI',
            'commands' => []
        ]));

        $useCase = self::$container->get(UstanovitUspehSbrosaPodgotovkiUseCase::class);
        $useCase->handle(new UstanovitUspehSbrosaPodgotovki($unitId, 'text_ot_runnera_sbros_podgotovki'));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->textOtRunnera, 'text_ot_runnera_sbros_podgotovki');
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'USPESHNO_SOBRAN',
            'commands' => [
                'nachatUdalenie',
                'nachatObnovlenie',
                'zapolnitPeremenie',
                'nachatPodgotovku',
            ]
        ]));

        $useCase = self::$container->get(ZapolnitPeremenieUnitaUseCase::class);
        $useCase->handle(new ZapolnitPeremenieUnita($unitId, [
            'DICTIONARY_SERVICE' => 'http://v2.dict.ru',
            'DICTIONARY_USER' => 'asd'
        ]));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->configValues, json_encode([
            'DICTIONARY_SERVICE' => 'http://v2.dict.ru',
            'DICTIONARY_USER' => 'asd'
        ]));

        $useCase = self::$container->get(PodgotovitUnitKZapuskuUseCase::class);
        $useCase->handle(new PodgotovitUnitKZapusku($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'JDET_RESULTATI_PODGOTOVKI',
            'commands' => []
        ]));

        $useCase = self::$container->get(UstanovitUspehPodgotovkiUnitaUseCase::class);
        $useCase->handle(new UstanovitUspehPodgotovkiUnita($unitId, 'text_ot_runnera_podgotovka'));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->textOtRunnera, 'text_ot_runnera_podgotovka');
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'USPESHNO_PODGOTOVLEN_K_ZAPUSKU',
            'commands' => [
                'nachatUdalenie',
                'nachatObnovlenie',
                'zapolnitPeremenie',
                'nachatSbrosPodgotovki',
                'nachatZapusk',
            ]
        ]));

        $useCase = self::$container->get(ZapustitUnitUseCase::class);
        $useCase->handle(new ZapustitUnit($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'JDET_RESULTATI_ZAPUSKA',
            'commands' => []
        ]));

        $useCase = self::$container->get(UstanovitUspehZapuskaUseCase::class);
        $useCase->handle(new UstanovitUspehZapuska($unitId, 'text_ot_runnera_zapusk'));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->textOtRunnera, 'text_ot_runnera_zapusk');
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'USPESHNO_ZAPUSHEN',
            'commands' => ['nachatOstanovku']
        ]));

        $useCase = self::$container->get(OstanovitUnitUseCase::class);
        $useCase->handle(new OstanovitUnit($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'JDET_RESULTATI_OSTANOVKI',
            'commands' => []
        ]));

        $useCase = self::$container->get(UstanovitUspehOstanovkiUnitaUseCase::class);
        $useCase->handle(new UstanovitUspehOstanovkiUnita($unitId, 'text_ot_runnera_ostanovka'));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'USPESHNO_PODGOTOVLEN_K_ZAPUSKU',
            'commands' => [
                'nachatUdalenie',
                'nachatObnovlenie',
                'zapolnitPeremenie',
                'nachatSbrosPodgotovki',
                'nachatZapusk',
            ]
        ]));
        $this->assertEquals($spisokUnitovReadModel->textOtRunnera, 'text_ot_runnera_ostanovka');

        $useCase = self::$container->get(UdalitUnitUseCase::class);
        $useCase->handle(new UdalitUnit($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, true);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'JDET_RESULTATI_UDALENIYA',
            'commands' => []
        ]));

        $useCase = self::$container->get(UstanovitOshibkuUdaleniyaUseCase::class);
        $useCase->handle(new UstanovitOshibkuUdaleniya($unitId, 'text_ot_runnera_oshibka_udaleniya'));

        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->waitResultFromRunner, false);
        $this->assertEquals($spisokUnitovReadModel->state, json_encode([
            'state' => 'SLOMAN',
            'commands' => ['udalitVruchnuyu']
        ]));
        $this->assertEquals($spisokUnitovReadModel->textOtRunnera, 'text_ot_runnera_oshibka_udaleniya');

        $useCase = self::$container->get(UdalitSlomaniyUnitUseCase::class);
        $useCase->handle(new UdalitSlomaniyUnit($unitId));

        $spisokUnitovReadModel = $spisokUnitovRepo->findById($unitId);
        $this->assertTrue(empty($spisokUnitovReadModel));


    }
}
