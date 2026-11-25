<?php

declare(strict_types=1);

namespace App\AiChat\Tests\Business\ReadModel;

use App\AiChat\Business\Model\SessiyaAiChata;
use App\AiChat\Business\Model\SoobshenieAiChata;
use App\AiChat\Business\ReadModel\RezultatHodaAiChataReadModel;
use App\AiChat\Business\ReadModel\SessiyaAiChataReadModel;
use App\AiChat\Business\ReadModel\SessiyaAiChataSPolnoiIstorieiReadModel;
use App\AiChat\Business\ReadModel\SoobshenieAiChataReadModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Тесты read-model AI-чата без БД: плоские toolCalls и обрезка превью.
 */
final class ReadModelyAiChataTest extends TestCase
{
    private const NOW = 1_000_000;

    #[Test]
    public function soobshenie_iz_domena_daet_ploskij_tool_calls(): void
    {
        $domain = SoobshenieAiChata::assistenta('m1', 's1', 2, null, [[
            'id' => 'call_1',
            'type' => 'function',
            'function' => ['name' => 'search', 'arguments' => '{"q":"x"}'],
        ]], self::NOW);

        $readModel = SoobshenieAiChataReadModel::izDomena($domain);

        $this->assertSame('assistant', $readModel->role);
        $this->assertSame(
            [['id' => 'call_1', 'name' => 'search', 'arguments' => '{"q":"x"}']],
            $readModel->toolCalls,
        );
    }

    #[Test]
    public function soobshenie_iz_stroki_dekodiruet_json_i_ploskij_format(): void
    {
        $row = [
            'id' => 'm2',
            'seq' => 3,
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => json_encode([[
                'id' => 'call_2',
                'type' => 'function',
                'function' => ['name' => 'list', 'arguments' => '{}'],
            ]]),
            'tool_call_id' => null,
            'name' => null,
            'created_at' => self::NOW,
        ];

        $readModel = SoobshenieAiChataReadModel::izStroki($row);

        $this->assertSame('m2', $readModel->id);
        $this->assertSame(3, $readModel->seq);
        $this->assertNull($readModel->content);
        $this->assertSame(
            [['id' => 'call_2', 'name' => 'list', 'arguments' => '{}']],
            $readModel->toolCalls,
        );
    }

    #[Test]
    public function soobshenie_iz_stroki_prinimaet_uzhe_ploskij_array(): void
    {
        $readModel = SoobshenieAiChataReadModel::izStroki([
            'id' => 'm3',
            'seq' => 1,
            'role' => 'assistant',
            'content' => 'ok',
            'tool_calls' => [['id' => 'c', 'name' => 'n', 'arguments' => '{}']],
            'tool_call_id' => null,
            'name' => null,
            'created_at' => self::NOW,
        ]);

        $this->assertSame(
            [['id' => 'c', 'name' => 'n', 'arguments' => '{}']],
            $readModel->toolCalls,
        );
    }

    #[Test]
    public function preview_obrezka_trim_i_limit(): void
    {
        $this->assertSame('Privet', SessiyaAiChataReadModel::sdelatPreview('  Privet  '));
        $this->assertSame('', SessiyaAiChataReadModel::sdelatPreview(null));
        $this->assertSame('', SessiyaAiChataReadModel::sdelatPreview("   \n "));

        $preview = SessiyaAiChataReadModel::sdelatPreview(str_repeat('ж', 250));

        $this->assertSame(SessiyaAiChata::PREVIEW_MAX_LENGTH, mb_strlen($preview));
    }

    #[Test]
    public function sessiya_v_massiv_soderzhit_ozhidaemie_polya(): void
    {
        $readModel = $this->sessiyaReadModel();

        $this->assertSame([
            'id' => 's1',
            'title' => 'Заголовок',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'archived' => true,
            'archiveReason' => 'expired',
            'credentialDeleted' => false,
            'preview' => 'Превью',
            'createdAt' => self::NOW,
            'updatedAt' => self::NOW + 10,
            'expiresAt' => self::NOW + 86_400,
            'messageCount' => 4,
        ], $readModel->vMassiv());
    }

    #[Test]
    public function sessiya_s_polnoj_istoriej_v_massiv(): void
    {
        $message = new SoobshenieAiChataReadModel(
            'm1',
            1,
            'user',
            'Privet',
            [],
            null,
            null,
            self::NOW,
        );

        $readModel = new SessiyaAiChataSPolnoiIstorieiReadModel(
            $this->sessiyaReadModel(),
            [$message],
        );

        $massiv = $readModel->vMassiv();

        $this->assertArrayHasKey('session', $massiv);
        $this->assertArrayHasKey('messages', $massiv);
        $this->assertSame('s1', $massiv['session']['id']);
        $this->assertSame('m1', $massiv['messages'][0]['id']);
    }

    #[Test]
    public function rezultat_hoda_v_massiv_imeet_formu_assistant_session(): void
    {
        $readModel = new RezultatHodaAiChataReadModel(
            'Готово',
            [['id' => 'call_1', 'name' => 'search', 'arguments' => '{}']],
            's1',
            self::NOW + 300,
            false,
            self::NOW + 10,
            'Заголовок',
        );

        $this->assertSame([
            'assistant' => [
                'content' => 'Готово',
                'toolCalls' => [['id' => 'call_1', 'name' => 'search', 'arguments' => '{}']],
            ],
            'session' => [
                'id' => 's1',
                'providerSessionExpiresAt' => self::NOW + 300,
                'archived' => false,
                'updatedAt' => self::NOW + 10,
                'title' => 'Заголовок',
            ],
        ], $readModel->vMassiv());
    }

    private function sessiyaReadModel(): SessiyaAiChataReadModel
    {
        return new SessiyaAiChataReadModel(
            's1',
            'Заголовок',
            'openai',
            'gpt-4o-mini',
            true,
            'expired',
            false,
            'Превью',
            self::NOW,
            self::NOW + 10,
            self::NOW + 86_400,
            4,
        );
    }
}
