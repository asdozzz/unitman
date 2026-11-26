<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Lexik\Bundle\JWTAuthenticationBundle\LexikJWTAuthenticationBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Nelmio\CorsBundle\NelmioCorsBundle::class => ['all' => true],
    FluffyDiscord\RoadRunnerBundle\FluffyDiscordRoadRunnerBundle::class => ['all' => true],
    Fresh\CentrifugoBundle\FreshCentrifugoBundle::class => ['all' => true],
    Highcore\TemporalBundle\TemporalBundle::class => ['all' => true],
    // Бандл требует расширение xhprof. В окружениях без него (например, в
    // минимальном CLI-образе) его нельзя подключать: иначе компиляция
    // контейнера падает на неопределённых константах профилировщика.
    Iluckhack\XhprofBuggregatorBundle\XhprofBuggregatorBundle::class => \extension_loaded('xhprof')
        ? ['dev' => true, 'test' => true]
        : [],
    Nelmio\ApiDocBundle\NelmioApiDocBundle::class => ['all' => true],
];
