<?php

declare(strict_types=1);

namespace App\A2A;

use App\Logging\PayloadSanitizer;
use App\Logging\TraceEvent;
use App\Repository\PipelineRunRepository;
use Psr\Log\LoggerInterface;

final class DevReporterA2AHandler
{
    private const SERVICE_NAME = 'dev-reporter-agent';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PayloadSanitizer $payloadSanitizer,
        private readonly PipelineRunRepository $repository,
        private readonly string $platformCoreUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function handle(array $request): array
    {
        $intent = (string) ($request['intent'] ?? '');
        $requestId = (string) ($request['request_id'] ?? uniqid('a2a_', true));
        $traceId = (string) ($request['trace_id'] ?? '');

        /** @var array<string, mixed> $payload */
        $payload = $request['payload'] ?? [];

        $logCtx = ['intent' => $intent, 'request_id' => $requestId, 'trace_id' => $traceId];

        return match ($intent) {
            'devreporter.ingest' => $this->handleIngest($payload, $requestId, $logCtx),
            'devreporter.status' => $this->handleStatus($payload, $requestId, $logCtx),
            'devreporter.notify' => $this->handleNotify($payload, $requestId, $logCtx),
            default => $this->handleUnknown($intent, $requestId, $logCtx),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $logCtx
     *
     * @return array<string, mixed>
     */
    private function handleIngest(array $payload, string $requestId, array $logCtx): array
    {
        $sanitizedInput = $this->payloadSanitizer->sanitize($payload);
        $this->logger->info(
            'Intent devreporter.ingest started',
            TraceEvent::build('devreporter.intent.ingest.started', 'intent_handle', self::SERVICE_NAME, 'started', $logCtx + [
                'target_app' => self::SERVICE_NAME,
                'intent' => 'devreporter.ingest',
                'step_input' => $sanitizedInput['data'],
                'capture_meta' => $sanitizedInput['capture_meta'],
            ]),
        );

        // Validate required fields
        foreach (['task', 'status'] as $required) {
            if (!isset($payload[$required]) || '' === (string) $payload[$required]) {
                $result = [
                    'status' => 'failed',
                    'request_id' => $requestId,
                    'error' => "Missing required field: {$required}",
                ];
                $this->logger->warning(
                    'Ingest validation failed',
                    TraceEvent::build('devreporter.intent.ingest.validation_failed', 'intent_handle', self::SERVICE_NAME, 'failed', $logCtx + [
                        'error_code' => 'missing_required_field',
                        'field' => $required,
                    ]),
                );

                return $result;
            }
        }

        try {
            $runId = $this->repository->insert($payload);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to store pipeline run',
                TraceEvent::build('devreporter.intent.ingest.db_error', 'intent_handle', self::SERVICE_NAME, 'failed', $logCtx + [
                    'error_code' => 'db_insert_failed',
                    'error' => $e->getMessage(),
                ]),
            );

            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'Failed to store pipeline run',
            ];
        }

        // Send Telegram notification (best-effort, non-blocking)
        $this->sendTelegramNotification($payload, $logCtx);

        $result = [
            'status' => 'completed',
            'request_id' => $requestId,
            'run_id' => $runId,
        ];

        $sanitizedOutput = $this->payloadSanitizer->sanitize($result);
        $this->logger->info(
            'Intent devreporter.ingest completed',
            TraceEvent::build('devreporter.intent.ingest.completed', 'intent_handle', self::SERVICE_NAME, 'completed', $logCtx + [
                'target_app' => self::SERVICE_NAME,
                'intent' => 'devreporter.ingest',
                'run_id' => $runId,
                'step_output' => $sanitizedOutput['data'],
                'capture_meta' => $sanitizedOutput['capture_meta'],
            ]),
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $logCtx
     *
     * @return array<string, mixed>
     */
    private function handleStatus(array $payload, string $requestId, array $logCtx): array
    {
        $limit = isset($payload['limit']) ? min(max((int) $payload['limit'], 1), 100) : 10;
        $days = isset($payload['days']) ? max((int) $payload['days'], 1) : null;
        $allowedStatuses = ['completed', 'failed'];
        $statusFilter = isset($payload['status_filter']) && \in_array((string) $payload['status_filter'], $allowedStatuses, true)
            ? (string) $payload['status_filter']
            : null;

        $sanitizedInput = $this->payloadSanitizer->sanitize($payload);
        $this->logger->info(
            'Intent devreporter.status started',
            TraceEvent::build('devreporter.intent.status.started', 'intent_handle', self::SERVICE_NAME, 'started', $logCtx + [
                'target_app' => self::SERVICE_NAME,
                'intent' => 'devreporter.status',
                'step_input' => $sanitizedInput['data'],
                'capture_meta' => $sanitizedInput['capture_meta'],
            ]),
        );

        try {
            $runs = $this->repository->findRecent($limit, $days, $statusFilter);
            $stats = $this->repository->getStats($days);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Failed to query pipeline runs',
                TraceEvent::build('devreporter.intent.status.db_error', 'intent_handle', self::SERVICE_NAME, 'failed', $logCtx + [
                    'error_code' => 'db_query_failed',
                    'error' => $e->getMessage(),
                ]),
            );

            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'Failed to query pipeline runs',
            ];
        }

        $result = [
            'status' => 'completed',
            'request_id' => $requestId,
            'result' => [
                'runs' => $runs,
                'stats' => $stats,
            ],
        ];

        $sanitizedOutput = $this->payloadSanitizer->sanitize($result);
        $this->logger->info(
            'Intent devreporter.status completed',
            TraceEvent::build('devreporter.intent.status.completed', 'intent_handle', self::SERVICE_NAME, 'completed', $logCtx + [
                'target_app' => self::SERVICE_NAME,
                'intent' => 'devreporter.status',
                'runs_count' => \count($runs),
                'step_output' => $sanitizedOutput['data'],
                'capture_meta' => $sanitizedOutput['capture_meta'],
            ]),
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $logCtx
     *
     * @return array<string, mixed>
     */
    private function handleNotify(array $payload, string $requestId, array $logCtx): array
    {
        $message = (string) ($payload['message'] ?? '');

        if ('' === $message) {
            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'Missing required field: message',
            ];
        }

        $sanitizedInput = $this->payloadSanitizer->sanitize(['message_length' => \strlen($message)]);
        $this->logger->info(
            'Intent devreporter.notify started',
            TraceEvent::build('devreporter.intent.notify.started', 'intent_handle', self::SERVICE_NAME, 'started', $logCtx + [
                'target_app' => self::SERVICE_NAME,
                'intent' => 'devreporter.notify',
                'step_input' => $sanitizedInput['data'],
                'capture_meta' => $sanitizedInput['capture_meta'],
            ]),
        );

        $this->dispatchToOpenClaw($message, $logCtx);

        $result = [
            'status' => 'completed',
            'request_id' => $requestId,
        ];

        $this->logger->info(
            'Intent devreporter.notify completed',
            TraceEvent::build('devreporter.intent.notify.completed', 'intent_handle', self::SERVICE_NAME, 'completed', $logCtx + [
                'target_app' => self::SERVICE_NAME,
                'intent' => 'devreporter.notify',
            ]),
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $logCtx
     *
     * @return array<string, mixed>
     */
    private function handleUnknown(string $intent, string $requestId, array $logCtx): array
    {
        $result = [
            'status' => 'failed',
            'request_id' => $requestId,
            'error' => "Unknown intent: {$intent}",
        ];
        $sanitizedOutput = $this->payloadSanitizer->sanitize($result);
        $this->logger->warning(
            'Unknown intent received',
            TraceEvent::build('devreporter.intent.unknown', 'intent_handle', self::SERVICE_NAME, 'failed', $logCtx + [
                'target_app' => self::SERVICE_NAME,
                'intent' => $intent,
                'error_code' => 'unknown_intent',
                'step_output' => $sanitizedOutput['data'],
                'capture_meta' => $sanitizedOutput['capture_meta'],
            ]),
        );

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $logCtx
     */
    private function sendTelegramNotification(array $payload, array $logCtx): void
    {
        $status = (string) ($payload['status'] ?? 'completed');
        $task = $this->esc((string) ($payload['task'] ?? ''));
        $branch = $this->esc((string) ($payload['branch'] ?? ''));
        $durationSeconds = (int) ($payload['duration_seconds'] ?? 0);
        $failedAgent = isset($payload['failed_agent']) ? $this->esc((string) $payload['failed_agent']) : null;

        /** @var list<array{agent: string, status: string, duration: int}> $agentResults */
        $agentResults = isset($payload['agent_results']) && \is_array($payload['agent_results'])
            ? $payload['agent_results']
            : [];

        $durationMin = (int) round($durationSeconds / 60);

        if ('completed' === $status) {
            $message = "🟢 <b>Pipeline COMPLETED</b>\n\n";
        } else {
            $failedLabel = null !== $failedAgent ? " at {$failedAgent}" : '';
            $message = "🔴 <b>Pipeline FAILED{$failedLabel}</b>\n\n";
        }

        $message .= "📋 {$task}\n";
        $message .= "🌿 Branch: <code>{$branch}</code>\n";
        $message .= "⏱ Duration: {$durationMin} min\n";

        if ([] !== $agentResults) {
            $message .= "\n";
            foreach ($agentResults as $agentResult) {
                $agentName = $this->esc((string) ($agentResult['agent'] ?? ''));
                $agentStatus = (string) ($agentResult['status'] ?? '');
                $agentDuration = (int) ($agentResult['duration'] ?? 0);
                $agentDurationMin = (int) round($agentDuration / 60);
                $icon = 'pass' === $agentStatus ? '✅' : '❌';
                $message .= "{$icon} {$agentName} — {$agentDurationMin} min\n";
            }
        }

        if ('failed' === $status && null !== $failedAgent) {
            $message .= "\n🔄 Resume: <code>./scripts/pipeline.sh --from {$failedAgent} --branch {$branch} \"...\"</code>";
        }

        $this->dispatchToOpenClaw($message, $logCtx);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $logCtx
     */
    private function dispatchToOpenClaw(string $message, array $logCtx): void
    {
        if ('' === $this->platformCoreUrl) {
            $this->logger->debug('Platform core URL not configured, skipping Telegram notification', $logCtx);

            return;
        }

        $body = json_encode([
            'intent' => 'openclaw.send_message',
            'payload' => [
                'message' => $message,
                'format' => 'html',
            ],
        ], \JSON_THROW_ON_ERROR);

        $url = rtrim($this->platformCoreUrl, '/').'/api/v1/a2a/send-message';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: ".\strlen($body)."\r\n",
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        try {
            set_error_handler(static fn (): bool => true);
            try {
                $response = file_get_contents($url, false, $context);
            } finally {
                restore_error_handler();
            }

            if (false === $response) {
                $this->logger->warning(
                    'Telegram notification delivery failed: no response',
                    TraceEvent::build('devreporter.notify.delivery_failed', 'a2a_outbound', self::SERVICE_NAME, 'failed', $logCtx + [
                        'target_app' => 'core',
                        'error_code' => 'no_response',
                    ]),
                );
            }
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Telegram notification delivery exception',
                TraceEvent::build('devreporter.notify.delivery_exception', 'a2a_outbound', self::SERVICE_NAME, 'failed', $logCtx + [
                    'target_app' => 'core',
                    'error_code' => 'exception',
                    'error' => $e->getMessage(),
                ]),
            );
        }
    }
}
