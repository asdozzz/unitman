<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Model\Project\Event\ProjectWasAdded;
use EventSauce\EventSourcing\Serialization\MessageSerializer;
use Ramsey\Uuid\Uuid;

/**
 * Регрессия на безопасное удаление legacy-полей proxy-модели из event store.
 *
 * Фикстура воспроизводит сообщение `ProjectWasAdded` старого формата, где в
 * `payload->payload` ещё лежал `proxyHost`. Тест применяет ту же JSONB-очистку,
 * что и `Version20261124150000`, и проверяет, что после неё событие
 * по-прежнему десериализуется текущим сериализатором EventSauce.
 */
final class LegacyProxyHostEventCleanupTest extends AbstractProjectUseCase
{
    public function test_legacy_event_with_proxy_host_deserializes_after_cleanup(): void
    {
        $projectId = Uuid::uuid7()->toString();
        $repoId = Uuid::uuid7()->toString();
        $eventId = Uuid::uuid7()->toString();

        $legacyMessage = [
            'headers' => [
                '__event_type' => 'ProjectWasAdded',
                '__aggregate_root_id' => $projectId,
                '__aggregate_root_type' => 'Project',
                '__aggregate_root_version' => 1,
            ],
            'payload' => [
                // ObjectHydrator хранит свойства события в snake_case.
                'id' => $projectId,
                'repo_id' => $repoId,
                'project_code' => 'unitman-tests/' . $projectId,
                'project_name' => 'Legacy-' . $projectId,
                'main_branch' => 'main',
                // Ключ, которого больше нет в ProjectWasAdded.
                'proxy_host' => 'legacy.proxy.example',
            ],
        ];

        self::$db->insert('unitman_event_store', [
            'event_id' => $eventId,
            'aggregate_root_id' => $projectId,
            'version' => 1,
            'payload' => json_encode($legacyMessage, JSON_THROW_ON_ERROR),
        ]);

        // Ровно та очистка, что выполняет Version20261124150000.
        self::$db->executeStatement(
            <<<'SQL'
                update unitman_event_store
                set payload = payload
                    #- '{payload,proxy_host}' #- '{payload,new_proxy_host}'
                    #- '{payload,proxyHost}'  #- '{payload,newProxyHost}'
                where aggregate_root_id = :id
                  and (jsonb_exists(payload->'payload', 'proxy_host')
                       or jsonb_exists(payload->'payload', 'new_proxy_host')
                       or jsonb_exists(payload->'payload', 'proxyHost')
                       or jsonb_exists(payload->'payload', 'newProxyHost'))
                SQL,
            ['id' => $projectId],
        );

        $raw = self::$db->fetchOne(
            'select payload::text from unitman_event_store where aggregate_root_id = ?',
            [$projectId],
        );
        self::assertIsString($raw);

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('proxy_host', $decoded['payload']);
        self::assertArrayNotHasKey('new_proxy_host', $decoded['payload']);
        self::assertArrayNotHasKey('proxyHost', $decoded['payload']);
        self::assertArrayNotHasKey('newProxyHost', $decoded['payload']);

        $serializer = self::$container->get('unitman.event_serializer');
        self::assertInstanceOf(MessageSerializer::class, $serializer);

        $message = $serializer->unserializePayload($decoded);
        $event = $message->payload();

        self::assertInstanceOf(ProjectWasAdded::class, $event);
        self::assertSame($projectId, $event->id);
        self::assertSame('main', $event->mainBranch);
    }
}
