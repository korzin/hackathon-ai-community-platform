<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Repository\PipelineRunRepository;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;

final class PipelineRunRepositoryTest extends Unit
{
    private Connection&MockObject $connection;
    private PipelineRunRepository $repository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->repository = new PipelineRunRepository($this->connection);
    }

    public function testInsertReturnsIdFromReturningClause(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('7');

        $id = $this->repository->insert([
            'pipeline_id' => '20260308_120000',
            'task' => 'Add streaming support',
            'branch' => 'pipeline/add-streaming',
            'status' => 'completed',
            'duration_seconds' => 2700,
            'agent_results' => [['agent' => 'Coder', 'status' => 'pass', 'duration' => 900]],
        ]);

        $this->assertSame(7, $id);
    }

    public function testInsertEncodesAgentResultsAsJson(): void
    {
        $capturedSql = '';
        $capturedParams = [];

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params) use (&$capturedSql, &$capturedParams): string {
                $capturedSql = $sql;
                $capturedParams = $params;

                return '1';
            });

        $this->repository->insert([
            'task' => 'Test task',
            'status' => 'completed',
            'agent_results' => [['agent' => 'Coder', 'status' => 'pass', 'duration' => 60]],
        ]);

        $this->assertStringContainsString('RETURNING id', $capturedSql);
        $this->assertStringContainsString('CAST(:agent_results AS jsonb)', $capturedSql);
        $this->assertJson($capturedParams['agent_results']);
        $decoded = json_decode($capturedParams['agent_results'], true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
    }

    public function testInsertUsesEmptyJsonArrayWhenAgentResultsNotArray(): void
    {
        $capturedParams = [];

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params) use (&$capturedParams): string {
                $capturedParams = $params;

                return '1';
            });

        $this->repository->insert([
            'task' => 'Test task',
            'status' => 'failed',
        ]);

        $this->assertSame('[]', $capturedParams['agent_results']);
    }

    public function testInsertSetsNullForEmptyFailedAgent(): void
    {
        $capturedParams = [];

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql, array $params) use (&$capturedParams): string {
                $capturedParams = $params;

                return '1';
            });

        $this->repository->insert([
            'task' => 'Test task',
            'status' => 'completed',
            'failed_agent' => '',
        ]);

        $this->assertNull($capturedParams['failed_agent']);
    }

    public function testFindRecentDelegatesToConnection(): void
    {
        $expected = [
            ['id' => 1, 'task' => 'Task A', 'status' => 'completed'],
        ];

        $this->connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($expected);

        $result = $this->repository->findRecent(10);

        $this->assertSame($expected, $result);
    }

    public function testGetStatsReturnsZeroesWhenNoRows(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $stats = $this->repository->getStats();

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['passed']);
        $this->assertSame(0, $stats['failed']);
        $this->assertSame(0.0, $stats['pass_rate']);
        $this->assertSame(0.0, $stats['avg_duration']);
    }

    public function testGetStatsCalculatesPassRate(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'total' => '4',
                'passed' => '3',
                'failed' => '1',
                'avg_duration' => '1800.0',
            ]);

        $stats = $this->repository->getStats();

        $this->assertSame(4, $stats['total']);
        $this->assertSame(3, $stats['passed']);
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(75.0, $stats['pass_rate']);
        $this->assertSame(1800.0, $stats['avg_duration']);
    }
}
