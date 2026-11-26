<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Api;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * LT-4 (schema-level): в публичном OpenAPI нет свойств-учётных данных.
 *
 * Логика вынесена в scripts/check-no-credentials-in-contracts.php, который
 * также используется в CI (`composer check-contracts`). Здесь тот же код
 * прогоняется в PHPUnit, чтобы регрессия ловилась и обычным прогоном тестов.
 */
final class OpenApiNoCredentialsContractTest extends TestCase
{
    private const PROJECT_ROOT = __DIR__.'/../../../..';

    protected function setUp(): void
    {
        require_once self::PROJECT_ROOT.'/scripts/check-no-credentials-in-contracts.php';
    }

    #[Test]
    public function openapi_responses_have_no_credential_properties(): void
    {
        $document = $this->readJson(self::PROJECT_ROOT.'/docs/openapi.json');

        self::assertSame(
            [],
            unitman_contracts_check_openapi($document),
            'В OpenAPI найдены свойства-учётные данные вне узкого allowlist.',
        );
    }

    #[Test]
    public function openapi_scanner_detects_an_injected_violation(): void
    {
        $document = $this->readJson(self::PROJECT_ROOT.'/docs/openapi.json');
        $document['paths']['/api/__ws11_phpunit_selftest__/leak'] = [
            'post' => [
                'responses' => [
                    '200' => [
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'privateKey' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        self::assertNotEmpty(
            unitman_contracts_check_openapi($document),
            'Сканер обязан обнаруживать синтетическое нарушение.',
        );
    }

    #[Test]
    public function ai_manifest_has_no_credentials_or_internal_paths(): void
    {
        $manifestPath = self::PROJECT_ROOT.'/../unitman-front/src/modules/ai-chat/generated/openapi.tools.json';
        if (!is_file($manifestPath)) {
            self::markTestSkipped('AI-манифест другой репозитории недоступен в этом checkout.');
        }

        $manifest = $this->readJson($manifestPath);

        self::assertSame([], unitman_contracts_check_manifest($manifest));
    }

    #[Test]
    public function ai_manifest_scanner_detects_an_injected_violation(): void
    {
        $manifest = [
            'paths' => [
                '/api/internal/selftest' => [
                    'post' => [
                        'operationId' => 'issue_runner_repo_credentials_selftest',
                        'responses' => [
                            '200' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => ['apiKey' => ['type' => 'string']],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        self::assertNotEmpty(
            unitman_contracts_check_manifest($manifest),
            'Сканер манифеста обязан обнаруживать internal/broker/credential.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $raw = file_get_contents($path);
        self::assertIsString($raw, 'Не удалось прочитать '.$path);

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
