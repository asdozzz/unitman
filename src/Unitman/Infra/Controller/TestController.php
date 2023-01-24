<?php

namespace App\Unitman\Infra\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

final class TestController extends AbstractController
{
    #[Route('/test')]
    function test()
    {
        /*$projectId = 'uwin';
        $unitId = '555';
        $projectRoot = $this->getParameter('kernel.project_dir');
        $pathProjects = $projectRoot.'/units/projects/'.$projectId.'/'.$unitId;

        if (!is_dir($pathProjects)) {
            mkdir($pathProjects, 777, true);
        }*/

        $commands = [
            ['groups'],
            ['id', 'www-data'],
            ['docker', 'ps']
           /* ['git','clone', 'https://github.com/asdozzz/exunit.git', '.'],
            ['export', 'UNITNAME='.$unitId],
            ['docker-compose','up', '-d']*/
        ];

        $output = '';
        $stop = false;
        foreach ($commands as $command) {
            $process = new Process($command);
            //$process->setWorkingDirectory($pathProjects);
            $process->run();

            foreach ($process as $type => $data) {
                if ($process::OUT === $type) {
                    $output .= $data;
                } else { // $process::ERR === $type
                    $output .= $data;
                    $stop = true;
                }
            }

            if ($stop) {
                break;
            }
        }

        $asdArr = explode("\n", rtrim($output, "\n"));

        die("<pre>" . print_r($asdArr, true) . "</pre>");
    }
}
