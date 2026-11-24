<?php

declare(strict_types=1);

namespace App\Runner\Business\UseCase;

use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidPayloadException;
use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidProjectIdException;

/**
 * Валидация входа build-state эндпоинтов (контракт §3).
 *
 * `projectId` в контракте не зафиксирован как UUID: допускаются и UUID, и
 * backend-идентификатор проекта. Поэтому проверяется только безопасная форма
 * строки и лимит колонки `project_build_state.project_id` (`varchar(64)`), а
 * не конкретный формат.
 */
final class ProjectBuildStateInputGuard
{
    private const MAX_PROJECT_ID_LENGTH = 64;
    private const MAX_COMMIT_LENGTH = 64;
    private const MAX_BRANCH_LENGTH = 256;

    private const PROJECT_ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]*$/';

    /**
     * @throws ProjectBuildStateInvalidProjectIdException
     */
    public function validateProjectId(string $projectId): string
    {
        $projectId = trim($projectId);

        if (
            $projectId === ''
            || strlen($projectId) > self::MAX_PROJECT_ID_LENGTH
            || preg_match(self::PROJECT_ID_PATTERN, $projectId) !== 1
        ) {
            throw new ProjectBuildStateInvalidProjectIdException();
        }

        return $projectId;
    }

    /**
     * @return array{builtAt: ?int, commit: ?string, branch: ?string}
     *
     * @throws ProjectBuildStateInvalidPayloadException
     */
    public function parseRecordPayload(string $rawBody): array
    {
        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ProjectBuildStateInvalidPayloadException();
        }

        if (!is_array($payload)) {
            throw new ProjectBuildStateInvalidPayloadException();
        }

        return [
            'builtAt' => $this->extractBuiltAt($payload),
            'commit' => $this->extractMetadata($payload, 'commit', self::MAX_COMMIT_LENGTH),
            'branch' => $this->extractMetadata($payload, 'branch', self::MAX_BRANCH_LENGTH),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ProjectBuildStateInvalidPayloadException
     */
    private function extractBuiltAt(array $payload): ?int
    {
        if (!array_key_exists('builtAt', $payload) || $payload['builtAt'] === null) {
            return null;
        }

        $builtAt = $payload['builtAt'];

        if (!is_int($builtAt) || $builtAt < 0) {
            throw new ProjectBuildStateInvalidPayloadException();
        }

        return $builtAt;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ProjectBuildStateInvalidPayloadException
     */
    private function extractMetadata(array $payload, string $key, int $maxLength): ?string
    {
        if (!array_key_exists($key, $payload) || $payload[$key] === null) {
            return null;
        }

        $value = $payload[$key];

        if (!is_string($value)) {
            throw new ProjectBuildStateInvalidPayloadException();
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (strlen($value) > $maxLength) {
            throw new ProjectBuildStateInvalidPayloadException();
        }

        return $value;
    }
}
