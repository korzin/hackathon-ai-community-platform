<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ManifestController extends AbstractController
{
    public function __construct(
        private readonly string $adminPublicUrl,
    ) {
    }

    #[Route('/api/v1/manifest', name: 'api_manifest', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'name' => 'dev-reporter-agent',
            'version' => '1.0.0',
            'description' => 'Pipeline observability agent — persists run history and delivers Telegram notifications',
            'url' => 'http://dev-reporter-agent/api/v1/a2a',
            'provider' => [
                'organization' => 'AI Community Platform',
                'url' => 'https://github.com/nmdimas/ai-community-platform',
            ],
            'capabilities' => [
                'streaming' => false,
                'pushNotifications' => false,
            ],
            'defaultInputModes' => ['text'],
            'defaultOutputModes' => ['text'],
            'skills' => [
                [
                    'id' => 'devreporter.ingest',
                    'name' => 'Pipeline Run Ingest',
                    'description' => 'Receives a pipeline run report, stores it in the database, and sends a Telegram notification.',
                    'tags' => ['pipeline', 'reporting'],
                    'examples' => ['Store pipeline result', 'Report pipeline completion'],
                ],
                [
                    'id' => 'devreporter.status',
                    'name' => 'Development Status',
                    'description' => 'Returns recent pipeline runs and aggregate statistics.',
                    'tags' => ['pipeline', 'status'],
                    'examples' => ['What was done last night?', 'Show failed pipelines', 'Development status'],
                ],
                [
                    'id' => 'devreporter.notify',
                    'name' => 'Send Notification',
                    'description' => 'Sends a custom message to Telegram via the platform messaging infrastructure.',
                    'tags' => ['notification', 'telegram'],
                    'examples' => ['Send notification', 'Notify team'],
                ],
            ],
            'skill_schemas' => [
                'devreporter.ingest' => [
                    'description' => 'Receives a pipeline run report and persists it.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'pipeline_id' => ['type' => 'string', 'description' => 'Timestamp-based pipeline ID'],
                            'task' => ['type' => 'string', 'description' => 'Task description'],
                            'branch' => ['type' => 'string', 'description' => 'Git branch name'],
                            'status' => ['type' => 'string', 'description' => 'completed or failed'],
                            'failed_agent' => ['type' => 'string', 'description' => 'Agent that caused failure (nullable)'],
                            'duration_seconds' => ['type' => 'integer', 'description' => 'Total pipeline duration'],
                            'agent_results' => ['type' => 'array', 'description' => 'Per-agent status and duration'],
                            'report_content' => ['type' => 'string', 'description' => 'Full Markdown report content'],
                        ],
                        'required' => ['task', 'status'],
                    ],
                ],
                'devreporter.status' => [
                    'description' => 'Returns recent pipeline runs and aggregate statistics.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Query type (recent)'],
                            'days' => ['type' => 'integer', 'description' => 'Time range in days'],
                            'limit' => ['type' => 'integer', 'description' => 'Max number of runs to return'],
                            'status_filter' => ['type' => 'string', 'description' => 'Filter by status (completed/failed)'],
                        ],
                    ],
                ],
                'devreporter.notify' => [
                    'description' => 'Sends a custom message to Telegram.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string', 'description' => 'Message text (HTML format)'],
                            'format' => ['type' => 'string', 'description' => 'Message format (html)'],
                        ],
                        'required' => ['message'],
                    ],
                ],
            ],
            'permissions' => [],
            'commands' => [],
            'events' => [],
            'admin_url' => '' !== $this->adminPublicUrl ? $this->adminPublicUrl : null,
            'health_url' => 'http://dev-reporter-agent/health',
        ]);
    }
}
