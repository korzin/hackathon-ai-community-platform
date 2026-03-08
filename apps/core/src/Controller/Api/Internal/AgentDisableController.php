<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\AgentDiscovery\OpenClawSyncService;
use App\AgentRegistry\AgentRegistryAuditLogger;
use App\AgentRegistry\AgentRegistryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AgentDisableController extends AbstractController
{
    public function __construct(
        private readonly AgentRegistryRepository $registry,
        private readonly AgentRegistryAuditLogger $audit,
        private readonly OpenClawSyncService $syncService,
    ) {
    }

    #[Route('/api/v1/internal/agents/{name}/disable', name: 'api_internal_agents_disable', methods: ['POST'])]
    public function __invoke(string $name): JsonResponse
    {
        $actor = $this->getUser()?->getUserIdentifier() ?? 'unknown';

        $updated = $this->registry->disable($name);

        if (!$updated) {
            return $this->json(['error' => sprintf('Agent "%s" not found', $name)], Response::HTTP_NOT_FOUND);
        }

        $this->audit->log($name, 'disabled', $actor);
        $this->syncService->pushDiscovery();

        return $this->json(['status' => 'disabled', 'name' => $name]);
    }
}
