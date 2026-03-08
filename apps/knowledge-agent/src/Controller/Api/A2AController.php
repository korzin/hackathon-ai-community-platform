<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\A2A\KnowledgeA2AHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class A2AController extends AbstractController
{
    public function __construct(
        private readonly KnowledgeA2AHandler $handler,
        private readonly string $internalToken,
    ) {
    }

    #[Route('/api/v1/knowledge/a2a', name: 'api_knowledge_a2a', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-Platform-Internal-Token');
        if ($token !== $this->internalToken) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            return $this->json(['error' => 'Invalid A2A payload'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var array<string, mixed> $a2aRequest */
        $a2aRequest = isset($data['request']) && \is_array($data['request'])
            ? $data['request']
            : $data;

        if (!isset($a2aRequest['intent'])) {
            return $this->json(['error' => 'Invalid A2A payload: intent is required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->handler->handle($a2aRequest);

        return $this->json($result);
    }
}
