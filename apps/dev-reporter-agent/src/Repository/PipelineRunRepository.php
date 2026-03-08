<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class PipelineRunRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $agentResults = isset($data['agent_results']) && \is_array($data['agent_results'])
            ? json_encode($data['agent_results'], \JSON_THROW_ON_ERROR)
            : '[]';

        $id = $this->connection->fetchOne(<<<'SQL'
            INSERT INTO pipeline_runs (
                pipeline_id,
                task,
                branch,
                status,
                failed_agent,
                duration_seconds,
                agent_results,
                report_content,
                created_at
            ) VALUES (
                :pipeline_id,
                :task,
                :branch,
                :status,
                :failed_agent,
                :duration_seconds,
                CAST(:agent_results AS jsonb),
                :report_content,
                now()
            )
            RETURNING id
        SQL, [
            'pipeline_id' => (string) ($data['pipeline_id'] ?? ''),
            'task' => (string) ($data['task'] ?? ''),
            'branch' => (string) ($data['branch'] ?? ''),
            'status' => (string) ($data['status'] ?? 'completed'),
            'failed_agent' => isset($data['failed_agent']) && '' !== $data['failed_agent'] ? (string) $data['failed_agent'] : null,
            'duration_seconds' => isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : 0,
            'agent_results' => $agentResults,
            'report_content' => isset($data['report_content']) && '' !== $data['report_content'] ? (string) $data['report_content'] : null,
        ]);

        return (int) $id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findRecent(int $limit = 10, ?int $days = null, ?string $statusFilter = null): array
    {
        $conditions = [];
        $params = [];

        if (null !== $days && $days > 0) {
            $conditions[] = 'created_at >= now() - make_interval(days => :days)';
            $params['days'] = $days;
        }

        if (null !== $statusFilter && '' !== $statusFilter) {
            $conditions[] = 'status = :status';
            $params['status'] = $statusFilter;
        }

        $where = [] !== $conditions ? 'WHERE '.implode(' AND ', $conditions) : '';

        $params['limit'] = $limit;

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, pipeline_id, task, branch, status, failed_agent, duration_seconds, agent_results, created_at
             FROM pipeline_runs
             {$where}
             ORDER BY created_at DESC
             LIMIT :limit",
            $params,
        );

        return $rows;
    }

    /**
     * @return array{total: int, passed: int, failed: int, pass_rate: float, avg_duration: float}
     */
    public function getStats(?int $days = null): array
    {
        $where = '';
        $params = [];

        if (null !== $days && $days > 0) {
            $where = 'WHERE created_at >= now() - make_interval(days => :days)';
            $params['days'] = $days;
        }

        /** @var array{total: string, passed: string, failed: string, avg_duration: string}|false $row */
        $row = $this->connection->fetchAssociative(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS passed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                AVG(duration_seconds) AS avg_duration
             FROM pipeline_runs
             {$where}",
            $params,
        );

        if (false === $row) {
            return ['total' => 0, 'passed' => 0, 'failed' => 0, 'pass_rate' => 0.0, 'avg_duration' => 0.0];
        }

        $total = (int) ($row['total'] ?? 0);
        $passed = (int) ($row['passed'] ?? 0);
        $failed = (int) ($row['failed'] ?? 0);
        $avgDuration = (float) ($row['avg_duration'] ?? 0.0);
        $passRate = $total > 0 ? round($passed / $total * 100, 1) : 0.0;

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'pass_rate' => $passRate,
            'avg_duration' => round($avgDuration, 1),
        ];
    }
}
