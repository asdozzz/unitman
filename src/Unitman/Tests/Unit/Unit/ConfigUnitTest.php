<?php

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Model\Unit\ConfigUnita;
use PHPUnit\Framework\TestCase;

final class ConfigUnitTest extends TestCase
{
    /**
     * @test
     * */
    function validnii_config()
    {
        $configAsArray = [
            'prepare' => [
                'ls -la'
            ],
            'reset_prepare' => [
                'ls -la'
            ],
            'up' => [
                'ls -la'
            ],
            'down' => [
                'ls -la'
            ],
            'services' => [
                'web' => [
                    'ports' => [
                        ['port' => 8081, 'type' => 'http', 'startUri' => '/test']
                    ]
                ],
                'php' => [
                    'cache' => [
                        'files' => [
                            'composer.lock'
                        ],
                        'paths' => [
                            'vendor'
                        ]
                    ]
                ]
            ]
        ];

        $config = ConfigUnita::fromArray($configAsArray);

        $this->assertEquals('/test', $config->getServices()[0]->ports[0]->startUri);
    }
}
