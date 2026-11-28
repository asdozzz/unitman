<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\Controller;

use App\Account\Api\AccountApi;
use App\OpenCode\Business\AgentMode\Model\AgentMode;
use App\OpenCode\Business\AgentMode\Model\NastroykaRezhimaAgenta;
use App\OpenCode\Business\AgentMode\Port\AgentModeRepository;
use App\OpenCode\Business\AgentMode\Utils\OpredelitelRezhimaAgentaHoda;
use App\OpenCode\Business\AgentMode\Utils\ProverkaRezhimaAgenta;
use App\OpenCode\Business\Port\UuidGenerator;
use App\Utils\Model\Reponse\Response;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Каталог модов агентов OpenCode.
 *
 * Общие моды создаёт и меняет администратор. Персональные моды принадлежат
 * пользователю. Настройки (реквизиты, модель, видимость, порядок) и дефолты
 * по типу чата всегда принадлежат текущему пользователю.
 */
#[Route('/api/opencode/agent-modes')]
final class AgentModeController extends AbstractController
{
    public function __construct(
        private readonly AgentModeRepository $rezhimy,
        private readonly AccountApi $accountApi,
        private readonly UuidGenerator $uuidGenerator,
    ) {
    }

    #[OA\Get(
        path: '/api/opencode/agent-modes/spisok',
        summary: 'Доступные моды агентов и настройки текущего пользователя',
        tags: ['AgentMode'],
        responses: [new OA\Response(response: 200, description: 'Список модов')]
    )]
    #[Route('/spisok', methods: ['GET'])]
    public function spisok(): HttpResponse
    {
        return $this->json(Response::success($this->spisokDlyaTekuschego()));
    }

    #[OA\Post(
        path: '/api/opencode/agent-modes/read',
        summary: 'Полный мод агента',
        tags: ['AgentMode'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            type: 'object',
            required: ['id'],
            properties: [new OA\Property(property: 'id', type: 'string')]
        )),
        responses: [new OA\Response(response: 200, description: 'Мод')]
    )]
    #[Route('/read', methods: ['POST'])]
    public function read(Request $request): HttpResponse
    {
        $data = $request->toArray();
        $accountId = $this->accountApi->getCurrentUserId();
        $mode = $this->trebovatDostupnyy((string) ($data['id'] ?? ''), $accountId);

        return $this->json(Response::success($this->polnyyMassiv($mode)));
    }

    #[OA\Post(
        path: '/api/opencode/agent-modes/sozdat',
        summary: 'Создать мод агента',
        tags: ['AgentMode'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Идентификатор мода')]
    )]
    #[Route('/sozdat', methods: ['POST'])]
    public function sozdat(Request $request): HttpResponse
    {
        $data = $request->toArray();
        $accountId = $this->accountApi->getCurrentUserId();
        $scope = strtolower(trim((string) ($data['scope'] ?? 'personal')));
        $isGlobal = $scope === 'global';

        if ($isGlobal && !$this->accountApi->isAdmin()) {
            throw new \DomainException('opencode.agent_mode.forbidden');
        }

        $mode = AgentMode::create(
            $this->uuidGenerator->makeGuid(),
            is_string($data['slug'] ?? null) ? $data['slug'] : null,
            (string) ($data['name'] ?? ''),
            is_string($data['description'] ?? null) ? $data['description'] : '',
            (string) ($data['prompt'] ?? ''),
            (string) ($data['accessMode'] ?? ''),
            $isGlobal,
            $isGlobal ? null : $accountId,
            $accountId,
            time(),
        );

        $this->proveritUnikalnostSluga($mode, null);

        $this->rezhimy->save($mode);

        return $this->json(Response::success([
            'id' => $mode->getId(),
            'slug' => $mode->getSlug(),
        ]));
    }

    #[OA\Post(
        path: '/api/opencode/agent-modes/izmenit',
        summary: 'Изменить мод агента',
        tags: ['AgentMode'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Версия мода')]
    )]
    #[Route('/izmenit', methods: ['POST'])]
    public function izmenit(Request $request): HttpResponse
    {
        $data = $request->toArray();
        $accountId = $this->accountApi->getCurrentUserId();
        $mode = $this->trebovatDostupnyy((string) ($data['id'] ?? ''), $accountId);

        if ($mode->isGlobal()) {
            if (!$this->accountApi->isAdmin()) {
                throw new \DomainException('opencode.agent_mode.forbidden');
            }
        } elseif (!$mode->prinadlezhit($accountId)) {
            throw new \DomainException('opencode.agent_mode.not_found');
        }

        $mode->izmenit(
            $accountId,
            time(),
            is_string($data['slug'] ?? null) ? $data['slug'] : null,
            is_string($data['name'] ?? null) ? $data['name'] : null,
            is_string($data['description'] ?? null) ? $data['description'] : null,
            is_string($data['prompt'] ?? null) ? $data['prompt'] : null,
            is_string($data['accessMode'] ?? null) ? $data['accessMode'] : null,
        );

        $this->proveritUnikalnostSluga($mode, $mode->getId());
        $this->rezhimy->update($mode);

        return $this->json(Response::success([
            'id' => $mode->getId(),
            'version' => $mode->getVersion(),
        ]));
    }

    #[OA\Post(
        path: '/api/opencode/agent-modes/udalit',
        summary: 'Архивировать мод агента',
        tags: ['AgentMode'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Мод архивирован')]
    )]
    #[Route('/udalit', methods: ['POST'])]
    public function udalit(Request $request): HttpResponse
    {
        $data = $request->toArray();
        $accountId = $this->accountApi->getCurrentUserId();
        $mode = $this->trebovatDostupnyy((string) ($data['id'] ?? ''), $accountId);

        if ($mode->isGlobal()) {
            if (!$this->accountApi->isAdmin()) {
                throw new \DomainException('opencode.agent_mode.forbidden');
            }
        } elseif (!$mode->prinadlezhit($accountId)) {
            throw new \DomainException('opencode.agent_mode.not_found');
        }

        $mode->arhivirovat($accountId, time());
        $this->rezhimy->update($mode);

        return $this->json(Response::success(null));
    }

    #[OA\Post(
        path: '/api/opencode/agent-modes/nastroit',
        summary: 'Настроить мод для текущего пользователя',
        tags: ['AgentMode'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Настройки мода')]
    )]
    #[Route('/nastroit', methods: ['POST'])]
    public function nastroit(Request $request): HttpResponse
    {
        $data = $request->toArray();
        $accountId = $this->accountApi->getCurrentUserId();
        $mode = $this->trebovatDostupnyy((string) ($data['modeId'] ?? ''), $accountId);
        $now = time();

        $tekushchaya = $this->rezhimy->findNastroyka($accountId, $mode->getId())
            ?? NastroykaRezhimaAgenta::poUmolchaniyu($accountId, $mode->getId(), $now);

        $credentialId = array_key_exists('credentialId', $data)
            ? (is_string($data['credentialId']) ? $data['credentialId'] : null)
            : $tekushchaya->credentialId;
        $credentialId = $this->normalize($credentialId);

        $model = array_key_exists('model', $data)
            ? (is_string($data['model']) ? $data['model'] : null)
            : $tekushchaya->model;
        $model = $this->normalize($model);

        if ($credentialId === null) {
            $credentialId = $this->accountApi->poluchitPervieRekvizityOpenCodeDlyaVladelca($accountId);
        }

        if ($model !== null) {
            if ($credentialId === null) {
                throw new \DomainException('opencode.agent_mode.credential_required');
            }

            $modeli = $this->accountApi->poluchitModeliRekvizitovOpenCodeDlyVladelca($accountId, $credentialId);

            if (!in_array($model, $modeli->models, true)) {
                throw new \DomainException('account.opencode_credential.model_not_available');
            }
        }

        if ($credentialId !== null) {
            $this->accountApi->trebovatRekvizityOpenCodeVladelca($accountId, $credentialId);
        }

        $nastroyka = $tekushchaya
            ->sRekvizitami($credentialId, $model, $now)
            ->sVidimostyu(
                array_key_exists('enabled', $data) ? (bool) $data['enabled'] : $tekushchaya->enabled,
                array_key_exists('sortOrder', $data) ? (int) $data['sortOrder'] : $tekushchaya->sortOrder,
                $now,
            );

        $this->rezhimy->saveNastroyka($nastroyka);

        return $this->json(Response::success($this->massivNastroyki($nastroyka)));
    }

    #[OA\Post(
        path: '/api/opencode/agent-modes/poUmolchaniyu',
        summary: 'Назначить дефолтный мод для типа чата',
        tags: ['AgentMode'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [new OA\Response(response: 200, description: 'Дефолт сохранён')]
    )]
    #[Route('/poUmolchaniyu', methods: ['POST'])]
    public function poUmolchaniyu(Request $request): HttpResponse
    {
        $data = $request->toArray();
        $accountId = $this->accountApi->getCurrentUserId();
        $mode = $this->trebovatDostupnyy((string) ($data['modeId'] ?? ''), $accountId);

        $chatType = (new OpredelitelRezhimaAgentaHoda($this->rezhimy))
            ->normalizeChatType((string) ($data['chatType'] ?? ''));

        $this->rezhimy->saveDefault($accountId, $chatType, $mode->getId(), time());

        return $this->json(Response::success(null));
    }

    /**
     * @return array{modes: list<array<string, mixed>>, defaults: array<string, string>}
     */
    private function spisokDlyaTekuschego(): array
    {
        $accountId = $this->accountApi->getCurrentUserId();
        $modes = $this->rezhimy->findDostupnye($accountId);
        $nastroyki = $this->rezhimy->findNastroyki($accountId);
        $defaulty = $this->rezhimy->findDefaulty($accountId);

        $result = [];

        foreach ($modes as $mode) {
            $result[] = $this->kratkiyMassiv($mode, $nastroyki[$mode->getId()] ?? null, $defaulty);
        }

        usort($result, static function (array $left, array $right): int {
            $byOrder = ($left['sortOrder'] ?? 0) <=> ($right['sortOrder'] ?? 0);

            if ($byOrder !== 0) {
                return $byOrder;
            }

            return ($right['isGlobal'] ? 1 : 0) <=> ($left['isGlobal'] ? 1 : 0);
        });

        return [
            'modes' => $result,
            'defaults' => [
                OpredelitelRezhimaAgentaHoda::CHAT_UNIT => $defaulty[OpredelitelRezhimaAgentaHoda::CHAT_UNIT] ?? '',
                OpredelitelRezhimaAgentaHoda::CHAT_WORKSPACE => $defaulty[OpredelitelRezhimaAgentaHoda::CHAT_WORKSPACE] ?? '',
            ],
        ];
    }

    private function trebovatDostupnyy(string $id, string $accountId): AgentMode
    {
        $mode = $this->rezhimy->findById(trim($id));

        if ($mode === null || !$mode->dostupen($accountId)) {
            throw new \DomainException('opencode.agent_mode.not_found');
        }

        return $mode;
    }

    private function proveritUnikalnostSluga(AgentMode $mode, ?string $ignoredId): void
    {
        $existing = $mode->isGlobal()
            ? $this->rezhimy->findGlobalBySlug($mode->getSlug())
            : $this->rezhimy->findPersonalBySlug((string) $mode->getOwnerAccountId(), $mode->getSlug());

        if ($existing !== null && $existing.getId() !== $ignoredId) {
            throw new \DomainException('opencode.agent_mode.slug_taken');
        }
    }

    /**
     * @param array<string, string> $defaulty
     *
     * @return array<string, mixed>
     */
    private function kratkiyMassiv(AgentMode $mode, ?NastroykaRezhimaAgenta $nastroyka, array $defaulty): array
    {
        return [
            'id' => $mode->getId(),
            'slug' => $mode->getSlug(),
            'name' => $mode->getName(),
            'description' => $mode->getDescription(),
            'accessMode' => $mode->getAccessMode(),
            'isGlobal' => $mode->isGlobal(),
            'isOwner' => $mode->prinadlezhit($this->accountApi->getCurrentUserId()),
            'enabled' => $nastroyka?->enabled ?? true,
            'sortOrder' => $nastroyka?->sortOrder ?? 0,
            'credentialId' => $nastroyka?->credentialId,
            'model' => $nastroyka?->model,
            'isDefaultUnit' => ($defaulty[OpredelitelRezhimaAgentaHoda::CHAT_UNIT] ?? null) === $mode->getId(),
            'isDefaultWorkspace' => ($defaulty[OpredelitelRezhimaAgentaHoda::CHAT_WORKSPACE] ?? null) === $mode->getId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function polnyyMassiv(AgentMode $mode): array
    {
        return [
            'id' => $mode->getId(),
            'slug' => $mode->getSlug(),
            'name' => $mode->getName(),
            'description' => $mode->getDescription(),
            'prompt' => $mode->getPrompt(),
            'accessMode' => $mode->getAccessMode(),
            'isGlobal' => $mode->isGlobal(),
            'isOwner' => $mode->prinadlezhit($this->accountApi->getCurrentUserId()),
            'version' => $mode->getVersion(),
            'createdAt' => $mode->getCreatedAt(),
            'updatedAt' => $mode->getUpdatedAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function massivNastroyki(NastroykaRezhimaAgenta $nastroyka): array
    {
        return [
            'modeId' => $nastroyka->modeId,
            'credentialId' => $nastroyka->credentialId,
            'model' => $nastroyka->model,
            'enabled' => $nastroyka->enabled,
            'sortOrder' => $nastroyka->sortOrder,
        ];
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
