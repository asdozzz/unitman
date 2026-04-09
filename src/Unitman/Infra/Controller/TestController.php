<?php

namespace App\Unitman\Infra\Controller;

use App\Unitman\Business\ReadModel\RepoList;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Infra\Repository\Repo\RepoListRepository;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\JsonStreamer\JsonStreamReader;
use Symfony\Component\JsonStreamer\StreamReaderInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\TypeInfo\Type;

final class TestController extends AbstractController
{
    #[Route('/test', methods: ['GET'])]
    function test(RepoListRepository $repository, JsonStreamReader $streamReader)
    {
        $row = $repository->findRowById('0199907b-8736-7334-bd1a-f0d72286a9cb');
        $json = $row['payload'];
        $type = Type::object(RepoList::class);
        $unit = $streamReader->read($json, $type);
        return new JsonResponse($unit);
    }
}
