<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Repository\SourceMessageRepository;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;

final class SourceMessageRepositoryTest extends Unit
{
    private Connection&MockObject $connection;
    private SourceMessageRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->repository = new SourceMessageRepository($this->connection);
    }

    public function testUpsertStoresNormalizedFieldsAndReturnsId(): void
    {
        $payload = [
            'message' => [
                'platform' => 'telegram',
                'event_type' => 'message_created',
                'chat' => ['id' => '-100123', 'title' => 'Community Chat', 'type' => 'supergroup'],
                'message_id' => 'm-42',
                'thread_id' => 'thread-1',
                'text' => 'Hello knowledge',
                'author' => [
                    'id' => 'u-7',
                    'username' => 'john',
                    'display_name' => 'John Doe',
                ],
                'sent_at' => '2026-03-07T12:00:00Z',
            ],
            'metadata' => [
                'channel' => 'telegram.main',
            ],
        ];

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('INSERT INTO knowledge_source_messages'),
                $this->callback(function (array $params): bool {
                    $this->assertSame('telegram', $params['source_platform']);
                    $this->assertSame('-100123', $params['chat_id']);
                    $this->assertSame('m-42', $params['message_id']);
                    $this->assertSame('john', $params['sender_username']);
                    $this->assertSame('John Doe', $params['sender_display_name']);
                    $this->assertSame('Hello knowledge', $params['message_text']);
                    $this->assertSame('trace-1', $params['trace_id']);
                    $this->assertSame('req-1', $params['request_id']);
                    $this->assertNotNull($params['message_timestamp']);

                    return true;
                }),
            )
            ->willReturn('msg-row-1');

        $id = $this->repository->upsert($payload, 'req-1', 'trace-1');

        $this->assertSame('msg-row-1', $id);
    }

    public function testUpsertSupportsFlatPayloadAndDefaults(): void
    {
        $payload = [
            'chat_id' => 'chat-flat',
            'message_id' => 'msg-flat',
            'text' => 'Flat payload',
            'sender_id' => 77,
            'date' => 1710000000,
        ];

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('INSERT INTO knowledge_source_messages'),
                $this->callback(function (array $params): bool {
                    $this->assertSame('telegram', $params['source_platform']);
                    $this->assertSame('message_created', $params['event_type']);
                    $this->assertSame('chat-flat', $params['chat_id']);
                    $this->assertSame('msg-flat', $params['message_id']);
                    $this->assertSame('77', $params['sender_id']);

                    return true;
                }),
            )
            ->willReturn('msg-row-flat');

        $id = $this->repository->upsert($payload, 'req-flat', null);

        $this->assertSame('msg-row-flat', $id);
    }
}
