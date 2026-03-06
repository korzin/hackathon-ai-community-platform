<?php

declare(strict_types=1);

namespace App\Logging;

final class TraceSequenceProjector
{
    /**
     * @param list<array<string, mixed>> $hits
     *
     * @return array{events: list<array<string, mixed>>, participants: list<string>}
     */
    public function project(array $hits): array
    {
        $events = [];
        $participants = [];

        foreach ($hits as $idx => $source) {
            $eventName = (string) ($source['event_name'] ?? '');
            $step = (string) ($source['step'] ?? '');
            $sourceApp = (string) ($source['source_app'] ?? $source['app_name'] ?? '');
            if ('' === $eventName || '' === $step || '' === $sourceApp) {
                continue;
            }

            $targetApp = (string) ($source['target_app'] ?? $sourceApp);
            /** @var array<string, mixed> $context */
            $context = is_array($source['context'] ?? null) ? $source['context'] : [];
            $operation = (string) ($source['tool'] ?? $source['intent'] ?? $step);
            $status = (string) ($source['status'] ?? strtolower((string) ($source['level_name'] ?? 'unknown')));
            $durationMs = (int) ($source['duration_ms'] ?? 0);

            $events[] = [
                'id' => sprintf('%s_%d', (string) ($source['@timestamp'] ?? 'evt'), $idx),
                'event_name' => $eventName,
                'step' => $step,
                'operation' => $operation,
                'from' => $sourceApp,
                'to' => $targetApp,
                'status' => $status,
                'duration_ms' => $durationMs,
                'timestamp' => (string) ($source['@timestamp'] ?? ''),
                'trace_id' => (string) ($source['trace_id'] ?? ''),
                'request_id' => (string) ($source['request_id'] ?? ''),
                'details' => [
                    'headers' => $context['request_headers'] ?? null,
                    'input' => $context['step_input'] ?? null,
                    'output' => $context['step_output'] ?? null,
                    'capture_meta' => $context['capture_meta'] ?? null,
                    'error_code' => $source['error_code'] ?? null,
                    'exception' => $source['exception'] ?? null,
                ],
            ];

            $participants[$sourceApp] = true;
            $participants[$targetApp] = true;
        }

        return [
            'events' => $events,
            'participants' => array_keys($participants),
        ];
    }
}
