<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\Model\ZadachaDlyOcheredi;
use App\Unitman\Infra\Adapter\RamseyGuidGenerator;
use App\Unitman\Infra\Repository\ZadachaDlyOcherediRepository;
use Symfony\Component\Serializer\SerializerInterface;

final class ZadachaDlyOcherediService
{
    public function __construct(private ZadachaDlyOcherediRepository $repository, private RamseyGuidGenerator $canGeneateGuid, private SerializerInterface $serializer)
    {
    }

    /**
     * @param non-empty-string $queueName
     * */
    function dobavitZadachuVOchered(string $queueName, object $task, int $attemptForRetryForQueue = 1, int $retryDelayForQueue = 1): void
    {
        $id = $this->canGeneateGuid->makeGuid();
        $taskData = $this->serializer->serialize($task, 'json');
        $this->repository->insert(new ZadachaDlyOcheredi($id, $queueName, $task::class, $taskData, $attemptForRetryForQueue, $retryDelayForQueue));
    }
}
