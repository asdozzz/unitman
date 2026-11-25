<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Infra\Command;

use App\AiChat\Infra\Command\AiChatRequest;
use PHPUnit\Framework\TestCase;

/**
 * Лимиты и нормализация DTO запроса AI-чата.
 */
final class AiChatRequestTest extends TestCase
{
    public function test_minimalniy_zapros_razbiraetsya(): void
    {
        $request = AiChatRequest::fromArray([
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ]);

        self::assertCount(1, $request->messages);
        self::assertSame([], $request->tools);
        self::assertNull($request->model);
        self::assertNull($request->credentialId);
        self::assertNull($request->sessionId);
    }

    public function test_session_id_razbiraetsya_i_pustaya_stroka_stanovitsya_null(): void
    {
        $request = AiChatRequest::fromArray([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'sessionId' => 'dialog-1.abc:def_2',
        ]);

        self::assertSame('dialog-1.abc:def_2', $request->sessionId);

        $pustoy = AiChatRequest::fromArray([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'sessionId' => '',
        ]);

        self::assertNull($pustoy->sessionId);
    }

    public function test_nekorrektniy_session_id_otklonyaetsya(): void
    {
        $this->ozhidatInvalidPayload([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'sessionId' => 'bad session',
        ]);

        $this->ozhidatInvalidPayload([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'sessionId' => "bad\nsession",
        ]);

        $this->ozhidatInvalidPayload([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'sessionId' => str_repeat('a', AiChatRequest::MAX_SESSION_ID_LENGTH + 1),
        ]);

        $this->ozhidatInvalidPayload([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'sessionId' => ['not', 'a', 'string'],
        ]);
    }

    public function test_model_i_credential_id_obrezayutsya_a_pustie_stanovyatsya_null(): void
    {
        $request = AiChatRequest::fromArray([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'model' => '  model-a  ',
            'credentialId' => '   ',
        ]);

        self::assertSame('model-a', $request->model);
        self::assertNull($request->credentialId);
    }

    public function test_slishkom_mnogo_soobsheniy_otklonyaetsya(): void
    {
        $messages = array_fill(0, AiChatRequest::MAX_MESSAGES + 1, ['role' => 'user', 'content' => 'x']);

        $this->ozhidatInvalidPayload(['messages' => $messages]);
    }

    public function test_slishkom_dlinnoe_soobshenie_otklonyaetsya(): void
    {
        $this->ozhidatInvalidPayload([
            'messages' => [[
                'role' => 'user',
                'content' => str_repeat('x', AiChatRequest::MAX_MESSAGE_LENGTH + 1),
            ]],
        ]);
    }

    public function test_slishkom_mnogo_instrumentov_otklonyaetsya(): void
    {
        $tool = [
            'type' => 'function',
            'function' => ['name' => 'tool_name'],
        ];

        $this->ozhidatInvalidPayload([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'tools' => array_fill(0, AiChatRequest::MAX_TOOLS + 1, $tool),
        ]);
    }

    public function test_nekorrektnoe_imya_instrumenta_otklonyaetsya(): void
    {
        $this->ozhidatInvalidPayload([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'tools' => [[
                'type' => 'function',
                'function' => ['name' => 'имя инструмента'],
            ]],
        ]);

        $this->ozhidatInvalidPayload([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'tools' => [[
                'type' => 'function',
                'function' => ['name' => str_repeat('a', AiChatRequest::MAX_TOOL_NAME_LENGTH + 1)],
            ]],
        ]);
    }

    public function test_nekorrektnaya_rol_otklonyaetsya(): void
    {
        $this->ozhidatInvalidPayload([
            'messages' => [['role' => 'owner', 'content' => 'hi']],
        ]);
    }

    public function test_nekorrektnaya_struktura_soobsheniy_otklonyaetsya(): void
    {
        $this->ozhidatInvalidPayload(['messages' => []]);
        $this->ozhidatInvalidPayload(['messages' => 'not-a-list']);
        $this->ozhidatInvalidPayload(['messages' => [['content' => 'without role']]]);
    }

    public function test_nekorrektniy_model_otklonyaetsya(): void
    {
        $this->ozhidatInvalidPayload([
            'messages' => [['role' => 'user', 'content' => 'hi']],
            'model' => ['not', 'a', 'string'],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function ozhidatInvalidPayload(array $payload): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ai.chat.invalid_payload');

        AiChatRequest::fromArray($payload);
    }
}
