<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Jobs\SobitiyaIzHranilisha;

use App\Unitman\Infra\Jobs\SobitieIzHranilishaJobsHandler;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\SobitiyaIzHranilishaActivity;
use App\Unitman\Tests\UseCase\AbstractUnitmanUseCase;
use App\Utils\Service\LockService;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Входной контроль размера задания до десериализации.
 *
 * Активность берётся из контейнера, потому что класс объявлен `final`
 * и не может быть замокан; до неё управление не доходит — задание
 * отбрасывается ещё до `deserialize`.
 */
final class SobitieIzHranilishaJobsHandlerTest extends AbstractUnitmanUseCase
{
    #[Test]
    public function zadanie_bolshe_limita_ne_deserializuetsya(): void
    {
        self::assertSame(6 * 1024 * 1024, SobitieIzHranilishaJobsHandler::MAX_JOB_PAYLOAD_BYTES);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('deserialize');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('sobitie_iz_hranilisha.job_payload_too_large');

        $handler = new SobitieIzHranilishaJobsHandler(
            $serializer,
            self::$container->get(SobitiyaIzHranilishaActivity::class),
            self::$container->get(LockService::class),
            $logger,
        );

        $task = $this->createMock(ReceivedTaskInterface::class);
        $task->method('getId')->willReturn('task-too-large');
        $task->method('getPayload')->willReturn(
            str_repeat('a', SobitieIzHranilishaJobsHandler::MAX_JOB_PAYLOAD_BYTES + 1)
        );

        $handler->handle($task);
    }
}
