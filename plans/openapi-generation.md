# OpenAPI generation for Unitman — design

## 1. Goal

Produce a machine-readable OpenAPI 3.1 document for the existing HTTP API (~70 operations in
4 modules) that is **generated from code**, served as Swagger UI, and guarded against drift in CI.

Non-goals: no changes to request/response behaviour, no refactor of controllers, no ORM, no
renaming of the `Reponse` namespace.

## 2. Chosen approach — attributes-first

* `nelmio/api-doc-bundle` **5.x** (Symfony 7 compatible) as the runtime: renders the spec
  (`/api/doc.json`) and the UI (`/api/doc`).
* `zircote/swagger-php` **5.x** as the schema engine: builds the document by scanning PHP
  attributes (`#[OA\...]`).
* Every controller action carries `#[OA\...]` metadata; request DTOs and response envelopes
  carry `#[OA\Schema]`.

Why this and not annotation-free generation: the codebase has no validator attributes and no
phpdoc on DTOs, and response bodies are built with `Response::success(mixed $data)` — payload
shapes are simply not present in the type system. A reflection-only generator would emit
`data: {}` everywhere. Attributes are the only way to get a *useful* spec, and they live next to
the code they describe.

```mermaid
flowchart LR
  A[Route attributes] --> D[swagger-php scanner]
  B[OA attributes on controllers] --> D
  C[OA Schema on DTOs and envelopes] --> D
  D --> E[OpenAPI 3.1 document]
  E --> F[/api/doc.json]
  E --> G[/api/doc Swagger UI]
  E --> H[CI drift test]
  E --> I[openapi.json artifact in repo]
```

## 3. Layout and layer constraints

| Rule | Consequence |
|---|---|
| Root [deptrac.yaml](deptrac.yaml:8) isolates modules; `Unitman`/`Runner` may depend only on `BackgroundJob` | No new cross-module imports. Documentation code must not be imported by modules |
| [src/App/.modulite.yaml](src/App/.modulite.yaml:7) exports only `Api\AccountApi`-style entrypoints; everything else is internal | Controllers must **not** import `App\Documentation\...` classes; schemas are referenced by **string** `ref: '#/components/schemas/Name'` |
| `src/**/{di,routing}.php` auto-loaded by [Kernel](src/Kernel.php:19) | New `src/App/Documentation/` needs no DI wiring (attributes only) |
| Module routing imports `./Infra/Controller` | Controllers stay where they are; only attributes are added |

Placement:

* `src/App/Documentation/OpenApiInfo.php` — root `#[OA\Info]`, `#[OA\Server]`, `#[OA\SecurityScheme]`.
* `src/App/Documentation/Schema/*.php` — shared envelope schemas (`SuccessEnvelope`,
  `FailEnvelope`, `ErrorEnvelope`).
* `#[OA\Schema]` for request payloads goes **on the DTO class itself** in
  `src/<Module>/Business/Command/...`. Attributes are inert metadata (no runtime, no
  behavioural dependency), and `OpenApi\Attributes` is vendor code, so neither deptrac nor
  modulite flags it. This keeps the schema next to the field it documents.
* Response payload schemas: annotate the `Business/ReadModel` classes that are actually returned
  (e.g. `AccountForManaging`, `UserList`, `AccountSettingsReadModel`), same rationale.

## 4. Conventions to follow when annotating

1. **Full paths in operations.** `#[Route('/register')]` under `#[Route('/api/account')]` must be
   documented as `path: '/api/account/register'` — swagger-php does not merge route prefixes.
2. **One tag per module**: `Account`, `Unitman`, `Project`, `Repo`, `Webhook`, `Runner`,
   `Initialization`. UI groups by tag.
3. **Envelope responses** are always described through shared component refs, never inline twice.
4. **Public operations** (`/api/runner/saveSteps`, `/api/initialization/list`,
   `/api/project/{id}/hook`, `/api/login`) get `security: []`. All others get
   `security: [['bearerAuth' => []]]`, derived from [security.yaml](config/packages/security.yaml:27).
5. **Path parameters** (`/api/project/read/{id}`, `/api/project/webhook/{id}/read`, …) need an
   explicit `OA\Parameter(in: 'path', required: true)`; type is `string` (UUID), not int.
6. **Errors are 200 + `status: error`** today (see
   [InitializationControllerTest](src/Initialization/Tests/Api/InitializationControllerTest.php:87)).
   Document that honestly: response `200` with `ErrorEnvelope`, plus `401`; do not invent `400`.
7. Descriptions/summaries in Russian, matching the transliterated naming of the codebase.

## 5. Example annotations

### Root document

```php
namespace App\App\Documentation;

use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', title: 'Unitman API', description: 'API управления юнитами')]
#[OA\Server(url: '/', description: 'Текущий хост')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
final class OpenApiInfo
{
}
```

### Shared envelope (referenced by string from controllers)

```php
namespace App\App\Documentation\Schema;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SuccessEnvelope',
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'success'),
        new OA\Property(property: 'data', type: 'object', nullable: true),
    ],
    type: 'object'
)]
final class SuccessEnvelope
{
}
```

### Controller action

```php
#[OA\Post(
    path: '/api/account/register',
    summary: 'Регистрация аккаунта',
    tags: ['Account'],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: '#/components/schemas/RegisterAccountRequest')
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Идентификатор созданного аккаунта',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(
                        property: 'data',
                        properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid')],
                        type: 'object'
                    ),
                ],
                type: 'object'
            )
        ),
        new OA\Response(response: 401, description: 'Не авторизован'),
    ],
    security: [['bearerAuth' => []]]
)]
#[Route('/register')]
function register(RegisterAccount $command, RegisterAccountUseCase $useCase): Response
```

### DTO schema (auto-derived vs manual)

```php
// public readonly promoted properties -> swagger-php derives the schema
#[OA\Schema(schema: 'SozdatUnitRequest')]
final class SozdatUnit { /* ... */ }

// private properties + getters -> properties MUST be listed explicitly
#[OA\Schema(
    schema: 'RegisterAccountRequest',
    required: ['email', 'password', 'roles', 'locale'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'password', type: 'string'),
        new OA\Property(property: 'roles', type: 'string', example: 'ROLE_USER'),
        new OA\Property(property: 'locale', type: 'string', example: 'ru'),
        new OA\Property(property: 'nickname', type: 'string', nullable: true),
    ],
    type: 'object'
)]
final class RegisterAccount implements JsonBodySerializableInterface
```

Note: `RegisterAccount` declares `roles` as a **string**, not an array — the spec must reflect the
code, and this inconsistency is worth a separate code issue.

## 6. Configuration changes

### `config/packages/nelmio_api_doc.yaml`

```yaml
nelmio_api_doc:
    documentation:
        openapi: 3.1.0
    areas:
        default:
            path_patterns:
                - ^/api(?!/doc$)
            host_patterns: []
            documentation:
                info:
                    title: Unitman API
                    version: 1.0.0
    models: { names: [] }
    use_validation_groups: false
```

`path_patterns` keeps the doc routes themselves out of the document.

### `config/routes/nelmio_api_doc.yaml`

```yaml
nelmio_api_doc.swagger_ui:
  path: /api/doc
  methods: GET
  defaults: { _controller: nelmio_api_doc.controller.swagger_ui, area: default }

nelmio_api_doc.json:
  path: /api/doc.json
  methods: GET
  defaults: { _controller: nelmio_api_doc.controller.swagger_json, area: default }
```

### `config/packages/security.yaml` — before the catch-all `^/api` rule

```yaml
        - { path: ^/api/doc, roles: PUBLIC_ACCESS }
```

### `config/bundles.php`

```php
Nelmio\ApiDocBundle\NelmioApiDocBundle::class => ['all' => true],
```

### Composer

```bash
composer require nelmio/api-doc-bundle:^5.0 zircote/swagger-php:^5.0
```

`extra.symfony.allow-contrib` is `false` in [composer.json](composer.json:122), so the Flex recipe
will not be applied. Either set it to `true` temporarily or add the bundle entry and config files
manually as shown above (preferred — no recipe side effects).

Swagger UI assets: nelmio 5 serves the UI from CDN by default. In an air-gapped environment
(`dc.mvp.yml`) publish assets locally and point `swagger_ui` at them.

## 7. Implementation order

1. Dependencies + bundle registration + both config files + `security.yaml` public path.
2. Root `OpenApiInfo` + the three envelope schemas.
3. `Account` module (13 operations) — establishes the pattern, including the
   `JsonBodySerializableInterface` binding (no `#[MapRequestPayload]`, body still documented via
   `OA\RequestBody`).
4. `Initialization` module (2 operations) — smallest, validates the public-endpoint convention.
5. `Runner` module (3 operations) — includes the public `saveSteps`.
6. `Unitman` module (approx. 50 operations) — split into `Project`, `Repo`, `Webhook`, `Unit`
   groups; path-anonymous actions (`/read/{id}`, `/user/{id}`) need explicit parameters and
   schemas since they take raw `string $id`.
7. Drift-guard test + `composer` script + optional committed `docs/openapi.json` artifact.

## 8. Drift guard

`src/App/Tests/Documentation/OpenApiCoverageTest.php` (picked up by the `src/*/Tests` suite):

```php
final class OpenApiCoverageTest extends WebTestCase
{
    public function testEveryApiRouteIsDocumented(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/doc.json');
        self::assertResponseIsSuccessful();

        /** @var array{paths: array<string, array<string, mixed>>} $spec */
        $spec = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $missing = [];
        foreach ($client->getContainer()->get('router')->getRouteCollection() as $name => $route) {
            $path = $route->getPath();
            if (!str_starts_with($path, '/api') || str_starts_with($path, '/api/doc')) {
                continue;
            }
            $pattern = preg_replace('/\{[^}]+\}/', '{}', $path);
            $documented = array_map(
                static fn (string $p): string => (string) preg_replace('/\{[^}]+\}/', '{}', $p),
                array_keys($spec['paths'])
            );
            if (!in_array($pattern, $documented, true)) {
                $missing[] = $name.' => '.$path;
            }
        }

        self::assertSame([], $missing, 'Незадокументированные маршруты: '.implode(', ', $missing));
    }
}
```

Add `"check-openapi": "php vendor/bin/phpunit --filter OpenApiCoverageTest"` and append it to
`all-checks` in [composer.json](composer.json:106).

## 9. Verification

Per project rules, run inside Docker after clearing cache:

```bash
docker compose exec web php bin/console cache:clear
docker compose exec web composer run check-psalm
docker compose exec web composer run check-phpunit
docker compose exec web composer run check-deptrac
docker compose exec web composer run check-unitman-deptrac
docker compose exec web composer run check-account-deptrac
docker compose exec web composer run check-runner-deptrac
docker compose exec web composer run check-init-deptrac
```

Then open `/api/doc` and confirm JWT authorisation works end-to-end against a live unit.

## 10. Risks and mitigations

| Risk | Mitigation |
|---|---|
| ~70 operations is a large annotation surface; drift returns after the first sprint | Coverage test in `all-checks`; make it part of the definition of done for new endpoints |
| `data` payloads are `mixed`; schemas will initially be shallow | Annotate ReadModels incrementally, starting with the ones used by the UI; `SuccessEnvelope.data` stays `object` until then |
| Swagger UI from CDN fails on internal networks | Publish assets locally in `dc.mvp.yml` |
| Psalm level 1 may complain about attribute arrays | Prefer explicit `new OA\Property(...)` objects (already the case) rather than array syntax; add targeted ignores only if unavoidable |
| Docs endpoint exposed in production | Decide: keep `/api/doc` public in `mvp` (convenient for testers) or restrict to `dev/test` via `when@prod` security rule |
| Nelmio 5 asset/route naming can differ between minor versions | Verify controller service ids with `bin/console debug:router` right after install |

## 11. Open decisions

1. Docs available in production / `mvp` or dev-only?
2. Commit the generated `docs/openapi.json` as a reviewable artifact, or generate on demand only?
3. Russian or English summaries and field descriptions (codebase is transliterated Russian; UX
   wording may be Russian, schema names stay English)?
