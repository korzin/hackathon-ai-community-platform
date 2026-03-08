<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2A;

use App\A2A\DevReporterA2AHandler;
use App\Logging\PayloadSanitizer;
use App\Repository\PipelineRunRepository;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class DevReporterA2AHandlerTest extends Unit
{
    private LoggerInterface&MockObject $logger;
    private Connection&MockObject $connection;
    private PipelineRunRepository $repository;
    private PayloadSanitizer $payloadSanitizer;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->repository = new PipelineRunRepository($this->connection);
        $this->payloadSanitizer = new PayloadSanitizer();
    }

    private function makeHandler(string $coreUrl = ''): DevReporterA2AHandler
    {
        return new DevReporterA2AHandler(
            $this->logger,
            $this->payloadSanitizer,
            $this->repository,
            $coreUrl,
        );
    }

    public function testIngestStoresRunAndReturnsRunId(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(42);

        $handler = $this->makeHandler();
        $result = $handler->handle([
            'intent' => 'devreporter.ingest',
            'request_id' => 'req-001',
            'payload' => [
                'pipeline_id' => '20260308_120000',
                'task' => 'Add streaming support',
                'branch' => 'pipeline/add-streaming',
                'status' => 'completed',
                'duration_seconds' => 2700,
                'agent_results' => [],
            ],
        ]);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(42, $result['run_id']);
        $this->assertSame('req-001', $result['request_id']);
    }

    public function testIngestWithMissingTaskReturnsFailed(): void
    {
        $this->connection->expects($this->never())->method('fetchOne');

        $handler = $this->makeHandler();
        $result = $handler->handle([
            'intent' => 'devreporter.ingest',
            'payload' => [
                'status' => 'completed',
            ],
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('task', $result['error']);
    }

    public function testIngestWithMissingStatusReturnsFailed(): void
    {
        $this->connection->expects($this->never())->method('fetchOne');

        $handler = $this->makeHandler();
        $result = $handler->handle([
            'intent' => 'devreporter.ingest',
            'payload' => [
                'task' => 'Some task',
            ],
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('status', $result['error']);
    }

    public function testIngestDbFailureReturnsFailed(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willThrowException(new \RuntimeException('DB error'));

        $handler = $this->makeHandler();
        $result = $handler->handle([
            'intent' => 'devreporter.ingest',
            'payload' => [
                'task' => 'Some task',
                'status' => 'completed',
            ],
        ]);

        $this->assertSame('failed', $result['status']);
    }

    public function testStatusReturnsRunsAndStats(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->stringContains('FROM pipeline_runs'),
                $this->callback(static fn (array $params): bool => 10 === $params['limit']),
            )
            ->willReturn([
                ['id' => 1, 'task' => 'Task A', 'status' => 'completed'],
            ]);

        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['total' => 1, 'passed' => 1, 'failed' => 0, 'pass_rate' => 100.0, 'avg_duration' => 2700.0]);

        $handler = $this->makeHandler();
        $result = $handler->handle([
            'intent' => 'devreporter.status',
            'payload' => [],
        ]);

        $this->assertSame('completed', $result['status']);
        $this->assertArrayHasKey('runs', $result['result']);
        $this->assertArrayHasKey('stats', $result['result']);
        $this->assertCount(1, $result['result']['runs']);
    }

    public function testStatusWithFiltersPassesThemToRepository(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                $this->stringContains('make_interval'),
                $this->callback(static fn (array $params): bool => 5 === $params['limit'] && 'failed' === $params['status'] && 7 === $params['days']),
            )
            ->willReturn([]);

        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                $this->stringContains('make_interval'),
                $this->callback(static fn (array $params): bool => 7 === $params['days']),
            )
            ->willReturn(['total' => 0, 'passed' => 0, 'failed' => 0, 'pass_rate' => 0.0, 'avg_duration' => 0.0]);

        $handler = $this->makeHandler();
        $result = $handler->handle([
            'intent' => 'devreporter.status',
            'payload' => [
                'limit' => 5,
                'days' => 7,
                'status_filter' => 'failed',
            ],
        ]);

        $this->assertSame('completed', $result['status']);
    }

    public function testNotifyWithMessageReturnsCompleted(): void
    {
        $handler = $this->makeHandler();
        $result = $handler->handle([
            'intent' => 'devreporter.notify',
            'payload' => [
                'message' => 'Hello from pipeline!',
            ],
        ]);

        $this->assertSame('completed', $result['status']);
    }

    public function testNotifyWithoutMessageReturnsFailed(): void
    {
        $handler = $this->makeHandler();
        $result = $handler->handle([
            'intent' => 'devreporter.notify',
            'payload' => [],
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('message', $result['error']);
    }

    public function testUnknownIntentReturnsFailed(): void
    {
        $handler = $this->makeHandler();
        $result = $handler->handle([
            'intent' => 'unknown.action',
            'request_id' => 'req-999',
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('req-999', $result['request_id']);
        $this->assertStringContainsString('Unknown intent', $result['error']);
    }

    public function testIngestPreservesRequestId(): void
    {
        $this->connection->method('fetchOne')->willReturn(1);

        $handler = $this->makeHandler();
        $result = $handler->handle([
            'intent' => 'devreporter.ingest',
            'request_id' => 'custom-req-id',
            'payload' => [
                'task' => 'Test task',
                'status' => 'completed',
            ],
        ]);

        $this->assertSame('custom-req-id', $result['request_id']);
    }
}
