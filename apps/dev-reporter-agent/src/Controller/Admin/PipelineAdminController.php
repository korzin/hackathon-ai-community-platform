<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\PipelineRunRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PipelineAdminController extends AbstractController
{
    public function __construct(
        private readonly PipelineRunRepository $repository,
        private readonly string $adminPublicUrl,
    ) {
    }

    #[Route('/admin/pipeline', name: 'admin_pipeline', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $statusFilter = $request->query->get('status');
        $allowedStatuses = ['completed', 'failed'];
        $statusFilter = \is_string($statusFilter) && \in_array($statusFilter, $allowedStatuses, true) ? $statusFilter : null;

        $runs = $this->repository->findRecent(50, null, $statusFilter);
        $stats = $this->repository->getStats(7);

        // Decode agent_results JSON string to array for each run so the template
        // doesn't need a custom |json_decode Twig filter.
        $runs = array_map(static function (array $run): array {
            if (isset($run['agent_results']) && \is_string($run['agent_results'])) {
                $decoded = json_decode($run['agent_results'], true);
                $run['agent_results_count'] = \is_array($decoded) ? \count($decoded) : 0;
            } else {
                $run['agent_results_count'] = 0;
            }

            return $run;
        }, $runs);

        return $this->render('admin/pipeline/index.html.twig', [
            'runs' => $runs,
            'stats' => $stats,
            'status_filter' => $statusFilter,
            'admin_public_url' => $this->adminPublicUrl,
        ]);
    }
}
