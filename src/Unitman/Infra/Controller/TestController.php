<?php

namespace App\Unitman\Infra\Controller;

use App\Runner\Api\RunnerApi;
use App\Runner\Business\Command\InitProjectCommand;
use Carbon\CarbonInterval;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;

final class TestController extends AbstractController
{
    public function __construct(private RunnerApi $runnerApi)
    {
    }

    #[Route('/test')]
    function test()
    {
        try {
            $result = $this->runnerApi->initProject(new InitProjectCommand('TestProjectId', 'master', 'https://github.com/asdozzz/americor'));
            return new Response("<pre>" . print_r($result, true) . "</pre>");
        } catch (\Exception | \Error $e) {
            return new Response("<pre>" . print_r($e->getMessage(), true) . "</pre>");
        }

    }
}
