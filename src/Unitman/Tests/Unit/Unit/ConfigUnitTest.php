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
                [
                    'id' => 'COLLECTION',
                    'label' => 'COLLECTION',
                    'type' => 'collection',
                    'defaultValue' => 'php',
                    'options' => [
                        'options' => [
                            [
                                'id' => '1',
                                'name' => '1111'
                            ],
                            [
                                'id' => '2',
                                'name' => '2222'
                            ]
                        ]
                    ],
                ]
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
            ],
            'actions' => [
                [
                    'id' => 'php-console',
                    'name' => "php bin/console",
                    'variables' => [
                        [
                            'id' => 'BIN_CONSOLE',
                            'label' => 'BIN_CONSOLE',
                            'type' => 'string',
                            'defaultValue' => 'debug:router'
                        ],
                    ],
                    'commands' => [
                        'podman-compose exec php php bin/console ${UNITMAN_ACTION_BIN_CONSOLE}'
                    ]
                ],
                [
                    'id' => 'podman-compose-logs',
                    'name' => "podman-compose logs",
                    'variables' => [
                        [
                            'id' => 'SERVICE_NAME',
                            'label' => 'SERVICE_NAME',
                            'type' => 'collection',
                            'defaultValue' => 'php',
                            'options' => [
                                'options' => [
                                    [
                                        'id' => 'php',
                                        'name' => 'php name'
                                    ],
                                    [
                                        'id' => 'nginx',
                                        'name' => 'nginx name'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'commands' => [
                        'podman-compose logs ${UNITMAN_ACTION_SERVICE_NAME}'
                    ]
                ]
            ]
        ];

        $config = ConfigUnita::fromArray($configAsArray);
        $errs = $config->validateValues([
            new VariableValue('INTEGER',"10"),
            new VariableValue('FLOAT', "10.5"),
            new VariableValue('STRING', "123123"),
            new VariableValue('COLLECTION', '1')
        ]);

        $this->assertEquals([], $errs);

        $this->assertEquals('/test', $config->getServices()[0]->ports[0]->startUri);

        $arr = $config->toArray();

        $this->assertEquals(['options' => [
            [
                'id' => '1',
                'name' => '1111'
            ],
            [
                'id' => '2',
                'name' => '2222'
            ]
        ]], $arr['variables'][3]['options']);
        $this->assertEquals(['options' => [
            [
                'id' => 'php',
                'name' => 'php name'
            ],
            [
                'id' => 'nginx',
                'name' => 'nginx name'
            ]
        ]], $arr['actions'][1]['variables'][0]['options']);
    }
}
