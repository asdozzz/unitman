<?php

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Model\Unit\ConfigUnita;
use App\Unitman\Business\Model\Unit\VariableValue;
use PHPUnit\Framework\TestCase;

final class ConfigUnitTest extends TestCase
{
    /**
     * @test
     * */
    function validnii_config()
    {
        $configAsArray = [
            'variables' => [
                ['id' => 'INTEGER', 'label' => 'INTEGER', 'type' => 'integer', 'defaultValue' => "10"],
                ['id' => 'FLOAT', 'label' => 'FLOAT', 'type' => 'float', 'defaultValue' => "10.5"],
                ['id' => 'STRING', 'label' => 'STRING', 'type' => 'string', 'defaultValue' => "asd"],
            ],
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
        $errs = $config->validateValues([
            new VariableValue('INTEGER',"10"),
            new VariableValue('FLOAT', "10.5"),
            new VariableValue('STRING', "123123"),
        ]);

        $this->assertEquals([], $errs);

        $this->assertEquals('/test', $config->getServices()[0]->ports[0]->startUri);
    }
}
