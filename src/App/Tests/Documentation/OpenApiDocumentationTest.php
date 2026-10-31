<?php

declare(strict_types=1);

namespace App\App\Tests\Documentation;

use Nelmio\ApiDocBundle\Render\RenderOpenApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Страж актуальности OpenAPI-спецификации.
 *
 * Проверяет, что сгенерированный документ:
 *  - валиден (без «висячих» $ref);
 *  - покрывает все HTTP-маршруты из /api (ни один контроллер не забыт);
 *  - у каждой операции проставлен тег, а у маршрута — ровно один HTTP-метод;
 *  - закоммиченный docs/openapi.json не отстал от кода.
 */
final class OpenApiDocumentationTest extends KernelTestCase
{
    /**
     * @var array{paths?: array<string, array<string, mixed>>, components?: array<string, mixed>}
     */
    private static array $document = [];

    /**
     * @var list<string>
     */
    private static array $apiRoutes = [];

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();

        /** @var RenderOpenApi $renderer */
        $renderer = self::getContainer()->get('nelmio_api_doc.render_docs');

        $json = $renderer->render(RenderOpenApi::JSON, 'default', ['no-pretty' => true]);

        $decoded = json_decode($json, true);
        self::assertIsArray($decoded, 'Nelmio вернул невалидный JSON.');

        self::$document = $decoded;

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $routes = [];

        /** @var Route $route */
        foreach ($router->getRouteCollection() as $route) {
            $path = $route->getPath();

            if (!str_starts_with($path, '/api') || in_array($path, ['/api/doc', '/api/doc.json'], true)) {
                continue;
            }

            $routes[] = $path;
        }

        self::$apiRoutes = array_values(array_unique($routes));
    }

    public static function tearDownAfterClass(): void
    {
        self::ensureKernelShutdown();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function paths(): array
    {
        $paths = self::$document['paths'] ?? [];
        self::assertIsArray($paths);
        self::assertNotEmpty($paths, 'OpenAPI-документ не содержит ни одного маршрута.');

        return $paths;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemas(): array
    {
        $schemas = self::$document['components']['schemas'] ?? [];
        self::assertIsArray($schemas);
        self::assertNotEmpty($schemas, 'OpenAPI-документ не содержит ни одной схемы.');

        return $schemas;
    }

    public function test_no_dangling_schema_refs(): void
    {
        $schemas = $this->schemas();
        $json = json_encode(self::$document, JSON_THROW_ON_ERROR);

        preg_match_all('~#/components/schemas/([A-Za-z0-9_.\-]+)~', $json, $matches);

        $referenced = array_values(array_unique($matches[1]));
        $dangling = array_values(array_diff($referenced, array_keys($schemas)));

        self::assertSame([], $dangling, 'Ссылки на несуществующие схемы: '.implode(', ', $dangling));
    }

    public function test_every_api_route_is_documented(): void
    {
        $documented = array_keys($this->paths());
        $missing = array_values(array_diff(self::$apiRoutes, $documented));

        self::assertSame([], $missing, "Маршруты без описания в OpenAPI:\n".implode("\n", $missing));
    }

    public function test_every_operation_has_exactly_one_verb_and_a_tag(): void
    {
        $verbs = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options', 'trace'];

        $untagged = [];
        $multiVerb = [];

        foreach ($this->paths() as $path => $operations) {
            $pathVerbs = array_values(array_intersect(array_keys($operations), $verbs));

            if (count($pathVerbs) !== 1) {
                $multiVerb[] = $path.' ('.implode(',', $pathVerbs).')';
                continue;
            }

            $operation = $operations[$pathVerbs[0]];

            if (!is_array($operation) || empty($operation['tags'])) {
                $untagged[] = $path.' '.$pathVerbs[0];
            }
        }

        self::assertSame([], $multiVerb, 'Маршруты с несколькими HTTP-методами: '.implode(', ', $multiVerb));
        self::assertSame([], $untagged, 'Операции без тега: '.implode(', ', $untagged));
    }

    public function test_committed_document_is_up_to_date(): void
    {
        $file = dirname(__DIR__, 4).'/docs/openapi.json';

        if (!is_file($file)) {
            self::markTestSkipped('docs/openapi.json отсутствует: запусти composer openapi-dump.');
        }

        $committed = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($committed);

        $committedPaths = array_keys((array) ($committed['paths'] ?? []));
        $currentPaths = array_keys($this->paths());

        sort($committedPaths);
        sort($currentPaths);

        self::assertSame(
            $currentPaths,
            $committedPaths,
            'docs/openapi.json устарел: запусти composer openapi-dump и закоммить результат.'
        );
    }
}
