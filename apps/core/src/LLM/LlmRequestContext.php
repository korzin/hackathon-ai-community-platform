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
        public readonly string $sessionId = '',
        public readonly string $userId = '',
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
     * Langfuse-compatible metadata for LiteLLM proxy callback.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        $traceId = '' !== $this->traceId ? $this->traceId : $this->requestId;
        $sessionId = '' !== $this->sessionId ? $this->sessionId : $traceId;

        return [
            'request_id' => $this->requestId,
            'trace_id' => $traceId,
            'trace_name' => $this->agentName.'.'.$this->featureName,
            'session_id' => $sessionId,
            'generation_name' => $this->featureName,
            'tags' => $this->tags(),
            'trace_user_id' => '' !== $this->userId ? $this->userId : $this->userTag(),
            'trace_metadata' => [
                'request_id' => $this->requestId,
                'session_id' => $sessionId,
                'agent_name' => $this->agentName,
                'feature_name' => $this->featureName,
            ],
        ];
    }

    public function userTag(): string
    {
        return \sprintf(
            'service=%s;feature=%s;request_id=%s',
            $this->agentName,
            $this->featureName,
            $this->requestId,
        );
    }
}
