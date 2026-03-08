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
            'name' => 'knowledge-agent',
            'version' => '1.0.0',
            'description' => 'Knowledge base management and semantic search',
            'url' => 'http://knowledge-agent/api/v1/knowledge/a2a',
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
                    'id' => 'knowledge.search',
                    'name' => 'Knowledge Search',
                    'description' => 'Search the knowledge base using semantic and hybrid search',
                    'tags' => ['search', 'knowledge'],
                ],
                [
                    'id' => 'knowledge.upload',
                    'name' => 'Knowledge Upload',
                    'description' => 'Extract and store knowledge from messages',
                    'tags' => ['upload', 'knowledge'],
                ],
                [
                    'id' => 'knowledge.store_message',
                    'name' => 'Knowledge Store Message',
                    'description' => 'Persist source message with full metadata for future knowledge workflows',
                    'tags' => ['ingestion', 'metadata', 'knowledge'],
                ],
            ],
            'skill_schemas' => [
                'knowledge.store_message' => [
                    'description' => 'Persist source message with structured metadata and raw payload.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'object',
                                'description' => 'Source message object with ids, sender, chat, text, and timestamps',
                            ],
                            'metadata' => [
                                'type' => 'object',
                                'description' => 'Additional contextual metadata (channel, event_type, tags, etc.)',
                            ],
                        ],
                    ],
                ],
            ],
            'permissions' => ['admin', 'moderator'],
            'commands' => ['/wiki', '/knowledge'],
            'events' => ['message.created'],
            'health_url' => 'http://knowledge-agent/health',
            'admin_url' => $this->adminPublicUrl,
            'storage' => [
                'postgres' => [
                    'db_name' => 'knowledge_agent',
                    'user' => 'knowledge_agent',
                    'password' => 'knowledge_agent',
                    'startup_migration' => [
                        'enabled' => true,
                        'mode' => 'best_effort',
                        'command' => 'php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true',
                    ],
                ],
                'redis' => [
                    'db_number' => 1,
                ],
                'opensearch' => [
                    'collections' => ['knowledge_entries'],
                ],
            ],
        ]);
    }
}
