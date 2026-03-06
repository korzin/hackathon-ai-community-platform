<?php

declare(strict_types=1);

namespace App\LLM;

final class LlmRequestContext
{
    public function __construct(
        public readonly string $agentName,
        public readonly string $featureName,
        public readonly string $requestId = '',
        public readonly string $traceId = '',
    ) {
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            'agent:'.$this->agentName,
            'method:'.$this->featureName,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function metadata(): array
    {
        return [
            'request_id' => $this->requestId,
            'trace_id' => $this->traceId,
            'service_name' => $this->agentName,
            'agent_name' => $this->agentName,
            'feature_name' => $this->featureName,
        ];
    }
}
